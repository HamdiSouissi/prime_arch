<?php
declare(strict_types=1);

namespace Modules\PlanningEquipe;

use App\Core\Auth;
use App\Core\HookManager;
use App\Core\Router;
use App\Middleware\AuthMiddleware;
use App\Middleware\CsrfMiddleware;
use Modules\PlanningEquipe\Controllers\PlanningController;

/**
 * Module « planning-equipe » : charge des collaborateurs, congés et budget d’heures.
 *
 * Le module lit `users`, `roles`, `projects`, `project_phase_assignments` et `time_entries`
 * et n’écrit que dans ses tables `planning_*`. Aucune capacité de travail n’est imposée :
 * le seuil de surcharge reste un paramètre d’écran.
 */
final class Module
{
    /** Entrée de navigation avant les réglages, si l’utilisateur peut voir le temps. */
    public function boot(): void
    {
        HookManager::addFilter('sidebar.items', static function (array $items): array {
            if (!Auth::can('time.view') && !Auth::can('time.view_all')) {
                return $items;
            }
            $entry = [
                'label' => __('planning.title'),
                'url' => url('/planning'),
                'icon' => 'timer',
                'key' => 'planning-equipe',
                'section' => 'pilotage',
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

    /** Routes du module. */
    public function routes(Router $router): void
    {
        $auth = [AuthMiddleware::class];
        $write = [AuthMiddleware::class, CsrfMiddleware::class];

        $router->get('/planning', [PlanningController::class, 'index'], $auth);
        $router->get('/planning/projets', [PlanningController::class, 'projects'], $auth);
        $router->post('/planning/affectations', [PlanningController::class, 'addAffectation'], $write);
        $router->post('/planning/affectations/{affectationId}/delete', [PlanningController::class, 'deleteAffectation'], $write);
        $router->post('/planning/conges', [PlanningController::class, 'addLeave'], $write);
        $router->post('/planning/conges/{leaveId}/delete', [PlanningController::class, 'deleteLeave'], $write);
    }

    /** Aucune affectation préremplie : le planning se construit avec l’équipe. */
    public function install(): void
    {
    }

    /** Réservé à une future désinstallation explicite ; jamais appelé à la désactivation. */
    public function uninstall(): void
    {
    }
}
