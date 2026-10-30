#!/usr/bin/env php
<?php
declare(strict_types=1);

/** Crée ou vérifie une sauvegarde : php bin/backup.php [--verify storage/backups/fichier.zip] */

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
    $service = new BackupService();
    if (($argv[1] ?? '') === '--verify') {
        $candidate = (string) ($argv[2] ?? '');
        if ($candidate === '') {
            throw new RuntimeException('Indiquez le chemin d’une archive sous storage/backups.');
        }
        $result = $service->verifyArchive($candidate);
        echo "Archive vérifiée : {$result['path']}\n";
        echo "Créée le : {$result['created_at']}\n";
        echo "Tables : {$result['tables']} · Fichiers : {$result['files']} · Taille logique : {$result['bytes']} octets\n";
        exit(0);
    }
    if (isset($argv[1])) {
        throw new RuntimeException('Option inconnue. Utilisez --verify <archive.zip> ou lancez sans option pour créer une sauvegarde.');
    }
    $path = $service->create();
    echo "Sauvegarde créée : {$path}\n";
} catch (Throwable $error) {
    fwrite(STDERR, 'Erreur : ' . $error->getMessage() . "\n");
    exit(1);
}
