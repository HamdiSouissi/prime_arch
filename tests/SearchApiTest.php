<?php
declare(strict_types=1);

namespace Tests;

use PHPUnit\Framework\TestCase;

/**
 * Recherche globale (lot 2) : route protégée, requêtes préparées limitées, palette accessible
 * au clavier, résultats échappés et fiches récentes.
 */
final class SearchApiTest extends TestCase
{
    private static function file(string $path): string
    {
        $contents = file_get_contents(BASE_PATH . $path);
        self::assertIsString($contents, "Fichier introuvable : {$path}");

        return $contents;
    }

    /** La route existe, passe par l'authentification et pointe le bon contrôleur. */
    public function testSearchRouteIsAuthenticated(): void
    {
        $routes = self::file('/config/routes.php');

        self::assertMatchesRegularExpression(
            "/\\\$router->get\\('\\/api\\/recherche',\\s*\\[SearchController::class,\\s*'index'\\],\\s*\\[AuthMiddleware::class\\]\\)/",
            $routes,
            'La recherche doit être exposée en GET /api/recherche derrière AuthMiddleware.'
        );
    }

    /**
     * Le contrôleur borne la saisie, respecte les droits et laisse les modules compléter
     * les résultats par le filtre `search.results`.
     */
    public function testControllerFiltersByPermissionAndEscapes(): void
    {
        $controller = self::file('/app/Controllers/SearchController.php');

        self::assertStringContainsString("Auth::can('clients.view')", $controller);
        self::assertStringContainsString("Auth::can('projects.view')", $controller);
        self::assertStringContainsString("'documents.view'", $controller);
        self::assertStringContainsString("HookManager::applyFilters('search.results', \$groups, \$query)", $controller);
        self::assertStringContainsString("'Cache-Control', 'private, no-store'", $controller);
        self::assertStringContainsString('MIN_LENGTH', $controller);
        self::assertStringContainsString('MAX_LENGTH', $controller);
        // Aucune donnée renvoyée telle quelle : le JSON est produit par Response::json().
        self::assertStringContainsString('Response::json(', $controller);
    }

    /** Les trois catégories du noyau sont préparées, échappées et limitées à huit lignes. */
    public function testRepositoryUsesPreparedStatementsAndLimit(): void
    {
        $repository = self::file('/app/Models/SearchRepository.php');

        self::assertSame(3, substr_count($repository, '->prepare('), 'Clients, projets et documents.');
        self::assertStringContainsString('ESCAPE', $repository);
        self::assertStringContainsString("str_replace(['\\\\', '%', '_']", $repository);
        self::assertStringContainsString('public const LIMIT = 8;', $repository);
        // Aucune concaténation du terme dans le SQL : seules des bornes numériques le sont.
        self::assertStringNotContainsString("LIKE '%' . \$", $repository);
        // Le terme de recherche n'entre jamais dans la requête par interpolations.
        self::assertStringNotContainsString('$query .', $repository);
    }

    /** La saisie est nettoyée avant d'atteindre la base. */
    public function testTermIsNormalised(): void
    {
        $controller = self::file('/app/Controllers/SearchController.php');

        self::assertStringContainsString('public static function normalize(', $controller);
        self::assertStringContainsString('mb_substr($clean, 0, self::MAX_LENGTH', $controller);
        self::assertMatchesRegularExpression('/preg_replace\(.+\[\\\\x00-\\\\x1F\\\\x7F\]/', $controller);
    }

    /** La palette est une combobox/listbox : focus stable, élément actif annoncé, statut vivant. */
    public function testPaletteIsAnAccessibleCombobox(): void
    {
        $palette = self::file('/app/Views/partials/command-palette.php');

        self::assertStringContainsString('role="combobox"', $palette);
        self::assertStringContainsString('aria-autocomplete="list"', $palette);
        self::assertStringContainsString('aria-controls="command-results"', $palette);
        self::assertStringContainsString('role="listbox"', $palette);
        self::assertStringContainsString('role="option"', $palette);
        self::assertStringContainsString('aria-selected="false"', $palette);
        self::assertStringContainsString('data-command-status', $palette);
        self::assertStringContainsString('aria-live="polite"', $palette);
        self::assertStringContainsString('data-command-results', $palette);
        self::assertStringContainsString('data-search-endpoint', $palette);
        self::assertStringContainsString('\\App\\Models\\SearchRepository::LIMIT', $palette);
    }

