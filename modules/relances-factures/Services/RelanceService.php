<?php
declare(strict_types=1);

namespace Modules\RelancesFactures\Services;

use App\Services\CabinetSettings;
use DateTimeImmutable;
use Modules\RelancesFactures\Models\RelanceRepository;
use Throwable;

/**
 * Calculs de la balance âgée, choix de la règle de relance et rédaction du courrier.
 *
 * Le module ne décide d’aucune sanction, d’aucun intérêt de retard et d’aucun délai légal :
 * il signale un encours, propose une tonalité et laisse le cabinet valider le texte.
 */
final class RelanceService
{
    public const TONES = ['courtesy', 'firm', 'formal', 'final'];

    public const CHANNELS = ['letter', 'call', 'other'];

    public const OUTCOMES = ['sent', 'promise', 'dispute', 'paid', 'ignored'];

    /** Bornes des tranches d’ancienneté, en jours de retard. */
    public const BUCKETS = [
        'd0_30' => [0, 30],
        'd31_60' => [31, 60],
        'd61_90' => [61, 90],
        'd90_plus' => [91, null],
    ];

    private RelanceRepository $repository;

    public function __construct(?RelanceRepository $repository = null)
    {
        $this->repository = $repository ?? new RelanceRepository();
    }

    /**
     * Enrichit les factures : encours, jours de retard et tranche.
     *
     * @param array<int,array<string,mixed>> $invoices
     * @return array<int,array<string,mixed>>
     */
    public function enrich(array $invoices, ?DateTimeImmutable $today = null): array
    {
        $today ??= new DateTimeImmutable('today');
        $enriched = [];
        foreach ($invoices as $invoice) {
            $total = self::decimal($invoice['total_amount'] ?? '0');
            $paid = self::decimal($invoice['paid_amount'] ?? '0');
            $outstanding = max(0.0, round($total - $paid, 3));
            if ($outstanding <= 0.0) {
                continue;
            }
            $reference = self::firstDate($invoice['due_date'] ?? null) ?? self::firstDate($invoice['issue_date'] ?? null);
            $daysLate = $reference === null ? 0 : (int) $reference->diff($today)->format('%r%a');
            $invoice['outstanding'] = self::money($outstanding);
            $invoice['days_late'] = $daysLate;
            $invoice['bucket'] = self::bucket($daysLate);
            $invoice['is_overdue'] = $daysLate > 0;
            $invoice['is_excluded'] = (bool) ($invoice['is_excluded'] ?? false);
            $enriched[] = $invoice;
        }

        usort(
            $enriched,
            static fn (array $a, array $b): int => [$b['days_late'], $b['outstanding']] <=> [$a['days_late'], $a['outstanding']]
        );

        return $enriched;
    }

    /** Tranche d’ancienneté d’un retard exprimé en jours (les factures non échues vont en 0-30). */
    public static function bucket(int $daysLate): string
    {
        foreach (self::BUCKETS as $key => [$min, $max]) {
            if ($daysLate >= $min && ($max === null || $daysLate <= $max)) {
                return $key;
            }
        }

        return 'd0_30';
    }

    /**
     * Totaux pour la balance âgée et les indicateurs d’écran.
     *
     * @param array<int,array<string,mixed>> $invoices
     * @return array{total:string,invoices:int,overdue:int,overdue_amount:string,buckets:array<string,string>,excluded:int}
     */
    public function totals(array $invoices): array
    {
        $total = 0.0;
        $overdueAmount = 0.0;
        $overdue = 0;
        $excluded = 0;
        $buckets = array_fill_keys(array_keys(self::BUCKETS), 0.0);
        foreach ($invoices as $invoice) {
            $amount = self::decimal($invoice['outstanding'] ?? '0');
            $total += $amount;
            $buckets[(string) $invoice['bucket']] += $amount;
            if ((bool) $invoice['is_overdue']) {
                $overdue++;
                $overdueAmount += $amount;
            }
            if ((bool) $invoice['is_excluded']) {
                $excluded++;
            }
        }

        return [
            'total' => self::money($total),
            'invoices' => count($invoices),
            'overdue' => $overdue,
            'overdue_amount' => self::money($overdueAmount),
            'buckets' => array_map(static fn (float $value): string => self::money($value), $buckets),
            'excluded' => $excluded,
        ];
    }

