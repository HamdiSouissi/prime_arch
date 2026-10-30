<?php
declare(strict_types=1);

namespace Modules\SuiviChantier\Controllers;

use App\Core\Auth;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\View;
use App\Services\AuditLogger;
use App\Services\CabinetSettings;
use App\Services\ViewContext;
use Modules\SuiviChantier\Models\ChantierRepository;
use Modules\SuiviChantier\Services\ChantierService;
use Throwable;

/**
 * Journal de chantier, registre des réserves et réceptions.
 *
 * Droits réutilisés du cœur : `projects.view` en lecture, `projects.manage` en écriture.
 */
final class ChantierController
{
    private ChantierRepository $chantier;
    private ChantierService $service;

    public function __construct(?ChantierRepository $chantier = null, ?ChantierService $service = null)
    {
        $this->chantier = $chantier ?? new ChantierRepository();
        $this->service = $service ?? new ChantierService();
    }

    public function index(Request $request): Response
    {
        if (($denied = $this->requireView()) !== null) {
            return $denied;
        }
        $filters = [
            'q' => self::queryText($request, 'q', 100),
            'only_open' => (string) $request->query('open', '') === '1',
        ];

        return Response::html(View::renderModule('suivi-chantier', 'index', $this->context('chantier.index_title') + [
            'projects' => $this->chantier->projects($filters),
            'filters' => $filters,
            'statistics' => $this->chantier->statistics(),
            'canManage' => $this->canManage(),
            'notice' => Session::pullFlash('chantier.notice'),
            'error' => Session::pullFlash('chantier.error'),
        ]));
    }

    public function show(Request $request, string $projectId): Response
    {
        if (($denied = $this->requireView()) !== null) {
            return $denied;
        }
        $project = ctype_digit($projectId) ? $this->chantier->findProject((int) $projectId) : null;
        if ($project === null) {
            return Response::html(__('chantier.not_found'), 404);
        }
        $id = (int) $project['id'];

        return Response::html(View::renderModule('suivi-chantier', 'show', $this->context('chantier.show_title') + [
            'project' => $project,
            'visits' => $this->chantier->visits($id),
            'reserves' => $this->chantier->reserves($id),
            'receptions' => $this->chantier->receptions($id),
            'canManage' => $this->canManage(),
            'weathers' => ChantierService::WEATHERS,
            'severities' => ChantierService::SEVERITIES,
            'notice' => Session::pullFlash('chantier.notice'),
            'error' => Session::pullFlash('chantier.error'),
            'visitErrors' => Session::pullFlash('chantier.visit_errors', []),
            'visitValues' => Session::pullFlash('chantier.visit_values', []),
            'reserveErrors' => Session::pullFlash('chantier.reserve_errors', []),
            'reserveValues' => Session::pullFlash('chantier.reserve_values', []),
            'receptionErrors' => Session::pullFlash('chantier.reception_errors', []),
            'receptionValues' => Session::pullFlash('chantier.reception_values', []),
        ]));
    }

    public function addVisit(Request $request, string $projectId): Response
    {
        if (($denied = $this->requireManage()) !== null) {
            return $denied;
        }
        $project = $this->findProjectOrNull($projectId);
        if ($project === null) {
            return Response::html(__('chantier.not_found'), 404);
        }

        $validated = $this->service->validateVisit($request->all());
        if ($validated['errors'] !== []) {
            Session::flash('chantier.visit_errors', $validated['errors']);
            Session::flash('chantier.visit_values', $validated['values']);

            return Response::redirect(url('/chantier/' . (int) $project['id']) . '#journal');
        }

        $values = $validated['values'];
        $workforce = $values['workforce'] === '' ? null : (int) $values['workforce'];
        $this->chantier->addVisit(
            (int) $project['id'],
            (string) $values['visit_date'],
            (string) $values['weather'],
            $workforce,
            (string) $values['observations'],
            Auth::id()
        );
        Session::flash('chantier.notice', __('chantier.visit_added'));

        return Response::redirect(url('/chantier/' . (int) $project['id']) . '#journal');
    }

    public function deleteVisit(Request $request, string $projectId, string $visitId): Response
    {
        if (($denied = $this->requireManage()) !== null) {
            return $denied;
        }
        $project = $this->findProjectOrNull($projectId);
        if ($project === null || !ctype_digit($visitId)) {
            return Response::html(__('chantier.not_found'), 404);
        }
        if (!$this->chantier->deleteVisit((int) $project['id'], (int) $visitId)) {
            Session::flash('chantier.error', __('chantier.visit_not_found'));
        } else {
            Session::flash('chantier.notice', __('chantier.visit_deleted'));
        }

        return Response::redirect(url('/chantier/' . (int) $project['id']) . '#journal');
    }

