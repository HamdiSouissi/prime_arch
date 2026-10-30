<?php
declare(strict_types=1);

namespace Modules\ConformiteOat\Controllers;

use App\Core\Auth;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\View;
use App\Services\AuditLogger;
use App\Services\CabinetSettings;
use App\Services\ViewContext;
use Modules\ConformiteOat\Models\ConformiteRepository;
use Modules\ConformiteOat\Services\ConformiteService;
use Throwable;

/**
 * Dossier professionnel du cabinet : pièces administratives, assurances, formations et
 * bureaux d’études partenaires, avec alertes d’échéance et attestation par projet.
 *
 * Droits réutilisés du cœur : `projects.view` ou `settings.view` en lecture,
 * `projects.manage` ou `settings.manage` en écriture.
 */
final class ConformiteController
{
    private ConformiteRepository $conformite;
    private ConformiteService $service;

    public function __construct(?ConformiteRepository $conformite = null, ?ConformiteService $service = null)
    {
        $this->conformite = $conformite ?? new ConformiteRepository();
        $this->service = $service ?? new ConformiteService();
    }

    /** Tableau de bord : échéances, pièces administratives, assurances, formations, partenaires. */
    public function index(Request $request): Response
    {
        if (($denied = $this->requireView()) !== null) {
            return $denied;
        }
        $horizon = ConformiteService::horizon($request->query('horizon', ''));
        $today = date('Y-m-d');

        $inscriptions = $this->decorate($this->conformite->inscriptions(), $today, $horizon);
        $assurances = $this->decorate($this->conformite->assurances(), $today, $horizon, 'ends_on');
        $formations = $this->decorate($this->conformite->formations(), $today, $horizon);
        $partenaires = $this->conformite->partenaires();

        $alerts = [];
        foreach ([[$inscriptions, 'inscription'], [$assurances, 'assurance'], [$formations, 'formation']] as [$rows, $source]) {
            foreach ($rows as $row) {
                if (!in_array((string) $row['status'], ['expired', 'expiring'], true)) {
                    continue;
                }
                $alerts[] = [
                    'source' => $source,
                    'label' => (string) $row['label'],
                    'detail' => (string) ($row['detail'] ?? ''),
                    'expires_on' => (string) $row['expires_on'],
                    'days_left' => $row['days_left'],
                    'status' => (string) $row['status'],
                ];
            }
        }
        usort($alerts, static fn (array $a, array $b): int => ((int) $a['days_left']) <=> ((int) $b['days_left']));

        $activePartenaires = 0;
        foreach ($partenaires as $partenaire) {
            if ((int) $partenaire['is_active'] === 1) {
                $activePartenaires++;
            }
        }

        return Response::html(View::renderModule('conformite-oat', 'index', $this->context('conformite.index_title') + [
            'today' => $today,
            'horizon' => $horizon,
            'inscriptions' => $inscriptions,
            'assurances' => $assurances,
            'formations' => $formations,
            'partenaires' => $partenaires,
            'alerts' => $alerts,
            'statistics' => [
                'inscriptions' => ConformiteService::summary($inscriptions, $today, $horizon),
                'assurances' => ConformiteService::summary($assurances, $today, $horizon),
                'formations' => ConformiteService::summary($formations, $today, $horizon),
                'partenaires' => $activePartenaires,
                'partenaires_total' => count($partenaires),
            ],
            'canManage' => $this->canManage(),
            'notice' => Session::pullFlash('conformite.notice'),
            'error' => Session::pullFlash('conformite.error'),
            'inscriptionErrors' => Session::pullFlash('conformite.inscription_errors', []),
            'inscriptionValues' => Session::pullFlash('conformite.inscription_values', []),
            'assuranceErrors' => Session::pullFlash('conformite.assurance_errors', []),
            'assuranceValues' => Session::pullFlash('conformite.assurance_values', []),
            'formationErrors' => Session::pullFlash('conformite.formation_errors', []),
            'formationValues' => Session::pullFlash('conformite.formation_values', []),
            'partenaireErrors' => Session::pullFlash('conformite.partenaire_errors', []),
            'partenaireValues' => Session::pullFlash('conformite.partenaire_values', []),
        ]));
    }

