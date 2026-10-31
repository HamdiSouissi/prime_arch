# Mise en ligne sur IONOS — suite.primevisionsolutions.fr

Guide de déploiement de Prime Architecture sur un hébergement web IONOS (Linux, Apache),
sous-domaine **`suite.primevisionsolutions.fr`**.

Il décrit la configuration exacte de **ce** dépôt : `public/` est la seule racine exposée,
`.env` porte la configuration, `APP_KEY` protège les empreintes de connexion, les scripts
`bin/migrate.php`, `bin/seed.php` et `bin/create-admin.php` préparent la base.

> **État de référence** : branche `arena/b53b7124-prime-arch`. Les commandes ci-dessous sont
> à lancer depuis la racine du projet (`/home/.../prime_arch` en local, dossier de l'application
> sur le serveur).

---

## 0. Vue d'ensemble

| # | Étape | Où | Durée |
|---|---|---|---|
| 1 | PHP 8.2+ sur le sous-domaine, extensions, `php.ini` | panneau IONOS | 10 min |
| 2 | Base de données MySQL/MariaDB + identifiants | panneau IONOS | 10 min |
| 3 | Préparer le paquet (`composer install --no-dev`, `bin/release.php`) | local | 15 min |
| 4 | Téléverser et pointer le sous-domaine sur `public/` | SFTP + panneau | 20 min |
| 5 | Créer `.env` en production | SFTP | 10 min |
| 6 | Migrations, référentiels, premier administrateur | SSH (ou cron) | 10 min |
| 7 | Droits des dossiers + contrôle `bin/preflight.php` | SSH | 5 min |
| 8 | HTTPS et redirection forcée | panneau + `.htaccess` | 10 min |
| 9 | Recette de mise en ligne (connexion, PDF, arabe, sauvegarde) | navigateur | 20 min |
| 10 | Sauvegardes planifiées | panneau (cron) | 10 min |

**Trois règles à ne jamais enfreindre**

1. Le fichier `.env` n'est **jamais** accessible en HTTP et n'est **jamais** écrasé lors d'une mise à jour.
2. La racine web du sous-domaine est **`public/`**, jamais la racine du projet.
3. `APP_DEBUG` reste à `false` et `APP_ENV` à `production`.

---

## 1. Choisir la disposition des fichiers

### Disposition A — recommandée (racine web = `public/`)

```
<racine du webspace IONOS>/
└── prime-arch/                 ← application complète (hors racine web)
    ├── app/ bin/ config/ database/ docs/ lang/ modules/ public/ storage/ vendor/
    ├── .env                    ← secrets, hors racine web donc inatteignable
    └── public/                 ← RACINE WEB DU SOUS-DOMAINE
        ├── index.php  .htaccess  assets/
```

Rien à modifier dans le code, `.env` et `storage/` sont hors d'atteinte du web, et le
`.htaccess` de `public/` fait déjà tout le travail (routeur unique + blocage des fichiers
sensibles).

### Disposition B — repli (racine web figée sur un dossier existant)

À n'utiliser que si le panneau IONOS refuse de pointer le sous-domaine sur un sous-dossier
(`prime-arch/public`). Voir l'annexe A : elle ajoute une amorce `index.php` et un `.htaccess`
de 10 lignes à la racine web.

**Étape de décision** : ouvrez IONOS → *Domaines & SSL* → `primevisionsolutions.fr` →
onglet *Sous-domaines* → roue dentée de `suite` → type d'utilisation *Espace web*. Si un
sélecteur de dossier s'ouvre, c'est la disposition A.

---

## 2. Étape 1 — PHP du sous-domaine

1. Panneau IONOS → **Hébergement** → tuile **PHP** → *Gérer*.
2. Repérez la ligne `suite.primevisionsolutions.fr`, cliquez dessus.
3. Choisissez **PHP 8.2** ou **8.3** (jamais en dessous de 8.2 : le code utilise les
   propriétés typées en lecture, les énumérations et `declare(strict_types=1)`).
