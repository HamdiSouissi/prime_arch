<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Protection CSRF : jeton stocké en session, vérifié sur les requêtes POST.
 */
final class Csrf
{
    private const KEY = '_csrf_token';

    public static function token(): string
    {
        if (!Session::get(self::KEY)) {
            Session::set(self::KEY, bin2hex(random_bytes(32)));
        }
        return (string) Session::get(self::KEY);
    }

    public static function verify(?string $token): bool
    {
        $expected = (string) Session::get(self::KEY, '');
        return $expected !== '' && $token !== null && hash_equals($expected, $token);
    }
}
