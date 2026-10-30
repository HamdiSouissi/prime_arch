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

if (!function_exists('money_format_tnd')) {
    /** Formate un montant en dinars tunisiens (3 décimales), ex. "1 250,500 DT". */
    function money_format_tnd(float|int|string $amount): string
    {
        $c = Config::get('tunisia.currency', []);
        $formatted = number_format(
            (float) $amount,
            (int) ($c['decimals'] ?? 3),
            (string) ($c['decimal_separator'] ?? ','),
            (string) ($c['thousands_separator'] ?? ' ')
        );
        $symbol = (string) ($c['symbol'] ?? 'DT');
        return ($c['symbol_after'] ?? true) ? "{$formatted} {$symbol}" : "{$symbol} {$formatted}";
    }
}
