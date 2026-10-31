# Exploitation, audit et sauvegardes

## Déploiement

La mise en ligne sur un hébergement mutualisé (IONOS), le HTTPS, les tâches planifiées de
sauvegarde et les mises à jour sont décrits pas à pas dans **`docs/DEPLOIEMENT-IONOS.md`**.
Trois commandes accompagnent l'exploitation : `php bin/release.php` (archive de livraison sans
`.env`, ni tests, ni contenus générés), `php bin/preflight.php` (diagnostic en lecture seule,
code de sortie 0/1) et `php bin/backup.php` (sauvegarde complète).

## Journal d’audit

Après déploiement, appliquer les migrations (`php bin/migrate.php`) avant d’utiliser l’application : `0011_create_audit_logs.sql` crée `audit_logs`, `0012_create_auth_throttle_markers.sql` déduplique atomiquement les événements de limitation et `0013_create_project_images.sql` crée les métadonnées des images de projet. Le journal suit les connexions réussies/échouées/limitées, les changements de comptes et rôles, les fiches et contacts clients, les projets, phases et images, les champs personnalisés, les modèles de documents et les taux horaires (sans les montants), le cycle de vie des brouillons et documents émis, les règlements, les saisies de temps, les réglages du cabinet, les modules et les commandes de sauvegarde/restauration.

Le journal est consultable à `/admin/audit` par un administrateur. Il conserve l’identifiant de l’objet, l’acteur, la date, le type d’événement et quelques détails d’état explicitement autorisés. Il n’enregistre pas les adresses IP brutes, courriels, coordonnées, identifiants fiscaux, RIB, descriptions, montants ou contenus de documents. Les échecs d’écriture sont envoyés au journal PHP et ne bloquent pas une action métier. Il faut donc surveiller les logs serveur et vérifier que la migration est appliquée. Aucun délai de rétention n’est fixé ici : le cabinet doit le décider avec son conseil en fonction des obligations applicables.

## Images de projet

Les utilisateurs autorisés à gérer les projets peuvent téléverser une image JPEG, PNG ou WebP par envoi, jusqu’à 20 Mio, 10 000 pixels par côté et 40 mégapixels. Configurer PHP avec `upload_max_filesize=20M` et `post_max_size=24M` au minimum. Le serveur vérifie le MIME réel et les dimensions, attribue un nom aléatoire et conserve le fichier sous `storage/uploads/projects/`, hors de `public/`. L’affichage passe par une route authentifiée qui vérifie l’accès `projects.view` ; les images ne sont pas exposées comme fichiers statiques. La sauvegarde inclut ces fichiers via `storage/uploads/`.

## Champs personnalisés de type image

Un administrateur qui configure les projets peut créer un champ personnalisé de type **Images**
(`/settings/custom-fields`, fiche concernée : projets). Le champ apparaît alors sur la fiche du
projet avec sa propre galerie et son formulaire d'envoi : de **une à vingt images** par champ,
**dix fichiers maximum par envoi**, JPEG/PNG/WebP jusqu'à 20 Mio chacun (mêmes contrôles de MIME
réel, de dimensions et de taille que les images de projet).

Choix d'implémentation et conséquences :

- Les fichiers sont stockés dans `storage/uploads/projects/<id>/`, hors racine web, et déclarés
  dans `project_images` avec la colonne `custom_field_key` (migration `0014`). La clé technique du
  champ est la seule jointure : la colonne JSON `custom_fields` du projet ne conserve **aucune**
  valeur pour ce type, il n'existe donc pas de seconde source de vérité à resynchroniser.
- La carte « Images du projet » ne montre que les images générales (`custom_field_key` NULL) ; les
  images d'un champ restent dans le bloc de ce champ, avec leur propre bouton de retrait.
