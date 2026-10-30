# Prime Arch

Application web de gestion d'un cabinet d'architecture en Tunisie. Le noyau est développé en PHP 8.2+ natif, selon une architecture MVC maison, MySQL 8/InnoDB, PDO et interfaces HTML/CSS/JavaScript vanilla. Seul `public/` est exposé au web.

## Prérequis

- PHP 8.2+ avec `pdo_mysql`, `mbstring`, `fileinfo`, `gd` et `zip` ; l'algorithme de hachage Argon2id doit être disponible (`password_algos()`).
- MySQL 8, InnoDB, `utf8mb4`.
- Composer 2.
- Apache avec `mod_rewrite` ou nginx + PHP-FPM.

## Installation locale

```bash
composer install
cp .env.example .env
```

Renseigner `DB_HOST`, `DB_NAME`, `DB_USER`, `DB_PASS` et `APP_URL` dans `.env`. Générer une clé d'application aléatoire (ne jamais la versionner) :

```bash
php -r "echo bin2hex(random_bytes(32)), PHP_EOL;"
```

Copier le résultat dans `APP_KEY`, puis préparer le schéma, les rôles et le premier administrateur :

```bash
php bin/migrate.php
php bin/seed.php
php bin/create-admin.php
php -S localhost:8000 -t public
```

Le script `create-admin.php` demande le nom, le courriel et un mot de passe de 12 caractères minimum ; aucun compte ni secret par défaut n'est créé. L'administrateur se connecte sur `http://localhost:8000/login`. La racine `/` ouvre le tableau de bord si une session est active, sinon la connexion. `/health` permet de vérifier l'installation.

En production, définir `APP_ENV=production`, `APP_DEBUG=false`, une clé `APP_KEY` aléatoire, `APP_URL` en HTTPS, et placer uniquement `public/` derrière le serveur web.

## Phase 1 livrée pour validation

- Connexion/déconnexion, sessions renouvelées à l'authentification, CSRF, mots de passe Argon2id et limite de tentatives.
- Rôles Administrateur, Architecte, Collaborateur, Comptable et Lecture seule, avec contrôle d'accès côté serveur.
- Réglages du cabinet et valeurs fiscales configurables ; téléversement sécurisé de logo JPEG/PNG/WebP privé.
- Référentiel bilingue préchargé des 24 gouvernorats et des 264 délégations ; table de codes postaux et validation à quatre chiffres.
- Commandes d'installation, graines idempotentes et pages de fondation.

Les codes postaux ne sont pas préchargés tant qu'une source exploitable et vérifiable n'a pas été validée. Voir `docs/DATA-SOURCES.md` et `docs/ROADMAP.md`.

## Tests

```bash
composer test
```

## Documentation

- `docs/ARCHITECTURE.md` — architecture et cycle de vie d'une requête.
- `docs/CONVENTIONS.md` — conventions de code.
- `docs/DATA-SOURCES.md` — attribution des données géographiques et limites de validation.
- `docs/DESIGN-SYSTEM.md` — tokens, composants, accessibilité et RTL.
- `docs/ROADMAP.md` — phases et critères de progression.
- `docs/MODULES.md` — format cible des modules.
