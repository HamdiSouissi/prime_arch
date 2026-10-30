<?php
declare(strict_types=1);

namespace Modules\GedVersions\Services;

use DateTimeImmutable;

/**
 * Règles du dépôt documentaire : catégories, étiquettes, versions, rétention et validation.
 *
 * Toutes les méthodes sont pures : elles ne lisent ni la base ni le disque (sauf `checksum`),
 * ce qui permet de les vérifier sans MySQL. Aucune règle de rétention légale n’est imposée :
 * la date limite est toujours saisie par le cabinet.
 */
final class GedService
{
    /** Catégories documentaires proposées. */
    public const CATEGORIES = ['plan', 'dce', 'contract', 'permit', 'report', 'photo', 'correspondence', 'other'];

    /** Types MIME acceptés et extension de stockage correspondante. */
    public const TYPES = [
        'application/pdf' => 'pdf',
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
        'text/plain' => 'txt',
        'text/csv' => 'csv',
        'application/zip' => 'zip',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => 'xlsx',
        'application/vnd.oasis.opendocument.text' => 'odt',
        'application/vnd.oasis.opendocument.spreadsheet' => 'ods',
    ];

    /** Taille maximale d’un fichier : 25 Mo. */
    public const MAX_BYTES = 26_214_400;

    /** Nombre maximal de versions conservées par document. */
    public const MAX_VERSIONS = 200;

    /** Longueurs maximales des champs saisis. */
    public const MAX_TITLE = 180;
    public const MAX_TAGS = 255;
    public const MAX_DESCRIPTION = 1000;
    public const MAX_NOTE = 500;
    public const MAX_SEARCH = 120;

    /** Nombre maximal d’étiquettes distinctes conservées. */
    public const MAX_TAG_COUNT = 8;

    /** Seuil d’alerte de rétention, en jours. */
    public const RETENTION_ALERT_DAYS = 60;

    /** Préfixe des noms de fichiers stockés. */
    public const STORED_PREFIX = 'ged-file-';

    /** Catégorie retenue lorsque la valeur reçue est inconnue. */
    public static function category(string $value): string
    {
        return in_array($value, self::CATEGORIES, true) ? $value : 'other';
    }

    /** Extension de stockage attendue pour un type MIME, ou `null` si le type est refusé. */
    public static function extensionForMime(string $mime): ?string
    {
        return self::TYPES[strtolower(trim($mime))] ?? null;
    }

    /** Extension lisible d’un nom de fichier, normalisée (`jpeg` → `jpg`). */
    public static function extensionOf(string $filename): string
    {
        $extension = strtolower((string) pathinfo(basename(str_replace('\\', '/', $filename)), PATHINFO_EXTENSION));
        $extension = preg_replace('/[^a-z0-9]/', '', $extension) ?? '';

        return $extension === 'jpeg' ? 'jpg' : $extension;
    }

    /**
     * Nom d’origine conservé pour l’affichage et le téléchargement.
     *
     * Le nom est réduit au seul nom de fichier, débarrassé des caractères de contrôle, des
     * séparateurs de chemin et des chevrons, puis tronqué. Un nom vide reçoit un libellé de repli :
     * le nom d’origine n’est jamais utilisé pour construire un chemin sur le disque.
     */
    public static function safeOriginalName(string $name, string $extension): string
    {
        $name = basename(str_replace('\\', '/', $name));
        $name = preg_replace('/[\x00-\x1F\x7F<>:"|?*]/u', '', $name) ?? '';
        $name = trim(preg_replace('/\s+/u', ' ', $name) ?? '');
        if (mb_strlen($name, 'UTF-8') > self::MAX_TITLE) {
            $name = mb_substr($name, 0, self::MAX_TITLE, 'UTF-8');
        }
        if ($name === '' || $name === '.' || $name === '..') {
            return 'document' . ($extension !== '' ? '.' . $extension : '');
        }

        return $name;
    }

