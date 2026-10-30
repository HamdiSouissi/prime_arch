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
- Navigation : toute destination à 3 clics maximum.

## Traductions

- Clés `domaine.clé` dans `lang/fr.php` et `lang/ar.php` ; le français est la langue de repli.

## Git

- Commits courts en français à l'impératif ; ne jamais committer `.env` ni `vendor/`.