- Lecture : `projects.view` (route d'image existante). Envoi et retrait : `projects.manage` —
  aucune permission nouvelle n'est introduite.
- Un champ image ne peut pas être rendu obligatoire : il est créé avant le projet, les fichiers ne
  peuvent donc arriver qu'après l'enregistrement. Le libellé et le bloc restent informatifs.
- Désactiver le champ masque son bloc et ses images, sans supprimer les fichiers ; supprimer le
  projet supprime les métadonnées (clé étrangère en cascade). La sauvegarde inclut les fichiers via
  `storage/uploads/`.
- Les champs image sont **exclus de l'export/import CSV** : un CSV ne transporte pas de binaire.
  Les fichiers restent consultables et imprimables depuis la fiche du projet.
- Chaque envoi et chaque retrait est journalisé (`projects.image.uploaded`,
  `projects.image.deleted`) avec l'identifiant du projet et la clé du champ, jamais le contenu ni
  le nom du fichier.

## Champs personnalisés de type fichier (plans AutoCAD)

Un administrateur peut créer un champ de type **Fichiers AutoCAD** (`/settings/custom-fields`,
fiche concernée : projets). Sur la fiche du projet, le bloc du champ permet de téléverser,
télécharger et retirer des plans **DWG ou DXF** — trois actions au plus, sans quitter la page.

Choix d'implémentation et conséquences :

- Le format est reconnu à partir de la **signature** du fichier (en-tête DWG `AC10xx`, DXF texte
  `0` / `SECTION`, DXF binaire `AutoCAD Binary DXF`), puis confronté à l'extension reçue : un
  exécutable ou une archive renommée en `.dwg` est refusé avant toute écriture.
- 25 Mio par fichier, 5 fichiers par envoi, 50 par champ. Configurer PHP avec
  `upload_max_filesize=25M` et `post_max_size=28M` au minimum.
- Les fichiers sont stockés sous `storage/uploads/projects/<id>/` (chmod `0640`), hors de
  `public/`, et déclarés dans `project_files` avec leur clé de champ (migration `0015`). Aucune
  valeur n'est conservée dans la colonne JSON `custom_fields` ; le nom d'origine, l'extension, la
  taille et l'empreinte SHA-256 sont mémorisés à côté du nom aléatoire du fichier stocké.
- Le téléchargement passe par une route authentifiée (`projects.view`) qui sert toujours le
  fichier en pièce jointe `application/octet-stream`, après vérification de l'empreinte. Aucun
  accès direct au dossier de stockage n'est possible.
- Envoi et retrait exigent `projects.manage` : aucune permission nouvelle. Les fichiers sont
  exclus de l'export CSV, comme les images.
- Chaque envoi et chaque retrait est journalisé (`projects.file.uploaded`,
  `projects.file.deleted`) avec l'identifiant du projet, la clé du champ et l'extension, jamais le
  nom ni le contenu.

## Base de démonstration

Une base de démonstration se prépare avec `php bin/migrate.php` puis `php bin/seed-demo.php`. Le script
précharge les référentiels, crée une équipe (un compte par rôle), des clients, des projets, du temps et
une chaîne de documents commerciale complète. Il est réexécutable, ne modifie aucune table de module et
refuse de s’exécuter lorsque `APP_ENV` vaut `production` sans l’option `--force` : les comptes partagent
un mot de passe de démonstration, généré aléatoirement et affiché une seule fois. Ne jamais l’exécuter
sur la base du cabinet. Détail du contenu et procédure de retrait : `docs/DEMO-DATA.md`.

## Créer et contrôler une sauvegarde

La sauvegarde s’exécute uniquement en CLI. Elle nécessite PHP 8.2+, `pdo_mysql`, l’extension `zip` (déjà requise par l’application) et les droits de lecture du schéma/données et d’écriture dans `storage/backups` et `storage/tmp`.

```bash
php bin/backup.php
php bin/backup.php --verify storage/backups/prime-arch-AAAAMMJJ-HHMMSS-XXXXXXXX.zip
```

L’archive comprend le schéma et les lignes de toutes les tables InnoDB de la base configurée, ainsi que les fichiers de `storage/uploads/`, `storage/pdf/` et `storage/modules/`. Elle ne contient pas `.env`, `APP_KEY`, les logs, caches ou temporaires. La commande prend un instantané cohérent des tables InnoDB, normalise la lecture des colonnes `TIMESTAMP` en UTC, exporte les lignes en flux pour limiter la mémoire et calcule les empreintes SHA-256 de chaque fichier. Le format est limité à 20 000 entrées ZIP, 4 Gio de données décompressées, 16 Mio de schéma et 64 Mio par ligne SQL. `--verify` vérifie le manifeste, les empreintes, la structure JSON et le nombre de lignes sans modifier la base.

Pour éviter une divergence entre un document et son PDF/logo pendant la copie des fichiers, lancer la sauvegarde dans une fenêtre de maintenance où les écritures de l’application sont suspendues. Une sauvegarde qui détecte des vues, des tables non-InnoDB, des triggers, routines ou événements SQL s’arrête plutôt que de produire un export incomplet ; ces objets ne font pas partie du schéma actuel du noyau.

Les archives contiennent des informations sensibles (clients, comptes, documents, fichiers de modules) et ne sont pas chiffrées par l’application. Elles sont créées avec des permissions privées, mais le cabinet doit aussi protéger le disque, limiter les comptes système autorisés, organiser une copie chiffrée hors serveur et définir une rétention. Le manifeste SHA-256 détecte une corruption accidentelle, mais ne signe pas l’archive et ne garantit pas son authenticité. Ne pas stocker les sauvegardes dans Git ni les exposer sur le web.

## Restaurer et éprouver la reprise

**La restauration est destructive.** Elle refuse toute base qui contient déjà une table/vue et tout dossier de stockage cible contenant des fichiers métier. Elle ne supprime jamais les tables existantes. Pour l’essai, cloner le code dans un environnement isolé, fournir un `.env` de préproduction pointant vers une nouvelle base vide, et utiliser des dossiers `storage/` dédiés.

```bash
php bin/backup.php --verify storage/backups/prime-arch-AAAAMMJJ-HHMMSS-XXXXXXXX.zip
php bin/restore.php --file=storage/backups/prime-arch-AAAAMMJJ-HHMMSS-XXXXXXXX.zip --confirm-empty-database
```

Utiliser le même code/version que celle dont provient la sauvegarde lorsque c’est possible. Le script restaure d’abord le schéma et les lignes, puis les fichiers privés. Les DDL MySQL effectuent des commits implicites : si l’import SQL échoue, les tables créées restent dans la base vide, mais la transaction des lignes est annulée ; si la copie du stockage échoue ensuite, les fichiers peuvent être partiels. Supprimer/recréer uniquement cette base de test et ses dossiers dédiés avant un nouvel essai. Ne pas exécuter `migrate.php` ou `seed.php` sur une cible de restauration avant l’import : la sauvegarde contient déjà son registre de migrations et ses graines.

Après restauration, vérifier la connexion, les fiches, les documents émis et l’ouverture des PDF/logo, puis consigner la date, le résultat, la durée et les anomalies. Le contrôle `--verify` est un contrôle de contenu, pas un essai de démarrage de MySQL ni un test d’acceptation de l’application. Aucun essai réel de restauration n’a pu être réalisé dans l’environnement de développement actuel faute de PHP/MySQL.

Les modules PHP sauvegardés restent du code exécutable non sandboxé. Restaurer uniquement une archive produite et conservée par une source de confiance. Conserver séparément `.env` et la même `APP_KEY` ; la clé n’est jamais incluse dans l’archive.

## Rôles et contrôle d’accès

Les rôles intégrés non-administrateurs énumèrent maintenant leurs permissions individuellement. Seul l’administrateur garde le joker global `*`. Les fonctions de réglages du cabinet (`settings.view`) et le journal d’audit (`audit.view`) sont réservés à l’administrateur, car l’écran des réglages contient notamment des coordonnées bancaires et fiscales. Les taux et coûts restent contrôlés par des permissions financières dédiées, distinctes de `time.*`. Après une mise à jour, exécuter `php bin/seed.php` pour synchroniser les permissions versionnées.

## Conformité tunisienne

Les paramètres, mentions de documents et calculs fiscaux restent configurables/indicatifs. Aucune conformité fiscale, comptable ou juridique tunisienne n’est déclarée validée ici. Avant utilisation réelle, faire vérifier les règles, taux, mentions et modèles par l’expert-comptable du cabinet et l’OAT ; consigner leur validation et sa date. Ne pas déduire une conformité d’un test logiciel.
