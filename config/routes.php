<?php
declare(strict_types=1);

use App\Controllers\AuthController;
use App\Controllers\DashboardController;
use App\Controllers\DesignController;
use App\Controllers\GeographyController;
use App\Controllers\HomeController;
use App\Controllers\MediaController;
use App\Controllers\SettingsController;
use App\Controllers\UserController;
use App\Core\Router;
use App\Middleware\AdminMiddleware;
use App\Middleware\AuthMiddleware;
use App\Middleware\CsrfMiddleware;

/**
 * Déclaration des routes du cœur.
 * Les modules ajoutent les leurs via Module::routes(Router $router).
 */
return function (Router $router): void {
    $router->get('/', [AuthController::class, 'home']);
    $router->get('/health', [HomeController::class, 'index']);

    $router->get('/login', [AuthController::class, 'showLogin']);
    $router->post('/login', [AuthController::class, 'login'], [CsrfMiddleware::class]);
    $router->post('/logout', [AuthController::class, 'logout'], [AuthMiddleware::class, CsrfMiddleware::class]);
    $router->get('/dashboard', [DashboardController::class, 'index'], [AuthMiddleware::class]);

    $router->get('/settings', [SettingsController::class, 'edit'], [AuthMiddleware::class]);
    $router->post('/settings', [SettingsController::class, 'update'], [AuthMiddleware::class, CsrfMiddleware::class]);
    $router->get('/admin/users', [UserController::class, 'index'], [AuthMiddleware::class, AdminMiddleware::class]);
    $router->post('/admin/users', [UserController::class, 'create'], [AuthMiddleware::class, AdminMiddleware::class, CsrfMiddleware::class]);
    $router->post('/admin/users/{id}', [UserController::class, 'update'], [AuthMiddleware::class, AdminMiddleware::class, CsrfMiddleware::class]);

    $router->get('/api/geography/delegations', [GeographyController::class, 'delegations'], [AuthMiddleware::class]);
    $router->get('/api/geography/postal-codes', [GeographyController::class, 'postalCodes'], [AuthMiddleware::class]);
    $router->get('/media/cabinet-logo', [MediaController::class, 'cabinetLogo'], [AuthMiddleware::class]);

    // Les aperçus statiques ne sont enregistrés qu'en dehors de la production.
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
};
