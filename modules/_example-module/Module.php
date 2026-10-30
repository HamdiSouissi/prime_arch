<?php
declare(strict_types=1);

namespace Modules\ExampleModule;

use App\Core\HookManager;
use App\Core\Request;
use App\Core\Response;
use App\Core\Router;
use App\Core\View;

/**
 * Point d'entrée du module d'exemple.
 * ModuleManager instancie cette classe quand le module est activé, puis appelle boot() et routes().
 *
 * Pour tester : copier le dossier sous un nom sans "_" (ex. modules/example-module)
 * et ajouter 'example-module' dans config/modules.php.
 */
final class Module
{
    /** Initialisation : enregistrement des hooks (menu, événements…). */
    public function boot(): void
    {
        HookManager::addFilter('sidebar.items', function (array $items): array {
            $items[] = ['label' => 'Exemple', 'url' => url('/example'), 'icon' => '★'];
            return $items;
        });
    }

    /** Déclaration des routes du module. */
    public function routes(Router $router): void
    {
        $router->get('/example', function (Request $request): Response {
            return Response::html(View::render('pages/home', [
                'title' => 'Module d\'exemple', 'phpVersion' => PHP_VERSION, 'phpOk' => true,
                'dbOk' => true, 'appVersion' => config('app.version'),
            ]));
        });
        // TODO : utiliser les vues du module (modules/<slug>/views) via un chemin dédié.
    }

    /** Appelé à l'installation (TODO : exécuter modules/<slug>/migrations). */
    public function install(): void
    {
    }

    /** Appelé à la désinstallation (TODO : nettoyage optionnel des données). */
    public function uninstall(): void
    {
    }
}
