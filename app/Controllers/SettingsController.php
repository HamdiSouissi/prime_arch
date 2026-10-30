<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\View;
use App\Models\GeographyRepository;
use App\Services\CabinetLogoStorage;
use App\Services\CabinetSettings;
use App\Services\ViewContext;
use Throwable;

/**
 * Réglages de l'identité du cabinet et taux fiscaux indicatifs.
 */
final class SettingsController
{
    public function edit(Request $request): Response
    {
        if (!Auth::can('settings.view')) {
            return Response::html(__('common.forbidden'), 403);
        }

        $settings = new CabinetSettings();
        $values = $settings->get();
        $flashedValues = Session::pullFlash('settings.values', []);
        if (is_array($flashedValues)) {
            $values = array_merge($values, $flashedValues);
        }
        $governorateId = ctype_digit((string) ($values['governorate_id'] ?? ''))
            ? (int) $values['governorate_id']
            : 0;
        $context = (new ViewContext())->authenticated('Réglages du cabinet', 'settings');

        return Response::html(View::render('pages/app/settings', $context + [
            'settings' => $values,
            'errors' => Session::pullFlash('settings.errors', []),
            'notice' => Session::pullFlash('settings.notice'),
            'governorates' => (new GeographyRepository())->governorates(),
            'delegations' => $governorateId > 0 ? (new GeographyRepository())->delegations($governorateId) : [],
            'canManage' => Auth::can('settings.manage'),
            'logoUrl' => $values['logo'] !== '' ? url('/media/cabinet-logo') : asset('img/logo-placeholder.svg'),
        ]));
    }

