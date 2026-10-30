# Conventions de code

## PHP

- `declare(strict_types=1);` dans chaque fichier.
- PSR-12 ; PSR-4 pour l'autoload ; une classe par fichier ; `final` par défaut.
- Docblocs et commentaires **en français**. Noms de classes/méthodes/variables en anglais.
- Requêtes SQL uniquement préparées (PDO) ; jamais de concaténation de données utilisateur.
- Échapper toute sortie HTML avec `e()`. Tout formulaire POST contient `csrf_field()`.
- Montants : stockés en `DECIMAL` avec trois décimales ; dimensionner la précision au domaine (coûts cumulés et honoraires de projet en `DECIMAL(18,3)`). Utiliser `money_format_tnd()` avec les chaînes décimales PDO pour préserver le millime, sans calcul monétaire en `float`.
- Aucun paramètre tunisien en dur : utiliser `config('tunisia.…')`.

## Base de données

- Tables au pluriel snake_case, colonnes snake_case ; InnoDB, `utf8mb4_unicode_ci`.
- Clés primaires `id INT UNSIGNED AUTO_INCREMENT` ; horodatages `created_at`, `updated_at`.
- Migrations : `NNNN_description.sql`, jamais modifiées après déploiement.

## Interface

- Apple HIG : sobriété, hiérarchie claire, cibles tactiles ≥ 44 px, mode sombre automatique.
- Propriétés CSS logiques (`margin-inline`, `padding-inline`) pour le RTL (arabe).
- Navigation : **toute destination à trois clics maximum** depuis n'importe quel écran, sur ordinateur
  comme sur mobile.

## Navigation — la règle des trois clics

Le budget est une contrainte de CI, pas une intention : `tests/NavigationDepthTest.php` reconstruit
le graphe de navigation à partir du code réel et échoue si une destination du noyau dépasse trois
clics depuis `/dashboard`.

**Ce que le test lit :** les routes GET de `config/routes.php`, la vue rendue par chaque méthode de
contrôleur (la 404 de garde est ignorée), les liens des vues (`url()` et `href` absolus), la coquille
commune — menu latéral, barre d'onglets, feuille « Plus », actions rapides, palette — et les entrées
de menu déclarées par les modules.

**Comment il compte :** un lien de liste (`/clients/` suivi d'un identifiant) désigne **une** fiche,
pas ses sous-écrans ; `/clients/{id}/edit` doit donc être atteint depuis la fiche. La coquille coûte
un clic depuis n'importe quelle page authentifiée. Les routes techniques (`/api/`, `/health`,
`/media/`, `/module-assets/`, `/design/`, `*/export-csv`, `*/import-template`) ne sont pas des
destinations et restent hors budget.

**Conséquences pratiques :**

- une nouvelle page du noyau doit être liée depuis une liste, une fiche, le menu ou une action
  rapide ; sans lien entrant, le test échoue avec la mention « inatteignable » ;
- un écran ajouté à une fiche (édition, PDF, pièce jointe) se compte depuis cette fiche : deux
  niveaux de sous-écran maximum sous une liste ;
- un module déclare ses entrées de menu dans `Module.php` (`'url' => url('/permis')`) en même temps
  que ses routes ; le test vérifie que chaque entrée correspond à une route déclarée et qu'aucun
  module ne revendique deux fois le même chemin ;
- pour une exception assumée, préférer un changement de structure (passerelle contextuelle, action
  sur la fiche) à un ajout dans la liste d'exclusions.

Exécution : `composer test` ou `vendor/bin/phpunit --filter NavigationDepthTest`.

## Traductions

- Clés `domaine.clé` dans `lang/fr.php` et `lang/ar.php` ; le français est la langue de repli.

## Git

- Commits courts en français à l'impératif ; ne jamais committer `.env` ni `vendor/`.
