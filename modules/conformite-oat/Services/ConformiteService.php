<?php
declare(strict_types=1);

namespace Modules\ConformiteOat\Services;

use DateTimeImmutable;

/**
 * Règles du dossier professionnel : échéances, montants et validations.
 *
 * Le module n’impose **aucune durée de validité légale** : seules les dates saisies par le
 * cabinet déterminent l’état d’une pièce. L’horizon d’alerte est un paramètre d’écran.
 * Les montants sont manipulés en millimes entiers et les durées en centièmes d’heure :
 * aucun flottant n’intervient dans les calculs.
 */
final class ConformiteService
{
    /** Types de pièces administratives suivies. */
    public const KINDS = ['registration', 'card', 'authorization', 'other'];

    /** États possibles d’une pièce, calculés à partir de sa date d’échéance. */
    public const STATUSES = ['valid', 'expiring', 'expired', 'none'];

    /** Horizon d’alerte par défaut, en jours — valeur d’affichage, pas une règle légale. */
    public const HORIZON_DEFAULT = 60;

    /** Horizon maximal accepté à l’écran. */
    public const HORIZON_MAX = 365;

    /** Millimes dans un dinar. */
    public const SCALE = 1000;

    /** Centièmes d’heure dans une heure. */
    public const HOUR_SCALE = 100;

    /** Durée maximale d’une formation, en centièmes d’heure (200 h). */
    public const MAX_FORMATION_HOURS = 20000;

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

    /**
     * État d’une pièce d’après sa date d’échéance.
     *
     * `none` : aucune échéance saisie (pièce sans terme connu) ; `expired` : date dépassée ;
     * `expiring` : échéance dans l’horizon d’alerte ; `valid` sinon.
     */
    public static function status(?string $expiresOn, string $today, int $horizon): string
    {
        if ($expiresOn === null || $expiresOn === '') {
            return 'none';
        }
        if ($expiresOn < $today) {
            return 'expired';
        }
        $limit = (new DateTimeImmutable($today))->modify('+' . max(0, $horizon) . ' days')->format('Y-m-d');

        return $expiresOn <= $limit ? 'expiring' : 'valid';
    }

    /** Jours restants avant l’échéance (négatifs une fois la date dépassée), ou null. */
    public static function daysLeft(?string $expiresOn, string $today): ?int
    {
        if ($expiresOn === null || $expiresOn === '') {
            return null;
        }
        $expiry = DateTimeImmutable::createFromFormat('!Y-m-d', $expiresOn);
        $now = DateTimeImmutable::createFromFormat('!Y-m-d', $today);
        if ($expiry === false || $now === false) {
            return null;
        }
        $days = (int) $now->diff($expiry)->days;

        return $expiry < $now ? -$days : $days;
    }

    /** Horizon d’alerte saisi à l’écran, borné entre 0 et 365 jours. */
    public static function horizon(mixed $raw): int
    {
        $text = trim(self::string($raw));
        if ($text === '' || !ctype_digit($text)) {
            return self::HORIZON_DEFAULT;
        }

        return max(0, min(self::HORIZON_MAX, (int) $text));
    }

    /** Lit un montant (« 150 000,500 ») et renvoie des millimes, ou null si vide/invalide. */
    public static function amount(mixed $value): ?int
    {
        $text = str_replace([' ', "\u{00A0}", "\u{202F}"], '', trim(self::string($value)));
        $text = str_replace(',', '.', $text);
        if (preg_match('/^\d{1,12}(?:\.\d{1,3})?$/', $text) !== 1) {
            return null;
        }
        [$whole, $fraction] = array_pad(explode('.', $text, 2), 2, '');

        return (int) ltrim($whole, '0') * self::SCALE + (int) str_pad($fraction, 3, '0');
    }

    /** Écrit des millimes sous la forme attendue par la base : « 150000.000 ». */
    public static function amountFormat(int $millimes): string
    {
        $sign = $millimes < 0 ? '-' : '';
        $absolute = abs($millimes);

        return $sign . intdiv($absolute, self::SCALE) . '.' . str_pad((string) ($absolute % self::SCALE), 3, '0', STR_PAD_LEFT);
    }

    /** Lit des heures de formation (« 14 », « 14,5 ») et renvoie des centièmes, ou null. */
    public static function hours(mixed $value): ?int
    {
        $text = str_replace([' ', "\u{00A0}", ',']  , ['', '', '.'], trim(self::string($value)));
        if (preg_match('/^\d{1,3}(?:\.\d{1,2})?$/', $text) !== 1) {
            return null;
        }
        [$whole, $fraction] = array_pad(explode('.', $text, 2), 2, '');

        return (int) ltrim($whole, '0') * self::HOUR_SCALE + (int) str_pad($fraction, 2, '0');
    }

