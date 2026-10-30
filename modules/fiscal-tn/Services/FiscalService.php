<?php
declare(strict_types=1);

namespace Modules\FiscalTn\Services;

use DateTimeImmutable;

/**
 * Règles de l’échéancier fiscal : dates, états, montants et export.
 *
 * Le module n’impose **aucune périodicité, aucun taux et aucun délai légal** : le cabinet fixe
 * lui-même la période et l’échéance de chaque obligation, qui doivent être validées par son
 * expert-comptable. Les montants sont manipulés en millimes entiers, jamais en flottants.
 */
final class FiscalService
{
    /** Nature de l’obligation. */
    public const KINDS = ['vat', 'withholding', 'tcl', 'other'];

    /** États d’une déclaration, dans l’ordre d’avancement. */
    public const STATUSES = ['to_prepare', 'filed', 'paid'];

    /** Millimes dans un dinar. */
    public const SCALE = 1000;

    /** Fenêtre d’alerte par défaut, en jours — valeur d’affichage, pas une règle légale. */
    public const HORIZON_DEFAULT = 15;

    /** Fenêtre d’alerte maximale, en jours. */
    public const HORIZON_MAX = 120;

    /** Vérifie une date « AAAA-MM-JJ » et la renvoie, ou null. */
    public static function date(mixed $value): ?string
    {
        $text = trim(self::string($value));
        $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $text);
        if ($parsed === false || $parsed->format('Y-m-d') !== $text) {
            return null;
        }