4. Vérifiez, sur la même page, que ces extensions sont **activées** :

   | Extension | Pourquoi |
   |---|---|
   | `pdo_mysql` | connexion base |
   | `mbstring` | UTF-8, arabe, longueurs |
   | `fileinfo` | contrôle réel des images et archives envoyées |
   | `zip` | installation de modules, sauvegardes |
   | `gd` | dimensions des images |
   | `json` | colonnes JSON (champs personnalisés, permissions) |
   | `openssl` | HTTPS, aléas cryptographiques |
   | Argon2id | hachage des mots de passe — vérifié par `bin/preflight.php` |

   Sur certains contrats, les extensions se règlent dans *PHP* → *Extensions* ; si Argon2id
   manque, changez de version de PHP (8.3 le propose toujours) avant d'aller plus loin.

5. **Réglages `php.ini`** : déposez un fichier `php.ini` **dans la racine web**
   (`prime-arch/public/` en disposition A, la racine du sous-domaine en disposition B) :

   ```ini
   upload_max_filesize = 24M
   post_max_size = 28M
   memory_limit = 256M
   max_execution_time = 120
   ```

   C'est le minimum pour l'envoi d'images de projet (20 Mio) et la génération de PDF.
   Contrats souscrits avant juillet 2026 : le fichier ne s'applique qu'à son propre dossier et
   il faut relancer Apache depuis SSH (`apachectl restart`). Sans SSH, ignorez cette étape :
   les valeurs par défaut suffisent pour tout sauf les très grosses images.

**Point de contrôle** : la page *PHP* affiche 8.2 ou 8.3 pour `suite.primevisionsolutions.fr`.

---

## 3. Étape 2 — Base de données

1. Panneau IONOS → **Hébergement** → tuile **Bases de données** → *Créer une base de données*.
2. Type : **MySQL 8** si proposé, sinon **MariaDB** (compatible ; les colonnes `JSON` y sont
   acceptées, l'application n'utilise ni procédure stockée ni `DELIMITER`).
