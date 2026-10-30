<?php
declare(strict_types=1);

namespace Modules\CrmOpportunites;

use App\Core\Auth;
use App\Core\HookManager;
use App\Core\Router;
use App\Middleware\AuthMiddleware;
use App\Middleware\CsrfMiddleware;
use Modules\CrmOpportunites\Controllers\CrmController;

/**
 * Module « crm-opportunites » : prospects et pipeline commercial du cabinet.
 *
 * Le module n’écrit que dans ses tables `crm_*`. Les fiches client et projet du noyau sont
 * seulement lues pour proposer un rattachement ; leur création reste dans les écrans du cœur.
 */
final class Module
{
    /** Entrée de navigation avant les réglages, si l’utilisateur peut lire clients ou projets. */
    public function boot(): void
    {
        HookManager::addFilter('sidebar.items', static function (array $items): array {
            if (!Auth::can('clients.view') && !Auth::can('projects.view')) {
                return $items;
            }
            $entry = [
                'label' => __('crm.title'),
                'url' => url('/crm'),
                'icon' => 'people',
                'key' => 'crm-opportunites',
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

    /** Routes du module : liste et export d’abord, puis fiches et actions protégées par CSRF. */
    public function routes(Router $router): void
    {
        $auth = [AuthMiddleware::class];
        $write = [AuthMiddleware::class, CsrfMiddleware::class];

        $router->get('/crm', [CrmController::class, 'index'], $auth);
        $router->get('/crm/export', [CrmController::class, 'exportCsv'], $auth);
        $router->get('/crm/opportunites/{id}', [CrmController::class, 'show'], $auth);

        $router->post('/crm/opportunites', [CrmController::class, 'store'], $write);
        $router->post('/crm/opportunites/{id}/modifier', [CrmController::class, 'update'], $write);
        $router->post('/crm/opportunites/{id}/etape', [CrmController::class, 'updateStage'], $write);
        $router->post('/crm/opportunites/{id}/rattacher', [CrmController::class, 'link'], $write);
        $router->post('/crm/opportunites/{id}/activites', [CrmController::class, 'storeActivity'], $write);
        $router->post('/crm/opportunites/{id}/activites/{activityId}/supprimer', [CrmController::class, 'deleteActivity'], $write);
        $router->post('/crm/opportunites/{id}/supprimer', [CrmController::class, 'destroy'], $write);
    }

    /** Aucun jeu de données n’est imposé : le cabinet crée ses propres opportunités. */
    public function install(): void
    {
    }

    /** Réservé à une future désinstallation explicite ; jamais appelé à la désactivation. */
    public function uninstall(): void
    {
    }
}
