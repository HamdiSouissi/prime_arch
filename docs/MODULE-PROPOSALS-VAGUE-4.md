# Vague 4 — nouvelles propositions de modules

Après les douze modules livrés (vagues 1 à 3, voir `docs/LIVRAISON.md`), voici une **quatrième
vague** de onze modules, conçus dans les mêmes contraintes techniques que les précédents :

- aucun fichier du noyau modifié (routes via `Module::routes()`, menu via `sidebar.items`, vues via
  `View::renderModule()`) ;
- **aucune nouvelle permission** : on réutilise `projects.*`, `clients.*`, `documents.*`,
  `finance.*`, `time.*`, `settings.*` (prérequis P2 toujours en attente) ;
- **aucun envoi de courriel** et **aucune tâche planifiée** : les rappels sont affichés à
  l’ouverture des écrans, les courriers restent à copier-coller (prérequis P3 et P4) ;
- tables préfixées par le slug, migrations idempotentes, FR + AR, montants en millimes, sorties
  échappées ;
- périmètre **lecture du cœur, écriture dans les tables du module uniquement**.

Effort indicatif : **S** ≈ 1–2 jours, **M** ≈ 3–5 jours, **L** ≈ 6–10 jours (hors recette MySQL).

---

## 1. Synthèse

| # | Module | Effort | Valeur principale | Tables | Droits réutilisés |
|---|---|---|---|---|---|
| 1 | `registre-courrier` | S | Traçabilité du courrier entrant/sortant, pièce manquante vite retrouvée | `courrier_registre` | `clients.view` / `clients.manage` |
| 2 | `reunions-chantier` | S/M | Comptes rendus de réunion et actions suivies, PV imprimable | `reunions_seances`, `reunions_participants`, `reunions_points` | `projects.view` / `projects.manage` |
| 3 | `appels-offres` | M | Ne plus rater une remise d’offre ni une caution | `ao_dossiers`, `ao_pieces`, `ao_evenements` | `projects.view` / `projects.manage` |
| 4 | `pilotage-cabinet` | S/M | Marge réelle par projet, encours, délais — **aucune table** | — | `finance.*` ou `documents.view` / `settings.view` |
| 5 | `notes-frais` | S/M | Coût réel des missions, remboursements justifiés | `frais_notes`, `frais_lignes` | `projects.view` / `projects.manage` |
| 6 | `marches-travaux` | M | Décomptes, avenants, retenues et cautions sous contrôle | `marches_marches`, `marches_decomptes`, `marches_avenants`, `marches_cautions` | `projects.view` / `projects.manage` |
| 7 | `sous-traitance` | M | Engagements et échéances des BET et consultants | `sous_traitance_contrats`, `sous_traitance_echeances`, `sous_traitance_pieces` | `projects.view` / `projects.manage` |
| 8 | `litiges-reclamations` | S/M | Réclamations et réserves non levées suivies jusqu’à la clôture | `litiges_dossiers`, `litiges_evenements` | `projects.view` / `projects.manage` |
| 9 | `parc-materiel` | S | Matériel, licences, étalonnages et affectations | `parc_materiels`, `parc_evenements` | `settings.view` / `settings.manage` |
| 10 | `donnees-personnelles` | M | Registre des traitements et demandes (loi 2004-63, INPDP) | `donnees_traitements`, `donnees_demandes` | `settings.view` / `settings.manage` |
| 11 | `veille-urbanisme` | S | Textes, PAU et règlements de zone retrouvés en un clic | `veille_textes`, `veille_notes` | `projects.view` / `projects.manage` |

Aucune route publique nouvelle. Aucun de ces modules n’écrit dans `clients`, `projects`,
`documents`, `document_payments`, `time_entries` ni dans une table du cœur.

---

## 2. Détail des modules

### 1. `registre-courrier` — Registre du courrier — **S**

- **Rôle :** registre chronologique du courrier entrant et sortant, avec numérotation continue,
  correspondant, objet, mode d’acheminement (dépôt, recommandé, e-mail, main propre), projet et
  client concernés, statut de traitement.
- **Écrans :** registre filtrable (sens, année, statut, projet, recherche libre), fiche courrier,
  impression du registre annuel, export CSV.
- **Tables :** `courrier_registre` (numéro, année, sens, date, correspondant, objet, référence,
  mode, statut, projet, client, notes).
- **Lit dans le noyau :** `clients`, `projects`, `users`.
- **Garde-fous :** aucun format de numérotation imposé (préfixe et compteur paramétrables par
  année) ; aucun envoi, aucune numérotation légale affirmée.
