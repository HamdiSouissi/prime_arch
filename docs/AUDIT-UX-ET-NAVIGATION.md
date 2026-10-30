# Audit design, UI/UX et règle des 3 clics

**Périmètre :** noyau Prime Arch (27 écrans, 37 routes GET dont 4 techniques, 6 entrées de menu) + les 12 modules livrés
(37 vues, 12 entrées de menu). **Méthode :** lecture du code réel des vues, des feuilles de style
(`tokens.css`, `layout.css`, `components.css`, `pages.css`, `dark.css`), du JavaScript
(`ui.js`, 863 lignes) et des routes ; calculs de contraste WCAG et de budgets de clics.
**Limite :** aucun navigateur ni serveur PHP n'est disponible dans cet environnement ; les
constats sont issus du code, pas d'une capture d'écran. Les chiffres cités sont vérifiables
fichier par fichier.

---

## 1. Ce qui fonctionne déjà bien

| Point | Preuve |
|---|---|
| Système de jetons cohérent (couleurs, espaces, rayons, ombres, typographie) | `tokens.css`, 116 lignes, un seul point de vérité |
| Contrastes conformes AA | texte principal 11,85:1 ; secondaire 5,77:1 ; tertiaire 4,61:1 ; badges 5,8–6,8:1 (calculé) |
| Propriétés logiques partout, RTL propre | zéro `left/right/margin-left` physique dans les 5 feuilles du noyau |
| Cibles tactiles ≥ 44 px | `--tap-target: 2.75rem`, `.button--icon` 44 × 44 |
| Focus visible et mouvement réduit | `:focus-visible` 3 px + `prefers-reduced-motion` global |
| Modales et tiroir mobile accessibles | `inert`, focus rendu au déclencheur, `aria-expanded`, piège clavier des onglets (`ui.js`) |
| Recherche locale accentuée-insensible | `normalizeSearch()` (NFD) |
| FR/AR complets, 1 590 clés par langue, parité vérifiée | `docs/LIVRAISON.md` |
| Modules homogènes avec le noyau | 34 vues de module sur 37 utilisent `page-header` ; mêmes composants, mêmes tables |

Le socle visuel est bon. Les problèmes sont **d'architecture de navigation**, de **densité
d'information** et d'**écarts entre la maquette et l'application réelle**.

---

## 2. Audit « 3 clics » — état des lieux

Règle cible : **depuis n'importe quel écran, toute destination doit être atteinte en 3 clics
maximum**, sur ordinateur **comme sur mobile**.

### 2.1 Ordinateur (depuis le tableau de bord)

| Destination | Chemin | Clics | Verdict |
|---|---|---|---|
| Fiche client | Clients → ligne | 2 | OK |
| Édition client | Clients → fiche → Modifier | 3 | OK |
| Fiche projet | Projets → ligne | 2 | OK |
| Document | Documents → ligne | 2 | OK |
| PDF d'un document | Documents → fiche → PDF | 3 | OK |
| Dossier de permis | Permis → ligne | 2 | OK |
| Projet de chantier | Chantier → projet | 2 | OK |
| Réception (PV) | Chantier → projet → réception | 3 | OK |
| DQE d'un métré | Métré → projet → DQE | 3 | OK |
| **Poste d'un DQE** | Métré → projet → DQE → poste | **4** | **Échec** |
| Décompte d'honoraires | Honoraires → projet → décompte | 3 | OK |
| Document GED | GED → document | 2 | OK |
| Facture en relance | Relances → impayés → facture | 3 | OK |
| Opportunité CRM | CRM → ligne | 2 | OK |
| Attestation de conformité | Conformité → attestations → projet | 3 | OK |
| **Audit** | (aucune entrée de menu) Utilisateurs → Journal | 2 | OK mais **caché** |
| **Champs personnalisés** | Projets → fiche → Champs personnalisés | 3 | OK mais **mal rangé** |
| **Chantier du projet que je regarde** | Projet → *rien* ; retour liste → Chantier → projet | 3 + perte de contexte | **Échec fonctionnel** |
| **Métré du projet que je regarde** | idem | 3 + perte de contexte | **Échec fonctionnel** |
| **Client d'un document** | Document → *texte non cliquable* | impossible | **Cul-de-sac** |

### 2.2 Mobile (barre d'onglets à 6 entrées, modules dans le tiroir)

| Destination | Chemin | Clics | Verdict |
|---|---|---|---|
| Module (liste) | ☰ → module | 2 | OK |
| Projet de chantier | ☰ → Chantier → projet | 3 | OK |
| **DQE** | ☰ → Métré → projet → DQE | **4** | **Échec** |
| **Réception / PV** | ☰ → Chantier → projet → réception | **4** | **Échec** |
| **Poste de métré** | ☰ → Métré → projet → DQE → poste | **5** | **Échec** |
| **Champs personnalisés** | ☰ → Projets → fiche → champs | **4** | **Échec** |

### 2.3 Les quatre causes racines