        return $text;
    }

    /** Lit un montant positif ou nul et renvoie des millimes, ou null si la saisie est invalide. */
    public static function millimes(mixed $value): ?int
    {
        $text = str_replace([' ', "\u{00A0}", "\u{202F}", ','], ['', '', '', '.'], trim(self::string($value)));
        if (preg_match('/^\d{1,12}(?:\.\d{1,3})?$/', $text) !== 1) {
            return null;
        }
        [$whole, $fraction] = array_pad(explode('.', $text, 2), 2, '');

        return (int) ltrim($whole, '0') * self::SCALE + (int) str_pad($fraction, 3, '0');
    }

    /** Écrit des millimes au format attendu par la base : « 1234.500 ». */
    public static function format(int $millimes): string
    {
        $sign = $millimes < 0 ? '-' : '';
        $absolute = abs($millimes);

        return $sign . intdiv($absolute, self::SCALE) . '.' . str_pad((string) ($absolute % self::SCALE), 3, '0', STR_PAD_LEFT);
    }

    /** Montant prêt à l’écran, ou « — » lorsqu’il n’est pas saisi. */
    public static function label(?int $millimes): string
    {
        return $millimes === null ? '—' : money_format_tnd(self::format($millimes));
    }

    /** Jours restants avant l’échéance (négatifs une fois la date passée). */
    public static function daysLeft(?string $dueOn, string $today): ?int
    {
        if ($dueOn === null || $dueOn === '') {
            return null;
        }
        $due = DateTimeImmutable::createFromFormat('!Y-m-d', $dueOn);
        $now = DateTimeImmutable::createFromFormat('!Y-m-d', $today);
        if ($due === false || $now === false) {
            return null;
        }
        $days = (int) $now->diff($due)->days;

        return $due < $now ? -$days : $days;
    }

    /** L’obligation est-elle en retard ? (échéance passée et déclaration non payée) */
    public static function isLate(?string $dueOn, string $status, string $today): bool
    {
        return $status !== 'paid' && $dueOn !== null && $dueOn !== '' && $dueOn < $today;
    }

    /** État affiché : l’état saisi, complété par « en retard » lorsque l’échéance est passée. */
    public static function displayStatus(?string $dueOn, string $status, string $today): string
    {
        return self::isLate($dueOn, $status, $today) ? 'late' : $status;
    }

    /** Fenêtre d’alerte saisie à l’écran, bornée entre 0 et 120 jours. */
    public static function horizon(mixed $raw): int
    {
        $text = trim(self::string($raw));
        if ($text === '' || !ctype_digit($text)) {
            return self::HORIZON_DEFAULT;
        }

        return max(0, min(self::HORIZON_MAX, (int) $text));
    }

    /**
     * Valide une obligation fiscale. Aucune périodicité n’est imposée : les trois dates sont
     * libres, seule leur cohérence est contrôlée.
     *
     * @param array<string,mixed> $input
     * @return array{values:array<string,mixed>,errors:array<string,string>}
     */
    public function validateEcheance(array $input): array
    {
        $kind = self::string($input['kind'] ?? 'vat');
        $values = [
            'kind' => in_array($kind, self::KINDS, true) ? $kind : 'vat',
            'label' => trim(self::string($input['label'] ?? '')),
            'period_start' => self::date($input['period_start'] ?? null),
            'period_end' => self::date($input['period_end'] ?? null),
            'due_on' => self::date($input['due_on'] ?? null),
            'note' => trim(self::string($input['note'] ?? '')),
        ];
        $errors = [];

        if ($values['label'] === '') {
            $errors['label'] = __('fiscal.invalid_label');
        } elseif (mb_strlen($values['label'], 'UTF-8') > 150) {
            $errors['label'] = __('fiscal.invalid_label_length');
        }
        if ($values['period_start'] === null) {
            $errors['period_start'] = __('fiscal.invalid_period_start');
        }
        if ($values['period_end'] === null) {
            $errors['period_end'] = __('fiscal.invalid_period_end');
        }
        if ($values['period_start'] !== null && $values['period_end'] !== null && $values['period_end'] < $values['period_start']) {
            $errors['period_end'] = __('fiscal.invalid_period_range');
        }
        if ($values['due_on'] === null) {
            $errors['due_on'] = __('fiscal.invalid_due_on');
        }
        if (mb_strlen($values['note'], 'UTF-8') > 500) {
            $errors['note'] = __('fiscal.invalid_note');
        }

        return ['values' => $values, 'errors' => $errors];
    }

    /**
     * Valide l’enregistrement d’une déclaration (état, montant, dates de dépôt et de paiement).
     *
     * @param array<string,mixed> $input
     * @param array<int,int>      $echeanceIds
     * @return array{values:array<string,mixed>,errors:array<string,string>}
     */
    public function validateDeclaration(array $input, array $echeanceIds): array
    {
        $today = date('Y-m-d');
        $status = self::string($input['status'] ?? 'to_prepare');
        $status = in_array($status, self::STATUSES, true) ? $status : 'to_prepare';
        $echeanceId = self::string($input['echeance_id'] ?? '');
        $amountRaw = trim(self::string($input['amount'] ?? ''));
        $amount = $amountRaw === '' ? null : self::millimes($amountRaw);
        $filedOn = self::date($input['filed_on'] ?? null);
        $paidOn = self::date($input['paid_on'] ?? null);

        $values = [
            'echeance_id' => null,
            'status' => $status,
            'amount' => $amount === null ? null : self::format($amount),
            'filed_on' => $filedOn,
            'paid_on' => $paidOn,
            'reference' => trim(self::string($input['reference'] ?? '')),
            'note' => trim(self::string($input['note'] ?? '')),
        ];
        $errors = [];

        if (!ctype_digit($echeanceId) || !in_array((int) $echeanceId, $echeanceIds, true)) {
            $errors['echeance_id'] = __('fiscal.invalid_echeance');
        } else {
            $values['echeance_id'] = (int) $echeanceId;
        }
        if ($amountRaw !== '' && $amount === null) {
            $errors['amount'] = __('fiscal.invalid_amount');
        }
        // Une déclaration déposée ou payée est datée du jour où l’état est enregistré.
        if ($status !== 'to_prepare' && $values['filed_on'] === null) {
            $values['filed_on'] = $today;
        }
        if ($status !== 'paid') {
            $values['paid_on'] = null;
        } elseif ($values['paid_on'] === null) {
            $values['paid_on'] = $today;
        }
        if ($values['paid_on'] !== null && $values['filed_on'] !== null && $values['paid_on'] < $values['filed_on']) {
            $errors['paid_on'] = __('fiscal.invalid_paid_before_filed');
        }
        if (mb_strlen($values['reference'], 'UTF-8') > 120) {
            $errors['reference'] = __('fiscal.invalid_reference');
        }
        if (mb_strlen($values['note'], 'UTF-8') > 500) {
            $errors['note'] = __('fiscal.invalid_note');
        }

        return ['values' => $values, 'errors' => $errors];
    }

    /**
     * Compteurs et montants d’une liste d’obligations.
     *
     * @param array<int,array<string,mixed>> $rows
     * @return array<string,int>
     */
    public function summary(array $rows, string $today): array
    {
        $summary = [
            'total' => count($rows),
            'to_prepare' => 0,
            'filed' => 0,
            'paid' => 0,
            'late' => 0,
            'amount_filed' => 0,
            'amount_paid' => 0,
        ];
        foreach ($rows as $row) {
            $status = (string) ($row['status'] ?? 'to_prepare');
            if (isset($summary[$status])) {
                $summary[$status]++;
            }
            if (self::isLate(isset($row['due_on']) ? (string) $row['due_on'] : null, $status, $today)) {
                $summary['late']++;
            }
            $amount = self::millimes($row['amount'] ?? '');
            if ($amount === null) {
                continue;
            }
            if ($status === 'filed' || $status === 'paid') {
                $summary['amount_filed'] += $amount;
            }
            if ($status === 'paid') {
                $summary['amount_paid'] += $amount;
            }
        }

        return $summary;
    }

    /**
     * Récapitulatif par période déclarée (une ligne par couple début/fin).
     *
     * @param array<int,array<string,mixed>> $rows
     * @return array<int,array<string,mixed>>
     */
    public function byPeriod(array $rows, string $today): array
    {
        $periods = [];
        foreach ($rows as $row) {
            $key = (string) ($row['period_start'] ?? '') . '|' . (string) ($row['period_end'] ?? '');
            if (!isset($periods[$key])) {
                $periods[$key] = [
                    'start' => (string) ($row['period_start'] ?? ''),
                    'end' => (string) ($row['period_end'] ?? ''),
                    'obligations' => 0,
                    'paid' => 0,
                    'late' => 0,
                    'amount' => 0,
                    'due_on' => (string) ($row['due_on'] ?? ''),
                ];
            }
            $status = (string) ($row['status'] ?? 'to_prepare');
            $periods[$key]['obligations']++;
            if ($status === 'paid') {
                $periods[$key]['paid']++;
            }
            if (self::isLate(isset($row['due_on']) ? (string) $row['due_on'] : null, $status, $today)) {
                $periods[$key]['late']++;
            }
            $periods[$key]['amount'] += self::millimes($row['amount'] ?? '') ?? 0;
            if ((string) ($row['due_on'] ?? '') < $periods[$key]['due_on']) {
                $periods[$key]['due_on'] = (string) $row['due_on'];
            }
        }
        ksort($periods);

        return array_values($periods);
    }

    /**
     * CSV UTF-8 (BOM), séparateur point-virgule, formules de tableur neutralisées.
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

    private static function string(mixed $value): string
    {
        return is_string($value) || is_int($value) ? trim((string) $value) : '';
    }
}
