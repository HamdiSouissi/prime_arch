<?php
declare(strict_types=1);

namespace Tests;

use PHPUnit\Framework\TestCase;

/**
 * Garde-fous de l'identité affichée : marque du produit en tête, mentions légales en pied.
 *
 * Trois régressions sont figées ici :
 *  - la barre latérale et l'écran de connexion affichaient un nom de produit écrit en dur
 *    dans la vue, impossible à changer sans toucher au balisage ;
 *  - le logo affiché était un emplacement de remplacement, pas la marque livrée ;
 *  - l'éditeur, l'adresse du site et le copyright encombraient l'en-tête de la barre latérale :
 *    ils doivent rester regroupés en pied de page, avec le millésime courant.
 *
 * Les tests sont statiques : ils lisent la configuration, le balisage, les feuilles de style et
 * les fichiers de langue. Aucune base de données, aucun navigateur.
 */
final class BrandIdentityTest extends TestCase
{
    private static function file(string $relativePath): string
    {
        return (string) file_get_contents(BASE_PATH . $relativePath);
    }

    /** Dernier bloc de règles correspondant au sélecteur exact. */
    private static function lastRule(string $css, string $selector): string
    {
        preg_match_all('/(?<![\w-])' . preg_quote($selector, '/') . '\s*\{([^}]*)\}/', $css, $matches);

        return $matches[1] === [] ? '' : (string) end($matches[1]);
    }

    /** @return array<string,mixed> */
    private static function brand(): array
    {
        $brand = require BASE_PATH . '/config/brand.php';
        self::assertIsArray($brand);

        return $brand;
    }

    public function testBrandConfigurationCarriesProductVendorAndSite(): void
    {
        $brand = self::brand();

        self::assertSame('Prime Architecture', $brand['product'] ?? null);
        self::assertSame('Prime Vision Solutions', $brand['vendor'] ?? null);
        self::assertSame('primevisionsolutions.fr', $brand['site'] ?? null);
        self::assertSame('https://primevisionsolutions.fr', $brand['site_url'] ?? null);
    }

    public function testBrandLogoFilesExist(): void
    {
        $brand = self::brand();

        foreach (['logo', 'logo_mark'] as $key) {
            $relative = 'public/assets/' . ltrim((string) ($brand[$key] ?? ''), '/');
            self::assertFileExists(BASE_PATH . '/' . $relative, $key);
            // Le logo est un raster : SVG non autorisé pour la marque livrée.
            self::assertMatchesRegularExpression('/\.png$/', $relative);
        }

        self::assertFileExists(BASE_PATH . '/public/assets/img/favicon.png');
    }

    public function testSidebarAndLoginReadTheBrandFromConfiguration(): void
    {
        foreach (['/app/Views/partials/sidebar.php', '/app/Views/pages/auth/login.php'] as $view) {
            $markup = self::file($view);
            self::assertStringContainsString("config('brand.product'", $markup, $view);
            self::assertStringContainsString("config('brand.logo'", $markup, $view);
            self::assertStringNotContainsString('img/logo-placeholder.svg', $markup, $view);
        }
    }

    public function testSidebarHeaderShowsOnlyTheProductName(): void
    {
        $markup = self::file('/app/Views/partials/sidebar.php');

        self::assertStringContainsString('sidebar-brand__product', $markup);
        self::assertStringContainsString('sidebar-brand__logo', $markup);
        // L'éditeur, l'adresse et le copyright ont quitté l'en-tête de la barre latérale.
        self::assertStringNotContainsString('sidebar-brand__caption', $markup);
        self::assertStringNotContainsString('sidebar-brand__site', $markup);
        self::assertStringNotContainsString("config('brand.site'", $markup);
        self::assertStringNotContainsString("config('brand.vendor'", $markup);

        $login = self::file('/app/Views/pages/auth/login.php');
        self::assertStringNotContainsString('auth-aside__brand-vendor', $login);
        self::assertStringNotContainsString('auth-aside__brand-site', $login);
    }

