<?php
declare(strict_types=1);

namespace Modules\PlanningEquipe\Controllers;

use App\Core\Auth;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\View;
use App\Services\AuditLogger;
use App\Services\ViewContext;
use Modules\PlanningEquipe\Models\PlanningRepository;
use Modules\PlanningEquipe\Services\PlanningService;

/**
 * Planning de l’équipe : charge par collaborateur, congés et budget d’heures.
 *
 * Droits réutilisés du cœur : `time.view` en lecture, `time.manage` en écriture. Un
 * collaborateur qui n’a pas `time.view_all` ne voit que sa propre ligne et ses heures.
 */
final class PlanningController
{
    private PlanningRepository $planning;
    private PlanningService $service;

    public function __construct(?PlanningRepository $planning = null, ?PlanningService $service = null)
    {
        $this->planning = $planning ?? new PlanningRepository();
        $this->service = $service ?? new PlanningService();
    }

    /** Vue semaine ou mois : charge planifiée, heures réalisées, congés. */
    public function index(Request $request): Response
    {
        if (($denied = $this->requireView()) !== null) {
            return $denied;
        }
        $start = PlanningService::date($request->query('start', '')) ?? date('Y-m-d');
        $scopeRaw = $request->query('scope', 'week');
        $scope = is_string($scopeRaw) && in_array($scopeRaw, PlanningService::SCOPES, true) ? $scopeRaw : 'week';
        $bounds = $this->service->periodBounds($start, $scope);
        $days = $this->service->days($bounds['start'], $bounds['end']);
        $capacity = $this->capacity($request);
        $canViewAll = $this->canViewAll();
        $onlyUserId = $canViewAll ? null : Auth::id();

        $people = $this->people($onlyUserId);
        $affectations = array_map(
            function (array $affectation): array {
                $affectation['planned'] = $this->service->plannedCenti($affectation);

                return $affectation;
            },
            $this->planning->affectations($bounds['start'], $bounds['end'], $onlyUserId)
        );
        $leaves = $this->planning->leaves($bounds['start'], $bounds['end'], $onlyUserId);
        $time = $this->planning->timeByDay($bounds['start'], $bounds['end'], $onlyUserId);

        $planned = [];
        $plannedByPerson = [];
        foreach ($affectations as $affectation) {
            $userId = (int) $affectation['user_id'];
            $daily = PlanningService::centi($affectation['daily_hours']) ?? 0;
            foreach ($days as $day) {
                if ($day['is_weekend']) {
                    continue;
                }
                if ((string) $affectation['start_date'] <= $day['date'] && (string) $affectation['end_date'] >= $day['date']) {
                    $planned[$userId][$day['date']] = ($planned[$userId][$day['date']] ?? 0) + $daily;
                    $plannedByPerson[$userId] = ($plannedByPerson[$userId] ?? 0) + $daily;
                }
            }
        }

        $leaveDays = [];
        $leaveByPerson = [];
        foreach ($leaves as $leave) {
            $userId = (int) $leave['user_id'];
            foreach ($days as $day) {
                if ($day['is_weekend']) {
                    continue;
                }
                if ((string) $leave['start_date'] <= $day['date'] && (string) $leave['end_date'] >= $day['date']) {
                    $leaveDays[$userId][$day['date']] = true;
                    $leaveByPerson[$userId] = ($leaveByPerson[$userId] ?? 0) + 1;
                }
            }
        }

        $rows = [];
        $totalPlanned = 0;
        $totalRealised = 0;
        $totalLeaveDays = 0;
        $overloadCells = 0;
        foreach ($people as $person) {
            $userId = (int) $person['id'];
            $cells = [];
            $personRealised = 0;
            $personOverloaded = 0;
            foreach ($days as $day) {
                $date = $day['date'];
                $plannedCenti = (int) ($planned[$userId][$date] ?? 0);
                $realisedCenti = PlanningService::centi($time[$userId][$date] ?? '0') ?? 0;
                $overloaded = !$day['is_weekend'] && $this->service->overloaded($plannedCenti, $realisedCenti, $capacity);
                if ($overloaded) {
                    $personOverloaded++;
                }
                $personRealised += $realisedCenti;
                $cells[] = [
                    'date' => $date,
                    'planned' => $plannedCenti,
                    'realised' => $realisedCenti,
                    'leave' => isset($leaveDays[$userId][$date]),
                    'overloaded' => $overloaded,
                    'is_weekend' => $day['is_weekend'],
                ];
            }
            $personPlanned = (int) ($plannedByPerson[$userId] ?? 0);
            $personLeaveDays = (int) ($leaveByPerson[$userId] ?? 0);
            $rows[] = [
                'user' => $person,
                'cells' => $cells,
                'planned' => $personPlanned,
                'realised' => $personRealised,
                'leave_days' => $personLeaveDays,
                'overload_days' => $personOverloaded,
            ];
            $totalPlanned += $personPlanned;
            $totalRealised += $personRealised;
            $totalLeaveDays += $personLeaveDays;
            $overloadCells += $personOverloaded;
        }

        return Response::html(View::renderModule('planning-equipe', 'index', $this->context('planning.index_title') + [
            'period' => $bounds,
            'days' => $days,
            'rows' => $rows,
            'scope' => $scope,
            'start' => $start,
            'capacity' => $capacity,
            'affectations' => $affectations,
            'leaves' => $leaves,
            'people' => $people,
            'projects' => $this->planning->projects(),
            'phases' => $this->planning->phaseAssignments(),
            'statistics' => [
                'people' => count($rows),
                'planned' => $totalPlanned,
                'realised' => $totalRealised,
                'leave_days' => $totalLeaveDays,
                'overload' => $overloadCells,
            ],
            'canManage' => $this->canManage(),
            'canViewAll' => $canViewAll,
            'currentUserId' => Auth::id(),
            'kinds' => PlanningService::KINDS,
            'notice' => Session::pullFlash('planning.notice'),
            'error' => Session::pullFlash('planning.error'),
            'affectationErrors' => Session::pullFlash('planning.affectation_errors', []),
            'affectationValues' => Session::pullFlash('planning.affectation_values', []),
            'leaveErrors' => Session::pullFlash('planning.leave_errors', []),
            'leaveValues' => Session::pullFlash('planning.leave_values', []),
        ]));
    }

