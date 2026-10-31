<?php
declare(strict_types=1);

namespace App\Services;

use App\Models\CustomFieldRepository;
use DateTimeImmutable;

/**
 * Validation et normalisation des définitions et valeurs de champs personnalisés.
 */
final class CustomFieldService
{
    /** Types proposés dans les réglages. « image » n'existe que pour les fiches projets. */
    public const TYPES = ['text', 'number', 'date', 'select', 'checkbox', 'image'];
    /** Un champ « image » accepte plusieurs fichiers : bornes appliquées côté serveur. */
    public const IMAGE_MAX_PER_UPLOAD = 10;
    public const IMAGE_MAX_PER_FIELD = 20;

    public function __construct(private ?CustomFieldRepository $fields = null)
    {
        $this->fields ??= new CustomFieldRepository();
    }

    /** @param array<string,mixed> $existing
     *  @return array{values:array<string,mixed>,errors:array<string,string>}
     */
    public function validateValues(string $entityType, mixed $submitted, array $existing = []): array
    {
        $errors = [];
        $values = $existing;
        $submitted = is_array($submitted) ? $submitted : [];

        foreach ($this->fields->all($entityType, true) as $field) {
            $key = $field['field_key'];
            if ($field['field_type'] === 'image') {
                // Les fichiers image ont leur propre formulaire sur la fiche projet : ils sont
                // stockés dans `project_images` et rattachés au champ par sa clé. Aucune valeur
                // n'est donc conservée dans la colonne JSON, qui resterait une seconde source
                // de vérité toujours désynchronisée.
                unset($values[$key]);
                continue;
            }
            if ($field['field_type'] === 'checkbox') {
                $value = in_array($submitted[$key] ?? null, ['1', 1, true, 'on'], true);
                if ($field['is_required'] && !$value) {
                    $errors[$key] = __('custom_fields.required');
                }
                $values[$key] = $value;
                continue;
            }

            $rawValue = $submitted[$key] ?? '';
            if (!is_scalar($rawValue) && $rawValue !== null) {
                $errors[$key] = __('custom_fields.invalid_value');
                continue;
            }
            $rawValue = trim((string) ($rawValue ?? ''));
            if ($rawValue === '') {
                if ($field['is_required']) {
                    $errors[$key] = __('custom_fields.required');
                } else {
                    unset($values[$key]);
                }
                continue;
            }

            switch ($field['field_type']) {
                case 'text':
                    if (mb_strlen($rawValue, 'UTF-8') > 2000) {
                        $errors[$key] = __('custom_fields.invalid_value');
                    } else {
                        $values[$key] = $rawValue;
                    }
                    break;
                case 'number':
                    $number = filter_var($rawValue, FILTER_VALIDATE_FLOAT);
                    if ($number === false || !is_finite((float) $number)) {
                        $errors[$key] = __('custom_fields.invalid_value');
                    } else {
                        $values[$key] = (float) $number;
                    }
                    break;
                case 'date':
                    $date = DateTimeImmutable::createFromFormat('!Y-m-d', $rawValue);
                    $dateErrors = DateTimeImmutable::getLastErrors();
                    if ($date === false || ($dateErrors !== false && ($dateErrors['warning_count'] > 0 || $dateErrors['error_count'] > 0))
                        || $date->format('Y-m-d') !== $rawValue
                    ) {
                        $errors[$key] = __('custom_fields.invalid_value');
                    } else {
                        $values[$key] = $rawValue;
                    }
                    break;
                case 'select':
                    if (!in_array($rawValue, $field['options'], true)) {
                        $errors[$key] = __('custom_fields.invalid_value');
                    } else {
                        $values[$key] = $rawValue;
                    }
                    break;
                default:
                    $errors[$key] = __('custom_fields.invalid_value');
            }
        }

        return ['values' => $values, 'errors' => $errors];
    }

    /** @param array<string,mixed> $input
     *  @return array{data:array<string,mixed>,errors:array<string,string>}
     */
    public function validateDefinition(array $input): array
    {
        $entityType = is_string($input['entity_type'] ?? null) ? $input['entity_type'] : '';
        $fieldKey = is_string($input['field_key'] ?? null) ? trim($input['field_key']) : '';
        $label = is_string($input['label'] ?? null) ? trim($input['label']) : '';
        $fieldType = is_string($input['field_type'] ?? null) ? $input['field_type'] : '';
        $optionsText = is_string($input['options'] ?? null) ? $input['options'] : '';
        $required = in_array($input['is_required'] ?? null, ['1', 1, true, 'on'], true);
        $sortInput = is_string($input['sort_order'] ?? null) ? $input['sort_order'] : '0';
        $errors = [];

        if (!in_array($entityType, ['client', 'project'], true)) {
            $errors['entity_type'] = __('custom_fields.invalid');
        }
        if (!preg_match('/^[a-z][a-z0-9_]{1,79}$/', $fieldKey)) {
            $errors['field_key'] = __('custom_fields.invalid_key');
        }
        if (mb_strlen($label, 'UTF-8') < 2 || mb_strlen($label, 'UTF-8') > 120) {
            $errors['label'] = __('custom_fields.invalid');
        }
        if (!in_array($fieldType, self::TYPES, true)) {
            $errors['field_type'] = __('custom_fields.invalid');
        } elseif ($fieldType === 'image' && $entityType !== 'project') {
            // Un téléversement d'images n'a de sens que sur une fiche projet : les images sont
            // stockées dans l'espace privé du projet et suivent sa suppression.
            $errors['field_type'] = __('custom_fields.image_projects_only');
        }
        if ($fieldType === 'image') {
            // Les images s'ajoutent une fois le projet créé : rendre le champ obligatoire
            // bloquerait toute création de projet. Le libellé reste informatif.
            $required = false;
        }
        if (!ctype_digit($sortInput) || (int) $sortInput > 9999) {
            $errors['sort_order'] = __('custom_fields.invalid');
        }

        $options = [];
        if ($fieldType === 'select') {
            $options = array_values(array_unique(array_filter(array_map(
                static fn (string $option): string => trim($option),
                preg_split('/\r\n|\r|\n/', $optionsText) ?: []
            ), static fn (string $option): bool => $option !== '')));
            if ($options === [] || count($options) > 50) {
                $errors['options'] = __('custom_fields.options_required');
            }
            foreach ($options as $option) {
                if (mb_strlen($option, 'UTF-8') > 120) {
                    $errors['options'] = __('custom_fields.invalid');
                    break;
                }
            }
        }

        return [
            'data' => [
                'entity_type' => $entityType,
                'field_key' => $fieldKey,
                'label' => $label,
                'field_type' => $fieldType,
                'options' => $options,
                'is_required' => $required,
                'sort_order' => ctype_digit($sortInput) ? (int) $sortInput : 0,
            ],
            'errors' => $errors,
        ];
    }
}
