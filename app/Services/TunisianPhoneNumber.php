<?php
declare(strict_types=1);

namespace App\Services;

/** Validation et normalisation des numéros tunisiens à huit chiffres. */
final class TunisianPhoneNumber
{
    public static function isValid(string $phone): bool
    {
        $phone = trim($phone);
        return $phone === '' || preg_match((string) config('tunisia.phone.pattern'), $phone) === 1;
    }

    public static function normalize(string $phone): string
    {
        $phone = trim($phone);
        if ($phone === '') {
            return '';
        }

        $digits = preg_replace('/\D/u', '', $phone) ?? '';
        if (str_starts_with($digits, '00216')) {
            $digits = substr($digits, 5);
        } elseif (str_starts_with($digits, '216') && strlen($digits) === 11) {
            $digits = substr($digits, 3);
        }

        if (strlen($digits) !== 8) {
            return $phone;
        }

        return '+216 ' . substr($digits, 0, 2) . ' '
            . substr($digits, 2, 3) . ' ' . substr($digits, 5, 3);
    }
}
