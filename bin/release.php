#!/usr/bin/env php
<?php
declare(strict_types=1);

/**
 * Construit l'archive à téléverser sur l'hébergement : php bin/release.php
 *
 * Le paquet contient tout ce qui est nécessaire à l'exécution et rien d'autre : ni .git,
 * ni tests, ni .env (les secrets restent sur le serveur), ni fichiers de travail. Le fichier
 * produit se place dans storage/tmp/ et son empreinte SHA-256 est affichée.
 *
 * Aucune dépendance : le script fonctionne même avant « composer install ».
 *
 * Options : --output <chemin.zip>  archive de destination (défaut : storage/tmp/prime-arch-<date>.zip)
 *           --with-vendor          inclut vendor/ même si vous préférez l'envoyer à part
 *           --no-vendor            exclut vendor/ (le serveur exécutera composer install)
 *           --dry-run              affiche le résumé du contenu sans créer d'archive
 *           --list                 affiche chaque fichier empaqueté, sans créer d'archive
 */

if (PHP_SAPI !== 'cli') {
    exit('Ce script doit être lancé en ligne de commande.');
}

$root = dirname(__DIR__);

// Toujours exclus : historique, secrets, fichiers de développement.
const ALWAYS_EXCLUDED = ['.git', '.github', '.idea', '.vscode', '.env', '.env.local', 'phpunit.xml', 'tests', 'prime-arch-design.zip'];

// Contenus générés au fil de l'exploitation : seuls les .htaccess et .gitkeep sont repris.
const RUNTIME_DIRECTORIES = ['storage/logs', 'storage/cache', 'storage/uploads', 'storage/pdf', 'storage/backups', 'storage/modules', 'storage/tmp'];

$arguments = array_slice($argv, 1);
$output = '';
$vendorMode = 'auto';
$dryRun = false;
$list = false;
foreach ($arguments as $index => $argument) {
    if ($argument === '--output') {
        $output = (string) ($arguments[$index + 1] ?? '');
    } elseif ($argument === '--dry-run') {
        $dryRun = true;
    } elseif ($argument === '--list') {
        $list = true;
    } elseif ($argument === '--with-vendor') {
        $vendorMode = 'with';
    } elseif ($argument === '--no-vendor') {
        $vendorMode = 'without';
    } elseif (str_starts_with($argument, '--')) {
        fwrite(STDERR, "Option inconnue : {$argument}\n");
        exit(2);
    }
}

$hasVendor = is_file($root . '/vendor/autoload.php');
if ($vendorMode === 'with' && !$hasVendor) {
    fwrite(STDERR, "vendor/ est absent : exécutez « composer install --no-dev --optimize-autoloader » d'abord.\n");
    exit(1);
}
$includeVendor = $vendorMode === 'with' || ($vendorMode === 'auto' && $hasVendor);

$output = $output !== ''
    ? (str_starts_with($output, '/') ? $output : $root . '/' . $output)
    : $root . '/storage/tmp/prime-arch-' . date('Ymd-His') . '.zip';
$outputDirectory = dirname($output);
if (!is_dir($outputDirectory) && !mkdir($outputDirectory, 0775, true) && !is_dir($outputDirectory)) {
    fwrite(STDERR, "Impossible de créer le dossier de sortie : {$outputDirectory}\n");
    exit(1);
}

/** Chemin relatif avec séparateurs « / », tel qu'il apparaîtra dans l'archive. */
function relativePath(string $root, string $path): string
{
    return str_replace('\\', '/', substr($path, strlen($root) + 1));
}

/** Dossier entièrement écarté (historique, développement) : rien à y prendre. */
function isExcludedDirectory(string $relative, bool $includeVendor): bool
{
    if (!$includeVendor && ($relative === 'vendor' || str_starts_with($relative, 'vendor/'))) {
        return true;
    }
    foreach (ALWAYS_EXCLUDED as $excluded) {
        if ($relative === $excluded || str_starts_with($relative, $excluded . '/')) {
            return true;
        }
    }

    return false;
}

