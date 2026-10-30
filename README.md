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

## Phases livrées pour validation

### Phase 1 — Fondation

- Connexion/déconnexion, sessions renouvelées à l'authentification, CSRF, mots de passe Argon2id et limite de tentatives.
- Rôles Administrateur, Architecte, Collaborateur, Comptable et Lecture seule, avec contrôle d'accès côté serveur.
- Réglages du cabinet et valeurs fiscales configurables ; téléversement sécurisé de logo JPEG/PNG/WebP privé.
- Référentiel bilingue des 24 gouvernorats et des 264 délégations ; 4 853 localités/codes postaux importés depuis un jeu communautaire sous licence MIT (2015), avec conservation des cas de délégation ambigus et validation à quatre chiffres.
- Commandes d'installation, graines idempotentes et pages de fondation.

### Phase 2 — Clients et projets

- Fiches clients, contacts, adresses, projets associés, phases configurables, recherche, filtres, champs personnalisés typés et pages de détail.

### Phase 3 — Temps, coûts et avancement

- Saisies de temps par utilisateur/projet/phase, taux horaires historisés, budgets d'heures et avancement pondéré.
- Coûts et indicateurs de rentabilité calculés en `DECIMAL` à trois décimales, avec des permissions financières distinctes des permissions `time.*`.
- Détails de fonctionnement et mise en service dans `docs/TIME-COSTS.md`.

### Phase 4 — Documents et PDF

- Devis, contrats, factures, modèles, instantanés immuables des documents émis, génération PDF bilingue, paiements, acomptes et avoirs.

### Phase 5 — Modules

- Installation ZIP réservée aux administrateurs, extraction contrôlée dans `storage/modules/`, manifeste, dépendances, migrations à l’activation et hooks.
- Les modules sont du code PHP non sandboxé : installer uniquement des archives de confiance. La désactivation conserve le code et les données.
- Format, limites d’archive et cycle de vie dans `docs/MODULES.md`.

### Phase 6 — Finition et exploitation (en cours de validation)

- Le clair reste le rendu normal ; le sombre suit automatiquement la préférence `prefers-color-scheme: dark` du système.
- Journal d’audit administrateur, sauvegarde ZIP privée en CLI, vérification d’intégrité et restauration limitée à une base vide.
- Marge supérieure PDF harmonisée à 48 mm pour les régénérations de tous types de documents ; documents associés listés sur la fiche client selon les permissions.
- Images JPEG/PNG/WebP téléversées par projet, stockées hors webroot et servies après contrôle d’accès ; galerie pour les plans 2D et visuels de référence.
- Permissions des rôles intégrés explicites et moindres privilèges ; les réglages de cabinet contenant des données sensibles sont réservés à l’administrateur.
- Interface bilingue **français / arabe** : sélecteur de langue dans l’en-tête et sur la connexion, direction `rtl` automatique, libellés JavaScript traduits et rôles localisés.
- Les tests de sécurité et de politique de permissions sont ajoutés, mais doivent encore être exécutés avec PHP/PHPUnit/MySQL. Aucune règle fiscale ou juridique tunisienne n’est déclarée conforme sans validation de l’expert-comptable et de l’OAT.
- Procédures d’exploitation et limites : `docs/OPERATIONS.md`.

Avant utilisation de la phase 3, les migrations `0006` à `0008` doivent avoir été exécutées avec succès ; les rôles intégrés doivent ensuite être resynchronisés avec `php bin/seed.php`. L’état d’exécution des migrations ne peut pas être déduit du code seul.

Les suggestions postales viennent d'un jeu communautaire ancien et non officiel ; elles ne garantissent ni l'exhaustivité ni l'actualité des données. Vérifier les codes auprès de La Poste tunisienne avant tout usage officiel. Les sources, l'attribution MIT et les cas non rattachés à une délégation sont détaillés dans `docs/DATA-SOURCES.md`.

## Langues de l'interface

L'interface est bilingue **français / arabe**. Le sélecteur de langue est disponible dans l'en-tête de
l'application et sur l'écran de connexion ; le choix est conservé en session et la direction du document
(`ltr` / `rtl`) suit la langue active. Les traductions sont dans `lang/fr.php` et `lang/ar.php` (mêmes clés
obligatoires) ; la langue par défaut se règle avec `APP_LOCALE`. Les libellés dynamiques du JavaScript sont
fournis par le bloc `#app-i18n` (`app/Views/partials/i18n.php`).

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
- `docs/TIME-COSTS.md` — taux historisés, calculs financiers et permissions de la phase 3.
- `docs/MODULES.md` — format, installation et sécurité des modules.
- `docs/OPERATIONS.md` — journal d’audit, sauvegarde, restauration et validation opérationnelle.
