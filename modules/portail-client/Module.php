<?php
declare(strict_types=1);

namespace Modules\PortailClient;

use App\Core\Auth;
use App\Core\HookManager;
use App\Core\Router;
use App\Middleware\AuthMiddleware;
use App\Middleware\CsrfMiddleware;
use Modules\PortailClient\Controllers\PortalAdminController;
use Modules\PortailClient\Controllers\PortalController;
use Modules\PortailClient\Services\PortailContext;

/**
 * Module « portail-client » : espace client en lecture seule, accessible par lien.
 *
 * Les routes publiques ne portent aucun middleware d’authentification : la seule preuve d’accès
 * est le jeton, vérifié à chaque requête (format, empreinte, expiration, révocation, limitation
 * de débit). Elles sont strictement en lecture : aucune route publique n’accepte de POST et le
 * module n’écrit dans aucune table du noyau.
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
                'label' => __('portail.title'),
                'url' => url('/portail'),
                'icon' => 'globe',
                'key' => 'portail-client',
                'section' => 'conformite',
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
        PortailContext::register();
    }

    /** Routes du cabinet (authentifiées) puis routes publiques à jeton, en lecture seule. */
    public function routes(Router $router): void
    {
        $auth = [AuthMiddleware::class];
        $write = [AuthMiddleware::class, CsrfMiddleware::class];

        $router->get('/portail', [PortalAdminController::class, 'index'], $auth);
        $router->post('/portail/liens', [PortalAdminController::class, 'store'], $write);
        $router->post('/portail/liens/{id}/revoquer', [PortalAdminController::class, 'revoke'], $write);
        $router->post('/portail/liens/{id}/prolonger', [PortalAdminController::class, 'extend'], $write);
        $router->post('/portail/liens/{id}/supprimer', [PortalAdminController::class, 'destroy'], $write);

        // Aucun middleware : le jeton est le seul justificatif, et ces routes ne modifient rien.
        $router->get('/portail/{token}', [PortalController::class, 'show']);
        $router->get('/portail/{token}/photo/{imageId}', [PortalController::class, 'photo']);
    }

    /** Aucun lien n’est créé par défaut : le cabinet décide de chaque accès. */
    public function install(): void
    {
    }

    /** Réservé à une future désinstallation explicite ; jamais appelé à la désactivation. */
    public function uninstall(): void
    {
    }
}
