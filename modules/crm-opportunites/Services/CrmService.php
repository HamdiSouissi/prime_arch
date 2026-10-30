<?php
declare(strict_types=1);

namespace Modules\CrmOpportunites\Services;

use DateTimeImmutable;

/**
 * Règles du pipeline commercial : étapes, montants estimés, relances et activités.
 *
 * Le module n’applique **aucune règle commerciale** : ni probabilité de signature, ni montant
 * par défaut, ni étape imposée par une durée. Les montants sont des estimations saisies par le
 * cabinet, manipulées en millimes entiers.
 */
final class CrmService
{
    /** Types de prospect, alignés sur ceux des clients du noyau. */
    public const CLIENT_TYPES = ['individual', 'company', 'organization'];

    /** Origine du contact. */
    public const ORIGINS = ['referral', 'website', 'network', 'walk_in', 'tender', 'social', 'other'];

    /** Étapes du pipeline, dans l’ordre d’avancement. */
    public const STAGES = ['new', 'contact', 'meeting', 'proposal', 'negotiation', 'won', 'lost'];

    /** Étapes qui closent une opportunité. */
    public const CLOSED_STAGES = ['won', 'lost'];

    /** Types d’activité consignés. */
    public const ACTIVITY_KINDS = ['call', 'meeting', 'visit', 'email', 'quote', 'note'];

    /** Millimes dans un dinar. */
    public const SCALE = 1000;

    /** Longueur maximale du titre et du nom du prospect. */
    public const MAX_NAME = 180;

    /** Millisecondes d’une journée, utilisées pour les écarts de dates. */
    private const DAY = 86400;

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

    /** Écrit des millimes au format attendu par la base : « 45000.000 ». */
    public static function format(int $millimes): string
    {
        $sign = $millimes < 0 ? '-' : '';
        $absolute = abs($millimes);

        return $sign . intdiv($absolute, self::SCALE) . '.' . str_pad((string) ($absolute % self::SCALE), 3, '0', STR_PAD_LEFT);
    }

    /** Montant prêt à l’écran, ou « — » lorsqu’il n’est pas estimé. */
    public static function label(?int $millimes): string
    {
        return $millimes === null ? '—' : money_format_tnd(self::format($millimes));
    }

    /**
     * État de la relance prévue : `none` (aucune date), `late`, `today` ou `upcoming`.
     */
    public static function followUpState(?string $nextActionOn, string $today): string
    {
        if ($nextActionOn === null || $nextActionOn === '') {
            return 'none';
        }
        if ($nextActionOn < $today) {
            return 'late';
        }

        return $nextActionOn === $today ? 'today' : 'upcoming';
    }

    /** Jours restants avant la relance prévue (négatifs une fois la date passée). */
    public static function daysLeft(?string $nextActionOn, string $today): ?int
    {
        if ($nextActionOn === null || $nextActionOn === '') {
            return null;
        }
        $target = DateTimeImmutable::createFromFormat('!Y-m-d', $nextActionOn);
        $now = DateTimeImmutable::createFromFormat('!Y-m-d', $today);
        if ($target === false || $now === false) {
            return null;
        }
        $days = (int) round(((float) $target->getTimestamp() - (float) $now->getTimestamp()) / self::DAY);

        return $days;
    }

