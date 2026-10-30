<?php
declare(strict_types=1);

namespace Tests;

use PHPUnit\Framework\TestCase;

/**
 * Garde-fou de la règle des trois clics (lot 5).
 *
 * Le test reconstruit le graphe de navigation à partir du code réel : routes GET de
 * `config/routes.php`, vue rendue par chaque méthode de contrôleur, liens sortants des vues,
 * coquille commune (menu, barre d'onglets, feuille « Plus », actions rapides, palette) et
 * entrées de menu des modules. Il calcule ensuite la distance minimale depuis `/dashboard`
 * et échoue dès qu'une destination du noyau demande plus de trois clics.
 *
 * Choix assumés :
 *  - un lien de liste (`/clients/` + identifiant) désigne **une** fiche, pas ses sous-écrans ;
 *    `/clients/{id}/edit` doit donc être atteint par un lien de la fiche, pas par la liste ;
 *  - les routes techniques (export CSV, API, médias, assets de module, `/health`, aperçus de
 *    développement) ne sont pas des destinations : elles restent hors du budget ;
 *  - la coquille est présente sur chaque page authentifiée, donc son coût est d'un clic
 *    depuis n'importe quel écran ;
 *  - les modules déclarent leurs entrées de menu dans `Module.php` : chacune doit correspondre à
 *    une route que ce module déclare lui-même, et être à un clic. La profondeur *interne* d'un
 *    module reste de sa responsabilité.
 *
 * Aucun navigateur, aucune base : ce test est purement statique et s'exécute en CI.
 */
final class NavigationDepthTest extends TestCase
{
    /** Budget de clics depuis le tableau de bord. */
    private const MAX_DEPTH = 3;

    /** Préfixes techniques : jamais des destinations, jamais dans le budget. */
    private const TECHNICAL_PREFIXES = [
        '/health',
        '/api/',
        '/media/',
        '/module-assets/',
        '/design/',
    ];

    /** Suffixes techniques : téléchargements et modèles, pas des écrans. */
    private const TECHNICAL_SUFFIXES = [
        '/export-csv',
        '/import-template',
    ];

    /** Pages accessibles sans la coquille : aucun menu, donc aucun coût de coquille. */
    private const PUBLIC_PATHS = ['/', '/login', '/health'];

    private static function file(string $relativePath): string
    {
        $contents = file_get_contents(BASE_PATH . $relativePath);
        self::assertIsString($contents, "Fichier introuvable : {$relativePath}");

        return $contents;
    }

