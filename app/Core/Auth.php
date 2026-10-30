<?php
declare(strict_types=1);

namespace App\Core;

use App\Models\UserRepository;

/**
 * Accès à l'identité authentifiée associée à la session.
 */
final class Auth
{
    private static bool $resolved = false;
    private static ?array $user = null;

    public static function user(): ?array
    {
        if (self::$resolved) {
            return self::$user;
        }
        self::$resolved = true;

        $id = filter_var(Session::get('auth_user_id'), FILTER_VALIDATE_INT);
        if ($id === false || $id < 1) {
            Session::forget('auth_user_id');
            return null;
        }

        self::$user = (new UserRepository())->findActiveById((int) $id);
        if (self::$user === null) {
            Session::forget('auth_user_id');
        }
        return self::$user;
    }

    public static function check(): bool
    {
        return self::user() !== null;
    }

    public static function id(): ?int
    {
        $user = self::user();
        return $user === null ? null : (int) $user['id'];
    }

    public static function can(string $permission): bool
    {
        $user = self::user();
        if ($user === null) {
            return false;
        }
        $permissions = $user['permissions'] ?? [];
        foreach (is_array($permissions) ? $permissions : [] as $granted) {
            if ($granted === '*' || $granted === $permission) {
                return true;
            }
            if (str_ends_with($granted, '.*') && str_starts_with($permission, substr($granted, 0, -1))) {
                return true;
            }
        }
        return false;
    }

    /** Ouvre une nouvelle session authentifiée et renouvelle le jeton CSRF. */
    public static function login(int $userId): void
    {
        Session::regenerate();
        Csrf::regenerate();
        Session::set('auth_user_id', $userId);
        self::$resolved = false;
        self::$user = null;
    }

    /** Efface l'identité et détruit la session. */
    public static function logout(): void
    {
        Session::regenerate();
        Session::destroy();
        self::$resolved = true;
        self::$user = null;
    }
}
