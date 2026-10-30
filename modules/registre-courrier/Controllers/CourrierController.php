<?php
declare(strict_types=1);

namespace Modules\RegistreCourrier\Controllers;

use App\Core\Auth;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\View;
use App\Services\AuditLogger;
use App\Services\CabinetSettings;
use App\Services\ViewContext;
use Modules\RegistreCourrier\Models\CourrierRepository;
use Modules\RegistreCourrier\Services\CourrierService;
use Throwable;

/**
 * Écrans du registre du courrier.
 *
 * Droits réutilisés du cœur : `clients.view` en lecture, `clients.manage` en écriture.
 * Aucun envoi de courriel, aucune numérotation légale : le registre est un outil interne.
 */
final class CourrierController
{
    private CourrierRepository $courrier;
    private CourrierService $service;

    public function __construct(?CourrierRepository $courrier = null, ?CourrierService $service = null)
    {
        $this->courrier = $courrier ?? new CourrierRepository();
        $this->service = $service ?? new CourrierService($this->courrier);
    }

    public function index(Request $request): Response
    {
        if (($denied = $this->requireView()) !== null) {
            return $denied;
        }

        $filters = $this->filters($request);
        $page = self::pageNumber($request->query('page'));
        $perPage = 20;
        $result = $this->courrier->paginate($filters, $perPage, ($page - 1) * $perPage);

        $projectFilter = null;
        if ($filters['projet'] !== '') {
            $projectFilter = $this->courrier->projectRow((int) $filters['projet']);
            if ($projectFilter === null) {
                $filters['projet'] = '';
            }
        }

        return Response::html(View::renderModule('registre-courrier', 'index', $this->context('courrier.title') + [
            'courrier' => $result['items'],
            'total' => $result['total'],
            'page' => $page,
            'pageCount' => max(1, (int) ceil($result['total'] / $perPage)),
            'filters' => $filters,
            'years' => $this->courrier->years(),
            'statistics' => $this->courrier->statistics(),
            'projectFilter' => $projectFilter,
            'canManage' => $this->canManage(),
            'notice' => Session::pullFlash('courrier.notice'),
            'error' => Session::pullFlash('courrier.error'),
        ]));
    }

    public function show(Request $request, string $id): Response
    {
        if (($denied = $this->requireView()) !== null) {
            return $denied;
        }
        $entry = $this->find($id);
        if ($entry === null) {
            return Response::html(__('courrier.not_found'), 404);
        }

        return Response::html(View::renderModule('registre-courrier', 'show', $this->context('courrier.detail_title') + [
            'entry' => $entry,
            'statuts' => CourrierService::STATUTS,
            'canManage' => $this->canManage(),
            'notice' => Session::pullFlash('courrier.notice'),
            'error' => Session::pullFlash('courrier.error'),
        ]));
    }

    public function createForm(Request $request): Response
    {
        if (($denied = $this->requireManage()) !== null) {
            return $denied;
        }

        return Response::html(View::renderModule('registre-courrier', 'form', $this->context('courrier.create') + [
            'entry' => null,
            'values' => [
                'sens' => self::queryText($request, 'sens', 3) === 'out' ? 'out' : 'in',
                'date_courrier' => date('Y-m-d'),
                'correspondant' => '',
                'objet' => '',
                'reference' => '',
                'mode' => 'other',
                'statut' => 'todo',
                'project_id' => self::queryText($request, 'projet', 10),
                'client_id' => '',
                'echeance' => '',
                'notes' => '',
            ],
            'errors' => [],
            'projects' => Auth::can('projects.view') ? $this->courrier->projectsForSelect() : [],
            'clients' => $this->courrier->clientsForSelect(),
            'action' => url('/courrier'),
            'cancelUrl' => url('/courrier'),
        ]));
    }

    public function create(Request $request): Response
    {
        if (($denied = $this->requireManage()) !== null) {
            return $denied;
        }

        $validated = $this->service->validate($request->all());
        if ($validated['errors'] !== []) {
            return Response::html(View::renderModule('registre-courrier', 'form', $this->context('courrier.create') + [
                'entry' => null,
                'values' => $validated['values'],
                'errors' => $validated['errors'],
                'projects' => Auth::can('projects.view') ? $this->courrier->projectsForSelect() : [],
                'clients' => $this->courrier->clientsForSelect(),
                'action' => url('/courrier'),
                'cancelUrl' => url('/courrier'),
            ]));
        }

        try {
            $id = $this->courrier->create($validated['values'], Auth::id());
            (new AuditLogger())->record('courrier.created', 'module', $id, [
                'sens' => $validated['values']['sens'],
                'statut' => $validated['values']['statut'],
            ], Auth::id());
        } catch (Throwable $error) {
            error_log('Enregistrement d’un courrier impossible : ' . $error->getMessage());
            Session::flash('courrier.error', __('courrier.save_failed'));

            return Response::redirect(url('/courrier'));
        }

        Session::flash('courrier.notice', __('courrier.created'));

        return Response::redirect(url('/courrier/' . $id));
    }

