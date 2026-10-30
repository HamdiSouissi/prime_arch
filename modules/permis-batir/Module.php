<?php
declare(strict_types=1);

namespace Modules\PermisBatir;

use App\Core\Auth;
use App\Core\HookManager;
use App\Core\Router;
use App\Middleware\AuthMiddleware;
use App\Middleware\CsrfMiddleware;
use Modules\PermisBatir\Controllers\DossierController;

/**
 * Module « permis-batir » : dossiers de permis de bâtir.
 *
 * Le module ne modifie aucune table du cœur : il lit les projets et les clients du noyau
 * et stocke ses données dans ses propres tables `permis_batir_*`.
 */
final class Module
{
    /** Ajoute l'entrée de navigation, avant les réglages, si l'utilisateur peut voir les projets. */
    public function boot(): void
    {
        HookManager::addFilter('sidebar.items', static function (array $items): array {
            if (!Auth::can('projects.view')) {
                return $items;
            }
            $entry = [
                'label' => __('permis.title'),
                'url' => url('/permis'),
                'icon' => 'shield',
                'key' => 'permis-batir',
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

    /** Déclare les routes du module ; l'ordre compte, `/permis/new` précède `/permis/{id}`. */
    public function routes(Router $router): void
    {
        $auth = [AuthMiddleware::class];
        $write = [AuthMiddleware::class, CsrfMiddleware::class];

        $router->get('/permis', [DossierController::class, 'index'], $auth);
        $router->get('/permis/export-csv', [DossierController::class, 'exportCsv'], $auth);
        $router->get('/permis/new', [DossierController::class, 'createForm'], $auth);
        $router->post('/permis', [DossierController::class, 'create'], $write);
        $router->get('/permis/{id}', [DossierController::class, 'show'], $auth);
        $router->get('/permis/{id}/edit', [DossierController::class, 'editForm'], $auth);
        $router->post('/permis/{id}', [DossierController::class, 'update'], $write);
        $router->post('/permis/{id}/pieces/{pieceId}', [DossierController::class, 'togglePiece'], $write);
        $router->post('/permis/{id}/evenements', [DossierController::class, 'addEvent'], $write);
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
