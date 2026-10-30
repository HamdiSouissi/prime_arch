<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Auth;
use App\Core\HookManager;
use App\Models\DashboardRepository;
use App\Models\DocumentRepository;
use DateTimeImmutable;

/**
 * Assemblage du tableau de bord (lot 4).
 *
 * Le service ne rend pas de HTML : il prépare des valeurs déjà traduites et formatées
 * (montants à trois décimales, heures en français et en arabe, badges d'échéance) que la vue
 * se contente d'afficher. Chaque bloc est conditionné par une permission : un collaborateur
 * sans accès aux documents ne voit ni encours ni facture en retard.
 *
 * Les modules peuvent ajouter leurs propres cartes avec le filtre
 * `dashboard.cards` (`$cards`, `$user`) : une carte a la forme d'un `stat-card`
 * (`label`, `value`, `icon`, `tone`, `note`, `url`).
 */
final class DashboardService
{
    /** Fenêtre d'anticipation des échéances, en jours. */
    public const HORIZON_DAYS = 14;

    /** Nombre de cartes d'indicateurs du noyau. */
    public const PROJECT_LIMIT = 4;

    private ?DashboardRepository $repository;
    private ?DocumentRepository $documents;

    public function __construct(?DashboardRepository $repository = null, ?DocumentRepository $documents = null)
    {
        $this->repository = $repository;
        $this->documents = $documents;
    }

    /**
     * Données complètes du tableau de bord pour l'utilisateur courant.
     *
     * @param array<string,mixed> $user
     * @return array<string,mixed>
     */
    public function overview(array $user, ?int $userId): array
    {
        $can = [
            'projects' => Auth::can('projects.view'),
            'clients' => Auth::can('clients.view'),
            'documents' => self::canViewDocuments(),
            'time' => self::canViewTime(),
            'timeAll' => Auth::can('time.view_all') || Auth::can('time.manage_all'),
        ];

        $today = new DateTimeImmutable('today');
        $weekStart = $today->modify('monday this week');
        $weekEnd = $weekStart->modify('+6 days');
        $timeScope = $can['timeAll'] ? null : $userId;

        $metrics = $can['projects'] || $can['clients'] || $can['time']
            ? $this->repository()->metrics(
                $today->format('Y-m-d'),
                $weekStart->format('Y-m-d'),
                $weekEnd->format('Y-m-d'),
                $timeScope
            )
            : self::emptyMetrics();

        $outstanding = null;
        $overdueInvoices = [];
        if ($can['documents']) {
            $summary = $this->documents()->summary();
            $outstanding = [
                'amount' => (string) ($summary['outstanding_amount'] ?? '0.000'),
                'issued' => (int) ($summary['issued_quote_count'] ?? 0),
            ];
            $overdueInvoices = $this->repository()->overdueInvoices($today->format('Y-m-d'), 3);
        }

        $deadlines = $this->deadlines($today, $can, $overdueInvoices);
        $activity = $this->activity($can);
        $projects = $can['projects']
            ? $this->repository()->openProjects(self::PROJECT_LIMIT)
            : [];
        $week = $can['time']
            ? $this->week($weekStart, $weekEnd, $timeScope, $metrics)
            : null;

        $cards = $this->cards($metrics, $outstanding, $can);
        /** @var array<int,array<string,string>> $cards */
        $cards = HookManager::applyFilters('dashboard.cards', $cards, $user);

        return [
            'cards' => array_values(is_array($cards) ? $cards : []),
            'projects' => $projects,
            'deadlines' => $deadlines,
            'week' => $week,
            'activity' => $activity,
            'can' => $can,
            'empty' => self::isEmpty($metrics, $outstanding) && $deadlines === [] && $activity === [],
            'horizon' => self::HORIZON_DAYS,
        ];
    }