    public function editForm(Request $request, string $id): Response
    {
        if (($denied = $this->requireManage()) !== null) {
            return $denied;
        }
        $entry = $this->find($id);
        if ($entry === null) {
            return Response::html(__('courrier.not_found'), 404);
        }

        return Response::html(View::renderModule('registre-courrier', 'form', $this->context('courrier.edit') + [
            'entry' => $entry,
            'values' => self::valuesOf($entry),
            'errors' => [],
            'projects' => Auth::can('projects.view') ? $this->courrier->projectsForSelect() : [],
            'clients' => $this->courrier->clientsForSelect(),
            'action' => url('/courrier/' . (int) $entry['id']),
            'cancelUrl' => url('/courrier/' . (int) $entry['id']),
        ]));
    }

    public function update(Request $request, string $id): Response
    {
        if (($denied = $this->requireManage()) !== null) {
            return $denied;
        }
        $entry = $this->find($id);
        if ($entry === null) {
            return Response::html(__('courrier.not_found'), 404);
        }

        $validated = $this->service->validate($request->all());
        if ($validated['errors'] !== []) {
            return Response::html(View::renderModule('registre-courrier', 'form', $this->context('courrier.edit') + [
                'entry' => $entry,
                'values' => $validated['values'],
                'errors' => $validated['errors'],
                'projects' => Auth::can('projects.view') ? $this->courrier->projectsForSelect() : [],
                'clients' => $this->courrier->clientsForSelect(),
                'action' => url('/courrier/' . (int) $entry['id']),
                'cancelUrl' => url('/courrier/' . (int) $entry['id']),
            ]));
        }

        try {
            $this->courrier->update((int) $entry['id'], $validated['values']);
            (new AuditLogger())->record('courrier.updated', 'module', (int) $entry['id'], [
                'statut' => $validated['values']['statut'],
            ], Auth::id());
        } catch (Throwable $error) {
            error_log('Mise à jour d’un courrier impossible : ' . $error->getMessage());
            Session::flash('courrier.error', __('courrier.save_failed'));

            return Response::redirect(url('/courrier/' . (int) $entry['id']));
        }

        Session::flash('courrier.notice', __('courrier.updated'));

        return Response::redirect(url('/courrier/' . (int) $entry['id']));
    }

    /** Change uniquement l’état de traitement, depuis la fiche. */
    public function updateStatus(Request $request, string $id): Response
    {
        if (($denied = $this->requireManage()) !== null) {
            return $denied;
        }
        $entry = $this->find($id);
        if ($entry === null) {
            return Response::html(__('courrier.not_found'), 404);
        }

        $statut = mb_substr(self::text($request->input('statut', '')), 0, 20, 'UTF-8');
        if (!in_array($statut, CourrierService::STATUTS, true)) {
            Session::flash('courrier.error', __('courrier.invalid'));

            return Response::redirect(url('/courrier/' . (int) $entry['id']));
        }

        try {
            $this->courrier->updateStatus((int) $entry['id'], $statut);
            (new AuditLogger())->record('courrier.status_changed', 'module', (int) $entry['id'], [
                'statut' => $statut,
            ], Auth::id());
        } catch (Throwable $error) {
            error_log('Changement d’état d’un courrier impossible : ' . $error->getMessage());
            Session::flash('courrier.error', __('courrier.save_failed'));
        }

        Session::flash('courrier.notice', __('courrier.status_updated'));

        return Response::redirect(url('/courrier/' . (int) $entry['id']));
    }

