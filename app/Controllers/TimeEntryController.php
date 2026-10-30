<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\View;
use App\Models\ProjectRepository;
use App\Models\TimeEntryRepository;
use App\Services\TimeEntryService;
use App\Services\ViewContext;
use DateTimeImmutable;
use Throwable;

/** Saisie et consultation du temps selon le périmètre de permissions de chaque rôle. */
final class TimeEntryController
{
    public function index(Request $request): Response
    {
        if (!self::canViewEntries()) {
            return Response::html(__('common.forbidden'), 403);
        }
        $canViewAll = Auth::can('time.view_all') || Auth::can('time.manage_all');
        $canViewCosts = Auth::can('finance.costs.view');
        $ownerId = $canViewAll ? null : Auth::id();
        $today = new DateTimeImmutable('today');
        $monday = $today->modify('monday this week');
        $dateFrom = self::filterDate($request->query('date_from'), $monday->format('Y-m-d'));
        $dateTo = self::filterDate($request->query('date_to'), $today->format('Y-m-d'));
        if ($dateFrom > $dateTo) {
            $dateFrom = $monday->format('Y-m-d');
            $dateTo = $today->format('Y-m-d');
        }
        $projectId = self::positiveId($request->query('project_id'));
        $userId = $canViewAll ? self::positiveId($request->query('user_id')) : '';
        $filters = [
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
            'project_id' => $projectId,
            'phase_id' => '',
            'user_id' => $userId,
        ];
        $page = self::page($request->query('page'));
        $repository = new TimeEntryRepository();
        $result = $repository->paginate($filters, 20, ($page - 1) * 20, $ownerId, $canViewCosts);
        $summary = $repository->summary($filters, $ownerId, $canViewCosts);
        $projects = (new ProjectRepository())->timeTrackingOptions();
        $context = (new ViewContext())->authenticated(__('time.title'), 'time');

        return Response::html(View::render('pages/time/index', $context + [
            'entries' => $result['items'],
            'summary' => $summary,
            'total' => $result['total'],
            'page' => $page,
            'pageCount' => max(1, (int) ceil($result['total'] / 20)),
            'filters' => $filters,
            'projects' => $projects,
            'users' => $canViewAll ? $repository->usersForFilter() : [],
            'canCreate' => self::canManageEntries(),
            'canViewAll' => $canViewAll,
            'canViewCosts' => $canViewCosts,
            'notice' => Session::pullFlash('time.notice'),
            'errors' => Session::pullFlash('time.errors', []),
        ]));
    }

    public function createForm(Request $request): Response
    {
        if (!self::canManageEntries()) {
            return Response::html(__('common.forbidden'), 403);
        }
        $values = Session::pullFlash('time.values', []);
        if (!is_array($values) || $values === []) {
            $values = [
                'project_id' => '',
                'phase_assignment_id' => '',
                'work_date' => (new DateTimeImmutable('today'))->format('Y-m-d'),
                'duration_hours' => '1.00',
                'description' => '',
                'is_billable' => '1',
            ];
        }
        return $this->formResponse(
            $values,
            null,
            Session::pullFlash('time.errors', []),
            __('time.new_entry')
        );
    }

    public function create(Request $request): Response
    {
        if (!self::canManageEntries()) {
            return Response::html(__('common.forbidden'), 403);
        }
        $validated = (new TimeEntryService())->validate($request->all(), (int) Auth::id());
        if ($validated['errors'] !== []) {
            Session::flash('time.errors', $validated['errors']);
            Session::flash('time.values', $validated['values']);
            return Response::redirect(url('/time/new'));
        }
        $values = self::entryValues($validated['values'], (int) Auth::id());
        try {
            (new TimeEntryRepository())->create($values);
        } catch (Throwable $error) {
            error_log((string) $error);
            Session::flash('time.errors', ['general' => __('time.save_failed')]);
            Session::flash('time.values', $validated['values']);
            return Response::redirect(url('/time/new'));
        }
        Session::flash('time.notice', __('time.created'));
        return Response::redirect(url('/time'));
    }