    /** Budget d’heures, charge planifiée et heures réalisées par projet. */
    public function projects(Request $request): Response
    {
        if (($denied = $this->requireView()) !== null) {
            return $denied;
        }
        $loads = $this->planning->projectLoads();
        $rows = [];
        $budgetTotal = 0;
        $plannedTotal = 0;
        $realisedTotal = 0;
        foreach ($loads['projects'] as $project) {
            $planned = 0;
            foreach ($loads['affectations'][(int) $project['id']] ?? [] as $affectation) {
                $planned += $this->service->plannedCenti($affectation);
            }
            $budget = $project['budget_hours'] === null
                ? null
                : (PlanningService::centi((string) $project['budget_hours']) ?? 0);
            $realised = PlanningService::centi((string) $project['realised_hours']) ?? 0;
            $rows[] = [
                'project' => $project,
                'budget' => $budget,
                'planned' => $planned,
                'realised' => $realised,
                'planned_gap' => $budget === null ? null : $planned - $budget,
                'realised_gap' => $budget === null ? null : $realised - $budget,
                'over_budget' => $budget !== null && $budget > 0 && ($realised > $budget || $planned > $budget),
            ];
            $budgetTotal += $budget ?? 0;
            $plannedTotal += $planned;
            $realisedTotal += $realised;
        }

        return Response::html(View::renderModule('planning-equipe', 'projects', $this->context('planning.projects_title') + [
            'rows' => $rows,
            'statistics' => [
                'projects' => count($rows),
                'budget' => $budgetTotal,
                'planned' => $plannedTotal,
                'realised' => $realisedTotal,
            ],
            'notice' => Session::pullFlash('planning.notice'),
            'error' => Session::pullFlash('planning.error'),
        ]));
    }

    /** Ajoute une affectation prévisionnelle. */
    public function addAffectation(Request $request): Response
    {
        if (($denied = $this->requireManage()) !== null) {
            return $denied;
        }
        $validated = $this->service->validateAffectation(
            $request->all(),
            array_map('intval', array_column($this->planning->collaborators(), 'id')),
            array_map('intval', array_column($this->planning->projects(), 'id'))
        );
        if ($validated['errors'] !== [] || !$this->allowedForUser($validated['values']['user_id'])) {
            Session::flash('planning.affectation_errors', $validated['errors'] !== []
                ? $validated['errors']
                : ['user_id' => __('common.forbidden')]);
            Session::flash('planning.affectation_values', $validated['values']);

            return $this->backToPeriod($request);
        }

        $affectationId = $this->planning->createAffectation($validated['values'], Auth::id());
        (new AuditLogger())->record('planning.affectation_created', 'module', $affectationId, [], Auth::id());
        Session::flash('planning.notice', __('planning.affectation_created'));

        return $this->backToPeriod($request);
    }