    /**
     * Cartes d'indicateurs du noyau, filtrées par permission puis complétées par les modules.
     *
     * @param array<string,int|string> $metrics
     * @param array{amount:string,issued:int}|null $outstanding
     * @param array<string,bool> $can
     * @return array<int,array<string,string>>
     */
    public function cards(array $metrics, ?array $outstanding, array $can): array
    {
        $cards = [];
        if ($can['projects']) {
            $late = (int) ($metrics['late_projects'] ?? 0);
            $cards[] = [
                'key' => 'projects',
                'label' => __('dashboard.kpi_projects'),
                'value' => (string) (int) ($metrics['open_projects'] ?? 0),
                'icon' => 'briefcase',
                'tone' => 'blue',
                'note' => $late > 0
                    ? __('dashboard.kpi_projects_late', ['count' => (string) $late])
                    : __('dashboard.kpi_projects_ontrack'),
                'url' => url('/projects'),
            ];
        }
        if ($can['documents'] && $outstanding !== null) {
            $hasAmount = self::hasAmount($outstanding['amount']);
            $cards[] = [
                'key' => 'outstanding',
                'label' => __('dashboard.kpi_outstanding'),
                'value' => money_format_tnd($outstanding['amount']),
                'icon' => 'wallet',
                'tone' => 'mint',
                'note' => $hasAmount
                    ? __('dashboard.kpi_outstanding_note')
                    : __('dashboard.kpi_outstanding_clear'),
                'url' => url('/documents'),
            ];
        }
        if ($can['time']) {
            $cards[] = [
                'key' => 'hours',
                'label' => __('dashboard.kpi_hours'),
                'value' => self::formatHours((string) ($metrics['week_hours'] ?? '0')),
                'icon' => 'clock',
                'tone' => 'coral',
                'note' => __('dashboard.kpi_hours_note', [
                    'count' => self::formatHours((string) ($metrics['week_billable_hours'] ?? '0'), false),
                ]),
                'url' => url('/time'),
            ];
        }
        if ($can['clients']) {
            $prospects = (int) ($metrics['prospect_clients'] ?? 0);
            $cards[] = [
                'key' => 'clients',
                'label' => __('dashboard.kpi_clients'),
                'value' => (string) (int) ($metrics['active_clients'] ?? 0),
                'icon' => 'people',
                'tone' => 'turquoise',
                'note' => __('dashboard.kpi_clients_note', ['count' => (string) $prospects]),
                'url' => url('/clients'),
            ];
        }

        return $cards;
    }

    /**
     * Échéances : retards d'abord (documents impayés), puis la fenêtre de 14 jours.
     *
     * @param array<string,bool> $can
     * @param array<int,array<string,mixed>> $overdueInvoices
     * @return array<int,array<string,mixed>>
     */
    public function deadlines(DateTimeImmutable $today, array $can, array $overdueInvoices): array
    {
        $items = [];
        foreach ($overdueInvoices as $row) {
            $items[] = $this->deadlineItem($row, $today, true);
        }
        $upcoming = $this->repository()->upcoming(
            $today->format('Y-m-d'),
            $today->modify('+' . self::HORIZON_DAYS . ' days')->format('Y-m-d'),
            $can['projects'],
            $can['documents'],
            5
        );
        foreach ($upcoming as $row) {
            $items[] = $this->deadlineItem($row, $today, false);
        }

        return array_slice($items, 0, 6);
    }

    /**
     * Activité récente : uniquement des objets que l'utilisateur peut ouvrir.
     *
     * @param array<string,bool> $can
     * @return array<int,array<string,string|null>>
     */
    public function activity(array $can): array
    {
        $subjectTypes = [];
        if ($can['clients']) {
            $subjectTypes[] = 'client';
            $subjectTypes[] = 'client_contact';
        }
        if ($can['projects']) {
            $subjectTypes[] = 'project';
            $subjectTypes[] = 'project_phase';
            $subjectTypes[] = 'project_image';
        }
        if ($can['documents']) {
            $subjectTypes[] = 'document';
        }
        if ($can['time']) {
            $subjectTypes[] = 'time_entry';
        }
        $rows = $this->repository()->activity($subjectTypes, 6);
        $now = new DateTimeImmutable('now');
        $items = [];
        foreach ($rows as $row) {
            $event = (string) ($row['event_key'] ?? '');
            $items[] = [
                'icon' => self::activityIcon($event),
                'text' => self::eventLabel($event),
                'meta' => (string) ($row['actor_name'] ?? __('audit.system')),
                'time' => self::relativeTime((string) ($row['created_at'] ?? ''), $now),
                'url' => self::subjectUrl((string) ($row['subject_type'] ?? ''), $row['subject_id'] ?? null),
            ];
        }

        return $items;
    }