3. Choisissez un mot de passe fort et notez-le immédiatement : il ne sera plus réaffiché.
4. Notez les quatre valeurs affichées (elles n'ont rien à voir avec un serveur local) :

   | Champ `.env` | Exemple IONOS | Remarque |
   |---|---|---|
   | `DB_HOST` | `db123456789.hosting-data.io` | **jamais `127.0.0.1` ni `localhost`** |
   | `DB_NAME` | `dbs123456789` | nom imposé par IONOS |
   | `DB_USER` | `dbo123456789` | utilisateur imposé par IONOS |
   | `DB_PASS` | *(votre choix)* | à conserver |
   | `DB_PORT` | `3306` | ne pas changer |

5. La base reste **vide** : les tables seront créées à l'étape 6. Elle n'est joignable que
   depuis les serveurs IONOS — c'est normal, et c'est une protection.

**Point de contrôle** : *Ouvrir* (phpMyAdmin) fonctionne et la base ne contient aucune table.

---

## 4. Étape 3 — Préparer le paquet en local

### 4.1 Dépendances

```bash
composer install --no-dev --optimize-autoloader
```

- `--no-dev` écarte PHPUnit (inutile en ligne).
- `composer.lock` est versionné : les versions sont donc reproductibles.
- `vendor/` contient mPDF (~15 Mo), qui produit les PDF.

Si vous n'avez ni PHP 8.2 ni Composer en local, sautez cette étape et installez les
dépendances sur le serveur (annexe B, SSH requis).

### 4.2 Construire l'archive

```bash
php bin/release.php --dry-run   # résumé : ce qui part, ce qui reste
php bin/release.php             # écrit storage/tmp/prime-arch-AAAAMMJJ-HHMMSS.zip
```

Le script n'embarque **ni** `.git`, **ni** `tests/`, **ni** `phpunit.xml`, **ni** `.env`,
**ni** les contenus générés (`storage/logs`, `storage/cache`, `storage/uploads`,
`storage/pdf`, `storage/backups`, `storage/modules`, `storage/tmp` — seuls leurs `.htaccess`
et `.gitkeep` sont conservés). Il inclut `vendor/` s'il existe, et affiche le SHA-256 de
l'archive.

Options : `--list` (liste chaque fichier), `--no-vendor` (envoi séparé de `vendor/`),
`--with-vendor`, `--output <chemin.zip>`.

### 4.3 Sans le script (envoi direct)

Se connecter en SFTP et envoyer, à la racine du dossier applicatif : `app/`, `bin/`,
`config/`, `database/`, `lang/`, `modules/`, `public/`, `storage/`, `vendor/`, `docs/`,
`composer.json`, `composer.lock`, `.env.example`, `.htaccess`, `README.md`.

**Ne pas envoyer** : `.git/`, `tests/`, `phpunit.xml`, `.env` (créé sur le serveur),
`prime-arch-design.zip`, `node_modules/`.

**Point de contrôle** : `php bin/release.php --dry-run` annonce environ 400 fichiers et
signale `vendor/ : inclus`.

---

## 5. Étape 4 — Téléverser et brancher le sous-domaine

### 5.1 Se connecter

Panneau IONOS → **Hébergement** → tuile **SFTP & SSH** → votre compte principal (le compte
SFTP principal est aussi le compte SSH).

| Réglage | Valeur |
|---|---|
| Protocole | **SFTP** (port 22) |
| Hôte | `accessXXXXXXXX.webspace-data.io` (affiché dans le panneau) |
| Utilisateur | votre identifiant principal (`u12345678` ou similaire) |
| Mot de passe | celui du compte FTP/SFTP principal |

Les comptes SFTP secondaires n'ont **pas** d'accès SSH : utilisez le compte principal.

### 5.2 Déposer les fichiers

1. Créez le dossier `prime-arch` à la racine du webspace.
2. Envoyez-y l'archive, puis décompressez-la (SFTP : *décompresser* dans FileZilla ; sinon
   décompressez en local et envoyez l'arborescence).
3. Vérifiez que le serveur contient bien `prime-arch/public/index.php` et
   `prime-arch/vendor/autoload.php`.

### 5.3 Pointer le sous-domaine

IONOS → *Domaines & SSL* → `primevisionsolutions.fr` → *Sous-domaines* → roue dentée de
`suite` → type d'utilisation **Espace web** → dossier cible **`prime-arch/public`** →
*Enregistrer*.

Le DNS est déjà en place (le sous-domaine résout), seuls le dossier cible et le certificat
restent à régler. Si le sélecteur n'offre pas les sous-dossiers : disposition B (annexe A).

**Point de contrôle** : `http://suite.primevisionsolutions.fr/health` répond une page
« installation » (le HTTPS viendra à l'étape 8). Une erreur 500 à ce stade est normale si
`.env` n'existe pas encore — étape 5.

---

## 6. Étape 5 — Créer le fichier `.env`

À la racine de l'application (à côté de `composer.json`), créez `.env` :

```dotenv
APP_NAME="Prime Architecture"
APP_ENV=production
APP_DEBUG=false
APP_URL=https://suite.primevisionsolutions.fr
APP_LOCALE=fr
APP_KEY=

DB_HOST=db123456789.hosting-data.io
DB_PORT=3306
DB_NAME=dbs123456789
DB_USER=dbo123456789
DB_PASS="votre-mot-de-passe-de-base"
DB_CHARSET=utf8mb4
DB_COLLATION=utf8mb4_unicode_ci

SESSION_NAME=primearch_session
SESSION_LIFETIME=7200
```

Générer la clé (32 octets aléatoires, en hexadécimal) et la coller dans `APP_KEY` :

```bash
php -r "echo bin2hex(random_bytes(32)), PHP_EOL;"
```

Sans PHP en local, sur le serveur : `openssl rand -hex 32`, ou n'importe quel générateur
cryptographique. Ne réutilisez jamais la clé d'une autre installation, ne la versionnez pas.

Règles de saisie (le lecteur `.env` du projet est volontairement simple) :

- **pas de barre oblique finale** dans `APP_URL` (`…fr`, pas `…fr/`) ;
- `APP_URL` décrit la racine publique : jamais `…/public` ;
- une valeur contenant un espace, un `#` ou un guillemet doit être **entre guillemets** :
  `DB_PASS="a b#c"` ;
- une ligne `CLE=valeur` par ligne, commentaires avec `#` en début de ligne.

Droits du fichier, si votre client SFTP le permet : **600** (lecture/écriture pour vous seul).

**Pourquoi ces valeurs** : `APP_URL` construit toutes les URL et le cookie de session
(`secure` en HTTPS) ; `APP_KEY` sert de poivre HMAC aux empreintes des tentatives de connexion
et **doit** faire au moins 32 octets en production, sinon la connexion refuse de s'ouvrir avec
un message explicite ; `APP_DEBUG=false` évite d'afficher les traces.

**Point de contrôle** : `https://suite.primevisionsolutions.fr/.env` doit répondre **403 ou
404**, jamais le contenu du fichier. (En disposition A, le fichier est hors racine web : il est
même inatteignable.)

---

## 7. Étape 6 — Schéma, référentiels, administrateur

Trois commandes, dans cet ordre, depuis la racine de l'application sur le serveur.

### 7.1 En SSH (voie normale)

```bash
cd ~/prime-arch                      # ou le chemin absolu affiché dans le panneau
/usr/bin/php8.2 bin/migrate.php       # tables du cœur et des modules activés (idempotent)
/usr/bin/php8.2 bin/seed.php          # rôles, gouvernorats, délégations, codes postaux,
                                      # phases de mission, modèles de documents
/usr/bin/php8.2 bin/create-admin.php  # nom, courriel, mot de passe (12 caractères minimum)
/usr/bin/php8.2 bin/preflight.php     # diagnostic complet (étape 7)
```

- `migrate.php` trace chaque migration appliquée dans la table `migrations` : relancer la
  commande ne rejoue que ce qui manque, y compris après une interruption.
- `seed.php` est idempotent : il synchronise les rôles et les référentiels (24 gouvernorats,
  264 délégations, 4 853 codes postaux, phases de mission et leurs poids, modèles de documents)
  sans dupliquer les lignes existantes.
- `create-admin.php` refuse de créer un compte s'il existe déjà un administrateur actif ; il
  n'existe aucun mot de passe par défaut.
- `bin/migrate.php` exécute aussi les migrations des modules **activés**, une fois les premiers
  modules installés depuis `/settings/modules`.

> ⚠️ Utilisez `/usr/bin/php8.2` (ou `php8.3`), jamais `/usr/bin/php` : sur IONOS, `php` pointe
> encore sur une version 4.x.

> ⚠️ **Aucune réinitialisation de mot de passe dans l'interface.** Le mot de passe saisi à cette
> étape est celui de l'administrateur ; conservez-le dans votre gestionnaire de secrets. La
> procédure d'urgence est en annexe C.

### 7.2 Sans SSH : tâches planifiées (cron)

IONOS → **Hébergement** → *Tâches planifiées* (cron). Créez une tâche, exécutez-la une fois,
puis **supprimez-la**.

| Ordre | Commande |
|---|---|
| 1 | `/usr/bin/php8.2 -f /chemin/absolu/bin/migrate.php` |
| 2 | `/usr/bin/php8.2 -f /chemin/absolu/bin/seed.php` |
| 3 | `printf 'Nom Prénom\nvous@exemple.tn\nMotDePasseTresLong\nMotDePasseTresLong\n' \| /usr/bin/php8.2 -f /chemin/absolu/bin/create-admin.php` |

Le chemin absolu a la forme `/homepages/xx/dXXXXXXXX/htdocs/prime-arch` (contrats antérieurs) ou
`/home/…/prime-arch` (contrats récents) : il figure dans les détails SFTP/SSH de votre compte,
ou dans le chemin affiché par votre client SFTP.

La commande 3 contient le mot de passe en clair dans le panneau : supprimez la tâche juste
après (et sachez que le mot de passe choisi restera celui du compte). Planifiez ces tâches à
quelques minutes d'intervalle pour lire le résultat de chacune.

### 7.3 Consulter le résultat

`migrate.php` liste les fichiers exécutés (`Migrations exécutées : …`), `seed.php` affiche le
nombre de lignes par table. En cas d'erreur, le message est explicite et la commande renvoie un
code non nul — voir le dépannage, section 12.

**Point de contrôle** : `php bin/migrate.php` relancé répond « Rien à migrer. »

---

## 8. Étape 7 — Droits et diagnostic

### 8.1 Droits

Sur IONOS, PHP s'exécute sous votre propre compte : les droits par défaut (644 fichiers,
755 dossiers) suffisent presque toujours. Si une écriture échoue, appliquez :

```bash
cd ~/prime-arch
find . -type d -exec chmod 755 {} \;
find . -type f -exec chmod 644 {} \;
chmod 600 .env
chmod -R 775 storage
```

`storage/` doit rester **inscriptible** : logs, cache, temporaires mPDF, PDF émis, images de
projet, sauvegardes, modules installés y écrivent.

### 8.2 Diagnostic automatique

```bash
/usr/bin/php8.2 bin/preflight.php
```

Le script est en lecture seule et affiche une ligne par point de contrôle, terminée par le
nombre d'erreurs :

| Ligne | Attendu en ligne |
|---|---|
| Version de PHP | 8.2 ou plus |
| Extensions | `pdo_mysql`, `mbstring`, `fileinfo`, `json`, `zip` en `OK` |
| Argon2id | `OK` |
| Fichier `.env` / permissions | présent, non lisible par tous |
| `APP_ENV` / `APP_DEBUG` | `production` / `false` |
| `APP_URL` | HTTPS, sans `/` final, sans `/public` |
| `APP_KEY` | 32 octets ou plus |
| `storage/*` (7 dossiers) | inscriptibles |
| Connexion MySQL | base + version |
| Migrations du cœur | aucune en attente |
| Administrateur actif | au moins 1 |

Code de sortie : `0` si aucune erreur, `1` sinon — utilisable dans un script de déploiement.

**Point de contrôle** : `0 erreur(s)` (les avertissements sur `gd`/`curl` sont tolérables).

---

## 9. Étape 8 — HTTPS

1. IONOS → *Domaines & SSL* → **Certificats SSL** : vérifiez que `suite.primevisionsolutions.fr`
   dispose d'un certificat actif (Let's Encrypt ou SSL IONOS). Sinon, activez-le pour le
   sous-domaine et attendez la validation (de quelques minutes à quelques heures).
