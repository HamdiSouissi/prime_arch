<?php
declare(strict_types=1);

namespace Modules\FiscalTn;

use App\Core\Auth;
use App\Core\HookManager;
use App\Core\Router;
use App\Middleware\AuthMiddleware;
use App\Middleware\CsrfMiddleware;
use Modules\FiscalTn\Controllers\FiscalController;

/**
 * Module « fiscal-tn » : échéancier et suivi des déclarations fiscales.
 *
 * Le module n’impose ni taux, ni périodicité, ni délai légal : il n’écrit que dans ses tables
 * `fiscal_*` et ne modifie aucune donnée du noyau.
 */
final class Module
{
    /** Entrée de navigation avant les réglages, si l’utilisateur peut lire les documents. */
    public function boot(): void
    {
        HookManager::addFilter('sidebar.items', static function (array $items): array {
            if (!Auth::can('documents.view') && !Auth::can('finance.view')) {
                return $items;
            }
            $entry = [
                'label' => __('fiscal.title'),
                'url' => url('/fiscal'),
                'icon' => 'calendar',
                'key' => 'fiscal-tn',
                'section' => 'finance',
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

    /** Routes du module ; l’export et les actions passent toujours après les routes de liste. */
    public function routes(Router $router): void
    {
        $auth = [AuthMiddleware::class];
        $write = [AuthMiddleware::class, CsrfMiddleware::class];

        $router->get('/fiscal', [FiscalController::class, 'index'], $auth);
        $router->get('/fiscal/export', [FiscalController::class, 'exportCsv'], $auth);
        $router->post('/fiscal/echeances', [FiscalController::class, 'storeEcheance'], $write);
        $router->post('/fiscal/echeances/{echeanceId}/supprimer', [FiscalController::class, 'deleteEcheance'], $write);
        $router->post('/fiscal/declarations', [FiscalController::class, 'saveDeclaration'], $write);
    }

    /** Aucune échéance préremplie : le cabinet saisit ses propres obligations. */
    public function install(): void
    {
    }

    /** Réservé à une future désinstallation explicite ; jamais appelé à la désactivation. */
    public function uninstall(): void
    {
    }
}