    /**
     * Sept jours de la semaine avec leur hauteur relative pour le graphique.
     *
     * @param array<string,int|string> $metrics
     * @return array{days:array<int,array<string,mixed>>,total:string,billable:string,label:string,max:string,hasHours:bool}
     */
    public function week(DateTimeImmutable $weekStart, DateTimeImmutable $weekEnd, ?int $timeScope, array $metrics): array
    {
        $rows = $this->repository()->weekHours(
            $weekStart->format('Y-m-d'),
            $weekEnd->format('Y-m-d'),
            $timeScope
        );
        $byDay = [];
        foreach ($rows as $row) {
            $byDay[$row['day']] = $row;
        }
        $days = [];
        $peak = 0.0;
        $isToday = (new DateTimeImmutable('today'))->format('Y-m-d');
        for ($offset = 0; $offset < 7; $offset++) {
            $date = $weekStart->modify('+' . $offset . ' days');
            $key = $date->format('Y-m-d');
            $hours = (float) ($byDay[$key]['hours'] ?? 0);
            $peak = max($peak, $hours);
            $days[] = [
                'date' => $key,
                'weekday' => (int) $date->format('N'),
                'hours' => $hours,
                'hoursLabel' => self::formatHours((string) ($byDay[$key]['hours'] ?? '0'), false),
                'isToday' => $key === $isToday,
            ];
        }
        foreach ($days as &$day) {
            // Une journée sans saisie garde une barre visible de 3 % : le graphique reste lisible.
            $day['height'] = $peak > 0 ? max(3, (int) round($day['hours'] / $peak * 100)) : 3;
        }
        unset($day);

        return [
            'days' => $days,
            'total' => self::formatHours((string) ($metrics['week_hours'] ?? '0')),
            'billable' => self::formatHours((string) ($metrics['week_billable_hours'] ?? '0')),
            'label' => $weekStart->format('d/m') . ' – ' . $weekEnd->format('d/m/Y'),
            'max' => (string) (int) round($peak),
            'hasHours' => $peak > 0,
        ];
    }

    /**
     * Montant d'heures lisible : deux décimales au plus, virgule décimale, unité optionnelle.
     * « 8.50 » devient « 8,5 h » ; « 0.00 » devient « 0 h ».
     */
    public static function formatHours(string $hours, bool $withUnit = true): string
    {
        $value = (float) $hours;
        $text = number_format($value, 2, ',', "\u{00A0}");
        $text = rtrim(rtrim($text, '0'), ',');

        return $withUnit ? $text . "\u{00A0}h" : $text;
    }

    /**
     * Badge d'échéance : « En retard de 3 j », « Aujourd'hui », « Demain », « Dans 5 j ».
     *
     * @return array{label:string,tone:string}
     */
    public static function dueBadge(string $dueDate, DateTimeImmutable $today): array
    {
        $days = self::daysBetween($today, $dueDate);
        if ($days < 0) {
            return [
                'label' => __('dashboard.relative.overdue', ['count' => (string) abs($days)]),
                'tone' => 'danger',
            ];
        }
        if ($days === 0) {
            return ['label' => __('dashboard.relative.today'), 'tone' => 'warning'];
        }
        if ($days === 1) {
            return ['label' => __('dashboard.relative.tomorrow'), 'tone' => 'warning'];
        }

        return [
            'label' => __('dashboard.relative.days', ['count' => (string) $days]),
            'tone' => $days <= 3 ? 'warning' : 'info',
        ];
    }

    /**
     * Écart en jours entre aujourd'hui et une date `Y-m-d` (négatif si dépassée).
     */
    public static function daysBetween(DateTimeImmutable $today, string $date): int
    {
        $target = DateTimeImmutable::createFromFormat('!Y-m-d', substr($date, 0, 10));
        if (!$target instanceof DateTimeImmutable) {
            return 0;
        }

        return (int) floor(($target->getTimestamp() - $today->getTimestamp()) / 86400);
    }

    /**
     * Libellé relatif d'un horodatage (« Il y a 12 min », « Hier », « Il y a 3 j »).
     */
    public static function relativeTime(string $timestamp, DateTimeImmutable $now): string
    {
        $moment = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', substr($timestamp, 0, 19));
        if (!$moment instanceof DateTimeImmutable) {
            return '';
        }
        $seconds = $now->getTimestamp() - $moment->getTimestamp();
        if ($seconds < 60) {
            return __('dashboard.time.now');
        }
        if ($seconds < 3600) {
            return __('dashboard.time.minutes', ['count' => (string) max(1, (int) floor($seconds / 60))]);
        }
        if ($seconds < 86400) {
            return __('dashboard.time.hours', ['count' => (string) max(1, (int) floor($seconds / 3600))]);
        }
        $days = (int) floor($seconds / 86400);

        return $days === 1
            ? __('dashboard.time.yesterday')
            : __('dashboard.time.days', ['count' => (string) $days]);
    }

