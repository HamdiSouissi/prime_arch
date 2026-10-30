# Dossier de livraison — modules Prime Arch

État au **30/10/2026**, commit `dccb3d4` de la branche `arena/b53b7124-prime-arch`.

Ce dossier récapitule les **douze modules** livrés, les paquets prêts à installer, les
vérifications exécutées et les points qui restent à trancher. Il ne remplace pas
`docs/MODULE-PROPOSALS.md` (catalogue et garde-fous) ni `docs/SECURITE-PORTAIL-CLIENT.md`
(revue de sécurité de la seule surface publique).

---

## 1. Inventaire des modules

| # | Module | Vague | Tables créées | Routes | Droits réutilisés (lecture / écriture) |
|---|---|---|---|---|---|
| 1 | `permis-batir` | 1 | `permis_batir_dossiers`, `permis_batir_pieces`, `permis_batir_evenements` | 9 | `projects.view` / `projects.manage` |
| 2 | `suivi-chantier` | 1 | `chantier_visites`, `chantier_reserves`, `chantier_receptions` | 8 | `projects.view` / `projects.manage` |
| 3 | `relances-factures` | 1 | `relances_regles`, `relances_modeles`, `relances_evenements`, `relances_exclusions` | 10 | `documents.view` / `documents.manage` |
| 4 | `metre-quantitatif` | 2 | `metre_lots`, `metre_postes`, `metre_bibliotheque_prix` | 13 | `projects.view` / `projects.manage` |
| 5 | `honoraires-bareme` | 2 | `bareme_missions`, `bareme_tranches`, `bareme_decomptes` | 14 | `projects.view` / `projects.manage` (+ `documents.create`) |
| 6 | `planning-equipe` | 2 | `planning_affectations`, `planning_conges` | 6 | `time.view` / `time.manage` (+ `*_all`) |
| 7 | `conformite-oat` | 2 | `conformite_inscriptions`, `conformite_assurances`, `conformite_formations`, `conformite_partenaires` | 12 | `projects.view` / `projects.manage` (+ `settings.*`) |
| 8 | `comptabilite-export` | 3 | **aucune** (lecture seule) | 2 | `documents.view` ou `finance.view` |
| 9 | `fiscal-tn` | 3 | `fiscal_echeances`, `fiscal_declarations` | 5 | `documents.view` / `documents.manage` (+ `finance.*`, `settings.manage`) |
| 10 | `crm-opportunites` | 3 | `crm_opportunites`, `crm_activites` | 10 | `clients.view` / `clients.manage` (+ `projects.*`) |
| 11 | `portail-client` | 3 | `portail_jetons`, `portail_acces`, `portail_tentatives` | 7 (dont **2 routes publiques GET**) | `clients.view` / `clients.manage` |
| 12 | `ged-versions` | 3 | `ged_documents`, `ged_versions` | 10 | `documents.view` ou `projects.view` / `documents.manage` ou `projects.manage` |

**Totaux** : 31 tables préfixées, 106 routes, 12 entrées de menu latéral, 1 590 clés de
traduction par langue. Aucune table, aucun fichier et aucun écran du noyau n’est modifié : les
seuls ajouts au cœur sont les trois aides génériques déjà présentes (`[data-print]`, `[data-copy]`,
`form[data-confirm]`).

Chaque module apporte : `module.json`, migration SQL idempotente, `Module.php` (routes + entrée de
menu), service métier, dépôt PDO, contrôleur(s), vues, `lang/fr.php` + `lang/ar.php`, et le cas
échéant ses assets privés.

---

## 2. Paquets prêts à installer

Générés depuis le commit `dccb3d4` (`cd modules && zip -qr <paquet> <slug>`), racine unique
`<slug>/`, contrôlés par `unzip -t` (12/12 intègres).

