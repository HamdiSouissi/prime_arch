# Modules et extensions

## Cycle de vie et emplacement

Un module est livré dans une archive ZIP dont l’unique dossier racine porte son slug :

```text
example-module.zip
└── example-module/
    ├── module.json
    ├── Module.php
    ├── migrations/
    ├── views/
    ├── assets/
    └── lang/
```

Le téléversement, disponible uniquement dans **Réglages → Modules** pour les administrateurs (`users.manage`), valide le ZIP puis extrait son contenu sous `storage/modules/<slug>/`. Le code d’un module installé n’est donc pas placé dans `public/`; `storage/` et `storage/modules/` refusent également l’accès direct sous Apache. Le noyau n’écrit pas les modules téléversés ailleurs que dans `storage/`.

`ModuleManager` n’active plus les extensions à partir de `config/modules.php` : l’état installé/activé est lu depuis la table `modules`. Les dossiers modèle préfixés par `_` ne sont pas des modules installés. `modules/_example-module/` est un exemple de contenu à empaqueter, pas un module actif.

La désactivation arrête le chargement des hooks, routes et traductions aux requêtes suivantes. Elle ne désinstalle pas le code, ne supprime pas les tables, ne fait pas de rollback et ne détruit aucune donnée. Aucune action de désinstallation n’est exposée. Le hook `uninstall()` d’un module n’est donc pas appelé à la désactivation. Les auteurs doivent placer les données générées sous `storage/` (de préférence dans un sous-dossier propre au module); le noyau ne peut pas imposer cette règle au code PHP d’une extension.

## Manifeste `module.json`

Exemple minimal :

```json
{
  "slug": "example-module",
  "name": "Module d'exemple",
  "version": "0.1.0",
  "description": "Exemple de module Prime Arch.",
  "author": "Équipe",
  "namespace": "Modules\\ExampleModule",
  "requires": {
    "php": ">=8.2",
    "app": ">=0.1.0",
    "modules": []
  },
  "entry": "Module.php",
  "migrations": "migrations",
  "lang": "lang",
  "enabled_by_default": false
}
```

