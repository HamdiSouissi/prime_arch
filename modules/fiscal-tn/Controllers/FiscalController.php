<?php
declare(strict_types=1);

namespace Modules\FiscalTn\Controllers;

use App\Core\Auth;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\View;
use App\Services\AuditLogger;
use App\Services\ViewContext;
use Modules\FiscalTn\Models\FiscalRepository;
use Modules\FiscalTn\Services\FiscalService;
use Throwable;

/**
 * Échéancier fiscal et suivi des déclarations.
 *
 * Aucun taux, aucune périodicité et aucun délai légal ne sont imposés : le cabinet saisit ses
 * obligations, le module se contente de les ordonner, d’en suivre l’état et de les exporter.
 * Droits réutilisés : `documents.view` ou `finance.view` en lecture,
 * `documents.manage`, `finance.manage` ou `settings.manage` en écriture.
 */
final class FiscalController
{
    private FiscalRepository $fiscal;
    private FiscalService $service;

    public function __construct(?FiscalRepository $fiscal = null, ?FiscalService $service = null)
    {
        $this->fiscal = $fiscal ?? new FiscalRepository();
        $this->service = $service ?? new FiscalService();
    }

    /** Calendrier de l’exercice : obligations, états, alertes et récapitulatif par période. */
    public function index(Request $request): Response
    {
        if (($denied = $this->requireView()) !== null) {
            return $denied;
        }
        $today = date('Y-m-d');
        $year = self::year($request->query('year', ''));
        $kindRaw = (string) $request->query('kind', '');
        $kind = in_array($kindRaw, FiscalService::KINDS, true) ? $kindRaw : '';
        $statusRaw = (string) $request->query('status', '');
        $status = in_array($statusRaw, array_merge([''], FiscalService::STATUSES, ['late']), true) ? $statusRaw : '';
        $horizon = FiscalService::horizon($request->query('horizon', ''));

        $rows = $this->fiscal->echeances($year, $kind, $status, $today);
        $all = $this->fiscal->echeances($year, '', '', $today);
        foreach ($all as $index => $row) {
            $all[$index]['display_status'] = FiscalService::displayStatus(
                isset($row['due_on']) ? (string) $row['due_on'] : null,
                (string) $row['status'],
                $today
            );
            $all[$index]['days_left'] = FiscalService::daysLeft(isset($row['due_on']) ? (string) $row['due_on'] : null, $today);
        }

        $upcoming = [];
        foreach ($all as $row) {
            $daysLeft = $row['days_left'];
            if ($row['display_status'] === 'paid' || $daysLeft === null) {
                continue;
            }
            if ($daysLeft <= $horizon) {
                $upcoming[] = $row;
            }
        }

        $selectedId = (int) $request->query('echeance', 0);
        $declarationErrors = Session::pullFlash('fiscal.declaration_errors', []);
        $declarationValues = Session::pullFlash('fiscal.declaration_values', []);
        if ($declarationErrors !== [] && (int) ($declarationValues['echeance_id'] ?? 0) > 0) {
            $selectedId = (int) $declarationValues['echeance_id'];
        }

        return Response::html(View::renderModule('fiscal-tn', 'index', $this->context('fiscal.index_title') + [
            'today' => $today,
            'year' => $year,
            'years' => $this->years($year),
            'kind' => $kind,
            'status' => $status,
            'horizon' => $horizon,
            'rows' => $rows,
            'all' => $all,
            'upcoming' => $upcoming,
            'byPeriod' => $this->service->byPeriod($rows, $today),
            'summary' => $this->service->summary($rows, $today),
            'totals' => $this->service->summary($all, $today),
            'selectedId' => $selectedId,
            'kinds' => FiscalService::KINDS,
            'statuses' => FiscalService::STATUSES,
            'canManage' => $this->canManage(),
            'notice' => Session::pullFlash('fiscal.notice'),
            'error' => Session::pullFlash('fiscal.error'),
            'echeanceErrors' => Session::pullFlash('fiscal.echeance_errors', []),
            'echeanceValues' => Session::pullFlash('fiscal.echeance_values', []),
            'declarationErrors' => $declarationErrors,
            'declarationValues' => $declarationValues,
        ]));
    }