- **Pourquoi maintenant :** c’est le module le moins risqué de la liste (une table, deux écrans) et
  celui qui manque le plus vite à un cabinet qui reçoit des recommandés de mairie.

### 2. `reunions-chantier` — Comptes rendus de réunion — **S/M**

- **Rôle :** séances de chantier, participants, points discutés, décisions, **actions** avec
  responsable et échéance, diffusion, PV imprimable.
- **Écrans :** liste par projet, fiche séance, actions en retard (toutes séances confondues),
  PV imprimable FR/AR.
- **Tables :** `reunions_seances`, `reunions_participants`, `reunions_points` (type point,
  discussion, décision, responsable, échéance, état).
- **Lit dans le noyau :** `projects`, `clients`, `users`.
- **Garde-fous :** le PV est un **document de travail**, jamais présenté comme contractuel ; aucun
  envoi automatique aux participants ; les actions ne modifient ni le planning ni `suivi-chantier`.
- **Complémentarité :** `suivi-chantier` suit les visites et les réserves ; ce module suit les
  réunions et les décisions. Aucune dépendance dure entre les deux.

### 3. `appels-offres` — Appels d’offres et consultations — **M**

- **Rôle :** de la publication à l’attribution : acheteur, objet, lot, montant estimé, caution
  provisoire, date limite de remise, visite des lieux, questions/réponses, pièces administratives,
  offre remise, résultat et motif.
- **Écrans :** liste par échéance, fiche dossier avec checklist de pièces, calendrier des remises,
  historique, export CSV.
- **Tables :** `ao_dossiers`, `ao_pieces` (libellé, obligatoire, échéance, état, note),
  `ao_evenements` (publication, visite, questions, remise, ouverture, attribution).
- **Lit dans le noyau :** `projects`, `clients`, `users`, `documents` (consultation seulement).
- **Garde-fous :** **aucun délai légal codé** (les dates sont saisies), aucun modèle de caution ni
  de mise en forme administrative imposé, aucun dépôt électronique : le module est un aide-mémoire
  de suivi, pas une plateforme de soumission. Les valeurs usuelles (TUNEPS, cautions) doivent être
  confirmées par le cabinet.
- **Pourquoi :** c’est le module à plus fort effet sur le chiffre d’affaires — une remise ratée ou
  une caution oubliée coûte un dossier entier.

### 4. `pilotage-cabinet` — Indicateurs de gestion — **S/M**

- **Rôle :** tableau de bord **en lecture seule** : chiffre d’affaires facturé et encaissé par
  période, encours et balance âgée, marge par projet (honoraires facturés − coûts saisis), taux
  horaire réalisé, délais moyens de paiement, pipeline commercial, production par phase.
- **Écrans :** synthèse annuelle et mensuelle, tableau par projet, export CSV.
- **Tables :** **aucune** — comme `comptabilite-export`, il lit sans écrire.
- **Lit dans le noyau :** `documents`, `document_payments`, `clients`, `projects`,
  `project_phases`, `project_phase_assignments`, `time_entries`, `users`.
- **Droits :** `finance.costs.view` ou `finance.rates.view` en lecture ; `settings.view` pour la
  configuration des seuils d’affichage.
- **Garde-fous :** aucun coût, taux ou marge n’est inventé : quand la donnée manque, la ligne est
  marquée « non calculable » plutôt que comblée par une estimation. Aucune écriture.
- **Pourquoi :** aujourd’hui le cabinet ne voit sa marge qu’en fin d’année, chez le comptable.

### 5. `notes-frais` — Frais de mission et avances — **S/M**

- **Rôle :** notes de frais par projet (déplacements, impressions, tests de sol, repas, hébergement),
  lignes détaillées, justificatifs, statut (brouillon, soumise, remboursée), récapitulatif mensuel
  et par projet, export CSV.
- **Écrans :** liste des notes, fiche note (lignes), récap par projet et par mois.
- **Tables :** `frais_notes`, `frais_lignes` (catégorie, date, libellé, montant, justificatif,
  TVA facultative).
- **Lit dans le noyau :** `projects`, `clients`, `users`.
- **Garde-fous :** **aucun barème de remboursement imposé** (le cabinet saisit ses montants) ;
  aucune écriture de paiement dans le noyau ; aucune TVA recalculée — le montant saisi fait foi.
- **Pourquoi :** c’est la brique qui rend la marge par projet crédible dans `pilotage-cabinet`.

### 6. `marches-travaux` — Marchés, décomptes et garanties — **M**

