<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\View;
use App\Models\ClientRepository;
use App\Models\CustomFieldRepository;
use App\Models\GeographyRepository;
use App\Models\ProjectRepository;
use App\Models\TimeEntryRepository;
use App\Services\AuditLogger;
use App\Services\ProjectPlanService;
use App\Services\ProjectService;
use App\Services\ViewContext;
use RuntimeException;
use Throwable;

/**
 * Gestion du portefeuille de projets et du cycle des phases architecturales.
 */
final class ProjectController
{
    public function index(Request $request): Response
    {
        if (!Auth::can('projects.view')) {
            return Response::html(__('common.forbidden'), 403);
        }
        $search = self::queryString($request, 'q');
        $status = self::queryString($request, 'status');
        $clientId = self::queryString($request, 'client_id');
        if (!in_array($status, ['', 'planning', 'active', 'on_hold', 'completed', 'cancelled'], true)) {
            $status = '';
        }
        if ($clientId !== '' && (!ctype_digit($clientId) || (int) $clientId < 1)) {
            $clientId = '';
        }
        $page = self::page($request->query('page'));
        $canViewCosts = Auth::can('finance.costs.view');
        $canViewTime = Auth::can('time.view') || Auth::can('time.manage')
            || Auth::can('time.view_all') || Auth::can('time.manage_all');
        $result = (new ProjectRepository())->paginate([
            'search' => mb_substr($search, 0, 100, 'UTF-8'),
            'status' => $status,
            'client_id' => $clientId,
        ], 20, ($page - 1) * 20, $canViewCosts, $canViewTime || $canViewCosts);
        $context = (new ViewContext())->authenticated(__('projects.title'), 'projects');
        return Response::html(View::render('pages/projects/index', $context + [
            'projects' => $result['items'],
            'total' => $result['total'],
            'page' => $page,
            'pageCount' => max(1, (int) ceil($result['total'] / 20)),
            'filters' => ['q' => $search, 'status' => $status, 'client_id' => $clientId],
            'clients' => Auth::can('clients.view') ? (new ClientRepository())->forSelect() : [],
            'canManage' => Auth::can('projects.manage'),
            'canConfigure' => Auth::can('projects.configure'),
            'canViewCosts' => $canViewCosts,
            'canViewTime' => $canViewTime,
        ]));
    }

    public function createForm(Request $request): Response
    {
        if (!Auth::can('projects.manage')) {
            return Response::html(__('common.forbidden'), 403);
        }
        $values = Session::pullFlash('projects.values', []);
        if (!is_array($values) || $values === []) {
            $requestedClientId = $request->query('client_id', '');
            $values = ['client_id' => is_string($requestedClientId) && ctype_digit($requestedClientId) ? $requestedClientId : ''];
        }
        return $this->formResponse(
            is_array($values) ? $values : [],
            null,
            Session::pullFlash('projects.errors', []),
            __('projects.create')
        );
    }

    public function create(Request $request): Response
    {
        if (!Auth::can('projects.manage')) {
            return Response::html(__('common.forbidden'), 403);
        }
        $input = self::formInput($request);
        if (!Auth::can('finance.costs.manage')) {
            $input['fee_budget_amount'] = '';
        }
        $validated = (new ProjectService())->validate($input, $request->input('custom_fields', []));
        if ($validated['errors'] !== []) {
            Session::flash('projects.errors', $validated['errors']);
            Session::flash('projects.values', $validated['values']);
            return Response::redirect(url('/projects/new'));
        }
        try {
            $id = (new ProjectRepository())->create($validated['values'], Auth::id());
        } catch (Throwable $error) {
            error_log((string) $error);
            Session::flash('projects.errors', ['general' => __('projects.save_failed')]);
            Session::flash('projects.values', $validated['values']);
            return Response::redirect(url('/projects/new'));
        }
        (new AuditLogger())->record('projects.created', 'project', $id, [], Auth::id());
        Session::flash('projects.notice', __('projects.created'));
        return Response::redirect(url('/projects/' . $id));
    }

    public function show(Request $request, string $id): Response
    {
        if (!Auth::can('projects.view')) {
            return Response::html(__('common.forbidden'), 403);
        }
        $projectId = filter_var($id, FILTER_VALIDATE_INT);
        $repository = new ProjectRepository();
        $canViewCosts = Auth::can('finance.costs.view');
        $project = $projectId !== false && $projectId > 0
            ? $repository->findById((int) $projectId, $canViewCosts || Auth::can('finance.costs.manage'))
            : null;
        if ($project === null) {
            return Response::html(View::render('pages/404', [], 'layouts/app'), 404);
        }
        $context = (new ViewContext())->authenticated((string) $project['name'], 'projects');
        $canViewTime = Auth::can('time.view') || Auth::can('time.manage')
            || Auth::can('time.view_all') || Auth::can('time.manage_all');
        $timeRepository = new TimeEntryRepository();
        return Response::html(View::render('pages/projects/show', $context + [
            'project' => $project,
            'phases' => $repository->phaseAssignments((int) $project['id']),
            'phaseSummaries' => ($canViewCosts || $canViewTime)
                ? $timeRepository->projectPhaseSummaries((int) $project['id'], $canViewCosts)
                : [],
            'timeSummary' => ($canViewCosts || $canViewTime)
                ? $timeRepository->projectSummary((int) $project['id'], $canViewCosts)
                : null,
            'customFields' => (new CustomFieldRepository())->all('project', true),
            'errors' => Session::pullFlash('projects.errors', []),
            'planValues' => Session::pullFlash('projects.plan_values', []),
            'notice' => Session::pullFlash('projects.notice'),
            'canManage' => Auth::can('projects.manage'),
            'canManageFee' => Auth::can('finance.costs.manage'),
            'canViewCosts' => $canViewCosts,
            'canViewTime' => $canViewTime,
            'canConfigure' => Auth::can('projects.configure'),
        ]));
    }

