<?php
declare(strict_types=1);

namespace Modules\HonorairesBareme\Services;

/**
 * Règles de saisie et calcul des honoraires.
 *
 * Aucun barème officiel, aucun taux de l’OAT et aucun pourcentage par défaut ne sont codés ici :
 * le cabinet saisit ses phases, ses tranches et ses taux. Le module se contente de calculer.
 */
final class HonoraireService
{
    /** Plafond de saisie d’un montant : 1 000 000 000,000 DT en millimes. */
    public const MAX_AMOUNT = 1_000_000_000_000;

    /** Taux maximal accepté : 100 % (en millièmes de pour cent). */
    public const MAX_RATE = 100_000;

    /** Statuts d’un décompte, dans l’ordre du cycle de vie. */
    public const STATUSES = ['draft', 'validated', 'invoiced'];

    /**
     * Valide la fiche d’un barème (mission).
     *
     * @param array<string,mixed> $input
     * @return array{values:array<string,mixed>,errors:array<string,string>}
     */
    public function validateMission(array $input): array
    {
        $values = [
            'name' => trim(self::string($input['name'] ?? '')),
            'description' => trim(self::string($input['description'] ?? '')),
            'is_active' => self::string($input['is_active'] ?? '') === '1' ? 1 : 0,
            'sort_order' => self::integer($input['sort_order'] ?? 0, 999, 0),
        ];
        $errors = [];

        $length = mb_strlen($values['name'], 'UTF-8');
        if ($length < 2 || $length > 150) {
            $errors['name'] = __('honoraire.invalid_name');
        }
        if (mb_strlen($values['description'], 'UTF-8') > 2000) {
            $errors['description'] = __('honoraire.invalid_description');
        }

        return ['values' => $values, 'errors' => $errors];
    }

    /**
     * Valide une tranche de barème (montant de travaux et taux pour une phase).
     *
     * Les tranches d’une même phase ne doivent pas se chevaucher : sinon le même dinar de
     * travaux serait facturé deux fois.
     *
     * @param array<string,mixed>                $input
     * @param array<int,array<string,mixed>>     $tranches  Tranches déjà enregistrées du barème.
     * @param array<int,string>                  $phaseKeys Clés de phases autorisées.
     * @return array{values:array<string,mixed>,errors:array<string,string>}
     */
    public function validateTranche(array $input, array $tranches, array $phaseKeys): array
    {
        $values = [
            'phase_key' => self::string($input['phase_key'] ?? ''),
            'min_amount' => trim(self::string($input['min_amount'] ?? '')),
            'max_amount' => trim(self::string($input['max_amount'] ?? '')),
            'rate_percent' => trim(self::string($input['rate_percent'] ?? '')),
            'sort_order' => self::integer($input['sort_order'] ?? 0, 999, 0),
        ];
        $errors = [];

        if (!in_array($values['phase_key'], $phaseKeys, true)) {
            $errors['phase_key'] = __('honoraire.invalid_phase');
        }

        $min = $values['min_amount'] === '' ? 0 : Decimal::parse($values['min_amount']);
        if ($min === null || $min > self::MAX_AMOUNT) {
            $errors['min_amount'] = __('honoraire.invalid_amount');
            $min = 0;
        }
        $max = null;
        if ($values['max_amount'] !== '') {
            $max = Decimal::parse($values['max_amount']);
            if ($max === null || $max > self::MAX_AMOUNT) {
                $errors['max_amount'] = __('honoraire.invalid_amount');
                $max = null;
            } elseif ($max <= $min) {
                $errors['max_amount'] = __('honoraire.invalid_range');
                $max = null;
            }
        }
        $rate = Decimal::parseRate($values['rate_percent']);
        if ($rate === null || $rate > self::MAX_RATE) {
            $errors['rate_percent'] = __('honoraire.invalid_rate');
            $rate = 0;
        }

        $values['min_amount'] = Decimal::format($min);
        $values['max_amount'] = $max === null ? null : Decimal::format($max);
        $values['rate_percent'] = number_format($rate / 1000, 3, '.', '');

        if (!isset($errors['phase_key']) && $this->overlaps($values['phase_key'], $min, $max, $tranches)) {
            $errors['min_amount'] = __('honoraire.overlap');
        }

        return ['values' => $values, 'errors' => $errors];
    }

    /**
     * Valide la création d’un décompte.
     *
     * @param array<string,mixed> $input
     * @return array{values:array<string,mixed>,errors:array<string,string>}
     */
    public function validateDecompte(array $input): array
    {
        $values = [
            'basis_amount' => trim(self::string($input['basis_amount'] ?? '')),
            'notes' => trim(self::string($input['notes'] ?? '')),
        ];
        $errors = [];

        $basis = Decimal::parse($values['basis_amount']);
        if ($basis === null || $basis > self::MAX_AMOUNT) {
            $errors['basis_amount'] = __('honoraire.invalid_basis');
            $basis = 0;
        } elseif ($basis <= 0) {
            $errors['basis_amount'] = __('honoraire.invalid_basis_positive');
            $basis = 0;
        }
        if (mb_strlen($values['notes'], 'UTF-8') > 2000) {
            $errors['notes'] = __('honoraire.invalid_notes');
        }
        $values['basis_amount'] = Decimal::format($basis);
        $values['basis_millimes'] = $basis;

        return ['values' => $values, 'errors' => $errors];
    }

