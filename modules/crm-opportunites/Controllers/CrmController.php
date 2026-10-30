<?php
declare(strict_types=1);

namespace Modules\CrmOpportunites\Controllers;

use App\Core\Auth;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\View;
use App\Services\AuditLogger;
use App\Services\ViewContext;
use DateTimeImmutable;
use Modules\CrmOpportunites\Models\CrmRepository;
use Modules\CrmOpportunites\Services\CrmService;
use Throwable;

/**
 * Pipeline commercial : prospects, étapes, relances et journal des activités.
 *
 * Le module écrit uniquement dans ses tables `crm_*`. Les clients, projets et utilisateurs sont
 * lus dans le noyau pour proposer un rattachement : aucune fiche du cœur n’est créée ni modifiée
 * ici. Droits réutilisés : `clients.view` ou `projects.view` en lecture, `clients.manage` ou
 * `projects.manage` en écriture.
 */
final class CrmController
{
    /** Nombre de jours de la fenêtre « relances de la semaine », aujourd’hui compris. */
    private const WEEK_DAYS = 7;

    /** Taille maximale d’une recherche libre. */
    private const MAX_SEARCH = 120;

    private CrmRepository $crm;
    private CrmService $service;

    public function __construct(?CrmRepository $crm = null, ?CrmService $service = null)
    {
        $this->crm = $crm ?? new CrmRepository();
        $this->service = $service ?? new CrmService();
    }

    /** Liste du pipeline : indicateurs, filtres, opportunités et récapitulatif par étape. */
    public function index(Request $request): Response
    {
        if (($denied = $this->requireView()) !== null) {
            return $denied;
        }
        $today = date('Y-m-d');
        $weekEnd = $this->weekEnd($today);
        $filters = $this->filters($request);
        $rows = $this->crm->opportunities($filters, $today, $weekEnd);
        $all = $filters === [] ? $rows : $this->crm->opportunities([], $today, $weekEnd);
        $rows = array_map(fn (array $row): array => $this->decorate($row, $today), $rows);

        return Response::html(View::renderModule('crm-opportunites', 'index', $this->context('crm.index_title') + [
            'today' => $today,
            'weekEnd' => $weekEnd,
            'filters' => $filters,
            'rows' => $rows,
            'statistics' => $this->service->statistics($all, $today),
            'stages' => CrmService::STAGES,
            'origins' => CrmService::ORIGINS,
            'owners' => $this->crm->ownerOptions(),
            'canManage' => $this->canManage(),
            'notice' => Session::pullFlash('crm.notice'),
            'error' => Session::pullFlash('crm.error'),
            'opportunityErrors' => Session::pullFlash('crm.opportunity_errors', []),
            'opportunityValues' => Session::pullFlash('crm.opportunity_values', []),
        ]));
    }

    /** Fiche d’une opportunité : informations, relance, rattachement et journal des activités. */
    public function show(Request $request, string $id): Response
    {
        if (($denied = $this->requireView()) !== null) {
            return $denied;
        }
        $row = $this->find($id);
        if ($row === null) {
            Session::flash('crm.error', __('crm.not_found'));

            return Response::redirect(url('/crm'));
        }
        $today = date('Y-m-d');
        $projects = $this->crm->projectOptions();
        $projectClients = [];
        foreach ($projects as $project) {
            $projectClients[(int) $project['id']] = (int) $project['client_id'];
        }

        return Response::html(View::renderModule('crm-opportunites', 'show', $this->context('crm.show_title') + [
            'today' => $today,
            'row' => $this->decorate($row, $today),
            'activities' => $this->crm->activities((int) $row['id']),
            'stages' => CrmService::STAGES,
            'origins' => CrmService::ORIGINS,
            'activityKinds' => CrmService::ACTIVITY_KINDS,
            'owners' => $this->crm->ownerOptions(),
            'clients' => $this->crm->clientOptions(),
            'projects' => $projects,
            'projectClients' => $projectClients,
            'canManage' => $this->canManage(),
            'notice' => Session::pullFlash('crm.notice'),
            'error' => Session::pullFlash('crm.error'),
            'stageErrors' => Session::pullFlash('crm.stage_errors', []),
            'opportunityErrors' => Session::pullFlash('crm.opportunity_errors', []),
            'opportunityValues' => Session::pullFlash('crm.opportunity_values', []),
            'conversionErrors' => Session::pullFlash('crm.conversion_errors', []),
            'conversionValues' => Session::pullFlash('crm.conversion_values', []),
            'activityErrors' => Session::pullFlash('crm.activity_errors', []),
            'activityValues' => Session::pullFlash('crm.activity_values', []),
        ]));
    }

