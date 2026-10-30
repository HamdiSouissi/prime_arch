<?php
declare(strict_types=1);

namespace Modules\HonorairesBareme\Controllers;

use App\Core\Auth;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\Translator;
use App\Core\View;
use App\Services\AuditLogger;
use App\Services\DocumentService;
use App\Services\ViewContext;
use Modules\HonorairesBareme\Models\HonoraireRepository;
use Modules\HonorairesBareme\Services\Decimal;
use Modules\HonorairesBareme\Services\HonoraireService;
use PDOException;
use Throwable;

/**
 * Barèmes d’honoraires, décomptes par projet et par phase, devis d’honoraires.
 *
 * Droits réutilisés du cœur : `projects.view` en lecture, `projects.manage` en écriture et
 * `documents.create` pour générer le devis. Aucun taux officiel n’est fourni par le module.
 */
final class HonoraireController
{
    private HonoraireRepository $repo;
    private HonoraireService $service;

    public function __construct(?HonoraireRepository $repo = null, ?HonoraireService $service = null)
    {
        $this->repo = $repo ?? new HonoraireRepository();
        $this->service = $service ?? new HonoraireService();
    }

    /** Liste des projets suivis en honoraires. */
    public function index(Request $request): Response
    {
        if (($denied = $this->requireView()) !== null) {
            return $denied;
        }
        $filters = ['q' => self::queryText($request, 'q', 100)];

        return Response::html(View::renderModule('honoraires-bareme', 'index', $this->context('honoraire.index_title') + [
            'projects' => $this->repo->projects($filters),
            'statistics' => $this->repo->statistics(),
            'filters' => $filters,
            'canManage' => $this->canManage(),
            'notice' => Session::pullFlash('honoraire.notice'),
            'error' => Session::pullFlash('honoraire.error'),
        ]));
    }

    /** Fiche honoraires d’un projet : décomptes et formulaire de calcul. */
    public function project(Request $request, string $projectId): Response
    {
        if (($denied = $this->requireView()) !== null) {
            return $denied;
        }
        $project = $this->findProjectOrNull($projectId);
        if ($project === null) {
            return Response::html(__('honoraire.not_found'), 404);
        }
        $id = (int) $project['id'];
        $decomptes = $this->repo->decomptes($id);
        $missions = $this->repo->missions(['q' => '', 'active_only' => true]);

        return Response::html(View::renderModule('honoraires-bareme', 'project', $this->context('honoraire.project_title') + [
            'project' => $project,
            'decomptes' => $decomptes,
            'missions' => $missions,
            'phases' => $this->repo->phases(),
            'totalFee' => Decimal::format($this->service->total($decomptes)),
            'totalBasis' => Decimal::format($this->basisTotal($decomptes)),
            'canManage' => $this->canManage(),
            'canCreateDocument' => $this->canCreateDocument(),
            'notice' => Session::pullFlash('honoraire.notice'),
            'error' => Session::pullFlash('honoraire.error'),
            'decompteErrors' => Session::pullFlash('honoraire.decompte_errors', []),
            'decompteValues' => Session::pullFlash('honoraire.decompte_values', []),
        ]));
    }

    /** Liste des barèmes du cabinet. */
    public function baremes(Request $request): Response
    {
        if (($denied = $this->requireView()) !== null) {
            return $denied;
        }
        $filters = [
            'q' => self::queryText($request, 'q', 100),
            'active_only' => (string) $request->query('all', '') !== '1',
        ];

        return Response::html(View::renderModule('honoraires-bareme', 'baremes', $this->context('honoraire.baremes_title') + [
            'missions' => $this->repo->missions($filters),
            'filters' => $filters,
            'phases' => $this->repo->phases(),
            'canManage' => $this->canManage(),
            'notice' => Session::pullFlash('honoraire.notice'),
            'error' => Session::pullFlash('honoraire.error'),
            'missionErrors' => Session::pullFlash('honoraire.mission_errors', []),
            'missionValues' => Session::pullFlash('honoraire.mission_values', []),
        ]));
    }

