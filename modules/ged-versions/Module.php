<?php
declare(strict_types=1);

namespace Modules\GedVersions;

use App\Core\Auth;
use App\Core\HookManager;
use App\Core\Router;
use App\Middleware\AuthMiddleware;
use App\Middleware\CsrfMiddleware;
use Modules\GedVersions\Controllers\GedController;
use Modules\GedVersions\Services\GedContext;

/**
 * Module « ged-versions » : dépôt documentaire privé, versionné et recherchable.
 *
 * Le module n’écrit que dans ses tables `ged_*` et dans son répertoire privé
 * `storage/modules/ged-versions/`. Aucune table ni aucun fichier du noyau n’est touché.
 */
final class Module
{
    /** Entrée de navigation avant les réglages, si l’utilisateur peut lire les documents. */
    public function boot(): void
    {
        HookManager::addFilter('sidebar.items', static function (array $items): array {
            if (!Auth::can('documents.view') && !Auth::can('projects.view')) {
                return $items;
            }
            $entry = [
                'label' => __('ged.title'),
                'url' => url('/ged'),
                'icon' => 'folder',
                'key' => 'ged-versions',
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
        GedContext::register();
    }

    /** Routes du module : liste et export d’abord, puis fiches, versions et actions CSRF. */
    public function routes(Router $router): void
    {
        $auth = [AuthMiddleware::class];
        $write = [AuthMiddleware::class, CsrfMiddleware::class];

        $router->get('/ged', [GedController::class, 'index'], $auth);
        $router->get('/ged/export', [GedController::class, 'exportCsv'], $auth);
        $router->get('/ged/{id}', [GedController::class, 'show'], $auth);
        $router->get('/ged/{id}/versions/{versionId}/telecharger', [GedController::class, 'download'], $auth);

        $router->post('/ged', [GedController::class, 'store'], $write);
        $router->post('/ged/{id}/modifier', [GedController::class, 'update'], $write);
        $router->post('/ged/{id}/versions', [GedController::class, 'upload'], $write);
        $router->post('/ged/{id}/versions/{versionId}/supprimer', [GedController::class, 'deleteVersion'], $write);
        $router->post('/ged/{id}/archiver', [GedController::class, 'archive'], $write);
        $router->post('/ged/{id}/supprimer', [GedController::class, 'destroy'], $write);
    }

    /** Aucun document n’est imposé : le cabinet constitue son propre dépôt. */
    public function install(): void
    {
    }

    /** Réservé à une future désinstallation explicite ; jamais appelé à la désactivation. */
    public function uninstall(): void
    {
    }
}