| Clé | Règle |
|---|---|
| `slug` | Obligatoire, unique, minuscules ASCII/chiffres/tirets, 1–100 caractères, sans tiret initial/final; identique au dossier racine du ZIP. |
| `name` | Obligatoire, chaîne non vide (150 caractères maximum). |
| `version` | Obligatoire, version SemVer (20 caractères maximum). |
| `description`, `author` | Chaînes non vides, respectivement 500 et 150 caractères maximum. |
| `namespace` | Obligatoire, commence par `Modules\`, segments StudlyCase; la classe d’entrée est `<namespace>\Module`. |
| `requires.php`, `requires.app` | Contraintes de versions `>=`, `>`, `<=`, `<`, `=`, `^` ou `~`; plusieurs comparateurs peuvent être séparés par une virgule. Contrôlées à l’installation, à l’activation et au démarrage. |
| `requires.modules` | Liste de slugs installés et activés requis; les cycles et dépendances manquantes sont refusés. L’ordre de démarrage respecte les dépendances. |
| `entry` | Chemin PHP relatif sûr, par défaut `Module.php`; le fichier doit exister dans le ZIP. |
| `migrations`, `lang` | Noms de répertoire simples, par défaut `migrations` et `lang`. |
| `enabled_by_default` | Booléen; `true` tente une activation après l’installation. En cas d’échec, le module reste installé mais désactivé. |

Les champs inconnus sont ignorés et seuls les champs normalisés sont enregistrés comme instantané de manifeste dans `modules.manifest_json`. Un changement ultérieur du manifeste sur disque est refusé au démarrage. La mise à jour d’un module déjà installé n’est pas proposée par cette version; ne remplacez pas manuellement ses fichiers.

## Classe d’entrée, vues, ressources et hooks

La classe `<namespace>\Module` peut déclarer les méthodes suivantes :

```php
public function boot(): void;
public function routes(Router $router): void;
public function install(): void;
public function uninstall(): void;
```

`boot()` et `routes()` sont appelés à chaque requête pour les modules activés. `install()` est appelé au plus une fois après le succès des migrations, lors de la première activation; rendez-le idempotent, car une panne entre son exécution et l’enregistrement du marqueur peut entraîner une nouvelle tentative. `uninstall()` est réservé à une éventuelle fonctionnalité de désinstallation ultérieure.

L’autoload PSR-4 est ajouté à partir du `namespace` du manifeste. Une vue privée se rend avec `View::renderModule('example-module', 'home', $viewData)`, depuis `views/home.php`. Les traductions `fr.php` et `ar.php` du dossier `lang/` sont chargées lors du démarrage; préfixez leurs clés avec le slug (par exemple `example-module.titre`). Les ressources CSS/JS/images autorisées sont accessibles aux utilisateurs authentifiés via `/module-assets/<slug>?file=css/module.css`.

### Passerelles depuis les fiches du noyau

Trois filtres permettent à un module d'apparaître directement sur une fiche du noyau, en un clic.
Ils reçoivent une liste de sections et la fiche concernée, et renvoient la liste complète :

| Filtre | Contexte passé | Fiche |
|---|---|---|
| `project.detail.sections` | `$project` (ligne `projects`) | `/projects/{id}` |
| `client.detail.sections` | `$client` (ligne `clients`) | `/clients/{id}` |
| `document.detail.actions` | `$document` (`document_type`, `status`, montants) | `/documents/{id}` |

Chaque entrée ajoutée est un tableau de six clés, dont deux obligatoires :

```php
$sections[] = [
    'key' => 'mon-module',            // identifiant stable, unique sur la fiche
    'label' => __('mon-module.title'), // libellé traduit (fr + ar)
    'url' => url('/mon-module/' . (int) $project['id']),
    'meta' => __('mon-module.context_meta', ['count' => (string) $count]), // facultatif
    'icon' => 'folder',               // facultatif, nom présent dans public/assets/img/icons.svg
    'tone' => 'info',                 // facultatif : info, success, warning, error, neutral
];
```

Règles :

- **Aucune permission nouvelle** : le module vérifie les droits du noyau (`projects.view`, `clients.view`,
  `documents.view`/`documents.manage`) et se retire lui-même quand l'utilisateur n'y a pas accès.
- **Le noyau ne requête rien** pour les modules : il applique le filtre et rend le résultat tel quel.
  Il n'entoure pas les erreurs des callbacks ; un module doit rester défensif (identifiant absent, etc.).
- **Dégradation silencieuse** : module absent ou désactivé ⇒ la fiche est inchangée, sans espace vide.
- **Ordre** : l'ordre d'ajout est conservé, les entrées du noyau passées en entrée restent en tête.

### Recherche globale

Le filtre `search.results` reçoit les groupes déjà trouvés par le noyau et le terme saisi
(`$groups`, `$query`) ; le module renvoie la liste complète, augmentée de ses propres groupes :

```php
HookManager::addFilter('search.results', static function (array $groups, string $query = ''): array {
    if (!Auth::can('projects.view') || mb_strlen(trim($query)) < 2) {
        return $groups;
    }
    $items = [];
    foreach ((new MonRepository())->search($query) as $row) {
        $items[] = [
            'title' => (string) $row['reference'],
            'meta' => $row['commune'] . ' · ' . $row['project_name'],
            'url' => url('/mon-module/' . (int) $row['id']),
            'icon' => 'shield',
        ];
    }
    if ($items !== []) {
        $groups[] = ['key' => 'mon-module', 'label' => __('mon-module.title'), 'items' => $items];
    }

    return $groups;
});
```

Règles :

- **Huit résultats par groupe** (`SearchRepository::LIMIT`), et un titre court qui tient sur une ligne.
- **Échappement partagé** : `SearchRepository::likeTerm($query)` neutralise `%`, `_` et `\`
  (`likeTerm($query, true)` pour un préfixe). Ne jamais concaténer le terme dans le SQL.
- **Texte brut uniquement** : la palette construit ses éléments par `textContent`, jamais par `innerHTML`.
- **Défensif** : entourer la requête d'un `try/catch (Throwable)` et renvoyer `$groups` inchangé
  si la table du module manque, sinon une base incomplète casserait la recherche de tout le cabinet.

Les extensions utilisent `HookManager` pour enregistrer des filtres (par exemple `sidebar.items`) et des actions/événements. Les événements émis par le noyau comprennent : `module.installed`, `module.activated`, `module.deactivated`, `module.booting` et `module.booted`. Les callbacks de hook sont du code PHP du module et s’exécutent avec les droits du processus PHP.

### Entrée de menu et sections

Une entrée ajoutée par `sidebar.items` est un tableau :

```php
$entry = [
    'label' => __('mon-module.title'),
    'url' => url('/mon-module'),
    'icon' => 'chart',              // nom du sprite public/assets/img/icons.svg
    'key' => 'mon-module',          // identifiant stable, sert à `aria-current`
    'section' => 'production',      // facultatif : pilotage, production, finance, conformite, administration
];
```

- **Section facultative** : sans `section` (ou avec une valeur inconnue), l'entrée rejoint « Modules externes ».
  Aucun module existant ne casse, et le séparateur historique est conservé.
- **Icône unique** : le menu de 761 à 1 100 px n'affiche que des icônes ; deux modules qui partagent la même icône
  deviennent indiscernables. Une icône par module, choisie dans le sprite.
- **Une seule déclaration** : `sidebar.items` n'est appliqué qu'une fois par page ; le menu latéral, la barre
  d'onglets mobile et la feuille « Plus » en dérivent. Ajouter l'entrée dans `boot()` suffit.

## Migrations et dépendances

Les fichiers `migrations/*.sql` sont appliqués par ordre alphabétique lors de la première activation, avant le hook `install()`. Le `Migrator` suit chaque fichier dans la table centrale `migrations`, en utilisant son chemin relatif à la racine de l’application. Les migrations SQL ne sont pas annulées lorsqu’un module est désactivé; elles ne doivent pas supprimer les données à la désactivation. Les fichiers de migration ne doivent pas utiliser `DELIMITER` ni des procédures stockées.

Un module requis doit être installé, actif et compatible. Il doit être activé avant son dépendant; un module utilisé par un autre module actif ne peut pas être désactivé. Si une activation échoue au cours d’une migration, le module reste inactif et la migration échouée sera réessayée; les migrations MySQL contenant du DDL peuvent avoir produit des effets avant l’échec.

Préfixez les tables propres au module par son slug (ou un identifiant court cohérent), par exemple `example_module_items`. Ne modifiez pas les tables du noyau; passez par les hooks et les API exposées.

## Contrôles de sécurité de l’archive

L’installateur vérifie notamment :

- présence et validité de `module.json`, cohérence entre le slug et l’unique dossier racine;
- chemins relatifs ASCII sûrs, absence de `..`, de chemins absolus, de chemins ambigus/dupliqués et de collisions de casse;
- rejet des liens symboliques, fichiers spéciaux et entrées ZIP chiffrées;
- limites de 25 Mo pour l’archive, 50 Mo décompressés, 20 Mo par fichier, 500 entrées, et ratio de compression maximal de 200 pour les fichiers volumineux;
- extraction manuelle dans un dossier temporaire privé, permissions restrictives, validation du manifeste extrait puis renommage atomique vers `storage/modules/<slug>`;
- enregistrement de l’empreinte SHA-256 de l’archive, du manifeste et de l’administrateur qui l’a installée.

Les archives ne sont jamais extraites directement par `ZipArchive::extractTo()`. Les migrations sont validées et appliquées uniquement à l’activation. Un dossier incomplet ou une archive invalide n’est pas conservé comme installation.

**Limite importante : le code PHP d’un module n’est pas sandboxé.** Un module activé peut exécuter des requêtes SQL arbitraires et effectuer toute opération permise au processus PHP, y compris écrire hors de `storage/`. La validation du ZIP réduit les risques d’extraction, mais ne prouve ni l’identité de l’éditeur ni l’innocuité du code. Installez uniquement des archives de confiance et vérifiez leur provenance avant de confirmer l’exécution du code.

## État de l’implémentation

Le registre, l’installation ZIP, l’extraction isolée, la vérification des manifestes, contraintes et dépendances, les migrations d’activation, les hooks/événements et l’interface administrateur sont implémentés. PHP 8.2+ et l’extension `zip` sont requis. Les essais d’intégration doivent être exécutés sur un environnement MySQL configuré avant la mise en production.
