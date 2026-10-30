<?php
declare(strict_types=1);

namespace App\Services;

use finfo;
use InvalidArgumentException;
use RuntimeException;

/** Stockage privé et validation des images de projet. */
final class ProjectImageStorage
{
    private const MAX_BYTES = 20_971_520;
    private const MAX_DIMENSION = 10_000;
    private const MAX_PIXELS = 40_000_000;
    private const EXTENSIONS = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
    ];

    /** @param array<string,mixed> $upload
     *  @return array{original_name:string,stored_name:string,mime_type:string,file_size:int,width:int,height:int}
     */
    public function store(int $projectId, array $upload): array
    {
        if ($projectId < 1) {
            throw new InvalidArgumentException(__('projects.image_invalid'));
        }
        $error = (int) ($upload['error'] ?? UPLOAD_ERR_NO_FILE);
        if ($error !== UPLOAD_ERR_OK) {
            throw new InvalidArgumentException($error === UPLOAD_ERR_NO_FILE
                ? __('projects.image_required')
                : __('projects.image_upload_failed'));
        }

        $temporaryPath = (string) ($upload['tmp_name'] ?? '');
        $reportedSize = filter_var($upload['size'] ?? null, FILTER_VALIDATE_INT);
        if ($temporaryPath === '' || !is_uploaded_file($temporaryPath)
            || $reportedSize === false || $reportedSize < 1 || $reportedSize > self::MAX_BYTES
        ) {
            throw new InvalidArgumentException(__('projects.image_invalid'));
        }
        $actualSize = filesize($temporaryPath);
        if (!is_int($actualSize) || $actualSize !== $reportedSize || $actualSize > self::MAX_BYTES) {
            throw new InvalidArgumentException(__('projects.image_invalid'));
        }

        $mime = (new finfo(FILEINFO_MIME_TYPE))->file($temporaryPath);
        if (!is_string($mime) || !isset(self::EXTENSIONS[$mime])) {
            throw new InvalidArgumentException(__('projects.image_format_invalid'));
        }
        $image = @getimagesize($temporaryPath);
        $width = is_array($image) ? (int) ($image[0] ?? 0) : 0;
        $height = is_array($image) ? (int) ($image[1] ?? 0) : 0;
        if ($width < 1 || $height < 1 || $width > self::MAX_DIMENSION || $height > self::MAX_DIMENSION
            || $width * $height > self::MAX_PIXELS || (string) ($image['mime'] ?? '') !== $mime
        ) {
            throw new InvalidArgumentException(__('projects.image_dimensions_invalid'));
        }

        $directory = $this->projectDirectory($projectId);
        $token = bin2hex(random_bytes(16));
        $storedName = 'project-image-' . $token . '.' . self::EXTENSIONS[$mime];
        $destination = $directory . '/' . $storedName;
        if (!move_uploaded_file($temporaryPath, $destination)) {
            throw new RuntimeException(__('projects.image_storage_failed'));
        }
        if (!chmod($destination, 0640)) {
            @unlink($destination);
            throw new RuntimeException(__('projects.image_storage_failed'));
        }

        return [
            'original_name' => self::safeOriginalName((string) ($upload['name'] ?? ''), self::EXTENSIONS[$mime]),
            'stored_name' => $storedName,
            'mime_type' => $mime,
            'file_size' => $actualSize,
            'width' => $width,
            'height' => $height,
        ];
    }

    /** @return array{contents:string,mime_type:string}|null */
    public function read(int $projectId, string $storedName): ?array
    {
        if ($projectId < 1 || !self::isStoredName($storedName)) {
            return null;
        }
        $projectsRoot = BASE_PATH . '/storage/uploads/projects';
        $directory = $this->projectDirectoryPath($projectId);
        $path = $directory . '/' . $storedName;
        if (is_link($projectsRoot) || is_link($directory) || is_link($path) || !is_file($path)) {
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
        if (!is_int($size) || $size < 1 || $size > self::MAX_BYTES) {
            return null;
        }
        $mime = (new finfo(FILEINFO_MIME_TYPE))->file($realPath);
        if (!is_string($mime) || !isset(self::EXTENSIONS[$mime])
            || pathinfo($storedName, PATHINFO_EXTENSION) !== self::EXTENSIONS[$mime]
        ) {
            return null;
        }
        $image = @getimagesize($realPath);
        $width = is_array($image) ? (int) ($image[0] ?? 0) : 0;
        $height = is_array($image) ? (int) ($image[1] ?? 0) : 0;
        if ($width < 1 || $height < 1 || $width > self::MAX_DIMENSION || $height > self::MAX_DIMENSION
            || $width * $height > self::MAX_PIXELS || (string) ($image['mime'] ?? '') !== $mime
        ) {
            return null;
        }
        $contents = file_get_contents($realPath);
        return is_string($contents) ? ['contents' => $contents, 'mime_type' => $mime] : null;
    }

    public function remove(int $projectId, string $storedName): void
    {
        if ($projectId < 1 || !self::isStoredName($storedName)) {
            return;
        }
        $projectsRoot = BASE_PATH . '/storage/uploads/projects';
        $directory = $this->projectDirectoryPath($projectId);
        $path = $directory . '/' . $storedName;
        $realDirectory = realpath($directory);
        $realPath = realpath($path);
        if (!is_link($projectsRoot) && !is_link($directory) && !is_link($path)
            && $realDirectory !== false && $realPath !== false
            && str_starts_with($realPath, $realDirectory . DIRECTORY_SEPARATOR) && is_file($realPath)
        ) {
            @unlink($realPath);
        }
    }

    private function projectDirectory(int $projectId): string
    {
        if ($projectId < 1) {
            throw new InvalidArgumentException(__('projects.image_invalid'));
        }
        $root = BASE_PATH . '/storage/uploads/projects';
        if (is_link($root)) {
            throw new RuntimeException(__('projects.image_storage_failed'));
        }
        if (!is_dir($root) && !mkdir($root, 0750, true) && !is_dir($root)) {
            throw new RuntimeException(__('projects.image_storage_failed'));
        }
        $directory = $root . '/project-' . $projectId;
        if (is_link($directory)) {
            throw new RuntimeException(__('projects.image_storage_failed'));
        }
        if (!is_dir($directory) && !mkdir($directory, 0750) && !is_dir($directory)) {
            throw new RuntimeException(__('projects.image_storage_failed'));
        }
        if (!chmod($directory, 0750) || !is_writable($directory)) {
            throw new RuntimeException(__('projects.image_storage_failed'));
        }
        return $directory;
    }

    private function projectDirectoryPath(int $projectId): string
    {
        return BASE_PATH . '/storage/uploads/projects/project-' . $projectId;
    }

    private static function isStoredName(string $filename): bool
    {
        return preg_match('/^project-image-[a-f0-9]{32}\.(?:jpg|png|webp)$/', $filename) === 1;
    }

    private static function safeOriginalName(string $name, string $extension): string
    {
        $name = basename(str_replace('\\', '/', $name));
        $name = preg_replace('/[\x00-\x1F\x7F]/u', '', $name) ?? '';
        $name = trim(mb_substr($name, 0, 180, 'UTF-8'));
        return $name !== '' ? $name : 'plan.' . $extension;
    }
}