- **Rôle :** suivi financier d’un marché de travaux : montant initial, avenants, décomptes
  successifs (travaux, retenue de garantie, avance, révision), cumul et solde, cautions
  (provisoire, définitive), délai d’exécution.
- **Écrans :** liste des marchés, fiche marché (décomptes, avenants, cautions), état financier
  imprimable, export CSV.
- **Tables :** `marches_marches`, `marches_decomptes`, `marches_avenants`, `marches_cautions`.
- **Lit dans le noyau :** `projects`, `clients`, `documents`.
- **Garde-fous :** **aucun taux imposé** (la retenue de garantie est un pourcentage saisi et
  affiché comme valeur du cabinet), aucun calcul de pénalités légales, aucune écriture comptable ;
  le module n’émet ni décompte officiel ni ordre de service.
- **Pourquoi :** retenues de garantie et avenants sont les deux postes où l’architecte perd de
  l’argent sans le voir.

### 7. `sous-traitance` — BET, géomètres et consultants — **M**

- **Rôle :** contrats de sous-traitance (BET structure, fluides, géomètre, topographe, acousticien),
  montant, échéancier de paiements, pièces contractuelles (patente, CNSS, assurance, attestation
  fiscale), retenue de garantie, état d’avancement.
- **Écrans :** liste des contrats, fiche contrat, échéancier, alertes « pièce manquante » et
  « paiement à venir ».
- **Tables :** `sous_traitance_contrats`, `sous_traitance_echeances`, `sous_traitance_pieces`.
- **Lit dans le noyau :** `projects`, `clients`, `users`.
- **Frontière avec `conformite-oat` :** `conformite-oat` vérifie la **conformité du cabinet et de
  ses partenaires** (inscriptions, assurances, formations) ; `sous-traitance` suit les
  **engagements contractuels et les paiements**. Aucun recouvrement de données.
- **Garde-fous :** aucune obligation légale de pièce affirmée (la liste est paramétrable), aucun
  taux ni délai imposé.

### 8. `litiges-reclamations` — Réclamations, réserves et désordres — **S/M**

- **Rôle :** registre des réclamations clients, réserves non levées, désordres signalés, mises en
  demeure, garanties invoquées, montant en jeu, statut et historique daté.
- **Écrans :** liste par statut et par projet, fiche dossier, chronologie, indicateurs (ouvertes,
  en attente, clôturées), export CSV.
- **Tables :** `litiges_dossiers`, `litiges_evenements`.
- **Lit dans le noyau :** `projects`, `clients`, `users`, `documents` (références seulement).
- **Garde-fous :** **aucun délai de garantie légal codé** (parfait achèvement, biennale, décennale
  restent des intitulés de travail à valider), aucun modèle juridique fourni, aucune mise en
  demeure générée automatiquement.
- **Pourquoi :** les réserves oubliées deviennent des litiges ; le module force une revue
  périodique.

### 9. `parc-materiel` — Matériel, logiciels et étalonnages — **S**

- **Rôle :** inventaire (station totale, niveau, scanner, drone, ordinateurs, licences), numéro de
  série, affectation à un collaborateur, étalonnage et maintenance, valeur d’achat et date.
- **Écrans :** liste du parc, fiche matériel, échéances d’étalonnage, historique d’affectation,
  export CSV.
- **Tables :** `parc_materiels`, `parc_evenements` (affectation, retour, maintenance, étalonnage).
- **Lit dans le noyau :** `users`.
- **Garde-fous :** aucun amortissement comptable officiel n’est calculé ; la valeur et la durée
  sont des informations de gestion, pas des écritures.
- **Droits :** `settings.view` / `settings.manage` (module administratif).

### 10. `donnees-personnelles` — Registre des traitements — **M**

- **Rôle :** registre des traitements (finalité, base légale invoquée, catégories de personnes et de
  données, destinataires, durée de conservation, mesures de sécurité), demandes d’accès, de
  rectification et d’opposition avec échéance de réponse, historique des actions.
- **Écrans :** liste des traitements, fiche traitement, registre des demandes, tableau de suivi des
  échéances, export CSV.
- **Tables :** `donnees_traitements`, `donnees_demandes`.
- **Lit dans le noyau :** `users` (responsable du traitement).
- **Garde-fous :** le module **ne donne aucun conseil juridique** et n’impose **aucun délai légal** :
  les durées et les échéances sont saisies et l’écran rappelle qu’elles doivent être validées
  (loi organique n° 2004-63 et recommandations de l’**INPDP**). Aucune donnée personnelle n’est
  dupliquée depuis les fiches clients.
