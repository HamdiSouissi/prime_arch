<?php
declare(strict_types=1);

namespace Modules\ExampleModule;

use App\Core\HookManager;
use App\Core\Request;
use App\Core\Response;
use App\Core\Router;
use App\Core\View;
use App\Middleware\AuthMiddleware;
use App\Services\ViewContext;

/** Exemple de point d'entrée pour un module installé depuis une archive vérifiée. */
final class Module
{
    /** Enregistre un filtre de navigation; les hooks sont réinitialisés à chaque requête. */
    public function boot(): void
    {
        HookManager::addFilter('sidebar.items', static function (array $items): array {
            $items[] = [
                'label' => 'Exemple',
                'url' => url('/example'),
                'icon' => 'grid',
                'key' => 'example-module',
            ];
            return $items;
        });
    }

    /** Déclare une route authentifiée et rend une vue du dossier privé views/. */
    public function routes(Router $router): void
    {
        $router->get('/example', static function (Request $request): Response {
            $context = (new ViewContext())->authenticated('Module d’exemple', 'example-module');
            return Response::html(View::renderModule('example-module', 'home', $context));
        }, [AuthMiddleware::class]);
    }

    /** Appelé une fois, après les migrations du module et lors de sa première activation. */
    public function install(): void
    {
    }

    /** Réservé à une future désinstallation explicite; jamais appelé à la désactivation. */
    public function uninstall(): void
    {
    }
}
