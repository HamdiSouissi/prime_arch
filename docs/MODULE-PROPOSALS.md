# Propositions de modules installables

Ce document décrit les extensions que je propose de développer pour Prime Arch, dans le cadre
technique réel du gestionnaire de modules (voir `docs/MODULES.md`) et pour un cabinet
d’architecture tunisien : missions d’architecte, permis de bâtir, DCE, suivi de chantier,
honoraires en millimes, TVA, timbre fiscal et retenue à la source.

---

## 1. Ce qu’un module peut faire aujourd’hui

| Capacité | État | Remarque |
|---|---|---|
| Routes, contrôleurs, vues privées | Oui | `routes(Router $router)` + `View::renderModule()` |
| Tables propres (migrations SQL) | Oui | Préfixer par le slug : `permis_batir_*` |
| Traductions FR/AR | Oui | `lang/fr.php` + `lang/ar.php`, clés préfixées |
| CSS/JS/images privés | Oui | `/module-assets/<slug>?file=css/module.css` |
| Entrée de menu latéral | Oui | Filtre `sidebar.items` |
| Événements | Oui | `module.installed/activated/deactivated/booting/booted` |
| **Bloc dans une fiche existante** (client, projet, document) | **Non** | Aucun hook de rendu dans le noyau |
| **Nouvelle permission** | **Non** | Permissions figées par `database/seeds/roles.php`, pas d’écran d’édition |
| **Entrée dans la barre d’onglets mobile** | **Non** | La barre doit rester identique partout (6 entrées noyau) |
| **Tâche planifiée** (relances, échéances) | **Non** | Aucun ordonnanceur ; seulement des actions à l’ouverture d’un écran |
| **Envoi de courriel** | **Non** | Aucun service de messagerie dans le noyau |
| Modification des PDF du noyau | Non | `DocumentPdfService` non hookable |

**Conséquence de conception :** un module est aujourd’hui une **section autonome** de
l’application (ses écrans, ses tables, son entrée de menu), pas une extension qui s’insère dans
les écrans du noyau. Les propositions ci-dessous respectent cette contrainte ; celles qui en
auraient besoin signalent le prérequis correspondant.

---

## 2. Prérequis noyau (petites évolutions à faire d’abord)

Ces ajouts sont légers mais débloquent l’essentiel de la valeur des modules.

| Réf. | Évolution | Pourquoi | Effort |
|---|---|---|---|
| **P1** | Filtres de rendu : `project.detail.sections`, `client.detail.sections`, `document.detail.actions`, `dashboard.cards` | Permettre à un module d’afficher un panneau dans une fiche existante (sinon l’architecte doit changer d’écran) | S |
| **P2** | Registre de permissions : filtre `permissions.registry` + écran de rôle affichant les capacités des modules | Sans cela, un module ne peut pas être restreint par rôle (il doit réutiliser `projects.view`, etc.) | M |
| **P3** | Service de messagerie : `Mailer` + file d’attente `mail_queue` + filtre `mail.transports` | Indispensable aux relances, au portail client et aux notifications d’échéance | M |
| **P4** | Commande CLI `bin/console module:run <slug> <tâche>` + exemple de cron documenté | Échéances, relances, purges ; sans cela un module ne peut agir qu’à l’ouverture d’un écran | S |
| **P5** | Convention de stockage privé `storage/modules/<slug>/files/` + helper de dépôt/validation (MIME, taille, anti-lien) | Éviter que chaque module réinvente (et casse) le téléversement | S |
| **P6** | Filtre `document.pdf.variables` (données additionnelles dans les modèles PDF) | Ajouter des mentions propres à un module sur devis/factures | S |

> **Décision à valider (P6/barre mobile) :** je recommande de **ne pas** ouvrir la barre
> d’onglets aux modules : vous avez demandé qu’elle reste identique sur tous les onglets. Un
> module s’atteint par le menu latéral et le tiroir mobile.

---

## 3. Catalogue proposé

Effort : **S** ≈ 1–2 jours, **M** ≈ 3–5 jours, **L** ≈ 6–10 jours (hors tests d’intégration MySQL).

### Vague 1 — Métier et trésorerie (à faire en premier)

