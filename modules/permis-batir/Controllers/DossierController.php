<?php
declare(strict_types=1);

namespace Modules\PermisBatir\Controllers;

use App\Core\Auth;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\View;
use App\Services\AuditLogger;
use App\Services\ViewContext;
use Modules\PermisBatir\Models\DossierRepository;
use Modules\PermisBatir\Services\DossierService;
use Throwable;

/**
 * Écrans des dossiers de permis de bâtir.
 *
 * Droits réutilisés du cœur : `projects.view` en lecture, `projects.manage` en écriture.
 */
final class DossierController
{
    private DossierRepository $dossiers;
    private DossierService $service;

    public function __construct(?DossierRepository $dossiers = null, ?DossierService $service = null)
    {
        $this->dossiers = $dossiers ?? new DossierRepository();
        $this->service = $service ?? new DossierService($this->dossiers);
    }

    public function index(Request $request): Response
    {
        if (($denied = $this->requireView()) !== null) {
            return $denied;
        }

        $filters = [
            'q' => self::queryText($request, 'q', 100),
            'status' => self::queryText($request, 'status', 20),
            'year' => self::queryText($request, 'year', 4),
        ];
        if (!in_array($filters['status'], array_merge([''], DossierService::STATUSES), true)) {
            $filters['status'] = '';
        }
        if ($filters['year'] !== '' && !ctype_digit($filters['year'])) {
            $filters['year'] = '';
        }
        $page = self::pageNumber($request->query('page'));
        $perPage = 20;
        $result = $this->dossiers->paginate($filters, $perPage, ($page - 1) * $perPage);

        return Response::html(View::renderModule('permis-batir', 'index', $this->context('permis-batir.index_title') + [
            'dossiers' => $result['items'],
            'total' => $result['total'],
            'page' => $page,
            'pageCount' => max(1, (int) ceil($result['total'] / $perPage)),
            'filters' => $filters,
            'years' => $this->dossiers->years(),
            'statistics' => $this->dossiers->statistics(),
            'canManage' => $this->canManage(),
            'notice' => Session::pullFlash('permis.notice'),
            'error' => Session::pullFlash('permis.error'),
        ]));
    }

    public function createForm(Request $request): Response
    {
        if (($denied = $this->requireManage()) !== null) {
            return $denied;
        }

        return Response::html(View::renderModule('permis-batir', 'form', $this->context('permis-batir.create') + [
            'dossier' => null,
            'values' => [
                'project_id' => '',
                'commune' => '',
                'status' => 'preparation',
                'deposit_number' => '',
                'deposit_date' => '',
                'decision_number' => '',
                'decision_date' => '',
                'deadline_date' => '',
                'notes' => '',
            ],
            'errors' => [],
            'projects' => $this->dossiers->projectsForSelect(),
            'action' => url('/permis'),
            'cancelUrl' => url('/permis'),
        ]));
    }

    public function create(Request $request): Response
    {
        if (($denied = $this->requireManage()) !== null) {
            return $denied;
        }

        $validated = $this->service->validate($request->all());
        if ($validated['errors'] !== []) {
            return Response::html(View::renderModule('permis-batir', 'form', $this->context('permis-batir.create') + [
                'dossier' => null,
                'values' => $validated['values'],
                'errors' => $validated['errors'],
                'projects' => $this->dossiers->projectsForSelect(),
                'action' => url('/permis'),
                'cancelUrl' => url('/permis'),
            ]));
        }

        $values = $validated['values'];
        $values['reference'] = $this->service->nextReference();
        try {
            $id = $this->dossiers->create($values, Auth::id());
            $this->dossiers->seedPieces($id, $this->service->checklist());
            (new AuditLogger())->record('permis.dossier_created', 'module', $id, ['status' => $values['status']], Auth::id());
        } catch (Throwable $error) {
            error_log('Création d’un dossier de permis impossible : ' . $error->getMessage());
            Session::flash('permis.error', __('permis.save_failed'));
            return Response::redirect(url('/permis'));
        }

        Session::flash('permis.notice', __('permis.created'));
        return Response::redirect(url('/permis/' . $id));
    }

