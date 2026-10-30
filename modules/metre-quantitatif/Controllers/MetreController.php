<?php
declare(strict_types=1);

namespace Modules\MetreQuantitatif\Controllers;

use App\Core\Auth;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\View;
use App\Services\AuditLogger;
use App\Services\CabinetSettings;
use App\Services\ViewContext;
use Modules\MetreQuantitatif\Models\MetreRepository;
use Modules\MetreQuantitatif\Services\MetreService;
use PDOException;
use Throwable;

/**
 * Avant-métré, DQE et bibliothèque de prix.
 *
 * Droits réutilisés du cœur : `projects.view` en lecture, `projects.manage` en écriture.
 * Les prix saisis sont ceux du cabinet : le module ne contient aucun barème.
 */
final class MetreController
{
    private MetreRepository $metre;
    private MetreService $service;

    public function __construct(?MetreRepository $metre = null, ?MetreService $service = null)
    {
        $this->metre = $metre ?? new MetreRepository();
        $this->service = $service ?? new MetreService();
    }

    /** Liste des projets avec le montant de leur DQE. */
    public function index(Request $request): Response
    {
        if (($denied = $this->requireView()) !== null) {
            return $denied;
        }
        $filters = ['q' => self::queryText($request, 'q', 100)];

        return Response::html(View::renderModule('metre-quantitatif', 'index', $this->context('metre.index_title') + [
            'projects' => $this->metre->projects($filters),
            'statistics' => $this->metre->statistics(),
            'filters' => $filters,
            'canManage' => $this->canManage(),
            'notice' => Session::pullFlash('metre.notice'),
            'error' => Session::pullFlash('metre.error'),
        ]));
    }

    /** Écran de saisie : lots, postes, totaux et marges. */
    public function show(Request $request, string $projectId): Response
    {
        if (($denied = $this->requireView()) !== null) {
            return $denied;
        }
        $project = $this->findProjectOrNull($projectId);
        if ($project === null) {
            return Response::html(__('metre.not_found'), 404);
        }
        $id = (int) $project['id'];
        $lots = $this->metre->lots($id);
        $postesByLot = $this->groupPostes($this->metre->postes($id));

        return Response::html(View::renderModule('metre-quantitatif', 'show', $this->context('metre.show_title') + [
            'project' => $project,
            'summary' => $this->service->buildSummary($lots, $postesByLot),
            'library' => $this->metre->library(['q' => '', 'active_only' => true]),
            'units' => MetreService::UNITS,
            'suggestedLots' => MetreService::SUGGESTED_LOTS,
            'canManage' => $this->canManage(),
            'notice' => Session::pullFlash('metre.notice'),
            'error' => Session::pullFlash('metre.error'),
            'lotErrors' => Session::pullFlash('metre.lot_errors', []),
            'lotValues' => Session::pullFlash('metre.lot_values', []),
            'posteErrors' => Session::pullFlash('metre.poste_errors', []),
            'posteValues' => Session::pullFlash('metre.poste_values', []),
        ]));
    }

    /** Ajoute un lot au projet. */
    public function addLot(Request $request, string $projectId): Response
    {
        if (($denied = $this->requireManage()) !== null) {
            return $denied;
        }
        $project = $this->findProjectOrNull($projectId);
        if ($project === null) {
            return Response::html(__('metre.not_found'), 404);
        }
        $id = (int) $project['id'];
        $validated = $this->service->validateLot($request->all());
        if ($validated['errors'] !== []) {
            Session::flash('metre.lot_errors', $validated['errors']);
            Session::flash('metre.lot_values', $validated['values']);

            return Response::redirect(url('/metre/' . $id) . '#lots');
        }

        $values = $validated['values'];
        if ((int) $values['sort_order'] === 0) {
            $values['sort_order'] = $this->metre->nextLotOrder($id);
        }

        try {
            $lotId = $this->metre->createLot($id, $values, Auth::id());
        } catch (PDOException) {
            Session::flash('metre.error', __('metre.lot_name_taken'));
            Session::flash('metre.lot_values', $validated['values']);

            return Response::redirect(url('/metre/' . $id) . '#lots');
        }

        (new AuditLogger())->record('metre.lot_created', 'module', $lotId, ['project_id' => $id], Auth::id());
        Session::flash('metre.notice', __('metre.lot_added'));

        return Response::redirect(url('/metre/' . $id) . '#lot-' . $lotId);
    }