    /** Ajoute une obligation fiscale. */
    public function storeEcheance(Request $request): Response
    {
        if (($denied = $this->requireManage()) !== null) {
            return $denied;
        }
        $validated = $this->service->validateEcheance($request->all());
        if ($validated['errors'] !== []) {
            Session::flash('fiscal.echeance_errors', $validated['errors']);
            Session::flash('fiscal.echeance_values', $validated['values']);

            return $this->backToCalendar($request);
        }
        try {
            $id = $this->fiscal->createEcheance($validated['values'], Auth::id());
            (new AuditLogger())->record('fiscal.echeance_created', 'module', $id, [
                'kind' => $validated['values']['kind'],
                'due_on' => $validated['values']['due_on'],
            ], Auth::id());
            Session::flash('fiscal.notice', __('fiscal.echeance_created'));
        } catch (Throwable $error) {
            error_log('Création d’une échéance fiscale impossible : ' . $error->getMessage());
            Session::flash('fiscal.error', __('fiscal.save_failed'));
        }

        return $this->backToCalendar($request);
    }

    /** Enregistre l’état d’une déclaration : à préparer, déposée ou payée. */
    public function saveDeclaration(Request $request): Response
    {
        if (($denied = $this->requireManage()) !== null) {
            return $denied;
        }
        $validated = $this->service->validateDeclaration($request->all(), $this->fiscal->ids());
        if ($validated['errors'] !== []) {
            Session::flash('fiscal.declaration_errors', $validated['errors']);
            Session::flash('fiscal.declaration_values', $validated['values']);

            return $this->backToCalendar($request);
        }
        try {
            $declarationId = $this->fiscal->saveDeclaration($validated['values'], Auth::id());
            (new AuditLogger())->record('fiscal.declaration_saved', 'module', $declarationId, [
                'echeance_id' => $validated['values']['echeance_id'],
                'status' => $validated['values']['status'],
            ], Auth::id());
            Session::flash('fiscal.notice', __('fiscal.declaration_saved'));
        } catch (Throwable $error) {
            error_log('Enregistrement d’une déclaration fiscale impossible : ' . $error->getMessage());
            Session::flash('fiscal.error', __('fiscal.save_failed'));
        }

        return $this->backToCalendar($request);
    }

    /** Supprime une obligation (et sa déclaration). */
    public function deleteEcheance(Request $request, string $echeanceId): Response
    {
        if (($denied = $this->requireManage()) !== null) {
            return $denied;
        }
        if (!ctype_digit($echeanceId) || $this->fiscal->findEcheance((int) $echeanceId) === null) {
            Session::flash('fiscal.error', __('fiscal.echeance_not_found'));

            return $this->backToCalendar($request);
        }
        $this->fiscal->deleteEcheance((int) $echeanceId);
        (new AuditLogger())->record('fiscal.echeance_deleted', 'module', (int) $echeanceId, [], Auth::id());
        Session::flash('fiscal.notice', __('fiscal.echeance_deleted'));

        return $this->backToCalendar($request);
    }

