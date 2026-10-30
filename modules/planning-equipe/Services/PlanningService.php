<?php
declare(strict_types=1);

namespace Modules\PlanningEquipe\Services;

use DateTimeImmutable;

/**
 * Calculs de charge : jours ouvrés, heures planifiées, congés et surcharge.
 *
 * Les heures sont manipulées en centièmes d’heure (7,50 h = 750) pour éviter les flottants.
 * Aucune capacité de travail n’est imposée : le seuil de surcharge est un paramètre d’écran.
 */
final class PlanningService
{
    /** Centièmes d’heure dans une journée pleine. */
    public const DAY_CENTI = 2400;

    /** Durée maximale d’une affectation ou d’un congé, en jours. */
    public const MAX_SPAN_DAYS = 366;

    /** Types de congé proposés (aucun n’est imposé par un texte). */
    public const KINDS = ['vacation', 'sick', 'training', 'other'];

    /** Périodes affichables. */
    public const SCOPES = ['week', 'month'];

    /** Lit des heures (« 7,5 » ou « 7.50 ») et renvoie des centièmes, ou null si invalide. */
    public static function centi(mixed $value): ?int
    {
        $text = str_replace([' ', "\u{00A0}", ','], ['', '', '.'], trim(is_string($value) || is_int($value) ? (string) $value : ''));
        if (preg_match('/^\d{1,2}(?:\.\d{1,2})?$/', $text) !== 1) {
            return null;
        }
        [$hours, $fraction] = array_pad(explode('.', $text, 2), 2, '');

        return (int) ltrim($hours, '0') * 100 + (int) str_pad($fraction, 2, '0');
    }

    /** Écrit des centièmes d’heure sous la forme « 7.50 ». */
    public static function format(int $centi): string
    {
        return intdiv($centi, 100) . '.' . str_pad((string) ($centi % 100), 2, '0', STR_PAD_LEFT);
    }

    /** Écrit des centièmes d’heure sans unité : « 7,5 », « 12 ». */
    public static function hoursValue(int $centi): string
    {
        $sign = $centi < 0 ? '-' : '';
        $centi = abs($centi);
        $fraction = $centi % 100;
        $whole = intdiv($centi, 100);
        if ($fraction === 0) {
            return $sign . $whole;
        }
        $digits = rtrim(str_pad((string) $fraction, 2, '0', STR_PAD_LEFT), '0');

        return $sign . $whole . ',' . $digits;
    }

    /** Écrit des centièmes d’heure pour l’écran : « 7,5 h », « 12 سا » selon l’unité fournie. */
    public static function hours(int $centi, string $unit = 'h'): string
    {
        $value = self::hoursValue($centi);

        return $unit === '' ? $value : $value . ' ' . $unit;
    }

    /** Vérifie une date « AAAA-MM-JJ » et la renvoie, ou null. */
    public static function date(mixed $value): ?string
    {
        $text = trim(is_string($value) ? $value : '');
        $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $text);
        if ($parsed === false || $parsed->format('Y-m-d') !== $text) {
            return null;
        }