- **Pourquoi :** un cabinet d’architecture traite des données de clients, de salariés et
  d’entreprises ; disposer d’un registre prêt vaut mieux qu’une reconstruction en urgence.

### 11. `veille-urbanisme` — Textes et règlements locaux — **S**

- **Rôle :** base de connaissance des textes utiles (code de l’urbanisme, PAU, règlements de zone,
  arrêtés municipaux), avec référence, date, commune concernée, résumé et note d’impact sur les
  projets en cours.
- **Écrans :** liste recherchable (commune, thème, année), fiche texte, notes liées à un projet,
  export CSV.
- **Tables :** `veille_textes`, `veille_notes`.
- **Lit dans le noyau :** `projects`, `users`.
- **Garde-fous :** aucune reproduction de texte réglementaire protégé — le module stocke des
  **références, liens et résumés saisis par le cabinet** ; l’exactitude juridique reste à vérifier
  à la source.

---

## 3. Ordre de livraison recommandé

1. **`registre-courrier`** (S) — gain immédiat, risque quasi nul, aucune dépendance.
2. **`reunions-chantier`** (S/M) — dans la continuité de `suivi-chantier`, très utilisé.
3. **`appels-offres`** (M) — le plus fort impact commercial.
4. **`pilotage-cabinet`** (S/M) — aucune table : rapide, et il donne de la valeur aux données déjà
   saisies.
5. **`notes-frais`** (S/M) — rend la marge par projet crédible.
6. **`marches-travaux`** (M) — sécurise les retenues et les avenants.
7. **`sous-traitance`** (M) — échéances de paiement et pièces des BET.
8. **`litiges-reclamations`** (S/M) — protège la trésorerie et la relation client.
9. **`parc-materiel`** (S) — court, administratif.
10. **`donnees-personnelles`** (M) — conformité, à faire avant un contrôle.
11. **`veille-urbanisme`** (S) — capitalisation progressive.

Critères : valeur quotidienne → impact trésorerie → risque technique. Les trois premiers modules
représentent environ **une semaine de développement** et couvrent l’essentiel du besoin non couvert.

---

## 4. Ce qui reste volontairement hors périmètre

| Sujet | Pourquoi on ne le fait pas en module |
|---|---|
| Facturation électronique, TTN, mentions légales | Doit être garanti par le noyau, avec immuabilité des documents émis |
| Paie, comptabilité générale, liasse fiscale | Hors périmètre produit ; export vers le comptable |
| Signature électronique | Exige un tiers de confiance, des preuves et un cadre juridique |
| Messagerie et relances automatiques | Nécessite le prérequis **P3** (`Mailer`, file d’attente) et **P4** (cron) |
| Synchronisation comptable ou bancaire | Intégrations externes, secrets d’API, hors modèle de modules actuel |
| Gestion des utilisateurs et des rôles | Sécurité du noyau |
| Thème, navigation, barre d’onglets | Doit rester homogène et testé dans le noyau |

---

## 5. Ce que débloquerait le noyau (rappel)

| Réf. | Évolution | Ce qu’elle ouvrirait pour la vague 4 |
|---|---|---|
| P1 | Filtres de rendu `project.detail.sections`, `client.detail.sections` | Afficher réunions, litiges, marchés et frais **dans** la fiche projet |
| P2 | Registre de permissions | Restreindre `donnees-personnelles` et `parc-materiel` aux administrateurs sans dépendre de `settings.*` |
| P3 | Messagerie | Convocation de réunion, envoi de PV, relance des pièces d’AO |
| P4 | Commande `module:run` + cron | Alertes d’échéance (AO, cautions, étalonnages) sans ouvrir un écran |
| P5 | Convention de stockage privé | Justificatifs de frais et pièces d’AO déposés par un helper commun |
| P9 | Fichiers privés signés | Téléchargement des pièces par lien temporaire |

---

## 6. Décision attendue

1. Le périmètre de la vague 4 est-il celui-ci, ou faut-il en retirer / ajouter ?
2. Quel module démarre ? Ma recommandation : **`registre-courrier`** (S, zéro dépendance), puis
   `reunions-chantier`, puis `appels-offres`.
3. Faut-il traiter `notifications-mail` (dernier module du catalogue initial, toujours non livré)
   avant la vague 4 ? Cela suppose de trancher **P3** : SMTP maison, `mail()` de PHP ou dépendance
   Composer.

Comme pour les vagues précédentes, je livrerai **un module à la fois**, avec contrôles, paquet ZIP,
captures de rendu et commit poussé sur la branche de travail.
