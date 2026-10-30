<?php
declare(strict_types=1);

namespace Modules\MetreQuantitatif;

use App\Core\Auth;
use App\Core\HookManager;
use App\Core\Router;
use App\Middleware\AuthMiddleware;
use App\Middleware\CsrfMiddleware;
use Modules\MetreQuantitatif\Controllers\MetreController;
use Modules\MetreQuantitatif\Services\MetreContext;

/**
 * Module « metre-quantitatif » : avant-métré, DQE et bibliothèque de prix.
 *
 * Le module lit les projets et les clients du cœur et n’écrit que dans ses tables `metre_*`.
 * Aucun prix, aucun taux et aucune marge ne sont imposés : tout est saisi par le cabinet.
 */
final class Module
{
    /** Entrée de navigation avant les réglages, si l’utilisateur peut voir les projets. */
    public function boot(): void
    {
        HookManager::addFilter('sidebar.items', static function (array $items): array {
            if (!Auth::can('projects.view')) {
                return $items;
            }
            $entry = [
                'label' => __('metre.title'),
                'url' => url('/metre'),
                'icon' => 'layers',
                'key' => 'metre-quantitatif',
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

        // Passerelle vers la fiche projet / client / document du noyau.
        MetreContext::register();
    }

    /** Routes du module ; `/metre/bibliotheque` est déclarée avant `/metre/{projectId}`. */
    public function routes(Router $router): void
    {
        $auth = [AuthMiddleware::class];
        $write = [AuthMiddleware::class, CsrfMiddleware::class];

        $router->get('/metre', [MetreController::class, 'index'], $auth);
        $router->get('/metre/bibliotheque', [MetreController::class, 'library'], $auth);
        $router->post('/metre/bibliotheque', [MetreController::class, 'saveLibrary'], $write);
        $router->post('/metre/bibliotheque/{libraryId}/delete', [MetreController::class, 'deleteLibrary'], $write);
        $router->get('/metre/{projectId}', [MetreController::class, 'show'], $auth);
        $router->get('/metre/{projectId}/dqe', [MetreController::class, 'document'], $auth);
        $router->get('/metre/{projectId}/export-csv', [MetreController::class, 'exportCsv'], $auth);
        $router->get('/metre/{projectId}/postes/{posteId}', [MetreController::class, 'editPoste'], $auth);
        $router->post('/metre/{projectId}/lots', [MetreController::class, 'addLot'], $write);
        $router->post('/metre/{projectId}/lots/{lotId}/delete', [MetreController::class, 'deleteLot'], $write);
        $router->post('/metre/{projectId}/lots/{lotId}/postes', [MetreController::class, 'addPoste'], $write);
        $router->post('/metre/{projectId}/postes/{posteId}', [MetreController::class, 'updatePoste'], $write);
        $router->post('/metre/{projectId}/postes/{posteId}/delete', [MetreController::class, 'deletePoste'], $write);
    }

    /** Aucune donnée imposée : lots, postes et prix sont créés par le cabinet. */
    public function install(): void
    {
    }

    /** Réservé à une future désinstallation explicite ; jamais appelé à la désactivation. */
    public function uninstall(): void
    {
    }
}