    /**
     * Balance âgée par client.
     *
     * @param array<int,array<string,mixed>> $invoices
     * @return array<int,array<string,mixed>>
     */
    public function byClient(array $invoices): array
    {
        $clients = [];
        foreach ($invoices as $invoice) {
            $clientId = (int) $invoice['client_id'];
            if (!isset($clients[$clientId])) {
                $clients[$clientId] = [
                    'client_id' => $clientId,
                    'client_name' => (string) $invoice['client_name'],
                    'is_excluded' => (bool) $invoice['is_excluded'],
                    'invoices' => 0,
                    'total' => 0.0,
                    'buckets' => array_fill_keys(array_keys(self::BUCKETS), 0.0),
                    'oldest' => 0,
                    'last_relance_on' => null,
                ];
            }
            $amount = self::decimal($invoice['outstanding'] ?? '0');
            $clients[$clientId]['invoices']++;
            $clients[$clientId]['total'] += $amount;
            $clients[$clientId]['buckets'][(string) $invoice['bucket']] += $amount;
            $clients[$clientId]['oldest'] = max((int) $clients[$clientId]['oldest'], (int) $invoice['days_late']);
            $last = $invoice['last_relance_on'] ?? null;
            if (is_string($last) && ($clients[$clientId]['last_relance_on'] === null || $last > $clients[$clientId]['last_relance_on'])) {
                $clients[$clientId]['last_relance_on'] = $last;
            }
        }

        $rows = [];
        foreach ($clients as $client) {
            $client['total'] = self::money((float) $client['total']);
            $client['buckets'] = array_map(static fn (float $value): string => self::money($value), $client['buckets']);
            $rows[] = $client;
        }
        usort($rows, static fn (array $a, array $b): int => [(float) $b['total'], $a['client_name']] <=> [(float) $a['total'], $b['client_name']]);

        return $rows;
    }

    /**
     * Règle la plus exigeante encore applicable à une facture.
     *
     * @param array<int,array<string,mixed>> $rules
     * @return array<string,mixed>|null
     */
    public function applicableRule(array $rules, int $daysLate, float $outstanding): ?array
    {
        $match = null;
        foreach ($rules as $rule) {
            if (!(bool) $rule['is_active']) {
                continue;
            }
            if ((int) $rule['delay_days'] > max(0, $daysLate)) {
                continue;
            }
            if (self::decimal($rule['min_amount']) > $outstanding) {
                continue;
            }
            if ($match === null
                || (int) $rule['delay_days'] >= (int) $match['delay_days']
            ) {
                $match = $rule;
            }
        }

        return $match;
    }

    /**
     * Sujet et corps du courrier pour une facture et une tonalité.
     *
     * Un modèle enregistré par le cabinet (table `relances_modeles`) prime sur le texte par
     * défaut fourni par le module.
     *
     * @param array<string,mixed> $invoice Facture enrichie (encours, jours de retard).
     * @param array<string,array<string,mixed>> $templates Modèles personnalisés indexés par « ton|langue ».
     * @return array{subject:string,body:string,is_custom:bool}
     */
    public function letter(array $invoice, string $tone, array $templates, string $locale): array
    {
        $key = $tone . '|' . $locale;
        $custom = $templates[$key] ?? null;
        $subject = is_array($custom) ? (string) $custom['subject'] : __('relance.modele.' . $tone . '.subject');
        $body = is_array($custom) ? (string) $custom['body'] : __('relance.modele.' . $tone . '.body');

        $replacements = $this->tokens($invoice);

        return [
            'subject' => strtr($subject, $replacements),
            'body' => strtr($body, $replacements),
            'is_custom' => is_array($custom),
        ];
    }

