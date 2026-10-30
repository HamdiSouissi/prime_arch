<?php
declare(strict_types=1);

namespace Tests;

use PHPUnit\Framework\TestCase;

/**
 * Garde-fous de la mise en page responsive.
 *
 * Trois régressions sont figées ici :
 *  - l'en-tête compressait ses actions jusqu'à faire disparaître le sélecteur de langue ;
 *  - la bulle d'initiales n'était qu'un bouton décoratif, sans destination ;
 *  - la barre d'onglets mobile dépendait du nombre d'entrées fournies par la page ;
 *  - un tableau large élargissait la page et décalait cette barre fixe sur mobile.
 *
 * Les tests sont statiques (lecture du balisage et des feuilles de style) : ils
 * s'exécutent sans navigateur et sans base de données.
 */
final class ResponsiveLayoutTest extends TestCase
{
    private static function file(string $relativePath): string
    {
        return (string) file_get_contents(BASE_PATH . $relativePath);
    }

    /** Tous les blocs de règles correspondant au sélecteur exact. */
    private static function allRules(string $css, string $selector): string
    {
        preg_match_all('/(?<![\w-])' . preg_quote($selector, '/') . '\s*\{([^}]*)\}/', $css, $matches);

        return implode("\n", $matches[1]);
    }

    /** Dernier bloc de règles correspondant au sélecteur exact. */
    private static function lastRule(string $css, string $selector): string
    {
        preg_match_all('/(?<![\w-])' . preg_quote($selector, '/') . '\s*\{([^}]*)\}/', $css, $matches);

        return $matches[1] === [] ? '' : (string) end($matches[1]);
    }

    public function testTopbarActionsNeverShrinkTheLanguageSwitcher(): void
    {
        $layout = self::file('/public/assets/css/layout.css');
        $actions = self::allRules($layout, '.topbar__actions');

        self::assertNotSame('', $actions, 'Le bloc mobile de .topbar__actions doit exister.');
        self::assertStringNotContainsString('flex: 1;', $actions, 'Les actions ne doivent pas absorber la largeur disponible.');
        self::assertStringContainsString('flex: 1 1 auto', $actions);
        self::assertStringContainsString('flex-wrap: wrap', $actions, 'Les actions passent à la ligne plutôt que d’être rognées.');
        self::assertStringContainsString('justify-content: flex-end', $actions);
    }

    public function testLanguageSwitcherIsNeverHidden(): void
    {
        $components = self::file('/public/assets/css/components.css');
        $layout = self::file('/public/assets/css/layout.css');

        self::assertStringContainsString('flex: 0 0 auto', self::lastRule($components, '.language-switcher'));
        foreach (['/public/assets/css/components.css' => $components, '/public/assets/css/layout.css' => $layout] as $path => $css) {
            preg_match_all('/\.language-switcher\s*\{([^}]*)\}/', $css, $matches);
            foreach ($matches[1] as $block) {
                self::assertStringNotContainsString('display: none', $block, 'Le sélecteur de langue ne doit jamais être masqué (' . $path . ').');
            }
        }
    }

    public function testEveryHeaderElementIsReducedRatherThanHiddenOnMobile(): void
    {
        $layout = self::file('/public/assets/css/layout.css');

        foreach (['.topbar-info', '.topbar-search', '.topbar-avatar', '.language-switcher'] as $selector) {
            $block = self::lastRule($layout, $selector);
            self::assertNotSame('', $block, 'Règle mobile attendue pour ' . $selector);
            self::assertStringNotContainsString('display: none', $block, $selector . ' doit rester visible sur mobile.');
        }

        // Le seul élément de contexte masqué est le titre, repris par .page-header.
        self::assertMatchesRegularExpression(
            '/\.topbar__context\s*\{[^}]*display:\s*none/',
            $layout,
            'Le titre de l’en-tête est repris par l’en-tête de page.'
        );
    }

    public function testInitialsBubbleIsAnAccessibleLink(): void
    {
        $topbar = self::file('/app/Views/partials/topbar.php');

        self::assertMatchesRegularExpression('/<a class="topbar-avatar" href="<\?= e\(\$profileUrl\) \?>"[^>]*aria-label="<\?= e\(\$profileLabel/', $topbar);
        self::assertDoesNotMatchRegularExpression('/<a class="topbar-avatar"[^>]*data-toast-message/', $topbar);
        self::assertMatchesRegularExpression('/<a class="topbar-avatar"[^>]*>.*?<\/a>/s', $topbar);
        self::assertMatchesRegularExpression('/if \(\(\$userProfile\[.initials.\] \?\? .{2}\) !== .{2}\)/', $topbar);
    }

    public function testProfileTargetIsProvidedByTheViewsContext(): void
    {
        $context = self::file('/app/Services/ViewContext.php');
        $layout = self::file('/app/Views/layouts/app.php');

        self::assertStringContainsString("'profileUrl' => \$settingsAvailable ? \$settingsUrl : url('/dashboard')", $context);
        self::assertStringContainsString("'profileUrl' => \$profileUrl", $layout);
        self::assertStringContainsString('new ViewContext()', $layout, 'Les pages sans navigation héritent du menu standard.');
        self::assertStringContainsString('Auth::check()', $layout);
    }