    /** Supprime un lot et tous ses postes. */
    public function deleteLot(Request $request, string $projectId, string $lotId): Response
    {
        if (($denied = $this->requireManage()) !== null) {
            return $denied;
        }
        $project = $this->findProjectOrNull($projectId);
        if ($project === null || !ctype_digit($lotId)) {
            return Response::html(__('metre.not_found'), 404);
        }
        $id = (int) $project['id'];
        if (!$this->metre->deleteLot($id, (int) $lotId)) {
            Session::flash('metre.error', __('metre.lot_not_found'));
        } else {
            (new AuditLogger())->record('metre.lot_deleted', 'module', (int) $lotId, ['project_id' => $id], Auth::id());
            Session::flash('metre.notice', __('metre.lot_deleted'));
        }

        return Response::redirect(url('/metre/' . $id) . '#lots');
    }

    /** Ajoute un poste à un lot. */
    public function addPoste(Request $request, string $projectId, string $lotId): Response
    {
        if (($denied = $this->requireManage()) !== null) {
            return $denied;
        }
        $project = $this->findProjectOrNull($projectId);
        if ($project === null || !ctype_digit($lotId)) {
            return Response::html(__('metre.not_found'), 404);
        }
        $id = (int) $project['id'];
        $lot = $this->metre->findLot((int) $lotId);
        if ($lot === null || (int) $lot['project_id'] !== $id) {
            return Response::html(__('metre.lot_not_found'), 404);
        }

        $libraryItem = $this->libraryFromInput($request);
        $validated = $this->service->validatePoste($request->all(), $libraryItem);
        if ($validated['errors'] !== []) {
            Session::flash('metre.poste_errors', $validated['errors']);
            Session::flash('metre.poste_values', $validated['values']);

            return Response::redirect(url('/metre/' . $id) . '#lot-' . (int) $lotId);
        }

        $values = $validated['values'];
        if ((int) $values['sort_order'] === 0) {
            $values['sort_order'] = $this->metre->nextPosteOrder((int) $lotId);
        }
        $posteId = $this->metre->createPoste((int) $lotId, $values);
        (new AuditLogger())->record('metre.poste_created', 'module', $posteId, ['project_id' => $id], Auth::id());
        Session::flash('metre.notice', __('metre.poste_added'));

        return Response::redirect(url('/metre/' . $id) . '#lot-' . (int) $lotId);
    }

    /** Formulaire de modification d’un poste. */
    public function editPoste(Request $request, string $projectId, string $posteId): Response
    {
        if (($denied = $this->requireView()) !== null) {
            return $denied;
        }
        $project = $this->findProjectOrNull($projectId);
        if ($project === null || !ctype_digit($posteId)) {
            return Response::html(__('metre.not_found'), 404);
        }
        $id = (int) $project['id'];
        $poste = $this->metre->findPoste((int) $posteId);
        if ($poste === null || (int) $poste['project_id'] !== $id) {
            return Response::html(__('metre.poste_not_found'), 404);
        }
        $lot = $this->metre->findLot((int) $poste['lot_id']);
        $errors = Session::pullFlash('metre.poste_errors', []);
        $values = Session::pullFlash('metre.poste_values', []);
        if (!is_array($values) || $values === []) {
            $values = $poste;
        }

        return Response::html(View::renderModule('metre-quantitatif', 'poste', $this->context('metre.poste_edit_title') + [
            'project' => $project,
            'lot' => $lot,
            'poste' => $poste,
            'values' => $values,
            'errors' => $errors,
            'units' => MetreService::UNITS,
            'canManage' => $this->canManage(),
            'notice' => Session::pullFlash('metre.notice'),
            'error' => Session::pullFlash('metre.error'),
        ]));
    }