    /** Crée une opportunité. */
    public function store(Request $request): Response
    {
        if (($denied = $this->requireManage()) !== null) {
            return $denied;
        }
        $ownerIds = array_map(static fn (array $owner): int => (int) $owner['id'], $this->crm->ownerOptions());
        $validated = $this->service->validate($request->all(), $ownerIds);
        if ($validated['errors'] !== []) {
            Session::flash('crm.opportunity_errors', $validated['errors']);
            Session::flash('crm.opportunity_values', $validated['values']);

            return $this->backToList($request);
        }
        try {
            $id = $this->crm->create($validated['values'], Auth::id());
            (new AuditLogger())->record('crm.opportunite_created', 'module', $id, [
                'stage' => $validated['values']['stage'],
                'origin' => $validated['values']['origin'],
            ], Auth::id());
            Session::flash('crm.notice', __('crm.opportunity_created'));

            return Response::redirect(url('/crm/opportunites/' . $id));
        } catch (Throwable $error) {
            error_log('Création d’une opportunité impossible : ' . $error->getMessage());
            Session::flash('crm.error', __('crm.save_failed'));
            Session::flash('crm.opportunity_values', $validated['values']);

            return $this->backToList($request);
        }
    }

    /** Met à jour la fiche complète d’une opportunité. */
    public function update(Request $request, string $id): Response
    {
        if (($denied = $this->requireManage()) !== null) {
            return $denied;
        }
        $row = $this->find($id);
        if ($row === null) {
            Session::flash('crm.error', __('crm.not_found'));

            return Response::redirect(url('/crm'));
        }
        $ownerIds = array_map(static fn (array $owner): int => (int) $owner['id'], $this->crm->ownerOptions());
        $validated = $this->service->validate($request->all(), $ownerIds);
        if ($validated['errors'] !== []) {
            Session::flash('crm.opportunity_errors', $validated['errors']);
            Session::flash('crm.opportunity_values', $validated['values']);

            return Response::redirect(url('/crm/opportunites/' . (int) $row['id'] . '#crm-edit'));
        }
        try {
            $this->crm->update((int) $row['id'], $validated['values']);
            (new AuditLogger())->record('crm.opportunite_updated', 'module', (int) $row['id'], [
                'stage' => $validated['values']['stage'],
            ], Auth::id());
            Session::flash('crm.notice', __('crm.opportunity_updated'));
        } catch (Throwable $error) {
            error_log('Mise à jour d’une opportunité impossible : ' . $error->getMessage());
            Session::flash('crm.error', __('crm.save_failed'));
        }

        return Response::redirect(url('/crm/opportunites/' . (int) $row['id']));
    }

    /** Change l’étape du pipeline depuis la fiche. */
    public function updateStage(Request $request, string $id): Response
    {
        if (($denied = $this->requireManage()) !== null) {
            return $denied;
        }
        $row = $this->find($id);
        if ($row === null) {
            Session::flash('crm.error', __('crm.not_found'));

            return Response::redirect(url('/crm'));
        }
        $validated = $this->service->validateStage($request->all());
        if ($validated['errors'] !== []) {
            Session::flash('crm.stage_errors', $validated['errors']);

            return Response::redirect(url('/crm/opportunites/' . (int) $row['id'] . '#crm-stage'));
        }
        try {
            $this->crm->updateStage((int) $row['id'], $validated['values']['stage'], $validated['values']['lost_reason']);
            (new AuditLogger())->record('crm.stage_changed', 'module', (int) $row['id'], [
                'from' => (string) $row['stage'],
                'to' => $validated['values']['stage'],
            ], Auth::id());
            Session::flash('crm.notice', __('crm.stage_changed'));
        } catch (Throwable $error) {
            error_log('Changement d’étape impossible : ' . $error->getMessage());
            Session::flash('crm.error', __('crm.save_failed'));
        }

        return Response::redirect(url('/crm/opportunites/' . (int) $row['id']));
    }

