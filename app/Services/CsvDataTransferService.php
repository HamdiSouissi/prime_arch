<?php
declare(strict_types=1);

namespace App\Services;

use App\Models\ClientRepository;
use App\Models\CustomFieldRepository;
use App\Models\GeographyRepository;
use App\Models\ProjectRepository;
use Throwable;

/** Export CSV compatible Excel et import contrôlé des fiches clients/projets. */
final class CsvDataTransferService
{
    private const MAX_FILE_BYTES = 5_242_880;
    private const MAX_ROWS = 500;
    private const MAX_DISPLAYED_ERRORS = 25;

    private const CLIENT_HEADERS = [
        'client_type', 'display_name', 'legal_name', 'tax_id', 'email', 'phone',
        'address_line', 'city', 'governorate_iso_code', 'delegation_code',
        'postal_code', 'status', 'notes',
    ];

    private const PROJECT_HEADERS = [
        'client_reference', 'name', 'project_type', 'description', 'status',
        'address_line', 'city', 'governorate_iso_code', 'delegation_code',
        'postal_code', 'start_date', 'target_end_date', 'budget_hours',
    ];

    public function __construct(
        private ?ClientRepository $clients = null,
        private ?ProjectRepository $projects = null,
        private ?GeographyRepository $geography = null,
        private ?CustomFieldRepository $customFields = null
    ) {
        $this->clients ??= new ClientRepository();
        $this->projects ??= new ProjectRepository();
        $this->geography ??= new GeographyRepository();
        $this->customFields ??= new CustomFieldRepository();
    }

    /** @return array<int,array<string,mixed>> */
    public function customFieldsFor(string $entity): array
    {
        return $this->customFields->all($entity === 'clients' ? 'client' : 'project', true);
    }

    /** @param array<int,array<string,mixed>> $customFields */
    public function template(string $entity, array $customFields, bool $includeCosts = false): string
    {
        $headers = $entity === 'clients'
            ? self::CLIENT_HEADERS
            : self::PROJECT_HEADERS;
        if ($entity === 'projects' && $includeCosts) {
            $headers[] = 'fee_budget_amount';
        }
        foreach ($customFields as $field) {
            $headers[] = 'custom_' . (string) $field['field_key'];
        }
        return $this->encode([$headers]);
    }

    /** @param array<string,string> $filters */
    public function exportClients(array $filters): string
    {
        $customFields = $this->customFieldsFor('clients');
        $headers = array_merge(['reference'], self::CLIENT_HEADERS, ['created_at', 'updated_at']);
        foreach ($customFields as $field) {
            $headers[] = 'custom_' . (string) $field['field_key'];
        }

        $rows = [$headers];
        foreach ($this->clients->exportRows($filters) as $client) {
            $values = [
                (string) ($client['reference'] ?? ''),
                (string) $client['client_type'],
                (string) $client['display_name'],
                (string) ($client['legal_name'] ?? ''),
                (string) ($client['tax_id'] ?? ''),
                (string) ($client['email'] ?? ''),
                (string) ($client['phone'] ?? ''),
                (string) ($client['address_line'] ?? ''),
                (string) ($client['city'] ?? ''),
                (string) ($client['governorate_iso_code'] ?? ''),
                (string) ($client['delegation_code'] ?? ''),
                (string) ($client['postal_code'] ?? ''),
                (string) $client['status'],
                (string) ($client['notes'] ?? ''),
                (string) $client['created_at'],
                (string) $client['updated_at'],
            ];
            $custom = self::decodeCustomFields($client['custom_fields'] ?? null);
            foreach ($customFields as $field) {
                $values[] = self::csvValue($custom[(string) $field['field_key']] ?? '');
            }
            $rows[] = $values;
        }
        return $this->encode($rows);
    }