#### 1. `permis-batir` — Dossiers de permis de bâtir et suivi administratif — **L**
**Problème :** le permis est le point de blocage administratif n° 1 du cabinet ; les délais
conditionnent la facturation des phases `permis` et `dce`.

- **Écrans :** liste filtrable (commune, état, année), fiche dossier, tableau de bord des délais.
- **Contenu :** commune (référentiel gouvernorat/délégation déjà présent), n° et date de dépôt,
  commission concernée, pièces déposées (checklist paramétrable), avis, demandes de complément,
  arrêté obtenu/refusé, dates de retrait et de recours, historique horodaté.
- **Tables :** `permis_batir_dossiers`, `permis_batir_pieces`, `permis_batir_evenements`.
- **Rattachements :** `projects`, `clients`, `delegations`, `project_images` (plans déposés).
- **Sorties :** bordereau de dépôt PDF (FR/AR), export CSV, relances d’échéance (via P3/P4).
- **Permissions :** `projects.view` en lecture, `projects.manage` en écriture (en attendant P2).
- **Risques :** les délais réglementaires varient selon la commune ; le module **ne code aucune
  durée légale** : elles sont saisies par le cabinet dans les réglages du module.

#### 2. `suivi-chantier` — Journal de chantier, réserves et réceptions — **L**
**Problème :** la phase « direction de l’exécution » n’est suivie nulle part ; les PV et réserves
vivent dans des courriels.

- **Écrans :** journal chronologique par projet (visite, météo, effectif, observations, photos),
  registre des réserves (émise / levée / contestée), PV de réception provisoire et définitive.
- **Tables :** `chantier_journal`, `chantier_reserves`, `chantier_pv`, `chantier_journal_photos`.
- **Rattachements :** `projects`, `project_phases`, `users`, stockage privé (photos).
- **Sorties :** PV de réception PDF FR/AR, extrait de journal par période.
- **Dépendance :** P5 (stockage).
- **Valeur :** protège juridiquement le cabinet (preuve de visite et de réserve) et alimente la
  facturation de la phase réception.

#### 3. `relances-factures` — Relances et recouvrement — **M**
**Problème :** les impayés sont visibles (paiements) mais rien ne les relance.

- **Écrans :** balance âgée (0-30/31-60/61-90/+90 j), file de relances, historique par client.
- **Contenu :** règles de relance paramétrables (délai, ton, modèle FR/AR), promesse de paiement,
  marquage « litige », exclusion d’un client.
- **Tables :** `relances_regles`, `relances_evenements`, `relances_modeles`.
- **Rattachements :** `documents`, `document_payments`, `clients` — **lecture seule** du noyau.
- **Dépendances :** P3 (envoi) et P4 (déclenchement). Sans elles, le module produit la liste et le
  courriel à copier-coller : dégradation acceptable pour une V1.
- **Gain :** le plus rapide à rentabiliser.

### Vague 2 — Production et conformité

#### 4. `metre-quantitatif` — Avant-métré, DQE et bibliothèque de prix — **L**
- Lots et postes (gros œuvre, second œuvre, lots techniques), unités, quantités, prix unitaires,
  totaux, marges ; bibliothèque de prix réutilisable ; export CSV et DQE PDF.
- **Tables :** `metre_lots`, `metre_postes`, `metre_bibliotheque_prix`.
- **Rattachements :** `projects` (et phase `dce`), `clients`.
- **Intérêt :** nourrit l’estimation du coût des travaux, donc l’assiette des honoraires (module 5).
- **Attention :** montants en `DECIMAL(15,3)`, jamais de flottant.

#### 5. `honoraires-bareme` — Décompte des honoraires par mission — **M**
- Barème **saisi par le cabinet** (assiette = coût des travaux, % par phase, missions partielles),
  décompte par projet et par phase, génération d’un devis d’honoraires dans le noyau.
- **Tables :** `bareme_missions`, `bareme_tranches`, `bareme_decomptes`.
- **Rattachements :** `projects`, `project_phases`, `documents` (création de devis).
- **Garde-fou :** aucun taux OAT/décret n’est codé en dur ni présenté comme validé ; l’écran
  affiche un avertissement « barème à faire valider par l’OAT ».

