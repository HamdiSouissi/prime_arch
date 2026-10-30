<?php
declare(strict_types=1);

namespace Modules\RegistreCourrier;

use App\Core\Auth;
use App\Core\HookManager;
use App\Core\Router;
use App\Middleware\AuthMiddleware;
use App\Middleware\CsrfMiddleware;
use App\Models\SearchRepository;
use Modules\RegistreCourrier\Controllers\CourrierController;
use Modules\RegistreCourrier\Models\CourrierRepository;
use Modules\RegistreCourrier\Services\CourrierContext;
use Throwable;

/**
 * Module « registre-courrier » : registre du courrier entrant et sortant.
 *
 * Le module ne modifie aucune table du cœur : il lit les projets, les clients et les
 * utilisateurs, et stocke son registre dans ses propres tables `courrier_*`.
 */
final class Module
{
    /** Entrée de navigation dans « Production », avant les réglages. */
    public function boot(): void
    {
        HookManager::addFilter('sidebar.items', static function (array $items): array {
            if (!Auth::can('clients.view')) {
                return $items;
            }
            $entry = [
                'label' => __('courrier.title'),
                'url' => url('/courrier'),
                'icon' => 'mail',
                'key' => 'registre-courrier',
                'section' => 'production',
            ];
            foreach ($items as $index => $existing) {
                if (($existing['key'] ?? '') === 'settings') {
                    array_splice($items, $index, 0, [$entry]);

                    return $items;
                }
            }
            $items[] = $entry;

            return $items;
        });

        // Passerelles vers les fiches projet et client du noyau.
        CourrierContext::register();

        // Recherche globale : le module complète la palette sans modifier le noyau.
        HookManager::addFilter('search.results', static function (array $groups, string $query = ''): array {
            if (!Auth::can('clients.view') || mb_strlen(trim($query), 'UTF-8') < 2) {
                return $groups;
            }

            try {
                $rows = (new CourrierRepository())->search($query);
            } catch (Throwable $error) {
                // Table du module absente ou base indisponible : la palette reste utilisable.
                error_log('Recherche registre-courrier indisponible : ' . $error->getMessage());

                return $groups;
            }

            $items = [];
            foreach ($rows as $row) {
                $meta = array_values(array_filter([
                    __('courrier.sens.' . (string) ($row['sens'] ?? 'in')),
                    trim((string) ($row['correspondant'] ?? '')),
                    trim((string) ($row['project_name'] ?? '')),
                ], static fn (string $value): bool => $value !== ''));

                $items[] = [
                    'title' => (string) ($row['numero'] ?? ''),
                    'meta' => implode(' · ', array_unique($meta)),
                    'url' => url('/courrier/' . (int) ($row['id'] ?? 0)),
                    'icon' => 'mail',
                    'type' => 'courrier',
                ];
            }

            if ($items === []) {
                return $groups;
            }

            $groups[] = [
                'key' => 'courrier',
                'label' => __('courrier.search_group'),
                'icon' => 'mail',
                'items' => $items,
                'truncated' => count($items) >= SearchRepository::LIMIT,
            ];

            return $groups;
        });
    }

    /**
     * Routes du module.
     *
     * Les chemins fixes (`/courrier/new`, `/courrier/reglages`, …) sont déclarés avant
     * `/courrier/{id}`, sinon ils seraient pris pour des identifiants.
     */
    public function routes(Router $router): void
    {
        $auth = [AuthMiddleware::class];
        $write = [AuthMiddleware::class, CsrfMiddleware::class];

        $router->get('/courrier', [CourrierController::class, 'index'], $auth);
        $router->get('/courrier/export-csv', [CourrierController::class, 'exportCsv'], $auth);
        $router->get('/courrier/imprimer', [CourrierController::class, 'printRegister'], $auth);
        $router->get('/courrier/reglages', [CourrierController::class, 'settings'], $auth);
        $router->post('/courrier/reglages', [CourrierController::class, 'saveSettings'], $write);
        $router->post('/courrier/reglages/compteur', [CourrierController::class, 'saveCounter'], $write);
        $router->get('/courrier/new', [CourrierController::class, 'createForm'], $auth);
        $router->post('/courrier', [CourrierController::class, 'create'], $write);
        $router->get('/courrier/{id}', [CourrierController::class, 'show'], $auth);
        $router->get('/courrier/{id}/edit', [CourrierController::class, 'editForm'], $auth);
        $router->post('/courrier/{id}', [CourrierController::class, 'update'], $write);
        $router->post('/courrier/{id}/statut', [CourrierController::class, 'updateStatus'], $write);
    }

    /** Exécuté une seule fois après les migrations : aucun jeu de données imposé. */
    public function install(): void
    {
    }

    /** Réservé à une future désinstallation explicite ; jamais appelé à la désactivation. */
    public function uninstall(): void
    {
    }
}