    /** @param array<string,string> $filters */
    public function exportProjects(array $filters, bool $includeCosts = false): string
    {
        $customFields = $this->customFieldsFor('projects');
        $headers = array_merge(['reference', 'client_name'], self::PROJECT_HEADERS);
        if ($includeCosts) {
            $headers[] = 'fee_budget_amount';
        }
        $headers = array_merge($headers, ['created_at', 'updated_at']);
        foreach ($customFields as $field) {
            $headers[] = 'custom_' . (string) $field['field_key'];
        }

        $rows = [$headers];
        foreach ($this->projects->exportRows($filters, $includeCosts) as $project) {
            $values = [
                (string) ($project['reference'] ?? ''),
                (string) $project['client_name'],
                (string) ($project['client_reference'] ?? ''),
                (string) $project['name'],
                (string) ($project['project_type'] ?? ''),
                (string) ($project['description'] ?? ''),
                (string) $project['status'],
                (string) ($project['address_line'] ?? ''),
                (string) ($project['city'] ?? ''),
                (string) ($project['governorate_iso_code'] ?? ''),
                (string) ($project['delegation_code'] ?? ''),
                (string) ($project['postal_code'] ?? ''),
                (string) ($project['start_date'] ?? ''),
                (string) ($project['target_end_date'] ?? ''),
                (string) ($project['budget_hours'] ?? ''),
            ];
            if ($includeCosts) {
                $values[] = (string) ($project['fee_budget_amount'] ?? '');
            }
            $values[] = (string) $project['created_at'];
            $values[] = (string) $project['updated_at'];
            $custom = self::decodeCustomFields($project['custom_fields'] ?? null);
            foreach ($customFields as $field) {
                $values[] = self::csvValue($custom[(string) $field['field_key']] ?? '');
            }
            $rows[] = $values;
        }
        return $this->encode($rows);
    }

    /** @param array<string,mixed> $upload
     *  @return array{file_error:string,created:int,failed:int,created_ids:array<int,int>,errors:array<int,array{line:int,message:string}>,errors_remaining:int}
     */
    public function importClients(array $upload, ?int $actorId): array
    {
        $customFields = $this->customFieldsFor('clients');
        $parsed = $this->readCsv($upload, self::CLIENT_HEADERS);
        if ($parsed['file_error'] !== '') {
            return self::failedImport($parsed['file_error']);
        }
        if ($parsed['rows'] === [] && $parsed['errors'] === []) {
            return self::failedImport(__('common.csv_empty'));
        }

        $createdIds = [];
        $errors = $parsed['errors'];
        $failed = count($errors);
        $service = new ClientService($this->geography, null);
        foreach ($parsed['rows'] as $record) {
            try {
                $row = $record['data'];
                $geography = $this->resolveGeography($row);
                if ($geography['errors'] !== []) {
                    $errors[] = ['line' => $record['line'], 'message' => implode(' · ', $geography['errors'])];
                    $failed++;
                    continue;
                }
                $values = [
                    'client_type' => $row['client_type'] ?? '',
                    'display_name' => $row['display_name'] ?? '',
                    'legal_name' => $row['legal_name'] ?? '',
                    'tax_id' => $row['tax_id'] ?? '',
                    'email' => $row['email'] ?? '',
                    'phone' => $row['phone'] ?? '',
                    'address_line' => $row['address_line'] ?? '',
                    'city' => $row['city'] ?? '',
                    'governorate_id' => $geography['governorate_id'],
                    'delegation_id' => $geography['delegation_id'],
                    'postal_code' => $row['postal_code'] ?? '',
                    'status' => trim((string) ($row['status'] ?? '')) !== '' ? $row['status'] : 'prospect',
                    'notes' => $row['notes'] ?? '',
                ];
                $validated = $service->validate($values, self::customValues($row, $customFields));
                if ($validated['errors'] !== []) {
                    $errors[] = [
                        'line' => $record['line'],
                        'message' => implode(' · ', self::flattenErrors($validated['errors'])),
                    ];
                    $failed++;
                    continue;
                }
                $createdIds[] = $this->clients->create($validated['values'], $actorId);
            } catch (Throwable $error) {
                error_log('Échec d’import CSV client : ' . get_class($error));
                $errors[] = ['line' => $record['line'], 'message' => __('common.csv_row_failed')];
                $failed++;
            }
        }
        return self::importResult($createdIds, $failed, $errors);
    }