    public function update(Request $request): Response
    {
        if (!Auth::can('settings.manage')) {
            return Response::html(__('common.forbidden'), 403);
        }

        $values = [
            'name' => self::inputString($request, 'name'),
            'address' => self::inputString($request, 'address'),
            'phone' => self::inputString($request, 'phone'),
            'email' => mb_strtolower(self::inputString($request, 'email'), 'UTF-8'),
            'tax_id' => strtoupper(self::inputString($request, 'tax_id')),
            'rib' => self::inputString($request, 'rib'),
            'governorate_id' => self::inputString($request, 'governorate_id'),
            'delegation_id' => self::inputString($request, 'delegation_id'),
            'postal_code' => self::inputString($request, 'postal_code'),
            'vat_default' => self::inputString($request, 'vat_default'),
            'vat_rates' => self::inputString($request, 'vat_rates'),
            'stamp_duty' => self::inputString($request, 'stamp_duty'),
            'withholding_rate' => self::inputString($request, 'withholding_rate'),
        ];
        $errors = $this->validate($values);
        if ($errors !== []) {
            Session::flash('settings.errors', $errors);
            Session::flash('settings.values', $values);
            return Response::redirect(url('/settings'));
        }

        $current = (new CabinetSettings())->get();
        $values['vat_rates'] = implode(',', array_map(static fn (string $rate): string => (string) (int) trim($rate), explode(',', $values['vat_rates'])));
        $values['stamp_duty'] = (string) self::decimal3($values['stamp_duty']);
        $values['withholding_rate'] = (string) self::decimal3($values['withholding_rate']);
        $values['governorate_id'] = $values['governorate_id'] === '' ? '' : (string) (int) $values['governorate_id'];
        $values['delegation_id'] = $values['delegation_id'] === '' ? '' : (string) (int) $values['delegation_id'];

        $logoStorage = new CabinetLogoStorage();
        $newLogo = null;
        $file = $request->file('logo');
        if (is_array($file) && (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
            try {
                $newLogo = $logoStorage->store($file);
                $values['logo'] = $newLogo;
            } catch (Throwable $error) {
                Session::flash('settings.errors', ['logo' => $error->getMessage()]);
                Session::flash('settings.values', $values);
                return Response::redirect(url('/settings'));
            }
        } elseif (self::inputString($request, 'remove_logo') === '1') {
            $values['logo'] = '';
        } else {
            $values['logo'] = $current['logo'];
        }

        try {
            (new CabinetSettings())->save($values);
        } catch (Throwable $error) {
            if ($newLogo !== null) {
                $logoStorage->remove($newLogo);
                $values['logo'] = $current['logo'];
            }
            error_log((string) $error);
            Session::flash('settings.errors', ['general' => __('settings.invalid')]);
            Session::flash('settings.values', $values);
            return Response::redirect(url('/settings'));
        }

        if ($current['logo'] !== '' && $current['logo'] !== $values['logo']) {
            $logoStorage->remove($current['logo']);
        }
        Session::flash('settings.notice', __('settings.saved'));
        return Response::redirect(url('/settings'));
    }

    /** @param array<string,string> $values
     *  @return array<string,string>
     */
    private function validate(array $values): array
    {
        $errors = [];
        if (mb_strlen($values['name'], 'UTF-8') < 2 || mb_strlen($values['name'], 'UTF-8') > 150) {
            $errors['name'] = __('settings.invalid');
        }
        if (mb_strlen($values['address'], 'UTF-8') > 255) {
            $errors['address'] = __('settings.invalid');
        }
        if (mb_strlen($values['phone'], 'UTF-8') > 32
            || ($values['phone'] !== '' && !preg_match((string) config('tunisia.phone.pattern'), $values['phone']))
        ) {
            $errors['phone'] = __('settings.invalid');
        }
        if ($values['email'] !== '' && (!filter_var($values['email'], FILTER_VALIDATE_EMAIL) || strlen($values['email']) > 190)) {
            $errors['email'] = __('settings.invalid');
        }
        if (mb_strlen($values['tax_id'], 'UTF-8') > 32
            || ($values['tax_id'] !== '' && !preg_match((string) config('tunisia.tax_id.pattern'), $values['tax_id']))
        ) {
            $errors['tax_id'] = __('settings.invalid');
        }
        if (mb_strlen($values['rib'], 'UTF-8') > 50) {
            $errors['rib'] = __('settings.invalid');
        }

        $geography = new GeographyRepository();
        $governorateId = $values['governorate_id'];
        $delegationId = $values['delegation_id'];
        if ($governorateId !== '' && (!ctype_digit($governorateId) || !$geography->governorateExists((int) $governorateId))) {
            $errors['governorate_id'] = __('settings.invalid');
        }
        if ($delegationId !== '' && (
            !ctype_digit($delegationId)
            || $governorateId === ''
            || !ctype_digit($governorateId)
            || !$geography->delegationBelongsTo((int) $delegationId, (int) $governorateId)
        )) {
            $errors['delegation_id'] = __('settings.invalid');
        }
        if ($values['postal_code'] !== '' && !preg_match((string) config('tunisia.postal_code.pattern'), $values['postal_code'])) {
            $errors['postal_code'] = __('settings.invalid');
        }

        $vatRates = array_map('trim', explode(',', $values['vat_rates']));
        $normalizedRates = [];
        $validRates = $vatRates !== [] && strlen($values['vat_rates']) <= 80;
        foreach ($vatRates as $rate) {
            if (!preg_match('/^(?:100|[1-9]?[0-9])$/', $rate)) {
                $validRates = false;
                continue;
            }
            $normalizedRates[] = (string) (int) $rate;
        }
        $normalizedRates = array_values(array_unique($normalizedRates));
        if (!$validRates || count($normalizedRates) !== count($vatRates) || !in_array($values['vat_default'], $normalizedRates, true)) {
            $errors['vat_rates'] = __('settings.invalid');
        }
        if (!in_array($values['vat_default'], $normalizedRates, true)) {
            $errors['vat_default'] = __('settings.invalid');
        }
        if (self::decimal3($values['stamp_duty']) === null) {
            $errors['stamp_duty'] = __('settings.invalid');
        }
        $withholding = self::decimal3($values['withholding_rate']);
        if ($withholding === null) {
            $errors['withholding_rate'] = __('settings.invalid');
        } else {
            [$wholeRate, $fractionRate] = explode('.', $withholding, 2);
            if ((int) $wholeRate > 100 || ((int) $wholeRate === 100 && (int) $fractionRate > 0)) {
                $errors['withholding_rate'] = __('settings.invalid');
            }
        }
        return $errors;
    }

    private static function inputString(Request $request, string $key): string
    {
        $value = $request->input($key, '');
        return is_string($value) ? trim($value) : '';
    }

    /** Accepte un montant décimal saisi avec point ou virgule et le normalise sans float. */
    private static function decimal3(string $value): ?string
    {
        $normalized = str_replace(',', '.', trim($value));
        if (!preg_match('/^\d{1,9}(?:\.\d{1,3})?$/', $normalized)) {
            return null;
        }
        [$whole, $fraction] = array_pad(explode('.', $normalized, 2), 2, '');
        return ltrim($whole, '0') . ($whole === str_repeat('0', strlen($whole)) ? '0' : '')
            . '.' . str_pad($fraction, 3, '0');
    }
}
