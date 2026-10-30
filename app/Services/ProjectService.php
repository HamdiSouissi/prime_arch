<?php
declare(strict_types=1);

namespace App\Services;

use App\Models\ClientRepository;
use App\Models\GeographyRepository;
use DateTimeImmutable;

/**
 * Validation métier des projets et de leur rattachement géographique/client.
 */
final class ProjectService
{
    private const STATUSES = ['planning', 'active', 'on_hold', 'completed', 'cancelled'];

    public function __construct(
        private ?ClientRepository $clients = null,
        private ?GeographyRepository $geography = null,
        private ?CustomFieldService $customFields = null
    ) {
        $this->clients ??= new ClientRepository();
        $this->geography ??= new GeographyRepository();
        $this->customFields ??= new CustomFieldService();
    }

    /** @param array<string,mixed> $input
     *  @param array<string,mixed> $existingCustomFields
     *  @return array{values:array<string,mixed>,errors:array<string,mixed>}
     */
    public function validate(array $input, mixed $submittedCustomFields, array $existingCustomFields = []): array
    {
        $values = [
            'client_id' => trim(self::string($input['client_id'] ?? '')),
            'name' => trim(self::string($input['name'] ?? '')),
            'project_type' => trim(self::string($input['project_type'] ?? '')),
            'description' => trim(self::string($input['description'] ?? '')),
            'status' => self::string($input['status'] ?? 'planning'),
            'address_line' => trim(self::string($input['address_line'] ?? '')),
            'city' => trim(self::string($input['city'] ?? '')),
            'governorate_id' => trim(self::string($input['governorate_id'] ?? '')),
            'delegation_id' => trim(self::string($input['delegation_id'] ?? '')),
            'postal_code' => trim(self::string($input['postal_code'] ?? '')),
            'start_date' => trim(self::string($input['start_date'] ?? '')),
            'target_end_date' => trim(self::string($input['target_end_date'] ?? '')),
            'budget_hours' => trim(self::string($input['budget_hours'] ?? '')),
            'fee_budget_amount' => trim(self::string($input['fee_budget_amount'] ?? '')),
        ];
        $errors = [];

        if (!ctype_digit($values['client_id']) || (int) $values['client_id'] < 1) {
            $errors['client_id'] = __('projects.invalid');
        } else {
            $client = $this->clients->findById((int) $values['client_id']);
            if ($client === null || ($client['status'] ?? '') === 'archived') {
                $errors['client_id'] = __('projects.invalid_client');
            }
        }
        if (mb_strlen($values['name'], 'UTF-8') < 2 || mb_strlen($values['name'], 'UTF-8') > 180) {
            $errors['name'] = __('projects.invalid');
        }
        if (mb_strlen($values['project_type'], 'UTF-8') > 120) {
            $errors['project_type'] = __('projects.invalid');
        }
        if (mb_strlen($values['description'], 'UTF-8') > 5000) {
            $errors['description'] = __('projects.invalid');
        }
        if (mb_strlen($values['address_line'], 'UTF-8') > 255) {
            $errors['address_line'] = __('projects.invalid');
        }
        if (mb_strlen($values['city'], 'UTF-8') > 120) {
            $errors['city'] = __('projects.invalid');
        }
        if (!in_array($values['status'], self::STATUSES, true)) {
            $errors['status'] = __('projects.invalid');
        }
        $this->validateDate($values['start_date'], 'start_date', $errors);
        $this->validateDate($values['target_end_date'], 'target_end_date', $errors);
        $budgetHours = self::normalizeDecimal($values['budget_hours'], 2, 7);
        if ($values['budget_hours'] !== '' && $budgetHours === null) {
            $errors['budget_hours'] = __('projects.invalid_budget');
        } elseif ($budgetHours !== null) {
            $values['budget_hours'] = $budgetHours;
        }
        $feeBudget = self::normalizeDecimal($values['fee_budget_amount'], 3, 15);
        if ($values['fee_budget_amount'] !== '' && $feeBudget === null) {
            $errors['fee_budget_amount'] = __('projects.invalid_budget');
        } elseif ($feeBudget !== null) {
            $values['fee_budget_amount'] = $feeBudget;
        }
        if ($values['start_date'] !== '' && $values['target_end_date'] !== ''
            && $values['target_end_date'] < $values['start_date']
        ) {
            $errors['target_end_date'] = __('projects.invalid_dates');
        }
        $this->validateGeography($values, $errors);

        $custom = $this->customFields->validateValues('project', $submittedCustomFields, $existingCustomFields);
        if ($custom['errors'] !== []) {
            $errors['custom_fields'] = $custom['errors'];
        }
        $values['custom_fields'] = $custom['values'];
        return ['values' => $values, 'errors' => $errors];
    }

    /** @param array<string,mixed> $errors */
    private function validateDate(string $value, string $key, array &$errors): void
    {
        if ($value === '') {
            return;
        }
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        $dateErrors = DateTimeImmutable::getLastErrors();
        if ($date === false || ($dateErrors !== false && ($dateErrors['warning_count'] > 0 || $dateErrors['error_count'] > 0))
            || $date->format('Y-m-d') !== $value
        ) {
            $errors[$key] = __('projects.invalid');
        }
    }

    /** @param array<string,mixed> $values
     *  @param array<string,string> $errors
     */
    private function validateGeography(array $values, array &$errors): void
    {
        $governorateId = $values['governorate_id'];
        $delegationId = $values['delegation_id'];
        if ($governorateId !== '' && (!ctype_digit($governorateId)
            || !$this->geography->governorateExists((int) $governorateId)
        )) {
            $errors['governorate_id'] = __('projects.invalid');
        }
        if ($delegationId !== '' && (!ctype_digit($delegationId) || $governorateId === ''
            || !ctype_digit($governorateId)
            || !$this->geography->delegationBelongsTo((int) $delegationId, (int) $governorateId)
        )) {
            $errors['delegation_id'] = __('projects.invalid');
        }
        if ($values['postal_code'] !== '' && !preg_match((string) config('tunisia.postal_code.pattern'), $values['postal_code'])) {
            $errors['postal_code'] = __('projects.invalid');
        }
    }

    private static function normalizeDecimal(string $value, int $scale, int $maxWholeDigits): ?string
    {
        $value = str_replace(',', '.', trim($value));
        if (!preg_match('/^\\d{1,' . $maxWholeDigits . '}(?:\\.\\d{1,' . $scale . '})?$/', $value)) {
            return null;
        }
        [$whole, $fraction] = array_pad(explode('.', $value, 2), 2, '');
        $whole = ltrim($whole, '0');
        return ($whole === '' ? '0' : $whole) . '.' . str_pad($fraction, $scale, '0');
    }

    private static function string(mixed $value): string
    {
        return is_string($value) ? $value : (is_int($value) ? (string) $value : '');
    }
}
