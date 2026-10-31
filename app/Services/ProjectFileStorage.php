<?php
declare(strict_types=1);

namespace App\Services;

use InvalidArgumentException;
use RuntimeException;

/**
 * Stockage privé des fichiers techniques rattachés aux champs personnalisés des projets.
 *
 * Un plan AutoCAD ou un PDF n'est pas une image : ni `getimagesize()` ni le type MIME déclaré
 * par le navigateur ne permettent de le valider. Le contenu est donc reconnu à partir de sa
 * signature, puis confronté à l'extension réellement reçue. Une même famille couvre plusieurs
 * extensions :
 *
 * - `acad` : DWG, DWT (gabarit), DWS (normes), BAK (sauvegarde) et SV$ (enregistrement
 *   automatique d'AutoCAD) sont tous des fichiers au format DWG — en-tête binaire « AC10xx » ;
 * - `dxf`  : échange texte ou binaire (« 0 / SECTION » ou « AutoCAD Binary DXF ») ;
 * - `dwf`  : DWF et DWFX — conteneur ZIP depuis DWF 6 (et DWFX), binaire « (DWF V… » avant ;
 * - `pdf`  : en-tête « %PDF- ».
 *
 * Le contenu reste hors de la racine publique : la seule voie d'accès est la route authentifiée
 * de téléchargement, qui sert toujours le fichier en pièce jointe.
 */
final class ProjectFileStorage
{
    /** 32 Mio : un plan DWG ou un PDF de plans scannés reste un livrable de cabinet. */
    public const MAX_BYTES = 33_554_432;
    /** Extensions acceptées, dans l'ordre d'affichage du formulaire. */
    public const EXTENSIONS = ['dwg', 'dxf', 'dwt', 'dws', 'bak', 'dwf', 'dwfx', 'pdf', 'sv$'];
    /** Type MIME servi au téléchargement : jamais interprété par le navigateur. */
    public const DOWNLOAD_MIME = 'application/octet-stream';

    /**
     * Famille de contenu attendue pour chaque extension.
     *
     * @var array<string,string>
     */
    private const FAMILIES = [
        'dwg' => 'acad',
        'dwt' => 'acad',
        'dws' => 'acad',
        'bak' => 'acad',
        'sv$' => 'acad',
        'dxf' => 'dxf',
        'dwf' => 'dwf',
        'dwfx' => 'dwf',
        'pdf' => 'pdf',
    ];

    /** En-têtes DWG connus (AC1009 à AC1032) : AutoCAD, BricsCAD et compatibles. */
    private const DWG_VERSION = '/^AC10(?:09|12|14|15|18|21|24|27|32)/';
    /** En-tête d'un DXF binaire (« AutoCAD Binary DXF » suivi de \r\n\x1a\0). */
    private const DXF_BINARY = "AutoCAD Binary DXF\r\n\x1a";
    /** En-tête des DWF binaires antérieurs à DWF 6 : « (DWF V06.00)P... ». */
    private const DWF_BINARY_HEADER = '/^\(DWF V\d{2}\.\d{2}\)/';

