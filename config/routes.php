<?php
declare(strict_types=1);

use App\Controllers\HomeController;
use App\Core\Router;

/**
 * Déclaration des routes du cœur.
 * Les modules ajoutent les leurs via Module::routes(Router $router).
 */
return function (Router $router): void {
    $router->get('/', [HomeController::class, 'index']);

    // TODO : /login, /logout, /clients, /projets, /devis, /factures, /parametres, /parametres/modules
    // Exemple de route protégée :
    // $router->get('/clients', [ClientController::class, 'index'], [AuthMiddleware::class]);
};