#### 6. `planning-equipe` — Charge et planning des collaborateurs — **M**
- Vue semaine/mois par collaborateur (saisies de temps existantes + affectations prévisionnelles),
  congés, détection de surcharge, comparaison budget d’heures / réalisé.
- **Tables :** `planning_affectations`, `planning_conges`.
- **Rattachements :** `users`, `projects`, `project_phases`, `time_entries` (lecture).

#### 7. `conformite-oat` — Dossier professionnel et assurances — **S/M**
- Inscription à l’OAT, carte professionnelle, RC professionnelle (échéances), formation continue,
  bureaux d’études partenaires ; alertes d’expiration ; attestation d’assurance par projet.
- **Tables :** `conformite_inscriptions`, `conformite_assurances`, `conformite_formations`.
- **Valeur :** évite une suspension d’activité pour pièce expirée.

### Vague 3 — Relation client et finance

#### 8. `portail-client` — Espace client en lecture seule — **L**
- Lien signé et expirable (sans création de compte) : avancement du projet, documents émis,
  photos de chantier ; journal des consultations ; révocation immédiate.
- **Tables :** `portail_jetons`, `portail_acces`.
- **Sécurité :** routes publiques à jeton HMAC, expiration, limitation de débit, aucun index
  exposé, `noindex` ; **à ne pas improviser** — je le propose en dernier de la vague, avec revue
  de sécurité dédiée.

#### 9. `fiscal-tn` — Échéancier et déclarations — **M**
- Calendrier des obligations (TVA mensuelle/trimestrielle, retenue à la source, TCL), état
  « à préparer / déposé / payé », récapitulatif par période, export CSV pour l’expert-comptable.
- **Tables :** `fiscal_echeances`, `fiscal_declarations`.
- **Garde-fou :** aucun taux ni périodicité imposés ; tout est paramétré et affiché comme
  « à valider par l’expert-comptable ».

#### 10. `comptabilite-export` — Journaux comptables — **S/M**
- Journal des ventes par période (factures, avoirs, paiements, TVA, timbre, retenue), export CSV
  compatible avec les logiciels utilisés en Tunisie ; un fichier par période et par journal.
- **Aucune table** (lecture seule du noyau) : le module le moins risqué du catalogue.
- **Valeur :** supprime la ressaisie et les erreurs de TVA.

#### 11. `crm-opportunites` — Prospects et pipeline — **M**
- Prospects (particulier/entreprise/organisme), origine, étape, relance prévue, conversion en
  client + projet ; activités (appel, visite, envoi de devis).
- **Tables :** `crm_opportunites`, `crm_activites`.
- **Rattachements :** `clients`, `projects`, `documents`.

#### 12. `notifications-mail` — Socle de messagerie — **M**
- Réglages SMTP, modèles FR/AR, file d’attente, journal des envois et des échecs, test d’envoi.
- **Tables :** `notifications_reglages`, `notifications_file`, `notifications_log`.
- **Rôle :** module « socle » dont dépendent `relances-factures`, `portail-client` et
  `fiscal-tn` (dépendances déclarées dans `requires.modules`).
- **Décision requise :** `mail()` de PHP seul, client SMTP maison, ou dépendance Composer
  autorisée. Sans accord, je pars sur **SMTP maison** (socket, STARTTLS, AUTH LOGIN/PLAIN,
  zéro dépendance).

#### 13. `ged-versions` — Versions et recherche documentaire — **M**
- Dépôt de fichiers par projet/client avec versions, étiquettes, recherche par titre/étiquette,
  rétention ; stockage privé hors `public/`.
- **Tables :** `ged_documents`, `ged_versions`.
- **Dépendance :** P5.

---

## 4. Ce qui ne doit **pas** être un module

| Sujet | Pourquoi |
|---|---|
| Facturation électronique / TTN, mentions légales obligatoires | Le noyau doit garantir la conformité et l’immuabilité des documents émis |
| Comptabilité générale, paie | Hors périmètre produit ; intégrations via export |
| Thème, design system, navigation | Doit rester homogène et testé dans le noyau |
| Moteur PDF / modèles de documents | Déjà couvert (mPDF FR/AR) ; à étendre par P6 si besoin |
| Gestion des utilisateurs et des rôles | Sécurité du noyau |