    public function testLegalFooterCarriesVendorSiteAndCopyright(): void
    {
        $footer = self::file('/app/Views/partials/brand-footer.php');

        self::assertStringContainsString("config('brand.vendor'", $footer);
        self::assertStringContainsString("config('brand.site'", $footer);
        self::assertStringContainsString("config('brand.site_url'", $footer);
        self::assertStringContainsString("__('app.copyright'", $footer);
        self::assertStringContainsString("date('Y')", $footer, 'Le millésime suit l’horloge du serveur.');
        self::assertStringContainsString('rel="noopener noreferrer"', $footer);
        self::assertStringContainsString('<footer class="app-footer">', $footer);

        // Le pied de page est rendu par les deux layouts, sous le contenu principal.
        foreach (['/app/Views/layouts/app.php', '/app/Views/layouts/auth.php'] as $layout) {
            self::assertStringContainsString(
                "partial('partials/brand-footer')",
                self::file($layout),
                $layout
            );
        }

        $appLayout = self::file('/app/Views/layouts/app.php');
        self::assertGreaterThan(
            (int) strpos($appLayout, '</main>'),
            (int) strpos($appLayout, "partial('partials/brand-footer')"),
            'Le pied de page vient après le contenu principal.'
        );
    }

    public function testLegalFooterStaysAtTheBottomAndClearsTheMobileTabbar(): void
    {
        $layout = self::file('/public/assets/css/layout.css');

        self::assertStringContainsString('flex: 1 1 auto', self::lastRule($layout, '.app-content'), 'Le contenu pousse le pied de page en bas.');

        $footer = self::lastRule($layout, '.app-footer');
        self::assertStringContainsString('flex-wrap: wrap', $footer);
        self::assertStringContainsString('padding-inline', $footer);

        // Sur mobile, le dernier élément de la colonne dégage la barre d'onglets fixe.
        self::assertMatchesRegularExpression(
            '/\.app-main > \.app-footer\s*\{[^}]*calc\(6\.5rem \+ env\(safe-area-inset-bottom\)\)/',
            $layout
        );

        // L'adresse latine garde son sens de lecture dans une interface arabe.
        self::assertStringContainsString('unicode-bidi: isolate', self::lastRule($layout, '.app-footer__site'));
    }

    public function testCopyrightAndSiteLabelsAreTranslatedInBothLanguages(): void
    {
        foreach (['fr' => 'Tous droits réservés', 'ar' => 'جميع الحقوق محفوظة'] as $locale => $expected) {
            $translations = self::file('/lang/' . $locale . '.php');
            self::assertStringContainsString("'app.copyright' =>", $translations, 'Copyright manquant en ' . $locale);
            self::assertStringContainsString($expected, $translations, 'Mention de droits en ' . $locale);
            self::assertStringContainsString(':year', $translations, 'Millésime paramétré en ' . $locale);
            self::assertStringContainsString(':vendor', $translations, 'Éditeur paramétré en ' . $locale);
            self::assertStringContainsString("'app.brand_site_label' =>", $translations);
            // Les anciennes clés de l'en-tête ne doivent pas survivre.
            self::assertStringNotContainsString("'app.brand_vendor'", $translations);
            self::assertStringNotContainsString("'app.brand_site' =>", $translations);
        }
    }

    public function testBrowserTabUsesTheProductLogo(): void
    {
        foreach (['/app/Views/layouts/app.php', '/app/Views/layouts/auth.php'] as $layout) {
            $markup = self::file($layout);
            self::assertStringContainsString('rel="icon"', $markup, $layout);
            self::assertStringContainsString('img/favicon.png', $markup, $layout);
            self::assertStringContainsString('rel="apple-touch-icon"', $markup, $layout);
        }
    }

    public function testDefaultApplicationNameFollowsTheProduct(): void
    {
        $config = self::file('/config/app.php');
        self::assertStringContainsString("Env::get('APP_NAME', 'Prime Architecture')", $config);

        // Plus aucune référence à l'ancien nom dans le code applicatif livré.
        $legacy = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator(BASE_PATH . '/app', \FilesystemIterator::SKIP_DOTS)
        );
        foreach ($iterator as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }
            // « Prime Architecture » contient « Prime Arch » : on retire le nom complet avant
            // de chercher l'ancien, qui n'apparaît que suivi d'une virgule ou entre guillemets.
            $needle = str_replace('Prime Architecture', '', $file->getContents());
            if (str_contains($needle, 'Prime Arch,') || str_contains($needle, "'Prime Arch'")) {
                $legacy[] = $file->getPathname();
            }
        }
        self::assertSame([], $legacy, 'Ancien nom encore présent : ' . implode(', ', $legacy));
    }
}
