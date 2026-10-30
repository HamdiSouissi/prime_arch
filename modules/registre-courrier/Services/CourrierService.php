<?php
declare(strict_types=1);

namespace Modules\RegistreCourrier\Services;

use DateTimeImmutable;
use Modules\RegistreCourrier\Models\CourrierRepository;

/**
 * Règles métier du registre du courrier.
 *
 * Aucun délai réglementaire, aucune numérotation légale et aucun format de référence
 * ne sont imposés : les préfixes, le séparateur et les compteurs sont saisis par le
 * cabinet, et l’échéance de traitement reste une décision interne.
 */
final class CourrierService
{
    /** Sens du courrier : entrant ou sortant. */
    public const SENS = ['in', 'out'];

    /** Étapes de traitement. */
    public const STATUTS = ['todo', 'in_progress', 'done', 'archived'];

    /** Statuts encore ouverts : seuls ceux-là surveillent une échéance. */
    public const OPEN_STATUTS = ['todo', 'in_progress'];

    /** Modes d’acheminement proposés. */
    public const MODES = ['deposit', 'registered', 'email', 'hand', 'other'];

    public function __construct(private ?CourrierRepository $courrier = null)
    {
        $this->courrier ??= new CourrierRepository();
    }

    /**
     * Valide la saisie d’un courrier (création ou modification).
     *
     * @param array<string,mixed> $input
     * @return array{values:array<string,mixed>,errors:array<string,string>}
     */
    public function validate(array $input): array
    {
        $values = [
            'sens' => self::text($input['sens'] ?? 'in'),
            'date_courrier' => trim(self::text($input['date_courrier'] ?? '')),
            'correspondant' => trim(self::text($input['correspondant'] ?? '')),
            'objet' => trim(self::text($input['objet'] ?? '')),
            'reference' => trim(self::text($input['reference'] ?? '')),
            'mode' => self::text($input['mode'] ?? 'other'),
            'statut' => self::text($input['statut'] ?? 'todo'),
            'project_id' => trim(self::text($input['project_id'] ?? '')),
            'client_id' => trim(self::text($input['client_id'] ?? '')),
            'echeance' => trim(self::text($input['echeance'] ?? '')),
            'notes' => trim(self::text($input['notes'] ?? '')),
        ];
        $errors = [];

        if (!in_array($values['sens'], self::SENS, true)) {
            $values['sens'] = 'in';
            $errors['sens'] = __('courrier.invalid');
        }
        if (!in_array($values['mode'], self::MODES, true)) {
            $values['mode'] = 'other';
            $errors['mode'] = __('courrier.invalid');
        }
        if (!in_array($values['statut'], self::STATUTS, true)) {
            $values['statut'] = 'todo';
            $errors['statut'] = __('courrier.invalid');
        }

        if ($values['date_courrier'] === '') {
            $values['date_courrier'] = date('Y-m-d');
        }
        if (!self::isDate($values['date_courrier'])) {
            $errors['date_courrier'] = __('courrier.invalid_date');
        }

        $correspondantLength = mb_strlen($values['correspondant'], 'UTF-8');
        if ($correspondantLength < 2 || $correspondantLength > 150) {
            $errors['correspondant'] = __('courrier.invalid_correspondant');
        }
        $objetLength = mb_strlen($values['objet'], 'UTF-8');
        if ($objetLength < 2 || $objetLength > 200) {
            $errors['objet'] = __('courrier.invalid_objet');
        }
        if (mb_strlen($values['reference'], 'UTF-8') > 80) {
            $errors['reference'] = __('courrier.invalid_reference');
        }
        if (mb_strlen($values['notes'], 'UTF-8') > 4000) {
            $errors['notes'] = __('courrier.invalid');
        }

        if ($values['project_id'] !== ''
            && (!ctype_digit($values['project_id']) || !$this->courrier->projectExists((int) $values['project_id']))
        ) {
            $values['project_id'] = '';
            $errors['project_id'] = __('courrier.invalid_project');
        }
        if ($values['client_id'] !== ''
            && (!ctype_digit($values['client_id']) || !$this->courrier->clientExists((int) $values['client_id']))
        ) {
            $values['client_id'] = '';
            $errors['client_id'] = __('courrier.invalid_client');
        }

        if ($values['echeance'] !== '') {
            if (!self::isDate($values['echeance'])) {
                $errors['echeance'] = __('courrier.invalid_date');
            } elseif (self::isDate($values['date_courrier']) && $values['echeance'] < $values['date_courrier']) {
                $errors['echeance'] = __('courrier.echeance_before_date');
            }
        }

        // Un courrier rattaché à un projet dont le client est connu garde ce client :
        // la fiche reste lisible même si le rattachement projet est retiré plus tard.
        if ($values['project_id'] !== '' && $values['client_id'] === '') {
            $project = $this->courrier->projectRow((int) $values['project_id']);
            if (is_array($project) && (int) ($project['client_id'] ?? 0) > 0) {
                $values['client_id'] = (string) (int) $project['client_id'];
            }
        }

        foreach (['project_id', 'client_id', 'echeance'] as $field) {
            $values[$field] = $values[$field] !== '' ? $values[$field] : null;
        }

        return ['values' => $values, 'errors' => $errors];
    }

