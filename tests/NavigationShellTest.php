<?php
declare(strict_types=1);

namespace Tests;

use PHPUnit\Framework\TestCase;

/**
 * Coquille de navigation (lot 3 de l'audit UX) : menu groupé par métier, barre mobile
 * « quatre destinations + Plus », feuille de navigation filtrable et bouton « Nouveau »
 * disponible hors mode maquette.
 */
final class NavigationShellTest extends TestCase
{
    private static function file(string $path): string
    {
        $contents = file_get_contents(BASE_PATH . $path);
        self::assertIsString($contents, "Fichier introuvable : {$path}");

        return $contents;
    }

    /** @return array<int,string> */
    private static function modules(): array
    {
        $modules = [];
        foreach (glob(BASE_PATH . '/modules/*/Module.php') ?: [] as $file) {
            $modules[] = $file;
        }
        self::assertNotSame([], $modules, 'Aucun module trouvé.');

        return $modules;
    }

    /** Chaque entrée du noyau déclare sa section : plus de liste unique de dix-huit entrées. */
    public function testCoreNavigationDeclaresSections(): void
    {
        $context = self::file('/app/Services/ViewContext.php');

        self::assertSame(
            6,
            substr_count($context, "'section' => NavigationSections::"),
            'Les six entrées du noyau doivent porter une section.'
        );
        foreach (['PILOTAGE', 'PRODUCTION', 'FINANCE', 'ADMINISTRATION'] as $constant) {
            self::assertStringContainsString('NavigationSections::' . $constant, $context, $constant);
        }
    }

    /** Les sections ont un ordre stable et une section de repli pour les extensions. */
    public function testSectionsHaveStableOrderAndFallback(): void
    {
        $service = self::file('/app/Services/NavigationSections.php');

        self::assertStringContainsString('self::PILOTAGE,', $service);
        self::assertMatchesRegularExpression(
            '/return \[\s*self::PILOTAGE,\s*self::PRODUCTION,\s*self::FINANCE,\s*self::CONFORMITE,\s*self::ADMINISTRATION,\s*self::EXTENSIONS,\s*\];/',
            $service,
            'L’ordre du menu doit être explicite : du quotidien vers l’administration.'
        );
        self::assertStringContainsString("return self::CORE_SECTIONS[\$key] ?? self::EXTENSIONS;", $service);
        // Une section vide ne produit pas de titre orphelin.
        self::assertStringContainsString('if ($buckets[$key] === []) {', $service);
    }

    /** Le menu latéral rend une liste nommée par section, et ne filtre plus lui-même. */
    public function testSidebarRendersNamedSections(): void
    {
        $sidebar = self::file('/app/Views/partials/sidebar.php');

        self::assertStringContainsString('NavigationSections::group($navigationItems)', $sidebar);
        self::assertStringContainsString('aria-labelledby="<?= e($labelId) ?>"', $sidebar);
        self::assertStringContainsString('sidebar-list--modules', $sidebar);
        self::assertStringContainsString('aria-current="page"', $sidebar);
        // Le filtre est appliqué une seule fois, dans le layout : sinon les modules
        // apparaîtraient deux fois (menu + feuille mobile).
        self::assertStringNotContainsString("applyFilters('sidebar.items'", $sidebar);
    }

    /** Le filtre n'est appliqué qu'une fois et alimente les trois surfaces de navigation. */
    public function testLayoutAppliesNavigationFilterOnce(): void
    {
        $layout = self::file('/app/Views/layouts/app.php');

        self::assertSame(1, substr_count($layout, "applyFilters('sidebar.items'"), 'Un seul point de filtrage.');
        // Une affectation + quatre consommateurs : commandes, menu, barre d'onglets, feuille.
        self::assertSame(5, substr_count($layout, '$shellNavigation'), 'Menu, barre d’onglets et feuille partagent la même liste.');
        self::assertStringContainsString("View::partial('partials/mobile-menu'", $layout);
    }

    /** La barre mobile garde quatre destinations quotidiennes, puis « Plus ». */
    public function testTabbarShowsFourTabsThenMore(): void
    {
        $tabbar = self::file('/app/Views/partials/tabbar.php');

        self::assertStringContainsString("\$tabKeys = ['dashboard', 'clients', 'projects', 'documents'];", $tabbar);
        self::assertStringContainsString('data-modal-open="#mobile-more-menu"', $tabbar);
        self::assertStringContainsString('aria-controls="mobile-more-menu"', $tabbar);
        self::assertStringContainsString('aria-expanded="false"', $tabbar);
        // « Plus » porte l'état courant quand la page n'est pas dans les quatre onglets.
        self::assertStringContainsString('$isMoreCurrent', $tabbar);
    }

    /** La feuille « Plus » reprend toutes les sections, avec un filtre et un état vide. */
    public function testMobileMenuListsEverySection(): void
    {
        $menu = self::file('/app/Views/partials/mobile-menu.php');

        self::assertStringContainsString('NavigationSections::group(', $menu);
        self::assertStringContainsString('data-mobile-menu-search', $menu);
        self::assertStringContainsString('data-mobile-menu-item', $menu);
        self::assertStringContainsString('data-mobile-menu-empty', $menu);
        self::assertStringContainsString('data-mobile-menu-group', $menu);
        self::assertStringContainsString('aria-current="page"', $menu);
        // L'horloge de l'en-tête mobile est masquée : elle reste consultable ici.
        self::assertStringContainsString('data-local-clock', $menu);
    }

