<?php
declare(strict_types=1);

namespace Modules\PermisBatir\Services;

use DateTimeImmutable;
use Modules\PermisBatir\Models\DossierRepository;

/**
 * Règles métier des dossiers de permis de bâtir.
 *
 * Aucune durée légale n’est imposée : la date d’échéance est saisie par le cabinet
 * (les délais d’instruction varient selon les communes et la réglementation applicable).
 */
final class DossierService
{
    public const STATUSES = ['preparation', 'deposited', 'instruction', 'complement', 'granted', 'refused', 'withdrawn'];

    /** Statuts qui exigent une date de dépôt. */
    public const STATUSES_WITH_DEPOSIT = ['deposited', 'instruction', 'complement', 'granted', 'refused'];

    /** Statuts qui exigent une date de décision. */
    public const STATUSES_WITH_DECISION = ['granted', 'refused'];

    public const EVENT_KINDS = ['deposit', 'complement', 'response', 'visit', 'decision', 'withdrawal', 'note'];

    public function __construct(private ?DossierRepository $dossiers = null)
    {
        $this->dossiers ??= new DossierRepository();
    }

    /** Liste type des pièces déposées ; les libellés sont traduits à l’affichage via leur code. */
    /** @return array<int,array{code:string,label:string,required:bool}> */
    public function checklist(): array
    {
        $codes = [
            ['plan_situation', true],
            ['plan_masse', true],
            ['plan_niveaux', true],
            ['plan_coupes', true],
            ['notice_technique', true],
            ['titre_propriete', true],
            ['certificat_possession', false],
            ['quittance_communale', false],
            ['engagement_architecte', true],
        ];

        $pieces = [];
        foreach ($codes as [$code, $required]) {
            $pieces[] = [
                'code' => $code,
                'label' => __('permis.pieces.' . $code),
                'required' => $required,
            ];
        }

        return $pieces;
    }

    /**
     * Valide la saisie d’un dossier (création ou modification).
     *
     * @param array<string,mixed> $input
     * @return array{values:array<string,mixed>,errors:array<string,string>}
     */
    public function validate(array $input, bool $isUpdate = false): array
    {
        $values = [
            'project_id' => trim(self::string($input['project_id'] ?? '')),
            'commune' => trim(self::string($input['commune'] ?? '')),
            'status' => self::string($input['status'] ?? 'preparation'),
            'deposit_number' => trim(self::string($input['deposit_number'] ?? '')),
            'deposit_date' => trim(self::string($input['deposit_date'] ?? '')),
            'decision_number' => trim(self::string($input['decision_number'] ?? '')),
            'decision_date' => trim(self::string($input['decision_date'] ?? '')),
            'deadline_date' => trim(self::string($input['deadline_date'] ?? '')),
            'notes' => trim(self::string($input['notes'] ?? '')),
        ];
        $errors = [];

        if (!in_array($values['status'], self::STATUSES, true)) {
            $values['status'] = 'preparation';
            $errors['status'] = __('permis.invalid');
        }

        if (!$isUpdate) {
            if (!ctype_digit($values['project_id']) || (int) $values['project_id'] < 1
                || !$this->dossiers->projectExists((int) $values['project_id'])
            ) {
                $errors['project_id'] = __('permis.invalid_project');
            }
        }

        $communeLength = mb_strlen($values['commune'], 'UTF-8');
        if ($communeLength < 2 || $communeLength > 150) {
            $errors['commune'] = __('permis.invalid_commune');
        }
        if (mb_strlen($values['deposit_number'], 'UTF-8') > 60 || mb_strlen($values['decision_number'], 'UTF-8') > 60) {
            $errors['deposit_number'] = __('permis.invalid');
        }
        if (mb_strlen($values['notes'], 'UTF-8') > 4000) {
            $errors['notes'] = __('permis.invalid');
        }

        foreach (['deposit_date', 'decision_date', 'deadline_date'] as $field) {
            if ($values[$field] !== '' && !self::isDate($values[$field])) {
                $errors[$field] = __('permis.invalid_date');
            }
        }
        if (in_array($values['status'], self::STATUSES_WITH_DEPOSIT, true) && $values['deposit_date'] === '') {
            $errors['deposit_date'] = __('permis.deposit_date_required');
        }
        if (in_array($values['status'], self::STATUSES_WITH_DECISION, true) && $values['decision_date'] === '') {
            $errors['decision_date'] = __('permis.decision_date_required');
        }
        if ($values['deposit_date'] !== '' && $values['decision_date'] !== ''
            && $values['decision_date'] < $values['deposit_date']
        ) {
            $errors['decision_date'] = __('permis.decision_before_deposit');
        }
        if ($values['deposit_date'] !== '' && $values['deadline_date'] !== ''
            && $values['deadline_date'] < $values['deposit_date']
        ) {
            $errors['deadline_date'] = __('permis.deadline_before_deposit');
        }

        foreach (['deposit_date', 'decision_date', 'deadline_date'] as $field) {
            $values[$field] = $values[$field] !== '' ? $values[$field] : null;
        }

        return ['values' => $values, 'errors' => $errors];
    }

