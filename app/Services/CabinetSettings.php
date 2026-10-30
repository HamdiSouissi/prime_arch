<?php
declare(strict_types=1);

namespace App\Services;

use App\Models\SettingRepository;

/**
 * Valeurs du cabinet et paramètres fiscaux modifiables sans changer le code métier.
 */
final class CabinetSettings
{
    public function __construct(private ?SettingRepository $settings = null)
    {
        $this->settings ??= new SettingRepository();
    }

    /** @return array<string,string> */
    public function get(): array
    {
        $values = [
            'name' => (string) config('app.name', 'Prime Architecture'),
            'address' => '',
            'phone' => '',
            'email' => '',
            'tax_id' => '',
            'rib' => '',
            'governorate_id' => '',
            'delegation_id' => '',
            'postal_code' => '',
            'logo' => '',
            'vat_default' => (string) config('tunisia.vat.default', 19),
            'vat_rates' => implode(',', array_map('strval', (array) config('tunisia.vat.rates', [19, 13, 7, 0]))),
            'stamp_duty' => (string) config('tunisia.taxes.stamp_duty', '1.000'),
            'withholding_rate' => (string) config('tunisia.taxes.withholding_tax', '0.000'),
        ];

        foreach (['cabinet', 'tax'] as $group) {
            foreach ($this->settings->all($group) as $key => $value) {
                $prefix = $group . '.';
                if (str_starts_with($key, $prefix)) {
                    $field = substr($key, strlen($prefix));
                    if (array_key_exists($field, $values)) {
                        $values[$field] = $value;
                    }
                }
            }
        }
        return $values;
    }

    /** @param array<string,string> $values */
    public function save(array $values): void
    {
        $cabinet = [];
        foreach (['name', 'address', 'phone', 'email', 'tax_id', 'rib', 'governorate_id', 'delegation_id', 'postal_code', 'logo'] as $field) {
            if (array_key_exists($field, $values)) {
                $cabinet['cabinet.' . $field] = $values[$field];
            }
        }
        $tax = [];
        foreach (['vat_default', 'vat_rates', 'stamp_duty', 'withholding_rate'] as $field) {
            if (array_key_exists($field, $values)) {
                $tax['tax.' . $field] = $values[$field];
            }
        }

        $groups = [];
        if ($cabinet !== []) {
            $groups['cabinet'] = $cabinet;
        }
        if ($tax !== []) {
            $groups['tax'] = $tax;
        }
        $this->settings->setGroups($groups);
    }
}