    public function addReserve(Request $request, string $projectId): Response
    {
        if (($denied = $this->requireManage()) !== null) {
            return $denied;
        }
        $project = $this->findProjectOrNull($projectId);
        if ($project === null) {
            return Response::html(__('chantier.not_found'), 404);
        }

        $validated = $this->service->validateReserve($request->all());
        if ($validated['errors'] !== []) {
            Session::flash('chantier.reserve_errors', $validated['errors']);
            Session::flash('chantier.reserve_values', $validated['values']);

            return Response::redirect(url('/chantier/' . (int) $project['id']) . '#reserves');
        }

        $values = $validated['values'];
        $values['reference'] = $this->chantier->nextReserveReference((int) $project['id']);
        $values['visit_id'] = $values['visit_id'] !== '' ? (int) $values['visit_id'] : null;
        $this->chantier->addReserve((int) $project['id'], $values, Auth::id());
        Session::flash('chantier.notice', __('chantier.reserve_added'));

        return Response::redirect(url('/chantier/' . (int) $project['id']) . '#reserves');
    }

    public function updateReserve(Request $request, string $projectId, string $reserveId): Response
    {
        if (($denied = $this->requireManage()) !== null) {
            return $denied;
        }
        $project = $this->findProjectOrNull($projectId);
        if ($project === null || !ctype_digit($reserveId)) {
            return Response::html(__('chantier.not_found'), 404);
        }

        $status = (string) $request->input('status', '');
        if (!in_array($status, ChantierService::RESERVE_STATUSES, true)) {
            return Response::html(__('chantier.invalid_status'), 422);
        }
        $note = $request->input('lift_note');
        $updated = $this->chantier->updateReserveStatus(
            (int) $project['id'],
            (int) $reserveId,
            $status,
            is_string($note) ? trim($note) : null
        );
        if ($updated) {
            (new AuditLogger())->record('chantier.reserve_updated', 'module', (int) $reserveId, ['status' => $status], Auth::id());
            Session::flash('chantier.notice', __('chantier.reserve_updated'));
        } else {
            Session::flash('chantier.error', __('chantier.reserve_not_found'));
        }

        return Response::redirect(url('/chantier/' . (int) $project['id']) . '#reserves');
    }

    public function addReception(Request $request, string $projectId): Response
    {
        if (($denied = $this->requireManage()) !== null) {
            return $denied;
        }
        $project = $this->findProjectOrNull($projectId);
        if ($project === null) {
            return Response::html(__('chantier.not_found'), 404);
        }

        $validated = $this->service->validateReception($request->all());
        if ($validated['errors'] !== []) {
            Session::flash('chantier.reception_errors', $validated['errors']);
            Session::flash('chantier.reception_values', $validated['values']);

            return Response::redirect(url('/chantier/' . (int) $project['id']) . '#receptions');
        }

        $values = $validated['values'];
        $receptionId = $this->chantier->addReception(
            (int) $project['id'],
            (string) $values['kind'],
            (string) $values['reception_date'],
            (string) $values['notes'],
            Auth::id()
        );
        Session::flash('chantier.notice', __('chantier.reception_added'));

        return Response::redirect(url('/chantier/' . (int) $project['id'] . '/receptions/' . $receptionId));
    }

    /** Procès-verbal imprimable (le navigateur produit ensuite le PDF). */
    public function printReception(Request $request, string $projectId, string $receptionId): Response
    {
        if (($denied = $this->requireView()) !== null) {
            return $denied;
        }
        $project = $this->findProjectOrNull($projectId);
        if ($project === null || !ctype_digit($receptionId)) {
            return Response::html(__('chantier.not_found'), 404);
        }
        $reception = $this->chantier->findReception((int) $project['id'], (int) $receptionId);
        if ($reception === null) {
            return Response::html(__('chantier.not_found'), 404);
        }

        $cabinetName = '';
        try {
            $cabinetName = (string) ((new CabinetSettings())->get()['name'] ?? '');
        } catch (Throwable) {
            $cabinetName = '';
        }

        return Response::html(View::renderModule('suivi-chantier', 'pv', [
            'project' => $project,
            'reception' => $reception,
            'reserves' => $this->chantier->openReserves((int) $project['id']),
            'cabinetName' => $cabinetName,
        ], null));
    }

    /** @return array<string,mixed> */
    private function context(string $titleKey): array
    {
        return (new ViewContext())->authenticated(__($titleKey), 'suivi-chantier');
    }

    /** @return array<string,mixed>|null */
    private function findProjectOrNull(string $projectId): ?array
    {
        return ctype_digit($projectId) ? $this->chantier->findProject((int) $projectId) : null;
    }

    private function requireView(): ?Response
    {
        return Auth::can('projects.view') ? null : Response::html(__('common.forbidden'), 403);
    }

    private function requireManage(): ?Response
    {
        return $this->canManage() ? null : Response::html(__('common.forbidden'), 403);
    }

    private function canManage(): bool
    {
        return Auth::can('projects.manage');
    }

    private static function queryText(Request $request, string $key, int $maxLength): string
    {
        $value = $request->query($key);

        return is_string($value) ? mb_substr(trim($value), 0, $maxLength, 'UTF-8') : '';
    }
}