1. **Aucune passerelle contextuelle.** La fiche projet du noyau
   (`app/Views/pages/projects/show.php`) ne contient **aucun lien vers un module** (vérifié :
   zéro occurrence de `chantier`, `permis`, `honoraires`, `metre`, `ged`, `relances`). Pour
   passer d'un projet au chantier *de ce projet*, il faut revenir à la liste et re-sélectionner.
   C'est le prérequis **P1** (`project.detail.sections`, `client.detail.sections`), non implémenté.
2. **La palette de commandes ne cherche rien.** Elle ne contient que les 6 entrées de menu
   (`ViewContext::authenticated()`), filtre par `textContent` sans normalisation d'accents, sans
   navigation clavier par flèches, sans historique. Elle promet « Rechercher dans Prime Arch »
   mais ne connaît ni client, ni projet, ni document.
3. **Aucun fil d'Ariane.** Les pages profondes n'ont qu'un bouton « Retour » vers la liste ;
   il n'existe pas de composant `breadcrumb` dans `partials/`. L'utilisateur ne sait pas où il est
   ni comment remonter d'un cran.
4. **Menu latéral non défilant.** `.sidebar-nav` a `min-block-size: 0` mais **aucun
   `overflow-y: auto`** (`layout.css:81`). Avec 18 entrées (6 noyau + 12 modules) :

   | Hauteur de fenêtre utile | Hauteur du menu | Entrées visibles |
   |---|---|---|
   | 768 px (portable 1366 × 768) | ≈ 1 130 px | **≈ 11 sur 18** → 7 modules inaccessibles |
   | 900 px | ≈ 1 130 px | ≈ 13 sur 18 |
   | 1 080 px | ≈ 1 130 px | 18 (limite) |

   Entre 761 et 1 100 px, la barre passe en rail d'icônes de 3,5 rem + 0,5 rem d'espacement :
   18 entrées = **1 152 px**, soit un débordement garanti même sur une tablette 810 × 1 080.

---

## 3. Propositions pour garantir les 3 clics

### A. Passerelles contextuelles — le gain le plus fort (prérequis P1)

Implémenter dans le noyau quatre filtres de rendu, puis les exposer aux modules :

```php
HookManager::applyFilters('project.detail.sections', $sections, $project);
HookManager::applyFilters('client.detail.sections', $sections, $client);
HookManager::applyFilters('document.detail.actions', $actions, $document);
HookManager::applyFilters('dashboard.cards', $cards, $user);
```

Chaque module renvoie une carte compacte (titre + 2 chiffres + lien). Résultat mesurable :

| Trajet | Aujourd'hui | Après |
|---|---|---|
| Projet → chantier du projet | 3 clics + re-recherche | **1 clic** |
| Projet → métré / honoraires / permis / GED / relances | 3 + re-recherche | **1 clic** |
| Client → portail / documents / projets | 2–3 | **1 clic** |
| Document → relance / GED | 3 | **1 clic** |

Effort : **M** (1 filtre, 1 partiel `partials/context-card.php`, 1 déclaration par module).
C'est la seule modification qui fait passer *tous* les modules sous les 3 clics, y compris sur
mobile, **sans toucher aux modules déjà livrés**.

### B. Palette de commandes qui cherche vraiment

- Nouvelle route `GET /api/recherche?q=` (clients, projets, documents, dossiers de permis),
  limitée aux droits de l'utilisateur, `LIMIT 8` par catégorie, résultats échappés.
- Navigation clavier `↑ ↓ Entrée Échap`, `aria-activedescendant`, annonce `aria-live`.
- Sections « Récents » (5 dernières fiches vues, en session) puis « Actions ».
- Normalisation d'accents côté client (`normalizeSearch` déjà écrite), insensible à la casse.
- Libellé de raccourci adapté à la plateforme (`⌘K` sur macOS, `Ctrl K` ailleurs).

Résultat : **⌘K puis ↓ ↓ Entrée** = n'importe quelle fiche en 2 frappes, 0 clic. Effort : **M**.

### C. Fil d'Ariane et retour d'un cran

Composant `partials/breadcrumb.php` (`Accueil / Clients / Ben Salem / Villa Sidi Bou Saïd`),
injecté par `page-header` via une clé `breadcrumb`. Règle : chaque écran déclare son parent,
donc « remonter » coûte toujours **1 clic**. Effort : **S**.

### D. Menu latéral groupé, défilant, repliable

- `overflow-y: auto` + `scrollbar-width: thin` sur `.sidebar-nav` (correctif immédiat du §2.3.4).
- Regroupement en 5 sections : **Pilotage** (tableau de bord, planning) · **Production**
  (clients, projets, chantier, métré, permis, GED) · **Finance** (documents, honoraires,
  relances, fiscal, comptabilité) · **Conformité** (OAT, CRM, portail) · **Administration** (réglages, utilisateurs, audit, modules).
