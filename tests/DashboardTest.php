<?php
declare(strict_types=1);

namespace Tests;

use PHPUnit\Framework\TestCase;

/**
 * Tableau de bord (lot 4) : indicateurs réels, échéances bornées, heures de la semaine,
 * activité filtrée par les droits, et aucun reste de données de démonstration.
 */
final class DashboardTest extends TestCase
{
    private static function file(string $path): string
    {
        $contents = file_get_contents(BASE_PATH . $path);
        self::assertIsString($contents, "Fichier introuvable : {$path}");

        return $contents;
    }

    /** La route du tableau de bord reste derrière l'authentification. */
    public function testRouteIsAuthenticated(): void
    {
        $routes = self::file('/config/routes.php');

        self::assertMatchesRegularExpression(
            "/\\\$router->get\('\/dashboard',\s*\[DashboardController::class,\s*'index'\],\s*\[AuthMiddleware::class\]\)/",
            $routes,
            'GET /dashboard doit exiger AuthMiddleware.'
        );
    }

    /** Le contrôleur vérifie la permission, délègue au service et ne calcule rien lui-même. */
    public function testControllerDelegatesToService(): void
    {
        $controller = self::file('/app/Controllers/DashboardController.php');

        self::assertStringContainsString("Auth::can('dashboard.view')", $controller);
        self::assertStringContainsString('new DashboardService()', $controller);
        self::assertStringContainsString('overview($user, Auth::id())', $controller);
        self::assertStringContainsString("View::render('pages/app/dashboard'", $controller);
        // Aucune requête dans le contrôleur : la couche données est dans le dépôt.
        self::assertStringNotContainsString('SELECT', $controller);
    }

    /** Les requêtes sont préparées, bornées et sans donnée de démonstration. */
    public function testRepositoryIsPreparedAndBounded(): void
    {
        $repository = self::file('/app/Models/DashboardRepository.php');

        self::assertSame(6, substr_count($repository, '->prepare('), 'Une requête préparée par lecture.');
        self::assertStringNotContainsString('->query(', $repository);
        self::assertStringContainsString('public const LIMIT = 12;', $repository);
        self::assertStringContainsString('max(1, min($limit, self::LIMIT))', $repository);
        // Les dates passent par des paramètres liés, jamais par concaténation.
        self::assertStringNotContainsString("BETWEEN '", $repository);
        self::assertStringNotContainsString('LIMIT :', $repository);
    }

    /**
     * PDO est en mode natif : un nom de paramètre ne peut pas être réutilisé dans la même
     * requête. Les deux sous-requêtes d'heures portent donc deux noms distincts.
     */
    public function testTimeScopeUsesDistinctParameters(): void
    {
        $repository = self::file('/app/Models/DashboardRepository.php');

        self::assertStringContainsString("' AND e.user_id = :scope_user'", $repository);
        self::assertStringContainsString("' AND e.user_id = :scope_billable_user'", $repository);
        self::assertStringContainsString('BETWEEN :billable_start AND :billable_end', $repository);
        self::assertStringContainsString("\$parameters['scope_billable_user'] = \$scopeUserId;", $repository);
    }

    /** Chaque bloc est conditionné par une permission. */
    public function testPermissionGating(): void
    {
        $service = self::file('/app/Services/DashboardService.php');

        foreach (["Auth::can('projects.view')", "Auth::can('clients.view')", "Auth::can('time.view_all')"] as $check) {
            self::assertStringContainsString($check, $service);
        }
        self::assertStringContainsString("public static function canViewDocuments(): bool", $service);
        self::assertStringContainsString("public static function canViewTime(): bool", $service);
        // Le temps d'autrui n'est agrégé que pour les rôles qui voient tout le cabinet.
        self::assertStringContainsString('$timeScope = $can[\'timeAll\'] ? null : $userId;', $service);
    }

    /** Les échéances couvrent une fenêtre de 14 jours, retards d'abord, six lignes au plus. */
    public function testDeadlineHorizon(): void
    {
        $service = self::file('/app/Services/DashboardService.php');

        self::assertStringContainsString('public const HORIZON_DAYS = 14;', $service);
        self::assertStringContainsString("modify('+' . self::HORIZON_DAYS . ' days')", $service);
        self::assertStringContainsString('array_slice($items, 0, 6)', $service);
        // Un retard reste visible : les factures impayées sont lues avant la fenêtre.
        self::assertStringContainsString('overdueInvoices(', $service);
        self::assertStringContainsString("'tone' => \$overdue ? 'danger' : \$badge['tone']", $service);
    }

    /** L'activité ne remonte que des objets que l'utilisateur peut ouvrir. */
    public function testActivityRespectsPermissions(): void
    {
        $service = self::file('/app/Services/DashboardService.php');

        self::assertStringContainsString("\$subjectTypes[] = 'client';", $service);
        self::assertStringContainsString("\$subjectTypes[] = 'project';", $service);
        self::assertStringContainsString("\$subjectTypes[] = 'document';", $service);
        self::assertStringContainsString("\$subjectTypes[] = 'time_entry';", $service);
        // Un contact ou une phase n'a pas de fiche propre : aucun lien n'est proposé.
        self::assertStringContainsString("default => null,", $service);

        $repository = self::file('/app/Models/DashboardRepository.php');
        self::assertStringContainsString("WHERE l.subject_type IN (", $repository);
    }