    /** Registre annuel imprimable (ou enregistrable en PDF par le navigateur). */
    public function printRegister(Request $request): Response
    {
        if (($denied = $this->requireView()) !== null) {
            return $denied;
        }

        $year = self::queryText($request, 'annee', 4);
        if (!ctype_digit($year) || (int) $year < 1900 || (int) $year > 2200) {
            $year = date('Y');
        }
        $sens = self::queryText($request, 'sens', 3);
        if (!in_array($sens, CourrierService::SENS, true)) {
            $sens = '';
        }

        $cabinetName = '';
        try {
            $cabinetName = (string) ((new CabinetSettings())->get()['name'] ?? '');
        } catch (Throwable) {
            $cabinetName = '';
        }

        return Response::html(View::renderModule('registre-courrier', 'print', [
            'year' => (int) $year,
            'sens' => $sens,
            'rows' => $this->courrier->registerRows((int) $year, $sens),
            'cabinetName' => $cabinetName,
            'generatedAt' => date('d/m/Y H:i'),
        ], null));
    }

    public function exportCsv(Request $request): Response
    {
        if (($denied = $this->requireView()) !== null) {
            return $denied;
        }
        $filters = $this->filters($request);

        $rows = [[
            __('courrier.numero'),
            __('courrier.sens_label'),
            __('courrier.date'),
            __('courrier.correspondant'),
            __('courrier.objet'),
            __('courrier.reference'),
            __('courrier.mode_label'),
            __('courrier.statut_label'),
            __('courrier.echeance'),
            __('courrier.projet'),
            __('courrier.client'),
        ]];
        foreach ($this->courrier->exportRows($filters) as $row) {
            $rows[] = [
                (string) $row['numero'],
                __('courrier.sens.' . (string) $row['sens']),
                (string) $row['date_courrier'],
                (string) $row['correspondant'],
                (string) $row['objet'],
                (string) ($row['reference'] ?? ''),
                __('courrier.mode.' . (string) $row['mode']),
                __('courrier.statut.' . (string) $row['statut']),
                (string) ($row['echeance'] ?? ''),
                (string) ($row['project_name'] ?? ''),
                (string) ($row['client_name'] ?? ''),
            ];
        }

        return Response::download(
            self::encodeCsv($rows),
            'prime-arch-courrier-' . date('Ymd-His') . '.csv',
            'text/csv; charset=utf-8'
        );
    }

    public function settings(Request $request): Response
    {
        if (($denied = $this->requireView()) !== null) {
            return $denied;
        }

        $year = self::queryText($request, 'annee', 4);
        if (!ctype_digit($year) || (int) $year < 1900 || (int) $year > 2200) {
            $year = date('Y');
        }

        $settings = $this->courrier->settings();
        $counters = $this->courrier->counters((int) $year);
        $previews = [];
        foreach (CourrierService::SENS as $sens) {
            $previews[$sens] = $this->service->nextNumberPreview($settings, $sens, (int) $year, (int) ($counters[$sens] ?? 0));
        }

        return Response::html(View::renderModule('registre-courrier', 'settings', $this->context('courrier.settings_title') + [
            'settings' => $settings,
            'year' => (int) $year,
            'counters' => $counters,
            'previews' => $previews,
            'canManage' => $this->canManage(),
            'settingsErrors' => Session::pullFlash('courrier.settings_errors', []),
            'settingsValues' => Session::pullFlash('courrier.settings_values', []),
            'counterErrors' => Session::pullFlash('courrier.counter_errors', []),
            'notice' => Session::pullFlash('courrier.notice'),
            'error' => Session::pullFlash('courrier.error'),
        ]));
    }

    public function saveSettings(Request $request): Response
    {
        if (($denied = $this->requireManage()) !== null) {
            return $denied;
        }

        $validated = $this->service->validateSettings($request->all());
        if ($validated['errors'] !== []) {
            Session::flash('courrier.settings_errors', $validated['errors']);
            Session::flash('courrier.settings_values', $validated['values']);

            return Response::redirect(url('/courrier/reglages'));
        }

        try {
            $this->courrier->saveSettings($validated['values']);
            (new AuditLogger())->record('courrier.settings_updated', 'module', null, [
                'prefix_in' => $validated['values']['prefix_in'],
                'prefix_out' => $validated['values']['prefix_out'],
            ], Auth::id());
            Session::flash('courrier.notice', __('courrier.settings_saved'));
        } catch (Throwable $error) {
            error_log('Enregistrement des réglages de numérotation impossible : ' . $error->getMessage());
            Session::flash('courrier.error', __('courrier.save_failed'));
        }

        return Response::redirect(url('/courrier/reglages'));
    }