2. Une fois `https://suite.primevisionsolutions.fr/health` accessible, forcez le HTTPS en
   ajoutant ce bloc **au début** de `public/.htaccess`, avant les règles existantes :

   ```apache
   <IfModule mod_rewrite.c>
       RewriteEngine On
       RewriteCond %{HTTPS} !=on
       RewriteCond %{HTTP:X-Forwarded-Proto} !https
       RewriteRule ^ https://%{HTTP_HOST}%{REQUEST_URI} [R=301,L]
   </IfModule>
   ```

3. Vérifiez dans le navigateur (outils de développement → *Application* → *Cookies*) que
   `primearch_session` porte bien `Secure` et `HttpOnly`.

> L'application marque le cookie `Secure` dès que `APP_URL` commence par `https://`. Un site
> servi en HTTP avec une `APP_URL` HTTPS produit donc une boucle de connexion : forcez le HTTPS
> (ci-dessus) avant de distribuer l'adresse aux utilisateurs.

**Point de contrôle** : `http://` redirige en une seule étape vers `https://`, cadenas affiché,
et la connexion reste active après un rafraîchissement.

---

## 10. Étape 9 — Recette de mise en ligne

À faire dans l'ordre, sur l'adresse HTTPS.

| # | Contrôle | Attendu |
|---|---|---|
| 1 | `/health` | PHP 8.x « OK », base « OK » |
| 2 | `/.env` | 403 ou 404 |
| 3 | `/app/Core/Application.php`, `/storage/logs/php-error.log`, `/composer.json` | 403 ou 404 |
| 4 | `/login` | page de connexion stylée (CSS et logo chargés) |
| 5 | Connexion administrateur | tableau de bord, menu complet |
| 6 | Menu → **Réglages** | réglages du cabinet, pas une page projets |
| 7 | `/settings` → logo du cabinet | envoi d'un PNG, affichage correct |
| 8 | Créer un client, un projet, une saisie de temps | enregistrement sans erreur |
| 9 | Documents → brouillon → *émettre* → PDF | PDF téléchargé, logo et montants lisibles |
| 10 | Sélecteur de langue → **العربية** | interface en RTL, montants lisibles |
| 11 | `/settings/modules` | liste des modules, activation possible |
| 12 | SSH : `php bin/backup.php` | archive créée dans `storage/backups/` |
| 13 | `storage/logs/php-error.log` | aucune erreur nouvelle |

