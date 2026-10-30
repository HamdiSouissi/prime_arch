<?php
declare(strict_types=1);

namespace Tests;

use PHPUnit\Framework\TestCase;

/**
 * Garde-fous de l'identité affichée : marque du produit, éditeur et site public.
 *
 * Trois régressions sont figées ici :
 *  - la barre latérale et l'écran de connexion affichaient un nom de produit écrit en dur
 *    dans la vue, impossible à changer sans toucher au balisage ;
 *  - le logo affiché était un emplacement de remplacement, pas la marque livrée ;
 *  - l'éditeur et l'adresse du site n'étaient affichés nulle part.
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
            self::assertStringContainsString("config('brand.site'", $markup, $view);
            self::assertStringContainsString("config('brand.site_url'", $markup, $view);
            self::assertStringNotContainsString('img/logo-placeholder.svg', $markup, $view);
        }
    }

    public function testSidebarShowsProductThenVendorThenSite(): void
    {
        $markup = self::file('/app/Views/partials/sidebar.php');

        $product = strpos($markup, 'sidebar-brand__product');
        $vendor = strpos($markup, 'sidebar-brand__caption');
        $site = strpos($markup, 'sidebar-brand__site');
        self::assertNotFalse($product);
        self::assertNotFalse($vendor);
        self::assertNotFalse($site);
        self::assertLessThan($vendor, $product, 'Le produit précède l’éditeur.');
        self::assertLessThan($site, $vendor, 'L’éditeur précède l’adresse du site.');

        // Deux liens ne peuvent pas être imbriqués : l'adresse du site est hors du lien de marque.
        self::assertStringContainsString('<div class="sidebar-brand-block">', $markup);
        self::assertStringContainsString('rel="noopener noreferrer"', $markup);
    }

    public function testBrandLinesNeverTruncateAndStayLegible(): void
    {
        $layout = self::file('/public/assets/css/layout.css');

        // Dernier bloc de règles du sélecteur exact : la première occurrence peut être une
        // règle partagée (`.a, .b { … }`) qui ne décrit pas le style propre de l'élément.
        foreach (['.sidebar-brand__caption', '.sidebar-brand__site'] as $selector) {
            $rules[$selector] = self::lastRule($layout, $selector);
            self::assertNotSame('', $rules[$selector], $selector);
        }

        foreach ($rules as $rule) {
            // Petites mais lisibles (≥ 0,65 rem) et jamais rognées par une ellipse.
            self::assertMatchesRegularExpression('/font-size:\s*0?\.6[5-9]rem|font-size:\s*0\.7/', $rule);
            self::assertStringNotContainsString('text-overflow: ellipsis', $rule);
            self::assertStringNotContainsString('white-space: nowrap', $rule);
        }

        // L'adresse latine garde son sens de lecture dans une interface arabe.
        self::assertStringContainsString('unicode-bidi: isolate', $rules['.sidebar-brand__site']);
    }

    public function testVendorLabelIsTranslatedInBothLanguages(): void
    {
        foreach (['fr' => 'Produit de :vendor', 'ar' => 'منتج :vendor'] as $locale => $expected) {
            $translations = self::file('/lang/' . $locale . '.php');
            self::assertStringContainsString(
                "'app.brand_vendor' => '" . $expected . "'",
                $translations,
                'Libellé éditeur manquant en ' . $locale
            );
            self::assertStringContainsString("'app.brand_site' =>", $translations);
            self::assertStringContainsString("'app.brand_site_label' =>", $translations);
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
            $contents = $file->getContents();
            // « Prime Architecture » contient « Prime Arch » : on exclut donc explicitement
            // le nom complet avant de chercher l'ancien.
            $needle = str_replace('Prime Architecture', '', $contents);
            if (str_contains($needle, 'Prime Arch')
                && (str_contains($needle, 'Prime Arch,') || str_contains($needle, "'Prime Arch'") || str_contains($needle, 'de Prime Arch'))
            ) {
                $legacy[] = $file->getPathname();
            }
        }
        self::assertSame([], $legacy, 'Ancien nom encore présent : ' . implode(', ', $legacy));
    }
}
