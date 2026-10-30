<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Chargement et lecture des variables d'environnement (fichier .env).
 */
final class Env
{
    /** @var array<string,string> */
    private static array $vars = [];

    /**
     * Charge un fichier .env (format CLE=valeur, commentaires avec #).
     */
    public static function load(string $file): void
    {
        if (!is_file($file)) {
            return;
        }
        foreach (file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
            $line = trim($line);
            if ($line === '' || $line[0] === '#' || !str_contains($line, '=')) {
                continue;
            }
            [$key, $value] = explode('=', $line, 2);
            $key = trim($key);
            $value = trim($value);
            // Retire un éventuel commentaire en fin de ligne (hors guillemets)
            if ($value !== '' && $value[0] !== '"' && $value[0] !== "'") {
                $value = trim(explode(' #', $value, 2)[0]);
            }
            $value = trim($value, "\"'");
            self::$vars[$key] = $value;
        }
    }

    /**
     * Retourne la valeur d'une variable, convertie (true/false/null) si besoin.
     */
    public static function get(string $key, mixed $default = null): mixed
    {
        $value = self::$vars[$key] ?? $_ENV[$key] ?? (getenv($key) !== false ? getenv($key) : null);
        if ($value === null) {
            return $default;
        }
        return match (strtolower((string) $value)) {
            'true'  => true,
            'false' => false,
            'null'  => null,
            default => $value,
        };
    }
}
