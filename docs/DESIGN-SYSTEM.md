# Système de design Prime Arch

## Intention

Prime Arch est l’espace de travail d’un cabinet d’architecture tunisien. L’interface reprend les principes d’Apple HIG — clarté, hiérarchie, retour immédiat, commandes familières — avec une interprétation légère de Liquid Glass : surfaces translucides, flou discret, reflets doux et profondeur mesurée. Le thème livré reste **clair par défaut** ; les préférences `prefers-color-scheme: dark` du système activent automatiquement une palette sombre, sans bascule manuelle ni thème sombre initial. Les aperçus `/design/*` sont des maquettes DEV ONLY, alimentées par des exemples fictifs et sans écriture métier.

Les écrans sont conçus pour une lecture rapide des dossiers, des phases de projet, du temps et des montants en dinars tunisiens. Les montants de démonstration utilisent trois décimales. Les taux fiscaux et mentions PDF sont indicatifs ; une validation par un expert-comptable reste nécessaire.

## Architecture CSS

`public/assets/css/app.css` est le point d’entrée. Il pré-déclare les couches puis importe les feuilles dans cet ordre :

1. `tokens.css` — palette, typographie, espacements, formes, ombres et mouvements ;
2. `base.css` — fond clair, typographie de base, focus, LTR/RTL et médias de préférence ;
3. `layout.css` — shell, sidebar, topbar, tabbar et responsive ;
4. `components.css` — primitives et composants réutilisables ;
5. `pages.css` — compositions propres aux écrans ;
6. `dark.css` — adaptations automatiques limitées à `prefers-color-scheme: dark` ;
7. `print.css` — sortie imprimée claire, sans chrome d’application.

Le reset est déclaré directement dans `app.css`. Toutes les feuilles sont placées dans des `@layer` afin que les styles des modules puissent s’insérer sans augmenter artificiellement la spécificité.

### Tokens principaux

| Catégorie | Variables | Usage |
|---|---|---|
| Surfaces | `--color-canvas`, `--color-surface`, `--color-surface-solid`, `--color-surface-muted` | Fond mesh presque blanc, verre dépoli et surfaces opaques de repli |
| Texte | `--color-ink`, `--color-ink-secondary`, `--color-ink-tertiary` | Texte gris-bleu foncé ; pas de noir pur pour le texte courant |
| Marque | `--color-primary` (`#2E67F2`, proche de `#2F6BFF` avec contraste AA), `--color-indigo` (`#6C5CFF`), `--gradient-primary` | Actions principales et éléments de navigation |
| Accents | `--color-turquoise`, `--color-coral`, `--color-pink`, `--color-sun`, `--color-mint` | Repères visuels, jamais seuls pour transmettre une information |
| Statuts | `--color-success`, `--color-warning`, `--color-error`, `--color-info` et leurs variantes `*-tint` | Badges et retours sémantiques sur fonds pastel |
| Typographie | `--font-system`, `--font-size-*` | Pile système uniquement, sans CDN ni font-face externe |
| Formes | `--radius-*`, `--tap-target` | Cartes 20–24 px, boutons 14 px, cibles 44 px, pills |
| Profondeur | `--shadow-soft`, `--shadow-card`, `--shadow-float` | Ombres multicouches très diffuses |
| Mouvement | `--transition-fast`, `--transition-normal` | 160–220 ms, courbe de réponse souple |

La palette claire reste celle de repli. `tokens.css` surcharge les surfaces, textes, accents sémantiques et ombres sous `prefers-color-scheme: dark`; `dark.css` adapte les surfaces restées historiques en valeurs littérales. Aucun choix utilisateur n’est mémorisé et aucune dépendance JavaScript n’est requise.