    /** Crée un barème (mission). */
    public function createBareme(Request $request): Response
    {
        if (($denied = $this->requireManage()) !== null) {
            return $denied;
        }
        $validated = $this->service->validateMission($request->all());
        if ($validated['errors'] !== []) {
            Session::flash('honoraire.mission_errors', $validated['errors']);
            Session::flash('honoraire.mission_values', $validated['values']);

            return Response::redirect(url('/honoraires/baremes') . '#baremes');
        }

        try {
            $missionId = $this->repo->createMission($validated['values'], Auth::id());
        } catch (PDOException) {
            Session::flash('honoraire.error', __('honoraire.name_taken'));
            Session::flash('honoraire.mission_values', $validated['values']);

            return Response::redirect(url('/honoraires/baremes') . '#baremes');
        }

        (new AuditLogger())->record('honoraire.mission_created', 'module', $missionId, [], Auth::id());
        Session::flash('honoraire.notice', __('honoraire.mission_created'));

        return Response::redirect(url('/honoraires/baremes/' . $missionId));
    }

    /** Édition d’un barème et de ses tranches. */
    public function bareme(Request $request, string $baremeId): Response
    {
        if (($denied = $this->requireView()) !== null) {
            return $denied;
        }
        $mission = $this->findMissionOrNull($baremeId);
        if ($mission === null) {
            return Response::html(__('honoraire.mission_not_found'), 404);
        }

        return Response::html(View::renderModule('honoraires-bareme', 'bareme', $this->context('honoraire.bareme_title') + [
            'mission' => $mission,
            'tranches' => $this->repo->tranches((int) $mission['id']),
            'phases' => $this->repo->phases(),
            'canManage' => $this->canManage(),
            'notice' => Session::pullFlash('honoraire.notice'),
            'error' => Session::pullFlash('honoraire.error'),
            'missionErrors' => Session::pullFlash('honoraire.mission_errors', []),
            'missionValues' => Session::pullFlash('honoraire.mission_values', []),
            'trancheErrors' => Session::pullFlash('honoraire.tranche_errors', []),
            'trancheValues' => Session::pullFlash('honoraire.tranche_values', []),
        ]));
    }

    /** Enregistre les informations générales d’un barème. */
    public function updateBareme(Request $request, string $baremeId): Response
    {
        if (($denied = $this->requireManage()) !== null) {
            return $denied;
        }
        $mission = $this->findMissionOrNull($baremeId);
        if ($mission === null) {
            return Response::html(__('honoraire.mission_not_found'), 404);
        }
        $validated = $this->service->validateMission($request->all());
        if ($validated['errors'] !== []) {
            Session::flash('honoraire.mission_errors', $validated['errors']);
            Session::flash('honoraire.mission_values', $validated['values']);

            return Response::redirect(url('/honoraires/baremes/' . (int) $mission['id']) . '#bareme');
        }

        try {
            $this->repo->updateMission((int) $mission['id'], $validated['values']);
        } catch (PDOException) {
            Session::flash('honoraire.error', __('honoraire.name_taken'));
            Session::flash('honoraire.mission_values', $validated['values']);

            return Response::redirect(url('/honoraires/baremes/' . (int) $mission['id']) . '#bareme');
        }

        (new AuditLogger())->record('honoraire.mission_updated', 'module', (int) $mission['id'], [], Auth::id());
        Session::flash('honoraire.notice', __('honoraire.mission_updated'));

        return Response::redirect(url('/honoraires/baremes/' . (int) $mission['id']) . '#bareme');
    }

    /** Supprime un barème ; les décomptes déjà établis restent consultables. */
    public function deleteBareme(Request $request, string $baremeId): Response
    {
        if (($denied = $this->requireManage()) !== null) {
            return $denied;
        }
        $mission = $this->findMissionOrNull($baremeId);
        if ($mission === null) {
            Session::flash('honoraire.error', __('honoraire.mission_not_found'));

            return Response::redirect(url('/honoraires/baremes'));
        }
        $this->repo->deleteMission((int) $mission['id']);
        (new AuditLogger())->record('honoraire.mission_deleted', 'module', (int) $mission['id'], [], Auth::id());
        Session::flash('honoraire.notice', __('honoraire.mission_deleted'));

        return Response::redirect(url('/honoraires/baremes'));
    }