    /**
     * Valide un événement d’historique.
     *
     * @param array<string,mixed> $input
     * @return array{values:array<string,mixed>,errors:array<string,string>}
     */
    public function validateEvent(array $input): array
    {
        $values = [
            'event_date' => trim(self::string($input['event_date'] ?? '')),
            'kind' => self::string($input['kind'] ?? 'note'),
            'summary' => trim(self::string($input['summary'] ?? '')),
            'details' => trim(self::string($input['details'] ?? '')),
        ];
        $errors = [];

        if ($values['event_date'] === '') {
            $values['event_date'] = date('Y-m-d');
        }
        if (!self::isDate($values['event_date'])) {
            $errors['event_date'] = __('permis.invalid_date');
        }
        if (!in_array($values['kind'], self::EVENT_KINDS, true)) {
            $values['kind'] = 'note';
        }
        $summaryLength = mb_strlen($values['summary'], 'UTF-8');
        if ($summaryLength < 2 || $summaryLength > 180) {
            $errors['summary'] = __('permis.invalid_summary');
        }
        if (mb_strlen($values['details'], 'UTF-8') > 4000) {
            $errors['details'] = __('permis.invalid');
        }

        return ['values' => $values, 'errors' => $errors];
    }

    /** Référence libre au format PB-AAAA-NNN (nouvelle tentative si collision). */
    public function nextReference(?DateTimeImmutable $now = null): string
    {
        $year = (int) ($now ?? new DateTimeImmutable('now'))->format('Y');
        $reference = $this->dossiers->nextReference($year);
        $attempts = 0;
        while ($this->dossiers->referenceExists($reference) && $attempts < 50) {
            $attempts++;
            $sequence = (int) substr($reference, -3) + 1;
            $reference = 'PB-' . $year . '-' . str_pad((string) $sequence, 3, '0', STR_PAD_LEFT);
        }

        return $reference;
    }

    /** Jours restants avant l’échéance (négatif si dépassée), null si aucune échéance. */
    public static function daysLeft(?string $deadline, ?string $today = null): ?int
    {
        if ($deadline === null || $deadline === '') {
            return null;
        }
        try {
            $end = new DateTimeImmutable($deadline);
            $start = new DateTimeImmutable($today ?? date('Y-m-d'));
        } catch (\Throwable) {
            return null;
        }

        return (int) $start->diff($end)->format('%r%a');
    }

    /** Classe de badge associée au statut (aucune couleur inventée hors du design system). */
    public static function badgeVariant(string $status): string
    {
        return match ($status) {
            'deposited', 'instruction' => 'info',
            'complement' => 'warning',
            'granted' => 'success',
            'refused' => 'error',
            default => 'neutral',
        };
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
