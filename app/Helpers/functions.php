<?php
declare(strict_types=1);

/**
 * Fonctions utilitaires globales (chargées par Composer via "autoload.files").
 */

use App\Core\Config;
use App\Core\Csrf;
use App\Core\Translator;

if (!function_exists('e')) {
    /** Échappe une valeur pour l'affichage HTML. */
    function e(mixed $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

if (!function_exists('url')) {
    /** Construit une URL absolue à partir d'un chemin. */
    function url(string $path = ''): string
    {
        return rtrim((string) Config::get('app.url', ''), '/') . '/' . ltrim($path, '/');
    }
}

if (!function_exists('asset')) {
    /** URL d'une ressource statique de public/assets. */
    function asset(string $path): string
    {
        return url('assets/' . ltrim($path, '/'));
    }
}

if (!function_exists('config')) {
    /** Raccourci vers Config::get(). */
    function config(string $key, mixed $default = null): mixed
    {
        return Config::get($key, $default);
    }
}

if (!function_exists('current_path')) {
    /**
     * Chemin de la requête en cours, sans la query string ni le sous-dossier d'installation.
     * Utilisé par exemple pour revenir sur la même page après un changement de langue.
     */
    function current_path(): string
    {
        $uri = (string) parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH);
        $base = rtrim(str_replace('\\', '/', dirname((string) ($_SERVER['SCRIPT_NAME'] ?? ''))), '/');
        if ($base !== '' && str_starts_with($uri, $base)) {
            $uri = substr($uri, strlen($base)) ?: '/';
        }
        return '/' . trim($uri, '/');
    }
}

if (!function_exists('__')) {
    /** Traduction d'une clé (ex. __('nav.home')). */
    function __(string $key, array $replace = []): string
    {
        return Translator::get($key, $replace);
    }
}

if (!function_exists('csrf_field')) {
    /** Champ caché CSRF à placer dans chaque formulaire POST. */
    function csrf_field(): string
    {
        return '<input type="hidden" name="_token" value="' . e(Csrf::token()) . '">';
    }
}

if (!function_exists('role_label')) {
    /**
     * Libellé traduit d'un rôle ; repli sur le libellé stocké en base (rôles personnalisés).
     */
    function role_label(string $slug, string $fallback = ''): string
    {
        $key = 'role.' . $slug;
        if ($slug !== '' && Translator::has($key)) {
            return __($key);
        }
        return $fallback !== '' ? $fallback : $slug;
    }
}

if (!function_exists('money_format_tnd')) {
    /** Formate un montant en dinars tunisiens (3 décimales), ex. "1 250,500 DT". */
    function money_format_tnd(float|int|string $amount): string
    {
        $c = Config::get('tunisia.currency', []);
        $decimals = (int) ($c['decimals'] ?? 3);
        $decimalSeparator = (string) ($c['decimal_separator'] ?? ',');
        $thousandsSeparator = (string) ($c['thousands_separator'] ?? ' ');
        $formatted = null;
        if (is_string($amount) && preg_match('/^(-?)(\\d+)(?:\\.(\\d+))?$/', trim($amount), $parts)
            && strlen($parts[3] ?? '') <= $decimals
        ) {
            $whole = ltrim($parts[2], '0');
            $whole = $whole === '' ? '0' : $whole;
            $whole = preg_replace('/\\B(?=(\\d{3})+(?!\\d))/', $thousandsSeparator, $whole) ?? $whole;
            $fraction = str_pad((string) ($parts[3] ?? ''), $decimals, '0');
            $formatted = ($parts[1] === '-' ? '-' : '') . $whole
                . ($decimals > 0 ? $decimalSeparator . $fraction : '');
        }
        $formatted ??= number_format(
            (float) $amount,
            $decimals,
            $decimalSeparator,
            $thousandsSeparator
        );
        $symbol = (string) ($c['symbol'] ?? 'DT');
        return ($c['symbol_after'] ?? true) ? "{$formatted} {$symbol}" : "{$symbol} {$formatted}";
    }
}