    /** Nom de stockage opaque : jamais dérivé du nom fourni par l’utilisateur. */
    public static function storedName(string $extension, ?string $token = null): string
    {
        $token ??= bin2hex(random_bytes(16));
        $extension = preg_replace('/[^a-z0-9]/', '', strtolower($extension)) ?? '';

        return self::STORED_PREFIX . $token . ($extension !== '' ? '.' . $extension : '');
    }

    /** Vérifie la forme d’un nom de stockage (anti-traversée de répertoire). */
    public static function isStoredName(string $name): bool
    {
        return preg_match(
            '/^' . preg_quote(self::STORED_PREFIX, '/') . '[a-f0-9]{32}\.(?:pdf|jpg|png|webp|txt|csv|zip|docx|xlsx|odt|ods)$/',
            $name
        ) === 1;
    }

    /**
     * Étiquettes normalisées : séparées par virgule ou point-virgule, sans doublon
     * (comparaison insensible à la casse), limitées en nombre et en longueur.
     */
    public static function normalizeTags(string $raw): string
    {
        $tags = [];
        $seen = [];
        foreach (preg_split('/[,;]+/u', $raw) ?: [] as $tag) {
            $tag = trim(preg_replace('/\s+/u', ' ', $tag) ?? '');
            $tag = trim(ltrim($tag, '#'), " \t\n\r\0\x0B");
            if ($tag === '') {
                continue;
            }
            if (mb_strlen($tag, 'UTF-8') > 40) {
                $tag = mb_substr($tag, 0, 40, 'UTF-8');
            }
            $key = mb_strtolower($tag, 'UTF-8');
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $tags[] = $tag;
            if (count($tags) >= self::MAX_TAG_COUNT) {
                break;
            }
        }
        $joined = implode(', ', $tags);
        if (mb_strlen($joined, 'UTF-8') > self::MAX_TAGS) {
            $joined = mb_substr($joined, 0, self::MAX_TAGS, 'UTF-8');
        }

        return $joined;
    }

    /** @return array<int,string> */
    public static function tagList(?string $stored): array
    {
        if ($stored === null || trim($stored) === '') {
            return [];
        }

        return array_values(array_filter(
            array_map(static fn (string $tag): string => trim($tag), explode(',', $stored)),
            static fn (string $tag): bool => $tag !== ''
        ));
    }

    /** Libellé de version affiché : `v1`, `v2`, etc. */
    public static function versionLabel(int $number): string
    {
        return 'v' . max(1, $number);
    }

    /** Numéro de la prochaine version, à partir de la liste existante. */
    public static function nextVersionNumber(array $versions): int
    {
        $max = 0;
        foreach ($versions as $version) {
            $max = max($max, (int) ($version['version_number'] ?? 0));
        }

        return $max + 1;
    }

    /** Empreinte d’intégrité d’un fichier (SHA-256), ou `null` si le fichier est illisible. */
    public static function checksum(string $path): ?string
    {
        if ($path === '' || !is_file($path)) {
            return null;
        }
        $hash = @hash_file('sha256', $path);

        return is_string($hash) && $hash !== '' ? $hash : null;
    }

    /** Taille lisible, avec une unité traduite (Ko, Mo, Go). */
    public static function humanSize(int $bytes): string
    {
        if ($bytes < 1024) {
            return __('ged.unit_bytes', ['size' => (string) max(0, $bytes)]);
        }
        if ($bytes < 1_048_576) {
            return __('ged.unit_kb', ['size' => self::decimal($bytes / 1024)]);
        }
        if ($bytes < 1_073_741_824) {
            return __('ged.unit_mb', ['size' => self::decimal($bytes / 1_048_576)]);
        }

        return __('ged.unit_gb', ['size' => self::decimal($bytes / 1_073_741_824)]);
    }