    /**
     * Valide une opportunité.
     *
     * @param array<string,mixed> $input
     * @param array<int,int>      $userIds
     * @return array{values:array<string,mixed>,errors:array<string,string>}
     */
    public function validate(array $input, array $userIds): array
    {
        $origin = self::string($input['origin'] ?? 'other');
        $stage = self::string($input['stage'] ?? 'new');
        $clientType = self::string($input['client_type'] ?? 'individual');
        $ownerRaw = trim(self::string($input['owner_id'] ?? ''));
        $amountRaw = trim(self::string($input['estimated_amount'] ?? ''));
        $amount = $amountRaw === '' ? null : self::millimes($amountRaw);
        $nextActionRaw = trim(self::string($input['next_action_on'] ?? ''));
        $expectedRaw = trim(self::string($input['expected_close_on'] ?? ''));

        $values = [
            'title' => trim(self::string($input['title'] ?? '')),
            'prospect_name' => trim(self::string($input['prospect_name'] ?? '')),
            'client_type' => in_array($clientType, self::CLIENT_TYPES, true) ? $clientType : 'individual',
            'email' => mb_strtolower(trim(self::string($input['email'] ?? '')), 'UTF-8'),
            'phone' => trim(self::string($input['phone'] ?? '')),
            'city' => trim(self::string($input['city'] ?? '')),
            'origin' => in_array($origin, self::ORIGINS, true) ? $origin : 'other',
            'stage' => in_array($stage, self::STAGES, true) ? $stage : 'new',
            'estimated_amount' => $amount === null ? null : self::format($amount),
            'expected_close_on' => self::date($input['expected_close_on'] ?? null),
            'next_action_on' => self::date($input['next_action_on'] ?? null),
            'owner_id' => null,
            'lost_reason' => trim(self::string($input['lost_reason'] ?? '')),
            'notes' => trim(self::string($input['notes'] ?? '')),
        ];
        $errors = [];

        if (mb_strlen($values['title'], 'UTF-8') < 2) {
            $errors['title'] = __('crm.invalid_title');
        } elseif (mb_strlen($values['title'], 'UTF-8') > self::MAX_NAME) {
            $errors['title'] = __('crm.invalid_title_length');
        }
        if (mb_strlen($values['prospect_name'], 'UTF-8') < 2) {
            $errors['prospect_name'] = __('crm.invalid_prospect');
        } elseif (mb_strlen($values['prospect_name'], 'UTF-8') > self::MAX_NAME) {
            $errors['prospect_name'] = __('crm.invalid_prospect_length');
        }
        if ($values['email'] !== '' && (!filter_var($values['email'], FILTER_VALIDATE_EMAIL) || strlen($values['email']) > 190)) {
            $errors['email'] = __('crm.invalid_email');
        }
        if (mb_strlen($values['phone'], 'UTF-8') > 32) {
            $errors['phone'] = __('crm.invalid_phone');
        }
        if (mb_strlen($values['city'], 'UTF-8') > 120) {
            $errors['city'] = __('crm.invalid_city');
        }
        if ($amountRaw !== '' && $amount === null) {
            $errors['estimated_amount'] = __('crm.invalid_amount');
        }
        if ($expectedRaw !== '' && $values['expected_close_on'] === null) {
            $errors['expected_close_on'] = __('crm.invalid_expected_close');
        }
        if ($nextActionRaw !== '' && $values['next_action_on'] === null) {
            $errors['next_action_on'] = __('crm.invalid_next_action');
        }
        if ($ownerRaw !== '') {
            if (!ctype_digit($ownerRaw) || !in_array((int) $ownerRaw, $userIds, true)) {
                $errors['owner_id'] = __('crm.invalid_owner');
            } else {
                $values['owner_id'] = (int) $ownerRaw;
            }
        }
        if ($values['stage'] === 'lost' && $values['lost_reason'] === '') {
            $errors['lost_reason'] = __('crm.invalid_lost_reason');
        }
        if ($values['stage'] !== 'lost') {
            $values['lost_reason'] = '';
        }
        if (mb_strlen($values['lost_reason'], 'UTF-8') > 500) {
            $errors['lost_reason'] = __('crm.invalid_lost_reason_length');
        }
        if (mb_strlen($values['notes'], 'UTF-8') > 1000) {
            $errors['notes'] = __('crm.invalid_notes');
        }

        return ['values' => $values, 'errors' => $errors];
    }

    /**
     * Valide un changement d’étape isolé (formulaire rapide du pipeline).
     *
     * @param array<string,mixed> $input
     * @return array{values:array<string,mixed>,errors:array<string,string>}
     */
    public function validateStage(array $input): array
    {
        $stage = self::string($input['stage'] ?? '');
        $values = [
            'stage' => in_array($stage, self::STAGES, true) ? $stage : '',
            'lost_reason' => trim(self::string($input['lost_reason'] ?? '')),
        ];
        $errors = [];

        if ($values['stage'] === '') {
            $errors['stage'] = __('crm.invalid_stage');
        } elseif ($values['stage'] === 'lost' && $values['lost_reason'] === '') {
            $errors['lost_reason'] = __('crm.invalid_lost_reason');
        } elseif (mb_strlen($values['lost_reason'], 'UTF-8') > 500) {
            $errors['lost_reason'] = __('crm.invalid_lost_reason_length');
        }
        if ($values['stage'] !== 'lost') {
            $values['lost_reason'] = '';
        }

        return ['values' => $values, 'errors' => $errors];
    }

