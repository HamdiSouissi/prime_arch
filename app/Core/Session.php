<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Gestion de session sécurisée et messages flash.
 */
final class Session
{
    public static function start(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }

        ini_set('session.use_strict_mode', '1');
        ini_set('session.use_only_cookies', '1');
        ini_set('session.use_trans_sid', '0');

        $configuredUrl = (string) Config::get('app.url', 'http://localhost');
        $secureRequest = !empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off';
        $secureCookie = $secureRequest || strtolower((string) parse_url($configuredUrl, PHP_URL_SCHEME)) === 'https';
        $lifetime = max(0, (int) Config::get('app.session.lifetime', 7200));
        ini_set('session.gc_maxlifetime', (string) max(1440, $lifetime));

        session_name((string) Config::get('app.session.name', 'primearch_session'));
        session_set_cookie_params([
            'lifetime' => $lifetime,
            'path'     => '/',
            'secure'   => $secureCookie,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        session_start();
    }

    /** Renouvelle l'identifiant de session lors d'un changement de privilège. */
    public static function regenerate(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
        }
    }

    /** Détruit les données et le cookie de session. */
    public static function destroy(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            return;
        }

        $_SESSION = [];
        $parameters = session_get_cookie_params();
        if (ini_get('session.use_cookies')) {
            setcookie(session_name(), '', [
                'expires'  => time() - 42000,
                'path'     => $parameters['path'],
                'domain'   => $parameters['domain'],
                'secure'   => $parameters['secure'],
                'httponly' => $parameters['httponly'],
                'samesite' => $parameters['samesite'] ?? 'Lax',
            ]);
        }
        session_destroy();
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        return $_SESSION[$key] ?? $default;
    }

    public static function set(string $key, mixed $value): void
    {
        $_SESSION[$key] = $value;
    }

    public static function forget(string $key): void
    {
        unset($_SESSION[$key]);
    }

    public static function flash(string $key, mixed $value): void
    {
        $_SESSION['_flash'][$key] = $value;
    }

    public static function pullFlash(string $key, mixed $default = null): mixed
    {
        $value = $_SESSION['_flash'][$key] ?? $default;
        unset($_SESSION['_flash'][$key]);
        if (empty($_SESSION['_flash'])) {
            unset($_SESSION['_flash']);
        }
        return $value;
    }
}
