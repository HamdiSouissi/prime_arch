#!/usr/bin/env php
<?php
declare(strict_types=1);

/** Exécute les migrations du cœur et des modules activés : php bin/migrate.php */

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
use App\Services\ModuleManifestValidator;

Env::load($root . '/.env');
Config::load($root . '/config');

try {
    $migrator = new Migrator();
    // La migration du registre doit précéder sa lecture par ModuleManager.
    $executed = $migrator->run([$root . '/database/migrations']);
    $modules = ModuleManager::discover();
    $validator = new ModuleManifestValidator();
    $moduleDirs = [];

    foreach (ModuleManager::enabled() as $slug) {
        $manifest = $modules[$slug] ?? null;
        if (!is_array($manifest)) {
            fwrite(STDERR, "Module activé mais indisponible, migrations ignorées : {$slug}\n");
            continue;
        }
        try {
            $validator->assertCompatible($manifest);
            $rootPath = realpath((string) $manifest['path']);
            $migrationPath = (string) $manifest['path'] . DIRECTORY_SEPARATOR . $manifest['migrations'];
            $realMigrationPath = realpath($migrationPath);
            if ($rootPath === false || $realMigrationPath === false || is_link($migrationPath)
                || !str_starts_with($realMigrationPath, $rootPath . DIRECTORY_SEPARATOR)
                || !is_dir($realMigrationPath)
            ) {
                continue;
            }
            $safe = true;
            foreach (glob($realMigrationPath . DIRECTORY_SEPARATOR . '*.sql') ?: [] as $file) {
                $realFile = realpath($file);
                if (is_link($file) || $realFile === false
                    || !str_starts_with($realFile, $realMigrationPath . DIRECTORY_SEPARATOR)
                    || strlen(str_replace('\\', '/', substr($file, strlen(BASE_PATH) + 1))) > 255
                ) {
                    $safe = false;
                    break;
                }
            }
            if ($safe) {
                $moduleDirs[] = $realMigrationPath;
            }
        } catch (Throwable $error) {
            fwrite(STDERR, "Migrations ignorées pour {$slug} : {$error->getMessage()}\n");
        }
    }

    if ($moduleDirs !== []) {
        $executed = array_merge($executed, $migrator->run($moduleDirs));
    }
    echo $executed === []
        ? "Rien à migrer.\n"
        : "Migrations exécutées :\n - " . implode("\n - ", $executed) . "\n";
} catch (Throwable $error) {
    fwrite(STDERR, 'Erreur : ' . $error->getMessage() . "\n");
    exit(1);
}