/** Fichier écarté : tout ce qui est généré pendant l'exploitation, sauf les protections. */
function isExcludedFile(string $relative, bool $includeVendor): bool
{
    if (isExcludedDirectory($relative, $includeVendor)) {
        return true;
    }
    foreach (RUNTIME_DIRECTORIES as $runtime) {
        if ($relative === $runtime || str_starts_with($relative, $runtime . '/')) {
            return !str_ends_with($relative, '.htaccess') && !str_ends_with($relative, '.gitkeep');
        }
    }

    return false;
}

/** Fichiers à empaqueter (chemin relatif => chemin absolu), triés. */
function collectFiles(string $root, bool $includeVendor): array
{
    $files = [];
    $directory = new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS);
    $filter = new RecursiveCallbackFilterIterator(
        $directory,
        static function (SplFileInfo $file) use ($root, $includeVendor): bool {
            if (is_link($file->getPathname())) {
                return false;
            }
            $relative = relativePath($root, $file->getPathname());

            return $file->isDir() ? !isExcludedDirectory($relative, $includeVendor) : !isExcludedFile($relative, $includeVendor);
        }
    );
    foreach (new RecursiveIteratorIterator($filter) as $file) {
        if ($file instanceof SplFileInfo && $file->isFile() && !$file->isLink()) {
            $files[relativePath($root, $file->getPathname())] = $file->getPathname();
        }
    }
    ksort($files);

    return $files;
}

try {
    $files = collectFiles($root, $includeVendor);
    if ($files === []) {
        throw new RuntimeException('Aucun fichier à empaqueter.');
    }
    if ($list) {
        foreach (array_keys($files) as $relative) {
            echo $relative, "\n";
        }
        printf("-- %d fichier(s) · vendor/ : %s\n", count($files), $includeVendor ? 'inclus' : 'exclu');
        exit(0);
    }
    if ($dryRun) {
        $perDirectory = [];
        foreach (array_keys($files) as $relative) {
            $top = str_contains($relative, '/') ? strstr($relative, '/', true) : '(racine)';
            $perDirectory[$top] = ($perDirectory[$top] ?? 0) + 1;
        }
        ksort($perDirectory);
        echo "Contenu prévu (aucune archive créée) :\n";
        foreach ($perDirectory as $directory => $count) {
            printf("  %-16s %d fichier(s)\n", $directory, $count);
        }
        printf("Total : %d fichier(s) · vendor/ : %s\n", count($files), $includeVendor ? 'inclus' : 'exclu');
        exit(0);
    }
    if (is_file($output) && !unlink($output)) {
        throw new RuntimeException('Impossible de remplacer l’archive existante : ' . $output);
    }

    if (class_exists(ZipArchive::class)) {
        $archive = new ZipArchive();
        if ($archive->open($output, ZipArchive::CREATE | ZipArchive::EXCL) !== true) {
            throw new RuntimeException('Impossible de créer ' . $output);
        }
        foreach ($files as $relative => $absolute) {
            if (!$archive->addFile($absolute, 'prime-arch/' . $relative)) {
                throw new RuntimeException('Ajout impossible : ' . $relative);
            }
        }
        if (!$archive->close()) {
            throw new RuntimeException('Fermeture de l’archive impossible.');
        }
    } elseif (class_exists(PharData::class)) {
        $archive = new PharData($output);
        foreach ($files as $relative => $absolute) {
            $archive->addFile($absolute, 'prime-arch/' . $relative);
        }
        unset($archive);
    } else {
        throw new RuntimeException('Extensions zip et phar indisponibles : archive impossible sur ce PHP.');
    }

    $size = (int) filesize($output);
    printf("Archive : %s\n", $output);
    printf("Fichiers : %d · Taille : %.1f Mo · vendor/ : %s\n", count($files), $size / 1048576, $includeVendor ? 'inclus' : 'exclu');
    printf("SHA-256 : %s\n", (string) hash_file('sha256', $output));
    echo "\nRappel : le fichier .env n'est jamais empaqueté ; il est créé sur le serveur\n";
    echo "(voir docs/DEPLOIEMENT-IONOS.md, étape 6).\n";
} catch (Throwable $error) {
    fwrite(STDERR, 'Erreur : ' . $error->getMessage() . "\n");
    exit(1);
}