    /**
     * Fixe le dernier numéro utilisé pour un sens et une année.
     *
     * Sert à reprendre une numérotation existante : le module ne devine jamais un
     * compteur, il ne fait que constater ce que le cabinet déclare.
     */
    public function saveCounter(Request $request): Response
    {
        if (($denied = $this->requireManage()) !== null) {
            return $denied;
        }

        $sens = self::text($request->input('sens', ''));
        $year = (int) self::text($request->input('annee', ''));
        $last = (int) self::text($request->input('dernier', ''));

        $errors = [];
        if (!in_array($sens, CourrierService::SENS, true)) {
            $errors['sens'] = __('courrier.invalid');
        }
        if ($year < 1900 || $year > 2200) {
            $errors['annee'] = __('courrier.invalid_year');
        }
        if ($last < 0) {
            $errors['dernier'] = __('courrier.invalid');
        }
        if ($errors !== []) {
            Session::flash('courrier.counter_errors', $errors);

            return Response::redirect(url('/courrier/reglages?annee=' . max(1900, min(2200, $year))));
        }

        try {
            $this->courrier->saveCounter($sens, $year, $last);
            (new AuditLogger())->record('courrier.counter_updated', 'module', null, [
                'sens' => $sens,
                'annee' => $year,
                'dernier' => $last,
            ], Auth::id());
            Session::flash('courrier.notice', __('courrier.counter_saved'));
        } catch (Throwable $error) {
            error_log('Enregistrement d’un compteur de courrier impossible : ' . $error->getMessage());
            Session::flash('courrier.error', __('courrier.save_failed'));
        }

        return Response::redirect(url('/courrier/reglages?annee=' . $year));
    }

    /**
     * Filtres de liste, bornés pour éviter les valeurs arbitraires venues de l’URL.
     *
     * @return array{q:string,sens:string,statut:string,annee:string,projet:string}
     */
    private function filters(Request $request): array
    {
        $filters = [
            'q' => self::queryText($request, 'q', 100),
            'sens' => self::queryText($request, 'sens', 3),
            'statut' => self::queryText($request, 'statut', 20),
            'annee' => self::queryText($request, 'annee', 4),
            'projet' => self::queryText($request, 'projet', 10),
        ];
        if (!in_array($filters['sens'], CourrierService::SENS, true)) {
            $filters['sens'] = '';
        }
        if (!in_array($filters['statut'], CourrierService::STATUTS, true)) {
            $filters['statut'] = '';
        }
        if ($filters['annee'] !== '' && (!ctype_digit($filters['annee']) || (int) $filters['annee'] < 1900)) {
            $filters['annee'] = '';
        }
        if ($filters['projet'] !== '' && !ctype_digit($filters['projet'])) {
            $filters['projet'] = '';
        }

        return $filters;
    }

    /** @return array<string,mixed>|null */
    private function find(string $id): ?array
    {
        return ctype_digit($id) ? $this->courrier->find((int) $id) : null;
    }

    /** @param array<string,mixed> $entry @return array<string,string> */
    private static function valuesOf(array $entry): array
    {
        return [
            'sens' => (string) $entry['sens'],
            'date_courrier' => (string) $entry['date_courrier'],
            'correspondant' => (string) $entry['correspondant'],
            'objet' => (string) $entry['objet'],
            'reference' => (string) ($entry['reference'] ?? ''),
            'mode' => (string) $entry['mode'],
            'statut' => (string) $entry['statut'],
            'project_id' => (string) ($entry['project_id'] ?? ''),
            'client_id' => (string) ($entry['client_id'] ?? ''),
            'echeance' => (string) ($entry['echeance'] ?? ''),
            'notes' => (string) ($entry['notes'] ?? ''),
        ];
    }

    /** Contexte de navigation partagé (menu, profil, fil d’Ariane). @return array<string,mixed> */
    private function context(string $titleKey): array
    {
        return (new ViewContext())->authenticated(__($titleKey), 'registre-courrier');
    }

    private function requireView(): ?Response
    {
        return Auth::can('clients.view') ? null : Response::html(__('common.forbidden'), 403);
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
        return Auth::can('clients.manage');
    }

    /** @param array<int,array<int,string>> $rows */
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

    private static function text(mixed $value): string
    {
        return is_string($value) || is_numeric($value) ? trim((string) $value) : '';
    }

    private static function pageNumber(mixed $value): int
    {
        $page = is_string($value) && ctype_digit($value) ? (int) $value : 1;

        return max(1, min($page, 10000));
    }
}
