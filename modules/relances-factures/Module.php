<?php
declare(strict_types=1);

namespace Modules\RelancesFactures;

use App\Core\Auth;
use App\Core\HookManager;
use App\Core\Router;
use App\Middleware\AuthMiddleware;
use App\Middleware\CsrfMiddleware;
use Modules\RelancesFactures\Controllers\RelanceController;

/**
 * Module « relances-factures » : balance âgée, règles et courriers de relance.
 *
 * Le module lit les factures et les règlements du cœur sans jamais les modifier ; il ne
 * comporte aucun envoi automatique de courriel (le noyau n’expose pas de service de messagerie).
 */
final class Module
{
    /** Entrée de navigation avant les réglages, si l’utilisateur peut consulter les documents. */
    public function boot(): void
    {
        HookManager::addFilter('sidebar.items', static function (array $items): array {
            if (!Auth::can('documents.view')) {
                return $items;
            }
            $entry = [
                'label' => __('relance.title'),
                'url' => url('/relances'),
                'icon' => 'wallet',
                'key' => 'relances-factures',
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

    /** Routes du module ; les chemins statiques sont déclarés avant les chemins paramétrés. */
    public function routes(Router $router): void
    {
        $auth = [AuthMiddleware::class];
        $write = [AuthMiddleware::class, CsrfMiddleware::class];

        $router->get('/relances', [RelanceController::class, 'index'], $auth);
        $router->get('/relances/impayes', [RelanceController::class, 'unpaid'], $auth);
        $router->get('/relances/historique', [RelanceController::class, 'history'], $auth);
        $router->get('/relances/regles', [RelanceController::class, 'rules'], $auth);
        $router->post('/relances/regles', [RelanceController::class, 'saveRule'], $write);
        $router->post('/relances/regles/{ruleId}', [RelanceController::class, 'updateRule'], $write);
        $router->post('/relances/modeles', [RelanceController::class, 'saveTemplate'], $write);
        $router->post('/relances/clients/{clientId}/exclusion', [RelanceController::class, 'excludeClient'], $write);
        $router->get('/relances/factures/{documentId}', [RelanceController::class, 'letterForm'], $auth);
        $router->post('/relances/factures/{documentId}', [RelanceController::class, 'record'], $write);
    }

    /** Aucune donnée imposée : les règles de relance sont créées par le cabinet. */
    public function install(): void
    {
    }

    /** Réservé à une future désinstallation explicite ; jamais appelé à la désactivation. */
    public function uninstall(): void
    {
    }
}