---

## 5. Ordre de livraison recommandé

1. **Prérequis** P1 → P5 (une itération courte, sans module).
2. **Vague 1** : `permis-batir`, `suivi-chantier`, `relances-factures`.
3. **Vague 2** : `metre-quantitatif`, `honoraires-bareme`, `planning-equipe`, `conformite-oat`.
4. **Vague 3** : `comptabilite-export` (rapide), `fiscal-tn`, `crm-opportunites`,
   `portail-client` (avec revue de sécurité), `ged-versions`.

Critères de priorisation retenus : valeur quotidienne pour l’architecte > impact trésorerie >
risque technique. Les deux modules à **plus fort effet perçu** sont `suivi-chantier` (il crée de la
matière que le cabinet n’a jamais eue) et `relances-factures` (il encaisse).

---

## 6. Décisions à valider avant le premier module

1. **Courriel** : SMTP maison (recommandé, zéro dépendance) ou dépendance Composer ?
2. **Permissions de modules** : on implémente P2 (recommandé) ou on réutilise les permissions du
   noyau pour la V1 ?
3. **Portée linguistique des données** : libellés saisis une seule fois (FR) ou bilingues par
   enregistrement (utile pour les PV et courriers arabes) ?
4. **Cron** : accès à une tâche planifiée sur le serveur d’hébergement (nécessaire aux relances et
   aux alertes d’échéance) ?
5. **Multi-cabinet** : les modules doivent-ils être conçus pour une seule installation (comme
   aujourd’hui) ou anticiper plusieurs cabinets partageant la base ?

---

## 7. État de livraison

Chaque module livré est un dossier autonome sous `modules/<slug>/`, empaquetable en ZIP
(racine unique `<slug>/`) et installable depuis **Réglages → Modules** avec la case
« code de confiance ». Aucun fichier du noyau n’est modifié à l’exception des deux aides
génériques ajoutées pour tous les modules : `[data-print]`, `[data-copy]`, `form[data-confirm]`
(`public/assets/js/ui.js` + clé `js.copied`).

| Module | Version | Tables | Permission réutilisée | Écrans |
|---|---|---|---|---|
| `permis-batir` | 1.0.0 | `permis_batir_dossiers`, `permis_batir_pieces`, `permis_batir_evenements` | `projects.view` / `projects.manage` | Liste + export CSV, création, fiche (checklist de pièces, historique horodaté), édition |
| `suivi-chantier` | 1.0.0 | `chantier_visites`, `chantier_reserves`, `chantier_receptions` | `projects.view` / `projects.manage` | Liste des chantiers, fiche chantier (journal, réserves, réceptions), PV de réception imprimable |
| `relances-factures` | 1.0.0 | `relances_regles`, `relances_modeles`, `relances_evenements`, `relances_exclusions` | `documents.view` / `documents.manage` | Balance âgée, file de relances, courrier à copier-coller, historique, règles et modèles FR/AR |
| `metre-quantitatif` | 1.0.0 | `metre_lots`, `metre_postes`, `metre_bibliotheque_prix` | `projects.view` / `projects.manage` | Liste des projets métrés, fiche de métré (lots, postes, marges), édition d’un poste, DQE imprimable, export CSV, bibliothèque de prix |
| `honoraires-bareme` | 1.0.0 | `bareme_missions`, `bareme_tranches`, `bareme_decomptes` | `projects.view` / `projects.manage` (+ `documents.create` pour le devis) | Liste des projets, fiche honoraires (décomptes), barèmes et tranches par phase, détail du calcul, génération d’un devis d’honoraires en brouillon |
| `planning-equipe` | 1.0.0 | `planning_affectations`, `planning_conges` | `time.view` / `time.manage` (+ `time.view_all` et `time.manage_all` pour toute l’équipe) | Grille semaine/mois de la charge par collaborateur, affectations prévisionnelles, congés, détection de surcharge à seuil réglable, budget d’heures par projet |

### Points de vigilance (à valider par le cabinet)

