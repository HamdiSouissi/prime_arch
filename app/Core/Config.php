<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Accès à la configuration (dossier config/) via la notation pointée.
 * Exemple : Config::get('app.name'), Config::get('tunisia.currency.code').
 */
final class Config
{
    /** @var array<string,mixed> */
    private static array $items = [];

    /**
     * Charge tous les fichiers config/*.php (sauf routes.php, qui retourne une closure).
     */
    public static function load(string $dir): void
    {
        foreach (glob(rtrim($dir, '/') . '/*.php') ?: [] as $file) {
            $name = basename($file, '.php');
            if ($name === 'routes') {
                continue;
            }
            self::$items[$name] = require $file;
        }
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        $value = self::$items;
        foreach (explode('.', $key) as $segment) {
            if (!is_array($value) || !array_key_exists($segment, $value)) {
                return $default;
            }
            $value = $value[$segment];
        }
        return $value;
    }

    public static function set(string $key, mixed $value): void
    {
        $ref = &self::$items;
        foreach (explode('.', $key) as $segment) {
            if (!isset($ref[$segment]) || !is_array($ref[$segment])) {
                $ref[$segment] = [];
            }
            $ref = &$ref[$segment];
        }
        $ref = $value;
    }
}
