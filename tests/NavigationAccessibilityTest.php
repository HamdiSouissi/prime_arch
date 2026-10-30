<?php
declare(strict_types=1);

namespace Tests;

use PHPUnit\Framework\TestCase;

/**
 * Garde-fous de navigation et d'accessibilité (lot 0 de l'audit UX).
 *
 * Quatre régressions sont figées ici, toutes constatées sur l'application réelle :
 *  - le menu latéral ne défilait pas : au-delà d'une dizaine de modules, les entrées
 *    suivantes étaient coupées et donc inaccessibles ;
 *  - `aria-current="false"` était émis sur chaque lien non courant, valeur interdite ;
 *  - la palette de commandes ne répondait ni aux flèches, ni aux accents ;
 *  - les fiches de document citaient le client et le projet sans lien vers leur fiche.
 *
 * Les tests sont statiques (lecture du balisage, des feuilles et du script) : ils
 * s'exécutent sans navigateur et sans base de données.
 */
final class NavigationAccessibilityTest extends TestCase
{
    private static function file(string $relativePath): string
    {
        return (string) file_get_contents(BASE_PATH . $relativePath);
    }

    /** @return array<int,string> */
    private static function viewFiles(): array
    {
        $files = [];
        $roots = [BASE_PATH . '/app/Views', BASE_PATH . '/modules'];
        foreach ($roots as $root) {
            $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root));
            foreach ($iterator as $file) {
                if ($file instanceof \SplFileInfo && $file->isFile() && $file->getExtension() === 'php'
                    && str_contains(strtolower($file->getPathname()), '/views/')
                ) {
                    $files[] = $file->getPathname();
                }
            }
        }

        return $files;
    }

    /** Le menu doit défiler : sinon les derniers modules sont hors d'atteinte. */
    public function testSidebarNavigationScrolls(): void
    {
        $css = self::file('/public/assets/css/layout.css');
        preg_match('/(?<![\w-])\.sidebar-nav\s*\{([^}]*)\}/', $css, $rule);
        self::assertNotSame([], $rule, 'La règle .sidebar-nav est introuvable.');

        self::assertMatchesRegularExpression('/overflow-y:\s*auto/', $rule[1]);
        self::assertMatchesRegularExpression('/min-block-size:\s*0/', $rule[1]);
    }

    /** La palette de commandes doit se piloter au clavier et ignorer les accents. */
    public function testCommandPaletteSupportsKeyboardAndAccents(): void
    {
        $script = self::file('/public/assets/js/ui.js');
        $start = (int) strpos($script, 'function initCommandPalette()');
        $end = (int) strpos($script, 'function initShortcutHints()');
        self::assertGreaterThan(0, $start);
        self::assertGreaterThan($start, $end);
        $palette = substr($script, $start, $end - $start);

        self::assertStringContainsString('normalizeSearch(', $palette);
        self::assertStringContainsString('"ArrowDown"', $palette);
        self::assertStringContainsString('"ArrowUp"', $palette);
        self::assertStringContainsString('command-list__label', $palette);
        self::assertStringContainsString('tabIndex', $palette);
    }

    /** Le raccourci affiché doit suivre la plateforme (⌘ sur macOS, Ctrl ailleurs). */
    public function testShortcutHintFollowsPlatform(): void
    {
        self::assertStringContainsString('data-shortcut-modifier', self::file('/app/Views/partials/topbar.php'));
        self::assertStringContainsString('initShortcutHints', self::file('/public/assets/js/ui.js'));
    }

    /** `aria-current` n'accepte pas la valeur « false » : l'attribut doit être absent. */
    public function testAriaCurrentIsOmittedWhenNotCurrent(): void
    {
        foreach (['/app/Views/partials/sidebar.php', '/app/Views/partials/tabbar.php'] as $partial) {
            $markup = self::file($partial);
            self::assertStringNotContainsString("'false'", $markup, $partial);
            self::assertStringContainsString('aria-current="page"', $markup, $partial);
        }
    }

    /** Les deux thèmes sont annoncés, sinon les contrôles natifs restent clairs en sombre. */
    public function testLayoutsDeclareBothColorSchemes(): void
    {
        foreach (['/app/Views/layouts/app.php', '/app/Views/layouts/auth.php'] as $layout) {
            $markup = self::file($layout);
            self::assertStringContainsString('name="color-scheme" content="light dark"', $markup, $layout);
            self::assertStringContainsString('prefers-color-scheme: dark', $markup, $layout);
        }
    }

    /** Chaque cellule d'en-tête annonce sa portée. */
    public function testTableHeadersDeclareScope(): void
    {
        $missing = [];
        foreach (self::viewFiles() as $file) {
            if (preg_match_all('/<th(?![a-zA-Z])(?![^>]*\bscope=)[^>]*>/', (string) file_get_contents($file), $matches)) {
                $missing[] = str_replace(BASE_PATH . '/', '', $file);
            }
        }

        self::assertSame([], $missing, 'En-têtes de tableau sans scope : ' . implode(', ', $missing));
    }

    /** Le client et le projet d'un document mènent à leur fiche, sous réserve du droit. */
    public function testDocumentViewsLinkToClientAndProject(): void
    {
        $index = self::file('/app/Views/pages/documents/index.php');
        $show = self::file('/app/Views/pages/documents/show.php');
        foreach ([$index, $show] as $markup) {
            self::assertStringContainsString("url('/clients/'", $markup);
            self::assertStringContainsString("url('/projects/'", $markup);
            self::assertStringContainsString('canViewClients', $markup);
            self::assertStringContainsString('canViewProjects', $markup);
        }

        $controller = self::file('/app/Controllers/DocumentController.php');
        self::assertSame(2, substr_count($controller, "'canViewClients' => Auth::can('clients.view')"));
        self::assertSame(2, substr_count($controller, "'canViewProjects' => Auth::can('projects.view')"));
    }

    /** La liste des documents doit exposer les identifiants nécessaires aux liens. */
    public function testDocumentListSelectsRelationIdentifiers(): void
    {
        $repository = self::file('/app/Models/DocumentRepository.php');
        self::assertStringContainsString('d.client_id, d.project_id', $repository);
    }
}
