<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Traductions (fichiers lang/{locale}.php) avec repli sur le français.
 * Les modules peuvent ajouter leurs propres fichiers via addPath().
 */
final class Translator
{
    private static string $locale = 'fr';
    /** @var array<string,array<string,string>> */
    private static array $messages = [];

    public static function setLocale(string $locale): void
    {
        $supported = (array) Config::get('app.locales', ['fr', 'ar']);
        self::$locale = in_array($locale, $supported, true) ? $locale : 'fr';
        self::addPath(BASE_PATH . '/lang');
    }

    public static function locale(): string
    {
        return self::$locale;
    }

    /** Direction du texte selon la langue : "rtl" pour l'arabe. */
    public static function direction(): string
    {
        return self::$locale === 'ar' ? 'rtl' : 'ltr';
    }

    /**
     * Ajoute un dossier de traductions (core ou module).
     */
    public static function addPath(string $dir): void
    {
        foreach (array_unique([self::$locale, 'fr']) as $locale) {
            $file = rtrim($dir, '/') . "/{$locale}.php";
            if (is_file($file)) {
                self::$messages[$locale] = (self::$messages[$locale] ?? []) + (array) require $file;
            }
        }
    }

    public static function get(string $key, array $replace = []): string
    {
        $text = self::$messages[self::$locale][$key]
            ?? self::$messages['fr'][$key]
            ?? $key;
        foreach ($replace as $name => $value) {
            $text = str_replace(':' . $name, (string) $value, $text);
        }
        return $text;
    }

    // TODO : pluriels, formats de dates localisés, chargement de la langue depuis la session/utilisateur.
}