    /** Rattache l’opportunité à un client existant, et éventuellement à l’un de ses projets. */
    public function link(Request $request, string $id): Response
    {
        if (($denied = $this->requireManage()) !== null) {
            return $denied;
        }
        $row = $this->find($id);
        if ($row === null) {
            Session::flash('crm.error', __('crm.not_found'));

            return Response::redirect(url('/crm'));
        }
        $clients = $this->crm->clientOptions();
        $projects = $this->crm->projectOptions();
        $clientIds = array_map(static fn (array $client): int => (int) $client['id'], $clients);
        $projectClients = [];
        foreach ($projects as $project) {
            $projectClients[(int) $project['id']] = (int) $project['client_id'];
        }
        $validated = $this->service->validateConversion($request->all(), $clientIds, $projectClients);
        if ($validated['errors'] !== []) {
            Session::flash('crm.conversion_errors', $validated['errors']);
            Session::flash('crm.conversion_values', $validated['values']);

            return Response::redirect(url('/crm/opportunites/' . (int) $row['id'] . '#crm-conversion'));
        }
        $markWon = (bool) $validated['values']['mark_won'];
        $stage = $markWon ? 'won' : (string) $row['stage'];
        $lostReason = $markWon ? '' : (string) ($row['lost_reason'] ?? '');
        try {
            $this->crm->link(
                (int) $row['id'],
                (int) $validated['values']['client_id'],
                $validated['values']['project_id'],
                $stage,
                $lostReason
            );
            (new AuditLogger())->record('crm.opportunity_linked', 'module', (int) $row['id'], [
                'client_id' => $validated['values']['client_id'],
                'project_id' => $validated['values']['project_id'],
            ], Auth::id());
            Session::flash('crm.notice', __('crm.opportunity_linked'));
        } catch (Throwable $error) {
            error_log('Rattachement d’une opportunité impossible : ' . $error->getMessage());
            Session::flash('crm.error', __('crm.save_failed'));
        }

        return Response::redirect(url('/crm/opportunites/' . (int) $row['id']));
    }

    /** Ajoute une activité au journal. */
    public function storeActivity(Request $request, string $id): Response
    {
        if (($denied = $this->requireManage()) !== null) {
            return $denied;
        }
        $row = $this->find($id);
        if ($row === null) {
            Session::flash('crm.error', __('crm.not_found'));

            return Response::redirect(url('/crm'));
        }
        $validated = $this->service->validateActivity($request->all());
        if ($validated['errors'] !== []) {
            Session::flash('crm.activity_errors', $validated['errors']);
            Session::flash('crm.activity_values', $validated['values']);

            return Response::redirect(url('/crm/opportunites/' . (int) $row['id'] . '#crm-activity-form'));
        }
        try {
            $activityId = $this->crm->addActivity((int) $row['id'], $validated['values'], Auth::id());
            (new AuditLogger())->record('crm.activity_added', 'module', $activityId, [
                'opportunite_id' => (int) $row['id'],
                'kind' => $validated['values']['kind'],
            ], Auth::id());
            Session::flash('crm.notice', __('crm.activity_added'));
        } catch (Throwable $error) {
            error_log('Ajout d’une activité impossible : ' . $error->getMessage());
            Session::flash('crm.error', __('crm.save_failed'));
        }

        return Response::redirect(url('/crm/opportunites/' . (int) $row['id'] . '#crm-activities'));
    }