    /**
     * Jetons remplacés dans les modèles ; les clés sont préfixées par « : ».
     *
     * @param array<string,mixed> $invoice
     * @return array<string,string>
     */
    public function tokens(array $invoice): array
    {
        $due = self::firstDate($invoice['due_date'] ?? null) ?? self::firstDate($invoice['issue_date'] ?? null);
        $daysLate = (int) ($invoice['days_late'] ?? 0);

        return [
            ':client' => (string) ($invoice['client_name'] ?? ''),
            ':invoice' => (string) ($invoice['document_number'] ?? $invoice['title'] ?? ''),
            ':amount' => money_format_tnd((string) ($invoice['outstanding'] ?? '0.000')),
            ':due' => $due === null ? '—' : $due->format('d/m/Y'),
            ':days' => (string) max(0, $daysLate),
            ':project' => (string) ($invoice['project_name'] ?? ''),
            ':cabinet' => $this->cabinetName(),
            ':today' => (new DateTimeImmutable('today'))->format('d/m/Y'),
        ];
    }

    /**
     * @param array<string,mixed> $input
     * @return array{values:array<string,mixed>,errors:array<string,string>}
     */
    public function validateEvent(array $input): array
    {
        $values = [
            'tone' => self::string($input['tone'] ?? 'courtesy'),
            'channel' => self::string($input['channel'] ?? 'letter'),
            'outcome' => self::string($input['outcome'] ?? 'sent'),
            'sent_on' => self::string($input['sent_on'] ?? ''),
            'promise_on' => self::string($input['promise_on'] ?? ''),
            'promise_amount' => self::string($input['promise_amount'] ?? ''),
            'note' => trim(self::string($input['note'] ?? '')),
            'regle_id' => self::string($input['regle_id'] ?? ''),
        ];
        $errors = [];

        if (!in_array($values['tone'], self::TONES, true)) {
            $values['tone'] = 'courtesy';
        }
        if (!in_array($values['channel'], self::CHANNELS, true)) {
            $values['channel'] = 'letter';
        }
        if (!in_array($values['outcome'], self::OUTCOMES, true)) {
            $values['outcome'] = 'sent';
        }
        $values['sent_on'] = $values['sent_on'] === '' ? date('Y-m-d') : $values['sent_on'];
        if (self::firstDate($values['sent_on']) === null) {
            $errors['sent_on'] = __('relance.invalid_date');
        }
        if ($values['promise_on'] !== '' && self::firstDate($values['promise_on']) === null) {
            $values['promise_on'] = '';
            $errors['promise_on'] = __('relance.invalid_date');
        }
        if ($values['promise_amount'] !== '') {
            $normalized = self::normalizeAmount($values['promise_amount']);
            if ($normalized === null) {
                $errors['promise_amount'] = __('relance.invalid_amount');
            } else {
                $values['promise_amount'] = $normalized;
            }
        }
        if (mb_strlen($values['note'], 'UTF-8') > 500) {
            $errors['note'] = __('relance.invalid_note');
        }
        if ($values['regle_id'] !== '' && !ctype_digit($values['regle_id'])) {
            $values['regle_id'] = '';
        }

        return ['values' => $values, 'errors' => $errors];
    }

