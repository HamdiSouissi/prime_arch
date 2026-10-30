<?php
declare(strict_types=1);

namespace Modules\ComptabiliteExport\Services;

use DateTimeImmutable;

/**
 * Mise en forme des journaux comptables.
 *
 * Le module ne calcule aucun taux et ne crée aucune écriture : il lit les documents émis et
 * les règlements du noyau, et se contente de les ordonner et de les totaliser. Les montants
 * sont manipulés en millimes entiers (1 DT = 1000), jamais en flottants.
 */
final class ComptabiliteService
{
    /** Journaux exportables. */
    public const JOURNALS = ['ventes', 'encaissements'];

    /** Millimes dans un dinar. */
    public const SCALE = 1000;

    /** Année minimale acceptée pour la période affichée. */
    public const MIN_YEAR = 2000;

    /** Année maximale acceptée pour la période affichée. */
    public const MAX_YEAR = 2100;

    /** Types de documents qui entrent au journal des ventes. */
    public const SALES_TYPES = ['invoice', 'credit_note'];

    /** Lit un montant DECIMAL de la base et le convertit en millimes. */
    public static function millimes(mixed $value): int
    {
        $text = str_replace([' ', "\u{00A0}", "\u{202F}", ','], ['', '', '', '.'], trim(is_string($value) || is_int($value) ? (string) $value : ''));
        if ($text === '' || $text === '-') {
            return 0;
        }
        $negative = str_starts_with($text, '-');
        if ($negative) {
            $text = substr($text, 1);
        }
        if (preg_match('/^\d{1,15}(?:\.\d{1,3})?$/', $text) !== 1) {
            return 0;
        }
        [$whole, $fraction] = array_pad(explode('.', $text, 2), 2, '');
        $millimes = (int) ltrim($whole, '0') * self::SCALE + (int) str_pad($fraction, 3, '0');

        return $negative ? -$millimes : $millimes;
    }

    /** Écrit des millimes au format de la base : « 1234.500 ». */
    public static function format(int $millimes): string
    {
        $sign = $millimes < 0 ? '-' : '';
        $absolute = abs($millimes);

        return $sign . intdiv($absolute, self::SCALE) . '.' . str_pad((string) ($absolute % self::SCALE), 3, '0', STR_PAD_LEFT);
    }

    /** Montant prêt à l’écran (séparateurs et devise selon la langue active). */
    public static function label(int $millimes): string
    {
        return money_format_tnd(self::format($millimes));
    }

    /** Signe d’un document au journal : les avoirs viennent en déduction des factures. */
    public static function sign(string $documentType): int
    {
        return $documentType === 'credit_note' ? -1 : 1;
    }

    /** Année de la période, bornée et repliée sur l’année courante si la valeur est invalide. */
    public static function year(mixed $raw): int
    {
        $text = trim(is_string($raw) || is_int($raw) ? (string) $raw : '');
        $current = (int) date('Y');
        if (!ctype_digit($text)) {
            return max(self::MIN_YEAR, min(self::MAX_YEAR, $current));
        }
        $year = (int) $text;

        return $year >= self::MIN_YEAR && $year <= self::MAX_YEAR ? $year : max(self::MIN_YEAR, min(self::MAX_YEAR, $current));
    }

    /** Mois de la période, entre 1 et 12 ; mois courant par défaut. */
    public static function month(mixed $raw): int
    {
        $text = trim(is_string($raw) || is_int($raw) ? (string) $raw : '');
        if (!ctype_digit($text)) {
            return (int) date('n');
        }
        $month = (int) $text;

        return $month >= 1 && $month <= 12 ? $month : (int) date('n');
    }

    /**
     * Bornes du mois comptable, au format « AAAA-MM-JJ ».
     *
     * @return array{start:string,end:string,key:string}
     */
    public static function period(int $year, int $month): array
    {
        $first = new DateTimeImmutable(sprintf('%04d-%02d-01', $year, $month));

        return [
            'start' => $first->format('Y-m-d'),
            'end' => $first->modify('last day of this month')->format('Y-m-d'),
            'key' => $first->format('Y-m'),
        ];
    }

    /**
     * Totaux d’un journal des ventes, avoirs déduits.
     *
     * @param array<int,array<string,mixed>> $rows
     * @return array<string,int>
     */
    public function salesTotals(array $rows): array
    {
        $totals = ['base' => 0, 'tax' => 0, 'stamp' => 0, 'withholding' => 0, 'total' => 0, 'documents' => 0];
        foreach ($rows as $row) {
            $sign = self::sign((string) $row['document_type']);
            $totals['base'] += $sign * self::millimes($row['subtotal'] ?? '0');
            $totals['tax'] += $sign * self::millimes($row['tax_amount'] ?? '0');
            $totals['stamp'] += $sign * self::millimes($row['stamp_amount'] ?? '0');
            $totals['withholding'] += $sign * self::millimes($row['withholding_amount'] ?? '0');
            $totals['total'] += $sign * self::millimes($row['total_amount'] ?? '0');
            $totals['documents']++;
        }

        return $totals;
    }

    /**
     * Total des encaissements d’une période.
     *
     * @param array<int,array<string,mixed>> $rows
     */
    public function paymentsTotal(array $rows): int
    {
        $total = 0;
        foreach ($rows as $row) {
            $total += self::millimes($row['amount'] ?? '0');
        }

        return $total;
    }

    /**
     * Ventilation de la TVA par taux, avoirs déduits.
     *
     * @param array<int,array<string,mixed>> $rows
     * @return array<int,array{rate:string,base:int,tax:int}>
     */
    public function vatBreakdown(array $rows): array
    {
        $buckets = [];
        foreach ($rows as $row) {
            $rate = (string) ($row['tax_rate'] ?? '0.000');
            $sign = self::sign((string) $row['document_type']);
            $key = $rate;
            if (!isset($buckets[$key])) {
                $buckets[$key] = ['rate' => $rate, 'base' => 0, 'tax' => 0];
            }
            $buckets[$key]['base'] += $sign * self::millimes($row['subtotal'] ?? '0');
            $buckets[$key]['tax'] += $sign * self::millimes($row['tax_amount'] ?? '0');
        }
        ksort($buckets);

        return array_values($buckets);
    }

    /**
     * CSV UTF-8 avec BOM, séparateur point-virgule, montants à trois décimales et point décimal.
     *
     * Le point décimal et l’absence de séparateur de milliers permettent l’import direct dans
     * les logiciels de comptabilité ; les formules de tableur sont neutralisées.
     *
     * @param array<int,array<int,string>> $rows
     */
    public function csv(array $rows): string
    {
        $stream = fopen('php://temp/maxmemory:2097152', 'w+b');
        if ($stream === false) {
            return '';
        }
        fwrite($stream, "\xEF\xBB\xBF");
        foreach ($rows as $row) {
            $cells = array_map(static function (string $value): string {
                return $value !== '' && preg_match("/^[\x00-\x20]*[=+@-]/", $value) === 1 ? "'" . $value : $value;
            }, $row);
            fputcsv($stream, $cells, ';', '"', '');
        }
        rewind($stream);
        $contents = stream_get_contents($stream);
        fclose($stream);

        return is_string($contents) ? $contents : '';
    }
}