    /** @param array<string,mixed> $upload
     *  @return array{file_error:string,created:int,failed:int,created_ids:array<int,int>,errors:array<int,array{line:int,message:string}>,errors_remaining:int}
     */
    public function importProjects(array $upload, ?int $actorId, bool $canManageCosts = false): array
    {
        $customFields = $this->customFieldsFor('projects');
        $requiredHeaders = self::PROJECT_HEADERS;
        if ($canManageCosts) {
            $requiredHeaders[] = 'fee_budget_amount';
        }
        $parsed = $this->readCsv($upload, $requiredHeaders);
        if ($parsed['file_error'] !== '') {
            return self::failedImport($parsed['file_error']);
        }
        if ($parsed['rows'] === [] && $parsed['errors'] === []) {
            return self::failedImport(__('common.csv_empty'));
        }

        $createdIds = [];
        $errors = $parsed['errors'];
        $failed = count($errors);
        $service = new ProjectService($this->clients, $this->geography, null);
        foreach ($parsed['rows'] as $record) {
            try {
                $row = $record['data'];
                $clientReference = trim((string) ($row['client_reference'] ?? ''));
                $clientId = $clientReference !== '' ? $this->clients->findIdByReference($clientReference) : null;
                if ($clientId === null) {
                    $errors[] = ['line' => $record['line'], 'message' => __('projects.invalid_client')];
                    $failed++;
                    continue;
                }
                $geography = $this->resolveGeography($row);
                if ($geography['errors'] !== []) {
                    $errors[] = ['line' => $record['line'], 'message' => implode(' · ', $geography['errors'])];
                    $failed++;
                    continue;
                }
                $values = [
                    'client_id' => (string) $clientId,
                    'name' => $row['name'] ?? '',
                    'project_type' => $row['project_type'] ?? '',
                    'description' => $row['description'] ?? '',
                    'status' => trim((string) ($row['status'] ?? '')) !== '' ? $row['status'] : 'planning',
                    'address_line' => $row['address_line'] ?? '',
                    'city' => $row['city'] ?? '',
                    'governorate_id' => $geography['governorate_id'],
                    'delegation_id' => $geography['delegation_id'],
                    'postal_code' => $row['postal_code'] ?? '',
                    'start_date' => $row['start_date'] ?? '',
                    'target_end_date' => $row['target_end_date'] ?? '',
                    'budget_hours' => $row['budget_hours'] ?? '',
                    'fee_budget_amount' => $canManageCosts ? ($row['fee_budget_amount'] ?? '') : '',
                ];
                $validated = $service->validate($values, self::customValues($row, $customFields));
                if ($validated['errors'] !== []) {
                    $errors[] = [
                        'line' => $record['line'],
                        'message' => implode(' · ', self::flattenErrors($validated['errors'])),
                    ];
                    $failed++;
                    continue;
                }
                $createdIds[] = $this->projects->create($validated['values'], $actorId);
            } catch (Throwable $error) {
                error_log('Échec d’import CSV projet : ' . get_class($error));
                $errors[] = ['line' => $record['line'], 'message' => __('common.csv_row_failed')];
                $failed++;
            }
        }
        return self::importResult($createdIds, $failed, $errors);
    }

