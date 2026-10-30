# Exploitation, audit et sauvegardes

## Journal d’audit

Après déploiement, appliquer les migrations (`php bin/migrate.php`) avant d’utiliser l’application : `0011_create_audit_logs.sql` crée `audit_logs`, `0012_create_auth_throttle_markers.sql` déduplique atomiquement les événements de limitation et `0013_create_project_images.sql` crée les métadonnées des images de projet. Le journal suit les connexions réussies/échouées/limitées, les changements de comptes et rôles, les fiches et contacts clients, les projets, phases et images, les champs personnalisés, les modèles de documents et les taux horaires (sans les montants), le cycle de vie des brouillons et documents émis, les règlements, les saisies de temps, les réglages du cabinet, les modules et les commandes de sauvegarde/restauration.

Le journal est consultable à `/admin/audit` par un administrateur. Il conserve l’identifiant de l’objet, l’acteur, la date, le type d’événement et quelques détails d’état explicitement autorisés. Il n’enregistre pas les adresses IP brutes, courriels, coordonnées, identifiants fiscaux, RIB, descriptions, montants ou contenus de documents. Les échecs d’écriture sont envoyés au journal PHP et ne bloquent pas une action métier. Il faut donc surveiller les logs serveur et vérifier que la migration est appliquée. Aucun délai de rétention n’est fixé ici : le cabinet doit le décider avec son conseil en fonction des obligations applicables.

## Images de projet

Les utilisateurs autorisés à gérer les projets peuvent téléverser une image JPEG, PNG ou WebP par envoi, jusqu’à 20 Mio, 10 000 pixels par côté et 40 mégapixels. Configurer PHP avec `upload_max_filesize=20M` et `post_max_size=24M` au minimum. Le serveur vérifie le MIME réel et les dimensions, attribue un nom aléatoire et conserve le fichier sous `storage/uploads/projects/`, hors de `public/`. L’affichage passe par une route authentifiée qui vérifie l’accès `projects.view` ; les images ne sont pas exposées comme fichiers statiques. La sauvegarde inclut ces fichiers via `storage/uploads/`.

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