    /** Les cartes d'indicateurs sont réelles, formatées et ouvrables. */
    public function testCardsAreRealAndLinked(): void
    {
        $service = self::file('/app/Services/DashboardService.php');

        self::assertSame(4, substr_count($service, "'key' =>"), 'Quatre indicateurs du noyau.');
        self::assertStringContainsString("money_format_tnd(\$outstanding['amount'])", $service);
        self::assertStringContainsString('self::formatHours(', $service);
        self::assertStringContainsString("'url' => url('/projects')", $service);
        // Aucune action factice : « dépense » n'existe pas dans le noyau.
        self::assertStringNotContainsString('dépense', $service);
        self::assertStringNotContainsString('expense', $service);
    }

    /** Les modules peuvent ajouter des cartes, une seule fois. */
    public function testModulesCanAddCards(): void
    {
        $service = self::file('/app/Services/DashboardService.php');

        self::assertSame(1, substr_count($service, "applyFilters('dashboard.cards'"), 'Un seul point d’extension.');
        self::assertStringContainsString("\$user", $service);
    }

    /** La semaine commence le lundi et compte toujours sept colonnes. */
    public function testWeekStartsOnMonday(): void
    {
        $service = self::file('/app/Services/DashboardService.php');

        self::assertStringContainsString("modify('monday this week')", $service);
        self::assertStringContainsString('for ($offset = 0; $offset < 7; $offset++)', $service);
        self::assertStringContainsString("max(3, (int) round(\$day['hours'] / \$peak * 100))", $service);
        self::assertStringContainsString("'isToday' => \$key === \$isToday", $service);
    }

    /** La vue ne contient plus aucune donnée de démonstration. */
    public function testViewHasNoDemoData(): void
    {
        $view = self::file('/app/Views/pages/app/dashboard.php');

        self::assertStringNotContainsString('Villa contemporaine', $view);
        self::assertStringNotContainsString('48750.000', $view);
        self::assertStringNotContainsString("url('/design/", $view);
        self::assertStringNotContainsString('geographyCounts', $view);
        self::assertStringContainsString("partial('partials/empty-state'", $view);
        self::assertStringContainsString("partial('partials/stat-card'", $view);
        // Chaque liste expose son état vide : jamais de carte muette.
        self::assertStringContainsString("__('dashboard.deadlines_empty'", $view);
        self::assertStringContainsString("__('dashboard.activity_empty'", $view);
    }

    /** Le graphique est décrit, la date est lisible et les unités sont explicites. */
    public function testAccessibilityOfTheDashboard(): void
    {
        $view = self::file('/app/Views/pages/app/dashboard.php');

        self::assertStringContainsString('role="img"', $view);
        self::assertStringContainsString("__('dashboard.hours_chart_label'", $view);
        self::assertStringContainsString('deadline-item__date" aria-hidden="true"', $view);
        self::assertStringContainsString("__('dashboard.due_date_label'", $view);
        self::assertStringContainsString('aria-labelledby="dashboard-projects-title"', $view);
        self::assertStringContainsString('u-sr-only', $view);
    }

    /** Toutes les clés utilisées par le tableau de bord existent en français et en arabe. */
    public function testTranslationsExistInBothLanguages(): void
    {
        $view = self::file('/app/Views/pages/app/dashboard.php');
        $service = self::file('/app/Services/DashboardService.php');
        $fr = self::file('/lang/fr.php');
        $ar = self::file('/lang/ar.php');

        preg_match_all("/__\('(dashboard\\.[a-z_.]+)'/", $view . $service, $matches);
        $keys = array_values(array_unique($matches[1]));
        self::assertGreaterThan(20, count($keys), 'Le tableau de bord utilise ses propres libellés.');

        foreach ($keys as $key) {
            self::assertStringContainsString("'{$key}'", $fr, "Clé absente du français : {$key}");
            self::assertStringContainsString("'{$key}'", $ar, "Clé absente de l’arabe : {$key}");
        }
        // Les jours de la semaine sont nommés, pas devinés par le navigateur.
        foreach (range(1, 7) as $day) {
            self::assertStringContainsString("'common.weekday.short.{$day}'", $fr);
            self::assertStringContainsString("'common.weekday.short.{$day}'", $ar);
        }
    }

    /** Les aides de formatage sont pures et testables sans base de données. */
    public function testFormattingHelpers(): void
    {
        $service = self::file('/app/Services/DashboardService.php');

        self::assertStringContainsString('public static function formatHours(string $hours, bool $withUnit = true): string', $service);
        self::assertStringContainsString('public static function dueBadge(string $dueDate, DateTimeImmutable $today): array', $service);
        self::assertStringContainsString('public static function daysBetween(DateTimeImmutable $today, string $date): int', $service);
        self::assertStringContainsString('public static function relativeTime(string $timestamp, DateTimeImmutable $now): string', $service);
        // Aucune conversion en flottant des montants : la chaîne DECIMAL reste la source.
        self::assertStringNotContainsString('(float) $outstanding', $service);
        self::assertStringContainsString("number_format(\$value, 2, ',', \"\\u{00A0}\")", $service);
    }
}