    /**
     * État de la rétention par rapport à une date de référence.
     *
     * @return array{state:string,days:?int} `state` vaut `none`, `past`, `due`, `soon` ou `running`
     */
    public static function retention(?string $until, string $today): array
    {
        if ($until === null || trim($until) === '') {
            return ['state' => 'none', 'days' => null];
        }
        $limit = self::date($until);
        $reference = self::date($today);
        if ($limit === null || $reference === null) {
            return ['state' => 'none', 'days' => null];
        }
        $days = (int) $reference->diff($limit)->format('%r%a');
        if ($days < 0) {
            return ['state' => 'past', 'days' => $days];
        }
        if ($days === 0) {
            return ['state' => 'due', 'days' => 0];
        }
        if ($days <= self::RETENTION_ALERT_DAYS) {
            return ['state' => 'soon', 'days' => $days];
        }

        return ['state' => 'running', 'days' => $days];
    }

    /** Référence de classement d’un document : `GED-2026-0042`. */
    public static function reference(int $id, string $year): string
    {
        return sprintf('GED-%s-%04d', preg_replace('/\D/', '', $year) ?: date('Y'), max(1, $id));
    }

    /**
     * Validation d’une fiche documentaire.
     *
     * @param array<string,mixed> $input
     * @param array<int,int> $clientIds      identifiants de clients existants
     * @param array<int,int> $projectClients identifiant de projet ⇒ identifiant de client
     * @return array{values:array<string,mixed>,errors:array<string,string>}
     */
    public static function validateDocument(array $input, array $clientIds, array $projectClients): array
    {
        $values = [
            'title' => trim((string) ($input['title'] ?? '')),
            'category' => self::category((string) ($input['category'] ?? 'other')),
            'client_id' => trim((string) ($input['client_id'] ?? '')),
            'project_id' => trim((string) ($input['project_id'] ?? '')),
            'tags' => self::normalizeTags((string) ($input['tags'] ?? '')),
            'description' => trim((string) ($input['description'] ?? '')),
            'retention_until' => trim((string) ($input['retention_until'] ?? '')),
        ];
        $errors = [];

        if ($values['title'] === '') {
            $errors['title'] = __('ged.error.title_required');
        } elseif (mb_strlen($values['title'], 'UTF-8') > self::MAX_TITLE) {
            $errors['title'] = __('ged.error.title_length', ['max' => (string) self::MAX_TITLE]);
        }

        $clientId = 0;
        if ($values['client_id'] !== '') {
            $candidate = filter_var($values['client_id'], FILTER_VALIDATE_INT);
            if ($candidate === false || $candidate < 1 || !in_array((int) $candidate, $clientIds, true)) {
                $errors['client_id'] = __('ged.error.client');
            } else {
                $clientId = (int) $candidate;
            }
        }

        $projectId = 0;
        if ($values['project_id'] !== '') {
            $candidate = filter_var($values['project_id'], FILTER_VALIDATE_INT);
            if ($candidate === false || $candidate < 1 || !isset($projectClients[(int) $candidate])) {
                $errors['project_id'] = __('ged.error.project');
            } else {
                $projectId = (int) $candidate;
                $owner = (int) $projectClients[(int) $candidate];
                if ($clientId > 0 && $owner !== $clientId) {
                    $errors['project_id'] = __('ged.error.project_client');
                } elseif ($clientId === 0) {
                    // Le client est déduit du projet : aucune saisie supplémentaire n’est demandée.
                    $clientId = $owner;
                }
            }
        }

        if ($values['description'] !== '' && mb_strlen($values['description'], 'UTF-8') > self::MAX_DESCRIPTION) {
            $errors['description'] = __('ged.error.description_length', ['max' => (string) self::MAX_DESCRIPTION]);
        }

        if ($values['retention_until'] !== '') {
            $date = self::date($values['retention_until']);
            if ($date === null) {
                $errors['retention_until'] = __('ged.error.retention');
            } else {
                $values['retention_until'] = $date->format('Y-m-d');
            }
        }

        $values['client_id'] = $clientId > 0 ? $clientId : null;
        $values['project_id'] = $projectId > 0 ? $projectId : null;

        return ['values' => $values, 'errors' => $errors];
    }