    public function show(Request $request, string $id): Response
    {
        if (($denied = $this->requireView()) !== null) {
            return $denied;
        }
        $dossier = ctype_digit($id) ? $this->dossiers->find((int) $id) : null;
        if ($dossier === null) {
            return Response::html(__('permis.not_found'), 404);
        }

        return Response::html(View::renderModule('permis-batir', 'show', $this->context('permis-batir.show_title') + [
            'dossier' => $dossier,
            'pieces' => $this->dossiers->pieces((int) $dossier['id']),
            'events' => $this->dossiers->events((int) $dossier['id']),
            'canManage' => $this->canManage(),
            'statuses' => DossierService::STATUSES,
            'eventKinds' => DossierService::EVENT_KINDS,
            'notice' => Session::pullFlash('permis.notice'),
            'error' => Session::pullFlash('permis.error'),
            'eventErrors' => Session::pullFlash('permis.event_errors', []),
            'eventValues' => Session::pullFlash('permis.event_values', []),
        ]));
    }

    public function editForm(Request $request, string $id): Response
    {
        if (($denied = $this->requireManage()) !== null) {
            return $denied;
        }
        $dossier = ctype_digit($id) ? $this->dossiers->find((int) $id) : null;
        if ($dossier === null) {
            return Response::html(__('permis.not_found'), 404);
        }

        return Response::html(View::renderModule('permis-batir', 'form', $this->context('permis-batir.edit') + [
            'dossier' => $dossier,
            'values' => [
                'project_id' => (string) $dossier['project_id'],
                'commune' => (string) $dossier['commune'],
                'status' => (string) $dossier['status'],
                'deposit_number' => (string) ($dossier['deposit_number'] ?? ''),
                'deposit_date' => (string) ($dossier['deposit_date'] ?? ''),
                'decision_number' => (string) ($dossier['decision_number'] ?? ''),
                'decision_date' => (string) ($dossier['decision_date'] ?? ''),
                'deadline_date' => (string) ($dossier['deadline_date'] ?? ''),
                'notes' => (string) ($dossier['notes'] ?? ''),
            ],
            'errors' => [],
            'projects' => [],
            'action' => url('/permis/' . (int) $dossier['id']),
            'cancelUrl' => url('/permis/' . (int) $dossier['id']),
        ]));
    }

    public function update(Request $request, string $id): Response
    {
        if (($denied = $this->requireManage()) !== null) {
            return $denied;
        }
        $dossier = ctype_digit($id) ? $this->dossiers->find((int) $id) : null;
        if ($dossier === null) {
            return Response::html(__('permis.not_found'), 404);
        }

        $validated = $this->service->validate($request->all(), true);
        if ($validated['errors'] !== []) {
            return Response::html(View::renderModule('permis-batir', 'form', $this->context('permis-batir.edit') + [
                'dossier' => $dossier,
                'values' => $validated['values'],
                'errors' => $validated['errors'],
                'projects' => [],
                'action' => url('/permis/' . (int) $dossier['id']),
                'cancelUrl' => url('/permis/' . (int) $dossier['id']),
            ]));
        }

        try {
            $this->dossiers->update((int) $dossier['id'], $validated['values']);
            (new AuditLogger())->record('permis.dossier_updated', 'module', (int) $dossier['id'], ['status' => $validated['values']['status']], Auth::id());
        } catch (Throwable $error) {
            error_log('Mise à jour d’un dossier de permis impossible : ' . $error->getMessage());
            Session::flash('permis.error', __('permis.save_failed'));
        }

        Session::flash('permis.notice', __('permis.updated'));
        return Response::redirect(url('/permis/' . (int) $dossier['id']));
    }

    public function togglePiece(Request $request, string $id, string $pieceId): Response
    {
        if (($denied = $this->requireManage()) !== null) {
            return $denied;
        }
        $dossier = ctype_digit($id) ? $this->dossiers->find((int) $id) : null;
        if ($dossier === null) {
            return Response::html(__('permis.not_found'), 404);
        }
        if (!ctype_digit($pieceId)) {
            return Response::html(__('permis.not_found'), 404);
        }

        $deposited = (string) $request->input('deposited', '0') === '1';
        $updated = $this->dossiers->togglePiece((int) $dossier['id'], (int) $pieceId, $deposited);
        if (!$updated) {
            Session::flash('permis.error', __('permis.piece_not_found'));
        }

        return Response::redirect(url('/permis/' . (int) $dossier['id']) . '#pieces');
    }

