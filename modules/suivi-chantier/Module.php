<?php
declare(strict_types=1);

namespace Modules\SuiviChantier;

use App\Core\Auth;
use App\Core\HookManager;
use App\Core\Router;
use App\Middleware\AuthMiddleware;
use App\Middleware\CsrfMiddleware;
use Modules\SuiviChantier\Controllers\ChantierController;

/**
 * Module « suivi-chantier » : journal, réserves et réceptions.
 *
 * Aucune table du cœur n’est modifiée : le module lit les projets/clients et écrit
 * uniquement dans ses tables `chantier_*`.
 */
final class Module
{
    /** Ajoute l'entrée de navigation si l'utilisateur peut voir les projets. */
    public function boot(): void
    {
        HookManager::addFilter('sidebar.items', static function (array $items): array {
            if (!Auth::can('projects.view')) {
                return $items;
            }
            $entry = [
                'label' => __('chantier.title'),
                'url' => url('/chantier'),
                'icon' => 'plan',
                'key' => 'suivi-chantier',
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
    }

    /** Routes du module ; `/chantier` (liste) est déclarée avant `/chantier/{projectId}`. */
    public function routes(Router $router): void
    {
        $auth = [AuthMiddleware::class];
        $write = [AuthMiddleware::class, CsrfMiddleware::class];

        $router->get('/chantier', [ChantierController::class, 'index'], $auth);
        $router->get('/chantier/{projectId}', [ChantierController::class, 'show'], $auth);
        $router->post('/chantier/{projectId}/visites', [ChantierController::class, 'addVisit'], $write);
        $router->post('/chantier/{projectId}/visites/{visitId}/delete', [ChantierController::class, 'deleteVisit'], $write);
        $router->post('/chantier/{projectId}/reserves', [ChantierController::class, 'addReserve'], $write);
        $router->post('/chantier/{projectId}/reserves/{reserveId}', [ChantierController::class, 'updateReserve'], $write);
        $router->post('/chantier/{projectId}/receptions', [ChantierController::class, 'addReception'], $write);
        $router->get('/chantier/{projectId}/receptions/{receptionId}', [ChantierController::class, 'printReception'], $auth);
    }

    /** Exécuté une seule fois, après les migrations d'activation. Aucun jeu de données imposé. */
    public function install(): void
    {
    }

    /** Réservé à une future désinstallation explicite ; jamais appelé à la désactivation. */
    public function uninstall(): void
    {
    }
}