    /** Écrit des centièmes d’heure sous la forme attendue par la base : « 14.50 ». */
    public static function hoursFormat(int $centi): string
    {
        return intdiv($centi, self::HOUR_SCALE) . '.' . str_pad((string) ($centi % self::HOUR_SCALE), 2, '0', STR_PAD_LEFT);
    }

    /** Écrit des centièmes d’heure pour l’écran : « 14 h », « 14,5 h ». */
    public static function hoursLabel(?int $centi, string $unit = 'h'): string
    {
        if ($centi === null) {
            return '—';
        }
        $fraction = $centi % self::HOUR_SCALE;
        $whole = intdiv($centi, self::HOUR_SCALE);
        if ($fraction === 0) {
            return $whole . ' ' . $unit;
        }

        return $whole . ',' . rtrim(str_pad((string) $fraction, 2, '0', STR_PAD_LEFT), '0') . ' ' . $unit;
    }

    /**
     * Valide une inscription ou une carte professionnelle.
     *
     * @param array<string,mixed> $input
     * @return array{values:array<string,mixed>,errors:array<string,string>}
     */
    public function validateInscription(array $input): array
    {
        $kind = self::string($input['kind'] ?? 'registration');
        $values = [
            'kind' => in_array($kind, self::KINDS, true) ? $kind : 'registration',
            'label' => trim(self::string($input['label'] ?? '')),
            'reference' => trim(self::string($input['reference'] ?? '')),
            'issued_on' => self::date($input['issued_on'] ?? null),
            'expires_on' => self::date($input['expires_on'] ?? null),
            'note' => trim(self::string($input['note'] ?? '')),
        ];
        $errors = [];

        if ($values['label'] === '') {
            $errors['label'] = __('conformite.invalid_label');
        } elseif (mb_strlen($values['label'], 'UTF-8') > 150) {
            $errors['label'] = __('conformite.invalid_label_length');
        }
        if (mb_strlen($values['reference'], 'UTF-8') > 120) {
            $errors['reference'] = __('conformite.invalid_reference');
        }
        if (trim(self::string($input['issued_on'] ?? '')) !== '' && $values['issued_on'] === null) {
            $errors['issued_on'] = __('conformite.invalid_issued_on');
        }
        if (trim(self::string($input['expires_on'] ?? '')) !== '' && $values['expires_on'] === null) {
            $errors['expires_on'] = __('conformite.invalid_expiry');
        }
        if ($values['issued_on'] !== null && $values['expires_on'] !== null && $values['expires_on'] < $values['issued_on']) {
            $errors['expires_on'] = __('conformite.invalid_range');
        }
        if (mb_strlen($values['note'], 'UTF-8') > 500) {
            $errors['note'] = __('conformite.invalid_note');
        }

        return ['values' => $values, 'errors' => $errors];
    }

    /**
     * Valide une police d’assurance (responsabilité civile professionnelle).
     *
     * @param array<string,mixed> $input
     * @return array{values:array<string,mixed>,errors:array<string,string>}
     */
    public function validateAssurance(array $input): array
    {
        $amountRaw = trim(self::string($input['coverage_amount'] ?? ''));
        $amount = $amountRaw === '' ? null : self::amount($amountRaw);
        $values = [
            'insurer' => trim(self::string($input['insurer'] ?? '')),
            'policy_number' => trim(self::string($input['policy_number'] ?? '')),
            'coverage_amount' => $amount === null ? null : self::amountFormat($amount),
            'starts_on' => self::date($input['starts_on'] ?? null),
            'ends_on' => self::date($input['ends_on'] ?? null),
            'note' => trim(self::string($input['note'] ?? '')),
        ];
        $errors = [];

        if ($values['insurer'] === '') {
            $errors['insurer'] = __('conformite.invalid_insurer');
        } elseif (mb_strlen($values['insurer'], 'UTF-8') > 150) {
            $errors['insurer'] = __('conformite.invalid_insurer_length');
        }
        if (mb_strlen($values['policy_number'], 'UTF-8') > 120) {
            $errors['policy_number'] = __('conformite.invalid_policy');
        }
        if ($amountRaw !== '' && $amount === null) {
            $errors['coverage_amount'] = __('conformite.invalid_amount');
        }
        if (trim(self::string($input['starts_on'] ?? '')) !== '' && $values['starts_on'] === null) {
            $errors['starts_on'] = __('conformite.invalid_issued_on');
        }
        if ($values['ends_on'] === null) {
            $errors['ends_on'] = __('conformite.invalid_expiry');
        }
        if ($values['starts_on'] !== null && $values['ends_on'] !== null && $values['ends_on'] < $values['starts_on']) {
            $errors['ends_on'] = __('conformite.invalid_range');
        }
        if (mb_strlen($values['note'], 'UTF-8') > 500) {
            $errors['note'] = __('conformite.invalid_note');
        }

        return ['values' => $values, 'errors' => $errors];
    }