    /**
     * Valide les réglages de numérotation (aucun format n’est imposé par le module).
     *
     * @param array<string,mixed> $input
     * @return array{values:array{prefix_in:string,prefix_out:string,separator:string,digits:int},errors:array<string,string>}
     */
    public function validateSettings(array $input): array
    {
        $values = [
            'prefix_in' => strtoupper(trim(self::text($input['prefix_in'] ?? ''))),
            'prefix_out' => strtoupper(trim(self::text($input['prefix_out'] ?? ''))),
            'separator' => trim(self::text($input['separator'] ?? '')),
            'digits' => (int) trim(self::text($input['digits'] ?? '3')),
        ];
        $errors = [];

        foreach (['prefix_in', 'prefix_out'] as $field) {
            if (preg_match('/^[A-Z0-9]{1,10}$/', $values[$field]) !== 1) {
                $errors[$field] = __('courrier.invalid_prefix');
            }
        }
        if (mb_strlen($values['separator'], 'UTF-8') > 3) {
            $errors['separator'] = __('courrier.invalid_separator');
        }
        if ($values['digits'] < 1 || $values['digits'] > 8) {
            $errors['digits'] = __('courrier.invalid_digits');
        }

        return ['values' => $values, 'errors' => $errors];
    }

    /**
     * Numéro qui serait attribué au prochain courrier de ce sens et de cette année.
     *
     * @param array{prefix_in:string,prefix_out:string,separator:string,digits:int} $settings
     */
    public function nextNumberPreview(array $settings, string $sens, int $year, int $lastSequence): string
    {
        return CourrierRepository::buildNumber($settings, $sens, $year, $lastSequence + 1);
    }

    /** Classe de badge associée au statut (palette du design system uniquement). */
    public static function badgeVariant(string $statut): string
    {
        return match ($statut) {
            'in_progress' => 'info',
            'done' => 'success',
            'archived' => 'neutral',
            default => 'warning',
        };
    }

    /** L’échéance est-elle dépassée pour un courrier encore ouvert ? */
    public static function isOverdue(?string $echeance, string $statut, ?string $today = null): bool
    {
        if ($echeance === null || $echeance === '' || !self::isDate($echeance)) {
            return false;
        }
        if (!in_array($statut, self::OPEN_STATUTS, true)) {
            return false;
        }

        return $echeance < ($today ?? date('Y-m-d'));
    }

    /** Nombre de jours restants avant l’échéance (négatif si dépassée). */
    public static function daysLeft(?string $echeance, ?string $today = null): ?int
    {
        if ($echeance === null || $echeance === '' || !self::isDate($echeance)) {
            return null;
        }
        $end = new DateTimeImmutable($echeance);
        $start = new DateTimeImmutable($today ?? date('Y-m-d'));

        return (int) $start->diff($end)->format('%r%a');
    }

    public static function isDate(string $value): bool
    {
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);

        return $date instanceof DateTimeImmutable && $date->format('Y-m-d') === $value;
    }

    private static function text(mixed $value): string
    {
        return is_string($value) || is_numeric($value) ? (string) $value : '';
    }
}