    /**
     * Enregistre un fichier téléversé dans l'espace privé du projet.
     *
     * @param array<string,mixed> $upload entrée de $_FILES (clés name, type, tmp_name, error, size)
     * @return array{original_name:string,stored_name:string,extension:string,mime_type:string,file_size:int,checksum:string}
     */
    public function store(int $projectId, array $upload): array
    {
        if ($projectId < 1) {
            throw new InvalidArgumentException(__('projects.file_invalid'));
        }
        $error = (int) ($upload['error'] ?? UPLOAD_ERR_NO_FILE);
        if ($error !== UPLOAD_ERR_OK) {
            throw new InvalidArgumentException($error === UPLOAD_ERR_NO_FILE
                ? __('projects.file_required')
                : __('projects.file_upload_failed'));
        }

        $temporaryPath = (string) ($upload['tmp_name'] ?? '');
        $reportedSize = filter_var($upload['size'] ?? null, FILTER_VALIDATE_INT);
        if ($temporaryPath === '' || !is_uploaded_file($temporaryPath)
            || $reportedSize === false || $reportedSize < 1 || $reportedSize > self::MAX_BYTES
        ) {
            throw new InvalidArgumentException(__('projects.file_invalid'));
        }
        $actualSize = filesize($temporaryPath);
        if (!is_int($actualSize) || $actualSize !== $reportedSize || $actualSize > self::MAX_BYTES) {
            throw new InvalidArgumentException(__('projects.file_invalid'));
        }

        $originalName = (string) ($upload['name'] ?? '');
        $extension = self::extensionOf($originalName);
        if ($extension === '' || !in_array($extension, self::EXTENSIONS, true)) {
            throw new InvalidArgumentException(__('projects.file_format_invalid'));
        }
        // L'extension annoncée doit correspondre au contenu réellement reçu : un exécutable,
        // une archive quelconque ou un PDF renommé en .dwg sont refusés avant toute écriture.
        $detected = self::detectFormat($temporaryPath);
        if ($detected === null) {
            throw new InvalidArgumentException(__('projects.file_format_invalid'));
        }
        if ($detected !== self::familyOf($extension)) {
            throw new InvalidArgumentException(__('projects.file_extension_mismatch'));
        }

        $directory = $this->projectDirectory($projectId);
        $storedName = 'project-file-' . bin2hex(random_bytes(16)) . '.' . $extension;
        $destination = $directory . '/' . $storedName;
        if (!move_uploaded_file($temporaryPath, $destination)) {
            throw new RuntimeException(__('projects.file_storage_failed'));
        }
        if (!chmod($destination, 0640)) {
            @unlink($destination);
            throw new RuntimeException(__('projects.file_storage_failed'));
        }
        $checksum = hash_file('sha256', $destination);
        if (!is_string($checksum)) {
            @unlink($destination);
            throw new RuntimeException(__('projects.file_storage_failed'));
        }

        return [
            'original_name' => self::safeOriginalName($originalName, $extension),
            'stored_name' => $storedName,
            'extension' => $extension,
            'mime_type' => self::DOWNLOAD_MIME,
            'file_size' => $actualSize,
            'checksum' => $checksum,
        ];
    }

    /**
     * Relit un fichier stocké, après avoir vérifié son nom, sa taille, sa signature et son
     * empreinte : un fichier altéré sur le disque n'est jamais servi.
     *
     * @return array{contents:string,mime_type:string,extension:string}|null
     */
    public function read(int $projectId, string $storedName, string $expectedChecksum = ''): ?array
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
        $extension = strtolower((string) pathinfo($realPath, PATHINFO_EXTENSION));
        if (!in_array($extension, self::EXTENSIONS, true) || self::detectFormat($realPath) !== self::familyOf($extension)) {
            return null;
        }
        if ($expectedChecksum !== '' && !hash_equals($expectedChecksum, (string) hash_file('sha256', $realPath))) {
            return null;
        }
        $contents = file_get_contents($realPath);
        if (!is_string($contents) || strlen($contents) !== $size) {
            return null;
        }

        return ['contents' => $contents, 'mime_type' => self::DOWNLOAD_MIME, 'extension' => $extension];
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

