<?php
declare(strict_types=1);

namespace Modules\MetreQuantitatif\Services;

/**
 * Arithmétique décimale en millimes, sans flottant.
 *
 * Tous les montants du module sont manipulés comme des entiers de millimes (1 DT = 1000),
 * ce qui garantit des totaux exacts : quantités, prix unitaires, montants de ligne, marges
 * et sous-totaux. Les valeurs sont stockées en base en DECIMAL(15,3).
 */
final class Decimal
{
    /** Trois décimales : un dinar vaut mille millimes. */
    public const SCALE = 1000;

    /** Pourcentage exprimé au millième (100 % = 100000). */
    public const PERCENT_SCALE = 100000;

    /**
     * Lit une saisie utilisateur et renvoie des millimes, ou null si la valeur est invalide.
     *
     * Accepte « 1 234,5 », « 1234.5 » et « 1234 ». Une seule virgule ou un seul point est
     * toléré comme séparateur décimal ; les espaces (dont l’espace insécable) sont ignorés.
     * Les valeurs négatives sont refusées : quantités et prix du DQE sont positifs.
     */
    public static function parse(mixed $value): ?int
    {
        $text = str_replace([' ', "\u{00A0}", "\u{202F}"], '', trim(self::string($value)));
        $text = str_replace(',', '.', $text);
        if (preg_match('/^\d{1,12}(?:\.\d{1,3})?$/', $text) !== 1) {
            return null;
        }
        [$whole, $fraction] = array_pad(explode('.', $text, 2), 2, '');
        $whole = ltrim($whole, '0');
        $whole = $whole === '' ? '0' : $whole;

        return (int) $whole * self::SCALE + (int) str_pad($fraction, 3, '0');
    }

    /** Lit un pourcentage (0 à 100) et renvoie des millièmes de pour cent. */
    public static function parseRate(mixed $value): ?int
    {
        $text = str_replace([' ', "\u{00A0}", "\u{202F}"], '', trim(self::string($value)));
        $text = str_replace(',', '.', $text);
        if (preg_match('/^\d{1,3}(?:\.\d{1,3})?$/', $text) !== 1) {
            return null;
        }
        [$whole, $fraction] = array_pad(explode('.', $text, 2), 2, '');
        $rate = (int) ltrim($whole, '0') * 1000 + (int) str_pad($fraction, 3, '0');

        return $rate <= self::PERCENT_SCALE ? $rate : null;
    }

    /** Écrit des millimes sous la forme attendue par la base : « 1234.500 ». */
    public static function format(int $millimes): string
    {
        $sign = $millimes < 0 ? '-' : '';
        $absolute = abs($millimes);

        return $sign . intdiv($absolute, self::SCALE) . '.' . str_pad((string) ($absolute % self::SCALE), 3, '0', STR_PAD_LEFT);
    }

    /**
     * Quantité prête à l’affichage : trois décimales et le séparateur décimal du pays.
     *
     * Le séparateur suit la convention du cœur (`config/tunisia.php`), qui affiche les
     * montants avec une virgule.
     */
    public static function label(int $millimes): string
    {
        return str_replace('.', ',', self::format($millimes));
    }

    /**
     * Taux prêt à l’affichage : « 12 », « 12,5 » ou « 0,25 ».
     *
     * @param int $rate Taux en millièmes de pour cent (12 % = 12000).
     */
    public static function rateLabel(int $rate): string
    {
        $whole = intdiv($rate, self::SCALE);
        $fraction = $rate % self::SCALE;
        if ($fraction === 0) {
            return (string) $whole;
        }

        return $whole . ',' . rtrim(str_pad((string) $fraction, 3, '0', STR_PAD_LEFT), '0');
    }

    /** Montant prêt à l’affichage (symbole et séparateurs selon la langue active). */
    public static function money(int $millimes): string
    {
        return money_format_tnd(self::format($millimes));
    }

    /**
     * Quantité × prix unitaire, arrondi au millime le plus proche.
     *
     * Les deux opérandes sont des millimes : le produit brut est en millionièmes de dinar.
     * Les opérandes sont décomposés en tranches de 1000 pour qu’aucun produit intermédiaire
     * ne dépasse la capacité d’un entier : seul le résultat final doit y tenir.
     */
    public static function multiply(int $quantity, int $unitPrice): int
    {
        $sign = ($quantity < 0) !== ($unitPrice < 0) ? -1 : 1;
        $absoluteQuantity = abs($quantity);
        $absolutePrice = abs($unitPrice);
        $quantityHigh = intdiv($absoluteQuantity, self::SCALE);
        $quantityLow = $absoluteQuantity % self::SCALE;
        $priceHigh = intdiv($absolutePrice, self::SCALE);
        $priceLow = $absolutePrice % self::SCALE;

        $result = $quantityHigh * $priceHigh * self::SCALE
            + $quantityHigh * $priceLow
            + $quantityLow * $priceHigh
            + self::divideRounded($quantityLow * $priceLow, self::SCALE);

        return $sign * $result;
    }

    /**
     * Pourcentage d’un montant (taux en millièmes de pour cent), arrondi au millime.
     *
     * Le calcul réutilise les tranches de multiply() : le montant est d’abord multiplié par
     * le taux (millièmes de pour cent), puis divisé par cent. L’arrondi final reste au
     * millime près.
     */
    public static function percentOf(int $amount, int $rate): int
    {
        return self::divideRounded(self::multiply($amount, $rate), self::PERCENT_SCALE / self::SCALE);
    }

    /**
     * Division entière arrondie au plus proche (les moitiés s’éloignent de zéro).
     *
     * @param int $numerator   Dividende, positif.
     * @param int $denominator Diviseur strictement positif.
     */
    public static function divideRounded(int $numerator, int $denominator): int
    {
        if ($denominator <= 0) {
            return 0;
        }
        $quotient = intdiv($numerator, $denominator);
        $remainder = $numerator % $denominator;

        return $remainder * 2 >= $denominator ? $quotient + 1 : $quotient;
    }

    /** Somme de montants en millimes. */
    public static function sum(int ...$millimes): int
    {
        return array_sum($millimes);
    }

    private static function string(mixed $value): string
    {
        return is_string($value) || is_int($value) || is_float($value) ? (string) $value : '';
    }
}