    /** Le script interroge la route, annule les requêtes obsolètes et pilote au clavier. */
    public function testPaletteScriptCallsTheApi(): void
    {
        $script = self::file('/public/assets/js/ui.js');
        $start = (int) strpos($script, 'function initCommandPalette()');
        $end = (int) strpos($script, 'function initShortcutHints()');
        self::assertGreaterThan(0, $start);
        self::assertGreaterThan($start, $end);
        $palette = substr($script, $start, $end - $start);

        self::assertStringContainsString('fetch(`${endpoint}?q=${encodeURIComponent(query)}`', $palette);
        self::assertStringContainsString('AbortController', $palette);
        self::assertStringContainsString('aria-activedescendant', $palette);
        self::assertStringContainsString('window.setTimeout(() => search(input.value), 180)', $palette);
        self::assertStringContainsString('"Enter"', $palette);
        self::assertStringContainsString('textContent', $palette);
        // Les résultats serveur ne passent jamais par innerHTML : pas d'injection possible.
        self::assertStringNotContainsString('innerHTML', $palette);
        // Historique local : nettoyé à la fermeture de l'onglet (sessionStorage, pas localStorage).
        self::assertStringContainsString('sessionStorage', $script);
        self::assertStringNotContainsString('localStorage', $script);
    }

    /** Les cinq dernières fiches vues alimentent la section « Récents ». */
    public function testRecentPagesAreLimitedAndDeduplicated(): void
    {
        $script = self::file('/public/assets/js/ui.js');

        self::assertStringContainsString('const RECENT_PAGES_LIMIT = 5;', $script);
        self::assertStringContainsString('filter((item) => item.url !== entry.url)', $script);
        self::assertStringContainsString('slice(0, RECENT_PAGES_LIMIT)', $script);
    }

    /** La fiche consultée est déclarée par le layout à partir du fil d'Ariane. */
    public function testLayoutExposesTheCurrentRecord(): void
    {
        $layout = self::file('/app/Views/layouts/app.php');

        self::assertStringContainsString('data-recent-record', self::file('/app/Views/partials/command-palette.php'));
        self::assertStringContainsString('$recentRecord', $layout);
        self::assertStringContainsString('$breadcrumb', $layout);
        self::assertStringContainsString("'recentRecord' => \$recentRecord", $layout);
    }

    /** Les libellés de la recherche existent dans les deux langues et sont exposés au JS. */
    public function testSearchLabelsAreTranslated(): void
    {
        foreach (['fr', 'ar'] as $locale) {
            $messages = require BASE_PATH . '/lang/' . $locale . '.php';
            foreach ([
                'search.group_clients',
                'search.group_projects',
                'search.group_documents',
                'search.hint',
                'search.loading',
                'js.search_recents',
                'js.search_count',
                'js.search_none',
                'js.search_unavailable',
            ] as $key) {
                self::assertArrayHasKey($key, $messages, "Clé manquante ({$locale}) : {$key}");
                self::assertNotSame('', trim((string) $messages[$key]), "Clé vide ({$locale}) : {$key}");
            }
        }

        $i18n = self::file('/app/Views/partials/i18n.php');
        foreach (['js.search_recents', 'js.search_count', 'js.search_none', 'js.search_unavailable'] as $key) {
            self::assertStringContainsString("'{$key}'", $i18n, "Clé non exposée au JavaScript : {$key}");
        }
    }

    /** Le module de permis s'y branche par un filtre, sans que le noyau le connaisse. */
    public function testPermisModuleContributesThroughTheHook(): void
    {
        $module = self::file('/modules/permis-batir/Module.php');
        $repository = self::file('/modules/permis-batir/Models/DossierRepository.php');

        self::assertStringContainsString("HookManager::addFilter('search.results'", $module);
        self::assertStringContainsString('public function search(string $query', $repository);
        self::assertStringContainsString('SearchRepository::likeTerm(', $repository);
        // Le noyau n'importe jamais une classe de module.
        self::assertStringNotContainsString('Modules\\\\', self::file('/app/Controllers/SearchController.php'));
    }
}