    public function addEvent(Request $request, string $id): Response
    {
        if (($denied = $this->requireManage()) !== null) {
            return $denied;
        }
        $dossier = ctype_digit($id) ? $this->dossiers->find((int) $id) : null;
        if ($dossier === null) {
            return Response::html(__('permis.not_found'), 404);
        }

        $validated = $this->service->validateEvent($request->all());
        if ($validated['errors'] !== []) {
            Session::flash('permis.event_errors', $validated['errors']);
            Session::flash('permis.event_values', $validated['values']);
            return Response::redirect(url('/permis/' . (int) $dossier['id']) . '#history');
        }

        $values = $validated['values'];
        $this->dossiers->addEvent(
            (int) $dossier['id'],
            (string) $values['event_date'],
            (string) $values['kind'],
            (string) $values['summary'],
            (string) $values['details'],
            Auth::id()
        );
        Session::flash('permis.notice', __('permis.event_added'));

        return Response::redirect(url('/permis/' . (int) $dossier['id']) . '#history');
    }

    public function exportCsv(Request $request): Response
    {
        if (($denied = $this->requireView()) !== null) {
            return $denied;
        }
        $filters = [
            'q' => self::queryText($request, 'q', 100),
            'status' => self::queryText($request, 'status', 20),
            'year' => self::queryText($request, 'year', 4),
        ];
        if (!in_array($filters['status'], array_merge([''], DossierService::STATUSES), true)) {
            $filters['status'] = '';
        }
        if ($filters['year'] !== '' && !ctype_digit($filters['year'])) {
            $filters['year'] = '';
        }

        $rows = [[
            __('permis.reference'),
            __('permis.project'),
            __('permis.client'),
            __('permis.commune'),
            __('permis.status.label'),
            __('permis.deposit_number'),
            __('permis.deposit_date'),
            __('permis.decision_number'),
            __('permis.decision_date'),
            __('permis.deadline_date'),
            __('permis.pieces_deposited'),
        ]];
        foreach ($this->dossiers->exportRows($filters) as $row) {
            $rows[] = [
                (string) $row['reference'],
                (string) $row['project_name'],
                (string) $row['client_name'],
                (string) $row['commune'],
                __('permis.status.' . (string) $row['status']),
                (string) ($row['deposit_number'] ?? ''),
                (string) ($row['deposit_date'] ?? ''),
                (string) ($row['decision_number'] ?? ''),
                (string) ($row['decision_date'] ?? ''),
                (string) ($row['deadline_date'] ?? ''),
                (string) $row['pieces_deposited'] . '/' . (string) $row['pieces_total'],
            ];
        }

        return Response::download(
            self::encodeCsv($rows),
            'prime-arch-permis-' . date('Ymd-His') . '.csv',
            'text/csv; charset=utf-8'
        );
    }

    /** Contexte de navigation partagé (menu, profil, fil d’Ariane). */
    /** @return array<string,mixed> */
    private function context(string $titleKey): array
    {
        return (new ViewContext())->authenticated(__($titleKey), 'permis-batir');
    }

    private function requireView(): ?Response
    {
        return Auth::can('projects.view') ? null : Response::html(__('common.forbidden'), 403);
    }

    private function requireManage(): ?Response
    {
        if (!$this->canManage()) {
            return Response::html(__('common.forbidden'), 403);
        }

        return null;
    }

    private function canManage(): bool
    {
        return Auth::can('projects.manage');
    }

    /**
     * @param array<int,array<int,string>> $rows
     */
    private static function encodeCsv(array $rows): string
    {
        $stream = fopen('php://temp/maxmemory:2097152', 'w+b');
        if ($stream === false) {
            return '';
        }
        fwrite($stream, "\xEF\xBB\xBF");
        foreach ($rows as $row) {
            $cells = array_map(static function (string $value): string {
                // Neutralise les formules d’un tableur avant l’échappement CSV.
                return $value !== '' && preg_match("/^[\x00-\x20]*[=+@-]/", $value) === 1 ? "'" . $value : $value;
            }, $row);
            fputcsv($stream, $cells, ';', '"', '');
        }
        rewind($stream);
        $contents = stream_get_contents($stream);
        fclose($stream);

        return is_string($contents) ? $contents : '';
    }

    private static function queryText(Request $request, string $key, int $maxLength): string
    {
        $value = $request->query($key);

        return is_string($value) ? mb_substr(trim($value), 0, $maxLength, 'UTF-8') : '';
    }

    private static function pageNumber(mixed $value): int
    {
        $page = is_string($value) && ctype_digit($value) ? (int) $value : 1;

        return max(1, min($page, 10000));
    }
}