    /** Libellé traduit d'un événement d'audit, avec repli sur la clé technique. */
    public static function eventLabel(string $event): string
    {
        $key = 'audit.event.' . $event;

        return \App\Core\Translator::has($key) ? __($key) : $event;
    }

    /** Icône d'activité selon la famille d'événement. */
    public static function activityIcon(string $event): string
    {
        foreach (['documents.' => 'file-text', 'clients.' => 'people', 'projects.' => 'briefcase',
            'project_phases.' => 'layers', 'time.' => 'clock', 'users.' => 'user',
            'modules.' => 'layers', 'settings.' => 'settings'] as $prefix => $icon) {
            if (str_starts_with($event, $prefix)) {
                return $icon;
            }
        }

        return 'info';
    }

    /** Route de la fiche concernée, uniquement quand l'identifiant désigne bien cette fiche. */
    public static function subjectUrl(string $subjectType, mixed $subjectId): ?string
    {
        $id = (int) $subjectId;
        if ($id <= 0) {
            return null;
        }

        return match ($subjectType) {
            'client' => url('/clients/' . $id),
            'project' => url('/projects/' . $id),
            'document' => url('/documents/' . $id),
            default => null,
        };
    }

    public static function canViewDocuments(): bool
    {
        return Auth::can('documents.view') || Auth::can('documents.manage')
            || Auth::can('documents.issue') || Auth::can('documents.create')
            || Auth::can('documents.payments.manage') || Auth::can('documents.templates.manage');
    }

    public static function canViewTime(): bool
    {
        return Auth::can('time.view') || Auth::can('time.manage')
            || Auth::can('time.view_all') || Auth::can('time.manage_all');
    }

    /**
     * @param array<string,mixed> $row
     * @return array<string,mixed>
     */
    private function deadlineItem(array $row, DateTimeImmutable $today, bool $overdue): array
    {
        $kind = (string) ($row['kind'] ?? 'project');
        $dueDate = (string) ($row['due_date'] ?? '');
        $badge = self::dueBadge($dueDate, $today);
        $url = match ($kind) {
            'project' => url('/projects/' . (int) ($row['subject_id'] ?? 0)),
            default => url('/documents/' . (int) ($row['subject_id'] ?? 0)),
        };
        $typeLabel = match ($kind) {
            'project' => __('dashboard.kind_project'),
            'quote' => __('documents.type.quote'),
            default => __('documents.type.invoice'),
        };

        return [
            'kind' => $kind,
            'title' => (string) ($row['title'] ?? ''),
            'reference' => (string) ($row['reference'] ?? ''),
            'client' => (string) ($row['client_name'] ?? ''),
            'type' => $typeLabel,
            'dueDate' => $dueDate,
            'day' => substr($dueDate, 8, 2),
            'month' => substr($dueDate, 5, 2),
            'badge' => $badge['label'],
            'tone' => $overdue ? 'danger' : $badge['tone'],
            'overdue' => $overdue,
            'url' => $url,
        ];
    }

    /** @return array<string,int|string> */
    private static function emptyMetrics(): array
    {
        return [
            'active_clients' => 0,
            'prospect_clients' => 0,
            'client_total' => 0,
            'open_projects' => 0,
            'late_projects' => 0,
            'issued_invoices' => 0,
            'week_hours' => '0.00',
            'week_billable_hours' => '0.00',
        ];
    }

    /**
     * @param array<string,int|string> $metrics
     * @param array{amount:string,issued:int}|null $outstanding
     */
    private static function isEmpty(array $metrics, ?array $outstanding): bool
    {
        $hasClients = (int) ($metrics['client_total'] ?? 0) > 0;
        $hasProjects = (int) ($metrics['open_projects'] ?? 0) > 0;
        $hasDocuments = $outstanding !== null
            && (self::hasAmount($outstanding['amount']) || $outstanding['issued'] > 0);

        return !$hasClients && !$hasProjects && !$hasDocuments;
    }

    /**
     * Un montant DECIMAL est comparé comme du texte : aucune conversion en flottant,
     * donc aucune perte sur les trois décimales du dinar.
     */
    public static function hasAmount(string $amount): bool
    {
        return preg_match('/[1-9]/', $amount) === 1;
    }

    private function repository(): DashboardRepository
    {
        return $this->repository ??= new DashboardRepository();
    }

    private function documents(): DocumentRepository
    {
        return $this->documents ??= new DocumentRepository();
    }
}
