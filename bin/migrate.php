#!/usr/bin/env php
<?php
declare(strict_types=1);

/**
 * Exécute les migrations en ligne de commande : php bin/migrate.php
 */

if (PHP_SAPI !== 'cli') {
    exit('Ce script doit être lancé en ligne de commande.');
}

$root = dirname(__DIR__);
require $root . '/vendor/autoload.php';

define('BASE_PATH', $root);

use App\Core\Config;
use App\Core\Env;
use App\Core\Migrator;
use App\Core\ModuleManager;

Env::load($root . '/.env');
Config::load($root . '/config');

try {
    // Migrations du cœur + migrations des modules activés
    $dirs = [$root . '/database/migrations'];
    ModuleManager::discover();
    foreach (ModuleManager::enabled() as $slug) {
        $dirs[] = $root . '/modules/' . $slug . '/migrations';
    }

    $executed = (new Migrator())->run($dirs);
    echo $executed === []
        ? "Rien à migrer.\n"
        : "Migrations exécutées :\n - " . implode("\n - ", $executed) . "\n";
} catch (Throwable $e) {
    fwrite(STDERR, 'Erreur : ' . $e->getMessage() . "\n");
    exit(1);
}