    /**
     * Validation d’une nouvelle version : seule la note est saisie, le fichier est contrôlé
     * par le service de stockage (taille, type réel, nom d’origine).
     *
     * @param array<string,mixed> $input
     * @return array{values:array<string,mixed>,errors:array<string,string>}
     */
    public static function validateVersion(array $input): array
    {
        $note = trim((string) ($input['note'] ?? ''));
        $errors = [];
        if (mb_strlen($note, 'UTF-8') > self::MAX_NOTE) {
            $errors['note'] = __('ged.error.note_length', ['max' => (string) self::MAX_NOTE]);
        }

        return ['values' => ['note' => $note], 'errors' => $errors];
    }

    /**
     * Synthèse de la liste : volumétrie, répartition par catégorie, rétention et archives.
     *
     * @param array<int,array<string,mixed>> $documents
     * @return array<string,mixed>
     */
    public static function statistics(array $documents, string $today): array
    {
        $byCategory = [];
        foreach (self::CATEGORIES as $category) {
            $byCategory[$category] = 0;
        }
        $totalBytes = 0;
        $versions = 0;
        $archived = 0;
        $retentionSoon = 0;
        $retentionPast = 0;
        foreach ($documents as $document) {
            $category = self::category((string) ($document['category'] ?? 'other'));
            $byCategory[$category]++;
            $totalBytes += max(0, (int) ($document['total_bytes'] ?? 0));
            $versions += max(0, (int) ($document['version_count'] ?? 0));
            if (($document['archived_at'] ?? null) !== null && (string) $document['archived_at'] !== '') {
                $archived++;
            }
            $retention = self::retention(
                isset($document['retention_until']) ? (string) $document['retention_until'] : null,
                $today
            );
            if ($retention['state'] === 'soon' || $retention['state'] === 'due') {
                $retentionSoon++;
            } elseif ($retention['state'] === 'past') {
                $retentionPast++;
            }
        }

        return [
            'documents' => count($documents),
            'versions' => $versions,
            'total_bytes' => $totalBytes,
            'archived' => $archived,
            'by_category' => $byCategory,
            'retention_soon' => $retentionSoon,
            'retention_past' => $retentionPast,
        ];
    }

    /**
     * Sérialise un tableau en CSV lisible par un tableur (séparateur `;`, BOM UTF-8).
     *
     * Les cellules commençant par `=`, `+`, `-` ou `@` sont neutralisées pour éviter toute
     * interprétation par un tableur.
     *
     * @param array<int,array<int,string>> $rows
     */
    public static function csv(array $rows): string
    {
        $stream = fopen('php://temp/maxmemory:2097152', 'w+b');
        if ($stream === false) {
            return '';
        }
        fwrite($stream, "\xEF\xBB\xBF");
        foreach ($rows as $row) {
            $cells = array_map(static function (string $value): string {
                return $value !== '' && preg_match("/^[\x00-\x20]*[=+@-]/", $value) === 1 ? "'" . $value : $value;
            }, $row);
            fputcsv($stream, $cells, ';', '"', '');
        }
        rewind($stream);
        $contents = stream_get_contents($stream);
        fclose($stream);

        return is_string($contents) ? $contents : '';
    }

    /** Date `Y-m-d` valide, ou `null`. */
    public static function date(?string $value): ?DateTimeImmutable
    {
        if ($value === null || trim($value) === '') {
            return null;
        }
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', trim($value), new \DateTimeZone('UTC'));

        return $date instanceof DateTimeImmutable && $date->format('Y-m-d') === trim($value) ? $date : null;
    }

    /** Nombre à une décimale : virgule en français, point en arabe (convention d’affichage). */
    private static function decimal(float $value): string
    {
        $separator = \App\Core\Translator::locale() === 'fr' ? ',' : '.';

        return number_format($value, 1, $separator, ' ');
    }
}
