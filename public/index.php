<?php
declare(strict_types=1);

/**
 * Front controller : point d'entrée unique de l'application.
 */

$root = dirname(__DIR__);

if (!is_file($root . '/vendor/autoload.php')) {
    http_response_code(500);
    exit('Dépendances manquantes : exécutez "composer install" à la racine du projet.');
}

require $root . '/vendor/autoload.php';

(new App\Core\Application($root))->run();
