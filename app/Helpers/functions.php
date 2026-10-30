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
     * Chemin de la requête en cours (query string conservée), sans le sous-dossier d'installation.
     * Utilisé par exemple pour revenir sur la même page après un changement de langue.
     */
    function current_path(): string
    {
        $requestUri = (string) ($_SERVER['REQUEST_URI'] ?? '/');
        $uri = (string) parse_url($requestUri, PHP_URL_PATH);
        $base = rtrim(str_replace('\\', '/', dirname((string) ($_SERVER['SCRIPT_NAME'] ?? ''))), '/');
        if ($base !== '' && str_starts_with($uri, $base)) {
            $uri = substr($uri, strlen($base)) ?: '/';
        }
        $path = '/' . trim($uri, '/');
        // La query string est conservée (filtres, pagination) sans caractère de contrôle.
        $query = (string) parse_url($requestUri, PHP_URL_QUERY);
        $query = trim(str_replace(["\r", "\n"], '', $query));
        return $query === '' ? $path : $path . '?' . $query;
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
    /**
     * Formate un montant en dinars tunisiens (3 décimales), ex. « 1 250,500 DT » ou « 1 250,500 د.ت ».
     *
     * Deux précautions pour l'affichage en arabe (dir="rtl") :
     *  – le séparateur de milliers est une espace insécable (U+00A0), de classe bidi CS : le
     *    montant reste un seul bloc de chiffres et « 2 380,000 » ne s'affiche plus « 380,000 2 » ;
     *  – le symbole monétaire suit la langue active (DT en français, د.ت en arabe) dans l'ordre
     *    logique montant puis devise, ce qui correspond à la lecture de droite à gauche.
     *
     * Les montants sont formatés à partir de la chaîne DECIMAL d'origine pour éviter
     * toute perte de précision lors d'une conversion en flottant.
     */
    function money_format_tnd(float|int|string $amount): string
    {
        $c = Config::get('tunisia.currency', []);
        $decimals = max(0, (int) ($c['decimals'] ?? 3));
        $decimalSeparator = (string) ($c['decimal_separator'] ?? ',');
        // Espace simple configurée → espace insécable : jamais de coupure ni de réordonnancement.
        $configuredSeparator = (string) ($c['thousands_separator'] ?? ' ');
        $thousandsSeparator = trim($configuredSeparator) === ''
            ? "\u{00A0}"
            : str_replace(' ', "\u{00A0}", $configuredSeparator);

        $formatted = null;
        if (is_string($amount)
            && preg_match('/^(-?)(\d+)(?:\.(\d+))?$/', trim($amount), $parts) === 1
            && strlen($parts[3] ?? '') <= $decimals
        ) {
            $whole = ltrim($parts[2], '0');
            $whole = $whole === '' ? '0' : $whole;
            $whole = preg_replace('/\B(?=(\d{3})+(?!\d))/', $thousandsSeparator, $whole) ?? $whole;
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

        $symbols = is_array($c['symbols'] ?? null) ? $c['symbols'] : [];
        $symbol = (string) ($symbols[Translator::locale()] ?? $c['symbol'] ?? 'DT');
        $space = "\u{00A0}";

        return ($c['symbol_after'] ?? true)
            ? $formatted . $space . $symbol
            : $symbol . $space . $formatted;
    }
}