Si le point 4 affiche une page sans style : `APP_URL` est faux (les URL d'assets en dépendent).
Si le point 5 boucle : cookie `Secure` + HTTP, voir étape 8.

---

## 11. Étape 10 — Sauvegardes planifiées

```bash
/usr/bin/php8.2 -f /chemin/absolu/bin/backup.php
```

- l'archive (schéma + données + `storage/uploads`, `storage/pdf`, `storage/modules`) est écrite
  dans `storage/backups/`, hors racine web, sans `.env` ni `APP_KEY` ;
- `bin/backup.php --verify storage/backups/<archive>.zip` contrôle le manifeste et les
  empreintes sans toucher à la base ;
- elle contient des données clients : **téléchargez-la hors du serveur** (SFTP) et conservez-la
  chiffrée ; prévoyez une rétention et testez une restauration sur une base vide, jamais sur
  celle du cabinet.

Tâche planifiée suggérée (IONOS → *Tâches planifiées*) :

```
0 3 * * * /usr/bin/php8.2 -f /chemin/absolu/bin/backup.php
```

Vérifiez le lendemain que le fichier existe dans `storage/backups/` et que sa taille est
cohérente. Détail des garanties et limites : `docs/OPERATIONS.md`.

---

## 12. Dépannage

| Symptôme | Cause probable | Action |
|---|---|---|
| Erreur 500 sur toutes les pages | `vendor/` absent, ou `.env` illisible, ou PHP < 8.2 | lire `storage/logs/php-error.log` ; `php bin/preflight.php` |
| « Dépendances manquantes : exécutez composer install » | `vendor/autoload.php` absent | envoyer `vendor/` ou lancer Composer (annexe B) |
| Page blanche | erreur fatale masquée par `APP_DEBUG=false` | consulter `storage/logs/php-error.log` |
| `SQLSTATE[HY000] [2002]` / `[1045]` | `DB_HOST` = `localhost`, ou mauvais identifiants | remettre l'hôte `dbXXXX.hosting-data.io` fourni par IONOS |
| `Unknown collation: utf8mb4_unicode_ci` | base créée en `utf8` | recréer la base en `utf8mb4` |
| « APP_KEY doit être défini en production » | `APP_KEY` vide | générer 32 octets, coller, recharger |
| Connexion impossible / boucle | cookie `Secure` sur HTTP | forcer HTTPS (étape 8) |
| Page sans CSS ni images | `APP_URL` faux (slash final, `/public`, mauvais sous-domaine) | corriger `.env`, vider le cache navigateur |
| 404 sur `/login` mais `/index.php/login` marche | `mod_rewrite` inactif ou `.htaccess` ignoré | vérifier `AllowOverride`/le `.htaccess` de `public/`, et la cible du sous-domaine |
| 403 sur tout le site | `.htaccess` racine (`Require all denied`) appliqué à la racine web, ou cible du sous-domaine sur la racine du projet | pointer sur `prime-arch/public` (disposition A) |
| PDF : « dossier temporaire privé de mPDF n'est pas accessible en écriture » | `storage/tmp` non inscriptible | `chmod -R 775 storage` |
| Envoi d'image impossible | `upload_max_filesize` trop bas | `php.ini` (étape 1) |
| Installation d'un module refusée | extension `zip` absente ou `storage/modules` non inscriptible | activer `zip`, corriger les droits |
| Migration interrompue | dépassement de temps ou verrou | relancer `php bin/migrate.php` (idempotent) |
| Fiche projet en erreur, ou galerie / fichiers vides | migrations `0013`, `0014` ou `0015` en attente : `project_images`, `custom_field_key`, `project_files` ou l'ENUM `field_type` manquent | `php bin/migrate.php` puis `php bin/preflight.php` (la ligne « Schéma de la fiche projet » nomme l'objet absent) |
| « Data truncated for column 'field_type' » en créant un champ | base antérieure à la migration `0015` : l'ENUM n'accepte pas `image`/`file` | `php bin/migrate.php`, puis recréer le champ |
| Champ personnalisé affiché comme une zone de texte au lieu d'un téléversement | définition créée avant `0015` : l'ENUM a enregistré un type vide | `php bin/migrate.php`, puis choisir le type du champ dans Réglages › Champs personnalisés |
| Tâche planifiée sans effet | `/usr/bin/php` (4.x) ou chemin relatif | chemin absolu + `php8.2`, sortie redirigée vers un fichier de log |