- **Délais de permis** : le module n’impose aucun délai légal ; l’architecte saisit les échéances
  et le module se contente de signaler les retards. Les valeurs usuelles doivent être confirmées
  auprès de l’**OAT** et des services municipaux compétents.
- **Réceptions et réserves** : le PV généré est un **document de travail** ; sa forme définitive
  doit être validée avec le maître d’ouvrage et, le cas échéant, le conseil juridique du cabinet.
- **Relances** : le module ne calcule aucun intérêt de retard, n’applique aucun délai légal et
  n’envoie aucun courrier (le noyau n’expose pas de service de messagerie). Les tonalités et les
  modèles doivent être relus par le cabinet avant envoi ; un expert-comptable ou un conseil
  juridique peut valider la formulation.
- **Aucune donnée financière** n’est créée par ces trois modules : ils ne modifient ni les
  documents, ni les coûts, ni la numérotation du noyau. `relances-factures` lit seulement
  `documents`, `document_payments` et `clients`.
- **Prix et marges du DQE** : `metre-quantitatif` n’embarque **aucun barème**, aucun prix de
  référence et aucune marge par défaut. Les prix unitaires, les quantités et les marges sont
  saisis par le cabinet ; les montants sont hors taxes et calculés en millimes entiers, arrondis
  au millime. Le DQE imprimé est un **document d’étude** : il ne vaut ni marché ni devis signé,
  et sa forme doit être validée par le cabinet avant remise au maître d’ouvrage.
- **Aucune donnée financière du noyau n’est modifiée** : `metre-quantitatif` lit `projects` et
  `clients` et n’écrit que dans ses tables `metre_*`.
- **Honoraires** : `honoraires-bareme` n’embarque **aucun barème officiel**, aucun taux de l’OAT
  et aucun pourcentage par défaut. Le cabinet saisit ses phases, ses tranches d’assiette et ses
  taux ; l’écran rappelle que le barème doit être confirmé auprès de l’**OAT** et du contrat. Chaque
  décompte fige l’assiette et le barème utilisés. Le devis généré est un **brouillon** créé dans
  les documents du noyau : il doit être relu avant émission.
- **Aucune capacité de travail imposée** : `planning-equipe` n’embarque ni horaire légal, ni
  durée hebdomadaire, ni taux de charge. Le seuil de surcharge est un paramètre d’écran, et le
  budget d’heures est celui saisi sur la fiche projet : lorsqu’il est vide, aucun écart n’est
  calculé et aucune valeur n’est inventée. Les **jours fériés ne sont pas modélisés** : ils
  doivent être saisis comme congés pour réduire la charge ; ce point doit être confirmé avec le
  cabinet.
- **Lecture seule du cœur** : `planning-equipe` lit `users`, `roles`, `projects`, `clients`,
  `project_phase_assignments` et `time_entries` (heures réalisées) et n’écrit que dans ses tables
  `planning_*`. Il ne crée aucun pointage et ne modifie ni coût ni budget.

### Installation

```bash
cd modules && zip -r permis-batir-1.0.0.zip permis-batir
cd modules && zip -r suivi-chantier-1.0.0.zip suivi-chantier
cd modules && zip -r relances-factures-1.0.0.zip relances-factures
cd modules && zip -r metre-quantitatif-1.0.0.zip metre-quantitatif
cd modules && zip -r honoraires-bareme-1.0.0.zip honoraires-bareme
cd modules && zip -r planning-equipe-1.0.0.zip planning-equipe
```

Puis **Réglages → Modules → Installer**, sélectionner le ZIP, cocher la case de confiance, puis
activer le module (les migrations sont appliquées à l’activation). Désactiver un module conserve
ses tables et ses données ; la désinstallation complète n’est jamais silencieuse.

### Préfixes de tables

Les préfixes suivent le domaine fonctionnel et restent réservés au module : `permis_batir_*`,
`chantier_*`, `relances_*`, `metre_*`, `bareme_*` et `planning_*`. Le gestionnaire de modules n’impose pas le préfixe du slug, mais
chaque migration est idempotente (`CREATE TABLE IF NOT EXISTS`) et ne touche aucune table du cœur.