    /** Met à jour un poste existant. */
    public function updatePoste(Request $request, string $projectId, string $posteId): Response
    {
        if (($denied = $this->requireManage()) !== null) {
            return $denied;
        }
        $project = $this->findProjectOrNull($projectId);
        if ($project === null || !ctype_digit($posteId)) {
            return Response::html(__('metre.not_found'), 404);
        }
        $id = (int) $project['id'];
        $poste = $this->metre->findPoste((int) $posteId);
        if ($poste === null || (int) $poste['project_id'] !== $id) {
            return Response::html(__('metre.poste_not_found'), 404);
        }

        $validated = $this->service->validatePoste($request->all());
        if ($validated['errors'] !== []) {
            Session::flash('metre.poste_errors', $validated['errors']);
            Session::flash('metre.poste_values', $validated['values'] + ['id' => (int) $posteId]);

            return Response::redirect(url('/metre/' . $id) . '#poste-' . (int) $posteId);
        }

        $this->metre->updatePoste((int) $posteId, $validated['values']);
        (new AuditLogger())->record('metre.poste_updated', 'module', (int) $posteId, ['project_id' => $id], Auth::id());
        Session::flash('metre.notice', __('metre.poste_updated'));

        return Response::redirect(url('/metre/' . $id) . '#poste-' . (int) $posteId);
    }

    /** Supprime un poste. */
    public function deletePoste(Request $request, string $projectId, string $posteId): Response
    {
        if (($denied = $this->requireManage()) !== null) {
            return $denied;
        }
        $project = $this->findProjectOrNull($projectId);
        if ($project === null || !ctype_digit($posteId)) {
            return Response::html(__('metre.not_found'), 404);
        }
        $id = (int) $project['id'];
        if (!$this->metre->deletePoste($id, (int) $posteId)) {
            Session::flash('metre.error', __('metre.poste_not_found'));
        } else {
            (new AuditLogger())->record('metre.poste_deleted', 'module', (int) $posteId, ['project_id' => $id], Auth::id());
            Session::flash('metre.notice', __('metre.poste_deleted'));
        }

        return Response::redirect(url('/metre/' . $id) . '#lots');
    }

    /** DQE imprimable (le navigateur produit ensuite le PDF). */
    public function document(Request $request, string $projectId): Response
    {
        if (($denied = $this->requireView()) !== null) {
            return $denied;
        }
        $project = $this->findProjectOrNull($projectId);
        if ($project === null) {
            return Response::html(__('metre.not_found'), 404);
        }
        $id = (int) $project['id'];
        $lots = $this->metre->lots($id);
        $summary = $this->service->buildSummary($lots, $this->groupPostes($this->metre->postes($id)));

        $cabinet = [];
        try {
            $cabinet = (new CabinetSettings())->get();
        } catch (Throwable) {
            $cabinet = [];
        }

        return Response::html(View::renderModule('metre-quantitatif', 'dqe', [
            'project' => $project,
            'summary' => $summary,
            'cabinet' => $cabinet,
        ], null));
    }

    /** Export CSV du DQE (lots, postes, totaux). */
    public function exportCsv(Request $request, string $projectId): Response
    {
        if (($denied = $this->requireView()) !== null) {
            return $denied;
        }
        $project = $this->findProjectOrNull($projectId);
        if ($project === null) {
            return Response::html(__('metre.not_found'), 404);
        }
        $id = (int) $project['id'];
        $summary = $this->service->buildSummary($this->metre->lots($id), $this->groupPostes($this->metre->postes($id)));

        $rows = [[
            __('metre.lot'),
            __('metre.code'),
            __('metre.designation'),
            __('metre.unit'),
            __('metre.quantity'),
            __('metre.unit_price'),
            __('metre.line_total'),
            __('metre.margin'),
        ]];
        foreach ($summary['lots'] as $lot) {
            foreach ($lot['postes'] as $poste) {
                $rows[] = [
                    (string) $lot['lot']['name'],
                    (string) ($poste['code'] ?? ''),
                    (string) $poste['designation'],
                    MetreService::unitLabel((string) $poste['unit']),
                    (string) $poste['quantity'],
                    (string) $poste['unit_price'],
                    (string) $poste['total'],
                    (string) $poste['margin_amount'],
                ];
            }
        }
        $rows[] = [__('metre.grand_total'), '', '', '', '', '', (string) $summary['total'], (string) $summary['margin_amount']];

        return Response::download(
            self::encodeCsv($rows),
            'prime-arch-dqe-' . self::slug((string) ($project['reference'] ?? '') ?: (string) $project['name']) . '-' . date('Ymd') . '.csv',
            'text/csv; charset=utf-8'
        );
    }

