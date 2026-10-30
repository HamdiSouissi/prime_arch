#!/usr/bin/env php
<?php
declare(strict_types=1);

/** Restauration destructive et limitée à une base cible vide. */

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "Cette commande doit être lancée en ligne de commande.\n");
    exit(1);
}

$root = dirname(__DIR__);
require $root . '/vendor/autoload.php';

define('BASE_PATH', $root);

use App\Core\Config;
use App\Core\Env;
use App\Services\BackupService;

Env::load($root . '/.env');
Config::load($root . '/config');

try {
    $candidate = '';
    $confirm = false;
    foreach (array_slice($argv, 1) as $argument) {
        if (str_starts_with($argument, '--file=')) {
            $candidate = substr($argument, strlen('--file='));
        } elseif ($argument === '--confirm-empty-database') {
            $confirm = true;
        } else {
            throw new RuntimeException('Option inconnue.');
        }
    }
    if ($candidate === '') {
        throw new RuntimeException('Indiquez --file=storage/backups/fichier.zip.');
    }
    if (!$confirm) {
        throw new RuntimeException('Ajoutez --confirm-empty-database pour autoriser la restauration.');
    }

    $result = (new BackupService())->restore($candidate, true);
    echo "Restauration terminée : {$result['tables']} tables · {$result['files']} fichiers vérifiés.\n";
} catch (Throwable $error) {
    fwrite(STDERR, 'Erreur : ' . $error->getMessage() . "\n");
    exit(1);
}
