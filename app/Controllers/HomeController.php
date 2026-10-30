<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Core\View;

/**
 * Page d'accueil : vérifie que l'installation fonctionne.
 */
final class HomeController
{
    public function index(Request $request): Response
    {
        return Response::html(View::render('pages/home', [
            'title'      => __('app.install_ok'),
            'phpVersion' => PHP_VERSION,
            'phpOk'      => version_compare(PHP_VERSION, '8.2.0', '>='),
            'dbOk'       => Database::isAvailable(),
            'appVersion' => config('app.version'),
        ]));
    }
}
