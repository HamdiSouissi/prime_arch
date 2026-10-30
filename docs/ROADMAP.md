# Feuille de route de développement

Le projet avance par phases validées une à une. Une phase suivante ne commence qu'après validation explicite de la précédente. Le code de chaque phase est livré sur la branche de travail Arena.

## Phase 1 — Fondations et accès sécurisé

**Statut : livrée pour validation.**

- Structure de données et migrations versionnées pour les rôles, utilisateurs, réglages, tentatives de connexion et référentiel géographique.
- Connexion/déconnexion, mots de passe Argon2id, limitation des tentatives, session renouvelée après authentification, CSRF et contrôle d'accès par permissions.
- Provisionnement du premier administrateur uniquement depuis la CLI ; aucune inscription publique ni identifiants par défaut.
- Gestion des utilisateurs et rôles par l'administrateur ; réglages du cabinet et paramètres fiscaux configurables.
- Référentiel bilingue des 24 gouvernorats et des 264 délégations ; 4 853 enregistrements postaux et suggestions par gouvernorat/délégation, avec cinq groupes source laissés sans délégation faute de rapprochement sûr.
- Design system Prime Arch existant réutilisé pour les écrans authentifiés.

**Objectif :** permettre l'installation, le provisionnement d'un administrateur, une connexion sécurisée, le contrôle des permissions et l'enregistrement des coordonnées du cabinet. Les sources des délégations et des suggestions postales sont documentées. Les codes postaux proviennent d'un annuaire communautaire ancien et non officiel ; leur exhaustivité et leur actualité ne sont pas garanties, et les cas ambigus ne sont pas rattachés arbitrairement. Le format à quatre chiffres est vérifié.

## Phase 2 — Clients et projets

**Statut : livrée pour validation.**

**Fonctionnalités livrées :** fiches particuliers/entreprises/organismes, contacts, adresses tunisiennes, liste et recherche des clients ; création des projets liés à un client ; phases d'architecte configurables ; champs personnalisés typés avec valeurs JSON ; filtres, statuts et pages de détail.

**Objectif :** gérer le portefeuille clients/projets et les champs propres à un cabinet sans modifier le noyau pour chaque nouvelle donnée.

## Phase 3 — Temps, coûts et avancement

**Statut : livrée pour validation.**

**Fonctionnalités livrées :** saisies de temps par utilisateur/projet/phase, taux horaires historisés, budgets d'heures, avancement pondéré, calcul des coûts et indicateurs de rentabilité protégés par permissions.

**Objectif :** connaître l'effort réel et la rentabilité par phase et par projet, en conservant des calculs monétaires exacts en millimes/DECIMAL.

## Phase 4 — Documents et PDF

**Statut : livrée pour validation.**

- Devis, contrats, factures, lignes et modèles éditables; taxes paramétrables; numérotation séquentielle à l'émission; instantanés des données émises; génération mPDF en français et en arabe; paiements, factures d'acompte/phase et avoirs.

**Objectif :** garantir que les documents brouillons reflètent les données actuelles et qu'un document émis puisse être régénéré à l'identique.

## Phase 5 — Modules

**Statut : implémentée pour validation.**

- Manifeste `module.json`, installation/activation/désactivation; hooks et événements; téléversement réservé aux administrateurs; validation stricte des ZIP, prévention du zip-slip, extraction isolée dans `storage/`, dépendances et migrations propres à chaque module.
- Désactiver un module ne supprime ni ses migrations ni ses données. Les modules PHP ne sont pas sandboxés : seules des archives de confiance doivent être installées.

**Objectif :** étendre le produit sans modifier les fonctionnalités stables du noyau, tout en protégeant l’extraction d’archives et en avertissant explicitement du risque inhérent au code PHP fourni par un module.

## Phase 6 — Finition et exploitation

**Fonctionnalités prévues :** mode sombre automatique, audit, sauvegardes et essais de restauration, permissions affinées, conformité aux règles tunisiennes confirmées, tests d'intégration et de sécurité, amélioration responsive et accessibilité.

**Objectif :** durcir l'application avant usage réel en cabinet, sans présenter comme validées les règles fiscales ou juridiques qui nécessitent l'avis de l'expert-comptable et de l'OAT.
