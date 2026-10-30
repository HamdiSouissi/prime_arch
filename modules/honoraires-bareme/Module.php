<?php
declare(strict_types=1);

namespace Modules\HonorairesBareme;

use App\Core\Auth;
use App\Core\HookManager;
use App\Core\Router;
use App\Middleware\AuthMiddleware;
use App\Middleware\CsrfMiddleware;
use Modules\HonorairesBareme\Controllers\HonoraireController;

/**
 * Module « honoraires-bareme » : barèmes, décomptes par phase et devis d’honoraires.
 *
 * Le module lit les projets, les phases et les clients du cœur et n’écrit que dans ses tables
 * `bareme_*`, à l’exception du devis que le cabinet demande explicitement depuis un décompte.
 * Aucun taux officiel n’est fourni : le barème est saisi par le cabinet.
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
                'label' => __('honoraire.title'),
                'url' => url('/honoraires'),
                'icon' => 'currency',
                'key' => 'honoraires-bareme',
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

    /** Routes du module ; les chemins fixes sont déclarés avant `/honoraires/{projectId}`. */
    public function routes(Router $router): void
    {
        $auth = [AuthMiddleware::class];
        $write = [AuthMiddleware::class, CsrfMiddleware::class];

        $router->get('/honoraires', [HonoraireController::class, 'index'], $auth);
        $router->get('/honoraires/baremes', [HonoraireController::class, 'baremes'], $auth);
        $router->post('/honoraires/baremes', [HonoraireController::class, 'createBareme'], $write);
        $router->get('/honoraires/baremes/{baremeId}', [HonoraireController::class, 'bareme'], $auth);
        $router->post('/honoraires/baremes/{baremeId}', [HonoraireController::class, 'updateBareme'], $write);
        $router->post('/honoraires/baremes/{baremeId}/delete', [HonoraireController::class, 'deleteBareme'], $write);
        $router->post('/honoraires/baremes/{baremeId}/tranches', [HonoraireController::class, 'addTranche'], $write);
        $router->post('/honoraires/baremes/{baremeId}/tranches/{trancheId}/delete', [HonoraireController::class, 'deleteTranche'], $write);
        $router->get('/honoraires/decomptes/{decompteId}', [HonoraireController::class, 'showDecompte'], $auth);
        $router->post('/honoraires/decomptes/{decompteId}/valider', [HonoraireController::class, 'validateDecompte'], $write);
        $router->post('/honoraires/decomptes/{decompteId}/devis', [HonoraireController::class, 'createQuote'], $write);
        $router->post('/honoraires/decomptes/{decompteId}/delete', [HonoraireController::class, 'deleteDecompte'], $write);
        $router->get('/honoraires/{projectId}', [HonoraireController::class, 'project'], $auth);
        $router->post('/honoraires/{projectId}/decomptes', [HonoraireController::class, 'createDecompte'], $write);
    }

    /** Aucun barème imposé : le cabinet crée ses missions et ses tranches. */
    public function install(): void
    {
    }

    /** Réservé à une future désinstallation explicite ; jamais appelé à la désactivation. */
    public function uninstall(): void
    {
    }
}