    /** Le bouton « Nouveau » existe hors maquette et ouvre les vraies destinations. */
    public function testGlobalNewButtonIsAvailableOutsideDesignPreview(): void
    {
        $topbar = self::file('/app/Views/partials/topbar.php');
        $layout = self::file('/app/Views/layouts/app.php');
        $context = self::file('/app/Services/ViewContext.php');

        self::assertStringContainsString('$hasQuickActions', $topbar);
        self::assertStringContainsString('data-modal-open="#quick-add-dialog"', $topbar);
        self::assertStringContainsString("'hasQuickActions' => \$quickActions !== []", $layout);
        self::assertStringContainsString("View::partial('partials/quick-add'", $layout);
        self::assertStringNotContainsString(
            "if (\$isDesignPreview): ?>\n    <?php View::partial('partials/quick-add'",
            $layout,
            'Le tiroir d’actions rapides ne doit plus dépendre du mode maquette.'
        );

        foreach (["url('/clients/new')", "url('/projects/new')", "url('/documents/new')", "url('/time')"] as $url) {
            self::assertStringContainsString($url, $context, $url);
        }
        // Chaque action a une route réelle : aucun lien mort dans le tiroir.
        $routes = self::file('/config/routes.php');
        foreach (["'/clients/new'", "'/projects/new'", "'/documents/new'", "'/time'"] as $route) {
            self::assertStringContainsString($route, $routes, $route);
        }
        // « dépense » n'existe pas dans le noyau : aucune action factice n'est proposée.
        self::assertStringNotContainsString("expense", strtolower($context));
    }

    /** Les icônes du rail ne se répètent plus (CRM et planning étaient confondus). */
    public function testRailIconsAreUnique(): void
    {
        $icons = [];
        foreach (self::modules() as $file) {
            if (str_contains($file, '_example-module')) {
                continue;
            }
            $source = (string) file_get_contents($file);
            if (preg_match("/'icon' => '([a-z-]+)',\s*\n\s*'key' => '([a-z-]+)'/", $source, $matches)) {
                self::assertArrayNotHasKey(
                    $matches[1],
                    $icons,
                    "L’icône {$matches[1]} est déjà utilisée par {$icons[$matches[1]]} (colision dans le rail)."
                );
                $icons[$matches[1]] = $matches[2];
            }
        }

        // La table associe une icône au module qui la porte : les deux paires historiquement
        // confondues sont figées ici (CRM ≠ Planning dans le rail).
        self::assertSame('crm-opportunites', $icons['chart'] ?? null, 'CRM doit utiliser « chart ».');
        self::assertSame('planning-equipe', $icons['timer'] ?? null, 'Planning doit utiliser « timer ».');
    }

    /** Chaque module range son entrée dans une section connue. */
    public function testModulesDeclareTheirSection(): void
    {
        foreach (self::modules() as $file) {
            if (str_contains($file, '_example-module')) {
                continue;
            }
            $source = (string) file_get_contents($file);
            self::assertMatchesRegularExpression(
                "/'section' => '(pilotage|production|finance|conformite|administration)',/",
                $source,
                str_replace(BASE_PATH . '/', '', $file) . ' doit déclarer sa section.'
            );
        }
    }

    /** Les libellés d'onglets redeviennent lisibles (0,68 rem au lieu de 0,55–0,59 rem). */
    public function testTabbarLabelsStayReadable(): void
    {
        $layout = self::file('/public/assets/css/layout.css');

        preg_match('/(?<![\w-])\.mobile-tabbar__link\s*\{([^}]*)\}/', $layout, $rule);
        self::assertNotSame([], $rule, 'La règle .mobile-tabbar__link est introuvable.');
        self::assertMatchesRegularExpression('/font-size:\s*0\.68rem/', $rule[1], 'Taille minimale de 0,68 rem.');
        self::assertStringNotContainsString('font-size: 0.55rem', $layout, 'Aucun onglet ne doit retomber sous 0,68 rem.');
        self::assertStringNotContainsString('font-size: 0.59rem', $layout);

        // La feuille est stylée avec les dialogues : sa largeur doit l'emporter sur `dialog`.
        $components = self::file('/public/assets/css/components.css');
        self::assertMatchesRegularExpression('/(?<![\w-])\.mobile-menu\s*\{[^}]*max-inline-size:\s*100vw/', $components);
        self::assertStringContainsString('grid-template-rows: auto auto minmax(0, 1fr) auto', $components);
    }

    /** Les libellés du lot existent en français et en arabe. */
    public function testShellLabelsAreTranslated(): void
    {
        foreach (['fr', 'ar'] as $locale) {
            $messages = require BASE_PATH . '/lang/' . $locale . '.php';
            foreach ([
                'nav.more',
                'nav.more_label',
                'nav.more_intro',
                'nav.documents_short',
                'nav.section.pilotage',
                'nav.section.production',
                'nav.section.finance',
                'nav.section.conformite',
                'nav.section.administration',
                'quick.client',
                'quick.project',
                'quick.quote',
                'quick.time',
            ] as $key) {
                self::assertArrayHasKey($key, $messages, "Clé manquante ({$locale}) : {$key}");
                self::assertNotSame('', trim((string) $messages[$key]), "Clé vide ({$locale}) : {$key}");
            }
        }
    }
}