    public function edit(Request $request, string $id): Response
    {
        $entryId = filter_var($id, FILTER_VALIDATE_INT);
        $includeCosts = Auth::can('finance.costs.view');
        $entry = $entryId !== false && $entryId > 0
            ? (new TimeEntryRepository())->findById((int) $entryId, $includeCosts)
            : null;
        if ($entry === null) {
            return Response::html(View::render('pages/404', [], 'layouts/app'), 404);
        }
        if (!self::canManageEntry($entry)) {
            return Response::html(__('common.forbidden'), 403);
        }
        $values = Session::pullFlash('time.values', []);
        return $this->formResponse(
            is_array($values) && $values !== [] ? $values : $entry,
            $entry,
            Session::pullFlash('time.errors', []),
            __('time.edit_entry')
        );
    }

    public function update(Request $request, string $id): Response
    {
        $entryId = filter_var($id, FILTER_VALIDATE_INT);
        $entry = $entryId !== false && $entryId > 0
            ? (new TimeEntryRepository())->findById((int) $entryId)
            : null;
        if ($entry === null) {
            return Response::html(View::render('pages/404', [], 'layouts/app'), 404);
        }
        if (!self::canManageEntry($entry)) {
            return Response::html(__('common.forbidden'), 403);
        }
        $validated = (new TimeEntryService())->validate($request->all(), (int) $entry['user_id']);
        if ($validated['errors'] !== []) {
            Session::flash('time.errors', $validated['errors']);
            Session::flash('time.values', $validated['values']);
            return Response::redirect(url('/time/' . $entryId . '/edit'));
        }
        $values = self::entryValues($validated['values'], (int) $entry['user_id']);
        unset($values['user_id']);
        try {
            $updated = (new TimeEntryRepository())->update(
                (int) $entryId,
                (int) Auth::id(),
                Auth::can('time.manage_all'),
                $values
            );
            if (!$updated) {
                Session::flash('time.errors', ['general' => __('time.not_found')]);
                return Response::redirect(url('/time'));
            }
        } catch (Throwable $error) {
            error_log((string) $error);
            Session::flash('time.errors', ['general' => __('time.save_failed')]);
            Session::flash('time.values', $validated['values']);
            return Response::redirect(url('/time/' . $entryId . '/edit'));
        }
        Session::flash('time.notice', __('time.updated'));
        return Response::redirect(url('/time'));
    }

    private function formResponse(array $values, ?array $entry, array $errors, string $title): Response
    {
        $context = (new ViewContext())->authenticated($title, 'time');
        return Response::html(View::render('pages/time/form', $context + [
            'values' => $values,
            'entry' => $entry,
            'errors' => is_array($errors) ? $errors : [],
            'projects' => (new ProjectRepository())->timeTrackingOptions(),
            'canViewCosts' => Auth::can('finance.costs.view'),
        ]));
    }

    /** @param array<string,mixed> $values
     *  @return array{user_id:int,project_id:int,phase_assignment_id:int,work_date:string,duration_hours:string,description:string,is_billable:bool}
     */
    private static function entryValues(array $values, int $userId): array
    {
        return [
            'user_id' => $userId,
            'project_id' => (int) $values['project_id'],
            'phase_assignment_id' => (int) $values['phase_assignment_id'],
            'work_date' => (string) $values['work_date'],
            'duration_hours' => (string) $values['duration_hours'],
            'description' => (string) $values['description'],
            'is_billable' => (bool) $values['is_billable'],
        ];
    }

    private static function canViewEntries(): bool
    {
        return Auth::can('time.view') || Auth::can('time.manage')
            || Auth::can('time.view_all') || Auth::can('time.manage_all');
    }

    private static function canManageEntries(): bool
    {
        return Auth::can('time.manage') || Auth::can('time.manage_all');
    }

    /** @param array<string,mixed> $entry */
    private static function canManageEntry(array $entry): bool
    {
        return Auth::can('time.manage_all')
            || (Auth::can('time.manage') && Auth::id() === (int) $entry['user_id']);
    }

    private static function filterDate(mixed $value, string $default): string
    {
        if (!is_string($value) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return $default;
        }
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        $errors = DateTimeImmutable::getLastErrors();
        return $date !== false
            && ($errors === false || ($errors['warning_count'] === 0 && $errors['error_count'] === 0))
            && $date->format('Y-m-d') === $value
            ? $value
            : $default;
    }

    private static function positiveId(mixed $value): string
    {
        if (!is_string($value) && !is_int($value)) {
            return '';
        }
        $text = (string) $value;
        return ctype_digit($text) && (int) $text > 0 ? (string) (int) $text : '';
    }

    private static function page(mixed $value): int
    {
        $page = filter_var($value, FILTER_VALIDATE_INT);
        return $page === false || $page < 1 ? 1 : min($page, 100000);
    }
}