    /**
     * Valide une formation continue.
     *
     * @param array<string,mixed> $input
     * @return array{values:array<string,mixed>,errors:array<string,string>}
     */
    public function validateFormation(array $input): array
    {
        $hoursRaw = trim(self::string($input['hours'] ?? ''));
        $hours = $hoursRaw === '' ? null : self::hours($hoursRaw);
        if ($hours !== null && $hours > self::MAX_FORMATION_HOURS) {
            $hours = null;
        }
        $values = [
            'title' => trim(self::string($input['title'] ?? '')),
            'provider' => trim(self::string($input['provider'] ?? '')),
            'hours' => $hours === null ? null : self::hoursFormat($hours),
            'completed_on' => self::date($input['completed_on'] ?? null),
            'expires_on' => self::date($input['expires_on'] ?? null),
            'certificate_reference' => trim(self::string($input['certificate_reference'] ?? '')),
            'note' => trim(self::string($input['note'] ?? '')),
        ];
        $errors = [];

        if ($values['title'] === '') {
            $errors['title'] = __('conformite.invalid_title');
        } elseif (mb_strlen($values['title'], 'UTF-8') > 180) {
            $errors['title'] = __('conformite.invalid_title_length');
        }
        if (mb_strlen($values['provider'], 'UTF-8') > 150) {
            $errors['provider'] = __('conformite.invalid_provider');
        }
        if ($hoursRaw !== '' && $hours === null) {
            $errors['hours'] = __('conformite.invalid_hours');
        }
        if ($values['completed_on'] === null) {
            $errors['completed_on'] = __('conformite.invalid_completed_on');
        }
        if (trim(self::string($input['expires_on'] ?? '')) !== '' && $values['expires_on'] === null) {
            $errors['expires_on'] = __('conformite.invalid_expiry');
        }
        if ($values['completed_on'] !== null && $values['expires_on'] !== null && $values['expires_on'] < $values['completed_on']) {
            $errors['expires_on'] = __('conformite.invalid_range');
        }
        if (mb_strlen($values['certificate_reference'], 'UTF-8') > 120) {
            $errors['certificate_reference'] = __('conformite.invalid_reference');
        }
        if (mb_strlen($values['note'], 'UTF-8') > 500) {
            $errors['note'] = __('conformite.invalid_note');
        }

        return ['values' => $values, 'errors' => $errors];
    }

    /**
     * Valide un bureau d’études partenaire.
     *
     * @param array<string,mixed> $input
     * @return array{values:array<string,mixed>,errors:array<string,string>}
     */
    public function validatePartenaire(array $input): array
    {
        $values = [
            'name' => trim(self::string($input['name'] ?? '')),
            'discipline' => trim(self::string($input['discipline'] ?? '')),
            'contact_name' => trim(self::string($input['contact_name'] ?? '')),
            'phone' => trim(self::string($input['phone'] ?? '')),
            'email' => trim(self::string($input['email'] ?? '')),
            'agreement_reference' => trim(self::string($input['agreement_reference'] ?? '')),
            'is_active' => (string) ($input['is_active'] ?? '1') === '1' ? 1 : 0,
            'note' => trim(self::string($input['note'] ?? '')),
        ];
        $errors = [];

        if ($values['name'] === '') {
            $errors['name'] = __('conformite.invalid_name');
        } elseif (mb_strlen($values['name'], 'UTF-8') > 150) {
            $errors['name'] = __('conformite.invalid_name_length');
        }
        foreach (['discipline' => 120, 'contact_name' => 150, 'phone' => 40, 'agreement_reference' => 120] as $field => $limit) {
            if (mb_strlen($values[$field], 'UTF-8') > $limit) {
                $errors[$field] = __('conformite.invalid_contact');
            }
        }
        if ($values['email'] !== '' && filter_var($values['email'], FILTER_VALIDATE_EMAIL) === false) {
            $errors['email'] = __('conformite.invalid_email');
        }
        if (mb_strlen($values['email'], 'UTF-8') > 190) {
            $errors['email'] = __('conformite.invalid_email');
        }
        if (mb_strlen($values['note'], 'UTF-8') > 500) {
            $errors['note'] = __('conformite.invalid_note');
        }

        return ['values' => $values, 'errors' => $errors];
    }

    /**
     * Compte les pièces par état, pour les cartes de synthèse.
     *
     * @param array<int,array<string,mixed>> $rows
     * @return array<string,int>
     */
    public static function summary(array $rows, string $today, int $horizon): array
    {
        $counts = ['total' => count($rows), 'valid' => 0, 'expiring' => 0, 'expired' => 0, 'none' => 0];
        foreach ($rows as $row) {
            $status = self::status(
                isset($row['expires_on']) && $row['expires_on'] !== null ? (string) $row['expires_on'] : null,
                $today,
                $horizon
            );
            $counts[$status]++;
        }

        return $counts;
    }

    private static function string(mixed $value): string
    {
        return is_string($value) || is_int($value) ? trim((string) $value) : '';
    }
}
