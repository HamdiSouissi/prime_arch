<?php
declare(strict_types=1);

namespace App\Services;

use RuntimeException;

/** Stockage privé des PDF émis et des logos conservés avec leur instantané. */
final class DocumentStorage
{
    public function __construct(private ?CabinetLogoStorage $cabinetLogos = null)
    {
        $this->cabinetLogos ??= new CabinetLogoStorage();
    }

    public function issuedPdfReference(int $documentId): string
    {
        return 'document-' . $documentId . '.pdf';
    }

    public function storeIssuedPdf(int $documentId, string $contents): string
    {
        if ($documentId < 1 || $contents === '' || !str_starts_with($contents, '%PDF-')) {
            throw new RuntimeException('Le fichier PDF généré est invalide.');
        }
        $directory = $this->directory();
        $filename = $this->issuedPdfReference($documentId);
        $temporary = $directory . '/.document-' . $documentId . '-' . bin2hex(random_bytes(12)) . '.tmp';
        $written = file_put_contents($temporary, $contents, LOCK_EX);
        if ($written === false || $written !== strlen($contents)) {
            @unlink($temporary);
            throw new RuntimeException('Le fichier PDF n’a pas pu être enregistré.');
        }
        chmod($temporary, 0640);
        if (!rename($temporary, $directory . '/' . $filename)) {
            @unlink($temporary);
            throw new RuntimeException('Le fichier PDF n’a pas pu être finalisé.');
        }
        return $filename;
    }

    public function readIssuedPdf(int $documentId): ?string
    {
        $path = $this->pathForPdfReference($this->issuedPdfReference($documentId));
        if ($path === null || !is_file($path)) {
            return null;
        }
        $contents = file_get_contents($path);
        return is_string($contents) && str_starts_with($contents, '%PDF-') ? $contents : null;
    }

    public function copyCabinetLogo(int $documentId, string $cabinetFilename): ?string
    {
        $source = $this->cabinetLogos->path($cabinetFilename);
        if ($source === null) {
            return null;
        }
        $extension = pathinfo($source, PATHINFO_EXTENSION);
        if (!in_array($extension, ['jpg', 'png', 'webp'], true)) {
            return null;
        }
        $name = 'document-' . $documentId . '-logo-' . bin2hex(random_bytes(12)) . '.' . $extension;
        $directory = $this->directory();
        if (!copy($source, $directory . '/' . $name)) {
            throw new RuntimeException('Le logo du cabinet n’a pas pu être conservé avec le document.');
        }
        chmod($directory . '/' . $name, 0640);
        return 'issued/' . $name;
    }

    public function resolveLogo(string $reference): ?string
    {
        if (preg_match('/^cabinet\/(logo-[a-f0-9]{32}\.(?:jpg|png|webp))$/', $reference, $matches)) {
            return $this->cabinetLogos->path($matches[1]);
        }
        if (preg_match('/^issued\/(document-[1-9][0-9]*-logo-[a-f0-9]{24}\.(?:jpg|png|webp))$/', $reference, $matches)) {
            $path = $this->directory() . '/' . $matches[1];
            return is_file($path) ? $path : null;
        }
        return null;
    }

    public function removeIssuedPdf(int $documentId): void
    {
        $path = $this->pathForPdfReference($this->issuedPdfReference($documentId));
        if ($path !== null && is_file($path)) {
            @unlink($path);
        }
    }

    public function removeLogoReference(?string $reference): void
    {
        if (!is_string($reference) || !preg_match('/^issued\/(document-[1-9][0-9]*-logo-[a-f0-9]{24}\.(?:jpg|png|webp))$/', $reference, $matches)) {
            return;
        }
        $path = $this->directory() . '/' . $matches[1];
        if (is_file($path)) {
            @unlink($path);
        }
    }

    private function pathForPdfReference(string $reference): ?string
    {
        if (!preg_match('/^document-[1-9][0-9]*\.pdf$/', $reference)) {
            return null;
        }
        return $this->directory() . '/' . $reference;
    }

    private function directory(): string
    {
        $directory = BASE_PATH . '/storage/pdf';
        if (!is_dir($directory) && !mkdir($directory, 0750, true) && !is_dir($directory)) {
            throw new RuntimeException('Le dossier privé de PDF ne peut pas être créé.');
        }
        if (!is_writable($directory)) {
            throw new RuntimeException('Le dossier privé de PDF n’est pas accessible en écriture.');
        }
        return $directory;
    }
}
