<?php
declare(strict_types=1);

namespace App\Services;

use ZipArchive;

/** Extraction manuelle et bornée d'archives de modules vers le stockage privé. */
final class ModuleArchiveInstaller
{
    private const MAX_ARCHIVE_BYTES = 25_000_000;
    private const MAX_UNCOMPRESSED_BYTES = 50_000_000;
    private const MAX_FILE_BYTES = 20_000_000;
    private const MAX_ENTRIES = 500;
    private const MAX_COMPRESSION_RATIO = 200;

    public function __construct(private ?ModuleManifestValidator $validator = null)
    {
        $this->validator ??= new ModuleManifestValidator();
    }

    /** @return array{manifest:array<string,mixed>,path:string,sha256:string} */
    public function installUpload(array $upload): array
    {
        if (!class_exists(ZipArchive::class)) {
            throw new ModuleException('L’extension PHP zip est requise pour installer un module.');
        }
        if ((int) ($upload['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            throw new ModuleException(self::uploadError((int) ($upload['error'] ?? UPLOAD_ERR_NO_FILE)));
        }
        $name = basename(str_replace('\\', '/', (string) ($upload['name'] ?? '')));
        $temporaryPath = $upload['tmp_name'] ?? null;
        if (!is_string($temporaryPath) || $temporaryPath === '' || !is_uploaded_file($temporaryPath)) {
            throw new ModuleException('Le téléversement du ZIP n’a pas pu être vérifié.');
        }
        if (strtolower(pathinfo($name, PATHINFO_EXTENSION)) !== 'zip') {
            throw new ModuleException('Sélectionnez une archive portant l’extension .zip.');
        }

        return $this->installArchive($temporaryPath);
    }

    /**
     * Installe une archive locale déjà approuvée par l'appelant (utile aux outils et tests).
     * Une requête HTTP doit impérativement passer par installUpload().
     *
     * @return array{manifest:array<string,mixed>,path:string,sha256:string}
     */
    public function installArchive(string $archivePath): array
    {
        if (!class_exists(ZipArchive::class)) {
            throw new ModuleException('L’extension PHP zip est requise pour installer un module.');
        }
        if (!is_file($archivePath) || is_link($archivePath) || !is_readable($archivePath)) {
            throw new ModuleException('L’archive ZIP ne peut pas être lue.');
        }
        $archiveSize = filesize($archivePath);
        if ($archiveSize === false || $archiveSize < 22 || $archiveSize > self::MAX_ARCHIVE_BYTES) {
            throw new ModuleException('La taille de l’archive doit être comprise entre 22 octets et 25 Mo.');
        }
        $archiveHash = hash_file('sha256', $archivePath);
        if (!is_string($archiveHash)) {
            throw new ModuleException('Impossible de vérifier l’empreinte de l’archive.');
        }

        $zip = new ZipArchive();
        if ($zip->open($archivePath, ZipArchive::RDONLY) !== true) {
            throw new ModuleException('Le fichier n’est pas une archive ZIP valide ou lisible.');
        }

        $stagePath = null;
        $zipIsOpen = true;
        try {
            if ($zip->numFiles < 1 || $zip->numFiles > self::MAX_ENTRIES) {
                throw new ModuleException('L’archive doit contenir au maximum 500 fichiers et dossiers.');
            }

            [$rootSlug, $entries, $totalBytes] = $this->inspectEntries($zip);
            $manifestEntry = $entries['module.json'] ?? null;
            if (!is_array($manifestEntry) || $manifestEntry['is_dir'] || $manifestEntry['size'] > 65536) {
                throw new ModuleException('L’archive doit contenir un module.json valide de 64 Ko au maximum.');
            }
            $manifestJson = $this->readEntry($zip, $manifestEntry['zip_name'], 65536);
            $manifest = $this->validator->decode($manifestJson, $rootSlug);

            $entry = $entries[$manifest['entry']] ?? null;
            if (!is_array($entry) || $entry['is_dir'] || !str_ends_with(strtolower($entry['relative_path']), '.php')) {
                throw new ModuleException('Le fichier PHP déclaré comme point d’entrée est absent de l’archive.');
            }
            if ($totalBytes > self::MAX_UNCOMPRESSED_BYTES) {
                throw new ModuleException('Le contenu décompressé dépasse la limite de 50 Mo.');
            }

            $moduleRoot = $this->prepareModuleRoot();
            $targetPath = $moduleRoot . DIRECTORY_SEPARATOR . $manifest['slug'];
            if (file_exists($targetPath) || is_link($targetPath)) {
                throw new ModuleException('Un dossier existe déjà pour ce slug. Aucun fichier n’a été remplacé.');
            }
            $stagePath = $moduleRoot . DIRECTORY_SEPARATOR . '.staging-' . bin2hex(random_bytes(16));
            if (!mkdir($stagePath, 0700)) {
                throw new ModuleException('Impossible de préparer le dossier temporaire privé du module.');
            }
            chmod($stagePath, 0700);

            foreach ($entries as $relativePath => $item) {
                if ($relativePath === '') {
                    continue;
                }
                $destination = $stagePath . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativePath);
                if ($item['is_dir']) {
                    $this->createDirectories($stagePath, $destination);
                    continue;
                }
                $parent = dirname($destination);
                $this->createDirectories($stagePath, $parent);
                $this->extractFile($zip, $item, $destination);
            }

            $extractedManifest = $this->validator->decode(
                (string) file_get_contents($stagePath . DIRECTORY_SEPARATOR . 'module.json'),
                $manifest['slug']
            );
            if ($extractedManifest !== $manifest || !is_file($stagePath . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $manifest['entry']))) {
                throw new ModuleException('Le contenu extrait ne correspond pas au manifeste validé.');
            }

            if (!$zip->close()) {
                throw new ModuleException('La lecture de l’archive s’est terminée avec une erreur.');
            }
            $zipIsOpen = false;

            if (!rename($stagePath, $targetPath)) {
                throw new ModuleException('Impossible de finaliser l’installation du module dans le stockage privé.');
            }
            $stagePath = null;
            chmod($targetPath, 0700);

            return ['manifest' => $manifest, 'path' => $targetPath, 'sha256' => $archiveHash];
        } catch (ModuleException $error) {
            throw $error;
        } catch (\Throwable $error) {
            error_log((string) $error);
            throw new ModuleException('L’archive a été refusée ou n’a pas pu être extraite en sécurité.');
        } finally {
            if ($zipIsOpen) {
                $zip->close();
            }
            if (is_string($stagePath) && is_dir($stagePath)) {
                $this->removeTree($stagePath);
            }
        }
    }