    /** @param array<string,mixed> $upload
     *  @param array<int,string> $requiredHeaders
     *  @return array{file_error:string,rows:array<int,array{line:int,data:array<string,string>}>,errors:array<int,array{line:int,message:string}>}
     */
    private function readCsv(array $upload, array $requiredHeaders): array
    {
        $uploadError = (int) ($upload['error'] ?? UPLOAD_ERR_NO_FILE);
        if ($uploadError === UPLOAD_ERR_NO_FILE) {
            return ['file_error' => __('common.csv_file_required'), 'rows' => [], 'errors' => []];
        }
        $filename = (string) ($upload['name'] ?? '');
        $temporaryPath = (string) ($upload['tmp_name'] ?? '');
        $reportedSize = filter_var($upload['size'] ?? null, FILTER_VALIDATE_INT);
        if ($uploadError !== UPLOAD_ERR_OK || strtolower(pathinfo($filename, PATHINFO_EXTENSION)) !== 'csv'
            || $temporaryPath === '' || !is_uploaded_file($temporaryPath)
            || $reportedSize === false || $reportedSize < 1 || $reportedSize > self::MAX_FILE_BYTES
        ) {
            return ['file_error' => __('common.csv_file_invalid'), 'rows' => [], 'errors' => []];
        }
        $actualSize = filesize($temporaryPath);
        if (!is_int($actualSize) || $actualSize !== $reportedSize || $actualSize > self::MAX_FILE_BYTES) {
            return ['file_error' => __('common.csv_file_invalid'), 'rows' => [], 'errors' => []];
        }
        $stream = fopen($temporaryPath, 'rb');
        if ($stream === false) {
            return ['file_error' => __('common.csv_file_invalid'), 'rows' => [], 'errors' => []];
        }

        try {
            $headerLine = fgets($stream);
            if (!is_string($headerLine)) {
                return ['file_error' => __('common.csv_header_invalid'), 'rows' => [], 'errors' => []];
            }
            if (str_starts_with($headerLine, "\xEF\xBB\xBF")) {
                $headerLine = substr($headerLine, 3);
            }
            $delimiter = str_contains($headerLine, ';') ? ';' : ',';
            $headers = str_getcsv(rtrim($headerLine, "\r\n"), $delimiter, '"', '');
            $headers = array_map(static fn (mixed $header): string => strtolower(trim((string) $header)), $headers);
            if ($headers === [] || count($headers) !== count(array_unique($headers))
                || array_diff($requiredHeaders, $headers) !== []
                || !mb_check_encoding(implode('', $headers), 'UTF-8')
            ) {
                return ['file_error' => __('common.csv_header_invalid'), 'rows' => [], 'errors' => []];
            }

            $rows = [];
            $errors = [];
            $csvLine = 1;
            $nonEmptyRows = 0;
            while (($cells = fgetcsv($stream, null, $delimiter, '"', '')) !== false) {
                $csvLine++;
                if ($cells === [null] || $cells === []) {
                    continue;
                }
                $cells = array_map(
                    static fn (mixed $cell): string => self::unescapeFormulaMarker(is_string($cell) ? $cell : ''),
                    $cells
                );
                if (array_filter($cells, static fn (string $cell): bool => trim($cell) !== '') === []) {
                    continue;
                }
                $nonEmptyRows++;
                if ($nonEmptyRows > self::MAX_ROWS) {
                    return ['file_error' => __('common.csv_too_many_rows'), 'rows' => [], 'errors' => []];
                }
                if (count($cells) > count($headers)) {
                    $errors[] = ['line' => $csvLine, 'message' => __('common.csv_row_columns_invalid')];
                    continue;
                }
                $cells = array_pad($cells, count($headers), '');
                if (!mb_check_encoding($cells, 'UTF-8')) {
                    $errors[] = ['line' => $csvLine, 'message' => __('common.csv_encoding_invalid')];
                    continue;
                }
                $mapped = array_combine($headers, $cells);
                if (is_array($mapped)) {
                    $rows[] = ['line' => $csvLine, 'data' => $mapped];
                }
            }
            return ['file_error' => '', 'rows' => $rows, 'errors' => $errors];
        } finally {
            fclose($stream);
        }
    }

    /** @param array<string,string> $row
     *  @return array{governorate_id:string,delegation_id:string,errors:array<int,string>}
     */
    private function resolveGeography(array $row): array
    {
        $governorateCode = strtoupper(trim((string) ($row['governorate_iso_code'] ?? '')));
        $delegationCode = trim((string) ($row['delegation_code'] ?? ''));
        $governorateId = null;
        $delegationId = null;
        $errors = [];

        if ($governorateCode !== '') {
            $governorateId = $this->geography->governorateIdByIsoCode($governorateCode);
            if ($governorateId === null) {
                $errors[] = __('common.csv_invalid_governorate');
            }
        }
        if ($delegationCode !== '') {
            if ($governorateId === null) {
                $errors[] = __('common.csv_delegation_needs_governorate');
            } else {
                $delegationId = $this->geography->delegationIdByCode($delegationCode, $governorateId);
                if ($delegationId === null) {
                    $errors[] = __('common.csv_invalid_delegation');
                }
            }
        }

        return [
            'governorate_id' => $governorateId === null ? '' : (string) $governorateId,
            'delegation_id' => $delegationId === null ? '' : (string) $delegationId,
            'errors' => $errors,
        ];
    }

