<?php
declare(strict_types=1);

namespace Tests;

use App\Core\Translator;
use PHPUnit\Framework\TestCase;

/**
 * Formatage monétaire bilingue.
 *
 * Un montant arabe affiché « 380,000 2 » venait d'un séparateur de milliers trop faible :
 * l'espace simple (U+0020, classe bidi WS) coupait la suite de chiffres en deux blocs que
 * l'algorithme bidi réordonnait. Le séparateur est désormais une espace insécable (U+00A0,
 * classe bidi CS) : le montant reste un seul bloc et la devise suit la langue active.
 */
final class CurrencyFormatTest extends TestCase
{
    private const NBSP = "\u{00A0}";

    protected function tearDown(): void
    {
        Translator::setLocale('fr');
    }

    public function testFrenchAmountUsesNonBreakingSpacesAndLatinSymbol(): void
    {
        Translator::setLocale('fr');

        self::assertSame('2' . self::NBSP . '380,000' . self::NBSP . 'DT', money_format_tnd('2380.000'));
        self::assertSame('0,000' . self::NBSP . 'DT', money_format_tnd('0.000'));
    }

    public function testArabicAmountUsesTheArabicDinarSymbol(): void
    {
        Translator::setLocale('ar');

        self::assertSame('2' . self::NBSP . '380,000' . self::NBSP . 'د.ت', money_format_tnd('2380.000'));
    }

    public function testAmountsNeverContainAPlainSpaceThatCouldReverseTheDigits(): void
    {
        Translator::setLocale('ar');

        foreach (['2380.000', '1234567.890', '-98765.4', '1250.500'] as $amount) {
            $formatted = money_format_tnd($amount);
            self::assertStringNotContainsString(' ', $formatted, "Le montant {$amount} contient une espace simple.");
        }

        // Le séparateur de milliers est bien une espace insécable, de classe bidi CS.
        self::assertStringContainsString(self::NBSP . '380,000', money_format_tnd('2380.000'));
    }

    public function testDecimalPrecisionIsPreservedWithoutFloatConversion(): void
    {
        Translator::setLocale('fr');

        // 0.1 + 0.2 ou de grands montants ne doivent pas être arrondis par un passage en flottant.
        self::assertSame('9' . self::NBSP . '999' . self::NBSP . '999' . self::NBSP . '999,999' . self::NBSP . 'DT', money_format_tnd('9999999999.999'));
        self::assertSame('1' . self::NBSP . '234,500' . self::NBSP . 'DT', money_format_tnd('1234.5'));
    }

    public function testNegativeAndZeroAmountsStayReadable(): void
    {
        Translator::setLocale('fr');

        self::assertSame('-98' . self::NBSP . '765,400' . self::NBSP . 'DT', money_format_tnd('-98765.4'));
        self::assertSame('0,000' . self::NBSP . 'DT', money_format_tnd(0));

        Translator::setLocale('ar');
        self::assertStringStartsWith('-', money_format_tnd('-98765.4'));
    }

    public function testSymbolFollowsTheActiveLanguageWithoutLeaking(): void
    {
        Translator::setLocale('ar');
        self::assertStringEndsWith('د.ت', money_format_tnd('1000.000'));

        Translator::setLocale('fr');
        self::assertStringEndsWith('DT', money_format_tnd('1000.000'));
    }
}