    /** Ajoute une tranche (phase, bornes, taux) à un barème. */
    public function addTranche(Request $request, string $baremeId): Response
    {
        if (($denied = $this->requireManage()) !== null) {
            return $denied;
        }
        $mission = $this->findMissionOrNull($baremeId);
        if ($mission === null) {
            return Response::html(__('honoraire.mission_not_found'), 404);
        }
        $missionId = (int) $mission['id'];
        $validated = $this->service->validateTranche(
            $request->all(),
            $this->repo->tranches($missionId),
            array_keys($this->repo->phases())
        );
        if ($validated['errors'] !== []) {
            Session::flash('honoraire.tranche_errors', $validated['errors']);
            Session::flash('honoraire.tranche_values', $validated['values']);

            return Response::redirect(url('/honoraires/baremes/' . $missionId) . '#tranches');
        }

        $this->repo->createTranche($missionId, $validated['values']);
        (new AuditLogger())->record('honoraire.tranche_created', 'module', $missionId, [], Auth::id());
        Session::flash('honoraire.notice', __('honoraire.tranche_created'));

        return Response::redirect(url('/honoraires/baremes/' . $missionId) . '#tranches');
    }

    /** Supprime une tranche d’un barème. */
    public function deleteTranche(Request $request, string $baremeId, string $trancheId): Response
    {
        if (($denied = $this->requireManage()) !== null) {
            return $denied;
        }
        $mission = $this->findMissionOrNull($baremeId);
        if ($mission === null || !ctype_digit($trancheId)) {
            return Response::html(__('honoraire.mission_not_found'), 404);
        }
        $missionId = (int) $mission['id'];
        if ($this->repo->deleteTranche($missionId, (int) $trancheId)) {
            (new AuditLogger())->record('honoraire.tranche_deleted', 'module', $missionId, [], Auth::id());
            Session::flash('honoraire.notice', __('honoraire.tranche_deleted'));
        } else {
            Session::flash('honoraire.error', __('honoraire.tranche_not_found'));
        }

        return Response::redirect(url('/honoraires/baremes/' . $missionId) . '#tranches');
    }

    /** Calcule et enregistre un décompte pour un projet. */
    public function createDecompte(Request $request, string $projectId): Response
    {
        if (($denied = $this->requireManage()) !== null) {
            return $denied;
        }
        $project = $this->findProjectOrNull($projectId);
        if ($project === null) {
            return Response::html(__('honoraire.not_found'), 404);
        }
        $id = (int) $project['id'];
        $rawMission = (string) $request->input('mission_id', '');
        $mission = ctype_digit($rawMission) ? $this->repo->findMission((int) $rawMission) : null;
        if ($mission === null) {
            Session::flash('honoraire.error', __('honoraire.mission_not_found'));

            return Response::redirect(url('/honoraires/' . $id) . '#decomptes');
        }

        $validated = $this->service->validateDecompte($request->all());
        if ($validated['errors'] !== []) {
            Session::flash('honoraire.decompte_errors', $validated['errors']);
            Session::flash('honoraire.decompte_values', $validated['values'] + ['mission_id' => (int) $mission['id']]);

            return Response::redirect(url('/honoraires/' . $id) . '#decomptes');
        }

        $computed = $this->service->compute($this->repo->tranches((int) $mission['id']), (int) $validated['values']['basis_millimes']);
        if ($computed['fee'] <= 0) {
            Session::flash('honoraire.error', __('honoraire.no_fee'));
            Session::flash('honoraire.decompte_values', $validated['values'] + ['mission_id' => (int) $mission['id']]);

            return Response::redirect(url('/honoraires/' . $id) . '#decomptes');
        }

        $phases = $this->repo->phases();
        $breakdown = array_map(
            static fn (array $phase): array => $phase + [
                'phase_name' => $phases[(string) $phase['phase_key']] ?? (string) $phase['phase_key'],
            ],
            $computed['phases']
        );

        $decompteId = $this->repo->createDecompte(
            $id,
            [
                'basis_amount' => (string) $validated['values']['basis_amount'],
                'fee_amount' => (string) $computed['fee_amount'],
                'notes' => (string) $validated['values']['notes'],
            ],
            $breakdown,
            (int) $mission['id'],
            (string) $mission['name'],
            Auth::id()
        );
        (new AuditLogger())->record('honoraire.decompte_created', 'module', $decompteId, ['project_id' => $id], Auth::id());
        Session::flash('honoraire.notice', __('honoraire.decompte_created', ['amount' => money_format_tnd($computed['fee_amount'])]));

        return Response::redirect(url('/honoraires/decomptes/' . $decompteId));
    }

