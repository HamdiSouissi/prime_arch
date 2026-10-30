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
| 1 | **Menu desktop tronqué** : 18 entrées, pas de défilement (`layout.css:81`) | `overflow-y:auto` + groupes + repli | S |
| 2 | **Palette vide de données** (6 entrées seulement) | recherche serveur + clavier + récents (§3.B) | M |
| 3 | **Fiche projet sans passerelles modules** | filtres P1 + cartes de contexte | M |
| 4 | **Tableau de bord réel inutilisable** : salutation, rôle, compteurs géographiques — aucun KPI métier (`pages/app/dashboard.php`, 68 lignes) quand la maquette montre 4 indicateurs, 3 échéances, un graphique | Reprendre la maquette en données réelles : projets en cours, encours client, échéances 14 jours, heures de la semaine, dernières activités | M |
| 5 | **Aucun bouton « Nouveau » global en production** : le bouton et le tiroir d'actions rapides sont rendus **uniquement** en mode maquette (`layouts/app.php`, `if ($isDesignPreview)`) | Rendre le bouton visible pour tous, avec menu : client / projet / devis / temps / dépense | S |
| 6 | **Fiches sans liens sortants** (documents) | §3.F | S |
| 7 | **Le mode sombre est déclaré mais bridé** : `<meta name="color-scheme" content="light">` alors que `tokens.css` gère `light dark` → contrôles natifs et barres de défilement restent clairs en thème sombre | `content="light dark"` + `theme-color` par média | S |
| 8 | **Filtres incohérents** : listes du noyau avec bouton « Filtrer » (soumission), certains écrans en filtrage instantané (`initFilters`) | Filtrage instantané partout, bouton supprimé, état dans l'URL | S |

### 4.2 Priorité moyenne

| # | Constat | Amélioration | Effort |
|---|---|---|---|
| 9 | 119 classes CSS (31 % du système) ne servent **qu'aux maquettes** : `project-tabs`, `document-preview`, `segmented-control`, `dropdown`, `week-chart`, `timer-card`… | Soit les utiliser dans l'app réelle (onglets de fiche projet, aperçu document), soit les retirer | M |
| 10 | CSS chargé en cascade d'`@import` (8 requêtes bloquantes, 128 Ko non minifiés) | Concaténer/minifier au déploiement, ou 8 `<link>` ordonnés + `preload` | S |
| 11 | 57 couleurs en dur + 110 `rgba()` hors jetons → `dark.css` doit rattraper 8 Ko d'exceptions | Ajouter jetons translucides (`--surface-glass`, `--overlay`) et vider `dark.css` | M |
| 12 | 103 `<th>` sur 436 sans `scope` (dont `documents/show.php` : 12) | Ajout systématique + `<caption>` en `u-sr-only` sur les tableaux de données | S |
| 13 | `aria-current="false"` (valeur invalide) dans `sidebar.php` et `tabbar.php` | Omettre l'attribut quand ce n'est pas la page courante | S |
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
| **Lot 2 — recherche** (3 jours) | `/api/recherche`, palette à sections, récents, raccourcis | M | destination atteinte en 2 frappes, 0 clic |
| **Lot 3 — coquille** (3 jours) | menu groupé + mobile « Plus » + bouton global « Nouveau » | M | mobile : plus aucun trajet > 3 clics |
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
| `th` sans `scope` | 103 sur 436 |
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