La police utilise la pile système `-apple-system`, `BlinkMacSystemFont`, SF Pro, Segoe UI, Roboto et Noto Sans Arabic. L’échelle suit les repères HIG : Large Title 34 px, Title 28/22/20 px, Headline 17 px, Body 17 px, Callout 16 px, Subhead 15 px, Footnote 13 px et Caption 12 px. Les montants utilisent `font-variant-numeric: tabular-nums`.

### Principes de layout

- **Grand écran** : sidebar en verre dépoli de 256 px environ, topbar persistante, contenu centré sur une largeur maximale de 92 rem.
- **Tablette (≤ 1100 px)** : sidebar compacte, icônes visibles et libellés disponibles aux technologies d’assistance et en infobulle.
- **Mobile (≤ 760 px)** : navigation inférieure à six destinations, menu compact ouvrable, zones sûres iOS et titres condensés après défilement.
- Les espacements et positions utilisent les axes logiques (`margin-block`, `padding-inline`, `inset-inline-start`, `border-block-end`, etc.). Le sens du document est transmis par `Translator::direction()`.
- `prefers-reduced-motion: reduce` neutralise les animations et le défilement doux. `prefers-reduced-transparency: reduce` rend les surfaces opaques et retire les flous.

## Composants et conventions d’usage

### Primitives

- `.card`, `.glass-card`, `.panel` : surface blanche ou translucide avec bordure 1 px et rayon généreux.
- `.button` avec variantes `--primary`, `--secondary`, `--tinted`, `--ghost`, `--danger`, `--icon` et `--small` : conserver une cible de 44 px (sauf action compacte explicitement secondaire).
- `.field`, `.field__label`, `.field__hint`, `.field__control` : libellé visible, erreur/hint explicite, focus clair.
- `.badge--success|warning|error|info|neutral` : associer la couleur à un libellé, ne jamais coder un statut par couleur seule.
- `.progress` : renseigner `role="progressbar"`, `aria-valuenow`, `aria-valuemin` et `aria-valuemax`.
- `.data-table` : en-têtes `<th scope="col">`, texte aligné au début de ligne, conteneur `.table-wrap` défilant sur petits écrans.
- `.timeline`, `.empty-state`, `.toast`, `.segmented-control`, `.tabs`, `.switch` : utiliser les rôles et attributs ARIA illustrés dans la bibliothèque `/design/styleguide`.

### Composants PHP paramétrés

Les partiels sont de petits composants de présentation ; ils ne contiennent pas de données métier. Ils reçoivent leurs valeurs en arguments et échappent chaque valeur dynamique. Le layout transmet `workspace` à la sidebar et à la topbar, ainsi que `userProfile` à la topbar ; les données de démonstration sont fournies par le contrôleur, pas codées dans ces composants :

```php
<?php
use App\Core\View;

View::partial('partials/stat-card', [
    'label' => 'Projets actifs',
    'value' => '12',
    'icon' => 'briefcase',
    'tone' => 'blue',
    'note' => 'sur les 30 derniers jours',
]);

View::partial('partials/badge', [
    'label' => $statusLabel,
    'tone' => 'success',
]);
?>
```

`page-header.php`, `progress.php` et `empty-state.php` suivent le même contrat. Ne pas transmettre de HTML non échappé à ces partiels. Les appels de `csrf_field()` sont réservés aux formulaires POST et la fonction produit déjà un champ échappé.

### Icônes et illustrations

`public/assets/img/icons.svg` contient le sprite de traits arrondis (61 symboles). Une icône s’emploie comme suit :

```html
<svg class="icon" aria-hidden="true" focusable="false">
  <use href="<?= e(asset('img/icons.svg')) ?>#calendar"></use>
</svg>
```

Les illustrations `empty-*.svg` restent vectorielles, légères et cohérentes avec les couleurs d’accent. Utiliser un `alt` descriptif si l’image apporte une information ; sinon un `alt=""`.

### Recherche et commandes

