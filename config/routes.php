<?php
declare(strict_types=1);

use App\Controllers\DesignController;
use App\Controllers\HomeController;
use App\Core\Router;

/**
 * Déclaration des routes du cœur.
 * Les modules ajoutent les leurs via Module::routes(Router $router).
 */
return function (Router $router): void {
    $router->get('/', [HomeController::class, 'index']);

    // Les aperçus de design sont enregistrés uniquement hors production.
    // Ils ne lisent ni n'écrivent aucune donnée métier.
    if (strtolower((string) config('app.env', 'production')) !== 'production') {
        $router->get('/design/dashboard', [DesignController::class, 'dashboard']);
        $router->get('/design/clients', [DesignController::class, 'clients']);
        $router->get('/design/clients/{id}', [DesignController::class, 'client']);
        $router->get('/design/projects', [DesignController::class, 'projects']);
        $router->get('/design/projects/{id}', [DesignController::class, 'project']);
        $router->get('/design/documents', [DesignController::class, 'documents']);
        $router->get('/design/documents/{id}', [DesignController::class, 'document']);
        $router->get('/design/time', [DesignController::class, 'time']);
        $router->get('/design/settings', [DesignController::class, 'settings']);
        $router->get('/design/modules', [DesignController::class, 'modules']);
        $router->get('/design/login', [DesignController::class, 'login']);
        $router->get('/design/styleguide', [DesignController::class, 'styleguide']);
    }

    // TODO : /login, /logout, /clients, /projets, /devis, /factures, /parametres, /parametres/modules
    // Exemple de route protégée :
    // $router->get('/clients', [ClientController::class, 'index'], [AuthMiddleware::class]);
};