- Contrat de menu étendu : `group` et `section` optionnels dans `sidebar.items` (rétrocompatible).
- Rail 761–1 100 px : garder le libellé au survol **et** corriger les collisions d'icônes
  (aujourd'hui `people` pour Clients **et** CRM, `calendar` pour Fiscal **et** Planning) →
  `crm-opportunites` → `chart`, `planning-equipe` → `timer`.
- Entrée « Aujourd'hui » en haut (échéances + tâches), facultative.

Effort : **S/M**. Gain : 18 entrées redeviennent lisibles et toutes atteignables.

### E. Barre mobile : 5 onglets + « Plus »

Aujourd'hui 6 onglets, dont « Réglages » qui occupe une place rare. Proposition :
`Accueil · Clients · Projets · Documents · Plus`. « Plus » ouvre une feuille plein écran groupée
(Production / Finance / Conformité / Administration) avec recherche intégrée. Les modules
passent de **2 clics** (tiroir + entrée) à **2 clics** (Plus + entrée), mais restent atteignables
d'un pouce, sans tiroir latéral ni perte de contexte. Libellés d'onglet à remonter de 0,59 rem
(≈ 9,4 px) à **0,68 rem minimum**. Effort : **S/M**.

### F. Zéro cul-de-sac

Rendre cliquables les entités citées dans les fiches : `documents/show.php` affiche le client et
le projet en **texte brut** ; `documents/index.php` idem. Chaque nom propre doit être un lien vers
sa fiche (sous réserve des droits). Effort : **S**.

### G. Test automatisé de la règle des 3 clics

Un test PHPUnit de « profondeur de navigation » : parser `config/routes.php` et
`modules/*/Module.php`, chercher les liens entrants dans les vues, calculer la distance minimale
depuis `/dashboard`, **échouer si une route dépassable dépasse 3**. Le budget cesse d'être une
intention et devient une contrainte de CI, y compris pour la vague 4. Effort : **S/M**.

---

## 4. UI/UX — constats et améliorations

### 4.1 Priorité haute

| # | Constat (preuve) | Amélioration proposée | Effort |
|---|---|---|---|
| 1 | **Menu desktop tronqué** : 18 entrées, pas de défilement (`layout.css:81`) | **fait au lot 0** : `overflow-y:auto` ; reste le regroupement et le repli | S |
| 2 | **Palette vide de données** (6 entrées seulement) | recherche serveur + clavier + récents (§3.B) | M |
| 3 | **Fiche projet sans passerelles modules** | filtres P1 + cartes de contexte | M |
| 4 | **Tableau de bord réel inutilisable** : salutation, rôle, compteurs géographiques — aucun KPI métier (`pages/app/dashboard.php`, 68 lignes) quand la maquette montre 4 indicateurs, 3 échéances, un graphique | Reprendre la maquette en données réelles : projets en cours, encours client, échéances 14 jours, heures de la semaine, dernières activités | M |
| 5 | **Aucun bouton « Nouveau » global en production** : le bouton et le tiroir d'actions rapides sont rendus **uniquement** en mode maquette (`layouts/app.php`, `if ($isDesignPreview)`) | **fait au lot 3** : bouton pour tous, actions réelles client / projet / devis / temps (pas de « dépense » : aucune fonction n'existe dans le noyau) | S |
| 6 | **Fiches sans liens sortants** (documents) | §3.F | S |
| 7 | **Le mode sombre est déclaré mais bridé** : `<meta name="color-scheme" content="light">` alors que `tokens.css` gère `light dark` → contrôles natifs et barres de défilement restent clairs en thème sombre | **fait au lot 0** : `light dark` + deux `theme-color` par média | S |
| 8 | **Filtres incohérents** : listes du noyau avec bouton « Filtrer » (soumission), certains écrans en filtrage instantané (`initFilters`) | Filtrage instantané partout, bouton supprimé, état dans l'URL | S |

### 4.2 Priorité moyenne

| # | Constat | Amélioration | Effort |
|---|---|---|---|
| 9 | 119 classes CSS (31 % du système) ne servent **qu'aux maquettes** : `project-tabs`, `document-preview`, `segmented-control`, `dropdown`, `week-chart`, `timer-card`… | Soit les utiliser dans l'app réelle (onglets de fiche projet, aperçu document), soit les retirer | M |
| 10 | CSS chargé en cascade d'`@import` (8 requêtes bloquantes, 128 Ko non minifiés) | Concaténer/minifier au déploiement, ou 8 `<link>` ordonnés + `preload` | S |
| 11 | 57 couleurs en dur + 110 `rgba()` hors jetons → `dark.css` doit rattraper 8 Ko d'exceptions | Ajouter jetons translucides (`--surface-glass`, `--overlay`) et vider `dark.css` | M |
| 12 | 38 `<th>` sur 371 sans `scope` (dont `documents/show.php` : 10) | ~~Ajout systématique~~ **fait au lot 0** ; reste `<caption>` en `u-sr-only` sur les tableaux de données | S |
| 13 | `aria-current="false"` (valeur invalide) dans `sidebar.php` et `tabbar.php` | **fait au lot 0** : attribut omis hors page courante | S |
| 14 | Tables de 42 rem minimum sur mobile : défilement horizontal systématique | Sous 760 px, basculer les listes en cartes empilées (le CSS `project-cards` existe déjà) | M |
| 15 | Aucun tri de colonnes | En-têtes `th` cliquables (`?tri=montant&sens=desc`), indicateur visuel | M |
| 16 | Pagination « précédent / suivant » seule | Ajouter « 1 … n », la taille de page et le total | S |
| 17 | Pas d'état de chargement sur les formulaires longs | `data-submitting` : bouton désactivé + libellé « Enregistrement… » | S |
| 18 | Aucune protection contre la perte de saisie | `beforeunload` si formulaire modifié (`data-dirty-guard`) | S |