    /** Ajoute une pièce administrative (inscription, carte professionnelle, autorisation). */
    public function storeInscription(Request $request): Response
    {
        if (($denied = $this->requireManage()) !== null) {
            return $denied;
        }
        $validated = $this->service->validateInscription($request->all());
        if ($validated['errors'] !== []) {
            Session::flash('conformite.inscription_errors', $validated['errors']);
            Session::flash('conformite.inscription_values', $validated['values']);

            return $this->backToList($request);
        }
        try {
            $id = $this->conformite->createInscription($validated['values'], Auth::id());
            (new AuditLogger())->record('conformite.inscription_created', 'module', $id, ['kind' => $validated['values']['kind']], Auth::id());
            Session::flash('conformite.notice', __('conformite.inscription_created'));
        } catch (Throwable $error) {
            error_log('Création d’une pièce administrative impossible : ' . $error->getMessage());
            Session::flash('conformite.error', __('conformite.save_failed'));
        }

        return $this->backToList($request);
    }

    /** Supprime une pièce administrative. */
    public function deleteInscription(Request $request, string $inscriptionId): Response
    {
        if (($denied = $this->requireManage()) !== null) {
            return $denied;
        }
        if (!ctype_digit($inscriptionId) || $this->conformite->findInscription((int) $inscriptionId) === null) {
            Session::flash('conformite.error', __('conformite.inscription_not_found'));

            return $this->backToList($request);
        }
        $this->conformite->deleteInscription((int) $inscriptionId);
        (new AuditLogger())->record('conformite.inscription_deleted', 'module', (int) $inscriptionId, [], Auth::id());
        Session::flash('conformite.notice', __('conformite.inscription_deleted'));

        return $this->backToList($request);
    }

    /** Ajoute une police d’assurance. */
    public function storeAssurance(Request $request): Response
    {
        if (($denied = $this->requireManage()) !== null) {
            return $denied;
        }
        $validated = $this->service->validateAssurance($request->all());
        if ($validated['errors'] !== []) {
            Session::flash('conformite.assurance_errors', $validated['errors']);
            Session::flash('conformite.assurance_values', $validated['values']);

            return $this->backToList($request);
        }
        try {
            $id = $this->conformite->createAssurance($validated['values'], Auth::id());
            (new AuditLogger())->record('conformite.assurance_created', 'module', $id, ['ends_on' => $validated['values']['ends_on']], Auth::id());
            Session::flash('conformite.notice', __('conformite.assurance_created'));
        } catch (Throwable $error) {
            error_log('Création d’une assurance impossible : ' . $error->getMessage());
            Session::flash('conformite.error', __('conformite.save_failed'));
        }

        return $this->backToList($request);
    }

    /** Supprime une police d’assurance. */
    public function deleteAssurance(Request $request, string $assuranceId): Response
    {
        if (($denied = $this->requireManage()) !== null) {
            return $denied;
        }
        if (!ctype_digit($assuranceId) || $this->conformite->findAssurance((int) $assuranceId) === null) {
            Session::flash('conformite.error', __('conformite.assurance_not_found'));

            return $this->backToList($request);
        }
        $this->conformite->deleteAssurance((int) $assuranceId);
        (new AuditLogger())->record('conformite.assurance_deleted', 'module', (int) $assuranceId, [], Auth::id());
        Session::flash('conformite.notice', __('conformite.assurance_deleted'));

        return $this->backToList($request);
    }

    /** Ajoute une formation continue. */
    public function storeFormation(Request $request): Response
    {
        if (($denied = $this->requireManage()) !== null) {
            return $denied;
        }
        $validated = $this->service->validateFormation($request->all());
        if ($validated['errors'] !== []) {
            Session::flash('conformite.formation_errors', $validated['errors']);
            Session::flash('conformite.formation_values', $validated['values']);

            return $this->backToList($request);
        }
        try {
            $id = $this->conformite->createFormation($validated['values'], Auth::id());
            (new AuditLogger())->record('conformite.formation_created', 'module', $id, [], Auth::id());
            Session::flash('conformite.notice', __('conformite.formation_created'));
        } catch (Throwable $error) {
            error_log('Création d’une formation impossible : ' . $error->getMessage());
            Session::flash('conformite.error', __('conformite.save_failed'));
        }

        return $this->backToList($request);
    }