    /**
     * @param array<string,mixed> $input
     * @return array{values:array<string,mixed>,errors:array<string,string>}
     */
    public function validateRule(array $input): array
    {
        $values = [
            'name' => trim(self::string($input['name'] ?? '')),
            'delay_days' => self::int($input['delay_days'] ?? 0),
            'min_amount' => self::string($input['min_amount'] ?? ''),
            'tone' => self::string($input['tone'] ?? 'courtesy'),
            'sort_order' => self::int($input['sort_order'] ?? 0),
            'is_active' => self::string($input['is_active'] ?? '') === '1' ? 1 : 0,
        ];
        $errors = [];

        $length = mb_strlen($values['name'], 'UTF-8');
        if ($length < 2 || $length > 120) {
            $errors['name'] = __('relance.invalid_name');
        }
        if ($values['delay_days'] < 0 || $values['delay_days'] > 3650) {
            $errors['delay_days'] = __('relance.invalid_delay');
        }
        if ($values['min_amount'] === '') {
            $values['min_amount'] = '0.000';
        } else {
            $normalized = self::normalizeAmount($values['min_amount']);
            if ($normalized === null) {
                $errors['min_amount'] = __('relance.invalid_amount');
            } else {
                $values['min_amount'] = $normalized;
            }
        }
        if (!in_array($values['tone'], self::TONES, true)) {
            $errors['tone'] = __('relance.invalid_tone');
        }
        if ($values['sort_order'] < 0 || $values['sort_order'] > 999) {
            $values['sort_order'] = 0;
        }

        return ['values' => $values, 'errors' => $errors];
    }

    /**
     * @param array<string,mixed> $input
     * @return array{values:array<string,mixed>,errors:array<string,string>}
     */
    public function validateTemplate(array $input): array
    {
        $values = [
            'tone' => self::string($input['tone'] ?? 'courtesy'),
            'locale' => self::string($input['locale'] ?? 'fr'),
            'subject' => trim(self::string($input['subject'] ?? '')),
            'body' => trim(self::string($input['body'] ?? '')),
        ];
        $errors = [];

        if (!in_array($values['tone'], self::TONES, true)) {
            $errors['tone'] = __('relance.invalid_tone');
        }
        if (!in_array($values['locale'], ['fr', 'ar'], true)) {
            $errors['locale'] = __('relance.invalid_locale');
        }
        $subjectLength = mb_strlen($values['subject'], 'UTF-8');
        if ($subjectLength < 2 || $subjectLength > 180) {
            $errors['subject'] = __('relance.invalid_subject');
        }
        $bodyLength = mb_strlen($values['body'], 'UTF-8');
        if ($bodyLength < 20 || $bodyLength > 4000) {
            $errors['body'] = __('relance.invalid_body');
        }

        return ['values' => $values, 'errors' => $errors];
    }

    // ------------------------------------------------------------------ outils

    /** Montant DECIMAL (« 1234.500 ») vers flottant, sans dépendre de bcmath. */
    public static function decimal(mixed $value): float
    {
        if (is_float($value) || is_int($value)) {
            return (float) $value;
        }
        if (!is_string($value) || preg_match('/^-?\d+(?:\.\d+)?$/', trim($value)) !== 1) {
            return 0.0;
        }

        return (float) $value;
    }

    /** Montant flottant vers chaîne DECIMAL à trois décimales (format de stockage et d’affichage). */
    public static function money(float $amount): string
    {
        return number_format(round($amount, 3), 3, '.', '');
    }

    /** Accepte « 1 234,500 », « 1234.5 » ; renvoie null si la saisie n’est pas un montant. */
    public static function normalizeAmount(string $value): ?string
    {
        $clean = str_replace([' ', "\u{00A0}", ','], ['', '', '.'], trim($value));
        if (preg_match('/^\d+(?:\.\d{1,3})?$/', $clean) !== 1) {
            return null;
        }

        return self::money((float) $clean);
    }

    public static function firstDate(mixed $value): ?DateTimeImmutable
    {
        if (!is_string($value) || $value === '') {
            return null;
        }
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        if ($date === false || $date->format('Y-m-d') !== $value) {
            return null;
        }

        return $date;
    }

    private function cabinetName(): string
    {
        try {
            $name = (string) ((new CabinetSettings())->get()['name'] ?? '');
        } catch (Throwable) {
            $name = '';
        }

        return $name !== '' ? $name : (string) config('app.name', 'Prime Arch');
    }

    private static function string(mixed $value): string
    {
        return is_string($value) || is_numeric($value) ? trim((string) $value) : '';
    }

    private static function int(mixed $value): int
    {
        $string = self::string($value);

        return preg_match('/^-?\d+$/', $string) === 1 ? (int) $string : 0;
    }
}