- La palette globale est disponible par le bouton de recherche ou `⌘K` / `Ctrl+K`. Elle interroge
  `GET /api/recherche` (clients, projets, documents, puis les sections des modules) et affiche les résultats
  groupés, les cinq fiches récentes et les commandes de navigation. Le champ est une combobox : `↑` `↓`
  `Home` `End` déplacent `aria-activedescendant`, `Entrée` ouvre, `Échap` ferme, et une région `aria-live`
  annonce le nombre de résultats. Les éléments sont construits par `textContent` (aucun `innerHTML`).
- Le bouton `Nouveau` ouvre une sheet d’actions rapides.
- Les tabs, switches, vues grille/liste, saisie de champs personnalisés, toast, impressions et chronomètre fonctionnent en JavaScript vanilla. Les formulaires de démonstration affichent un toast et n’enregistrent rien.

## PDF mPDF

`app/Views/pdf/base.php` centralise l’en-tête du cabinet, le destinataire, la référence, les mentions et la pagination `{PAGENO} / {nbpg}`. `quote.php`, `invoice.php` et `contract.php` fournissent le contenu. `public/assets/css/pdf.css` n’emploie ni flexbox, ni grid, ni variables CSS, ni `backdrop-filter` : tableaux et styles sobres compatibles mPDF, contraste imprimable en noir et blanc, direction héritée de `dir="rtl"` pour les documents arabes. Les montants, TVA, timbre fiscal et retenue à la source des exemples sont des données de démonstration.

## Accessibilité — checklist de livraison

- [x] Thème clair par défaut ; le mode sombre suit automatiquement `prefers-color-scheme: dark`, et les contrôles natifs suivent `color-scheme: light dark`.
- [x] Texte normal gris-bleu sur surfaces blanches/pastel, ratio visé ≥ 4,5:1 ; focus clavier net de 3 px.
- [x] Contrôles principaux et navigation mobile d’au moins 44 px ; boutons icon-only avec nom accessible.
- [x] Navigation clavier, lien d’évitement, titres hiérarchisés, labels de formulaires, tableaux sémantiques et annonces de toast.
- [x] Préférences `prefers-reduced-motion` et `prefers-reduced-transparency` prises en compte.
- [x] Écrans utilisables en `dir="rtl"`, y compris les espacements, l’ordre logique et les champs numériques/contacts LTR.
- [x] États vides illustrés mais accompagnés d’un titre et d’une explication textuelle.

Les couples principaux clairs ont été contrôlés par calcul de luminance relative WCAG (sRGB) : `#25334B` sur blanc = 12,70:1 ; `#53627A` sur blanc = 6,18:1 ; `#62718A` sur blanc = 4,94:1 ; `#2458E8` sur blanc = 5,78:1 ; `#176B49`, `#805900`, `#B4234A` et `#1D4FA8` sur blanc dépassent 6:1. En sombre, les couples principaux calculés dépassent également 4,5:1 : `#EDF3FB` sur `#111821` = 15,99:1 ; `#C4D0E0` sur `#1D2938` = 9,43:1 ; `#AAB8CC` sur `#1D2938` = 7,32:1 ; `#B4CAFF` sur `#1D2938` = 9,00:1 ; les paires des statuts succès, avertissement, erreur et information sur leurs fonds sombres dépassent 6,5:1. Le texte blanc sur les extrémités du dégradé des boutons (`#2D65F5` et `#6C5CFF`) atteint au minimum 4,55:1 ; ne pas éclaircir ces couleurs sur les boutons remplis.

Pour auditer une nouvelle paire de tokens, calculer pour chaque canal sRGB `c/12.92` si `c ≤ 0,04045`, sinon `((c+0,055)/1,055)^2,4`, puis `L = 0,2126R + 0,7152G + 0,0722B`. Le ratio est `(Lclair + 0,05) / (Lfoncé + 0,05)` et doit être ≥ 4,5 pour du texte normal.

## Ajouter le style d’un module

