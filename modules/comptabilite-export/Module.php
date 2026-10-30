<?php
declare(strict_types=1);

namespace Modules\ComptabiliteExport;

use App\Core\Auth;
use App\Core\HookManager;
use App\Core\Router;
use App\Middleware\AuthMiddleware;
use Modules\ComptabiliteExport\Controllers\ComptabiliteController;

/**
 * Module « comptabilite-export » : journaux comptables en lecture seule.
 *
 * Le module n’a **aucune table** et n’exécute aucune écriture : il lit les documents émis et
 * leurs règlements, puis propose leur export CSV. C’est le module le moins risqué du catalogue.
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
                'label' => __('comptabilite.title'),
                'url' => url('/comptabilite'),
                'icon' => 'receipt',
                'key' => 'comptabilite-export',
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

    /** Routes du module : consultation et export, toutes en lecture. */
    public function routes(Router $router): void
    {
        $auth = [AuthMiddleware::class];

        $router->get('/comptabilite', [ComptabiliteController::class, 'index'], $auth);
        $router->get('/comptabilite/export', [ComptabiliteController::class, 'exportCsv'], $auth);
    }

    /** Aucune table, aucune donnée initiale : rien à installer. */
    public function install(): void
    {
    }

    /** Réservé à une future désinstallation explicite ; jamais appelé à la désactivation. */
    public function uninstall(): void
    {
    }
}