### 4.3 Détails de finition

- Barre d'onglets mobile : libellés à 0,59 rem, tronqués en arabe → 0,68 rem et libellés courts
  revus (`nav.*_short`).
- En-tête mobile : 6 contrôles simultanés (menu, recherche, langue, horloge, déconnexion,
  initiales) ; sous 430 px, déplacer l'horloge dans le tiroir.
- Palette : libellé `⌘K` figé même sous Windows.
- `theme-color` unique (#F4F7FF) : prévoir la variante sombre.
- Tableaux : pas d'indice de défilement horizontal (ombre de bord).
- Toasts : `role="status"` + `aria-live="polite"` déjà présents — bon point, rien à faire.

---

## 5. Plan de mise en œuvre proposé

| Lot | Contenu | Effort | Effet sur la règle des 3 clics |
|---|---|---|---|
| **Lot 0 — correctifs** (1 journée) | défilement du menu, `color-scheme`, `aria-current`, `scope`, palette clavier/accents, liens des fiches document | S | supprime 3 échecs, rend 7 modules visibles |
| **Lot 1 — contexte** (3–5 jours) | filtres P1 + cartes de contexte dans projet / client / document + fil d'Ariane | M | **tous les trajets projet↔module passent à 1 clic** |
| **Lot 2 — recherche** (3 jours) — **livré** | `/api/recherche`, palette à sections, récents, raccourcis | M | destination atteinte en 2 frappes, 0 clic |
| **Lot 3 — coquille** (3 jours) — **livré** | menu groupé + mobile « Plus » + bouton global « Nouveau » | M | mobile : plus aucun trajet > 3 clics |
| **Lot 4 — tableau de bord** (3–4 jours) | KPI réels, échéances 14 jours, activité, heures | M | première page utile |
| **Lot 5 — garde-fou** (1–2 jours) | test PHPUnit de profondeur ≤ 3 + documentation | S | la règle ne peut plus régresser |

Les lots 0 à 3 suffisent à **garantir 3 clics partout** ; les lots 4 et 5 rendent le résultat
durable. Aucun de ces lots ne modifie les 12 modules livrés : le lot 1 ajoute des points
d'accroche que les modules consommeront, le lot 0 est purement correctif.

---

## 6. Annexes

### 6.1 Mesures relevées

| Mesure | Valeur |
|---|---|
| Entrées de menu avec 12 modules actifs | 18 |
| Hauteur estimée du menu | ≈ 1 130 px (desktop) / 1 152 px (rail tablette) |
| Feuilles CSS | 8 fichiers chargés, 128 Ko, chaîne de 7 `@import` |
| JavaScript | 48 Ko (`ui.js` 38 Ko, `documents.js` 10 Ko), modules ES natifs |
| Classes CSS définies / utilisées hors maquettes | 385 / 266 |
| Contrastes | minimum 4,61:1 (texte tertiaire) — conforme AA |
| Cibles tactiles | 44 px minimum — conforme |
| `th` sans `scope` | 38 sur 371 (mesure exacte : la première estimation comptait aussi les balises `<thead>`) |
| Routes GET | 37 (noyau, dont 4 techniques) + 12 modules |

### 6.2 Fichiers clés

- Navigation : `app/Services/ViewContext.php`, `app/Views/partials/{sidebar,topbar,tabbar,command-palette,page-header}.php`
- Réglages de rendu : `public/assets/css/{tokens,layout,pages,dark}.css`, `public/assets/js/ui.js`
- Maquette de référence : `app/Views/pages/dashboard.php`, `app/Views/pages/styleguide.php`
- Application réelle : `app/Views/pages/app/dashboard.php`, `app/Views/pages/{clients,projects,documents,time}/`,
  `modules/*/views/`

### 6.3 Livrable visuel

`docs/design/maquette-navigation.html` — maquette statique autonome (aucune dépendance) montrant,
côte à côte, la navigation actuelle et la navigation proposée : menu groupé défilant, fil
d'Ariane, cartes de contexte projet, palette enrichie, barre mobile avec « Plus ».

---

## 7. Lot 0 — correctifs livrés

Aucune vue métier n'est modifiée ; les douze modules restent intacts. Six correctifs :

| # | Correctif | Fichiers | Preuve |
|---|---|---|---|
| 1 | **Menu défilant** : la barre latérale ne coupe plus les derniers modules | `public/assets/css/layout.css` | `.sidebar-nav` porte `flex: 1 1 auto`, `min-block-size: 0`, `overflow-y: auto`, `overscroll-behavior: contain`, `scrollbar-width: thin`, `scrollbar-gutter: stable` ; la marque et le pied de barre sont en `flex: 0 0 auto` et ne se compriment plus |
| 2 | **Thèmes annoncés** : contrôles natifs et barres de défilement suivent le thème | `app/Views/layouts/app.php`, `app/Views/layouts/auth.php` | `color-scheme: light dark` + `theme-color` clair (`#F4F7FF`) et sombre (`#111821`) par média |
| 3 | **`aria-current` valide** | `app/Views/partials/sidebar.php`, `app/Views/partials/tabbar.php` | attribut émis seulement sur la page courante ; plus aucune valeur `false` |
| 4 | **En-têtes de tableau** : portée explicite pour les lecteurs d'écran | 7 vues (documents, clients, PDF) | 38 `<th>` complétés, 0 restant sans `scope` sur 371 |
| 5 | **Palette de commandes** : recherche réelle et pilotage complet au clavier | `public/assets/js/ui.js`, `app/Views/partials/command-palette.php`, `app/Views/partials/topbar.php` | accents ignorés (`normalizeSearch`), groupes vides masqués, `↑ ↓` `Début` `Fin` `Échap`, roving `tabindex`, un seul élément tabulable, raccourci affiché selon la plateforme (`⌘` / `Ctrl`) |
| 6 | **Fin des culs-de-sac** : client et projet d'un document mènent à leur fiche | `app/Models/DocumentRepository.php`, `app/Controllers/DocumentController.php`, `app/Views/pages/documents/{index,show}.php` | `client_id`/`project_id` sélectionnés ; liens affichés seulement si `clients.view` / `projects.view` |

### 7.1 Effet sur la règle des 3 clics

| Trajet | Avant | Après lot 0 |
|---|---|---|
| Module n° 12 depuis n'importe quel écran, fenêtre de 768 px | **inaccessible** | 2 clics |
| Client d'un document | cul-de-sac | 1 clic |
| Projet d'un document | cul-de-sac | 1 clic |
| Toute fiche de menu via la palette | 1 clic + saisie approximative | 2 frappes (0 clic) |

Restent hors lot 0 (lots 1 à 5) : passerelles projet ↔ modules, fil d'Ariane, recherche serveur
dans la palette, menu groupé, barre mobile « Plus », tableau de bord métier.

### 7.2 Garde-fous ajoutés

`tests/NavigationAccessibilityTest.php` fige les six correctifs : défilement du menu, palette au
clavier et sans accents, raccourci selon la plateforme, absence d'`aria-current` invalide, double
`color-scheme`, `scope` sur tous les `<th>` des vues, liens client/projet des documents et
sélection des identifiants de relation. Ces tests sont statiques : ni base de données, ni
navigateur.

### 7.3 Vérifications exécutées

| Contrôle | Résultat |
|---|---|
| Analyse syntaxique PHP (277 fichiers) | 0 erreur |
| Palette de commandes dans un DOM réel (jsdom) | 13 vérifications, 0 échec |
| Assertions de `NavigationAccessibilityTest` rejouées | 28 vérifications, 0 échec |
| Modules livrés touchés | aucun |

---

## 8. Lot 1 — passerelles contextuelles livrées

Le lot 1 supprime les derniers trajets de plus de trois clics : depuis une fiche projet, client ou
document, chaque module concerné se propose lui-même. Le noyau n'exécute aucune requête métier : il
applique un filtre et rend ce qu'on lui donne.

### 8.1 Contrat des trois points d'extension

| Filtre | Fiche | Contexte | Passerelles branchées |
|---|---|---|---|
| `project.detail.sections` | `/projects/{id}` | ligne `projects` | suivi-chantier, metre-quantitatif, honoraires-bareme, permis-batir, ged-versions, conformite-oat |
| `client.detail.sections` | `/clients/{id}` | ligne `clients` | ged-versions, portail-client, crm-opportunites |
| `document.detail.actions` | `/documents/{id}` | document (`document_type`, `status`) | relances-factures |

Entrée ajoutée : `key`, `label`, `url` (obligatoires) puis `meta`, `icon`, `tone`
(`info|success|warning|error|neutral`). Contrat complet pour les auteurs de modules : `docs/MODULES.md`.

### 8.2 Ce que voit l'utilisateur

| Fiche | Passerelle | Ce qui est affiché | Condition |
|---|---|---|---|
| Projet | Suivi de chantier | `4 visites · 2 réserves`, teinte d'alerte si réserve ouverte | `projects.view` |
| Projet | Métré et DQE | `18 postes` | `projects.view` |
| Projet | Honoraires et barèmes | `3 décomptes` | `projects.view` ou `documents.view` |
| Projet | Permis de bâtir | `PC-2026-014 · En instruction`, teinte selon le statut | `projects.view` + dossier existant |
| Projet | Dépôt documentaire | `12 pièces` | `documents.view/manage`, `projects.view` ou `clients.view` |
| Projet | Conformité OAT | attestation d'assurance du projet | `projects.view` |
| Client | Dépôt documentaire | `n pièces` (filtre `client=`) | idem |
| Client | Espace client | `n liens actifs`, teinte de succès si un lien court | `clients.view` ou `projects.view` |
| Client | Prospects et opportunités | `n opportunités` hors gagnées/perdues | `clients.view` |
| Document | Relances de factures | `reste 1 250,500 DT` | facture **émise** dont le solde est > 0 |

Un module absent ou désactivé ne laisse ni trou ni espace vide ; un utilisateur sans droit ne voit
rien du module. Le module ne crée aucune permission : il réutilise les droits du noyau.

### 8.3 Fil d'Ariane

`partials/breadcrumb.php` rend un `nav` discret au-dessus du titre de la fiche, avec
`aria-current="page"` sur le dernier élément et un chevron décoratif qui s'inverse en arabe
(`[dir="rtl"]`). Rien n'est rendu en dessous de deux entrées.

| Fiche | Fil |
|---|---|
| Projet | Tableau de bord › Projets › *client* › projet |
| Client | Tableau de bord › Clients › client |
| Document | Tableau de bord › Documents › *client* › numéro |

### 8.4 Effet sur la règle des 3 clics

| Trajet | Avant | Après lot 1 |
|---|---|---|
| Projet → suivi de chantier | menu (2) + liste (1) + projet (1) = **4** | 1 clic depuis la fiche projet |
| Projet → permis de bâtir (dossier du projet) | **5** | 1 clic |
| Client → espace client | 4 | 1 clic |
| Document/facture → relances | 4 | 1 clic |
| Projet → retour à la liste | 1 (bouton) | 1 (bouton) **ou** 1 (fil) |
| Document → client | 1 (lot 0) | 1 (lot 0) **ou** 1 (fil) |

### 8.5 Vérifications exécutées

| Contrôle | Résultat |
|---|---|
| Analyse syntaxique PHP | 288 fichiers, 0 erreur |
| Logique réelle des 9 passerelles, exécutée en PHP 8.2 avec doublures de dépôts | 40 vérifications FR, 0 échec ; 40 en AR, 0 échec |
| Rendu réel des partials (`breadcrumb`, `context-links`, `sidebar`, `page-header`) | conformes, y compris en `rtl` |
| Parité des clés FR/AR des 12 modules | 1 598 clés de chaque côté, 9 modules + noyau sans écart |
| Icônes des passerelles | 9 noms sur 9 présents dans `public/assets/img/icons.svg` |
| Routes ciblées par les passerelles | 9 sur 9 existantes, paramètres de requête conformes |
| Modules livrés touchés | 9 (ajout d'une passerelle et de son compteur, aucun changement de comportement) |

---

## 9. Correctif — barre du bas sur les pages de liste

Signalement : en mode téléphone, la barre de navigation du bas est décalée ou coupée sur Clients et Documents,
alors qu'elle est correcte sur les autres pages.

### 9.1 Diagnostic instrumenté

Trois mesures ont été nécessaires ; les deux premières pistes étaient fausses.

1. **Balisage** — rendu réel des trois pages en PHP 8.2, puis comparaison : le `<nav class="mobile-tabbar">` est un
   enfant direct de `<body>`, mêmes six entrées, même ordre. Seul l'`aria-current` change. La barre n'est donc pas
   en cause.
2. **Largeur du contenu** — première hypothèse : la piste implicite `auto` de `.card.stack-md` élargie par le tableau
   de 42 rem. C'était une fragilité réelle (corrigée : `grid-template-columns: minmax(0, 1fr)`), mais **pas la cause** :
   après correction, la carte mesurait bien 366 px… et la barre restait à 945 px de large.
3. **Cause réelle, mesurée dans Chromium** — page Clients chargée en viewport 390 px : `document.documentElement.clientWidth`
   = 390, `body.scrollWidth` = 390, `.table-wrap` = 322 px **mais `documentElement.scrollWidth` = 945 px et
   `window.innerWidth` = 945**. La zone de débordement du tableau, contenue dans `.table-wrap` en `overflow: auto`,
   remonte malgré tout jusqu'à la zone de débordement du document : sur mobile, le **viewport s'élargit à la largeur
   du tableau**. Or `.mobile-tabbar` est en `position: fixed`, donc ancrée au viewport : elle est calculée sur 945 px
   et se retrouve décalée puis coupée. Pages sans tableau large : viewport = 390 px, barre correcte. `overflow-x: clip`
   sur `html` et `body` n'y change rien (vérifié) : le clip ne s'applique pas à la zone de débordement du viewport.

### 9.2 Correctif retenu

```
.table-wrap { contain: layout; }   /* + inline-size/max-inline-size 100 %, min-inline-size 0, overflow-x auto */
.document-lines-wrap { contain: layout; }
```

`contain: layout` isole la mise en page du conteneur : sa zone de débordement ne remonte plus jusqu'au document, le
viewport reprend la largeur de l'écran et la barre fixe reste collée en bas, pleine largeur. Vérifié en comparaison
A/B dans Chromium : sans confinement 945 px (Clients) et 819 px (Documents) ; avec, 390 px sur les deux.

Effet de bord traité : `contain: layout` empêche un bloc de se répartir sur plusieurs pages à l'impression ;
`print.css` pose donc `contain: none` et `overflow: visible` sur ces deux conteneurs. Aucune autre conséquence :
un défilement interne, un arrondi et une ombre restent rendus normalement.

### 9.3 Mesures après correction

| Largeur | Clients | Documents | Projets |
|---|---|---|---|
| 320 px | viewport 320, barre 320 à x = 0, collée en bas | idem | idem |
| 360 px | idem | idem | idem |
| 390 px | idem | idem | idem |
| 430 px | idem | idem | idem |

Contrôles complémentaires : le tableau défile toujours dans son cadre (`scrollWidth` 969 px pour `clientWidth` 322 px),
la barre garde ses six onglets et ne recouvre pas les messages, aucune page ne défile horizontalement.

### 9.4 Garde-fous

`tests/ResponsiveLayoutTest.php` → `testWideTablesNeverWidenThePageNorMoveTheFixedTabbar` : clip horizontal sur `html`
et `body`, piste `minmax(0, 1fr)` sur les piles, conteneur de tableau compressible, `contain: layout` sur les deux
conteneurs de tableau, `contain: none` à l'impression, barre ancrée au viewport. 15 assertions, rejouées hors PHPUnit :
15 / 15.

## 10. Lot 2 — recherche globale livrée

La palette ne se contente plus de filtrer douze entrées : elle interroge le serveur et renvoie de vraies fiches.
`GET /api/recherche?q=` répond en JSON, derrière `AuthMiddleware`.

### 10.1 Contrat de la route

| Élément | Règle |
|---|---|
| Méthode | `GET /api/recherche?q=<terme>` |
| Longueur | 2 caractères minimum (`SearchController::MIN_LENGTH`), 80 au maximum ; les caractères de contrôle sont retirés et les espaces compactés |
| Catégories | clients (`clients.view`), projets (`projects.view`), documents (les mêmes droits que l'entrée « Documents ») |
| Limite | 8 fiches par catégorie (`SearchRepository::LIMIT`), tri par pertinence de préfixe puis par date |
| Échappement | requêtes préparées ; `%`, `_` et `\` neutralisés (`SearchRepository::likeTerm`) — « 50 % » reste une chaîne |
| Cache | `Cache-Control: private, no-store` : jamais de résultat d'un autre utilisateur en cache |
| Extensions | filtre `search.results` ; le module permis-batir ajoute sa section (contrat dans `docs/MODULES.md`) |

Réponse : `{"query": "villa", "tooShort": false, "total": 4, "groups": [{"key": "clients", "label": "Clients", "items": [{"title", "meta", "url", "icon"}]}]}`.

### 10.2 Palette

- Combobox et listbox réelles : le champ garde le focus, `aria-activedescendant` désigne la ligne active,
  chaque résultat est un `role="option"` cliquable ; un région `aria-live` annonce « n résultat(s) ».
- Clavier : `↓` `↑` `Home` `End` `Entrée` `Échap` ; `⌘K` / `Ctrl K` ouvre la palette, le libellé du raccourci suit la plateforme.
- **Récents** : les cinq dernières fiches consultées, dédupliquées, conservées en `sessionStorage` (donc effacées
  à la fermeture de l'onglet). La fiche courante est déclarée par le layout à partir de son fil d'Ariane.
- Anti-rebond de 180 ms, requêtes obsolètes annulées (`AbortController`), résultats mis en cache pour la session de la page.
- Les résultats sont construits par `textContent` : un intitulé contenant `<script>` s'affiche comme du texte.

### 10.3 Mesures

Palette pilotée dans Chromium, réponse de l'API simulée : 3 groupes affichés (Clients, Projets, Documents),
`aria-activedescendant` posé sur le premier résultat, une seule requête pour « villa », `↓` puis `Entrée`
ouvre bien le deuxième résultat (`/projects/11`), section « Récents » peuplée après visite d'une fiche,
aucun débordement à 390 px, rendu correct en arabe (`dir="rtl"`) et en thème sombre.

### 10.4 Garde-fous

`tests/SearchApiTest.php` — 10 cas : route protégée, permissions et filtre dans le contrôleur, trois requêtes
préparées avec `LIMIT 8` et échappement, normalisation du terme, balisage combobox/listbox/`aria-live`,
appel `fetch` avec `AbortController` et anti-rebond, `textContent` sans `innerHTML`, cinq récents dédupliqués,
fiche courante exposée par le layout, libellés FR/AR et contribution du module permis.

## 11. Lot 3 — coquille de navigation livrée

### 11.1 Menu latéral groupé

Le contrat `sidebar.items` gagne une clé **facultative** `section` (`pilotage`, `production`, `finance`,
`conformite`, `administration`) ; une entrée sans section rejoint « Modules externes ». Les entrées du noyau la
déclarent dans `ViewContext`, les douze modules livrés aussi (leur version passe à 1.1.0, permis-batir à 1.2.0).
`NavigationSections` répartit, ordonne et omet les sections vides.

| Section | Entrées (12 modules actifs) |
|---|---|
| Pilotage | Tableau de bord, Planning d'équipe |
| Production | Clients, Projets, Temps, Suivi de chantier, Métré et DQE, Permis de bâtir, Dépôt documentaire |
| Finance | Documents, Honoraires et barèmes, Relances de factures, Fiscalité tunisienne, Comptabilité |
| Conformité | Conformité OAT, Prospects et opportunités, Espace client |
| Administration | Réglages |
| Modules externes | ce qui ne déclare pas de section |

Chaque section est un `ul` nommé (`aria-labelledby`) : le lecteur d'écran annonce « Production, liste de quatre
éléments ». Le filtre n'est appliqué **qu'une fois**, dans le layout : menu, barre d'onglets et feuille mobile
partagent la même liste, sans doublon d'entrée.

Deux collisions d'icônes du rail sont corrigées : `crm-opportunites` passe à `chart` (au lieu de `people`, déjà pris
par Clients) et `planning-equipe` à `timer` (au lieu de `calendar`). Le libellé reste accessible au survol (`title`)
et par `aria-label` dans le rail de 761 à 1 100 px.

### 11.2 Barre mobile : quatre onglets + « Plus »

`Accueil · Clients · Projets · Docs · Plus`. Le cinquième onglet ouvre `partials/mobile-menu.php` : une feuille
pleine largeur avec les mêmes sections, un champ de filtre (normalisation d'accents identique à la palette), un état
vide et l'horloge du cabinet. « Plus » porte `aria-current` quand la page courante n'est pas dans les quatre onglets
(Temps, Réglages, module), donc l'utilisateur sait toujours où il se trouve. Budget de clics : une entrée de module
reste à **2 gestes** (Plus, entrée) au lieu de 2 clics par le tiroir latéral, et la barre gagne une place.

Les libellés remontent de 0,59 rem à **0,68 rem** (0,55 rem sous 390 px auparavant) ; « Docs » remplace « Documents »
pour que rien ne soit tronqué à 320 px. Le bouton « Plus » occupe sa colonne (`inline-size: 100%`) comme les liens.

### 11.3 Bouton « Nouveau » pour tous

Le bouton de l'en-tête et le tiroir d'actions rapides ne dépendent plus de `$isDesignPreview`. `ViewContext` fournit
les quatre actions réellement disponibles selon les droits — `/clients/new`, `/projects/new`, `/documents/new`,
`/time` — et le bouton n'apparaît que si la liste n'est pas vide. « Dépense » n'existe pas dans le noyau : aucune
action factice n'est proposée.

L'ajout du bouton a obligé à resserrer l'en-tête : les actions acceptent de céder de la place (`flex: 0 1 auto`) et
la recherche se réduit jusqu'à 8 rem, le contexte se tronque avec une ellipse. Sous 430 px, l'horloge quitte
l'en-tête (elle est dans la feuille « Plus ») : l'en-tête tient sur une ligne de 62 px de 320 px à 1440 px, sans
retirer aucun contrôle.

### 11.4 Mesures

Palette de navigation pilotée dans Chromium (rendu réel des vues, aucune base) :

| Contrôle | Résultat |
|---|---|
| Menu groupé 1440 px | 4 sections nommées (core seul dans le jeu de test), 6 liens, un `ul` par section |
| Bouton « Nouveau » | présent hors maquette, ouvre le tiroir, 4 actions aux URLs réelles |
| Barre mobile 390 / 320 px | 5 cellules égales (61 px à 320), libellés à 10,88 px, aucun tronqué, aucun débordement |
| Feuille « Plus » | collée au bas, largeur = écran, focus dans le filtre, `aria-expanded` synchronisé |
| Filtre de la feuille | « doc » ne laisse que Documents (1 groupe), « zzz » affiche l'état vide |
| En-tête | 1440/1280/1024/900/760/390/320 px : une ligne, `scrollWidth` = largeur de la barre |
| Thème | rendu vérifié en clair, sombre et arabe (`dir="rtl"`) |

### 11.5 Garde-fous

`tests/NavigationShellTest.php` — 11 cas : sections déclarées par le noyau, ordre et repli du service, menu latéral
nommé, filtre appliqué une seule fois, barre quatre onglets + « Plus », feuille complète, bouton « Nouveau » hors
maquette avec routes réelles, icônes uniques, sections déclarées par les modules, lisibilité des libellés, traductions
FR/AR.