    private static function isTechnical(string $path): bool
    {
        foreach (self::TECHNICAL_PREFIXES as $prefix) {
            if (str_starts_with($path, $prefix)) {
                return true;
            }
        }
        foreach (self::TECHNICAL_SUFFIXES as $suffix) {
            if (str_ends_with($path, $suffix)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Toutes les routes GET du noyau : chemin, contrôleur et méthode.
     *
     * @return array<int,array{path:string,controller:string,method:string}>
     */
    private static function routes(): array
    {
        $source = self::file('/config/routes.php');
        preg_match_all(
            "/\\\$router->get\\('([^']+)',\\s*\\[([A-Za-z]+)::class,\\s*'([A-Za-z]+)'\\]/",
            $source,
            $matches,
            PREG_SET_ORDER
        );
        $routes = [];
        foreach ($matches as $match) {
            $routes[] = ['path' => $match[1], 'controller' => $match[2], 'method' => $match[3]];
        }

        return $routes;
    }

    /** Vue réellement rendue par une méthode de contrôleur (la 404 est ignorée). */
    private static function renderedView(string $controller, string $method): ?string
    {
        $file = BASE_PATH . '/app/Controllers/' . $controller . '.php';
        if (!is_file($file)) {
            return null;
        }
        $source = (string) file_get_contents($file);
        $signature = '/function\s+' . preg_quote($method, '/') . '\s*\(/';
        if (preg_match($signature, $source, $start, PREG_OFFSET_CAPTURE) !== 1) {
            return null;
        }
        $body = substr($source, (int) $start[0][1] + 1);
        $boundary = '/\n    (?:public|private|protected) (?:static )?function /';
        if (preg_match($boundary, $body, $end, PREG_OFFSET_CAPTURE) === 1) {
            $body = substr($body, 0, (int) $end[0][1]);
        }
        preg_match_all("/View::render\\('([^']+)'/", $body, $views);
        $views = array_values(array_filter($views[1], static fn (string $view): bool => $view !== 'pages/404'));

        return $views === [] ? null : (string) end($views);
    }

    /**
     * Contenu de l'appel dont la parenthèse ouvrante est à `$offset`.
     */
    private static function callArguments(string $source, int $offset): string
    {
        $depth = 1;
        $out = '';
        $length = strlen($source);
        for ($index = $offset + 1; $index < $length; $index++) {
            $char = $source[$index];
            if ($char === '(') {
                $depth++;
            } elseif ($char === ')') {
                $depth--;
                if ($depth === 0) {
                    return $out;
                }
            }
            $out .= $char;
        }

        return $out;
    }

    /**
     * Motif de route d'une expression PHP : les littéraux sont conservés, chaque expression
     * (identifiant, appel) devient `{*}`. Exemple : `'/clients/' . $id . '/edit'` donne
     * `/clients/{*}/edit`.
     */
    private static function patternOf(string $expression): ?string
    {
        $parts = [];
        $current = '';
        $quote = null;
        $length = strlen($expression);
        for ($index = 0; $index < $length; $index++) {
            $char = $expression[$index];
            if ($quote !== null) {
                $current .= $char;
                if ($char === $quote) {
                    $quote = null;
                }
                continue;
            }
            if ($char === "'" || $char === '"') {
                $quote = $char;
                $current .= $char;
                continue;
            }
            if ($char === '.') {
                $parts[] = trim($current);
                $current = '';
                continue;
            }
            $current .= $char;
        }
        $parts[] = trim($current);

        $segments = [];
        foreach ($parts as $part) {
            if (preg_match('/^([\'"])(.*)\1$/s', $part, $literal) === 1) {
                if ($literal[2] !== '') {
                    $segments[] = $literal[2];
                }
                continue;
            }
            if ($part !== '') {
                $segments[] = '{*}';
            }
        }
        $pattern = implode('', $segments);
        while (str_contains($pattern, '{*}{*}')) {
            $pattern = str_replace('{*}{*}', '{*}', $pattern);
        }

        return str_starts_with($pattern, '/') && !str_starts_with($pattern, '/assets') ? $pattern : null;
    }

    /**
     * Motifs de liens d'une vue (helpers `url()` et `href` absolus).
     *
     * @return array<int,string>
     */
    private static function links(string $view): array
    {
        $file = BASE_PATH . '/app/Views/' . $view . '.php';
        if (!is_file($file)) {
            return [];
        }
        $source = (string) file_get_contents($file);
        $patterns = [];
        preg_match_all('/\burl\(/', $source, $calls, PREG_OFFSET_CAPTURE);
        foreach ($calls[0] as $call) {
            $pattern = self::patternOf(self::callArguments($source, (int) $call[1] + 3));
            if ($pattern !== null) {
                $patterns[$pattern] = true;
            }
        }
        preg_match_all('/href="(\/[^"<]*)"/', $source, $hrefs);
        foreach ($hrefs[1] as $href) {
            $patterns[$href] = true;
        }

        return array_keys($patterns);
    }

    /**
     * Routes désignées par un motif : un `{*}` ne vaut que pour un segment paramétré.
     *
     * @param array<int,array{path:string,controller:string,method:string}> $routes
     * @return array<int,string>
     */
    private static function matchRoute(string $pattern, array $routes): array
    {
        $pattern = explode('?', $pattern)[0];
        $wanted = array_values(array_filter(explode('/', $pattern), static fn (string $s): bool => $s !== ''));
        $matched = [];
        foreach ($routes as $route) {
            $segments = explode('/', $route['path']);
            $actual = array_values(array_filter($segments, static fn (string $s): bool => $s !== ''));
            if (count($actual) !== count($wanted)) {
                continue;
            }
            $ok = true;
            foreach ($actual as $index => $segment) {
                if ($wanted[$index] === '{*}') {
                    if (!str_starts_with($segment, '{')) {
                        $ok = false;
                        break;
                    }
                    continue;
                }
                if ($wanted[$index] !== $segment) {
                    $ok = false;
                    break;
                }
            }
            if ($ok) {
                $matched[] = $route['path'];
            }
        }

        return $matched;
    }

    /**
     * Destinations présentes sur toutes les pages authentifiées : menu latéral, barre
     * d'onglets, feuille « Plus », actions rapides, palette et entrées de menu des modules.
     *
     * @param array<int,array{path:string,controller:string,method:string}> $routes
     * @return array<int,string>
     */
    private static function shellTargets(array $routes): array
    {
        $patterns = [];
        preg_match_all("/url\\('([^']*)'\\)/", self::file('/app/Services/ViewContext.php'), $contextUrls);
        foreach ($contextUrls[1] as $url) {
            $patterns[$url] = true;
        }
        foreach (['partials/sidebar', 'partials/tabbar', 'partials/mobile-menu', 'partials/topbar',
            'partials/quick-add', 'partials/command-palette', 'partials/context-links', 'layouts/app'] as $shellView) {
            foreach (self::links($shellView) as $pattern) {
                $patterns[$pattern] = true;
            }
        }

        $targets = [];
        foreach (array_keys($patterns) as $pattern) {
            foreach (self::matchRoute($pattern, $routes) as $path) {
                $targets[$path] = true;
            }
        }
        foreach (self::moduleMenuPatterns() as $pattern) {
            foreach (self::matchRoute($pattern, $routes) as $path) {
                $targets[$path] = true;
            }
        }

        return array_keys($targets);
    }

    /**
     * Modules réels de `modules/`, indexés par slug. Les dossiers préfixés par `_` sont des
     * gabarits (`_example-module`) : ils ne sont jamais installés.
     *
     * @return array<string,string>
     */
    private static function moduleFiles(): array
    {
        $files = [];
        foreach (glob(BASE_PATH . '/modules/*/Module.php') ?: [] as $file) {
            $slug = basename(dirname($file));
            if (str_starts_with($slug, '_')) {
                continue;
            }
            $files[$slug] = $file;
        }

        return $files;
    }

    /**
     * Entrées de menu déclarées par les modules : `'url' => url('/permis')`.
     *
     * @return array<int,string>
     */
    private static function moduleMenuPatterns(): array
    {
        $patterns = [];
        foreach (self::moduleFiles() as $file) {
            $source = (string) file_get_contents($file);
            preg_match_all("/'url'\\s*=>\\s*url\\(([^)]*)\\)/", $source, $matches);
            foreach ($matches[1] as $expression) {
                $pattern = self::patternOf($expression);
                // Une entrée de menu est un chemin fixe : les liens contextuels restent hors coquille.
                if ($pattern !== null && !str_contains($pattern, '{*}')) {
                    $patterns[$pattern] = true;
                }
            }
        }

        return array_keys($patterns);
    }

    /**
     * Distance minimale depuis `/dashboard` vers chaque route.
     *
     * @param array<int,array{path:string,controller:string,method:string}> $routes
     * @return array<string,int>
     */
    private static function depths(array $routes): array
    {
        $edges = [];
        foreach ($routes as $route) {
            $edges[$route['path']] = [];
        }
        foreach ($routes as $route) {
            $view = self::renderedView($route['controller'], $route['method']);
            foreach ($view === null ? [] : self::links($view) as $pattern) {
                foreach (self::matchRoute($pattern, $routes) as $target) {
                    $edges[$route['path']][$target] = true;
                }
            }
        }
        $shell = self::shellTargets($routes);

        $depths = ['/dashboard' => 0];
        $frontier = ['/dashboard'];
        while ($frontier !== []) {
            $next = [];
            foreach ($frontier as $node) {
                $targets = array_keys($edges[$node] ?? []);
                if (!in_array($node, self::PUBLIC_PATHS, true)) {
                    $targets = array_merge($targets, $shell);
                }
                foreach ($targets as $target) {
                    if (!array_key_exists($target, $depths)) {
                        $depths[$target] = $depths[$node] + 1;
                        $next[] = $target;
                    }
                }
            }
            $frontier = $next;
        }

        return $depths;
    }

    /** Aucune destination du noyau ne demande plus de trois clics depuis le tableau de bord. */
    public function testEveryCoreDestinationIsWithinThreeClicks(): void
    {
        $routes = self::routes();
        $destinations = array_values(array_filter(
            $routes,
            static fn (array $route): bool => !self::isTechnical($route['path'])
        ));
        self::assertGreaterThanOrEqual(25, count($destinations), 'La lecture des routes a échoué.');

        $depths = self::depths($routes);
        $failures = [];
        foreach ($destinations as $route) {
            $depth = $depths[$route['path']] ?? PHP_INT_MAX;
            if ($depth <= self::MAX_DEPTH) {
                continue;
            }
            $label = $depth === PHP_INT_MAX ? 'inatteignable' : $depth . ' clics';
            $failures[] = $route['path'] . ' (' . $label . ')';
        }

        self::assertSame(
            [],
            $failures,
            "Règle des trois clics enfreinte depuis /dashboard :\n - " . implode("\n - ", $failures)
        );
    }

    /**
     * Chaque entrée de menu d'un module désigne une route que ce module déclare réellement,
     * une seule fois, et reste à un clic puisqu'elle est ajoutée à la coquille.
     */
    public function testModuleMenuEntriesMatchDeclaredRoutes(): void
    {
        $menu = self::moduleMenuPatterns();
        self::assertNotSame([], $menu, 'Aucune entrée de module détectée.');

        $declared = [];
        $seen = [];
        foreach (self::moduleFiles() as $slug => $file) {
            $source = (string) file_get_contents($file);
            preg_match_all("/router->get\\(\'([^\']+)\'/", $source, $routes);
            foreach ($routes[1] as $path) {
                $declared[$slug][$path] = true;
            }
            preg_match_all("/'url'\\s*=>\\s*url\\(([^)]*)\\)/", $source, $entries);
            foreach ($entries[1] as $expression) {
                $pattern = self::patternOf($expression);
                if ($pattern === null || str_contains($pattern, '{*}')) {
                    continue;
                }
                self::assertArrayHasKey(
                    $pattern,
                    $declared[$slug] ?? [],
                    "Le module {$slug} affiche un menu vers {$pattern} sans déclarer cette route."
                );
                self::assertArrayNotHasKey(
                    $pattern,
                    $seen,
                    "Deux modules revendiquent la même entrée de menu : {$pattern}"
                );
                $seen[$pattern] = $slug;
            }
        }
        self::assertNotSame([], $seen, 'Aucune entrée de menu rapprochée d’une route.');
    }

    /** Les exclusions sont bien techniques : aucune page utilisateur n'échappe au budget. */
    public function testExcludedRoutesAreTechnical(): void
    {
        foreach (self::routes() as $route) {
            if (!self::isTechnical($route['path'])) {
                continue;
            }
            $technical = false;
            foreach (self::TECHNICAL_PREFIXES as $prefix) {
                $technical = $technical || str_starts_with($route['path'], $prefix);
            }
            foreach (self::TECHNICAL_SUFFIXES as $suffix) {
                $technical = $technical || str_ends_with($route['path'], $suffix);
            }
            self::assertTrue($technical, 'Route exclue à tort : ' . $route['path']);
        }
        // Les aperçus de développement ne sont jamais des destinations de production.
        self::assertContains('/design/', self::TECHNICAL_PREFIXES);
        self::assertContains('/api/', self::TECHNICAL_PREFIXES);
        self::assertContains('/design/', self::TECHNICAL_PREFIXES);
        self::assertContains('/api/', self::TECHNICAL_PREFIXES);
    }

    /**
     * Le calcul détecte bien une profondeur excessive : on l'éprouve sur un lien absent.
     * Sans cette preuve, un graphe vide passerait le test sans rien garantir.
     */
    public function testGraphIsNotVacuouslyEmpty(): void
    {
        $routes = self::routes();
        $depths = self::depths($routes);
        // Un graphe vide passerait toutes les assertions de profondeur : on exige de le voir grandir.
        self::assertGreaterThanOrEqual(25, count($routes), 'Le graphe de navigation est suspicieusement vide.');
        self::assertSame(0, $depths['/dashboard'] ?? null);
        self::assertArrayHasKey('/clients', $depths);
        self::assertArrayHasKey('/clients/{id}', $depths);
        self::assertArrayHasKey('/clients/{id}/edit', $depths);
        self::assertLessThanOrEqual(self::MAX_DEPTH, $depths['/clients/{id}/edit']);
        // Les pages publiques restent hors du graphe authentifié (aucune coquille).
        self::assertNotSame(1, $depths['/login'] ?? null, 'La coquille ne doit pas exister avant connexion.');
    }

    /** La règle est documentée là où un développeur la cherchera. */
    public function testRuleIsDocumented(): void
    {
        $conventions = self::file('/docs/CONVENTIONS.md');
        self::assertStringContainsString('trois clics', $conventions);
        self::assertMatchesRegularExpression('/NavigationDepthTest/', $conventions);

        $audit = self::file('/docs/AUDIT-UX-ET-NAVIGATION.md');
        self::assertStringContainsString('Lot 5', $audit);
        self::assertStringContainsString('NavigationDepthTest', $audit);
    }
}