    public function edit(Request $request, string $id): Response
    {
        if (!Auth::can('projects.manage')) {
            return Response::html(__('common.forbidden'), 403);
        }
        $projectId = filter_var($id, FILTER_VALIDATE_INT);
        $project = $projectId !== false && $projectId > 0
            ? (new ProjectRepository())->findById(
                (int) $projectId,
                Auth::can('finance.costs.view') || Auth::can('finance.costs.manage')
            )
            : null;
        if ($project === null) {
            return Response::html(View::render('pages/404', [], 'layouts/app'), 404);
        }
        $values = Session::pullFlash('projects.values', []);
        return $this->formResponse(
            is_array($values) && $values !== [] ? $values : $project,
            $project,
            Session::pullFlash('projects.errors', []),
            __('projects.edit')
        );
    }

    public function update(Request $request, string $id): Response
    {
        if (!Auth::can('projects.manage')) {
            return Response::html(__('common.forbidden'), 403);
        }
        $projectId = filter_var($id, FILTER_VALIDATE_INT);
        $repository = new ProjectRepository();
        $canViewCosts = Auth::can('finance.costs.view');
        $project = $projectId !== false && $projectId > 0
            ? $repository->findById((int) $projectId, $canViewCosts || Auth::can('finance.costs.manage'))
            : null;
        if ($project === null) {
            return Response::html(View::render('pages/404', [], 'layouts/app'), 404);
        }
        $input = self::formInput($request);
        if (!Auth::can('finance.costs.manage')) {
            $input['fee_budget_amount'] = '';
        }
        $validated = (new ProjectService())->validate($input, $request->input('custom_fields', []), $project['custom_fields']);
        if ($validated['errors'] !== []) {
            Session::flash('projects.errors', $validated['errors']);
            Session::flash('projects.values', $validated['values']);
            return Response::redirect(url('/projects/' . $projectId . '/edit'));
        }
        try {
            $repository->update((int) $projectId, $validated['values'], Auth::can('finance.costs.manage'));
        } catch (Throwable $error) {
            error_log((string) $error);
            Session::flash('projects.errors', ['general' => __('projects.save_failed')]);
            Session::flash('projects.values', $validated['values']);
            return Response::redirect(url('/projects/' . $projectId . '/edit'));
        }
        (new AuditLogger())->record('projects.updated', 'project', (int) $projectId, [
            'status' => (string) ($validated['values']['status'] ?? ''),
        ], Auth::id());
        Session::flash('projects.notice', __('projects.updated'));
        return Response::redirect(url('/projects/' . $projectId));
    }

    public function updatePhase(Request $request, string $id, string $assignmentId): Response
    {
        if (!Auth::can('projects.manage')) {
            return Response::html(__('common.forbidden'), 403);
        }
        $projectId = filter_var($id, FILTER_VALIDATE_INT);
        $phaseId = filter_var($assignmentId, FILTER_VALIDATE_INT);
        $action = self::inputString($request, 'action');
        if ($projectId === false || $projectId < 1 || $phaseId === false || $phaseId < 1
            || !in_array($action, ['start', 'complete'], true)
        ) {
            return Response::redirect(url('/projects'));
        }
        try {
            $result = (new ProjectRepository())->updatePhaseStatus((int) $projectId, (int) $phaseId, $action);
        } catch (Throwable $error) {
            error_log((string) $error);
            $result = 'invalid';
        }
        $message = match ($result) {
            'updated' => __('projects.phase_updated'),
            'previous_incomplete' => __('projects.previous_phase_required'),
            'active_exists' => __('projects.active_phase_exists'),
            'missing' => __('projects.not_found'),
            default => __('projects.invalid_phase_action'),
        };
        if ($result === 'updated') {
            (new AuditLogger())->record('projects.phase.status.updated', 'project_phase', (int) $phaseId, [
                'project_id' => (int) $projectId,
                'phase_action' => $action,
            ], Auth::id());
        }
        Session::flash($result === 'updated' ? 'projects.notice' : 'projects.errors',
            $result === 'updated' ? $message : ['general' => $message]
        );
        return Response::redirect(url('/projects/' . $projectId));
    }