    /**
     * Calcule les honoraires d’une assiette avec les tranches d’un barème.
     *
     * Chaque tranche s’applique à la portion de l’assiette comprise entre ses bornes ; les
     * tranches sont groupées par phase pour produire le détail du décompte.
     *
     * @param array<int,array<string,mixed>> $tranches
     * @param int                            $basis Assiette en millimes.
     * @return array{phases:array<int,array<string,mixed>>,fee:int,fee_amount:string}
     */
    public function compute(array $tranches, int $basis): array
    {
        $basis = max(0, $basis);
        $lines = [];

        foreach ($tranches as $tranche) {
            $phaseKey = (string) ($tranche['phase_key'] ?? '');
            if ($phaseKey === '') {
                continue;
            }
            $min = Decimal::parse($tranche['min_amount'] ?? '0') ?? 0;
            $max = isset($tranche['max_amount']) && $tranche['max_amount'] !== null && $tranche['max_amount'] !== ''
                ? Decimal::parse($tranche['max_amount'])
                : null;
            $rate = Decimal::parseRate($tranche['rate_percent'] ?? '0') ?? 0;

            $upper = $max ?? $basis;
            $portion = max(0, min($basis, $upper) - $min);
            $fee = Decimal::percentOf($portion, $rate);

            $lines[$phaseKey]['phase_key'] = $phaseKey;
            $lines[$phaseKey]['fee'] = (int) ($lines[$phaseKey]['fee'] ?? 0) + $fee;
            $lines[$phaseKey]['basis'] = (int) ($lines[$phaseKey]['basis'] ?? 0) + $portion;
            $lines[$phaseKey]['tranches'][] = [
                'min_amount' => Decimal::format($min),
                'max_amount' => $max === null ? null : Decimal::format($max),
                'rate_percent' => Decimal::rateLabel($rate),
                'portion' => Decimal::format($portion),
                'fee' => Decimal::format($fee),
            ];
        }

        $total = 0;
        $phases = [];
        foreach ($lines as $line) {
            $total += (int) $line['fee'];
            $phases[] = [
                'phase_key' => (string) $line['phase_key'],
                'basis' => Decimal::format((int) $line['basis']),
                'fee' => Decimal::format((int) $line['fee']),
                'tranches' => $line['tranches'],
            ];
        }

        return ['phases' => $phases, 'fee' => $total, 'fee_amount' => Decimal::format($total)];
    }

    /**
     * Relit le détail stocké dans un décompte.
     *
     * @param array<string,mixed> $row
     * @return array<int,array<string,mixed>>
     */
    public function breakdown(array $row): array
    {
        $raw = $row['breakdown'] ?? null;
        if (!is_string($raw) || $raw === '') {
            return [];
        }
        $decoded = json_decode($raw, true);

        return is_array($decoded) ? array_values(array_filter($decoded, 'is_array')) : [];
    }

    /**
     * Somme les honoraires de plusieurs décomptes (millimes).
     *
     * @param array<int,array<string,mixed>> $decomptes
     */
    public function total(array $decomptes): int
    {
        $total = 0;
        foreach ($decomptes as $decompte) {
            $total += Decimal::parse($decompte['fee_amount'] ?? '0') ?? 0;
        }

        return $total;
    }

    /** @param array<int,array<string,mixed>> $tranches */
    private function overlaps(string $phaseKey, int $min, ?int $max, array $tranches): bool
    {
        foreach ($tranches as $tranche) {
            if ((string) ($tranche['phase_key'] ?? '') !== $phaseKey) {
                continue;
            }
            $otherMin = Decimal::parse($tranche['min_amount'] ?? '0') ?? 0;
            $otherMax = isset($tranche['max_amount']) && $tranche['max_amount'] !== null
                ? Decimal::parse($tranche['max_amount'])
                : null;

            $thisStartsBeforeOtherEnds = $otherMax === null || $min < $otherMax;
            $otherStartsBeforeThisEnds = $max === null || $otherMin < $max;
            if ($thisStartsBeforeOtherEnds && $otherStartsBeforeThisEnds) {
                return true;
            }
        }

        return false;
    }

    private static function integer(mixed $value, int $max, int $default): int
    {
        $text = self::string($value);
        if (!ctype_digit($text) || (int) $text > $max) {
            return $default;
        }

        return (int) $text;
    }

    private static function string(mixed $value): string
    {
        return is_string($value) || is_int($value) ? trim((string) $value) : '';
    }
}