    /** Détail d’un décompte : assiette, phases et honoraires. */
    public function showDecompte(Request $request, string $decompteId): Response
    {
        if (($denied = $this->requireView()) !== null) {
            return $denied;
        }
        $decompte = $this->findDecompteOrNull($decompteId);
        if ($decompte === null) {
            return Response::html(__('honoraire.decompte_not_found'), 404);
        }
        $project = $this->repo->project((int) $decompte['project_id']);

        return Response::html(View::renderModule('honoraires-bareme', 'decompte', $this->context('honoraire.decompte_title') + [
            'decompte' => $decompte,
            'project' => $project,
            'breakdown' => $this->service->breakdown($decompte),
            'canManage' => $this->canManage(),
            'canCreateDocument' => $this->canCreateDocument(),
            'notice' => Session::pullFlash('honoraire.notice'),
            'error' => Session::pullFlash('honoraire.error'),
        ]));
    }

    /** Valide un décompte (il devient facturable). */
    public function validateDecompte(Request $request, string $decompteId): Response
    {
        if (($denied = $this->requireManage()) !== null) {
            return $denied;
        }
        $decompte = $this->findDecompteOrNull($decompteId);
        if ($decompte === null) {
            Session::flash('honoraire.error', __('honoraire.decompte_not_found'));

            return Response::redirect(url('/honoraires'));
        }
        if ((string) $decompte['status'] === 'invoiced') {
            Session::flash('honoraire.error', __('honoraire.already_invoiced'));

            return Response::redirect(url('/honoraires/decomptes/' . (int) $decompte['id']));
        }

        $this->repo->updateDecompteStatus((int) $decompte['id'], 'validated');
        (new AuditLogger())->record('honoraire.decompte_validated', 'module', (int) $decompte['id'], [], Auth::id());
        Session::flash('honoraire.notice', __('honoraire.decompte_validated'));

        return Response::redirect(url('/honoraires/decomptes/' . (int) $decompte['id']));
    }

    /** Génère un devis d’honoraires dans le noyau (brouillon, une ligne par phase). */
    public function createQuote(Request $request, string $decompteId): Response
    {
        if (($denied = $this->requireView()) !== null) {
            return $denied;
        }
        if (!Auth::can('documents.create')) {
            return Response::html(__('common.forbidden'), 403);
        }
        $decompte = $this->findDecompteOrNull($decompteId);
        if ($decompte === null) {
            Session::flash('honoraire.error', __('honoraire.decompte_not_found'));

            return Response::redirect(url('/honoraires'));
        }
        $decompteIdInt = (int) $decompte['id'];
        if ((int) ($decompte['document_id'] ?? 0) > 0) {
            Session::flash('honoraire.error', __('honoraire.document_exists'));

            return Response::redirect(url('/honoraires/decomptes/' . $decompteIdInt));
        }
        $project = $this->repo->project((int) $decompte['project_id']);
        if ($project === null) {
            Session::flash('honoraire.error', __('honoraire.not_found'));

            return Response::redirect(url('/honoraires'));
        }

        $lines = $this->quoteLines($decompte);
        if ($lines === []) {
            Session::flash('honoraire.error', __('honoraire.quote_empty'));

            return Response::redirect(url('/honoraires/decomptes/' . $decompteIdInt));
        }

        try {
            $documents = new DocumentService();
            $defaults = $documents->draftDefaults('quote', Translator::locale());
            $candidate = array_merge($defaults, [
                'client_id' => (string) (int) $project['client_id'],
                'project_id' => (string) (int) $project['id'],
                'project_phase_assignment_id' => '',
                'title' => mb_substr(__('honoraire.quote_title', ['project' => (string) $project['name']]), 0, 180),
                'notes' => (string) __('honoraire.quote_notes', [
                    'mission' => (string) $decompte['mission_name'],
                    'basis' => money_format_tnd((string) $decompte['basis_amount']),
                ]),
                'lines' => $lines,
            ]);
            $validated = $documents->validateDraft($candidate);
            if ($validated['errors'] !== []) {
                Session::flash('honoraire.error', __('honoraire.quote_failed'));

                return Response::redirect(url('/honoraires/decomptes/' . $decompteIdInt));
            }
            $documentId = $documents->createDraft($validated['values'], (int) Auth::id());
        } catch (Throwable $error) {
            error_log('Devis d’honoraires impossible : ' . $error->getMessage());
            Session::flash('honoraire.error', __('honoraire.quote_failed'));

            return Response::redirect(url('/honoraires/decomptes/' . $decompteIdInt));
        }

        $this->repo->attachDocument($decompteIdInt, $documentId);
        (new AuditLogger())->record('honoraire.document_created', 'module', $decompteIdInt, ['document_id' => $documentId], Auth::id());
        Session::flash('honoraire.notice', __('honoraire.document_created'));

        return Response::redirect(url('/documents/' . $documentId));
    }