    /** Supprime une affectation prévisionnelle. */
    public function deleteAffectation(Request $request, string $affectationId): Response
    {
        if (($denied = $this->requireManage()) !== null) {
            return $denied;
        }
        if (!ctype_digit($affectationId)) {
            return Response::html(__('planning.not_found'), 404);
        }
        $affectation = $this->planning->findAffectation((int) $affectationId);
        if ($affectation === null) {
            Session::flash('planning.error', __('planning.affectation_not_found'));

            return $this->backToPeriod($request);
        }
        if (!$this->allowedForUser((int) $affectation['user_id'])) {
            return Response::html(__('common.forbidden'), 403);
        }
        $this->planning->deleteAffectation((int) $affectation['id']);
        (new AuditLogger())->record('planning.affectation_deleted', 'module', (int) $affectation['id'], [], Auth::id());
        Session::flash('planning.notice', __('planning.affectation_deleted'));

        return $this->backToPeriod($request);
    }

    /** Ajoute un congé. */
    public function addLeave(Request $request): Response
    {
        if (($denied = $this->requireManage()) !== null) {
            return $denied;
        }
        $validated = $this->service->validateLeave(
            $request->all(),
            array_map('intval', array_column($this->planning->collaborators(), 'id'))
        );
        if ($validated['errors'] !== [] || !$this->allowedForUser($validated['values']['user_id'])) {
            Session::flash('planning.leave_errors', $validated['errors'] !== []
                ? $validated['errors']
                : ['user_id' => __('common.forbidden')]);
            Session::flash('planning.leave_values', $validated['values']);

            return $this->backToPeriod($request);
        }

        $leaveId = $this->planning->createLeave($validated['values'], Auth::id());
        (new AuditLogger())->record('planning.leave_created', 'module', $leaveId, [], Auth::id());
        Session::flash('planning.notice', __('planning.leave_created'));

        return $this->backToPeriod($request);
    }

    /** Supprime un congé. */
    public function deleteLeave(Request $request, string $leaveId): Response
    {
        if (($denied = $this->requireManage()) !== null) {
            return $denied;
        }
        if (!ctype_digit($leaveId)) {
            return Response::html(__('planning.not_found'), 404);
        }
        $leave = $this->planning->findLeave((int) $leaveId);
        if ($leave === null) {
            Session::flash('planning.error', __('planning.leave_not_found'));

            return $this->backToPeriod($request);
        }
        if (!$this->allowedForUser((int) $leave['user_id'])) {
            return Response::html(__('common.forbidden'), 403);
        }
        $this->planning->deleteLeave((int) $leave['id']);
        (new AuditLogger())->record('planning.leave_deleted', 'module', (int) $leave['id'], [], Auth::id());
        Session::flash('planning.notice', __('planning.leave_deleted'));

        return $this->backToPeriod($request);
    }

    /** @return array<int,array<string,mixed>> */
    private function people(?int $onlyUserId): array
    {
        $people = $this->planning->collaborators();
        if ($onlyUserId === null) {
            return $people;
        }

        return array_values(array_filter(
            $people,
            static fn (array $person): bool => (int) $person['id'] === $onlyUserId
        ));
    }

    /** Retour à la période affichée, en conservant le mode et le seuil. */
    private function backToPeriod(Request $request): Response
    {
        $start = PlanningService::date($request->input('start', '')) ?? date('Y-m-d');
        $scopeRaw = $request->input('scope', 'week');
        $scope = is_string($scopeRaw) && in_array($scopeRaw, PlanningService::SCOPES, true) ? $scopeRaw : 'week';
        $capacityRaw = $request->input('capacity', '8');
        $capacity = PlanningService::centi($capacityRaw) ?? 800;

        return Response::redirect(url('/planning?' . http_build_query([
            'start' => $start,
            'scope' => $scope,
            'capacity' => PlanningService::format($capacity),
        ])));
    }

    /** Seuil de surcharge saisi à l’écran (centièmes), borné entre 1 h et 24 h. */
    private function capacity(Request $request): int
    {
        $raw = $request->query('capacity', '8');
        $centi = PlanningService::centi($raw) ?? 800;

        return max(100, min(PlanningService::DAY_CENTI, $centi));
    }

    private function allowedForUser(?int $userId): bool
    {
        if ($userId === null) {
            return false;
        }
        if ($this->canViewAll()) {
            return true;
        }

        return $userId === Auth::id();
    }

    /** @return array<string,mixed> */
    private function context(string $titleKey): array
    {
        return (new ViewContext())->authenticated(__($titleKey), 'planning-equipe');
    }

    private function requireView(): ?Response
    {
        return Auth::can('time.view') || Auth::can('time.view_all')
            ? null
            : Response::html(__('common.forbidden'), 403);
    }

    private function requireManage(): ?Response
    {
        return Auth::can('time.manage') || Auth::can('time.manage_all')
            ? null
            : Response::html(__('common.forbidden'), 403);
    }

    private function canManage(): bool
    {
        return Auth::can('time.manage') || Auth::can('time.manage_all');
    }

    private function canViewAll(): bool
    {
        return Auth::can('time.view_all');
    }
}
