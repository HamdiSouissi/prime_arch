<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use PDO;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use Throwable;
use ZipArchive;

/**
 * Sauvegardes privées (archives `.zip`), vérifiables et restaurables.
 *
 * Deux modes de restauration coexistent : `restore()` n'accepte qu'une base et des
 * dossiers de stockage vides (ligne de commande), tandis que `restoreInPlace()` remplace
 * l'état de l'instance en service après avoir archivé cet état dans une sauvegarde de
 * sécurité. Les archives importées ne sont jamais exécutées : seule leur structure est
 * relue, validée puis rejouée en SQL paramétré.
 */
final class BackupService
{
    private const FORMAT_VERSION = 1;
    private const MAX_ARCHIVE_ENTRIES = 20_000;
    private const MAX_ARCHIVE_BYTES = 4_294_967_296;
    private const MAX_MANIFEST_BYTES = 2_097_152;
    private const MAX_SCHEMA_BYTES = 16_777_216;
    private const MAX_ROW_BYTES = 67_108_864;
    /** Taille maximale d'une archive importée depuis l'interface (le php.ini peut être plus strict). */
    private const MAX_IMPORT_BYTES = 268_435_456;
    /** Nombre maximal d'archives réunies dans un téléchargement groupé. */
    private const MAX_BUNDLE_ARCHIVES = 20;
    /** Plafond d'archives affichées : au-delà, les plus anciennes restent sur le disque. */
    private const MAX_LISTED_ARCHIVES = 200;
    private const STORAGE_ROOTS = ['uploads', 'pdf', 'modules'];