Un module hérite automatiquement des tokens et composants parce que son écran s’insère sous `layouts/app`. Il ne doit pas redéfinir les couleurs globales, le reset, les polices ou les comportements RTL. Préfixer ses classes avec le slug et garder les styles dans `storage/modules/<slug>/assets/`; les charger via la route authentifiée `/module-assets/<slug>?file=css/module.css`.

```css
@layer components {
    .module-clients__summary {
        display: grid;
        gap: var(--space-4);
        padding-block: var(--space-5);
        padding-inline: var(--space-5);
        border: 1px solid var(--color-border);
        border-radius: var(--radius-card);
        color: var(--color-ink);
        background: var(--color-surface);
        box-shadow: var(--shadow-soft);
    }
}
```

Réutiliser `.card`, `.button`, `.badge`, `.field`, `.progress` et `.data-table` avant d’en créer une variante. Garder les propriétés directionnelles logiques et tester chaque écran avec `dir="rtl"`. Les modules ne chargent ni CDN ni bibliothèque UI externe ; préférer le sprite d’icônes partagé et les interactions JavaScript natives. Une feuille de module doit rester scoped (`.module-<slug> …`) et ne pas modifier les éléments du cœur.
## Interface bilingue (français / arabe)

- `app/Core/Translator.php` expose la langue active, les langues prises en charge et la direction du texte.
- Le sélecteur partagé `app/Views/partials/language-switcher.php` poste vers `/locale` ; `App\Controllers\LocaleController`
  valide la langue, la conserve en session et renvoie vers un chemin interne sûr.
- Le sélecteur est présent dans la barre supérieure (`partials/topbar.php`) et sur l'écran de connexion (`layouts/auth.php`).
- Toute nouvelle chaîne visible doit être ajoutée dans `lang/fr.php` **et** `lang/ar.php` avec la même clé.
- Les éléments directionnels utilisent les propriétés logiques (`margin-inline`, `inset-inline`, `padding-block`) afin que
  le passage à `dir="rtl"` reste correct sans feuille de style dédiée.
- Les messages produits côté navigateur sont exposés par `app/Views/partials/i18n.php` (JSON `#app-i18n`) et lus dans
  `public/assets/js/ui.js` via la fonction `t()`.
### Montants et devise en arabe

- Toujours passer par `money_format_tnd()` : jamais de `number_format()` ni de « DT » codé en dur dans une vue.
- Le séparateur de milliers est une espace insécable (U+00A0, classe bidi `CS`) convertie automatiquement : une espace
  simple (classe `WS`) coupe le nombre en plusieurs blocs que l'algorithme bidi réordonne (`2 380,000` devenait `380,000 2`).
- Le symbole suit la langue active via `config('tunisia.currency.symbols')` : `DT` en français, `د.ت` en arabe ; il est
  placé après le montant dans l'ordre logique, ce qui correspond au sens de lecture droite à gauche.
- Les montants sont formatés depuis la chaîne `DECIMAL` d'origine (pas de conversion en flottant) pour conserver les millimes.

### Mise en page responsive (en-tête, barre d’onglets, contenus)

- **En-tête toujours complet** : `.topbar__actions` ne se comprime jamais (`flex: 0 0 auto` pour chaque action) et le
  contexte (`.topbar__context`) se réduit à sa place. Le titre courant est repris par `.page-header`, aussi l’en-tête
  ne conserve-t-il que les commandes sur mobile.
- **Réduction avant masquage** : sous 1100 px la recherche passe en icône (libellé masqué, `aria-label` conservé) ;
  sous 760 px l’horloge n’affiche plus que l’heure ; sous 430 px les boutons, le sélecteur de langue et la bulle
  d’initiales se réduisent. Aucune action de l’en-tête n’est retirée de l’interface, et le sélecteur de langue reste
  visible de 320 px à 1440 px.
