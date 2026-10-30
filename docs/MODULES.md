# Spécification des modules

Un module est un dossier dans `modules/` (nom en **kebab-case**), installable par téléversement ZIP
et activable/désactivable depuis les réglages. Les dossiers préfixés par `_` sont des modèles ignorés.

## Structure

```
modules/<slug>/
├─ module.json     (obligatoire : manifeste)
├─ Module.php      (obligatoire : point d'entrée)
├─ migrations/     (*.sql, exécutées par ordre alphabétique)
├─ views/          (templates PHP)
├─ assets/         (CSS/JS/images du module)
├─ lang/           (fr.php, ar.php)
└─ …               (autres classes, autoload PSR-4 selon le namespace du manifeste)
```

## `module.json`

| Clé | Type | Description |
|---|---|---|
| `slug` | string | Identifiant unique, = nom du dossier (`^[a-z0-9-]+$`) |
| `name` | string | Nom affiché |
| `version` | string | SemVer |
| `description` | string | Description courte |
| `author` | string | Auteur |
| `namespace` | string | Namespace racine, ex. `Modules\\Clients` |
| `requires.php` | string | Contrainte de version PHP |
| `requires.app` | string | Version minimale de Prime Arch |
| `requires.modules` | string[] | Slugs de modules requis |
| `entry` | string | Fichier d'entrée (par défaut `Module.php`) |
| `migrations` | string | Dossier des migrations |
| `lang` | string | Dossier des traductions |
| `enabled_by_default` | bool | Activation à l'installation |

## Classe `Module`

Classe `<namespace>\Module` avec les méthodes :

- `boot(): void` — enregistrer hooks et filtres (ex. `sidebar.items`).
- `routes(Router $router): void` — déclarer les routes.
- `install(): void` / `uninstall(): void` — logique d'installation / désinstallation.

## Règles

- Tables préfixées par le slug du module (ex. `clients_contacts`).
- Ne jamais modifier le cœur : passer par les hooks.
- Traductions : clés préfixées (`<slug>.titre`).
- Aucun écrit hors de `storage/`.
- À l'activation : migrations exécutées, état enregistré dans la table `modules`.
- Un ZIP de module doit contenir un unique dossier racine avec `module.json`.

## État actuel

`ModuleManager` découvre les manifestes et démarre les modules listés dans `config/modules.php`.
TODO : téléversement ZIP (validation, extraction sécurisée), activation en base, dépendances, migrations automatiques.