| Paquet | Taille | SHA-256 |
|---|---|---|
| `comptabilite-export-1.0.0.zip` | 16 Ko | `a6817a032a1f60d39fc673887a214bcff146a230e6dfc586b10394aa5a0010d6` |
| `conformite-oat-1.0.0.zip` | 28 Ko | `254761673a251c95baaff1ce5e04473fbb75ae93ea8a164a85a70193b433f3e7` |
| `crm-opportunites-1.0.0.zip` | 32 Ko | `abfe7abccaa38c294265ad2d3a97df6d283574f0844adb16dcde2a2b5f2f2d5b` |
| `fiscal-tn-1.0.0.zip` | 24 Ko | `01b3586baf9764d7ae4e96bab89f744925f7e4493376d600287939e93638cfc6` |
| `ged-versions-1.0.0.zip` | 36 Ko | `5bab3506eb8df56d96a51eb194b203370b30a787a6ad04029d65e16a0601ec1f` |
| `honoraires-bareme-1.0.0.zip` | 36 Ko | `0333e8162fa462453a06c13f12c77666719874d11bc1decbb0f32c322c161e98` |
| `metre-quantitatif-1.0.0.zip` | 36 Ko | `8acc40eaaca5b2df4eb69ec2f8692fcda5a79fe654c1c7d08e23ebd37d8878bb` |
| `permis-batir-1.0.0.zip` | 28 Ko | `94c3274d4ece0944ee6fc5e1b2f6ab78286e9defbb488c4440fa5f5588bf4f8c` |
| `planning-equipe-1.0.0.zip` | 24 Ko | `ba36fa9fa6b6e4d801eb694cf8acdd7835e847b15acb00f9271fa580cc11c2cd` |
| `portail-client-1.0.0.zip` | 40 Ko | `3561f6936ca528c647336594570031001f82557e50107dbc4ed6fd6139b9b83c` |
| `relances-factures-1.0.0.zip` | 36 Ko | `2033f29678648de3c095c49348314b8d785d5352252e1ae8420f11fdf8bbfcc4` |
| `suivi-chantier-1.0.0.zip` | 24 Ko | `08f9a212390cbf206644e2447165ebff0b176af73d5dabbee6fd3da3898eebb3` |

> Les empreintes valent pour **ce** build : un ZIP réempaqueté plus tard diffère par ses
> horodatages, pas par son contenu.

---

## 3. Ordre d’installation conseillé

1. `permis-batir` → `suivi-chantier` → `relances-factures` (vague 1, valeur immédiate).
2. `metre-quantitatif` → `honoraires-bareme` → `planning-equipe` → `conformite-oat` (vague 2).
3. `comptabilite-export` → `fiscal-tn` → `crm-opportunites` → `portail-client` → `ged-versions`
   (vague 3).

Procédure : **Réglages → Modules → Installer**, choisir le ZIP, cocher la case de confiance
(l’installation exécute du code PHP : ce garde-fou n’est jamais contourné), puis activer le module.
Les migrations sont appliquées à l’activation ; une désactivation conserve tables et données.

Avant d’ouvrir `portail-client` aux clients : servir l’application en **HTTPS** et lire la liste de
contrôle de `docs/SECURITE-PORTAIL-CLIENT.md`.

---

## 4. Vérifications exécutées

| Contrôle | Résultat |
|---|---|
| Analyse statique de tous les fichiers PHP de l’application et des modules | **266 fichiers, 0 erreur** |
| Manifestes des modules (slug, namespace, version, entrée, migrations, langues) | **12/12 conformes** |
| Parité des traductions FR/AR, module par module | **12/12 exactes** (1 590 clés par langue) |
| Vérifications de logique métier (harness PHP sans base) | **717 vérifications, 0 échec** |
| Rendus d’écrans FR + AR sur les douze modules | **24 rendus, 0 erreur**, aucune clé de traduction brute |
| Rapports de rendu approfondis (états vides, erreurs, lecture seule, portée client, pages 404/410/429) | portail : 14 rendus ; CRM : 10 ; ged : 10 ; autres modules : 4 à 8 chacun |
| Revue de sécurité du portail | `docs/SECURITE-PORTAIL-CLIENT.md` (20 contrôles, 6 risques résiduels) |
| Intégrité des paquets | `unzip -t` **12/12** |