    /** Supprime un décompte (le devis éventuel reste dans le noyau). */
    public function deleteDecompte(Request $request, string $decompteId): Response
    {
        if (($denied = $this->requireManage()) !== null) {
            return $denied;
        }
        $decompte = $this->findDecompteOrNull($decompteId);
        if ($decompte === null) {
            Session::flash('honoraire.error', __('honoraire.decompte_not_found'));

            return Response::redirect(url('/honoraires'));
        }
        $projectId = (int) $decompte['project_id'];
        if ((int) ($decompte['document_id'] ?? 0) > 0) {
            Session::flash('honoraire.error', __('honoraire.delete_linked'));

            return Response::redirect(url('/honoraires/decomptes/' . (int) $decompte['id']));
        }

        $this->repo->deleteDecompte((int) $decompte['id']);
        (new AuditLogger())->record('honoraire.decompte_deleted', 'module', (int) $decompte['id'], ['project_id' => $projectId], Auth::id());
        Session::flash('honoraire.notice', __('honoraire.decompte_deleted'));

        return Response::redirect(url('/honoraires/' . $projectId) . '#decomptes');
    }

    /**
     * Lignes du devis : une par phase, montant = honoraires de la phase.
     *
     * @param array<string,mixed> $decompte
     * @return array<int,array<string,string>>
     */
    private function quoteLines(array $decompte): array
    {
        $lines = [];
        foreach ($this->service->breakdown($decompte) as $phase) {
            $fee = (string) ($phase['fee'] ?? '0.000');
            if ((Decimal::parse($fee) ?? 0) <= 0) {
                continue;
            }
            $label = trim(sprintf(
                '%s — %s — %s',
                __('honoraire.quote_line_prefix'),
                (string) ($phase['phase_name'] ?? $phase['phase_key'] ?? ''),
                (string) $decompte['mission_name']
            ));
            $lines[] = [
                'description' => mb_substr($label, 0, 255),
                'quantity' => '1.000',
                'unit' => __('honoraire.quote_line_unit'),
                'unit_price' => $fee,
            ];
        }

        return $lines;
    }

    /**
     * Somme des assiettes décomptées (millimes).
     *
     * @param array<int,array<string,mixed>> $decomptes
     */
    private function basisTotal(array $decomptes): int
    {
        $total = 0;
        foreach ($decomptes as $decompte) {
            $total += Decimal::parse($decompte['basis_amount'] ?? '0') ?? 0;
        }

        return $total;
    }

    /** @return array<string,mixed> */
    private function context(string $titleKey): array
    {
        return (new ViewContext())->authenticated(__($titleKey), 'honoraires-bareme');
    }

    private function requireView(): ?Response
    {
        return Auth::can('projects.view') ? null : Response::html(__('common.forbidden'), 403);
    }

    private function requireManage(): ?Response
    {
        return Auth::can('projects.manage') ? null : Response::html(__('common.forbidden'), 403);
    }

    private function canManage(): bool
    {
        return Auth::can('projects.manage');
    }

    private function canCreateDocument(): bool
    {
        return Auth::can('documents.create');
    }

    private function findProjectOrNull(string $projectId): ?array
    {
        if (!ctype_digit($projectId)) {
            return null;
        }
        $project = $this->repo->project((int) $projectId);

        return $project === null ? null : $project + ['id' => (int) $project['id']];
    }

    private function findMissionOrNull(string $missionId): ?array
    {
        if (!ctype_digit($missionId)) {
            return null;
        }

        return $this->repo->findMission((int) $missionId);
    }

    private function findDecompteOrNull(string $decompteId): ?array
    {
        if (!ctype_digit($decompteId)) {
            return null;
        }

        return $this->repo->findDecompte((int) $decompteId);
    }

    private static function queryText(Request $request, string $key, int $maxLength): string
    {
        $value = $request->query($key, '');

        return is_string($value) ? mb_substr(trim($value), 0, $maxLength, 'UTF-8') : '';
    }
}
