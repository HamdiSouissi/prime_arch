<?php
declare(strict_types=1);

namespace Tests;

use App\Core\Translator;
use PHPUnit\Framework\TestCase;

/**
 * Garantit que le français et l'arabe exposent exactement les mêmes clés,
 * sans valeur vide, et que la direction du document suit la langue.
 */
final class TranslationParityTest extends TestCase
{
    public function testFrenchAndArabicFilesShareTheSameKeys(): void
    {
        $french = require BASE_PATH . '/lang/fr.php';
        $arabic = require BASE_PATH . '/lang/ar.php';

        self::assertIsArray($french);
        self::assertIsArray($arabic);
        self::assertNotSame([], $french);

        $frenchKeys = array_keys($french);
        $arabicKeys = array_keys($arabic);
        sort($frenchKeys);
        sort($arabicKeys);

        self::assertSame($frenchKeys, $arabicKeys, 'Les deux langues doivent exposer les mêmes clés.');
    }

    public function testNoTranslationIsEmpty(): void
    {
        foreach (['fr', 'ar'] as $locale) {
            $messages = require BASE_PATH . '/lang/' . $locale . '.php';
            foreach ($messages as $key => $value) {
                self::assertIsString($value, "La clé {$key} ({$locale}) doit être une chaîne.");
                self::assertNotSame('', trim($value), "La clé {$key} ({$locale}) est vide.");
            }
        }
    }

    public function testArabicUsesRightToLeftDirection(): void
    {
        self::assertSame('rtl', Translator::directionOf('ar'));
        self::assertSame('ltr', Translator::directionOf('fr'));

        Translator::setLocale('ar');
        self::assertSame('ar', Translator::locale());
        self::assertSame('rtl', Translator::direction());

        Translator::setLocale('fr');
        self::assertSame('ltr', Translator::direction());
    }

    public function testUnknownLocaleFallsBackToFrench(): void
    {
        Translator::setLocale('es');
        self::assertSame('fr', Translator::locale());
    }
}
