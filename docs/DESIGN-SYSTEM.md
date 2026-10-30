# Système de design Prime Arch

## Intention

Prime Arch est l’espace de travail d’un cabinet d’architecture tunisien. L’interface reprend les principes d’Apple HIG — clarté, hiérarchie, retour immédiat, commandes familières — avec une interprétation légère de Liquid Glass : surfaces translucides, flou discret, reflets doux et profondeur mesurée. Le thème livré est **clair uniquement**. Les aperçus `/design/*` sont des maquettes DEV ONLY, alimentées par des exemples fictifs et sans écriture métier.

Les écrans sont conçus pour une lecture rapide des dossiers, des phases de projet, du temps et des montants en dinars tunisiens. Les montants de démonstration utilisent trois décimales. Les taux fiscaux et mentions PDF sont indicatifs ; une validation par un expert-comptable reste nécessaire.

## Architecture CSS

`public/assets/css/app.css` est le point d’entrée. Il pré-déclare les couches puis importe les feuilles dans cet ordre :

1. `tokens.css` — palette, typographie, espacements, formes, ombres et mouvements ;
2. `base.css` — fond clair, typographie de base, focus, LTR/RTL et médias de préférence ;
3. `layout.css` — shell, sidebar, topbar, tabbar et responsive ;
4. `components.css` — primitives et composants réutilisables ;
5. `pages.css` — compositions propres aux écrans ;
6. `print.css` — sortie imprimée, sans chrome d’application.

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

- La palette globale est disponible par le bouton de recherche ou `⌘K` / `Ctrl+K`. Elle propose des liens filtrables au clavier et se ferme avec `Échap`.
- Le bouton `Nouveau` ouvre une sheet d’actions rapides.
- Les tabs, switches, vues grille/liste, saisie de champs personnalisés, toast, impressions et chronomètre fonctionnent en JavaScript vanilla. Les formulaires de démonstration affichent un toast et n’enregistrent rien.

## PDF mPDF

`app/Views/pdf/base.php` centralise l’en-tête du cabinet, le destinataire, la référence, les mentions et la pagination `{PAGENO} / {nbpg}`. `quote.php`, `invoice.php` et `contract.php` fournissent le contenu. `public/assets/css/pdf.css` n’emploie ni flexbox, ni grid, ni variables CSS, ni `backdrop-filter` : tableaux et styles sobres compatibles mPDF, contraste imprimable en noir et blanc, direction héritée de `dir="rtl"` pour les documents arabes. Les montants, TVA, timbre fiscal et retenue à la source des exemples sont des données de démonstration.

## Accessibilité — checklist de livraison

- [x] Thème clair uniquement ; le méta `color-scheme` est `light` et aucune bascule sombre automatique n’est activée.
- [x] Texte normal gris-bleu sur surfaces blanches/pastel, ratio visé ≥ 4,5:1 ; focus clavier net de 3 px.
- [x] Contrôles principaux et navigation mobile d’au moins 44 px ; boutons icon-only avec nom accessible.
- [x] Navigation clavier, lien d’évitement, titres hiérarchisés, labels de formulaires, tableaux sémantiques et annonces de toast.
- [x] Préférences `prefers-reduced-motion` et `prefers-reduced-transparency` prises en compte.
- [x] Écrans utilisables en `dir="rtl"`, y compris les espacements, l’ordre logique et les champs numériques/contacts LTR.
- [x] États vides illustrés mais accompagnés d’un titre et d’une explication textuelle.

Les couples principaux ont été contrôlés par calcul de luminance relative WCAG (sRGB) : `#25334B` sur blanc = 12,70:1 ; `#53627A` sur blanc = 6,18:1 ; `#62718A` sur blanc = 4,94:1 ; `#2458E8` sur blanc = 5,78:1 ; `#176B49`, `#805900`, `#B4234A` et `#1D4FA8` sur blanc dépassent 6:1. Le texte blanc sur les extrémités du dégradé des boutons (`#2D65F5` et `#6C5CFF`) atteint au minimum 4,55:1 ; ne pas éclaircir ces couleurs sur les boutons remplis.

Pour auditer une nouvelle paire de tokens, calculer pour chaque canal sRGB `c/12.92` si `c ≤ 0,04045`, sinon `((c+0,055)/1,055)^2,4`, puis `L = 0,2126R + 0,7152G + 0,0722B`. Le ratio est `(Lclair + 0,05) / (Lfoncé + 0,05)` et doit être ≥ 4,5 pour du texte normal.

## Ajouter le style d’un module

Un module hérite automatiquement des tokens et composants parce que son écran s’insère sous `layouts/app`. Il ne doit pas redéfinir les couleurs globales, le reset, les polices ou les comportements RTL. Préfixer ses classes avec le slug et garder les styles dans `modules/<slug>/assets/`.

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