    /**
     * Valide une activité du journal.
     *
     * @param array<string,mixed> $input
     * @return array{values:array<string,mixed>,errors:array<string,string>}
     */
    public function validateActivity(array $input): array
    {
        $kind = self::string($input['kind'] ?? 'note');
        $values = [
            'kind' => in_array($kind, self::ACTIVITY_KINDS, true) ? $kind : 'note',
            'occurred_on' => self::date($input['occurred_on'] ?? null),
            'summary' => trim(self::string($input['summary'] ?? '')),
            'detail' => trim(self::string($input['detail'] ?? '')),
        ];
        $errors = [];

        if ($values['occurred_on'] === null) {
            $errors['occurred_on'] = __('crm.invalid_occurred_on');
        }
        if ($values['summary'] === '') {
            $errors['summary'] = __('crm.invalid_summary');
        } elseif (mb_strlen($values['summary'], 'UTF-8') > 255) {
            $errors['summary'] = __('crm.invalid_summary_length');
        }
        if (mb_strlen($values['detail'], 'UTF-8') > 1000) {
            $errors['detail'] = __('crm.invalid_detail');
        }

        return ['values' => $values, 'errors' => $errors];
    }

    /**
     * Valide le rattachement d’une opportunité à une fiche client et, au besoin, à un projet
     * existants du noyau.
     *
     * Le module ne crée ni client ni projet : il ne fait que pointer vers des fiches existantes.
     * Les identifiants autorisés sont fournis par le contrôleur après lecture du noyau.
     *
     * @param array<string,mixed> $input
     * @param array<int,int>      $clientIds       identifiants de clients rattachables
     * @param array<int,int>      $projectClients  identifiant de projet => identifiant de client
     * @return array{values:array<string,mixed>,errors:array<string,string>}
     */
    public function validateConversion(array $input, array $clientIds, array $projectClients): array
    {
        $clientRaw = trim(self::string($input['client_id'] ?? ''));
        $projectRaw = trim(self::string($input['project_id'] ?? ''));
        $values = [
            'client_id' => null,
            'project_id' => null,
            'mark_won' => (string) ($input['mark_won'] ?? '0') === '1',
        ];
        $errors = [];

        if (!ctype_digit($clientRaw) || !in_array((int) $clientRaw, $clientIds, true)) {
            $errors['client_id'] = __('crm.invalid_client');
        } else {
            $values['client_id'] = (int) $clientRaw;
        }
        if ($projectRaw !== '') {
            if (!ctype_digit($projectRaw) || !isset($projectClients[(int) $projectRaw])) {
                $errors['project_id'] = __('crm.invalid_project');
            } elseif ($values['client_id'] !== null && $projectClients[(int) $projectRaw] !== $values['client_id']) {
                // Un projet appartient toujours au client sélectionné : on refuse le mélange.
                $errors['project_id'] = __('crm.invalid_project_client');
            } else {
                $values['project_id'] = (int) $projectRaw;
            }
        }

        return ['values' => $values, 'errors' => $errors];
    }

    /**
     * Synthèse du pipeline : nombre et montants estimés par étape.
     *
     * @param array<int,array<string,mixed>> $rows
     * @return array<string,mixed>
     */
    public function statistics(array $rows, string $today): array
    {
        $byStage = array_fill_keys(self::STAGES, ['count' => 0, 'amount' => 0]);
        $statistics = [
            'total' => count($rows),
            'open' => 0,
            'won' => 0,
            'lost' => 0,
            'pipeline' => 0,
            'won_amount' => 0,
            'late' => 0,
            'today' => 0,
            'without_amount' => 0,
        ];
        foreach ($rows as $row) {
            $stage = (string) ($row['stage'] ?? 'new');
            if (!isset($byStage[$stage])) {
                $stage = 'new';
            }
            $amount = self::millimes($row['estimated_amount'] ?? '');
            $byStage[$stage]['count']++;
            $byStage[$stage]['amount'] += $amount ?? 0;
            if ($amount === null) {
                $statistics['without_amount']++;
            }
            if (in_array($stage, self::CLOSED_STAGES, true)) {
                $statistics[$stage === 'won' ? 'won' : 'lost']++;
                if ($stage === 'won') {
                    $statistics['won_amount'] += $amount ?? 0;
                }
            } else {
                $statistics['open']++;
                $statistics['pipeline'] += $amount ?? 0;
                $state = self::followUpState(
                    isset($row['next_action_on']) && $row['next_action_on'] !== null ? (string) $row['next_action_on'] : null,
                    $today
                );
                if ($state === 'late') {
                    $statistics['late']++;
                } elseif ($state === 'today') {
                    $statistics['today']++;
                }
            }
        }
        $statistics['by_stage'] = $byStage;

        return $statistics;
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