    public function testTabbarIsFixedAndIndependentFromTheItemCount(): void
    {
        $layout = self::file('/public/assets/css/layout.css');
        $tabbar = self::allRules($layout, '.mobile-tabbar');

        self::assertStringContainsString('position: fixed', $tabbar);
        self::assertStringContainsString('inset-block-end: 0', $tabbar);
        self::assertStringContainsString('grid-auto-flow: column', $tabbar, 'Une colonne par entrée, quel que soit le nombre d’entrées.');
        self::assertStringContainsString('grid-auto-columns: minmax(0, 1fr)', $tabbar);
        self::assertStringNotContainsString('repeat(6', $tabbar, 'Le gabarit ne doit pas dépendre d’un nombre fixe.');
        self::assertStringContainsString('env(safe-area-inset-bottom)', $tabbar);
    }

    public function testTabbarStaysBelowTheMobileDrawer(): void
    {
        $layout = self::file('/public/assets/css/layout.css');

        preg_match('/\.mobile-tabbar\s*\{[^}]*z-index:\s*(\d+)/', $layout, $tabbar);
        preg_match('/\.app-shell\.is-mobile-menu-open \.mobile-menu-backdrop\s*\{[^}]*z-index:\s*(\d+)/', $layout, $backdrop);
        preg_match('/\.app-shell\.is-mobile-menu-open \.sidebar\s*\{[^}]*z-index:\s*(\d+)/', $layout, $sidebar);

        self::assertNotSame([], $tabbar);
        self::assertNotSame([], $backdrop);
        self::assertNotSame([], $sidebar);
        self::assertLessThan((int) $backdrop[1], (int) $tabbar[1], 'Le voile du menu doit couvrir la barre d’onglets.');
        self::assertLessThan((int) $sidebar[1], (int) $backdrop[1]);
    }

    public function testAppContentKeepsRoomForTheFixedTabbar(): void
    {
        $layout = self::file('/public/assets/css/layout.css');

        self::assertMatchesRegularExpression(
            '/\.app-content\s*\{[^}]*padding-block-end:\s*calc\(6\.5rem \+ env\(safe-area-inset-bottom\)\)/',
            $layout
        );
    }

    public function testMobileContentCannotOverflowTheViewport(): void
    {
        $pages = self::file('/public/assets/css/pages.css');
        $base = self::file('/public/assets/css/base.css');
        $components = self::file('/public/assets/css/components.css');

        // Le corps de page ne défile jamais horizontalement.
        self::assertStringContainsString('overflow-x: clip', $base);

        // Les conteneurs et leurs enfants peuvent se comprimer jusqu'à la largeur écran.
        self::assertStringContainsString('.app-content > *', $pages);
        self::assertMatchesRegularExpression('/\.app-content[^{]*\{[^}]*min-inline-size:\s*0/s', $pages);
        self::assertStringContainsString('max-inline-size: 100%', $pages);

        // Les tableaux larges défilent dans leur propre conteneur, pas dans la page.
        self::assertStringContainsString('overflow-x: auto', self::lastRule($components, '.table-wrap'));
        self::assertStringContainsString('max-inline-size: 100%', self::lastRule($pages, '.table-wrap'));
    }

    /**
     * Une page qui contient un tableau large (Clients, Documents) ne doit jamais déborder
     * horizontalement : c'est ce débordement qui décalait et coupait la barre d'onglets fixe
     * sur mobile, alors que son balisage est identique partout.
     */
    public function testWideTablesNeverWidenThePageNorMoveTheFixedTabbar(): void
    {
        $base = self::file('/public/assets/css/base.css');
        $layout = self::file('/public/assets/css/layout.css');
        $components = self::file('/public/assets/css/components.css');
        $utilities = self::file('/public/assets/css/app.css');

        // 1. Ni le document ni le corps de page ne défilent horizontalement.
        self::assertMatchesRegularExpression('/html\s*\{[^}]*overflow-x:\s*clip/s', $base);
        self::assertMatchesRegularExpression('/body\s*\{[^}]*overflow-x:\s*clip/s', $base);

        // 2. Les cartes empilées sont des grilles : la piste se comprime au lieu de suivre
        //    le contenu (un tableau de 42 rem élargissait la carte sur les pages de liste).
        foreach (['.stack-md', '.stack-lg'] as $selector) {
            self::assertStringContainsString('minmax(0, 1fr)', self::lastRule($utilities, $selector), $selector);
        }

        // 3. Le tableau large défile dans son conteneur, pas dans la page.
        $wrap = self::allRules($components, '.table-wrap');
        self::assertStringContainsString('overflow-x: auto', $wrap);
        self::assertStringContainsString('min-inline-size: 0', $wrap);
        self::assertStringContainsString('max-inline-size: 100%', $wrap);

        // 4. La barre reste ancrée à la fenêtre, pas à la largeur du contenu.
        $tabbar = self::allRules($layout, '.mobile-tabbar');
        self::assertStringContainsString('position: fixed', $tabbar);
        self::assertStringContainsString('inset-inline: 0', $tabbar);
        self::assertStringContainsString('inline-size: 100%', $tabbar);
        self::assertStringContainsString('max-inline-size: 100%', $tabbar);
    }

    public function testProfileLabelsAreTranslatedInBothLanguages(): void
    {
        foreach (['fr', 'ar'] as $locale) {
            $translations = self::file('/lang/' . $locale . '.php');
            self::assertStringContainsString("'common.profile' =>", $translations);
            self::assertStringContainsString("'common.profile_open' =>", $translations);
        }
    }
}