    public function savePlan(Request $request, string $id): Response
    {
        if (!Auth::can('projects.manage')) {
            return Response::html(__('common.forbidden'), 403);
        }
        $projectId = filter_var($id, FILTER_VALIDATE_INT);
        $repository = new ProjectRepository();
        $project = $projectId !== false && $projectId > 0 ? $repository->findById((int) $projectId) : null;
        if ($project === null) {
            return Response::html(View::render('pages/404', [], 'layouts/app'), 404);
        }
        $validated = (new ProjectPlanService())->validate(
            (int) $projectId,
            $request->input('budget_hours', ''),
            $request->input('phases', [])
        );
        if ($validated['errors'] !== []) {
            Session::flash('projects.errors', $validated['errors']);
            Session::flash('projects.plan_values', [
                'budget_hours' => $request->input('budget_hours', ''),
                'phases' => $request->input('phases', []),
            ]);
            return Response::redirect(url('/projects/' . $projectId));
        }
        try {
            if (!$repository->updatePlan(
                (int) $projectId,
                $validated['values']['budget_hours'],
                $validated['values']['phases']
            )) {
                throw new RuntimeException('Le plan du projet n’existe plus.');
            }
        } catch (Throwable $error) {
            error_log((string) $error);
            Session::flash('projects.errors', ['general' => __('projects.save_failed')]);
            return Response::redirect(url('/projects/' . $projectId));
        }
        (new AuditLogger())->record('projects.plan.updated', 'project', (int) $projectId, [], Auth::id());
        Session::flash('projects.notice', __('projects.plan_saved'));
        return Response::redirect(url('/projects/' . $projectId));
    }

    public function updatePhaseProgress(Request $request, string $id, string $assignmentId): Response
    {
        if (!Auth::can('projects.manage')) {
            return Response::html(__('common.forbidden'), 403);
        }
        $projectId = filter_var($id, FILTER_VALIDATE_INT);
        $phaseId = filter_var($assignmentId, FILTER_VALIDATE_INT);
        if ($projectId === false || $projectId < 1 || $phaseId === false || $phaseId < 1) {
            return Response::redirect(url('/projects'));
        }
        $planService = new ProjectPlanService();
        $progress = $planService->normalizeProgress($request->input('progress_percent', ''));
        if ($progress === null) {
            Session::flash('projects.errors', ['progress' => __('projects.invalid_progress')]);
            return Response::redirect(url('/projects/' . $projectId));
        }
        try {
            $result = (new ProjectRepository())->updatePhaseProgress((int) $projectId, (int) $phaseId, $progress);
        } catch (Throwable $error) {
            error_log((string) $error);
            $result = 'invalid';
        }
        if ($result === 'updated') {
            (new AuditLogger())->record('projects.phase.progress.updated', 'project_phase', (int) $phaseId, [
                'project_id' => (int) $projectId,
                'progress_percent' => $progress,
            ], Auth::id());
        }
        $message = match ($result) {
            'updated' => __('projects.progress_saved'),
            'missing' => __('projects.not_found'),
            default => __('projects.invalid_phase_action'),
        };
        Session::flash($result === 'updated' ? 'projects.notice' : 'projects.errors',
            $result === 'updated' ? $message : ['general' => $message]
        );
        return Response::redirect(url('/projects/' . $projectId));
    }

    private function formResponse(array $values, ?array $project, array $errors, string $title): Response
    {
        $governorateId = ctype_digit((string) ($values['governorate_id'] ?? ''))
            ? (int) $values['governorate_id']
            : 0;
        $context = (new ViewContext())->authenticated($title, 'projects');
        return Response::html(View::render('pages/projects/form', $context + [
            'project' => $project,
            'values' => $values,
            'errors' => is_array($errors) ? $errors : [],
            'customFields' => (new CustomFieldRepository())->all('project', true),
            'canConfigureFields' => Auth::can('projects.configure'),
            'canManageClients' => Auth::can('clients.manage'),
            'canManageFee' => Auth::can('finance.costs.manage'),
            'clients' => (new ClientRepository())->forSelect(),
            'governorates' => (new GeographyRepository())->governorates(),
            'delegations' => $governorateId > 0 ? (new GeographyRepository())->delegations($governorateId) : [],
            'title' => $title,
        ]));
    }

    /** @return array<string,mixed> */
    private static function formInput(Request $request): array
    {
        $values = [];
        foreach ([
            'client_id', 'name', 'project_type', 'description', 'status', 'address_line', 'city',
            'governorate_id', 'delegation_id', 'postal_code', 'start_date', 'target_end_date',
            'budget_hours', 'fee_budget_amount',
        ] as $key) {
            $values[$key] = self::inputString($request, $key);
        }
        return $values;
    }

    private static function inputString(Request $request, string $key): string
    {
        $value = $request->input($key, '');
        return is_string($value) || is_int($value) ? trim((string) $value) : '';
    }

    private static function queryString(Request $request, string $key): string
    {
        $value = $request->query($key, '');
        return is_string($value) ? trim($value) : '';
    }

    private static function page(mixed $value): int
    {
        $page = filter_var($value, FILTER_VALIDATE_INT);
        return $page === false || $page < 1 ? 1 : min($page, 100000);
    }
}
