#!/usr/bin/env php
<?php
declare(strict_types=1);

/**
 * Précharge les rôles et données géographiques de base : php bin/seed.php
 */
if (PHP_SAPI !== 'cli') {
    exit('Ce script doit être lancé en ligne de commande.');
}

$root = dirname(__DIR__);
require $root . '/vendor/autoload.php';
define('BASE_PATH', $root);

use App\Core\Config;
use App\Core\Env;
use App\Services\CoreSeeder;

Env::load($root . '/.env');
Config::load($root . '/config');

date_default_timezone_set((string) Config::get('tunisia.timezone', 'Africa/Tunis'));

try {
    $summary = (new CoreSeeder())->run();
    foreach ($summary as $table => $count) {
        printf("%-16s %d\n", $table . ':', $count);
    }
    echo "Préchargement terminé.\n";
} catch (Throwable $error) {
    fwrite(STDERR, 'Erreur de préchargement : ' . $error->getMessage() . "\n");
    exit(1);
}