    /** Supprime une activité du journal. */
    public function deleteActivity(Request $request, string $id, string $activityId): Response
    {
        if (($denied = $this->requireManage()) !== null) {
            return $denied;
        }
        $row = $this->find($id);
        if ($row === null) {
            Session::flash('crm.error', __('crm.not_found'));

            return Response::redirect(url('/crm'));
        }
        if (!ctype_digit($activityId) || !$this->crm->deleteActivity((int) $row['id'], (int) $activityId)) {
            Session::flash('crm.error', __('crm.activity_not_found'));

            return Response::redirect(url('/crm/opportunites/' . (int) $row['id'] . '#crm-activities'));
        }
        (new AuditLogger())->record('crm.activity_deleted', 'module', (int) $activityId, [
            'opportunite_id' => (int) $row['id'],
        ], Auth::id());
        Session::flash('crm.notice', __('crm.activity_deleted'));

        return Response::redirect(url('/crm/opportunites/' . (int) $row['id'] . '#crm-activities'));
    }

    /** Supprime une opportunité et son journal (cascade). */
    public function destroy(Request $request, string $id): Response
    {
        if (($denied = $this->requireManage()) !== null) {
            return $denied;
        }
        $row = $this->find($id);
        if ($row === null) {
            Session::flash('crm.error', __('crm.not_found'));

            return Response::redirect(url('/crm'));
        }
        $this->crm->delete((int) $row['id']);
        (new AuditLogger())->record('crm.opportunite_deleted', 'module', (int) $row['id'], [], Auth::id());
        Session::flash('crm.notice', __('crm.opportunity_deleted'));

        return Response::redirect(url('/crm'));
    }

    /** Export CSV du pipeline filtré. */
    public function exportCsv(Request $request): Response
    {
        if (($denied = $this->requireView()) !== null) {
            return $denied;
        }
        $today = date('Y-m-d');
        $rows = array_map(
            fn (array $row): array => $this->decorate($row, $today),
            $this->crm->opportunities($this->filters($request), $today, $this->weekEnd($today))
        );

        $table = [[
            __('crm.title_label'),
            __('crm.prospect_name'),
            __('crm.client_type.label'),
            __('crm.email'),
            __('crm.phone'),
            __('crm.city'),
            __('crm.origin.label'),
            __('crm.stage.label'),
            __('crm.estimated_amount'),
            __('crm.expected_close_on'),
            __('crm.next_action_on'),
            __('crm.owner'),
            __('crm.client'),
            __('crm.project'),
            __('crm.last_activity'),
            __('crm.notes'),
            __('crm.lost_reason'),
        ]];
        foreach ($rows as $row) {
            $amount = CrmService::millimes($row['estimated_amount'] ?? '');
            $table[] = [
                (string) $row['title'],
                (string) $row['prospect_name'],
                __('crm.client_type.' . (string) $row['client_type']),
                (string) ($row['email'] ?? ''),
                (string) ($row['phone'] ?? ''),
                (string) ($row['city'] ?? ''),
                __('crm.origin.' . (string) $row['origin']),
                __('crm.stage.' . (string) $row['stage']),
                $amount === null ? '' : CrmService::format($amount),
                self::dateColumn($row['expected_close_on']),
                self::dateColumn($row['next_action_on']),
                (string) ($row['owner_name'] ?? ''),
                (string) ($row['client_name'] ?? ''),
                (string) ($row['project_name'] ?? ''),
                self::dateColumn($row['last_activity_on']),
                (string) ($row['notes'] ?? ''),
                (string) ($row['lost_reason'] ?? ''),
            ];
        }

        return Response::download(
            $this->service->csv($table),
            'prime-arch-pipeline-crm-' . $today . '.csv',
            'text/csv; charset=utf-8'
        );
    }

    /**
     * Enrichit une ligne : état de la relance, jours restants et montant en millimes.
     *
     * @param array<string,mixed> $row
     * @return array<string,mixed>
     */
    private function decorate(array $row, string $today): array
    {
        $nextAction = isset($row['next_action_on']) && $row['next_action_on'] !== null
            ? (string) $row['next_action_on']
            : null;
        $row['follow_state'] = CrmService::followUpState($nextAction, $today);
        $row['follow_days'] = CrmService::daysLeft($nextAction, $today);
        $row['amount_millimes'] = CrmService::millimes($row['estimated_amount'] ?? '');
        $row['is_closed'] = in_array((string) $row['stage'], CrmService::CLOSED_STAGES, true);
        $row['is_linked'] = ($row['client_id'] ?? null) !== null;

        return $row;
    }