    /** @param array<string,string> $row
     *  @param array<int,array<string,mixed>> $definitions
     *  @return array<string,string>
     */
    private static function customValues(array $row, array $definitions): array
    {
        $values = [];
        foreach ($definitions as $field) {
            $column = 'custom_' . (string) $field['field_key'];
            if (array_key_exists($column, $row)) {
                $values[(string) $field['field_key']] = $row[$column];
            }
        }
        return $values;
    }

    /** @param array<string,mixed> $errors
     *  @return array<int,string>
     */
    private static function flattenErrors(array $errors): array
    {
        $messages = [];
        $flatten = static function (mixed $value) use (&$flatten, &$messages): void {
            if (is_array($value)) {
                foreach ($value as $item) {
                    $flatten($item);
                }
            } elseif (is_scalar($value) && trim((string) $value) !== '') {
                $messages[] = (string) $value;
            }
        };
        $flatten($errors);
        return array_values(array_unique($messages));
    }

    /** @param array<int,array{line:int,message:string}> $errors
     *  @param array<int,int> $createdIds
     *  @return array{file_error:string,created:int,failed:int,created_ids:array<int,int>,errors:array<int,array{line:int,message:string}>,errors_remaining:int}
     */
    private static function importResult(array $createdIds, int $failed, array $errors): array
    {
        $displayedErrors = array_slice($errors, 0, self::MAX_DISPLAYED_ERRORS);
        return [
            'file_error' => '',
            'created' => count($createdIds),
            'failed' => $failed,
            'created_ids' => $createdIds,
            'errors' => $displayedErrors,
            'errors_remaining' => max(0, count($errors) - count($displayedErrors)),
        ];
    }

    /** @return array{file_error:string,created:int,failed:int,created_ids:array<int,int>,errors:array<int,array{line:int,message:string}>,errors_remaining:int} */
    private static function failedImport(string $message): array
    {
        return [
            'file_error' => $message,
            'created' => 0,
            'failed' => 0,
            'created_ids' => [],
            'errors' => [],
            'errors_remaining' => 0,
        ];
    }

    /** @param array<int,array<int,mixed>> $rows */
    private function encode(array $rows): string
    {
        $stream = fopen('php://temp/maxmemory:2097152', 'w+b');
        if ($stream === false) {
            throw new \RuntimeException('Le fichier CSV temporaire ne peut pas être créé.');
        }
        fwrite($stream, "\xEF\xBB\xBF");
        foreach ($rows as $row) {
            $cells = array_map(self::csvValue(...), $row);
            fputcsv($stream, $cells, ';', '"', '');
        }
        rewind($stream);
        $contents = stream_get_contents($stream);
        fclose($stream);
        return is_string($contents) ? $contents : '';
    }

    private static function unescapeFormulaMarker(string $value): string
    {
        return preg_match("/^'[\\x00-\\x20]*[=+@-]/", $value) === 1
            ? substr($value, 1)
            : $value;
    }

    private static function csvValue(mixed $value): string
    {
        if (is_bool($value)) {
            $value = $value ? '1' : '0';
        } elseif (!is_scalar($value)) {
            $value = '';
        }
        $text = (string) $value;
        if ($text !== '' && preg_match('/^[\x00-\x20]*[=+@-]/', $text) === 1) {
            return "'" . $text;
        }
        return $text;
    }

    /** @return array<string,mixed> */
    private static function decodeCustomFields(mixed $encoded): array
    {
        if (is_array($encoded)) {
            return $encoded;
        }
        if (is_string($encoded)) {
            $decoded = json_decode($encoded, true);
            return is_array($decoded) ? $decoded : [];
        }
        return [];
    }
}