- **Marque du produit** : `config/brand.php` porte le nom (`Prime Architecture`), l'éditeur
  (`Prime Vision Solutions`), l'adresse publique (`primevisionsolutions.fr`) et les fichiers du logo. La barre
  latérale n'affiche que **le logo et le nom** : l'éditeur, le site et le copyright sont regroupés en pied de page.
- **Pied de page légal** (`partials/brand-footer.php`, rendu par les deux layouts sous le contenu) : à gauche
  `© <année> Prime Vision Solutions — Tous droits réservés` (libellé `app.copyright`, millésime pris sur l'horloge du
  serveur), à droite l'adresse du site en lien externe (`rel="noopener noreferrer"`, `unicode-bidi: isolate` pour
  l'adresse latine en arabe). Il reste collé au bas de la fenêtre sur une page courte (`.app-content` en
  `flex: 1 1 auto` dans `.app-main`, `.auth-shell` en colonne pour la connexion) et, sur mobile, porte lui-même la
  réserve de la barre d'onglets fixe (`calc(6.5rem + env(safe-area-inset-bottom))`).
- **Sélecteur de langue** : un formulaire POST par langue (`partials/language-switcher.php`), valeur dans un champ
  caché, jamais de bouton désactivé. Il est en `flex: 0 0 auto` et ne peut donc pas être écrasé par la recherche.
- **Bulle d’initiales** : c’est un lien (`<a class="topbar-avatar">`) vers les réglages accessibles à l’utilisateur
  (`profileUrl`, calculé par `ViewContext`) ou, à défaut, vers le tableau de bord. `aria-label` explicite et focus natif.
- **Barre d’onglets mobile** : `.mobile-tabbar` est ancrée (`position: fixed`, `inset-block-end: 0`), sur une grille
  `grid-auto-flow: column` + `grid-auto-columns: minmax(0, 1fr)` : une colonne par entrée, quel que soit le nombre
  d’entrées, avec `env(safe-area-inset-*)`. Le layout fournit la même navigation partout, y compris sur les pages 404
  qui n’en déclarent pas. Ordre des plans : contenu `app-content` < `.page-header` collant (12) < `.topbar` (15) <
  `.mobile-tabbar` (35) < voile du menu (40) < tiroir latéral (45).
- **Aucun débordement horizontal** : `html` et `body` portent `overflow-x: clip` ; `.app-content` et ses blocs sont en
  `min-inline-size: 0` sous 760 px ; les grilles et formulaires repassent en une colonne. Les tableaux larges
  (`.data-table`, `.audit-table`, `.document-lines`) gardent une largeur minimale mais défilent **dans** `.table-wrap` :
  la page, elle, ne défile jamais horizontalement. `.table-wrap` porte `inline-size: 100%`, `max-inline-size: 100%` et
  `min-inline-size: 0` à toutes les largeurs.
- **Les piles (`.stack-md`, `.stack-lg`) déclarent `grid-template-columns: minmax(0, 1fr)`.** Une piste de grille
  implicite (`auto`) peut être élargie par le contenu d'un enfant : la piste explicite la rend compressible.
  `.mobile-tabbar` porte en complément `inline-size: 100%` et `max-inline-size: 100%`.
- **Les conteneurs de tableau portent `contain: layout`** (`.table-wrap`, `.document-lines-wrap`). Sans confinement,
  la zone de débordement d'un tableau en `overflow-x: auto` remonte jusqu'à la zone de débordement du document : sur
  mobile le **viewport s'élargit à la largeur du tableau** (mesuré : 945 px au lieu de 390 px) et toute barre
  `position: fixed` — ancrée au viewport — apparaît décalée et coupée. `overflow-x: clip` sur `html`/`body` ne suffit
  pas. À l'impression, `print.css` rétablit `contain: none` pour laisser le tableau se répartir sur les pages.
- **Tests de garde** : `tests/ResponsiveLayoutTest.php` vérifie ces invariants sur le balisage et les feuilles de style.
