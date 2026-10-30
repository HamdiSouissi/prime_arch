<?php
declare(strict_types=1);

namespace App\Core;

use Throwable;

/**
 * Noyau de l'application : initialisation puis traitement de la requête.
 */
final class Application
{
    public function __construct(private string $basePath)
    {
        define('BASE_PATH', rtrim($basePath, '/\\'));
    }

    public function run(): void
    {
        $this->boot();

        try {
            $router = new Router();
            (require BASE_PATH . '/config/routes.php')($router);
            ModuleManager::boot($router);
            HookManager::doAction('app.booted', $router);

            $response = $router->dispatch(Request::capture());
            $response->send();
        } catch (Throwable $e) {
            $this->handleException($e);
        }
    }

    private function boot(): void
    {
        Env::load(BASE_PATH . '/.env');
        Config::load(BASE_PATH . '/config');

        date_default_timezone_set((string) Config::get('tunisia.timezone', 'Africa/Tunis'));
        mb_internal_encoding('UTF-8');

        if (Config::get('app.debug')) {
            ini_set('display_errors', '1');
            error_reporting(E_ALL);
        } else {
            ini_set('display_errors', '0');
        }
        ini_set('log_errors', '1');
        ini_set('error_log', BASE_PATH . '/storage/logs/php-error.log');

        Session::start();
        Translator::setLocale((string) (Session::get('locale') ?? Config::get('app.locale', 'fr')));
    }

    private function handleException(Throwable $e): void
    {
        error_log((string) $e);
        http_response_code(500);
        if (Config::get('app.debug')) {
            echo '<pre>' . htmlspecialchars((string) $e, ENT_QUOTES, 'UTF-8') . '</pre>';
        } else {
            echo 'Une erreur est survenue.';
        }
    }
}