    /** Supprime uniquement le dossier privé d'un slug syntaxiquement valide. */
    public function removeModuleDirectory(string $slug): void
    {
        if (!preg_match('/^[a-z0-9](?:[a-z0-9-]{0,98}[a-z0-9])?$/D', $slug)) {
            return;
        }
        $root = $this->moduleRootIfPresent();
        if ($root === null) {
            return;
        }
        $path = $root . DIRECTORY_SEPARATOR . $slug;
        if (!file_exists($path) && !is_link($path)) {
            return;
        }
        if (is_link($path)) {
            unlink($path);
            return;
        }
        $realPath = realpath($path);
        if ($realPath !== false && str_starts_with($realPath, $root . DIRECTORY_SEPARATOR)) {
            $this->removeTree($realPath);
        }
    }

    /** @return array{0:string,1:array<string,array<string,mixed>>,2:int} */
    private function inspectEntries(ZipArchive $zip): array
    {
        $rootSlug = null;
        $entries = [];
        $pathTypes = [];
        $totalBytes = 0;
        $fileCount = 0;

        for ($index = 0; $index < $zip->numFiles; $index++) {
            $stat = $zip->statIndex($index);
            if (!is_array($stat) || !is_string($stat['name'] ?? null)) {
                throw new ModuleException('L’archive contient une entrée ZIP illisible.');
            }
            $zipName = $stat['name'];
            $isDir = str_ends_with($zipName, '/');
            $normalized = $isDir ? substr($zipName, 0, -1) : $zipName;
            if (!ModuleManifestValidator::isSafeRelativePath($normalized)) {
                throw new ModuleException('L’archive contient un chemin absolu, traversant ou non autorisé.');
            }

            $segments = explode('/', $normalized);
            $entryRoot = array_shift($segments);
            if ($entryRoot === '' || !preg_match('/^[a-z0-9](?:[a-z0-9-]{0,98}[a-z0-9])?$/D', $entryRoot)) {
                throw new ModuleException('L’archive doit contenir un unique dossier racine au nom de slug valide.');
            }
            if ($rootSlug === null) {
                $rootSlug = $entryRoot;
            } elseif ($rootSlug !== $entryRoot) {
                throw new ModuleException('Toutes les entrées du ZIP doivent rester dans un seul dossier racine.');
            }
            $relativePath = implode('/', $segments);
            if ($relativePath === '' && !$isDir) {
                throw new ModuleException('Le dossier racine du ZIP ne peut pas être un fichier.');
            }

            $size = (int) ($stat['size'] ?? -1);
            $compressedSize = (int) ($stat['comp_size'] ?? -1);
            if ($size < 0 || $compressedSize < 0 || ($isDir && $size !== 0) || $size > self::MAX_FILE_BYTES) {
                throw new ModuleException('L’archive contient un fichier de taille invalide ou supérieur à 20 Mo.');
            }
            if (!$isDir) {
                $fileCount++;
                $totalBytes += $size;
                if ($size > 1_000_000 && ($compressedSize === 0 || $size / $compressedSize > self::MAX_COMPRESSION_RATIO)) {
                    throw new ModuleException('Le taux de compression d’un fichier dépasse la limite autorisée.');
                }
                if (($stat['encryption_method'] ?? 0) !== 0) {
                    throw new ModuleException('Les entrées ZIP chiffrées ne sont pas acceptées.');
                }
            }

            $opsys = 0;
            $attributes = 0;
            if ($zip->getExternalAttributesIndex($index, $opsys, $attributes, 0) && $opsys === ZipArchive::OPSYS_UNIX) {
                $type = ($attributes >> 16) & 0170000;
                if ($type !== 0 && !(($isDir && $type === 0040000) || (!$isDir && $type === 0100000))) {
                    throw new ModuleException('Les liens symboliques et fichiers spéciaux sont interdits dans un module ZIP.');
                }
            }

            $key = strtolower($relativePath);
            if (array_key_exists($key, $pathTypes)) {
                throw new ModuleException('L’archive contient des chemins en double ou ambigus.');
            }
            $pathTypes[$key] = $isDir;
            $entries[$relativePath] = [
                'zip_name' => $zipName,
                'relative_path' => $relativePath,
                'is_dir' => $isDir,
                'size' => $size,
            ];
        }

        if ($rootSlug === null) {
            throw new ModuleException('L’archive ZIP est vide.');
        }
        if ($fileCount < 1 || $fileCount > self::MAX_ENTRIES) {
            throw new ModuleException('Le nombre de fichiers du module est invalide.');
        }

        foreach ($entries as $path => $item) {
            if ($path === '') {
                continue;
            }
            $parts = explode('/', $path);
            array_pop($parts);
            $ancestor = '';
            foreach ($parts as $part) {
                $ancestor = $ancestor === '' ? $part : $ancestor . '/' . $part;
                if (array_key_exists(strtolower($ancestor), $pathTypes) && !$pathTypes[strtolower($ancestor)]) {
                    throw new ModuleException('Un fichier de l’archive est utilisé comme dossier parent.');
                }
            }
            if (!$item['is_dir']) {
                $prefix = strtolower($path) . '/';
                foreach (array_keys($pathTypes) as $otherPath) {
                    if (str_starts_with($otherPath, $prefix)) {
                        throw new ModuleException('Un fichier de l’archive ne peut pas contenir d’autres entrées.');
                    }
                }
            }
        }

        return [$rootSlug, $entries, $totalBytes];
    }

