<?php
declare(strict_types=1);

namespace Modules\GedVersions\Services;

use finfo;
use InvalidArgumentException;
use RuntimeException;

/**
 * Stockage privé des fichiers du dépôt documentaire.
 *
 * Les fichiers sont écrits sous `storage/modules/ged-versions/files/document-<id>/`, jamais dans
 * `public/`. Le nom sur le disque est opaque (`ged-file-<32 hex>.<ext>`), l’extension est déduite
 * du type MIME réel, et le nom d’origine n’est conservé qu’en base pour l’affichage.
 */
final class GedStorage
{
    /** Racine privée du module. */
    public static function root(): string
    {
        return BASE_PATH . '/storage/modules/ged-versions/files';
    }

    /**
     * Enregistre un téléversement après contrôle strict.
     *
     * @param array<string,mixed> $upload entrée de `$_FILES`
     * @return array{original_name:string,stored_name:string,mime_type:string,extension:string,file_size:int,checksum:string}
     */
    public function store(int $documentId, array $upload): array
    {
        if ($documentId < 1) {
            throw new InvalidArgumentException(__('ged.error.invalid_file'));
        }
        $error = (int) ($upload['error'] ?? UPLOAD_ERR_NO_FILE);
        if ($error !== UPLOAD_ERR_OK) {
            throw new InvalidArgumentException($error === UPLOAD_ERR_NO_FILE
                ? __('ged.error.file_required')
                : __('ged.error.upload_failed'));
        }

        $temporaryPath = (string) ($upload['tmp_name'] ?? '');
        $reportedSize = filter_var($upload['size'] ?? null, FILTER_VALIDATE_INT);
        if ($temporaryPath === '' || !is_uploaded_file($temporaryPath)
            || $reportedSize === false || $reportedSize < 1 || $reportedSize > GedService::MAX_BYTES
        ) {
            throw new InvalidArgumentException(__('ged.error.invalid_file'));
        }
        $actualSize = filesize($temporaryPath);
        if (!is_int($actualSize) || $actualSize !== $reportedSize || $actualSize > GedService::MAX_BYTES) {
            throw new InvalidArgumentException(__('ged.error.invalid_file'));
        }

        $mime = (new finfo(FILEINFO_MIME_TYPE))->file($temporaryPath);
        $extension = is_string($mime) ? GedService::extensionForMime($mime) : null;
        if ($extension === null) {
            throw new InvalidArgumentException(__('ged.error.type_refused'));
        }
        $checksum = GedService::checksum($temporaryPath);
        if ($checksum === null) {
            throw new InvalidArgumentException(__('ged.error.invalid_file'));
        }

        $directory = $this->documentDirectory($documentId);
        $storedName = GedService::storedName($extension);
        $destination = $directory . '/' . $storedName;
        if (!move_uploaded_file($temporaryPath, $destination)) {
            throw new RuntimeException(__('ged.error.storage_failed'));
        }
        if (!chmod($destination, 0640)) {
            @unlink($destination);
            throw new RuntimeException(__('ged.error.storage_failed'));
        }

        return [
            'original_name' => GedService::safeOriginalName((string) ($upload['name'] ?? ''), $extension),
            'stored_name' => $storedName,
            'mime_type' => (string) $mime,
            'extension' => $extension,
            'file_size' => $actualSize,
            'checksum' => $checksum,
        ];
    }

    /**
     * Lit un fichier stocké, après vérification du chemin réel et du type.
     *
     * @return array{contents:string,mime_type:string,checksum:string}|null
     */
    public function read(int $documentId, string $storedName): ?array
    {
        if ($documentId < 1 || !GedService::isStoredName($storedName)) {
            return null;
        }
        $root = self::root();
        $directory = $root . '/document-' . $documentId;
        $path = $directory . '/' . $storedName;
        if (is_link($root) || is_link($directory) || is_link($path) || !is_file($path)) {
            return null;
        }
        $realDirectory = realpath($directory);
        $realPath = realpath($path);
        if ($realDirectory === false || $realPath === false
            || !str_starts_with($realPath, $realDirectory . DIRECTORY_SEPARATOR)
        ) {
            return null;
        }
        $size = filesize($realPath);
        if (!is_int($size) || $size < 1 || $size > GedService::MAX_BYTES) {
            return null;
        }
        $mime = (new finfo(FILEINFO_MIME_TYPE))->file($realPath);
        $extension = is_string($mime) ? GedService::extensionForMime($mime) : null;
        if ($extension === null
            || pathinfo($storedName, PATHINFO_EXTENSION) !== $extension
            || GedService::extensionOf($storedName) !== $extension
        ) {
            return null;
        }
        $contents = file_get_contents($realPath);
        $checksum = GedService::checksum($realPath);
        if (!is_string($contents) || $checksum === null) {
            return null;
        }

        return ['contents' => $contents, 'mime_type' => (string) $mime, 'checksum' => $checksum];
    }

    /** Supprime un fichier du disque, sans jamais sortir du répertoire du document. */
    public function remove(int $documentId, string $storedName): void
    {
        if ($documentId < 1 || !GedService::isStoredName($storedName)) {
            return;
        }
        $root = self::root();
        $directory = $root . '/document-' . $documentId;
        $path = $directory . '/' . $storedName;
        $realDirectory = realpath($directory);
        $realPath = realpath($path);
        if (!is_link($root) && !is_link($directory) && !is_link($path)
            && $realDirectory !== false && $realPath !== false
            && str_starts_with($realPath, $realDirectory . DIRECTORY_SEPARATOR) && is_file($realPath)
        ) {
            @unlink($realPath);
        }
    }

    /** Supprime tous les fichiers d’un document, puis le répertoire s’il est vide. */
    public function removeAll(int $documentId): void
    {
        if ($documentId < 1) {
            return;
        }
        $root = self::root();
        if (is_link($root)) {
            return;
        }
        $directory = $root . '/document-' . $documentId;
        if (is_link($directory) || !is_dir($directory)) {
            return;
        }
        $realDirectory = realpath($directory);
        if ($realDirectory === false) {
            return;
        }
        foreach (scandir($realDirectory) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..' || !GedService::isStoredName($entry)) {
                continue;
            }
            $path = $realDirectory . DIRECTORY_SEPARATOR . $entry;
            if (!is_link($path) && is_file($path)) {
                @unlink($path);
            }
        }
        @rmdir($realDirectory);
    }

    /** Crée (ou retrouve) le répertoire privé d’un document. */
    private function documentDirectory(int $documentId): string
    {
        $root = self::root();
        if (is_link($root)) {
            throw new RuntimeException(__('ged.error.storage_failed'));
        }
        if (!is_dir($root) && !mkdir($root, 0750, true) && !is_dir($root)) {
            throw new RuntimeException(__('ged.error.storage_failed'));
        }
        $directory = $root . '/document-' . $documentId;
        if (is_link($directory)) {
            throw new RuntimeException(__('ged.error.storage_failed'));
        }
        if (!is_dir($directory) && !mkdir($directory, 0750) && !is_dir($directory)) {
            throw new RuntimeException(__('ged.error.storage_failed'));
        }
        if (!chmod($directory, 0750) || !is_writable($directory)) {
            throw new RuntimeException(__('ged.error.storage_failed'));
        }

        return $directory;
    }
}