    /** Export CSV de l’échéancier affiché. */
    public function exportCsv(Request $request): Response
    {
        if (($denied = $this->requireView()) !== null) {
            return $denied;
        }
        $today = date('Y-m-d');
        $year = self::year($request->query('year', ''));
        $kindRaw = (string) $request->query('kind', '');
        $kind = in_array($kindRaw, FiscalService::KINDS, true) ? $kindRaw : '';
        $statusRaw = (string) $request->query('status', '');
        $status = in_array($statusRaw, array_merge([''], FiscalService::STATUSES, ['late']), true) ? $statusRaw : '';
        $rows = $this->fiscal->echeances($year, $kind, $status, $today);

        $table = [[
            __('fiscal.kind.label'),
            __('fiscal.label'),
            __('fiscal.period_start'),
            __('fiscal.period_end'),
            __('fiscal.due_on'),
            __('fiscal.status.label'),
            __('fiscal.amount'),
            __('fiscal.filed_on'),
            __('fiscal.paid_on'),
            __('fiscal.reference'),
            __('fiscal.note'),
        ]];
        foreach ($rows as $row) {
            $statusValue = FiscalService::displayStatus(
                isset($row['due_on']) ? (string) $row['due_on'] : null,
                (string) $row['status'],
                $today
            );
            $amount = FiscalService::millimes($row['amount'] ?? '');
            $table[] = [
                __('fiscal.kind.' . (string) $row['kind']),
                (string) $row['label'],
                self::dateColumn((string) $row['period_start']),
                self::dateColumn((string) $row['period_end']),
                self::dateColumn((string) $row['due_on']),
                __('fiscal.status.' . $statusValue),
                $amount === null ? '' : FiscalService::format($amount),
                self::dateColumn((string) ($row['filed_on'] ?? '')),
                self::dateColumn((string) ($row['paid_on'] ?? '')),
                (string) ($row['reference'] ?? ''),
                (string) ($row['note'] ?? ''),
            ];
        }

        return Response::download(
            $this->service->csv($table),
            'prime-arch-echeancier-fiscal-' . $year . '.csv',
            'text/csv; charset=utf-8'
        );
    }

    /**
     * Années proposées : celles de l’échéancier, plus l’année affichée et l’année courante.
     *
     * @return array<int,int>
     */
    private function years(int $selected): array
    {
        $years = $this->fiscal->years();
        foreach ([$selected, (int) date('Y')] as $year) {
            if (!in_array($year, $years, true)) {
                $years[] = $year;
            }
        }
        rsort($years);

        return $years;
    }

    private static function year(mixed $raw): int
    {
        $text = is_string($raw) || is_int($raw) ? trim((string) $raw) : '';
        $current = (int) date('Y');
        if (!ctype_digit($text)) {
            return $current;
        }
        $year = (int) $text;

        return $year >= 2000 && $year <= 2100 ? $year : $current;
    }

    private static function dateColumn(string $value): string
    {
        $timestamp = strtotime($value);

        return $timestamp === false ? '' : date('d/m/Y', $timestamp);
    }

    /** Retour au calendrier en conservant l’exercice, les filtres et la fenêtre d’alerte. */
    private function backToCalendar(Request $request): Response
    {
        // Les filtres sont relus dans des champs dédiés : « kind » et « status » servent aux
        // formulaires eux-mêmes (type d’obligation, état de la déclaration).
        $kindFilter = (string) $request->input('kind_filter', '');
        $statusFilter = (string) $request->input('status_filter', '');
        $query = array_filter([
            'year' => self::year($request->input('year', '')),
            'kind' => in_array($kindFilter, FiscalService::KINDS, true) ? $kindFilter : '',
            'status' => in_array($statusFilter, array_merge([''], FiscalService::STATUSES, ['late']), true) ? $statusFilter : '',
            'horizon' => (string) FiscalService::horizon($request->input('horizon', '')),
        ], static fn (string $value): bool => $value !== '');

        return Response::redirect(url('/fiscal?' . http_build_query($query)));
    }

    /** @return array<string,mixed> */
    private function context(string $titleKey): array
    {
        return (new ViewContext())->authenticated(__($titleKey), 'fiscal-tn');
    }

    private function requireView(): ?Response
    {
        return Auth::can('documents.view') || Auth::can('finance.view')
            ? null
            : Response::html(__('common.forbidden'), 403);
    }

    private function requireManage(): ?Response
    {
        return Auth::can('documents.manage') || Auth::can('finance.manage') || Auth::can('settings.manage')
            ? null
            : Response::html(__('common.forbidden'), 403);
    }

    private function canManage(): bool
    {
        return Auth::can('documents.manage') || Auth::can('finance.manage') || Auth::can('settings.manage');
    }
}