    /**
     * Famille de contenu reconnue d'après la signature du fichier, ou null.
     *
     * @return string|null « acad », « dxf », « dwf », « pdf » ou null si inconnu
     */
    public static function detectFormat(string $path): ?string
    {
        $handle = @fopen($path, 'rb');
        if ($handle === false) {
            return null;
        }
        $head = (string) fread($handle, 512);
        fclose($handle);
        if ($head === '') {
            return null;
        }
        // DWG et ses variantes (gabarit, normes, sauvegarde, enregistrement automatique).
        if (preg_match(self::DWG_VERSION, $head) === 1) {
            return 'acad';
        }
        if (str_starts_with($head, self::DXF_BINARY)) {
            return 'dxf';
        }
        if (str_starts_with($head, '%PDF-')) {
            return 'pdf';
        }
        // DWF et DWFX : conteneur ZIP (DWF 6+, DWFX/OPC) ou binaire historique.
        if (str_starts_with($head, "PK\x03\x04") || str_starts_with($head, "PK\x05\x06")) {
            return 'dwf';
        }
        if (preg_match(self::DWF_BINARY_HEADER, $head) === 1) {
            return 'dwf';
        }
        // Un binaire quelconque (exécutable, archive d'un autre type) contient des octets nuls.
        if (str_contains($head, "\0")) {
            return null;
        }
        // DXF texte : « 0 » puis « SECTION » sur les deux lignes utiles.
        $normalized = ltrim(str_replace(["\r\n", "\r"], "\n", $head), " \t\n");
        $lines = array_values(array_filter(
            array_map('trim', explode("\n", $normalized)),
            static fn (string $line): bool => $line !== ''
        ));
        if (count($lines) >= 2 && $lines[0] === '0' && strtoupper($lines[1]) === 'SECTION') {
            return 'dxf';
        }
        // Certains exportateurs placent d'abord un commentaire « 999 » ou un en-tête « HEADER ».
        if (count($lines) >= 1 && str_starts_with(strtoupper($lines[0]), 'SECTION')) {
            return 'dxf';
        }

        return null;
    }

    /** Famille de contenu attendue pour une extension, ou null si elle n'est pas acceptée. */
    public static function familyOf(string $extension): ?string
    {
        return self::FAMILIES[strtolower($extension)] ?? null;
    }

    /** Extension normalisée d'un nom de fichier, ou chaîne vide. */
    public static function extensionOf(string $filename): string
    {
        $name = basename(str_replace('\\', '/', $filename));
        $extension = strtolower((string) pathinfo($name, PATHINFO_EXTENSION));
        // « sv$ » est une extension légitime d'AutoCAD : le dollar fait partie du jeu autorisé.
        return preg_match('/^[a-z0-9$]{2,10}$/', $extension) === 1 ? $extension : '';
    }

    private function projectDirectory(int $projectId): string
    {
        if ($projectId < 1) {
            throw new InvalidArgumentException(__('projects.file_invalid'));
        }
        $root = BASE_PATH . '/storage/uploads/projects';
        if (is_link($root)) {
            throw new RuntimeException(__('projects.file_storage_failed'));
        }
        if (!is_dir($root) && !mkdir($root, 0750, true) && !is_dir($root)) {
            throw new RuntimeException(__('projects.file_storage_failed'));
        }
        $directory = $root . '/project-' . $projectId;
        if (is_link($directory)) {
            throw new RuntimeException(__('projects.file_storage_failed'));
        }
        if (!is_dir($directory) && !mkdir($directory, 0750) && !is_dir($directory)) {
            throw new RuntimeException(__('projects.file_storage_failed'));
        }
        if (!chmod($directory, 0750) || !is_writable($directory)) {
            throw new RuntimeException(__('projects.file_storage_failed'));
        }

        return $directory;
    }

    private function projectDirectoryPath(int $projectId): string
    {
        return BASE_PATH . '/storage/uploads/projects/project-' . $projectId;
    }

    private static function isStoredName(string $filename): bool
    {
        return preg_match('/^project-file-[a-f0-9]{32}\.(?:dwg|dxf|dwt|dws|bak|dwf|dwfx|pdf|sv\$)$/', $filename) === 1;
    }

    private static function safeOriginalName(string $name, string $extension): string
    {
        $name = basename(str_replace('\\', '/', $name));
        $name = preg_replace('/[\x00-\x1F\x7F]/u', '', $name) ?? '';
        $name = trim(mb_substr($name, 0, 180, 'UTF-8'));
        // Le nom d'origine est conservé pour l'affichage et le téléchargement, mais il ne
        // doit jamais ressembler à un chemin ni perdre son extension.
        $name = str_replace(['/', '\\'], '-', $name);

        return $name !== '' ? $name : 'plan.' . $extension;
    }
}
