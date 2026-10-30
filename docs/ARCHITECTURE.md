# Architecture de Prime Arch

## Principes

- PHP natif, sans framework ; MVC maison ; PSR-4 (`App\` → `app/`, `Modules\` dynamique vers le dossier privé du module installé; `modules/` reste réservé aux modules livrés avec le code).
- Seul `public/` est accessible depuis le web. Config, code, stockage et `.env` restent hors de portée.
- Tout paramètre propre à la Tunisie (TVA, matricule fiscal, téléphone, phases) vit dans `config/tunisia.php`,
  jamais dans le code métier.
- Extensibilité par **modules** et **hooks** (actions / filtres).

## Cycle de vie d'une requête

1. `public/index.php` charge l'autoload Composer et lance `App\Core\Application`.
2. `Application::boot()` : lit `.env` (`Env`), charge `config/*.php` (`Config`), fuseau `Africa/Tunis`,
   démarre la session, fixe la langue (`Translator`).
3. `config/routes.php` enregistre les routes du cœur dans le `Router` : connexion, déconnexion, tableau de bord, clients et contacts, projets et phases, champs personnalisés, réglages, administration des utilisateurs, journal d’audit administrateur et référentiel géographique.
4. `ModuleManager::boot()` lit les modules actifs dans la table `modules`, vérifie manifestes, compatibilités et dépendances, puis démarre leurs hooks, routes et traductions en ordre topologique.
5. `Router::dispatch()` exécute les middlewares d'authentification, de rôle et de CSRF avant le contrôleur, qui retourne une `Response`.
6. Les vues (`app/Views`) sont rendues par `View::render()` dans `layouts/app` ou `layouts/auth`.

Les modules téléversés et leurs vues, migrations et ressources résident sous `storage/modules/<slug>/`; les vues peuvent utiliser `View::renderModule()` et les ressources passent par une route authentifiée. Le noyau n’extrait jamais de ZIP hors de `storage/`. Le code PHP d’un module n’est pas sandboxé et dispose des droits du processus PHP : seules des archives de confiance doivent être installées.

## Authentification et autorisations

- Aucun formulaire d'inscription public : le premier administrateur est créé avec `php bin/create-admin.php` après les migrations et graines.
- Les mots de passe sont stockés avec Argon2id. Les tentatives échouées sont limitées par une empreinte HMAC du courriel et de l'adresse IP ; ces valeurs ne sont pas persistées en clair.
- L'identifiant de session est renouvelé après connexion ; les cookies sont `HttpOnly`, `SameSite=Lax` et `Secure` sous HTTPS. La déconnexion détruit les données et le cookie de session.
- Les routes POST privées requièrent un jeton CSRF. `AuthMiddleware` impose une session, et `AdminMiddleware` exige la permission `users.manage`.
- Les permissions sont chargées depuis le rôle courant à chaque requête afin que la désactivation d'un compte prenne effet immédiatement.
- Les rôles intégrés hors administrateur énumèrent leurs droits au lieu d’utiliser des jokers de domaine ; le journal `audit_logs` n’enregistre pas les champs de formulaire ni l’adresse IP brute.
- Les commandes de sauvegarde/restauration sont limitées à la CLI ; une restauration exige une base vide et des dossiers privés de stockage vides. Voir `docs/OPERATIONS.md`.
- Le téléversement du logo n'accepte que JPEG, PNG et WebP, vérifie le MIME réel et les dimensions, génère un nom aléatoire et stocke le fichier hors de `public/`.

## Initialisation des données

`php bin/migrate.php` applique les migrations versionnées. `php bin/seed.php` synchronise les rôles intégrés, les 24 gouvernorats, les 264 délégations, le jeu postal fourni et les phases de projet initiales définies dans `config/tunisia.php`, de façon répétable ; après la migration de la phase 3, il initialise aussi à parts égales les poids des phases de projets existants qui n'en ont pas encore. Les données postales sont des suggestions communautaires anciennes et non officielles ; les cas sans rapprochement sûr restent rattachés au seul gouvernorat (voir `docs/DATA-SOURCES.md`). Les repositories utilisent PDO et des requêtes préparées ; les valeurs de champs personnalisés des clients et projets sont stockées dans des colonnes JSON et validées à partir des définitions actives.

## Dossiers

| Dossier | Rôle |
|---|---|
| `app/Core` | Noyau (routeur, requête, réponse, DB, vues, session, CSRF, config, i18n, migrations, modules, hooks) |
| `app/Controllers`, `Models`, `Services`, `Middleware` | Couche applicative |
| `app/Views` | Templates PHP (`layouts`, `partials`, `pages`) |
| `config/` | Configuration (app, database, routes, tunisia); `modules.php` ne porte plus l’état d’activation. |
| `database/` | Migrations SQL et seeds PHP |
| `modules/` | Exemples et modules livrés avec le code; les archives téléversées sont extraites dans `storage/modules/` (voir `MODULES.md`). |
| `lang/` | Traductions fr / ar |
| `storage/` | Logs, cache, uploads, PDF, sauvegardes, temporaires et modules extraits (protégés) |
| `bin/` | Scripts CLI |

## Hooks disponibles (initiaux)

| Hook | Type | Rôle |
|---|---|---|
| `app.booted` | action | Après enregistrement des routes ; reçoit le `Router` |
| `sidebar.items` | filtre | Ajouter/retirer des entrées du menu latéral |
| `module.installed`, `module.activated`, `module.deactivated` | actions | Cycle de vie du registre des modules |
| `module.booting`, `module.booted` | actions | Démarrage des modules actifs |

## Navigation (contrainte produit)

Toute destination doit être atteignable en **3 clics maximum** depuis l'accueil.

## Progression par phases

Le détail des objectifs, fonctionnalités et conditions de validation se trouve dans `docs/ROADMAP.md`. Aucune phase métier suivante ne commence sans validation explicite de la phase livrée.
