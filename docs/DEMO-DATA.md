# Jeu de données de démonstration

La démonstration remplit une base **migrée et préchargée** avec une équipe, des clients, des
projets, des saisies de temps et une chaîne de documents commerciaux cohérente, afin de
parcourir l'application avec des écrans peuplés (tableau de bord, listes, fiches, documents).

## Mise en route

```bash
php bin/migrate.php
php bin/seed-demo.php
php -S localhost:8000 -t public
```

`seed-demo.php` exécute d'abord `CoreSeeder` (rôles, géographie, phases, modèles de documents),
puis écrit le jeu de démonstration. Les deux étapes sont réexécutables. Prérequis : base migrée,
`composer install` effectué (l'émission des documents rend un PDF via mPDF) et `APP_ENV` différent
de `production`.

| Option | Effet |
| --- | --- |
| `--password=SECRET` | mot de passe commun aux comptes créés (12 caractères minimum) |
| `--today=AAAA-MM-JJ` | décale la date de référence du jeu (démonstration figée, recette) |
| `--force` | autorise l'exécution alors que `APP_ENV` vaut `production` |

Sans `--password`, un mot de passe aléatoire est généré et **affiché une seule fois** en fin
d'exécution : aucun mot de passe de démonstration n'est codé en dur. Le script refuse de
s'exécuter sur un environnement `production` sans `--force`, car tous les comptes créés
partagent ce mot de passe.

## Contenu

Le jeu est décrit une seule fois dans `app/Services/DemoDataset.php` et écrit par
`app/Services/DemoDataSeeder.php`. Les dates sont relatives à la date du jour : la
démonstration reste d'actualité, et `--today` permet de la figer.

| Élément | Volume | Remarques |
| --- | --- | --- |
| Comptes | 6 | un par rôle du noyau (administrateur, architecte ×2, collaborateur, comptable, lecture seule), dont un compte en arabe |
| Taux horaires | 6 | valeur initiale datée, plus une revalorisation (historisation réelle) |
| Clients | 7 | particuliers, sociétés et association, dont deux prospects et un client archivé |
| Contacts | 7 | un contact principal par client actif |
| Projets | 7 | en cours, en attente, en planification et livré ; phases avancées dans l'ordre du noyau |
| Temps saisi | 32 | 8 semaines d'activité, quantités au quart d'heure, temps facturable et non facturable |
| Documents | 11 | devis (dont un brouillon et un devis arabe), contrats, factures (phase, acompte, échue), avoir |
| Règlements | 2 | un virement intégral et un chèque partiel |

Les documents passent par les règles réelles de l'application : totaux recalculés, numérotation
annuelle (`DEV-2025-000001`, `FAC-2026-000003`…), instantané figé et PDF conservé. Les factures
émises portent des dates passées, ce qui alimente les écrans « échéances » et « en retard ».

## Données et règles

- **Aucune règle fiscale n'est inventée.** La TVA, le timbre et la retenue appliqués sont ceux
  de la configuration du cabinet (`Réglages`) et des modèles de documents ; le jeu ne modifie
  aucun de ces paramètres.
- **Montants en millimes** (trois décimales), comme le reste de l'application.
- **Géographie réelle** : gouvernorats, délégations et codes postaux proviennent du référentiel
  préchargé ; la ville est le nom de la délégation. Aucun code postal n'est saisi à la main.
- **Identifiants fictifs** : adresses électroniques en `@demo.prime-architecture.tn`, matricules
  fiscaux, téléphones et adresses sont inventés et ne désignent aucune personne ni société réelle.
- Les comptes et clients portent la mention « données de démonstration » dans leurs notes.

## Réexécution et retrait

Le seeder est idempotent : chaque élément est recherché par sa clé naturelle (adresse
électronique, nom du client, nom du projet, référence de document, saisie de temps identique)
avant toute insertion. Une seconde exécution ne crée rien de nouveau et laisse les données
modifiées en place.

Le jeu ne touche que les tables du noyau (utilisateurs, clients, projets, phases, temps,
documents, règlements) : il n'écrit **aucune** table de module, et aucun module n'a besoin
d'être installé pour la démonstration.

Pour retirer les données, le plus sûr est de restaurer une sauvegarde antérieure
(`php bin/restore.php`, voir `docs/OPERATIONS.md`) ou de réinitialiser une base dédiée. Ne
jamais lancer ce script sur une base contenant des données réelles : les comptes créés
partagent un mot de passe connu de l'exploitant.

## Vérification

`tests/DemoDataTest.php` relit le jeu sans base de données :

```bash
vendor/bin/phpunit --filter DemoDataTest
```

Il vérifie l'unicité des clés, la validité des téléphones et matricules, l'appartenance des
délégations à leur gouvernorat, l'ordre des phases, la cohérence des saisies de temps, la
chaîne devis → contrat → facture → avoir, les formats monétaires et l'absence de date future.
