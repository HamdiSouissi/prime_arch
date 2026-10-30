<?php
declare(strict_types=1);

namespace Modules\SuiviChantier\Services;

use DateTimeImmutable;

/**
 * Règles métier du suivi de chantier.
 *
 * Le module n’invente aucune obligation contractuelle : il enregistre ce que le cabinet
 * constate (visites, réserves, réceptions) et permet d’en produire un procès-verbal.
 */
final class ChantierService
{
    public const WEATHERS = ['clear', 'cloudy', 'rain', 'wind', 'heat', 'cold', 'other'];

    public const SEVERITIES = ['minor', 'major', 'critical'];

    public const RESERVE_STATUSES = ['open', 'lifted', 'contested'];

    public const RECEPTION_KINDS = ['provisional', 'final'];

    /**
     * @param array<string,mixed> $input
     * @return array{values:array<string,mixed>,errors:array<string,string>}
     */
    public function validateVisit(array $input): array
    {
        $values = [
            'visit_date' => trim(self::string($input['visit_date'] ?? '')),
            'weather' => self::string($input['weather'] ?? 'clear'),
            'workforce' => trim(self::string($input['workforce'] ?? '')),
            'observations' => trim(self::string($input['observations'] ?? '')),
        ];
        $errors = [];

        if ($values['visit_date'] === '') {
            $values['visit_date'] = date('Y-m-d');
        }
        if (!self::isDate($values['visit_date'])) {
            $errors['visit_date'] = __('chantier.invalid_date');
        }
        if (!in_array($values['weather'], self::WEATHERS, true)) {
            $values['weather'] = 'clear';
        }
        $observationsLength = mb_strlen($values['observations'], 'UTF-8');
        if ($observationsLength < 2 || $observationsLength > 4000) {
            $errors['observations'] = __('chantier.invalid_observations');
        }
        if ($values['workforce'] !== '') {
            if (!ctype_digit($values['workforce']) || (int) $values['workforce'] > 9999) {
                $errors['workforce'] = __('chantier.invalid_workforce');
            }
        }

        return ['values' => $values, 'errors' => $errors];
    }

    /**
     * @param array<string,mixed> $input
     * @return array{values:array<string,mixed>,errors:array<string,string>}
     */
    public function validateReserve(array $input): array
    {
        $values = [
            'description' => trim(self::string($input['description'] ?? '')),
            'severity' => self::string($input['severity'] ?? 'minor'),
            'opened_on' => trim(self::string($input['opened_on'] ?? '')),
            'visit_id' => trim(self::string($input['visit_id'] ?? '')),
        ];
        $errors = [];

        $length = mb_strlen($values['description'], 'UTF-8');
        if ($length < 2 || $length > 2000) {
            $errors['description'] = __('chantier.invalid_description');
        }
        if (!in_array($values['severity'], self::SEVERITIES, true)) {
            $values['severity'] = 'minor';
        }
        if ($values['opened_on'] === '') {
            $values['opened_on'] = date('Y-m-d');
        }
        if (!self::isDate($values['opened_on'])) {
            $errors['opened_on'] = __('chantier.invalid_date');
        }
        if ($values['visit_id'] !== '' && !ctype_digit($values['visit_id'])) {
            $values['visit_id'] = '';
        }

        return ['values' => $values, 'errors' => $errors];
    }

    /**
     * @param array<string,mixed> $input
     * @return array{values:array<string,mixed>,errors:array<string,string>}
     */
    public function validateReception(array $input): array
    {
        $values = [
            'kind' => self::string($input['kind'] ?? 'provisional'),
            'reception_date' => trim(self::string($input['reception_date'] ?? '')),
            'notes' => trim(self::string($input['notes'] ?? '')),
        ];
        $errors = [];

        if (!in_array($values['kind'], self::RECEPTION_KINDS, true)) {
            $values['kind'] = 'provisional';
        }
        if ($values['reception_date'] === '') {
            $values['reception_date'] = date('Y-m-d');
        }
        if (!self::isDate($values['reception_date'])) {
            $errors['reception_date'] = __('chantier.invalid_date');
        }
        if (mb_strlen($values['notes'], 'UTF-8') > 4000) {
            $errors['notes'] = __('chantier.invalid_description');
        }

        return ['values' => $values, 'errors' => $errors];
    }

    /** Classe de badge d’une gravité de réserve. */
    public static function severityVariant(string $severity): string
    {
        return match ($severity) {
            'critical' => 'error',
            'major' => 'warning',
            default => 'neutral',
        };
    }

    /** Classe de badge de l’état d’une réserve. */
    public static function reserveVariant(string $status): string
    {
        return match ($status) {
            'lifted' => 'success',
            'contested' => 'warning',
            default => 'info',
        };
    }

    /** Nombre de jours ouverts d’une réserve, null si elle est levée. */
    public static function openDays(string $openedOn, ?string $today = null): int
    {
        try {
            $start = new DateTimeImmutable($openedOn);
            $end = new DateTimeImmutable($today ?? date('Y-m-d'));
        } catch (\Throwable) {
            return 0;
        }

        return max(0, (int) $start->diff($end)->format('%a'));
    }

    private static function isDate(string $value): bool
    {
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);

        return $date instanceof DateTimeImmutable && $date->format('Y-m-d') === $value;
    }

    private static function string(mixed $value): string
    {
        return is_string($value) || is_numeric($value) ? (string) $value : '';
    }
}
