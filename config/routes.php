<?php
declare(strict_types=1);

use App\Controllers\AuthController;
use App\Controllers\ClientController;
use App\Controllers\CustomFieldController;
use App\Controllers\DocumentController;
use App\Controllers\DocumentTemplateController;
use App\Controllers\DashboardController;
use App\Controllers\DesignController;
use App\Controllers\GeographyController;
use App\Controllers\HomeController;
use App\Controllers\HourlyRateController;
use App\Controllers\MediaController;
use App\Controllers\ModuleAssetController;
use App\Controllers\ModuleController;
use App\Controllers\ProjectController;
use App\Controllers\TimeEntryController;
use App\Controllers\ProjectPhaseController;
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

    $router->get('/clients', [ClientController::class, 'index'], [AuthMiddleware::class]);
    $router->get('/clients/new', [ClientController::class, 'createForm'], [AuthMiddleware::class]);
    $router->post('/clients', [ClientController::class, 'create'], [AuthMiddleware::class, CsrfMiddleware::class]);
    $router->get('/clients/{id}/edit', [ClientController::class, 'edit'], [AuthMiddleware::class]);
    $router->post('/clients/{id}', [ClientController::class, 'update'], [AuthMiddleware::class, CsrfMiddleware::class]);
    $router->post('/clients/{id}/contacts', [ClientController::class, 'createContact'], [AuthMiddleware::class, CsrfMiddleware::class]);
    $router->post('/clients/{id}/contacts/{contactId}', [ClientController::class, 'updateContact'], [AuthMiddleware::class, CsrfMiddleware::class]);
    $router->post('/clients/{id}/contacts/{contactId}/delete', [ClientController::class, 'deleteContact'], [AuthMiddleware::class, CsrfMiddleware::class]);
    $router->get('/clients/{id}', [ClientController::class, 'show'], [AuthMiddleware::class]);

    $router->get('/projects', [ProjectController::class, 'index'], [AuthMiddleware::class]);
    $router->get('/projects/new', [ProjectController::class, 'createForm'], [AuthMiddleware::class]);
    $router->post('/projects', [ProjectController::class, 'create'], [AuthMiddleware::class, CsrfMiddleware::class]);
    $router->get('/projects/{id}/edit', [ProjectController::class, 'edit'], [AuthMiddleware::class]);
    $router->post('/projects/{id}', [ProjectController::class, 'update'], [AuthMiddleware::class, CsrfMiddleware::class]);
    $router->post('/projects/{id}/plan', [ProjectController::class, 'savePlan'], [AuthMiddleware::class, CsrfMiddleware::class]);
    $router->post('/projects/{id}/phases/{assignmentId}/progress', [ProjectController::class, 'updatePhaseProgress'], [AuthMiddleware::class, CsrfMiddleware::class]);
    $router->post('/projects/{id}/phases/{assignmentId}', [ProjectController::class, 'updatePhase'], [AuthMiddleware::class, CsrfMiddleware::class]);
    $router->get('/projects/{id}', [ProjectController::class, 'show'], [AuthMiddleware::class]);

    $router->get('/documents', [DocumentController::class, 'index'], [AuthMiddleware::class]);
    $router->get('/documents/new', [DocumentController::class, 'createForm'], [AuthMiddleware::class]);
    $router->post('/documents', [DocumentController::class, 'create'], [AuthMiddleware::class, CsrfMiddleware::class]);
    $router->get('/documents/{id}/edit', [DocumentController::class, 'edit'], [AuthMiddleware::class]);
    $router->post('/documents/{id}', [DocumentController::class, 'update'], [AuthMiddleware::class, CsrfMiddleware::class]);
    $router->post('/documents/{id}/issue', [DocumentController::class, 'issue'], [AuthMiddleware::class, CsrfMiddleware::class]);
    $router->post('/documents/{id}/delete', [DocumentController::class, 'deleteDraft'], [AuthMiddleware::class, CsrfMiddleware::class]);
    $router->get('/documents/{id}/pdf', [DocumentController::class, 'pdf'], [AuthMiddleware::class]);
    $router->post('/documents/{id}/payments', [DocumentController::class, 'addPayment'], [AuthMiddleware::class, CsrfMiddleware::class]);
    $router->get('/documents/{id}', [DocumentController::class, 'show'], [AuthMiddleware::class]);

    $router->get('/settings/document-templates', [DocumentTemplateController::class, 'index'], [AuthMiddleware::class]);
    $router->post('/settings/document-templates', [DocumentTemplateController::class, 'create'], [AuthMiddleware::class, CsrfMiddleware::class]);
    $router->post('/settings/document-templates/{id}', [DocumentTemplateController::class, 'update'], [AuthMiddleware::class, CsrfMiddleware::class]);

    $router->get('/time', [TimeEntryController::class, 'index'], [AuthMiddleware::class]);
    $router->get('/time/new', [TimeEntryController::class, 'createForm'], [AuthMiddleware::class]);
    $router->post('/time', [TimeEntryController::class, 'create'], [AuthMiddleware::class, CsrfMiddleware::class]);
    $router->get('/time/{id}/edit', [TimeEntryController::class, 'edit'], [AuthMiddleware::class]);
    $router->post('/time/{id}', [TimeEntryController::class, 'update'], [AuthMiddleware::class, CsrfMiddleware::class]);

    $router->get('/settings/hourly-rates', [HourlyRateController::class, 'index'], [AuthMiddleware::class]);
    $router->post('/settings/hourly-rates', [HourlyRateController::class, 'create'], [AuthMiddleware::class, CsrfMiddleware::class]);
    $router->get('/settings/project-phases', [ProjectPhaseController::class, 'index'], [AuthMiddleware::class]);
    $router->post('/settings/project-phases', [ProjectPhaseController::class, 'create'], [AuthMiddleware::class, CsrfMiddleware::class]);
    $router->post('/settings/project-phases/{id}', [ProjectPhaseController::class, 'update'], [AuthMiddleware::class, CsrfMiddleware::class]);
    $router->get('/settings/custom-fields', [CustomFieldController::class, 'index'], [AuthMiddleware::class]);
    $router->post('/settings/custom-fields', [CustomFieldController::class, 'create'], [AuthMiddleware::class, CsrfMiddleware::class]);
    $router->post('/settings/custom-fields/{id}', [CustomFieldController::class, 'update'], [AuthMiddleware::class, CsrfMiddleware::class]);
    $router->post('/settings/custom-fields/{id}/toggle', [CustomFieldController::class, 'toggle'], [AuthMiddleware::class, CsrfMiddleware::class]);

    $router->get('/settings/modules', [ModuleController::class, 'index'], [AuthMiddleware::class, AdminMiddleware::class]);
    $router->post('/settings/modules/install', [ModuleController::class, 'install'], [AuthMiddleware::class, AdminMiddleware::class, CsrfMiddleware::class]);
    $router->post('/settings/modules/{slug}/activate', [ModuleController::class, 'activate'], [AuthMiddleware::class, AdminMiddleware::class, CsrfMiddleware::class]);
    $router->post('/settings/modules/{slug}/deactivate', [ModuleController::class, 'deactivate'], [AuthMiddleware::class, AdminMiddleware::class, CsrfMiddleware::class]);

    $router->get('/settings', [SettingsController::class, 'edit'], [AuthMiddleware::class]);
    $router->post('/settings', [SettingsController::class, 'update'], [AuthMiddleware::class, CsrfMiddleware::class]);
    $router->get('/admin/users', [UserController::class, 'index'], [AuthMiddleware::class, AdminMiddleware::class]);
    $router->post('/admin/users', [UserController::class, 'create'], [AuthMiddleware::class, AdminMiddleware::class, CsrfMiddleware::class]);
    $router->post('/admin/users/{id}', [UserController::class, 'update'], [AuthMiddleware::class, AdminMiddleware::class, CsrfMiddleware::class]);

    $router->get('/api/geography/delegations', [GeographyController::class, 'delegations'], [AuthMiddleware::class]);
    $router->get('/api/geography/postal-codes', [GeographyController::class, 'postalCodes'], [AuthMiddleware::class]);
    $router->get('/media/cabinet-logo', [MediaController::class, 'cabinetLogo'], [AuthMiddleware::class]);
    $router->get('/module-assets/{slug}', [ModuleAssetController::class, 'show'], [AuthMiddleware::class]);

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
