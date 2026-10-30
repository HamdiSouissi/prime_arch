# Feuille de route de développement

Le projet avance par phases validées une à une. Une phase suivante ne commence qu'après validation explicite de la précédente. Le code de chaque phase est livré sur la branche de travail Arena.

## Phase 1 — Fondations et accès sécurisé

**Statut : livrée pour validation.**

- Structure de données et migrations versionnées pour les rôles, utilisateurs, réglages, tentatives de connexion et référentiel géographique.
- Connexion/déconnexion, mots de passe Argon2id, limitation des tentatives, session renouvelée après authentification, CSRF et contrôle d'accès par permissions.
- Provisionnement du premier administrateur uniquement depuis la CLI ; aucune inscription publique ni identifiants par défaut.
- Gestion des utilisateurs et rôles par l'administrateur ; réglages du cabinet et paramètres fiscaux configurables.
- Référentiel bilingue des 24 gouvernorats et des 264 délégations ; table et interfaces de requête prêtes pour les codes postaux.
- Design system Prime Arch existant réutilisé pour les écrans authentifiés.

**Objectif :** permettre l'installation, le provisionnement d'un administrateur, une connexion sécurisée, le contrôle des permissions et l'enregistrement des coordonnées du cabinet. La source de délégations est attribuée ; les codes postaux ne sont pas préchargés tant qu'une source suffisamment fiable et réutilisable n'a pas été validée. Le format de saisie est vérifié.

## Phase 2 — Clients et projets

**Fonctionnalités prévues :** fiches particuliers/entreprises/organismes, contacts, adresses tunisiennes, liste et recherche des clients ; création des projets liés à un client ; phases d'architecte configurables ; champs personnalisés typés avec valeurs JSON ; filtres, statuts et pages de détail.

**Objectif :** gérer le portefeuille clients/projets et les champs propres à un cabinet sans modifier le noyau pour chaque nouvelle donnée.

## Phase 3 — Temps, coûts et avancement

**Fonctionnalités prévues :** saisies de temps par utilisateur/projet/phase, taux horaires historisés, budgets d'heures, avancement pondéré, calcul des coûts et indicateurs de rentabilité protégés par permissions.

**Objectif :** connaître l'effort réel et la rentabilité par phase et par projet, en conservant des calculs monétaires exacts en millimes/DECIMAL.

## Phase 4 — Documents et PDF

**Fonctionnalités prévues :** devis, contrats, factures, lignes et modèles éditables ; taxes paramétrables ; numérotation séquentielle à l'émission ; instantané JSON immuable des données émises ; génération mPDF en français et en arabe ; paiements, factures d'acompte/phase et avoirs.

**Objectif :** garantir que les documents brouillons reflètent les données actuelles et qu'un document émis puisse être régénéré à l'identique.

## Phase 5 — Modules

**Fonctionnalités prévues :** manifeste `module.json`, installation/activation/désactivation, hooks et événements ; téléversement réservé aux administrateurs ; validation stricte des ZIP, protection zip-slip, extraction isolée, dépendances et migrations dédiées.

**Objectif :** étendre le produit sans modifier les fonctionnalités stables du noyau et sans exposer le serveur à un téléversement de code arbitraire.

## Phase 6 — Finition et exploitation

**Fonctionnalités prévues :** mode sombre automatique, audit, sauvegardes et essais de restauration, permissions affinées, conformité aux règles tunisiennes confirmées, tests d'intégration et de sécurité, amélioration responsive et accessibilité.

**Objectif :** durcir l'application avant usage réel en cabinet, sans présenter comme validées les règles fiscales ou juridiques qui nécessitent l'avis de l'expert-comptable et de l'OAT.