Journal serveur : `storage/logs/php-error.log` (écrit par l'application). Journaux Apache/PHP
du panneau IONOS : *Hébergement* → *Statistiques/Logs*.

---

## 13. Mises à jour de l'application

1. En local : `git pull`, puis `composer install --no-dev --optimize-autoloader` si
   `composer.lock` a changé, puis `php bin/release.php`.
2. Envoyer **uniquement** les fichiers modifiés (SFTP) ou la nouvelle archive.
3. **Écraser le code, jamais** `.env`, `storage/` (données, PDF émis, images, modules).
4. Sur le serveur : `php bin/migrate.php` puis `php bin/preflight.php`.
5. Vider le cache navigateur si les styles changent ; rejouer les points 1 à 5 de la recette.

Les migrations sont idempotentes et tracées : une mise à jour peut être rejouée sans risque.
Les modules installés depuis l'interface conservent leurs tables et leurs migrations
(`php bin/migrate.php` les applique après activation).

---

## 14. Sécurité — liste finale

- [ ] `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL` en HTTPS.
- [ ] `APP_KEY` aléatoire de 32 octets ou plus, jamais réutilisée, jamais versionnée.
- [ ] Racine web = `public/` ; `.env`, `app/`, `config/`, `storage/` inatteignables en HTTP.
- [ ] `.env` en 600 ; `storage/` inscriptible mais non listable (`.htaccess` fournis).
- [ ] HTTPS actif et redirection forcée ; cookie `Secure` + `HttpOnly`.
- [ ] Mots de passe de 12 caractères minimum, un compte par personne, rôle le plus faible possible.
- [ ] Sauvegarde planifiée + copie hors serveur chiffrée, restauration testée une fois.
- [ ] `storage/logs/php-error.log` consulté après la recette, sans erreur nouvelle.
- [ ] `/health` connu comme seule page publique d'information (version PHP, état base) — aucune
      donnée métier n'y figure.

---

## Annexe A — Disposition B (racine web figée sur un dossier)

Arborescence :

```
<racine web du sous-domaine>/      ex. /suite
├── index.php        ← amorce (12 lignes, ci-dessous)
├── .htaccess        ← routage + blocage
├── assets/          ← copie de prime-arch/public/assets
└── prime-arch/      ← application complète (bloquée en HTTP)
```

`index.php` (racine web) :

```php
<?php
declare(strict_types=1);

/**
 * Amorce du sous-domaine : la racine web reste ce dossier, l'application vit dans
 * prime-arch/ et seul son dossier public est destiné au web.
 */

$entry = __DIR__ . '/prime-arch/public/index.php';
if (!is_file($entry)) {
    http_response_code(500);
    exit('Application absente : vérifier le dossier prime-arch/.');
}

require $entry;
```

`.htaccess` (racine web) :

```apache
Options -Indexes -MultiViews
DirectoryIndex index.php

<IfModule mod_rewrite.c>
    RewriteEngine On

    # Le code, la configuration, .env, vendor/ et storage/ ne sont jamais servis.
    RewriteRule ^prime-arch(/|$) - [F,L]

    # Front controller unique.
    RewriteCond %{REQUEST_FILENAME} !-f
    RewriteCond %{REQUEST_FILENAME} !-d
    RewriteRule ^ index.php [L]
</IfModule>

<FilesMatch "^\.">
    Require all denied
</FilesMatch>
```

Après chaque mise à jour, recopiez `prime-arch/public/assets/` vers `/suite/assets/` (les
feuilles et scripts changent rarement, mais une feuille périmée se voit immédiatement).

**À vérifier absolument dans cette disposition** :

```
https://suite.primevisionsolutions.fr/prime-arch/.env            → 403
https://suite.primevisionsolutions.fr/prime-arch/config/app.php  → 403
https://suite.primevisionsolutions.fr/prime-arch/storage/logs/   → 403
```

## Annexe B — Composer sur le serveur (sans PHP/Composer en local)

```bash
cd ~/prime-arch
curl -sS https://getcomposer.org/installer -o /tmp/composer-setup.php
/usr/bin/php8.2 /tmp/composer-setup.php --install-dir=. --filename=composer.phar
/usr/bin/php8.2 composer.phar install --no-dev --optimize-autoloader
rm composer.phar /tmp/composer-setup.php
```

Nécessite SSH et `curl`. Sans SSH, préparez l'archive depuis un poste disposant de PHP 8.2 et
de Composer (`bin/release.php` inclut alors `vendor/`).

## Annexe C — Mot de passe administrateur perdu

Il n'existe **aucun** écran de réinitialisation. Procédure d'urgence, sur le serveur :

**Option 1 — recréer un administrateur avec le script officiel.** Dans phpMyAdmin
(IONOS → Bases de données → *Ouvrir*) :

```sql
UPDATE users SET is_active = 0 WHERE email = 'ancien@exemple.tn';
```

Puis, en SSH :

```bash
/usr/bin/php8.2 bin/create-admin.php   # nom, courriel, mot de passe de 12 caractères minimum
```

Le script accepte de s'exécuter dès qu'il n'existe plus d'administrateur **actif** ; l'ancien
compte reste en base, désactivé, et peut être réactivé depuis `/admin/users`.

**Option 2 — remplacer l'empreinte du mot de passe.** Générez l'empreinte sur un PHP disposant
d'Argon2id (params encodés dans l'empreinte, donc relus à la vérification) :

```bash
/usr/bin/php8.2 -r 'echo password_hash("NouveauMotDePasseLong", PASSWORD_ARGON2ID, ["memory_cost" => 65536, "time_cost" => 3, "threads" => 1]), PHP_EOL;'
```

puis, dans phpMyAdmin :

```sql
UPDATE users SET password = '<empreinte>' WHERE email = '<compte>';
```

Dans les deux cas, vérifiez ensuite la connexion et rangez le nouveau secret dans votre
gestionnaire de mots de passe.

L'empreinte doit être produite sur un PHP disposant d'Argon2id, avec les mêmes options que
`AuthService` (Argon2id par défaut). Après intervention, vérifiez la connexion et notez le
nouveau secret dans votre gestionnaire de mots de passe.

## Annexe D — Récapitulatif des commandes

```bash
# Local
composer install --no-dev --optimize-autoloader
php bin/release.php --dry-run
php bin/release.php

# Serveur (SSH)
/usr/bin/php8.2 bin/preflight.php
/usr/bin/php8.2 bin/migrate.php
/usr/bin/php8.2 bin/seed.php
/usr/bin/php8.2 bin/create-admin.php
/usr/bin/php8.2 bin/backup.php
```
