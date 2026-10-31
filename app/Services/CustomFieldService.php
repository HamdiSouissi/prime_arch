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
    /**
     * Types proposés dans les réglages.
     *
     * « image » (photos de chantier, plans scannés) et « file » (plans AutoCAD DWG/DXF/DWT/DWS,
     * sauvegardes BAK/SV$, DWF/DWFX, PDF) n'existent que pour les fiches projets : les fichiers
     * vivent dans l'espace privé du projet et suivent sa suppression.
     */
    public const TYPES = ['text', 'number', 'date', 'select', 'checkbox', 'image', 'file'];
    /** Types alimentés par téléversement : aucune valeur n'est stockée dans la colonne JSON. */
    public const UPLOAD_TYPES = ['image', 'file'];
    /** Types saisis directement dans le formulaire de la fiche, comme valeur texte. */
    public const VALUE_TYPES = ['text', 'number', 'date', 'select', 'checkbox'];
    /** Un champ « image » accepte plusieurs fichiers : bornes appliquées côté serveur. */
    public const IMAGE_MAX_PER_UPLOAD = 10;
    public const IMAGE_MAX_PER_FIELD = 20;
    /** Un champ « fichier » accepte un plan par envoi, dans la limite du champ. */
    public const FILE_MAX_PER_UPLOAD = 5;
    public const FILE_MAX_PER_FIELD = 50;

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
            $key = (string) $field['field_key'];
            $result = self::normalizeValue($field, $submitted[$key] ?? null);
            if ($result['action'] === 'error') {
                $errors[$key] = (string) $result['error'];
                continue;
            }
            if ($result['action'] === 'unset') {
                unset($values[$key]);
                continue;
            }
            $values[$key] = $result['value'];
        }

        return ['values' => $values, 'errors' => $errors];
    }

    /**
     * Valide la valeur d'un seul champ actif, sans exiger celles des autres.
     *
     * La fiche projet ou client propose de renseigner un champ directement dans son bloc
     * « Champs personnalisés » : ce formulaire ne transporte qu'une valeur, et un champ
     * obligatoire laissé vide ailleurs ne doit pas bloquer cette saisie isolée.
     *
     * @param array<string,mixed> $existing valeurs déjà enregistrées sur la fiche
     * @return array{values:array<string,mixed>,errors:array<string,string>}
     */
    public function validateFieldValue(string $entityType, string $fieldKey, mixed $submitted, array $existing = []): array
    {
        $values = $existing;
        $field = null;
        foreach ($this->fields->all($entityType, true) as $candidate) {
            if ((string) $candidate['field_key'] === $fieldKey) {
                $field = $candidate;
                break;
            }
        }
        if ($field === null) {
            return ['values' => $values, 'errors' => ['field' => __('custom_fields.unknown_field')]];
        }
        if (in_array((string) $field['field_type'], self::UPLOAD_TYPES, true)) {
            return ['values' => $values, 'errors' => ['field' => __('custom_fields.upload_field_not_editable')]];
        }

        $result = self::normalizeValue($field, $submitted);
        if ($result['action'] === 'error') {
            return ['values' => $values, 'errors' => [$fieldKey => (string) $result['error']]];
        }
        if ($result['action'] === 'unset') {
            unset($values[$fieldKey]);

            return ['values' => $values, 'errors' => []];
        }
        $values[$fieldKey] = $result['value'];

        return ['values' => $values, 'errors' => []];
    }

    /**
     * Normalise la valeur soumise pour une définition.
     *
     * Un champ à téléversement n'a jamais de valeur JSON : les fichiers vivent dans
     * `project_images` / `project_files`, rattachés au champ par sa clé. Un champ obligatoire
     * vide produit une erreur ; un champ facultatif vide est retiré des valeurs.
     *
     * @param array<string,mixed> $field définition normalisée (clé `field_type`, `options`…)
     * @return array{action:string,value:mixed,error:?string} action : set, unset ou error
     */
    private static function normalizeValue(array $field, mixed $rawValue): array
    {
        if (in_array((string) $field['field_type'], self::UPLOAD_TYPES, true)) {
            return ['action' => 'unset', 'value' => null, 'error' => null];
        }
        if ($field['field_type'] === 'checkbox') {
            $value = in_array($rawValue, ['1', 1, true, 'on'], true);
            if ($field['is_required'] && !$value) {
                return ['action' => 'error', 'value' => null, 'error' => __('custom_fields.required')];
            }

            return ['action' => 'set', 'value' => $value, 'error' => null];
        }

        if (!is_scalar($rawValue) && $rawValue !== null) {
            return ['action' => 'error', 'value' => null, 'error' => __('custom_fields.invalid_value')];
        }
        $rawValue = trim((string) ($rawValue ?? ''));
        if ($rawValue === '') {
            return $field['is_required']
                ? ['action' => 'error', 'value' => null, 'error' => __('custom_fields.required')]
                : ['action' => 'unset', 'value' => null, 'error' => null];
        }

        switch ($field['field_type']) {
            case 'text':
                return mb_strlen($rawValue, 'UTF-8') > 2000
                    ? ['action' => 'error', 'value' => null, 'error' => __('custom_fields.invalid_value')]
                    : ['action' => 'set', 'value' => $rawValue, 'error' => null];
            case 'number':
                $number = filter_var($rawValue, FILTER_VALIDATE_FLOAT);
                return $number === false || !is_finite((float) $number)
                    ? ['action' => 'error', 'value' => null, 'error' => __('custom_fields.invalid_value')]
                    : ['action' => 'set', 'value' => (float) $number, 'error' => null];
            case 'date':
                $date = DateTimeImmutable::createFromFormat('!Y-m-d', $rawValue);
                $dateErrors = DateTimeImmutable::getLastErrors();
                $valid = $date !== false
                    && !($dateErrors !== false && ($dateErrors['warning_count'] > 0 || $dateErrors['error_count'] > 0))
                    && $date->format('Y-m-d') === $rawValue;
                return $valid
                    ? ['action' => 'set', 'value' => $rawValue, 'error' => null]
                    : ['action' => 'error', 'value' => null, 'error' => __('custom_fields.invalid_value')];
            case 'select':
                return in_array($rawValue, $field['options'], true)
                    ? ['action' => 'set', 'value' => $rawValue, 'error' => null]
                    : ['action' => 'error', 'value' => null, 'error' => __('custom_fields.invalid_value')];
            default:
                // Type inconnu du code (base antérieure à la migration 0015) : jamais deviné.
                return ['action' => 'error', 'value' => null, 'error' => __('custom_fields.invalid_value')];
        }
    }

    /** Le type est-il connu du code (et non seulement de la base) ? */
    public static function isKnownType(string $type): bool
    {
        return in_array($type, self::TYPES, true);
    }

    /** Le type désigne-t-il un téléversement (image ou fichier) ? */
    public static function isUploadType(string $type): bool
    {
        return in_array($type, self::UPLOAD_TYPES, true);
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
        } elseif (in_array($fieldType, self::UPLOAD_TYPES, true) && $entityType !== 'project') {
            // Un téléversement n'a de sens que sur une fiche projet : les fichiers sont stockés
            // dans l'espace privé du projet et suivent sa suppression.
            $errors['field_type'] = $fieldType === 'file'
                ? __('custom_fields.file_projects_only')
                : __('custom_fields.image_projects_only');
        }
        if (in_array($fieldType, self::UPLOAD_TYPES, true)) {
            // Les fichiers s'ajoutent une fois le projet créé : rendre le champ obligatoire
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
