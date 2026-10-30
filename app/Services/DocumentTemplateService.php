<?php
declare(strict_types=1);

namespace App\Services;

/** Validation des modèles de documents modifiables par le cabinet. */
final class DocumentTemplateService
{
    private const TYPES = ['quote', 'contract', 'invoice', 'credit_note'];
    private const LOCALES = ['fr', 'ar'];

    /** @param array<string,mixed> $input
     *  @return array{values:array<string,mixed>,errors:array<string,string>}
     */
    public function validate(array $input): array
    {
        $values = [
            'document_type' => self::string($input['document_type'] ?? ''),
            'locale' => self::string($input['locale'] ?? ''),
            'name' => trim(self::string($input['name'] ?? '')),
            'title' => trim(self::string($input['title'] ?? '')),
            'terms_text' => trim(self::string($input['terms_text'] ?? '')),
            'legal_notice' => trim(self::string($input['legal_notice'] ?? '')),
            'validity_days' => self::string($input['validity_days'] ?? '0'),
            'payment_terms_days' => self::string($input['payment_terms_days'] ?? '0'),
            'is_default' => in_array($input['is_default'] ?? '0', [1, '1', true, 'on'], true),
            'is_active' => in_array($input['is_active'] ?? '0', [1, '1', true, 'on'], true),
        ];
        $errors = [];
        if (!in_array($values['document_type'], self::TYPES, true)
            || !in_array($values['locale'], self::LOCALES, true)
        ) {
            $errors['document_type'] = __('documents.templates_invalid');
        }
        if (mb_strlen($values['name'], 'UTF-8') < 2 || mb_strlen($values['name'], 'UTF-8') > 120) {
            $errors['name'] = __('documents.templates_invalid');
        }
        if (mb_strlen($values['title'], 'UTF-8') < 2 || mb_strlen($values['title'], 'UTF-8') > 180) {
            $errors['title'] = __('documents.templates_invalid');
        }
        if (mb_strlen($values['terms_text'], 'UTF-8') > 4000
            || mb_strlen($values['legal_notice'], 'UTF-8') > 2000
        ) {
            $errors['terms_text'] = __('documents.templates_invalid');
        }
        foreach (['validity_days', 'payment_terms_days'] as $field) {
            if (!ctype_digit($values[$field]) || (int) $values[$field] > 3650) {
                $errors[$field] = __('documents.templates_invalid');
                $values[$field] = 0;
            } else {
                $values[$field] = (int) $values[$field];
            }
        }
        if ($values['is_default']) {
            $values['is_active'] = true;
        }
        return ['values' => $values, 'errors' => $errors];
    }

    private static function string(mixed $value): string
    {
        return is_string($value) || is_int($value) ? trim((string) $value) : '';
    }
}