        return $text;
    }

    /** Nombre de jours ouvrés (lundi à vendredi) entre deux dates incluses. */
    public function workingDays(string $start, string $end): int
    {
        $from = DateTimeImmutable::createFromFormat('!Y-m-d', $start);
        $to = DateTimeImmutable::createFromFormat('!Y-m-d', $end);
        if ($from === false || $to === false || $to < $from) {
            return 0;
        }
        $days = 0;
        for ($day = $from; $day <= $to; $day = $day->modify('+1 day')) {
            if ((int) $day->format('N') <= 5) {
                $days++;
            }
        }

        return $days;
    }

    /**
     * Heures planifiées d’une affectation (centièmes) : heures par jour × jours ouvrés.
     *
     * @param array<string,mixed> $affectation
     */
    public function plannedCenti(array $affectation): int
    {
        $daily = self::centi($affectation['daily_hours'] ?? '0') ?? 0;
        $days = $this->workingDays(
            (string) ($affectation['start_date'] ?? ''),
            (string) ($affectation['end_date'] ?? '')
        );

        return $daily * $days;
    }

    /**
     * Bornes d’une période : semaine (lundi → dimanche) ou mois calendaire.
     *
     * @return array{start:string,end:string}
     */
    public function periodBounds(string $start, string $scope): array
    {
        $anchor = DateTimeImmutable::createFromFormat('!Y-m-d', $start);
        if ($anchor === false) {
            $anchor = new DateTimeImmutable('today');
        }
        if ($scope === 'month') {
            $first = $anchor->modify('first day of this month');

            return ['start' => $first->format('Y-m-d'), 'end' => $first->modify('last day of this month')->format('Y-m-d')];
        }
        $monday = $anchor->modify('-' . ((int) $anchor->format('N') - 1) . ' days');

        return ['start' => $monday->format('Y-m-d'), 'end' => $monday->modify('+6 days')->format('Y-m-d')];
    }

    /**
     * Jours de la période, avec leur libellé et leur caractère ouvré.
     *
     * @return array<int,array{date:string,label:string,weekday:int,is_weekend:bool}>
     */
    public function days(string $start, string $end): array
    {
        $from = DateTimeImmutable::createFromFormat('!Y-m-d', $start);
        $to = DateTimeImmutable::createFromFormat('!Y-m-d', $end);
        if ($from === false || $to === false || $to < $from) {
            return [];
        }
        $days = [];
        for ($day = $from; $day <= $to; $day = $day->modify('+1 day')) {
            $weekday = (int) $day->format('N');
            $days[] = [
                'date' => $day->format('Y-m-d'),
                'label' => __('planning.weekday.' . $weekday),
                'weekday' => $weekday,
                'is_weekend' => $weekday >= 6,
            ];
        }

        return $days;
    }

    /** Une journée dépasse-t-elle la capacité retenue ? */
    public function overloaded(int $plannedCenti, int $realizedCenti, int $capacityCenti): bool
    {
        return $capacityCenti > 0 && ($plannedCenti + $realizedCenti) > $capacityCenti;
    }

    /**
     * Valide une affectation prévisionnelle.
     *
     * @param array<string,mixed> $input
     * @param array<int,int>      $userIds
     * @param array<int,int>      $projectIds
     * @return array{values:array<string,mixed>,errors:array<string,string>}
     */
    public function validateAffectation(array $input, array $userIds, array $projectIds): array
    {
        $values = [
            'user_id' => self::positiveId($input['user_id'] ?? null),
            'project_id' => self::positiveId($input['project_id'] ?? null),
            'project_phase_assignment_id' => self::positiveId($input['project_phase_assignment_id'] ?? null),
            'start_date' => self::date($input['start_date'] ?? null),
            'end_date' => self::date($input['end_date'] ?? null),
            'daily_hours' => trim(self::string($input['daily_hours'] ?? '')),
            'note' => trim(self::string($input['note'] ?? '')),
        ];
        $errors = [];

        if ($values['user_id'] === null || !in_array($values['user_id'], $userIds, true)) {
            $errors['user_id'] = __('planning.invalid_user');
        }
        if ($values['project_id'] === null || !in_array($values['project_id'], $projectIds, true)) {
            $errors['project_id'] = __('planning.invalid_project');
        }
        $errors += $this->dateErrors($values['start_date'], $values['end_date']);

        $daily = self::centi($values['daily_hours']);
        if ($daily === null || $daily < 25 || $daily > self::DAY_CENTI) {
            $errors['daily_hours'] = __('planning.invalid_daily_hours');
            $daily = 0;
        }
        $values['daily_hours'] = self::format($daily);

        if (mb_strlen($values['note'], 'UTF-8') > 500) {
            $errors['note'] = __('planning.invalid_note');
        }

        return ['values' => $values, 'errors' => $errors];
    }

    /**
     * Valide un congé.
     *
     * @param array<string,mixed> $input
     * @param array<int,int>      $userIds
     * @return array{values:array<string,mixed>,errors:array<string,string>}
     */
    public function validateLeave(array $input, array $userIds): array
    {
        $kind = self::string($input['kind'] ?? 'vacation');
        $values = [
            'user_id' => self::positiveId($input['user_id'] ?? null),
            'start_date' => self::date($input['start_date'] ?? null),
            'end_date' => self::date($input['end_date'] ?? null),
            'kind' => in_array($kind, self::KINDS, true) ? $kind : 'vacation',
            'note' => trim(self::string($input['note'] ?? '')),
        ];
        $errors = [];

        if ($values['user_id'] === null || !in_array($values['user_id'], $userIds, true)) {
            $errors['user_id'] = __('planning.invalid_user');
        }
        $errors += $this->dateErrors($values['start_date'], $values['end_date']);
        if (mb_strlen($values['note'], 'UTF-8') > 500) {
            $errors['note'] = __('planning.invalid_note');
        }

        return ['values' => $values, 'errors' => $errors];
    }

    /**
     * Erreurs communes de dates (obligatoires, ordonnées, plage bornée).
     *
     * @return array<string,string>
     */
    private function dateErrors(?string $start, ?string $end): array
    {
        $errors = [];
        if ($start === null) {
            $errors['start_date'] = __('planning.invalid_start_date');
        }
        if ($end === null) {
            $errors['end_date'] = __('planning.invalid_end_date');
        }
        if ($start !== null && $end !== null) {
            if ($end < $start) {
                $errors['end_date'] = __('planning.invalid_range');
            } else {
                $span = (new DateTimeImmutable($start))->diff(new DateTimeImmutable($end))->days;
                if ($span !== false && $span > self::MAX_SPAN_DAYS) {
                    $errors['end_date'] = __('planning.range_too_long');
                }
            }
        }

        return $errors;
    }

    private static function positiveId(mixed $value): ?int
    {
        $text = self::string($value);

        return ctype_digit($text) && (int) $text > 0 ? (int) $text : null;
    }

    private static function string(mixed $value): string
    {
        return is_string($value) || is_int($value) ? trim((string) $value) : '';
    }
}