    private function readEntry(ZipArchive $zip, string $name, int $maxBytes): string
    {
        $stream = $zip->getStream($name);
        if (!is_resource($stream)) {
            throw new ModuleException('Impossible de lire module.json depuis l’archive.');
        }
        try {
            $contents = stream_get_contents($stream, $maxBytes + 1);
        } finally {
            fclose($stream);
        }
        if (!is_string($contents) || strlen($contents) > $maxBytes) {
            throw new ModuleException('Le fichier module.json est trop volumineux.');
        }
        return $contents;
    }

    private function extractFile(ZipArchive $zip, array $entry, string $destination): void
    {
        if (file_exists($destination) || is_link($destination)) {
            throw new ModuleException('L’archive tente d’écrire deux fois au même emplacement.');
        }
        $input = $zip->getStream($entry['zip_name']);
        if (!is_resource($input)) {
            throw new ModuleException('Un fichier de l’archive ne peut pas être décompressé.');
        }
        $output = @fopen($destination, 'xb');
        if (!is_resource($output)) {
            fclose($input);
            throw new ModuleException('Impossible d’écrire un fichier dans le dossier privé temporaire.');
        }

        $written = 0;
        try {
            while (!feof($input)) {
                $chunk = fread($input, 8192);
                if ($chunk === false) {
                    throw new ModuleException('La décompression d’un fichier a échoué.');
                }
                if ($chunk === '') {
                    if (feof($input)) {
                        break;
                    }
                    throw new ModuleException('La décompression a renvoyé une lecture vide inattendue.');
                }
                $length = strlen($chunk);
                $written += $length;
                if ($written > $entry['size'] || $written > self::MAX_FILE_BYTES) {
                    throw new ModuleException('Un fichier décompressé dépasse la taille déclarée ou la limite autorisée.');
                }
                $offset = 0;
                while ($offset < $length) {
                    $count = fwrite($output, substr($chunk, $offset));
                    if (!is_int($count) || $count < 1) {
                        throw new ModuleException('L’écriture d’un fichier extrait a échoué.');
                    }
                    $offset += $count;
                }
            }
            if ($written !== $entry['size']) {
                throw new ModuleException('La taille réelle d’un fichier ne correspond pas à l’archive.');
            }
        } finally {
            fclose($input);
            fclose($output);
        }
        chmod($destination, 0600);
    }