    /** Bibliothèque de prix réutilisable. */
    public function library(Request $request): Response
    {
        if (($denied = $this->requireView()) !== null) {
            return $denied;
        }
        $filters = [
            'q' => self::queryText($request, 'q', 100),
            'active_only' => (string) $request->query('all', '') !== '1',
        ];

        return Response::html(View::renderModule('metre-quantitatif', 'library', $this->context('metre.library_title') + [
            'items' => $this->metre->library($filters),
            'filters' => $filters,
            'units' => MetreService::UNITS,
            'canManage' => $this->canManage(),
            'notice' => Session::pullFlash('metre.notice'),
            'error' => Session::pullFlash('metre.error'),
            'libraryErrors' => Session::pullFlash('metre.library_errors', []),
            'libraryValues' => Session::pullFlash('metre.library_values', []),
        ]));
    }

    /** Crée ou met à jour un article de la bibliothèque. */
    public function saveLibrary(Request $request): Response
    {
        if (($denied = $this->requireManage()) !== null) {
            return $denied;
        }
        $validated = $this->service->validateLibrary($request->all());
        $rawId = $request->input('id');
        $libraryId = is_string($rawId) && ctype_digit($rawId) && (int) $rawId > 0 ? (int) $rawId : null;

        if ($validated['errors'] !== []) {
            Session::flash('metre.library_errors', $validated['errors']);
            Session::flash('metre.library_values', $validated['values'] + ['id' => $libraryId]);

            return Response::redirect(url('/metre/bibliotheque') . '#articles');
        }

        try {
            if ($libraryId !== null) {
                $this->metre->updateLibrary($libraryId, $validated['values']);
                (new AuditLogger())->record('metre.library_updated', 'module', $libraryId, [], Auth::id());
                Session::flash('metre.notice', __('metre.library_updated'));
            } else {
                $newId = $this->metre->createLibrary($validated['values'], Auth::id());
                (new AuditLogger())->record('metre.library_created', 'module', $newId, [], Auth::id());
                Session::flash('metre.notice', __('metre.library_added'));
            }
        } catch (PDOException) {
            Session::flash('metre.error', __('metre.library_code_taken'));
            Session::flash('metre.library_values', $validated['values']);

            return Response::redirect(url('/metre/bibliotheque') . '#articles');
        }

        return Response::redirect(url('/metre/bibliotheque') . '#articles');
    }

    /** Supprime un article de la bibliothèque ; les postes déjà saisis sont conservés. */
    public function deleteLibrary(Request $request, string $libraryId): Response
    {
        if (($denied = $this->requireManage()) !== null) {
            return $denied;
        }
        if (!ctype_digit($libraryId)) {
            return Response::html(__('metre.not_found'), 404);
        }
        if ($this->metre->deleteLibrary((int) $libraryId)) {
            (new AuditLogger())->record('metre.library_deleted', 'module', (int) $libraryId, [], Auth::id());
            Session::flash('metre.notice', __('metre.library_deleted'));
        } else {
            Session::flash('metre.error', __('metre.library_not_found'));
        }

        return Response::redirect(url('/metre/bibliotheque') . '#articles');
    }

    /**
     * Article de bibliothèque sélectionné dans le formulaire, s’il appartient au cabinet.
     *
     * @return array<string,mixed>|null
     */
    private function libraryFromInput(Request $request): ?array
    {
        $raw = $request->input('library_id');
        if (!is_string($raw) || !ctype_digit($raw) || (int) $raw <= 0) {
            return null;
        }

        return $this->metre->findLibrary((int) $raw);
    }

    /**
     * @param array<int,array<string,mixed>> $postes
     * @return array<int,array<int,array<string,mixed>>>
     */
    private function groupPostes(array $postes): array
    {
        $grouped = [];
        foreach ($postes as $poste) {
            $grouped[(int) $poste['lot_id']][] = $poste;
        }

        return $grouped;
    }

    /** @return array<string,mixed>|null */
    private function findProjectOrNull(string $projectId): ?array
    {
        return ctype_digit($projectId) ? $this->metre->project((int) $projectId) : null;
    }

    /** @return array<string,mixed> */
    private function context(string $titleKey): array
    {
        return (new ViewContext())->authenticated(__($titleKey), 'metre-quantitatif');
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

    /** Nom de fichier lisible mais sûr (ASCII, sans espace). */
    private static function slug(string $value): string
    {
        $slug = preg_replace('/[^A-Za-z0-9]+/', '-', $value) ?? '';

        return trim($slug, '-') !== '' ? strtolower(trim($slug, '-')) : 'projet';
    }

    /**
     * CSV UTF-8 (BOM), séparateur point-virgule, formules neutralisées.
     *
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
}
