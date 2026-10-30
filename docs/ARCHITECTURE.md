# Architecture de Prime Arch

## Principes

- PHP natif, sans framework ; MVC maison ; PSR-4 (`App\` → `app/`, `Modules\` → `modules/`).
- Seul `public/` est accessible depuis le web. Config, code, stockage et `.env` restent hors de portée.
- Tout paramètre propre à la Tunisie (TVA, matricule fiscal, téléphone, phases) vit dans `config/tunisia.php`,
  jamais dans le code métier.
- Extensibilité par **modules** et **hooks** (actions / filtres).

## Cycle de vie d'une requête

1. `public/index.php` charge l'autoload Composer et lance `App\Core\Application`.
2. `Application::boot()` : lit `.env` (`Env`), charge `config/*.php` (`Config`), fuseau `Africa/Tunis`,
   démarre la session, fixe la langue (`Translator`).
3. `config/routes.php` enregistre les routes du cœur dans le `Router`.
4. `ModuleManager::boot()` charge les modules activés (leurs hooks et routes).
5. `Router::dispatch()` exécute les middlewares puis le contrôleur, qui retourne une `Response`.
6. Les vues (`app/Views`) sont rendues par `View::render()` dans le layout `layouts/app`.

## Dossiers

| Dossier | Rôle |
|---|---|
| `app/Core` | Noyau (routeur, requête, réponse, DB, vues, session, CSRF, config, i18n, migrations, modules, hooks) |
| `app/Controllers`, `Models`, `Services`, `Middleware` | Couche applicative |
| `app/Views` | Templates PHP (`layouts`, `partials`, `pages`) |
| `config/` | Configuration (app, database, routes, tunisia, modules) |
| `database/` | Migrations SQL et seeds PHP |
| `modules/` | Extensions (voir `MODULES.md`) |
| `lang/` | Traductions fr / ar |
| `storage/` | Logs, cache, uploads, PDF, sauvegardes, temporaires (protégés) |
| `bin/` | Scripts CLI |

## Hooks disponibles (initiaux)

| Hook | Type | Rôle |
|---|---|---|
| `app.booted` | action | Après enregistrement des routes ; reçoit le `Router` |
| `sidebar.items` | filtre | Ajouter/retirer des entrées du menu latéral |

## Navigation (contrainte produit)

Toute destination doit être atteignable en **3 clics maximum** depuis l'accueil.

## Feuille de route suggérée

1. Authentification, rôles, réglages du cabinet (logo, adresse, coordonnées).
2. Clients et projets (champs personnalisables).
3. Phases, suivi d'avancement, temps passé et coûts.
4. Devis, factures, contrats avec PDF (mPDF) régénérables.
5. Gestion complète des modules (téléversement ZIP, activation).
