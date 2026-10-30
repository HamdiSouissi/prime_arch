# Prime Arch

Application web de gestion de cabinet d'architecture pour la **Tunisie**.
Squelette vierge : PHP 8.2+ natif (sans framework), MySQL 8, MVC maison, modules type extensions WordPress.

## Prérequis

- PHP 8.2+ (extensions `pdo_mysql`, `mbstring`, `gd` pour mPDF)
- MySQL 8 (InnoDB, utf8mb4)
- Composer 2
- Apache (mod_rewrite) ou nginx + PHP-FPM

## Installation

```bash
composer install
cp .env.example .env        # puis renseigner DB_* et APP_URL
php bin/migrate.php         # crée les tables
php -S localhost:8000 -t public   # serveur de développement
```

Ouvrir http://localhost:8000 : la page **« Installation OK »** doit s'afficher
(l'indicateur « Base de données » est vert si la connexion fonctionne).

## Déploiement

- **Seul `public/` doit être exposé.** Pointer le `DocumentRoot` (ou `root` nginx) vers `prime-arch/public`.
  Voir `nginx.conf.example`.
- **Hébergement mutualisé** sans accès au DocumentRoot : placer le projet dans `public_html/prime-arch/`.
  Le `.htaccess` racine (`Require all denied`) protège tout le projet ; `public/.htaccess` réautorise
  uniquement `public/`. Renseigner `APP_URL` avec l'URL complète vers `public/`
  (ex. `https://exemple.tn/prime-arch/public`).
- En production : `APP_ENV=production`, `APP_DEBUG=false`, générer `APP_KEY`, activer HTTPS.

## Tests

```bash
composer test
```

## Documentation

- `docs/ARCHITECTURE.md` — architecture et cycle de vie d'une requête
- `docs/MODULES.md` — spécification du format d'un module
- `docs/CONVENTIONS.md` — conventions de code

## État du squelette

Fonctionnent : front controller, routeur, config/.env, connexion PDO, moteur de vues, traductions fr/ar,
hooks, migrateur SQL. Squelettes à compléter (TODO dans le code) : authentification, modèles métier,
gestion des modules (téléversement/activation), PDF (mPDF), seeds des délégations.