    /**
     * Filtres de liste relus depuis la requête, chacun validé contre les valeurs connues.
     *
     * @return array<string,string>
     */
    private function filters(Request $request): array
    {
        $stage = (string) $request->query('stage', '');
        $origin = (string) $request->query('origin', '');
        $owner = (string) $request->query('owner', '');
        $follow = (string) $request->query('follow', '');
        $search = trim((string) $request->query('q', ''));
        if (mb_strlen($search, 'UTF-8') > self::MAX_SEARCH) {
            $search = mb_substr($search, 0, self::MAX_SEARCH, 'UTF-8');
        }
        $filters = [
            'stage' => in_array($stage, CrmService::STAGES, true) ? $stage : '',
            'origin' => in_array($origin, CrmService::ORIGINS, true) ? $origin : '',
            'owner_id' => $owner === 'none' || ctype_digit($owner) ? $owner : '',
            'follow' => in_array($follow, ['late', 'today', 'week', 'none'], true) ? $follow : '',
            'search' => $search,
        ];

        return array_filter($filters, static fn (string $value): bool => $value !== '');
    }

    /** Retour à la liste en conservant les filtres saisis (champs suffixés `_filter`). */
    private function backToList(Request $request): Response
    {
        $stage = (string) $request->input('stage_filter', '');
        $origin = (string) $request->input('origin_filter', '');
        $owner = (string) $request->input('owner_filter', '');
        $follow = (string) $request->input('follow_filter', '');
        $search = trim((string) $request->input('q_filter', ''));
        if (mb_strlen($search, 'UTF-8') > self::MAX_SEARCH) {
            $search = mb_substr($search, 0, self::MAX_SEARCH, 'UTF-8');
        }
        $query = array_filter([
            'stage' => in_array($stage, CrmService::STAGES, true) ? $stage : '',
            'origin' => in_array($origin, CrmService::ORIGINS, true) ? $origin : '',
            'owner' => $owner === 'none' || ctype_digit($owner) ? $owner : '',
            'follow' => in_array($follow, ['late', 'today', 'week', 'none'], true) ? $follow : '',
            'q' => $search,
        ], static fn (string $value): bool => $value !== '');

        return Response::redirect(url('/crm' . ($query !== [] ? '?' . http_build_query($query) : '')));
    }

    /** Dernier jour de la fenêtre d’alerte, aujourd’hui compris. */
    private function weekEnd(string $today): string
    {
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $today);
        if ($date === false) {
            return $today;
        }

        return $date->modify('+' . (self::WEEK_DAYS - 1) . ' days')->format('Y-m-d');
    }

    private static function dateColumn(mixed $value): string
    {
        $text = is_string($value) ? trim($value) : '';
        $timestamp = $text === '' ? false : strtotime($text);

        return $timestamp === false ? '' : date('d/m/Y', $timestamp);
    }

    /** @return array<string,mixed>|null */
    private function find(string $id): ?array
    {
        if (!ctype_digit($id) || (int) $id < 1) {
            return null;
        }

        return $this->crm->find((int) $id);
    }

    /** @return array<string,mixed> */
    private function context(string $titleKey): array
    {
        return (new ViewContext())->authenticated(__($titleKey), 'crm-opportunites');
    }

    private function requireView(): ?Response
    {
        return Auth::can('clients.view') || Auth::can('projects.view')
            ? null
            : Response::html(__('common.forbidden'), 403);
    }

    private function requireManage(): ?Response
    {
        return $this->canManage() ? null : Response::html(__('common.forbidden'), 403);
    }

    private function canManage(): bool
    {
        return Auth::can('clients.manage') || Auth::can('projects.manage');
    }
}