Répartition des 717 vérifications : comptabilité 45, conformité 70, CRM 73, fiscal 62, GED 119,
honoraires 43, métré 67, planning 80, portail 76, relances 58, noyau de modules 24.

Limites assumées : pas de MySQL ni de navigateur dans l’environnement de contrôle. Les requêtes SQL
ont été relues et bornées, mais **les parcours d’écriture réels restent à valider sur une
installation de recette** (voir §6).

---

## 5. Règles de conception tenues sur les douze modules

- **Aucune modification du noyau** : routes déclarées via `Module::routes()`, menu via le filtre
  `sidebar.items`, vues via `View::renderModule()`.
- **Préfixes réservés** : `permis_batir_*`, `chantier_*`, `relances_*`, `metre_*`, `bareme_*`,
  `planning_*`, `conformite_*`, `fiscal_*`, `crm_*`, `portail_*`, `ged_*`.
- **Permissions du cœur réutilisées** : aucun module n’invente de permission (prérequis P2 non
  implémenté).
- **Bilingue** FR/AR de bout en bout, `dir="rtl"` correct, devise `د.ت` en arabe, montants à trois
  décimales.
- **Barre d’onglets mobile inchangée** : chaque module s’atteint par le menu latéral ou le tiroir.
- **Stockage privé** : seuls `portail-client` et `ged-versions` écrivent des fichiers, tous hors de
  `public/` (`storage/modules/...`), avec noms opaques, type MIME réel et lecture contrôlée.
- **Aucune donnée financière interne exposée** par le portail ; les montants n’apparaissent que si
  le cabinet a coché le droit correspondant sur le lien.

---

## 6. Points ouverts

### 6.1 Prérequis noyau proposés (non implémentés)

| Réf. | Évolution | Bénéfice attendu |
|---|---|---|
| P1 | Filtres de rendu dans les fiches client / projet / document, `dashboard.cards` | Supprimer les allers-retours entre écrans |
| P2 | Registre de permissions de modules + écran de rôle | Restreindre un module par rôle |
| P3 | Messagerie (`Mailer`, `mail_queue`, filtre `mail.transports`) | Envoi réel des relances et des liens de portail |
| P4 | Commande `bin/console module:run <slug> <tâche>` | Purges, alertes d’échéance sans ouvrir un écran |
| P5 | Convention de stockage privé + helper de dépôt validé | Éviter que chaque module réimplémente le téléversement |
| P6 | Filtre `document.pdf.variables` | Mentions propres aux modules dans les PDF |
| P7 | Routes publiques sans démarrage de session | Le portail ne créerait plus un fichier de session par visite |
| P8 | Jeton d’URL non journalisé (journal d’accès filtré ou échange de cookie signé) | Le jeton ne traîne plus dans les `access_log` |
| P9 | Service de fichiers privés signés à courte durée | Remise des PDF de documents émis depuis le portail |

### 6.2 À valider par le cabinet ou un tiers

Délais de permis (OAT et municipalités), forme définitive des PV de réception, tonalité des
relances, barèmes d’honoraires et prix du DQE, périodicités et taux fiscaux, capacité de travail de
l’équipe, obligations de conformité OAT et d’assurance, durées de rétention documentaire. Le détail
figure en « Points de vigilance » de `docs/MODULE-PROPOSALS.md`.

### 6.3 Recette recommandée avant mise en production

1. Installer les douze modules sur une base de recette (MySQL 8) et activer dans l’ordre du §3.
2. Créer un client, un projet et un document émis ; vérifier l’affichage dans les modules qui les
   lisent (portail, GED, CRM, relances, comptabilité).
3. Téléverser un PDF dans `ged-versions`, le télécharger, puis vérifier l’empreinte SHA-256 affichée.
4. Créer un lien de portail, l’ouvrir en navigation privée, vérifier le journal de consultation et
   la révocation immédiate.
5. Contrôler les exports CSV (comptabilité, fiscal, CRM, GED) à l’ouverture dans un tableur.
6. Vérifier les écrans en arabe : direction, montants, dates, impression.