    private function createDirectories(string $stageRoot, string $directory): void
    {
        $stageReal = realpath($stageRoot);
        if ($stageReal === false) {
            throw new ModuleException('Le dossier temporaire du module est introuvable.');
        }
        $relative = ltrim(substr($directory, strlen($stageRoot)), DIRECTORY_SEPARATOR);
        $current = $stageReal;
        if ($relative !== '') {
            foreach (explode(DIRECTORY_SEPARATOR, $relative) as $segment) {
                $current .= DIRECTORY_SEPARATOR . $segment;
                if (is_link($current)) {
                    throw new ModuleException('Un lien symbolique a été détecté pendant l’extraction.');
                }
                if (!is_dir($current) && !mkdir($current, 0700)) {
                    throw new ModuleException('Impossible de créer un dossier extrait.');
                }
            }
        }
        $realDirectory = realpath($current);
        if ($realDirectory === false || !str_starts_with($realDirectory, $stageReal . DIRECTORY_SEPARATOR)
            && $realDirectory !== $stageReal
        ) {
            throw new ModuleException('Un chemin extrait sort du dossier temporaire du module.');
        }
    }

    private function prepareModuleRoot(): string
    {
        $storage = BASE_PATH . '/storage';
        if (is_link($storage) || !is_dir($storage) || realpath($storage) === false) {
            throw new ModuleException('Le stockage privé de l’application n’est pas disponible.');
        }
        $storageReal = realpath($storage);
        $root = $storageReal . DIRECTORY_SEPARATOR . 'modules';
        if (is_link($root)) {
            throw new ModuleException('Le dossier privé des modules ne peut pas être un lien symbolique.');
        }
        if (!is_dir($root) && !mkdir($root, 0700)) {
            throw new ModuleException('Impossible de créer le dossier privé des modules.');
        }
        $rootReal = realpath($root);
        if ($rootReal === false || !str_starts_with($rootReal, $storageReal . DIRECTORY_SEPARATOR)) {
            throw new ModuleException('Le dossier des modules doit rester à l’intérieur de storage/.');
        }
        chmod($rootReal, 0700);

        $denyFile = $rootReal . DIRECTORY_SEPARATOR . '.htaccess';
        if (is_link($denyFile)) {
            throw new ModuleException('Le fichier de protection HTTP du stockage est invalide.');
        }
        if (!is_file($denyFile) || !str_contains((string) file_get_contents($denyFile), 'Require all denied')) {
            if (file_put_contents($denyFile, "Require all denied\n", LOCK_EX) === false) {
                throw new ModuleException('Impossible de protéger le dossier de modules contre un accès HTTP direct.');
            }
            chmod($denyFile, 0600);
        }
        return $rootReal;
    }

    private function moduleRootIfPresent(): ?string
    {
        $storage = BASE_PATH . '/storage';
        if (is_link($storage) || !is_dir($storage)) {
            return null;
        }
        $root = $storage . DIRECTORY_SEPARATOR . 'modules';
        if (is_link($root) || !is_dir($root)) {
            return null;
        }
        $storageReal = realpath($storage);
        $rootReal = realpath($root);
        return $storageReal !== false && $rootReal !== false
            && str_starts_with($rootReal, $storageReal . DIRECTORY_SEPARATOR)
            ? $rootReal
            : null;
    }

    private function removeTree(string $directory): void
    {
        if (is_link($directory) || !is_dir($directory)) {
            if (file_exists($directory) || is_link($directory)) {
                unlink($directory);
            }
            return;
        }
        foreach (scandir($directory) ?: [] as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $path = $directory . DIRECTORY_SEPARATOR . $item;
            if (is_link($path) || !is_dir($path)) {
                unlink($path);
            } else {
                $this->removeTree($path);
            }
        }
        rmdir($directory);
    }

    private static function uploadError(int $code): string
    {
        return match ($code) {
            UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'Le ZIP dépasse la taille autorisée par le serveur.',
            UPLOAD_ERR_PARTIAL => 'Le téléversement du ZIP est incomplet.',
            UPLOAD_ERR_NO_FILE => 'Choisissez une archive ZIP à installer.',
            UPLOAD_ERR_NO_TMP_DIR, UPLOAD_ERR_CANT_WRITE, UPLOAD_ERR_EXTENSION => 'Le serveur n’a pas pu recevoir le ZIP.',
            default => 'Le téléversement du ZIP a échoué.',
        };
    }
}