    /** Supprime une formation. */
    public function deleteFormation(Request $request, string $formationId): Response
    {
        if (($denied = $this->requireManage()) !== null) {
            return $denied;
        }
        if (!ctype_digit($formationId) || $this->conformite->findFormation((int) $formationId) === null) {
            Session::flash('conformite.error', __('conformite.formation_not_found'));

            return $this->backToList($request);
        }
        $this->conformite->deleteFormation((int) $formationId);
        (new AuditLogger())->record('conformite.formation_deleted', 'module', (int) $formationId, [], Auth::id());
        Session::flash('conformite.notice', __('conformite.formation_deleted'));

        return $this->backToList($request);
    }

    /** Ajoute un bureau d’études partenaire. */
    public function storePartenaire(Request $request): Response
    {
        if (($denied = $this->requireManage()) !== null) {
            return $denied;
        }
        $validated = $this->service->validatePartenaire($request->all());
        if ($validated['errors'] !== []) {
            Session::flash('conformite.partenaire_errors', $validated['errors']);
            Session::flash('conformite.partenaire_values', $validated['values']);

            return $this->backToList($request);
        }
        try {
            $id = $this->conformite->createPartenaire($validated['values'], Auth::id());
            (new AuditLogger())->record('conformite.partenaire_created', 'module', $id, [], Auth::id());
            Session::flash('conformite.notice', __('conformite.partenaire_created'));
        } catch (Throwable $error) {
            error_log('Création d’un partenaire impossible : ' . $error->getMessage());
            Session::flash('conformite.error', __('conformite.save_failed'));
        }

        return $this->backToList($request);
    }

    /** Active ou désactive un partenaire sans supprimer sa fiche. */
    public function togglePartenaire(Request $request, string $partenaireId): Response
    {
        if (($denied = $this->requireManage()) !== null) {
            return $denied;
        }
        $partenaire = ctype_digit($partenaireId) ? $this->conformite->findPartenaire((int) $partenaireId) : null;
        if ($partenaire === null) {
            Session::flash('conformite.error', __('conformite.partenaire_not_found'));

            return $this->backToList($request);
        }
        $active = (int) $partenaire['is_active'] !== 1;
        $this->conformite->togglePartenaire((int) $partenaire['id'], $active);
        (new AuditLogger())->record('conformite.partenaire_toggled', 'module', (int) $partenaire['id'], ['is_active' => $active], Auth::id());
        Session::flash('conformite.notice', $active ? __('conformite.partenaire_activated') : __('conformite.partenaire_deactivated'));

        return $this->backToList($request);
    }

    /** Supprime un partenaire. */
    public function deletePartenaire(Request $request, string $partenaireId): Response
    {
        if (($denied = $this->requireManage()) !== null) {
            return $denied;
        }
        if (!ctype_digit($partenaireId) || $this->conformite->findPartenaire((int) $partenaireId) === null) {
            Session::flash('conformite.error', __('conformite.partenaire_not_found'));

            return $this->backToList($request);
        }
        $this->conformite->deletePartenaire((int) $partenaireId);
        (new AuditLogger())->record('conformite.partenaire_deleted', 'module', (int) $partenaireId, [], Auth::id());
        Session::flash('conformite.notice', __('conformite.partenaire_deleted'));

        return $this->backToList($request);
    }

    /** Liste des projets pour lesquels une attestation peut être imprimée. */
    public function attestations(Request $request): Response
    {
        if (($denied = $this->requireView()) !== null) {
            return $denied;
        }
        $today = date('Y-m-d');
        $assurance = $this->conformite->currentAssurance($today);
        $status = $assurance === null
            ? 'none'
            : ConformiteService::status((string) $assurance['ends_on'], $today, 0);

        return Response::html(View::renderModule('conformite-oat', 'attestations', $this->context('conformite.attestations_title') + [
            'today' => $today,
            'projects' => $this->conformite->projects(),
            'assurance' => $assurance,
            'assuranceStatus' => $status,
            'assuranceDaysLeft' => $assurance === null ? null : ConformiteService::daysLeft((string) $assurance['ends_on'], $today),
            'canManage' => $this->canManage(),
            'notice' => Session::pullFlash('conformite.notice'),
            'error' => Session::pullFlash('conformite.error'),
        ]));
    }

