# Exploitation, audit et sauvegardes

## Déploiement

La mise en ligne sur un hébergement mutualisé (IONOS), le HTTPS, les tâches planifiées de
sauvegarde et les mises à jour sont décrits pas à pas dans **`docs/DEPLOIEMENT-IONOS.md`**.
Trois commandes accompagnent l'exploitation : `php bin/release.php` (archive de livraison sans
`.env`, ni tests, ni contenus générés), `php bin/preflight.php` (diagnostic en lecture seule,
code de sortie 0/1) et `php bin/backup.php` (sauvegarde complète). Les sauvegardes se créent aussi
depuis **Réglages › Sauvegardes** (onglet réservé à l’administrateur), décrit plus bas.

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

## Champs personnalisés de type fichier (plans AutoCAD, DWF, PDF)

Un administrateur peut créer un champ de type **Fichiers techniques** (`/settings/custom-fields`,
fiche concernée : projets). Sur la fiche du projet, le bloc du champ permet de téléverser,
télécharger et retirer des fichiers **DWG, DXF, DWT, DWS, BAK, SV$, DWF, DWFX ou PDF** — trois
actions au plus, sans quitter la page.

Choix d'implémentation et conséquences :

- Le contenu est reconnu à partir de sa **signature**, puis confronté à l'extension reçue. Quatre
  familles : `acad` (DWG, DWT, DWS, BAK et SV$ partagent l'en-tête binaire `AC10xx`), `dxf`
  (texte `0` / `SECTION` ou binaire `AutoCAD Binary DXF`), `dwf` (conteneur ZIP depuis DWF 6 et
  pour DWFX, binaire `(DWF V…` avant) et `pdf` (`%PDF-`). Un exécutable, un texte ou un PDF
  renommé en `.dwg` est refusé avant toute écriture.
- 32 Mio par fichier, 5 fichiers par envoi, 50 par champ. Configurer PHP avec
  `upload_max_filesize=32M` et `post_max_size=36M` au minimum.
- Les fichiers sont stockés sous `storage/uploads/projects/<id>/` (chmod `0640`), hors de
  `public/`, et déclarés dans `project_files` avec leur clé de champ (migration `0015`). Aucune
  valeur n'est conservée dans la colonne JSON `custom_fields` ; le nom d'origine, l'extension, la
  taille et l'empreinte SHA-256 sont mémorisés à côté du nom aléatoire du fichier stocké.
- Le téléchargement passe par une route authentifiée (`projects.view`) qui sert toujours le
  fichier en pièce jointe `application/octet-stream`, après vérification de l'empreinte. Aucun
  accès direct au dossier de stockage n'est possible.
- Envoi et retrait exigent `projects.manage` : aucune permission nouvelle. Les fichiers sont
  exclus de l'export CSV, comme les images.
- Avant d'enregistrer une définition, l'application compare son type aux valeurs acceptées par
  l'ENUM `field_type` : sur une base non migrée, la création est refusée avec la marche à suivre
  plutôt que d'enregistrer un type vide, qui afficherait le champ comme une simple zone de texte.
  Une définition déjà enregistrée de cette façon peut être réparée depuis les réglages (champ
  « Type de valeur » proposé sur la carte du champ).
- Chaque envoi et chaque retrait est journalisé (`projects.file.uploaded`,
  `projects.file.deleted`) avec l'identifiant du projet, la clé du champ et l'extension, jamais le
  nom ni le contenu.

## Modifier ou supprimer un champ personnalisé

Chaque carte de `/settings/custom-fields` est éditable : libellé, ordre d'affichage, caractère
obligatoire, type et, pour une liste de choix, les options.

- **La clé technique et le type de fiche ne changent jamais** : ils portent le stockage (clé du
  JSON `custom_fields`, jointure des images et des fichiers).
- **Le type est verrouillé dès que le champ contient des données.** Les valeurs d'un type
  (nombre, date, liste…) ne sont pas converties : basculer un champ alimenté rendrait ses
  valeurs illisibles. L'interface le signale et le serveur refuse l'opération ; il faut
  supprimer le champ puis en créer un nouveau.
- **La suppression est définitive** et emporte le contenu : fichiers privés puis métadonnées
  (`project_images` / `project_files`) pour les types à téléversement, valeurs retirées du JSON
  de chaque fiche pour les autres types, puis la définition elle-même. L'ordre évite qu'une
  panne laisse un fichier sans métadonnées ; les métadonnées restantes seraient rejouables.
- La carte affiche le contenu actuel (« 14 valeur(s), 3 image(s), 0 fichier(s) ») et la
  confirmation reprend ces nombres avant l'action. Chaque suppression est journalisée
  (`custom_fields.deleted`) avec les compteurs, jamais le contenu.

## Champs rappelés depuis une fiche client ou projet

Le bloc « Champs personnalisés » d'une fiche client ou projet liste les définitions du même type
de fiche **créées mais non activées**, avec un bouton « Activer ». Un champ désactivé depuis les
réglages ne disparaît donc plus du terrain : il reste visible là où on en a besoin.

- **Activation** : `POST /clients/{id}/custom-fields/{fieldId}/activate` ou
  `POST /projects/{id}/custom-fields/{fieldId}/activate`, un clic, jeton CSRF. La définition doit
  appartenir au type de fiche de la page (sinon 404) et l'utilisateur doit détenir
  `clients.manage` (client) ou `projects.configure` (projet) ; sinon 403.
- **Création rapide** : un formulaire replié sous la liste demande le libellé, le type, les
  choix éventuels et le caractère obligatoire. La **clé technique est calculée à partir du
  libellé** (`Typologie du projet` → `typologie_du_projet`), rendue unique par un suffixe
  `_2`, `_3`… et remplacée par `champ` pour un libellé sans caractère latin (arabe). L'ordre
  d'affichage place le nouveau champ après les existants (dernier ordre + 10). Le reste des
  règles est celui des réglages : liste de choix sans option refusée, champ à téléversement
  réservé aux projets et jamais obligatoire, type absent de la base refusé avec le rappel de la
  migration 0015.
- La création active immédiatement le champ, puis journalise `custom_fields.created`
  (`source = record`) ; l'activation journalise `custom_fields.toggled`. Les deux reviennent sur
  la fiche, au bloc des champs personnalisés, avec un message de confirmation ou d'erreur.
- Le bloc apparaît même quand aucun champ n'est encore défini, pour les profils qui peuvent
  configurer : c'est le chemin le plus court vers un premier champ.

## Supprimer un client ou un projet

Deux chemins, un seul jeu de règles : cocher des fiches dans la liste (`/clients`, `/projects`)
puis « Supprimer la sélection », ou utiliser la **zone de danger** de la fiche concernée. Il
faut trois clics au plus (sélection, suppression, confirmation).

- **Droits** : `clients.delete` et `projects.delete`, détenus par l'administrateur (joker `*`) et
  l'architecte. Les autres rôles ne voient ni les cases à cocher, ni la zone de danger ; le
  serveur répond 403 même sur un envoi forgé.
- **Migration 0016** : ajoute ces deux permissions au rôle `architect` des bases déjà installées,
  sans doublon (`php bin/migrate.php`).
- **Fiches protégées** : un client qui porte des projets ou des documents, un projet auquel des
  documents (devis, factures) sont rattachés, ne sont **pas** supprimables. Le refus donne les
  compteurs et renvoie vers l'écran à traiter ; le bouton reste désactivé côté fiche. Un document
  émis garde ainsi son numéro et sa valeur comptable.
- **Ce qui part avec la fiche** : pour un projet, les images privées, les plans techniques
  (fichiers du disque retirés avant leurs métadonnées), les phases affectées et le temps saisi ;
  pour un client, le carnet de contacts. Les lignes sont supprimées dans une transaction.
- **Suppression par lot** : chaque fiche est traitée séparément. Une fiche protégée n'annule pas
  les suppressions déjà faites ; le message final distingue « N supprimé(s) » et « M conservé(s) ».
  La sélection est bornée à 200 identifiants et dédoublonnée.
- Chaque suppression est journalisée (`clients.deleted`, `projects.deleted`) avec les compteurs
  du contenu retiré et, pour un lot, `source = selection`.

## Base de démonstration

Une base de démonstration se prépare avec `php bin/migrate.php` puis `php bin/seed-demo.php`. Le script
précharge les référentiels, crée une équipe (un compte par rôle), des clients, des projets, du temps et
une chaîne de documents commerciale complète. Il est réexécutable, ne modifie aucune table de module et
refuse de s’exécuter lorsque `APP_ENV` vaut `production` sans l’option `--force` : les comptes partagent
un mot de passe de démonstration, généré aléatoirement et affiché une seule fois. Ne jamais l’exécuter
sur la base du cabinet. Détail du contenu et procédure de retrait : `docs/DEMO-DATA.md`.

## Créer et contrôler une sauvegarde

Une sauvegarde se crée en CLI (`php bin/backup.php`) ou depuis **Réglages › Sauvegardes**. Dans les deux
cas elle nécessite PHP 8.2+, `pdo_mysql`, l’extension `zip` (déjà requise par l’application) et les droits
de lecture du schéma/données et d’écriture dans `storage/backups` et `storage/tmp`.

```bash
php bin/backup.php
php bin/backup.php --verify storage/backups/prime-arch-AAAAMMJJ-HHMMSS-XXXXXXXX.zip
```

L’archive comprend le schéma et les lignes de toutes les tables InnoDB de la base configurée, ainsi que les fichiers de `storage/uploads/`, `storage/pdf/` et `storage/modules/`. Elle ne contient pas `.env`, `APP_KEY`, les logs, caches ou temporaires. La commande prend un instantané cohérent des tables InnoDB, normalise la lecture des colonnes `TIMESTAMP` en UTC, exporte les lignes en flux pour limiter la mémoire et calcule les empreintes SHA-256 de chaque fichier. Le format est limité à 20 000 entrées ZIP, 4 Gio de données décompressées, 16 Mio de schéma et 64 Mio par ligne SQL. `--verify` vérifie le manifeste, les empreintes, la structure JSON et le nombre de lignes sans modifier la base.

Pour éviter une divergence entre un document et son PDF/logo pendant la copie des fichiers, lancer la sauvegarde dans une fenêtre de maintenance où les écritures de l’application sont suspendues. Une sauvegarde qui détecte des vues, des tables non-InnoDB, des triggers, routines ou événements SQL s’arrête plutôt que de produire un export incomplet ; ces objets ne font pas partie du schéma actuel du noyau.

Les archives contiennent des informations sensibles (clients, comptes, documents, fichiers de modules) et ne sont pas chiffrées par l’application. Elles sont créées avec des permissions privées, mais le cabinet doit aussi protéger le disque, limiter les comptes système autorisés, organiser une copie chiffrée hors serveur et définir une rétention. Le manifeste SHA-256 détecte une corruption accidentelle, mais ne signe pas l’archive et ne garantit pas son authenticité. Ne pas stocker les sauvegardes dans Git ni les exposer sur le web.

## Onglet Réglages › Sauvegardes

L’écran des réglages comporte un onglet **Sauvegardes** (`/settings#sauvegardes`). Il est visible et
utilisable uniquement pour un compte qui cumule `settings.manage` et `users.manage`, c’est-à-dire
l’administrateur dans les rôles livrés.

| Action | Route | Effet |
| --- | --- | --- |
| Créer | `POST /settings/backups` | Archive `.zip` complète, écrite dans `storage/backups/` (0600). |
| Importer | `POST /settings/backups/import` | Téléversement d’un `.zip`, renommé `prime-arch-import-AAAAMMJJ-HHMMSS-XXXXXXXX.zip`, puis vérifié ; une archive non conforme est supprimée. |
| Télécharger | `GET /settings/backups/download/{nom}` · `POST /settings/backups/download` | Envoi par flux, sans passer par `public/`. Une sélection de plusieurs archives est regroupée dans une `.zip` de transit supprimée après l’envoi. |
| Supprimer | `POST /settings/backups/delete` | Suppression définitive d’une ou plusieurs archives sélectionnées. |
| Restaurer | `POST /settings/backups/restore` | Restauration **en place** : voir ci-dessous. |

L’import n’accepte que
l’extension `.zip`, une signature ZIP réelle (`PK\x03\x04`), et une taille plafonnée par le plus petit des
trois réglages : 256 Mio applicatifs, `upload_max_filesize`, `post_max_size`. L’écran affiche la limite
effective ; relever ces deux valeurs du `php.ini` si les archives du cabinet sont plus volumineuses.
Chaque ligne de la liste affiche le nom de l’archive, la date, l’heure, la taille, le nombre de tables et
de fichiers, ainsi qu’un état « Vérifiée » ou « Illisible » (manifeste absent ou format inconnu). La liste
affiche au plus les 200 archives les plus récentes ; au-delà, les fichiers restent sur le disque mais ne
sont plus listés, et doivent être archivés puis supprimés à la main.
Toutes les actions sont journalisées (`backup.created`, `backup.imported`, `backup.downloaded`,
`backup.deleted`, `backup.restored`).

## Restaurer et éprouver la reprise

Deux chemins de restauration existent et ne s’appliquent pas aux mêmes situations.

1. **Restauration en place depuis Réglages › Sauvegardes** (`BackupService::restoreInPlace()`) : la base
   et les dossiers `storage/{uploads,pdf,modules}` de l’instance en service sont **remplacés** par ceux de
   l’archive. L’opérateur doit recopier exactement `RESTAURER` dans le formulaire ; le serveur revérifie
   le mot, puis l’archive complète (schéma, colonnes, lignes, empreintes) avant toute écriture. Une
   **sauvegarde de sécurité de l’état courant est créée automatiquement** et son nom est rappelé dans le
   message de confirmation. La base est ensuite vidée (vues puis tables), recréée à partir du schéma de
   l’archive, alimentée dans une transaction, puis les fichiers privés sont remplacés. Les DDL MySQL
   effectuent des commits implicites : une erreur en cours d’import laisse une base partiellement
   restaurée, et seule la sauvegarde de sécurité permet de revenir en arrière — via `php bin/restore.php`
   dans une base vide dédiée. Après restauration, les comptes et mots de passe sont ceux de l’archive :
   se reconnecter, puis vérifier les documents émis et l’ouverture des PDF/logo.
2. **Restauration vers une cible vide** (`BackupService::restore()`, CLI) : **la restauration est
   destructive.** Elle refuse toute base qui contient déjà une table/vue et tout dossier de stockage
   cible contenant des fichiers métier. Elle ne supprime jamais les tables existantes. Pour l’essai,
   cloner le code dans un environnement isolé, fournir un `.env` de préproduction pointant vers une
   nouvelle base vide, et utiliser des dossiers `storage/` dédiés.

```bash
php bin/backup.php --verify storage/backups/prime-arch-AAAAMMJJ-HHMMSS-XXXXXXXX.zip
php bin/restore.php --file=storage/backups/prime-arch-AAAAMMJJ-HHMMSS-XXXXXXXX.zip --confirm-empty-database
```

Utiliser le même code/version que celle dont provient la sauvegarde lorsque c’est possible. Le script restaure d’abord le schéma et les lignes, puis les fichiers privés. Les DDL MySQL effectuent des commits implicites : si l’import SQL échoue, les tables créées restent dans la base vide, mais la transaction des lignes est annulée ; si la copie du stockage échoue ensuite, les fichiers peuvent être partiels. Supprimer/recréer uniquement cette base de test et ses dossiers dédiés avant un nouvel essai. Ne pas exécuter `migrate.php` ou `seed.php` sur une cible de restauration avant l’import : la sauvegarde contient déjà son registre de migrations et ses graines.

Après restauration, vérifier la connexion, les fiches, les documents émis et l’ouverture des PDF/logo, puis consigner la date, le résultat, la durée et les anomalies. Le contrôle `--verify` est un contrôle de contenu, pas un essai de démarrage de MySQL ni un test d’acceptation de l’application. Aucun essai réel de restauration n’a pu être réalisé dans l’environnement de développement actuel faute de PHP/MySQL.

Les modules PHP sauvegardés restent du code exécutable non sandboxé. Restaurer uniquement une archive produite et conservée par une source de confiance. Conserver séparément `.env` et la même `APP_KEY` ; la clé n’est jamais incluse dans l’archive.

## Rôles et contrôle d’accès

Les rôles intégrés non-administrateurs énumèrent maintenant leurs permissions individuellement. Seul l’administrateur garde le joker global `*`. Les fonctions de réglages du cabinet (`settings.view`) et le journal d’audit (`audit.view`) sont réservés à l’administrateur, car l’écran des réglages contient notamment des coordonnées bancaires et fiscales. L’onglet **Sauvegardes** exige en plus `users.manage` : il donne accès à l’intégralité des données du cabinet et permet de les remplacer. Les taux et coûts restent contrôlés par des permissions financières dédiées, distinctes de `time.*`. Après une mise à jour, exécuter `php bin/seed.php` pour synchroniser les permissions versionnées.

## Conformité tunisienne

Les paramètres, mentions de documents et calculs fiscaux restent configurables/indicatifs. Aucune conformité fiscale, comptable ou juridique tunisienne n’est déclarée validée ici. Avant utilisation réelle, faire vérifier les règles, taux, mentions et modèles par l’expert-comptable du cabinet et l’OAT ; consigner leur validation et sa date. Ne pas déduire une conformité d’un test logiciel.
