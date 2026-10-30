<?php
declare(strict_types=1);

namespace App\Services;

use App\Models\GeographyRepository;

/**
 * Validation métier des fiches clients et de leurs adresses tunisiennes.
 */
final class ClientService
{
    private const TYPES = ['individual', 'company', 'organization'];
    private const STATUSES = ['prospect', 'active', 'archived'];

    public function __construct(
        private ?GeographyRepository $geography = null,
        private ?CustomFieldService $customFields = null
    ) {
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
            'client_type' => self::string($input['client_type'] ?? ''),
            'display_name' => trim(self::string($input['display_name'] ?? '')),
            'legal_name' => trim(self::string($input['legal_name'] ?? '')),
            'tax_id' => strtoupper(trim(self::string($input['tax_id'] ?? ''))),
            'email' => mb_strtolower(trim(self::string($input['email'] ?? '')), 'UTF-8'),
            'phone' => trim(self::string($input['phone'] ?? '')),
            'address_line' => trim(self::string($input['address_line'] ?? '')),
            'city' => trim(self::string($input['city'] ?? '')),
            'governorate_id' => trim(self::string($input['governorate_id'] ?? '')),
            'delegation_id' => trim(self::string($input['delegation_id'] ?? '')),
            'postal_code' => trim(self::string($input['postal_code'] ?? '')),
            'status' => self::string($input['status'] ?? 'prospect'),
            'notes' => trim(self::string($input['notes'] ?? '')),
        ];
        $errors = [];

        if (!in_array($values['client_type'], self::TYPES, true)) {
            $errors['client_type'] = __('clients.invalid');
        }
        if (mb_strlen($values['display_name'], 'UTF-8') < 2 || mb_strlen($values['display_name'], 'UTF-8') > 180) {
            $errors['display_name'] = __('clients.invalid');
        }
        if (mb_strlen($values['legal_name'], 'UTF-8') > 180) {
            $errors['legal_name'] = __('clients.invalid');
        }
        if (mb_strlen($values['tax_id'], 'UTF-8') > 32) {
            $errors['tax_id'] = __('clients.invalid');
        }
        if ($values['tax_id'] !== '' && !preg_match((string) config('tunisia.tax_id.pattern'), $values['tax_id'])) {
            $errors['tax_id'] = __('clients.invalid');
        }
        if ($values['email'] !== '' && (!filter_var($values['email'], FILTER_VALIDATE_EMAIL) || strlen($values['email']) > 190)) {
            $errors['email'] = __('clients.invalid');
        }
        if (mb_strlen($values['phone'], 'UTF-8') > 32 || !TunisianPhoneNumber::isValid($values['phone'])) {
            $errors['phone'] = __('clients.invalid_phone');
        } elseif ($values['phone'] !== '') {
            $values['phone'] = TunisianPhoneNumber::normalize($values['phone']);
        }
        if (mb_strlen($values['address_line'], 'UTF-8') > 255) {
            $errors['address_line'] = __('clients.invalid');
        }
        if (mb_strlen($values['city'], 'UTF-8') > 120) {
            $errors['city'] = __('clients.invalid');
        }
        if (mb_strlen($values['notes'], 'UTF-8') > 5000) {
            $errors['notes'] = __('clients.invalid');
        }
        if (!in_array($values['status'], self::STATUSES, true)) {
            $errors['status'] = __('clients.invalid');
        }
        $this->validateGeography($values, $errors);

        $custom = $this->customFields->validateValues('client', $submittedCustomFields, $existingCustomFields);
        if ($custom['errors'] !== []) {
            $errors['custom_fields'] = $custom['errors'];
        }
        $values['custom_fields'] = $custom['values'];
        return ['values' => $values, 'errors' => $errors];
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
            $errors['governorate_id'] = __('clients.invalid');
        }
        if ($delegationId !== '' && (!ctype_digit($delegationId) || $governorateId === ''
            || !ctype_digit($governorateId)
            || !$this->geography->delegationBelongsTo((int) $delegationId, (int) $governorateId)
        )) {
            $errors['delegation_id'] = __('clients.invalid');
        }
        if ($values['postal_code'] !== '' && !preg_match((string) config('tunisia.postal_code.pattern'), $values['postal_code'])) {
            $errors['postal_code'] = __('clients.invalid');
        }
    }

    private static function string(mixed $value): string
    {
        return is_string($value) ? $value : (is_int($value) ? (string) $value : '');
    }
}
