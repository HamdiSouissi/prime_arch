<?php
declare(strict_types=1);

namespace App\Services;

use finfo;
use InvalidArgumentException;
use RuntimeException;

/**
 * Stockage privé des logos du cabinet ; seules les images raster validées sont acceptées.
 */
final class CabinetLogoStorage
{
    private const MAX_BYTES = 2_097_152;
    private const MAX_DIMENSION = 4096;

    /** @param array<string,mixed> $upload */
    public function store(array $upload): string
    {
        $error = (int) ($upload['error'] ?? UPLOAD_ERR_NO_FILE);
        if ($error !== UPLOAD_ERR_OK) {
            throw new InvalidArgumentException($error === UPLOAD_ERR_NO_FILE
                ? 'Sélectionnez un fichier image.'
                : 'Le téléversement du logo a échoué.');
        }

        $temporaryPath = (string) ($upload['tmp_name'] ?? '');
        $fileSize = (int) ($upload['size'] ?? 0);
        if ($temporaryPath === '' || !is_uploaded_file($temporaryPath) || $fileSize < 1 || $fileSize > self::MAX_BYTES) {
            throw new InvalidArgumentException('Le logo doit être une image téléversée de 2 Mo maximum.');
        }

        $fileInfo = new finfo(FILEINFO_MIME_TYPE);
        $mime = $fileInfo->file($temporaryPath);
        $extensions = [
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
        ];
        if (!is_string($mime) || !isset($extensions[$mime])) {
            throw new InvalidArgumentException('Formats autorisés : JPEG, PNG ou WebP.');
        }

        $image = getimagesize($temporaryPath);
        if (!is_array($image)
            || (int) ($image[0] ?? 0) < 1
            || (int) ($image[1] ?? 0) < 1
            || (int) $image[0] > self::MAX_DIMENSION
            || (int) $image[1] > self::MAX_DIMENSION
            || (string) ($image['mime'] ?? '') !== $mime
        ) {
            throw new InvalidArgumentException('Le fichier image est invalide ou ses dimensions sont trop grandes.');
        }

        $directory = BASE_PATH . '/storage/uploads/cabinet';
        if (!is_dir($directory) && !mkdir($directory, 0750, true) && !is_dir($directory)) {
            throw new RuntimeException('Le dossier de stockage du logo ne peut pas être créé.');
        }

        $filename = 'logo-' . bin2hex(random_bytes(16)) . '.' . $extensions[$mime];
        $destination = $directory . '/' . $filename;
        if (!move_uploaded_file($temporaryPath, $destination)) {
            throw new RuntimeException('Le logo ne peut pas être déplacé vers son dossier sécurisé.');
        }
        chmod($destination, 0640);
        return $filename;
    }

    public function path(string $filename): ?string
    {
        if (!preg_match('/^logo-[a-f0-9]{32}\.(?:jpg|png|webp)$/', $filename)) {
            return null;
        }
        $path = BASE_PATH . '/storage/uploads/cabinet/' . $filename;
        return is_file($path) ? $path : null;
    }

    public function remove(string $filename): void
    {
        $path = $this->path($filename);
        if ($path !== null) {
            unlink($path);
        }
    }
}