    private ?PDO $pdo;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo;
    }

    /** Crée une archive ZIP privée et retourne son chemin relatif au dépôt. */
    public function create(): string
    {
        if (!class_exists(ZipArchive::class)) {
            throw new RuntimeException('L’extension PHP zip est nécessaire pour créer une sauvegarde.');
        }

        $pdo = $this->connection();
        $backupDirectory = self::ensurePrivateDirectory(BASE_PATH . '/storage/backups');
        $workRoot = self::ensurePrivateDirectory(BASE_PATH . '/storage/tmp');
        $token = bin2hex(random_bytes(12));
        $workDirectory = $workRoot . '/backup-' . $token;
        $temporaryArchive = $backupDirectory . '/.prime-arch-' . $token . '.zip.tmp';
        $filename = 'prime-arch-' . date('Ymd-His') . '-' . substr($token, 0, 8) . '.zip';
        $finalArchive = $backupDirectory . '/' . $filename;

        try {
            if (!mkdir($workDirectory, 0700) || !mkdir($workDirectory . '/database', 0700)
                || !mkdir($workDirectory . '/database/data', 0700)
                || !mkdir($workDirectory . '/payload', 0700)
            ) {
                throw new RuntimeException('Le dossier temporaire de sauvegarde ne peut pas être créé.');
            }

            $schema = $this->exportDatabase($pdo, $workDirectory . '/database');
            $storageFiles = $this->snapshotStorage($workDirectory . '/payload');
            $archiveFiles = [];

            foreach (new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($workDirectory . '/database', RecursiveDirectoryIterator::SKIP_DOTS)
            ) as $file) {
                if (!$file->isFile() || $file->isLink()) {
                    throw new RuntimeException('Contenu inattendu dans les fichiers temporaires de sauvegarde.');
                }
                $relative = str_replace('\\', '/', substr($file->getPathname(), strlen($workDirectory) + 1));
                $archiveFiles[$relative] = $file->getPathname();
            }
            foreach ($storageFiles as $archivePath => $stagedPath) {
                $archiveFiles[$archivePath] = $stagedPath;
            }
            ksort($archiveFiles, SORT_STRING);

            if (count($archiveFiles) + 1 > self::MAX_ARCHIVE_ENTRIES) {
                throw new RuntimeException('La sauvegarde dépasse le nombre maximal de fichiers autorisé.');
            }

            $manifestFiles = [];
            $totalBytes = 0;
            foreach ($archiveFiles as $archivePath => $stagedPath) {
                $size = filesize($stagedPath);
                $hash = hash_file('sha256', $stagedPath);
                if (!is_int($size) || !is_string($hash)) {
                    throw new RuntimeException('Un fichier de sauvegarde ne peut pas être vérifié.');
                }
                if ($size > self::MAX_ARCHIVE_BYTES - $totalBytes) {
                    throw new RuntimeException('La sauvegarde dépasse la taille décompressée maximale autorisée.');
                }
                $totalBytes += $size;
                $manifestFiles[] = ['path' => $archivePath, 'size' => $size, 'sha256' => $hash];
            }

            $manifest = [
                'format' => self::FORMAT_VERSION,
                'app_version' => (string) config('app.version', 'unknown'),
                'created_at' => gmdate('Y-m-d\TH:i:s\Z'),
                'tables' => count($schema['tables']),
                'files' => $manifestFiles,
            ];
            $manifestPath = $workDirectory . '/manifest.json';
            $manifestContents = json_encode(
                $manifest,
                JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT
            );
            if (strlen($manifestContents) + 1 > self::MAX_MANIFEST_BYTES) {
                throw new RuntimeException('Le manifeste de sauvegarde dépasse la taille maximale autorisée.');
            }
            if (strlen($manifestContents) + 1 > self::MAX_ARCHIVE_BYTES - $totalBytes) {
                throw new RuntimeException('La sauvegarde dépasse la taille décompressée maximale autorisée.');
            }
            self::writeFile($manifestPath, $manifestContents);

            $zip = new ZipArchive();
            if ($zip->open($temporaryArchive, ZipArchive::CREATE | ZipArchive::EXCL) !== true) {
                throw new RuntimeException('L’archive ZIP de sauvegarde ne peut pas être ouverte.');
            }
            try {
                foreach ($archiveFiles as $archivePath => $stagedPath) {
                    if (!$zip->addFile($stagedPath, $archivePath)) {
                        throw new RuntimeException('Un fichier ne peut pas être ajouté à l’archive de sauvegarde.');
                    }
                }
                if (!$zip->addFile($manifestPath, 'manifest.json') || !$zip->close()) {
                    throw new RuntimeException('L’archive ZIP de sauvegarde ne peut pas être finalisée.');
                }
            } catch (Throwable $error) {
                $zip->close();
                throw $error;
            }

            if (!is_file($temporaryArchive) || !chmod($temporaryArchive, 0600) || !rename($temporaryArchive, $finalArchive)) {
                throw new RuntimeException('L’archive de sauvegarde ne peut pas être publiée.');
            }
            (new AuditLogger($pdo))->record('backup.created', 'backup', $filename, [
                'files' => count($manifestFiles),
                'tables' => count($schema['tables']),
            ], null);
            return 'storage/backups/' . $filename;
        } finally {
            if (is_file($temporaryArchive)) {
                @unlink($temporaryArchive);
            }
            self::removeTree($workDirectory);
        }
    }

    /** Vérifie le contenu et les empreintes d'une archive sans modifier la base. */
    public function verifyArchive(string $candidate): array
    {
        $path = $this->resolveArchivePath($candidate);
        $inspected = self::inspectArchive($path);
        return [
            'path' => 'storage/backups/' . basename($path),
            'created_at' => (string) $inspected['manifest']['created_at'],
            'tables' => count($inspected['schema']['tables']),
            'files' => count($inspected['manifest']['files']),
            'bytes' => array_sum(array_column($inspected['manifest']['files'], 'size')),
        ];
    }

    /** Restaure uniquement dans une base et des dossiers de stockage vides. */
    public function restore(string $candidate, bool $confirmEmptyDatabase): array
    {
        if (!$confirmEmptyDatabase) {
            throw new RuntimeException('Confirmez explicitement la restauration dans une base vide.');
        }
        if (!class_exists(ZipArchive::class)) {
            throw new RuntimeException('L’extension PHP zip est nécessaire pour restaurer une sauvegarde.');
        }

        $archivePath = $this->resolveArchivePath($candidate);
        $inspected = self::inspectArchive($archivePath);
        $pdo = $this->connection();
        $existingTables = $pdo->query('SHOW FULL TABLES')->fetchAll(\PDO::FETCH_NUM);
        if ($existingTables !== []) {
            throw new RuntimeException('La base cible n’est pas vide. Utilisez une nouvelle base dédiée à la restauration.');
        }
        self::assertStorageTargetsEmpty();

        $workRoot = self::ensurePrivateDirectory(BASE_PATH . '/storage/tmp');
        $workDirectory = $workRoot . '/restore-' . bin2hex(random_bytes(12));
        if (!mkdir($workDirectory, 0700)) {
            throw new RuntimeException('Le dossier temporaire de restauration ne peut pas être créé.');
        }

        try {
            self::extractStoragePayload($archivePath, $inspected['manifest'], $workDirectory);
            $orderedTables = self::orderTables($inspected['schema']['tables']);
            $pdo->exec("SET time_zone = '+00:00'");
            $pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
            try {
                foreach ($orderedTables as $table) {
                    $pdo->exec($table['create_sql']);
                }
                $pdo->beginTransaction();
                try {
                    foreach ($orderedTables as $table) {
                        self::restoreTableData($pdo, $archivePath, $table);
                    }
                    $pdo->commit();
                } catch (Throwable $error) {
                    if ($pdo->inTransaction()) {
                        $pdo->rollBack();
                    }
                    throw $error;
                }
            } finally {
                $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
            }
            self::installStoragePayload($workDirectory);

            (new AuditLogger($pdo))->record('backup.restored', 'backup', basename($archivePath), [
                'files' => count($inspected['manifest']['files']),
                'tables' => count($orderedTables),
            ], null);
            return ['tables' => count($orderedTables), 'files' => count($inspected['manifest']['files'])];
        } finally {
            self::removeTree($workDirectory);
        }
    }

    /** Autorise uniquement une archive locale placée dans storage/backups. */
    public function resolveArchivePath(string $candidate): string
    {
        $backupRoot = BASE_PATH . '/storage/backups';
        $backupDirectory = realpath($backupRoot);
        if ($backupDirectory === false || is_link($backupRoot) || $candidate === '' || str_contains($candidate, "\0")) {
            throw new RuntimeException('Chemin d’archive invalide.');
        }
        $path = str_starts_with($candidate, DIRECTORY_SEPARATOR)
            ? $candidate
            : BASE_PATH . '/' . ltrim($candidate, '/');
        if (!str_starts_with(str_replace('\\', '/', $path), str_replace('\\', '/', $backupDirectory) . '/')) {
            throw new RuntimeException('L’archive doit se trouver dans storage/backups.');
        }
        $resolved = realpath($path);
        if ($resolved === false || is_link($path) || !is_file($resolved)
            || !str_starts_with($resolved, $backupDirectory . DIRECTORY_SEPARATOR)
        ) {
            throw new RuntimeException('L’archive demandée est absente ou inaccessible.');
        }
        return $resolved;
    }

    /**
     * Liste les archives locales, de la plus récente à la plus ancienne.
     *
     * Seuls les fichiers `.zip` réels placés dans `storage/backups` sont retenus : ni
     * lien symbolique, ni fichier temporaire `.zip.tmp`. Le manifeste est relu sans
     * décompresser les données métier, ce qui suffit à qualifier chaque sauvegarde.
     *
     * @return list<array{name:string,path:string,bytes:int,modified_at:int,created_at:?string,tables:?int,files:?int,logical_bytes:?int,valid:bool}>
     */
    public function list(): array
    {
        $backupRoot = BASE_PATH . '/storage/backups';
        $directory = realpath($backupRoot);
        if ($directory === false || is_link($backupRoot) || !is_dir($directory)) {
            return [];
        }

        $candidates = [];
        foreach (scandir($directory) ?: [] as $name) {
            if ($name === '.' || $name === '..' || str_starts_with($name, '.')) {
                continue;
            }
            $path = $directory . '/' . $name;
            if (strtolower(pathinfo($name, PATHINFO_EXTENSION)) !== 'zip' || is_link($path) || !is_file($path)) {
                continue;
            }
            $bytes = filesize($path);
            $modifiedAt = filemtime($path);
            if ($bytes === false || $modifiedAt === false) {
                continue;
            }
            $candidates[] = [
                'name' => $name,
                'path' => $path,
                'bytes' => $bytes,
                'modified_at' => $modifiedAt,
            ];
        }

        // Les manifestes ne sont relus que pour les archives réellement affichées.
        usort($candidates, static fn (array $left, array $right): int
            => [$right['modified_at'], $right['name']] <=> [$left['modified_at'], $left['name']]);
        $candidates = array_slice($candidates, 0, self::MAX_LISTED_ARCHIVES);

        $entries = [];
        foreach ($candidates as $candidate) {
            $summary = self::describeArchive((string) $candidate['path']);
            $entries[] = [
                'name' => $candidate['name'],
                'path' => 'storage/backups/' . $candidate['name'],
                'bytes' => $candidate['bytes'],
                'modified_at' => $candidate['modified_at'],
                'created_at' => $summary['created_at'] ?? null,
                'tables' => $summary['tables'] ?? null,
                'files' => $summary['files'] ?? null,
                'logical_bytes' => $summary['logical_bytes'] ?? null,
                'valid' => $summary !== null,
            ];
        }

        return $entries;
    }

    /**
     * Importe une archive `.zip` téléversée puis la vérifie intégralement.
     *
     * L'archive prend un nom généré par l'application : le nom d'origine n'est jamais
     * réutilisé comme chemin. Une archive non conforme au format est supprimée aussitôt.
     *
     * @param array<string,mixed> $upload entrée de $_FILES (name, type, tmp_name, error, size)
     * @return array{name:string,path:string,bytes:int,created_at:string,tables:int,files:int}
     */
    public function import(array $upload): array
    {
        if (!class_exists(ZipArchive::class)) {
            throw new RuntimeException(__('backup.import_zip_required'));
        }
        $error = (int) ($upload['error'] ?? UPLOAD_ERR_NO_FILE);
        if ($error === UPLOAD_ERR_NO_FILE) {
            throw new RuntimeException(__('backup.import_required'));
        }
        $temporaryPath = (string) ($upload['tmp_name'] ?? '');
        $reportedSize = filter_var($upload['size'] ?? null, FILTER_VALIDATE_INT);
        if ($error !== UPLOAD_ERR_OK || $temporaryPath === '' || !is_uploaded_file($temporaryPath)
            || $reportedSize === false || $reportedSize < 1
        ) {
            throw new RuntimeException(__('backup.import_failed'));
        }
        if ($reportedSize > self::MAX_IMPORT_BYTES) {
            throw new RuntimeException(__('backup.import_too_large', [
                'size' => (string) intdiv(self::MAX_IMPORT_BYTES, 1_048_576),
            ]));
        }
        $actualSize = filesize($temporaryPath);
        if (!is_int($actualSize) || $actualSize !== $reportedSize) {
            throw new RuntimeException(__('backup.import_failed'));
        }
        $originalName = (string) ($upload['name'] ?? '');
        if (strtolower(pathinfo($originalName, PATHINFO_EXTENSION)) !== 'zip') {
            throw new RuntimeException(__('backup.import_extension'));
        }
        $signature = file_get_contents($temporaryPath, false, null, 0, 4);
        if (!is_string($signature) || !in_array($signature, ["PK\x03\x04", "PK\x05\x06"], true)) {
            throw new RuntimeException(__('backup.import_not_zip'));
        }

        $directory = self::ensurePrivateDirectory(BASE_PATH . '/storage/backups');
        $token = bin2hex(random_bytes(8));
        $filename = 'prime-arch-import-' . gmdate('Ymd-His') . '-' . $token . '.zip';
        $destination = $directory . '/' . $filename;
        if (!move_uploaded_file($temporaryPath, $destination)) {
            throw new RuntimeException(__('backup.import_failed'));
        }
        if (!chmod($destination, 0600)) {
            @unlink($destination);
            throw new RuntimeException(__('backup.import_failed'));
        }

        try {
            $verified = $this->verifyArchive($destination);
        } catch (Throwable $error) {
            @unlink($destination);
            throw new RuntimeException(__('backup.import_invalid') . ' ' . $error->getMessage(), 0, $error);
        }

        (new AuditLogger($this->connection()))->record('backup.imported', 'backup', $filename, [
            'files' => $verified['files'],
            'tables' => $verified['tables'],
        ], null);

        return [
            'name' => $filename,
            'path' => 'storage/backups/' . $filename,
            'bytes' => $reportedSize,
            'created_at' => $verified['created_at'],
            'tables' => $verified['tables'],
            'files' => $verified['files'],
        ];
    }

    /** Supprime définitivement une archive locale (jamais un fichier hors de `storage/backups`). */
    public function delete(string $candidate): string
    {
        $path = $this->resolveArchivePath($candidate);
        $name = basename($path);
        if (strtolower(pathinfo($name, PATHINFO_EXTENSION)) !== 'zip') {
            throw new RuntimeException(__('backup.archive_not_zip'));
        }
        if (!unlink($path)) {
            throw new RuntimeException(__('backup.delete_failed'));
        }
        (new AuditLogger($this->connection()))->record('backup.deleted', 'backup', $name, [], null);

        return $name;
    }

    /**
     * Réunit plusieurs archives dans une seule `.zip` de transit pour un téléchargement groupé.
     *
     * L'archive produite contient les sauvegardes telles quelles : elle n'est pas
     * elle-même restaurable, elle sert uniquement à récupérer plusieurs fichiers d'un coup.
     *
     * @param list<string> $candidates chemins relatifs sous storage/backups
     * @return array{path:string,filename:string,count:int}
     */
    public function bundle(array $candidates): array
    {
        $names = [];
        foreach ($candidates as $candidate) {
            if (!is_string($candidate) || $candidate === '') {
                continue;
            }
            $names[basename($this->resolveArchivePath($candidate))] = true;
        }
        $names = array_keys($names);
        if ($names === []) {
            throw new RuntimeException(__('backup.selection_empty'));
        }
        if (count($names) > self::MAX_BUNDLE_ARCHIVES) {
            throw new RuntimeException(__('backup.selection_too_large', [
                'count' => (string) self::MAX_BUNDLE_ARCHIVES,
            ]));
        }
        if (!class_exists(ZipArchive::class)) {
            throw new RuntimeException(__('backup.import_zip_required'));
        }

        $workRoot = self::ensurePrivateDirectory(BASE_PATH . '/storage/tmp');
        $bundlePath = $workRoot . '/sauvegardes-' . bin2hex(random_bytes(10)) . '.zip';
        $zip = new ZipArchive();
        if ($zip->open($bundlePath, ZipArchive::CREATE | ZipArchive::EXCL) !== true) {
            throw new RuntimeException(__('backup.bundle_failed'));
        }
        try {
            foreach ($names as $name) {
                if (!$zip->addFile(BASE_PATH . '/storage/backups/' . $name, $name)) {
                    throw new RuntimeException(__('backup.bundle_failed'));
                }
            }
        } finally {
            $zip->close();
        }
        if (!chmod($bundlePath, 0600)) {
            @unlink($bundlePath);
            throw new RuntimeException(__('backup.bundle_failed'));
        }

        return [
            'path' => $bundlePath,
            'filename' => 'prime-arch-sauvegardes-' . gmdate('Ymd-His') . '.zip',
            'count' => count($names),
        ];
    }

    /**
     * Restaure une archive sur l'instance en service, après sauvegarde de l'état courant.
     *
     * La base est vidée puis reconstruite à partir du schéma de l'archive, et les dossiers
     * `storage/{uploads,pdf,modules}` sont remplacés. Une archive de sécurité de l'état
     * courant est créée au préalable : elle seule permet de revenir en arrière.
     *
     * @return array{tables:int,files:int,safety:string}
     */
    public function restoreInPlace(string $candidate, bool $confirm): array
    {
        if (!$confirm) {
            throw new RuntimeException(__('backup.restore_confirm_required'));
        }
        if (!class_exists(ZipArchive::class)) {
            throw new RuntimeException(__('backup.import_zip_required'));
        }

        $archivePath = $this->resolveArchivePath($candidate);
        // Validation complète (schéma, colonnes, lignes, manifeste) avant toute destruction.
        $inspected = self::inspectArchive($archivePath);
        $orderedTables = self::orderTables($inspected['schema']['tables']);
        $safety = $this->create();

        $workRoot = self::ensurePrivateDirectory(BASE_PATH . '/storage/tmp');
        $workDirectory = $workRoot . '/restore-' . bin2hex(random_bytes(12));
        if (!mkdir($workDirectory, 0700)) {
            throw new RuntimeException(__('backup.restore_failed'));
        }

        try {
            // Les fichiers privés sont extraits avant toute modification de la base.
            self::extractStoragePayload($archivePath, $inspected['manifest'], $workDirectory);
            $pdo = $this->connection();
            $pdo->exec("SET time_zone = '+00:00'");
            $pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
            try {
                self::dropExistingTables($pdo);
                foreach ($orderedTables as $table) {
                    $pdo->exec($table['create_sql']);
                }
                $pdo->beginTransaction();
                try {
                    foreach ($orderedTables as $table) {
                        self::restoreTableData($pdo, $archivePath, $table);
                    }
                    $pdo->commit();
                } catch (Throwable $error) {
                    if ($pdo->inTransaction()) {
                        $pdo->rollBack();
                    }
                    throw $error;
                }
            } finally {
                $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
            }
            self::purgeStorageTargets();
            self::installStoragePayload($workDirectory);

            (new AuditLogger($pdo))->record('backup.restored', 'backup', basename($archivePath), [
                'files' => count($inspected['manifest']['files']),
                'tables' => count($orderedTables),
                'mode' => 'in_place',
                'safety' => basename($safety),
            ], null);

            return [
                'tables' => count($orderedTables),
                'files' => count($inspected['manifest']['files']),
                'safety' => $safety,
            ];
        } finally {
            self::removeTree($workDirectory);
        }
    }

    /** Relit uniquement le manifeste d'une archive : aucune donnée métier n'est décompressée. */
    private static function describeArchive(string $path): ?array
    {
        if (!class_exists(ZipArchive::class)) {
            return null;
        }
        $zip = new ZipArchive();
        if ($zip->open($path, ZipArchive::RDONLY) !== true) {
            return null;
        }
        try {
            $stat = $zip->statName('manifest.json');
            if (!is_array($stat) || (int) ($stat['size'] ?? 0) < 2
                || (int) ($stat['size'] ?? 0) > self::MAX_MANIFEST_BYTES
            ) {
                return null;
            }
            $contents = $zip->getFromName('manifest.json', self::MAX_MANIFEST_BYTES);
            if (!is_string($contents)) {
                return null;
            }
            $manifest = json_decode($contents, true, 64);
            if (!is_array($manifest) || (int) ($manifest['format'] ?? 0) !== self::FORMAT_VERSION
                || !is_string($manifest['created_at'] ?? null) || !is_array($manifest['files'] ?? null)
            ) {
                return null;
            }
            $logicalBytes = 0;
            foreach ($manifest['files'] as $file) {
                if (!is_array($file)) {
                    return null;
                }
                $logicalBytes += max(0, (int) ($file['size'] ?? 0));
            }

            return [
                'created_at' => (string) $manifest['created_at'],
                'tables' => (int) ($manifest['tables'] ?? 0),
                'files' => count($manifest['files']),
                'logical_bytes' => $logicalBytes,
            ];
        } catch (Throwable) {
            return null;
        } finally {
            $zip->close();
        }
    }

    /** Vide la base cible : vues puis tables, quel que soit l'ordre des dépendances. */
    private static function dropExistingTables(PDO $pdo): void
    {
        $rows = $pdo->query('SHOW FULL TABLES')->fetchAll(PDO::FETCH_NUM);
        $views = [];
        $tables = [];
        foreach ($rows as $row) {
            $name = (string) ($row[0] ?? '');
            if ($name === '') {
                continue;
            }
            if (strtoupper((string) ($row[1] ?? 'BASE TABLE')) === 'VIEW') {
                $views[] = $name;
                continue;
            }
            $tables[] = $name;
        }
        foreach ($views as $view) {
            $pdo->exec('DROP VIEW IF EXISTS ' . self::quoteIdentifier($view));
        }
        if ($tables !== []) {
            $pdo->exec('DROP TABLE IF EXISTS ' . implode(', ', array_map(
                static fn (string $table): string => self::quoteIdentifier($table),
                $tables
            )));
        }
    }

    /** Vide les dossiers de stockage privés en conservant leurs fichiers de garde. */
    private static function purgeStorageTargets(): void
    {
        foreach (self::STORAGE_ROOTS as $rootName) {
            $root = BASE_PATH . '/storage/' . $rootName;
            if (is_link($root)) {
                throw new RuntimeException(__('backup.storage_invalid'));
            }
            if (!file_exists($root)) {
                continue;
            }
            if (!is_dir($root)) {
                throw new RuntimeException(__('backup.storage_invalid'));
            }
            foreach (scandir($root) ?: [] as $entry) {
                if ($entry === '.' || $entry === '..') {
                    continue;
                }
                $path = $root . '/' . $entry;
                if (is_link($path) || !is_dir($path)) {
                    if (in_array($entry, ['.htaccess', '.gitkeep'], true) && is_file($path) && !is_link($path)) {
                        continue;
                    }
                    if (!@unlink($path) && file_exists($path)) {
                        throw new RuntimeException(__('backup.storage_invalid'));
                    }
                    continue;
                }
                self::removeTree($path);
                if (file_exists($path)) {
                    throw new RuntimeException(__('backup.storage_invalid'));
                }
            }
        }
    }

    private function connection(): PDO
    {
        $this->pdo ??= Database::connection();
        return $this->pdo;
    }

    /** @return array{tables:array<int,array<string,mixed>>} */
    private function exportDatabase(PDO $pdo, string $directory): array
    {
        $pdo->exec("SET time_zone = '+00:00'");
        $pdo->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');
        $pdo->exec('START TRANSACTION WITH CONSISTENT SNAPSHOT');
        try {
            $objects = $pdo->query(
                'SELECT TABLE_NAME, TABLE_TYPE, ENGINE
                 FROM information_schema.TABLES
                 WHERE TABLE_SCHEMA = DATABASE()
                 ORDER BY TABLE_NAME'
            )->fetchAll(PDO::FETCH_ASSOC);
            $tableNames = [];
            foreach ($objects as $object) {
                if (($object['TABLE_TYPE'] ?? '') !== 'BASE TABLE') {
                    throw new RuntimeException('La sauvegarde complète ne prend pas en charge les vues SQL.');
                }
                if (strtoupper((string) ($object['ENGINE'] ?? '')) !== 'INNODB') {
                    throw new RuntimeException('Toutes les tables doivent utiliser InnoDB pour une sauvegarde cohérente.');
                }
                $name = (string) ($object['TABLE_NAME'] ?? '');
                if (!preg_match('/^[A-Za-z_][A-Za-z0-9_]{0,63}$/', $name)) {
                    throw new RuntimeException('Nom de table SQL non pris en charge par la sauvegarde.');
                }
                $tableNames[] = $name;
            }
            if (!in_array('users', $tableNames, true)
                || !in_array('roles', $tableNames, true)
                || !in_array('migrations', $tableNames, true)
            ) {
                throw new RuntimeException('La base ne contient pas le schéma Prime Architecture initialisé.');
            }
            if (count($tableNames) > 10_000) {
                throw new RuntimeException('La base dépasse le nombre maximal de tables pris en charge.');
            }

            $triggerCount = (int) $pdo->query(
                'SELECT COUNT(*) FROM information_schema.TRIGGERS WHERE TRIGGER_SCHEMA = DATABASE()'
            )->fetchColumn();
            if ($triggerCount > 0) {
                throw new RuntimeException('Supprimez ou documentez les déclencheurs SQL avant de créer une sauvegarde complète.');
            }
            $routineCount = (int) $pdo->query(
                'SELECT COUNT(*) FROM information_schema.ROUTINES WHERE ROUTINE_SCHEMA = DATABASE()'
            )->fetchColumn();
            $eventCount = (int) $pdo->query(
                'SELECT COUNT(*) FROM information_schema.EVENTS WHERE EVENT_SCHEMA = DATABASE()'
            )->fetchColumn();
            if ($routineCount > 0 || $eventCount > 0) {
                throw new RuntimeException('Les routines et événements SQL ne sont pas pris en charge par ce format de sauvegarde.');
            }

            $tables = [];
            foreach ($tableNames as $index => $tableName) {
                $quotedTable = self::quoteIdentifier($tableName);
                $createRow = $pdo->query('SHOW CREATE TABLE ' . $quotedTable)->fetch(PDO::FETCH_NUM);
                $createSql = is_array($createRow) ? (string) ($createRow[1] ?? '') : '';
                if (!self::isSafeCreateTable($createSql, $tableName)) {
                    throw new RuntimeException('La définition SQL de la table « ' . $tableName . ' » ne peut pas être sauvegardée en sécurité.');
                }

                $columns = $pdo->query('SHOW COLUMNS FROM ' . $quotedTable)->fetchAll(PDO::FETCH_ASSOC);
                $columnDefinitions = [];
                foreach ($columns as $column) {
                    $columnName = (string) ($column['Field'] ?? '');
                    $columnType = (string) ($column['Type'] ?? '');
                    if (!preg_match('/^[A-Za-z_][A-Za-z0-9_]{0,63}$/', $columnName)) {
                        throw new RuntimeException('Nom de colonne SQL non pris en charge.');
                    }
                    if (self::isSpatialType($columnType)) {
                        throw new RuntimeException('Les colonnes spatiales ne sont pas prises en charge par ce format de sauvegarde.');
                    }
                    $columnDefinitions[] = [
                        'name' => $columnName,
                        'type' => $columnType,
                        'writable' => !str_contains(strtoupper((string) ($column['Extra'] ?? '')), 'GENERATED'),
                        'binary' => self::isBinaryType($columnType),
                    ];
                }

                $dataPath = 'database/data/table-' . str_pad((string) $index, 5, '0', STR_PAD_LEFT) . '.ndjson';
                $filePath = $directory . '/data/table-' . str_pad((string) $index, 5, '0', STR_PAD_LEFT) . '.ndjson';
                $handle = fopen($filePath, 'xb');
                if ($handle === false) {
                    throw new RuntimeException('Le fichier de données SQL ne peut pas être créé.');
                }
                $rowCount = 0;
                $rows = null;
                $bufferedQuery = $pdo->getAttribute(PDO::MYSQL_ATTR_USE_BUFFERED_QUERY);
                try {
                    if (!$pdo->setAttribute(PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, false)) {
                        throw new RuntimeException('L’export SQL en flux ne peut pas être activé.');
                    }
                    $rows = $pdo->query('SELECT * FROM ' . $quotedTable);
                    while (($row = $rows->fetch(PDO::FETCH_ASSOC)) !== false) {
                        $serialized = [];
                        foreach ($columnDefinitions as $column) {
                            if (!$column['writable']) {
                                continue;
                            }
                            $value = $row[$column['name']] ?? null;
                            if (is_resource($value)) {
                                $value = stream_get_contents($value, self::MAX_ROW_BYTES + 1);
                            }
                            if (is_string($value) && strlen($value) > self::MAX_ROW_BYTES) {
                                throw new RuntimeException('Une valeur SQL dépasse la limite du format de sauvegarde.');
                            }
                            if ($column['binary'] && $value !== null) {
                                $serialized[$column['name']] = ['__prime_arch_base64' => base64_encode((string) $value)];
                            } else {
                                $serialized[$column['name']] = $value;
                            }
                        }
                        $line = json_encode($serialized, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                        if (strlen($line) + 1 > self::MAX_ROW_BYTES) {
                            throw new RuntimeException('Une ligne de données dépasse la limite du format de sauvegarde.');
                        }
                        self::writeLine($handle, $line);
                        $rowCount++;
                    }
                } finally {
                    if ($rows instanceof \PDOStatement) {
                        $rows->closeCursor();
                    }
                    $pdo->setAttribute(PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, $bufferedQuery);
                    fclose($handle);
                }

                $tables[] = [
                    'name' => $tableName,
                    'create_sql' => $createSql,
                    'columns' => $columnDefinitions,
                    'data_path' => $dataPath,
                    'rows' => $rowCount,
                ];
            }
            $schema = ['tables' => $tables];
            $schemaContents = json_encode(
                $schema,
                JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
            );
            if (strlen($schemaContents) + 1 > self::MAX_SCHEMA_BYTES) {
                throw new RuntimeException('Le schéma SQL dépasse la taille maximale du format de sauvegarde.');
            }
            self::writeFile($directory . '/schema.json', $schemaContents);
            $pdo->commit();
            return $schema;
        } catch (Throwable $error) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $error;
        }
    }

    /** @return array<string,string> archive path vers fichier temporaire */
    private function snapshotStorage(string $payloadRoot): array
    {
        $files = [];
        foreach (self::STORAGE_ROOTS as $rootName) {
            $sourceRoot = BASE_PATH . '/storage/' . $rootName;
            if (is_link($sourceRoot)) {
                throw new RuntimeException('Un dossier de stockage est un lien symbolique.');
            }
            if (!file_exists($sourceRoot)) {
                continue;
            }
            if (!is_dir($sourceRoot)) {
                throw new RuntimeException('Un dossier de stockage est un lien symbolique ou n’est pas un dossier.');
            }
            $realRoot = realpath($sourceRoot);
            if ($realRoot === false) {
                throw new RuntimeException('Un dossier de stockage est inaccessible.');
            }
            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($sourceRoot, RecursiveDirectoryIterator::SKIP_DOTS),
                RecursiveIteratorIterator::SELF_FIRST
            );
            foreach ($iterator as $item) {
                if ($item->isLink()) {
                    throw new RuntimeException('Les liens symboliques dans le stockage ne sont pas inclus dans une sauvegarde.');
                }
                if ($item->isDir()) {
                    continue;
                }
                if (!$item->isFile()) {
                    continue;
                }
                $source = $item->getPathname();
                $relative = str_replace('\\', '/', substr($source, strlen(BASE_PATH) + 1));
                if (in_array($relative, [
                    'storage/' . $rootName . '/.htaccess',
                    'storage/' . $rootName . '/.gitkeep',
                ], true) || !$this->isSnapshotFile($item->getFilename())) {
                    continue;
                }
                $realSource = realpath($source);
                if ($realSource === false || !str_starts_with($realSource, $realRoot . DIRECTORY_SEPARATOR)) {
                    throw new RuntimeException('Un fichier de stockage sort de son dossier autorisé.');
                }
                if (!self::isAllowedStoragePath($relative)) {
                    throw new RuntimeException('Chemin de fichier de stockage non pris en charge.');
                }
                $destination = $payloadRoot . '/' . $relative;
                $destinationDirectory = dirname($destination);
                if (!is_dir($destinationDirectory) && !mkdir($destinationDirectory, 0700, true) && !is_dir($destinationDirectory)) {
                    throw new RuntimeException('Un dossier temporaire de stockage ne peut pas être créé.');
                }
                if (!copy($realSource, $destination) || !chmod($destination, 0600)) {
                    throw new RuntimeException('Un fichier privé ne peut pas être copié dans la sauvegarde temporaire avec des permissions sûres.');
                }
                $files[$relative] = $destination;
            }
        }
        return $files;
    }

    private function isSnapshotFile(string $filename): bool
    {
        return $filename !== '.env' && !str_ends_with(strtolower($filename), '.tmp');
    }

    /** @return array{manifest:array<string,mixed>,schema:array{tables:array<int,array<string,mixed>>}} */
    private static function inspectArchive(string $path): array
    {
        if (!class_exists(ZipArchive::class)) {
            throw new RuntimeException('L’extension PHP zip est nécessaire pour vérifier une sauvegarde.');
        }
        $zip = new ZipArchive();
        if ($zip->open($path, ZipArchive::RDONLY) !== true) {
            throw new RuntimeException('L’archive de sauvegarde est illisible.');
        }
        try {
            if ($zip->numFiles < 2 || $zip->numFiles > self::MAX_ARCHIVE_ENTRIES) {
                throw new RuntimeException('Le nombre de fichiers dans l’archive est invalide.');
            }
            $actualFiles = [];
            $totalBytes = 0;
            for ($index = 0; $index < $zip->numFiles; $index++) {
                $stat = $zip->statIndex($index);
                if (!is_array($stat)) {
                    throw new RuntimeException('Une entrée de l’archive est illisible.');
                }
                $name = (string) ($stat['name'] ?? '');
                if (method_exists($zip, 'getExternalAttributesIndex')
                    && $zip->getExternalAttributesIndex($index, $operatingSystem, $attributes)
                ) {
                    $mode = ((int) $attributes >> 16) & 0xFFFF;
                    if (($mode & 0170000) === 0120000) {
                        throw new RuntimeException('Les liens symboliques ne sont pas admis dans une sauvegarde.');
                    }
                }
                if ($name === 'manifest.json') {
                    $manifestSize = (int) ($stat['size'] ?? -1);
                    if (isset($actualFiles[$name]) || $manifestSize < 0 || $manifestSize > self::MAX_MANIFEST_BYTES) {
                        throw new RuntimeException('Le manifeste de sauvegarde est absent, dupliqué ou trop volumineux.');
                    }
                    $actualFiles[$name] = $manifestSize;
                    continue;
                }
                if (!self::isAllowedArchivePath($name) || isset($actualFiles[$name])) {
                    throw new RuntimeException('L’archive contient un chemin interdit ou dupliqué.');
                }
                $size = (int) ($stat['size'] ?? -1);
                if ($size < 0 || $size > self::MAX_ARCHIVE_BYTES) {
                    throw new RuntimeException('La taille d’un fichier de sauvegarde est invalide.');
                }
                if ($size > self::MAX_ARCHIVE_BYTES - $totalBytes) {
                    throw new RuntimeException('La taille décompressée de l’archive dépasse la limite autorisée.');
                }
                $totalBytes += $size;
                $actualFiles[$name] = $size;
            }

            if (!isset($actualFiles['manifest.json']) || $actualFiles['manifest.json'] > self::MAX_MANIFEST_BYTES) {
                throw new RuntimeException('Le manifeste de sauvegarde est absent ou trop volumineux.');
            }
            if ($actualFiles['manifest.json'] > self::MAX_ARCHIVE_BYTES - $totalBytes) {
                throw new RuntimeException('La taille décompressée de l’archive dépasse la limite autorisée.');
            }
            $manifestContents = self::readArchiveEntry($zip, 'manifest.json', self::MAX_MANIFEST_BYTES);
            if (strlen($manifestContents) !== $actualFiles['manifest.json']) {
                throw new RuntimeException('La taille réelle du manifeste ne correspond pas à l’archive.');
            }
            $manifest = json_decode($manifestContents, true, 512, JSON_THROW_ON_ERROR);
            if (!is_array($manifest) || ($manifest['format'] ?? null) !== self::FORMAT_VERSION
                || !is_array($manifest['files'] ?? null) || !is_string($manifest['created_at'] ?? null)
            ) {
                throw new RuntimeException('Le format du manifeste de sauvegarde n’est pas pris en charge.');
            }

            $expectedFiles = [];
            foreach ($manifest['files'] as $entry) {
                if (!is_array($entry)) {
                    throw new RuntimeException('Une entrée du manifeste est invalide.');
                }
                $filePath = (string) ($entry['path'] ?? '');
                $size = filter_var($entry['size'] ?? null, FILTER_VALIDATE_INT);
                $hash = (string) ($entry['sha256'] ?? '');
                if (!self::isAllowedArchivePath($filePath) || $size === false || $size < 0
                    || !preg_match('/^[a-f0-9]{64}$/', $hash) || isset($expectedFiles[$filePath])
                ) {
                    throw new RuntimeException('Une empreinte du manifeste est invalide.');
                }
                $expectedFiles[$filePath] = ['size' => (int) $size, 'sha256' => $hash];
            }
            $actualPayload = $actualFiles;
            unset($actualPayload['manifest.json']);
            if (array_keys($expectedFiles) !== array_keys($actualPayload)) {
                $expectedKeys = array_keys($expectedFiles);
                $actualKeys = array_keys($actualPayload);
                sort($expectedKeys, SORT_STRING);
                sort($actualKeys, SORT_STRING);
                if ($expectedKeys !== $actualKeys) {
                    throw new RuntimeException('Le manifeste ne correspond pas au contenu de l’archive.');
                }
            }

            foreach ($expectedFiles as $filePath => $expected) {
                if (($actualPayload[$filePath] ?? null) !== $expected['size']) {
                    throw new RuntimeException('La taille d’un fichier ne correspond pas au manifeste.');
                }
                $stream = $zip->getStream($filePath);
                if (!is_resource($stream)) {
                    throw new RuntimeException('Un fichier de l’archive ne peut pas être lu.');
                }
                $context = hash_init('sha256');
                $readBytes = 0;
                try {
                    while (!feof($stream)) {
                        $chunk = fread($stream, 1_048_576);
                        if ($chunk === false) {
                            throw new RuntimeException('La lecture d’un fichier de l’archive a échoué.');
                        }
                        if ($chunk === '') {
                            continue;
                        }
                        $readBytes += strlen($chunk);
                        if ($readBytes > $expected['size'] || $readBytes > self::MAX_ARCHIVE_BYTES) {
                            throw new RuntimeException('La taille réelle d’un fichier dépasse les limites de l’archive.');
                        }
                        hash_update($context, $chunk);
                    }
                } finally {
                    fclose($stream);
                }
                if ($readBytes !== $expected['size'] || !hash_equals($expected['sha256'], hash_final($context))) {
                    throw new RuntimeException('L’empreinte d’un fichier de l’archive ne correspond pas.');
                }
            }

            if (!isset($expectedFiles['database/schema.json'])
                || $expectedFiles['database/schema.json']['size'] > self::MAX_SCHEMA_BYTES
            ) {
                throw new RuntimeException('Le schéma SQL de sauvegarde est absent ou trop volumineux.');
            }
            $schemaSize = $expectedFiles['database/schema.json']['size'];
            $schemaContents = self::readArchiveEntry($zip, 'database/schema.json', self::MAX_SCHEMA_BYTES);
            if (strlen($schemaContents) !== $schemaSize) {
                throw new RuntimeException('La taille réelle du schéma ne correspond pas à l’archive.');
            }
            $schema = json_decode($schemaContents, true, 512, JSON_THROW_ON_ERROR);
            self::validateSchema($schema, array_keys($expectedFiles));
            if (!is_int($manifest['tables'] ?? null) || $manifest['tables'] !== count($schema['tables'])) {
                throw new RuntimeException('Le nombre de tables ne correspond pas au manifeste.');
            }
            self::orderTables($schema['tables']);
            self::validateArchiveRows($zip, $schema['tables']);

            return ['manifest' => $manifest, 'schema' => $schema];
        } finally {
            $zip->close();
        }
    }

    private static function readArchiveEntry(ZipArchive $zip, string $path, int $maxBytes): string
    {
        $stream = $zip->getStream($path);
        if (!is_resource($stream)) {
            throw new RuntimeException('Une entrée de l’archive ne peut pas être lue.');
        }
        try {
            $contents = stream_get_contents($stream, $maxBytes + 1);
        } finally {
            fclose($stream);
        }
        if (!is_string($contents) || strlen($contents) > $maxBytes) {
            throw new RuntimeException('Une entrée JSON de l’archive dépasse la taille maximale autorisée.');
        }
        return $contents;
    }

    /** @param mixed $schema @param array<int,string> $archivePaths */
    private static function validateSchema(mixed $schema, array $archivePaths): void
    {
        if (!is_array($schema) || !is_array($schema['tables'] ?? null) || count($schema['tables']) > 10_000) {
            throw new RuntimeException('Le schéma SQL de sauvegarde est invalide.');
        }
        $paths = array_fill_keys($archivePaths, true);
        $tableNames = [];
        $dataPaths = [];
        foreach ($schema['tables'] as $table) {
            if (!is_array($table)) {
                throw new RuntimeException('Une table de sauvegarde est invalide.');
            }
            $name = (string) ($table['name'] ?? '');
            $createSql = (string) ($table['create_sql'] ?? '');
            $dataPath = (string) ($table['data_path'] ?? '');
            if (!preg_match('/^[A-Za-z_][A-Za-z0-9_]{0,63}$/', $name)
                || isset($tableNames[$name])
                || !self::isAllowedDataPath($dataPath)
                || !isset($paths[$dataPath])
                || isset($dataPaths[$dataPath])
                || !self::isSafeCreateTable($createSql, $name)
                || !is_array($table['columns'] ?? null)
                || !is_int($table['rows'] ?? null)
                || $table['rows'] < 0
            ) {
                throw new RuntimeException('La définition d’une table de sauvegarde est invalide.');
            }
            if ($table['columns'] === []) {
                throw new RuntimeException('Une table sans colonne n’est pas prise en charge.');
            }
            $seenColumns = [];
            foreach ($table['columns'] as $column) {
                if (!is_array($column)) {
                    throw new RuntimeException('Une définition de colonne est invalide.');
                }
                $columnName = (string) ($column['name'] ?? '');
                if (!preg_match('/^[A-Za-z_][A-Za-z0-9_]{0,63}$/', $columnName)
                    || isset($seenColumns[$columnName])
                    || !is_string($column['type'] ?? null)
                    || self::isSpatialType((string) ($column['type'] ?? ''))
                    || !is_bool($column['writable'] ?? null)
                    || !is_bool($column['binary'] ?? null)
                ) {
                    throw new RuntimeException('Une définition de colonne est invalide.');
                }
                $seenColumns[$columnName] = true;
            }
            $tableNames[$name] = true;
            $dataPaths[$dataPath] = true;
        }
        if (!isset($tableNames['users'], $tableNames['roles'], $tableNames['migrations'])) {
            throw new RuntimeException('Le schéma de sauvegarde ne contient pas les tables principales de Prime Architecture.');
        }
        foreach ($archivePaths as $archivePath) {
            if (str_starts_with($archivePath, 'database/data/') && !isset($dataPaths[$archivePath])) {
                throw new RuntimeException('L’archive contient un fichier de données SQL orphelin.');
            }
        }
    }

    /** @param array<int,array<string,mixed>> $tables */
    private static function validateArchiveRows(ZipArchive $zip, array $tables): void
    {
        foreach ($tables as $table) {
            $stream = $zip->getStream((string) $table['data_path']);
            if (!is_resource($stream)) {
                throw new RuntimeException('Un fichier de lignes SQL est absent de l’archive.');
            }
            $writableColumns = array_values(array_filter(
                $table['columns'],
                static fn (array $column): bool => $column['writable']
            ));
            $rowCount = 0;
            try {
                while (($line = fgets($stream, self::MAX_ROW_BYTES + 2)) !== false) {
                    if (strlen($line) > self::MAX_ROW_BYTES || !str_ends_with($line, "\n")) {
                        throw new RuntimeException('Une ligne de données dépasse la limite de vérification.');
                    }
                    $row = json_decode($line, true, 512, JSON_THROW_ON_ERROR);
                    if (!is_array($row) || ($row !== [] && array_is_list($row))
                        || count($row) !== count($writableColumns)
                    ) {
                        throw new RuntimeException('Une ligne de données SQL est invalide.');
                    }
                    foreach ($writableColumns as $column) {
                        $columnName = (string) $column['name'];
                        if (!array_key_exists($columnName, $row)) {
                            throw new RuntimeException('Une colonne manque dans une ligne de données.');
                        }
                        $value = $row[$columnName];
                        if ($column['binary'] && $value !== null) {
                            if (!is_array($value) || count($value) !== 1
                                || !is_string($value['__prime_arch_base64'] ?? null)
                                || base64_decode($value['__prime_arch_base64'], true) === false
                            ) {
                                throw new RuntimeException('Une valeur binaire de sauvegarde est invalide.');
                            }
                        } elseif (is_array($value) || (!is_scalar($value) && $value !== null)) {
                            throw new RuntimeException('Une valeur de sauvegarde SQL est invalide.');
                        }
                    }
                    $rowCount++;
                }
            } finally {
                fclose($stream);
            }
            if ($rowCount !== (int) $table['rows']) {
                throw new RuntimeException('Le nombre de lignes ne correspond pas au schéma de sauvegarde.');
            }
        }
    }

    private static function isSafeCreateTable(string $sql, string $expectedName): bool
    {
        if (!preg_match('/^\s*CREATE\s+TABLE\s+(`(?:``|[^`])+`)\s*\(/i', $sql, $matches)
            || !preg_match('/\bENGINE\s*=\s*INNODB\b/i', $sql)
        ) {
            return false;
        }
        $actualName = str_replace('``', '`', substr($matches[1], 1, -1));
        if ($actualName !== $expectedName) {
            return false;
        }

        $quote = '';
        $depth = 0;
        $closedDefinition = false;
        $definitionEnd = null;
        $length = strlen($sql);
        for ($index = 0; $index < $length; $index++) {
            $character = $sql[$index];
            if ($quote !== '') {
                if ($character === '\\' && $quote !== '`') {
                    $index++;
                    continue;
                }
                if ($character === $quote) {
                    if ($quote === '`' && ($sql[$index + 1] ?? '') === '`') {
                        $index++;
                        continue;
                    }
                    $quote = '';
                }
                continue;
            }
            if ($character === "'" || $character === '"' || $character === '`') {
                $quote = $character;
            } elseif ($character === ';') {
                return false;
            } elseif ($character === '(') {
                $depth++;
            } elseif ($character === ')') {
                $depth--;
                if ($depth < 0) {
                    return false;
                }
                if ($depth === 0) {
                    $closedDefinition = true;
                    $definitionEnd = $index;
                }
            }
        }
        if ($quote !== '' || $depth !== 0 || !$closedDefinition || $definitionEnd === null) {
            return false;
        }
        $tableOptions = substr($sql, $definitionEnd + 1);
        return preg_match('/^\s+ENGINE\s*=\s*INNODB\b/i', $tableOptions) === 1;
    }

    /** @param array<int,array<string,mixed>> $tables
     *  @return array<int,array<string,mixed>>
     */
    private static function orderTables(array $tables): array
    {
        $byName = [];
        foreach ($tables as $table) {
            $byName[(string) $table['name']] = $table;
        }
        $dependencies = [];
        foreach ($byName as $name => $table) {
            preg_match_all('/\bREFERENCES\s+`((?:``|[^`])+)`/i', (string) $table['create_sql'], $matches);
            $references = [];
            foreach ($matches[1] ?? [] as $reference) {
                $reference = str_replace('``', '`', $reference);
                if ($reference !== $name) {
                    if (!isset($byName[$reference])) {
                        throw new RuntimeException('Une clé étrangère référence une table absente de la sauvegarde.');
                    }
                    $references[$reference] = true;
                }
            }
            $dependencies[$name] = array_keys($references);
        }

        $ordered = [];
        $done = [];
        while (count($ordered) < count($byName)) {
            $advanced = false;
            foreach ($byName as $name => $table) {
                if (isset($done[$name])) {
                    continue;
                }
                if (array_diff($dependencies[$name], array_keys($done)) !== []) {
                    continue;
                }
                $ordered[] = $table;
                $done[$name] = true;
                $advanced = true;
            }
            if (!$advanced) {
                throw new RuntimeException('Les dépendances cycliques des tables empêchent une restauration sûre.');
            }
        }
        return $ordered;
    }

    /** @param array<string,mixed> $table */
    private static function restoreTableData(PDO $pdo, string $archivePath, array $table): void
    {
        $writableColumns = array_values(array_filter(
            $table['columns'],
            static fn (array $column): bool => $column['writable']
        ));
        $quotedTable = self::quoteIdentifier((string) $table['name']);
        if ($writableColumns === []) {
            $insertSql = 'INSERT INTO ' . $quotedTable . ' () VALUES ()';
        } else {
            $names = array_map(static fn (array $column): string => self::quoteIdentifier((string) $column['name']), $writableColumns);
            $placeholders = implode(', ', array_fill(0, count($writableColumns), '?'));
            $insertSql = 'INSERT INTO ' . $quotedTable . ' (' . implode(', ', $names) . ') VALUES (' . $placeholders . ')';
        }
        $insert = $pdo->prepare($insertSql);
        $zip = new ZipArchive();
        if ($zip->open($archivePath, ZipArchive::RDONLY) !== true) {
            throw new RuntimeException('L’archive ne peut pas être rouverte pendant la restauration.');
        }
        $stream = $zip->getStream((string) $table['data_path']);
        if (!is_resource($stream)) {
            $zip->close();
            throw new RuntimeException('Un fichier de lignes SQL est absent de l’archive.');
        }
        $rowCount = 0;
        try {
            while (($line = fgets($stream, self::MAX_ROW_BYTES + 2)) !== false) {
                if (strlen($line) > self::MAX_ROW_BYTES || !str_ends_with($line, "\n")) {
                    throw new RuntimeException('Une ligne de données dépasse la limite de restauration.');
                }
                $row = json_decode($line, true, 512, JSON_THROW_ON_ERROR);
                if (!is_array($row) || ($row !== [] && array_is_list($row))) {
                    throw new RuntimeException('Une ligne de données SQL est invalide.');
                }
                $values = [];
                foreach ($writableColumns as $column) {
                    $columnName = (string) $column['name'];
                    if (!array_key_exists($columnName, $row)) {
                        throw new RuntimeException('Une colonne manque dans une ligne de données.');
                    }
                    $value = $row[$columnName];
                    if ($column['binary'] && $value !== null) {
                        if (!is_array($value) || !is_string($value['__prime_arch_base64'] ?? null)) {
                            throw new RuntimeException('Une valeur binaire de sauvegarde est invalide.');
                        }
                        $value = base64_decode($value['__prime_arch_base64'], true);
                        if (!is_string($value)) {
                            throw new RuntimeException('Une valeur binaire de sauvegarde est invalide.');
                        }
                    } elseif (is_array($value)) {
                        throw new RuntimeException('Une valeur de sauvegarde contient une structure inattendue.');
                    }
                    if (!is_scalar($value) && $value !== null) {
                        throw new RuntimeException('Une valeur de sauvegarde SQL est invalide.');
                    }
                    $values[] = $value;
                }
                if (count($row) !== count($writableColumns)) {
                    throw new RuntimeException('Une ligne de données contient des colonnes inattendues.');
                }
                $insert->execute($values);
                $rowCount++;
            }
        } finally {
            fclose($stream);
            $zip->close();
        }
        if ($rowCount !== (int) $table['rows']) {
            throw new RuntimeException('Le nombre de lignes restaurées ne correspond pas au schéma de sauvegarde.');
        }
    }

    /** @param array<string,mixed> $manifest */
    private static function extractStoragePayload(string $archivePath, array $manifest, string $workDirectory): void
    {
        $zip = new ZipArchive();
        if ($zip->open($archivePath, ZipArchive::RDONLY) !== true) {
            throw new RuntimeException('L’archive ne peut pas être ouverte pour extraire les fichiers privés.');
        }
        try {
            foreach ($manifest['files'] as $entry) {
                $archivePathName = (string) $entry['path'];
                if (!str_starts_with($archivePathName, 'storage/')) {
                    continue;
                }
                $destination = $workDirectory . '/' . $archivePathName;
                $directory = dirname($destination);
                if (!is_dir($directory) && !mkdir($directory, 0700, true) && !is_dir($directory)) {
                    throw new RuntimeException('Un dossier temporaire de restauration ne peut pas être créé.');
                }
                $input = $zip->getStream($archivePathName);
                $output = fopen($destination, 'xb');
                if (!is_resource($input) || $output === false) {
                    if (is_resource($input)) {
                        fclose($input);
                    }
                    throw new RuntimeException('Un fichier privé de sauvegarde ne peut pas être extrait.');
                }
                try {
                    $expectedSize = (int) $entry['size'];
                    $copied = stream_copy_to_stream($input, $output, $expectedSize + 1);
                    if ($copied !== $expectedSize) {
                        throw new RuntimeException('La taille réelle d’un fichier privé ne correspond pas au manifeste.');
                    }
                } finally {
                    fclose($input);
                    fclose($output);
                }
                if (!chmod($destination, 0640)) {
                    throw new RuntimeException('Les permissions d’un fichier extrait ne peuvent pas être sécurisées.');
                }
            }
        } finally {
            $zip->close();
        }
    }

    private static function assertStorageTargetsEmpty(): void
    {
        foreach (self::STORAGE_ROOTS as $rootName) {
            $root = BASE_PATH . '/storage/' . $rootName;
            if (is_link($root)) {
                throw new RuntimeException('Un dossier cible de stockage est invalide.');
            }
            if (!file_exists($root)) {
                continue;
            }
            if (!is_dir($root)) {
                throw new RuntimeException('Un dossier cible de stockage est invalide.');
            }
            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($root, RecursiveDirectoryIterator::SKIP_DOTS),
                RecursiveIteratorIterator::SELF_FIRST
            );
            foreach ($iterator as $item) {
                if ($item->isLink()) {
                    throw new RuntimeException('Un lien symbolique est présent dans un dossier cible de restauration.');
                }
                if ($item->isDir()) {
                    continue;
                }
                $relative = str_replace('\\', '/', substr(
                    $item->getPathname(),
                    strlen($root) + 1
                ));
                if (!$item->isFile() || !in_array($relative, ['.htaccess', '.gitkeep'], true)) {
                    throw new RuntimeException('Les dossiers de stockage cible ne sont pas vides.');
                }
            }
        }
    }

    private static function installStoragePayload(string $workDirectory): void
    {
        foreach (self::STORAGE_ROOTS as $rootName) {
            $stagedRoot = $workDirectory . '/storage/' . $rootName;
            if (!is_dir($stagedRoot)) {
                continue;
            }
            $destinationRoot = BASE_PATH . '/storage/' . $rootName;
            if (!is_dir($destinationRoot) && !mkdir($destinationRoot, 0750, true) && !is_dir($destinationRoot)) {
                throw new RuntimeException('Un dossier de stockage restauré ne peut pas être créé.');
            }
            if (!chmod($destinationRoot, 0750)) {
                throw new RuntimeException('Les permissions d’un dossier de stockage restauré ne peuvent pas être sécurisées.');
            }
            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($stagedRoot, RecursiveDirectoryIterator::SKIP_DOTS),
                RecursiveIteratorIterator::SELF_FIRST
            );
            foreach ($iterator as $item) {
                if ($item->isLink()) {
                    throw new RuntimeException('Un lien symbolique inattendu a été extrait.');
                }
                $destination = BASE_PATH . '/storage/' . $rootName . '/' . substr(
                    str_replace('\\', '/', $item->getPathname()),
                    strlen(str_replace('\\', '/', $stagedRoot)) + 1
                );
                if ($item->isDir()) {
                    if (!is_dir($destination) && !mkdir($destination, 0750, true) && !is_dir($destination)) {
                        throw new RuntimeException('Un dossier de stockage restauré ne peut pas être créé.');
                    }
                    if (!chmod($destination, 0750)) {
                        throw new RuntimeException('Les permissions d’un dossier de stockage restauré ne peuvent pas être sécurisées.');
                    }
                    continue;
                }
                if (!$item->isFile() || !self::isAllowedStoragePath(str_replace('\\', '/', substr($destination, strlen(BASE_PATH) + 1)))) {
                    throw new RuntimeException('Un chemin de fichier restauré est interdit.');
                }
                $parent = dirname($destination);
                if (!is_dir($parent) && !mkdir($parent, 0750, true) && !is_dir($parent)) {
                    throw new RuntimeException('Un dossier de stockage restauré ne peut pas être créé.');
                }
                if (!rename($item->getPathname(), $destination)) {
                    throw new RuntimeException('Un fichier de stockage restauré ne peut pas être publié.');
                }
                if (!chmod($destination, 0640)) {
                    throw new RuntimeException('Les permissions d’un fichier restauré ne peuvent pas être sécurisées.');
                }
            }
        }
    }

    private static function isAllowedArchivePath(string $path): bool
    {
        if ($path === '' || strlen($path) > 512 || str_contains($path, "\0") || str_contains($path, '\\')
            || str_starts_with($path, '/') || preg_match('/^[A-Za-z]:/', $path)
        ) {
            return false;
        }
        foreach (explode('/', $path) as $part) {
            if ($part === '' || $part === '.' || $part === '..' || !preg_match('/^[A-Za-z0-9._-]{1,120}$/', $part)) {
                return false;
            }
        }
        return $path === 'database/schema.json'
            || self::isAllowedDataPath($path)
            || self::isAllowedStoragePath($path);
    }

    public static function isAllowedStoragePath(string $path): bool
    {
        foreach (self::STORAGE_ROOTS as $rootName) {
            if (str_starts_with($path, 'storage/' . $rootName . '/')) {
                return self::isAllowedRelativePath(substr($path, strlen('storage/' . $rootName . '/')));
            }
        }
        return false;
    }

    private static function isAllowedDataPath(string $path): bool
    {
        return preg_match('/^database\/data\/table-[0-9]{5}\.ndjson$/', $path) === 1;
    }

    private static function isAllowedRelativePath(string $path): bool
    {
        if ($path === '' || strlen($path) > 512) {
            return false;
        }
        foreach (explode('/', $path) as $part) {
            if ($part === '' || $part === '.' || $part === '..' || !preg_match('/^[A-Za-z0-9._-]{1,120}$/', $part)) {
                return false;
            }
        }
        return true;
    }

    private static function isSpatialType(string $type): bool
    {
        return preg_match('/^(?:geometry|point|linestring|polygon|multipoint|multilinestring|multipolygon|geometrycollection)\b/i', $type) === 1;
    }

    private static function isBinaryType(string $type): bool
    {
        return preg_match('/^(?:bit|tinyblob|blob|mediumblob|longblob|binary|varbinary|geometry|point|linestring|polygon|multipoint|multilinestring|multipolygon|geometrycollection)\b/i', $type) === 1;
    }

    private static function quoteIdentifier(string $identifier): string
    {
        return '`' . str_replace('`', '``', $identifier) . '`';
    }

    private static function ensurePrivateDirectory(string $directory): string
    {
        if (is_link($directory)) {
            throw new RuntimeException('Un dossier privé de sauvegarde ne peut pas être un lien symbolique.');
        }
        if (!is_dir($directory) && !mkdir($directory, 0700, true) && !is_dir($directory)) {
            throw new RuntimeException('Un dossier privé de sauvegarde ne peut pas être créé.');
        }
        if (!chmod($directory, 0700)) {
            throw new RuntimeException('Les permissions d’un dossier privé ne peuvent pas être sécurisées.');
        }
        if (!is_writable($directory)) {
            throw new RuntimeException('Un dossier privé de sauvegarde n’est pas accessible en écriture.');
        }
        return $directory;
    }

    private static function writeFile(string $path, string $contents): void
    {
        $handle = fopen($path, 'xb');
        if ($handle === false) {
            throw new RuntimeException('Un fichier temporaire de sauvegarde ne peut pas être créé.');
        }
        try {
            self::writeLine($handle, $contents);
        } finally {
            fclose($handle);
        }
        if (!chmod($path, 0600)) {
            throw new RuntimeException('Les permissions d’un fichier temporaire ne peuvent pas être sécurisées.');
        }
    }

    /** @param resource $handle */
    private static function writeLine($handle, string $contents): void
    {
        $written = 0;
        $length = strlen($contents);
        while ($written < $length) {
            $count = fwrite($handle, substr($contents, $written));
            if ($count === false || $count === 0) {
                throw new RuntimeException('L’écriture d’un fichier de sauvegarde a échoué.');
            }
            $written += $count;
        }
        if ($length === 0 || !str_ends_with($contents, "\n")) {
            if (fwrite($handle, "\n") !== 1) {
                throw new RuntimeException('La fin d’une ligne de sauvegarde ne peut pas être écrite.');
            }
        }
    }

    private static function removeTree(string $directory): void
    {
        if ($directory === '' || !is_dir($directory) || is_link($directory)) {
            return;
        }
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($directory, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($iterator as $item) {
            if ($item->isLink() || $item->isFile()) {
                @unlink($item->getPathname());
            } elseif ($item->isDir()) {
                @rmdir($item->getPathname());
            }
        }
        @rmdir($directory);
    }
}
