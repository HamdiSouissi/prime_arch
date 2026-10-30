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
3. `config/routes.php` enregistre les routes du cœur dans le `Router` : connexion, déconnexion, tableau de bord, réglages, administration des utilisateurs et référentiel géographique.
4. `ModuleManager::boot()` charge les modules activés (leurs hooks et routes).
5. `Router::dispatch()` exécute les middlewares d'authentification, de rôle et de CSRF avant le contrôleur, qui retourne une `Response`.
6. Les vues (`app/Views`) sont rendues par `View::render()` dans `layouts/app` ou `layouts/auth`.

## Authentification et autorisations

- Aucun formulaire d'inscription public : le premier administrateur est créé avec `php bin/create-admin.php` après les migrations et graines.
- Les mots de passe sont stockés avec Argon2id. Les tentatives échouées sont limitées par une empreinte HMAC du courriel et de l'adresse IP ; ces valeurs ne sont pas persistées en clair.
- L'identifiant de session est renouvelé après connexion ; les cookies sont `HttpOnly`, `SameSite=Lax` et `Secure` sous HTTPS. La déconnexion détruit les données et le cookie de session.
- Les routes POST privées requièrent un jeton CSRF. `AuthMiddleware` impose une session, et `AdminMiddleware` exige la permission `users.manage`.
- Les permissions sont chargées depuis le rôle courant à chaque requête afin que la désactivation d'un compte prenne effet immédiatement.
- Le téléversement du logo n'accepte que JPEG, PNG et WebP, vérifie le MIME réel et les dimensions, génère un nom aléatoire et stocke le fichier hors de `public/`.

## Initialisation des données

`php bin/migrate.php` applique les migrations versionnées. `php bin/seed.php` insère les rôles et le référentiel des 24 gouvernorats / 264 délégations de façon répétable. La table `postal_codes` est prête, mais son jeu de données est laissé vide en attendant une source vérifiable (voir `docs/DATA-SOURCES.md`).

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

## Progression par phases

Le détail des objectifs, fonctionnalités et conditions de validation se trouve dans `docs/ROADMAP.md`. Aucune phase métier suivante ne commence sans validation explicite de la phase livrée.