    /** Attestation d’assurance imprimable pour un projet. */
    public function attestation(Request $request, string $projectId): Response
    {
        if (($denied = $this->requireView()) !== null) {
            return $denied;
        }
        if (!ctype_digit($projectId)) {
            return Response::html(__('conformite.not_found'), 404);
        }
        $project = $this->conformite->findProject((int) $projectId);
        if ($project === null) {
            return Response::html(__('conformite.project_not_found'), 404);
        }
        $today = date('Y-m-d');
        $assurance = $this->conformite->currentAssurance($today);
        if ($assurance === null) {
            Session::flash('conformite.error', __('conformite.no_assurance'));

            return Response::redirect(url('/conformite/attestations'));
        }
        if (ConformiteService::status((string) $assurance['ends_on'], $today, 0) === 'expired') {
            Session::flash('conformite.error', __('conformite.assurance_expired'));

            return Response::redirect(url('/conformite/attestations'));
        }

        $cabinet = (new CabinetSettings())->get();
        $coverageAmount = $assurance['coverage_amount'] === null
            ? null
            : ConformiteService::amount((string) $assurance['coverage_amount']);

        return Response::html(View::renderModule('conformite-oat', 'attestation', [
            'title' => __('conformite.attestation_title'),
            'project' => $project,
            'assurance' => $assurance,
            'cabinet' => $cabinet,
            'today' => $today,
            'coverage' => $coverageAmount === null ? null : ConformiteService::amountFormat($coverageAmount),
        ], null));
    }

    /**
     * Ajoute l’état calculé et les jours restants à chaque ligne d’un registre.
     *
     * @param array<int,array<string,mixed>> $rows
     * @return array<int,array<string,mixed>>
     */
    private function decorate(array $rows, string $today, int $horizon, string $expiryField = 'expires_on'): array
    {
        foreach ($rows as $index => $row) {
            $expiresOn = isset($row[$expiryField]) && $row[$expiryField] !== null ? (string) $row[$expiryField] : null;
            $rows[$index]['expires_on'] = $expiresOn;
            $rows[$index]['status'] = ConformiteService::status($expiresOn, $today, $horizon);
            $rows[$index]['days_left'] = ConformiteService::daysLeft($expiresOn, $today);
            $rows[$index]['label'] = $this->rowLabel($row, $expiryField);
            $rows[$index]['detail'] = (string) ($row['reference'] ?? $row['policy_number'] ?? $row['provider'] ?? '');
        }

        return $rows;
    }

    /** @param array<string,mixed> $row */
    private function rowLabel(array $row, string $expiryField): string
    {
        if (isset($row['label'])) {
            return (string) $row['label'];
        }
        if (isset($row['insurer'])) {
            return (string) $row['insurer'];
        }
        if (isset($row['title'])) {
            return (string) $row['title'];
        }

        return (string) ($row['name'] ?? $expiryField);
    }

    /** Retour au tableau de bord en conservant l’horizon d’alerte affiché. */
    private function backToList(Request $request): Response
    {
        $horizon = ConformiteService::horizon($request->input('horizon', ''));

        return Response::redirect(url('/conformite?horizon=' . $horizon));
    }

    /** @return array<string,mixed> */
    private function context(string $titleKey): array
    {
        return (new ViewContext())->authenticated(__($titleKey), 'conformite-oat');
    }

    private function requireView(): ?Response
    {
        return Auth::can('projects.view') || Auth::can('settings.view')
            ? null
            : Response::html(__('common.forbidden'), 403);
    }

    private function requireManage(): ?Response
    {
        return Auth::can('projects.manage') || Auth::can('settings.manage')
            ? null
            : Response::html(__('common.forbidden'), 403);
    }

    private function canManage(): bool
    {
        return Auth::can('projects.manage') || Auth::can('settings.manage');
    }
}
