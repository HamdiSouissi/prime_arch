<?php
declare(strict_types=1);

namespace Modules\RelancesFactures\Controllers;

use App\Core\Auth;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\Translator;
use App\Core\View;
use App\Services\AuditLogger;
use App\Services\ViewContext;
use Modules\RelancesFactures\Models\RelanceRepository;
use Modules\RelancesFactures\Services\RelanceService;
use PDOException;
use Throwable;

/**
 * Balance âgée, file de relances, courrier à copier-coller et règles de relance.
 *
 * Droits réutilisés du cœur : `documents.view` en lecture, `documents.manage` pour consigner
 * une relance, modifier une règle ou exclure un client. Aucune donnée du noyau n’est modifiée.
 */
final class RelanceController
{
    private RelanceRepository $relances;
    private RelanceService $service;

    public function __construct(?RelanceRepository $relances = null, ?RelanceService $service = null)
    {
        $this->relances = $relances ?? new RelanceRepository();
        $this->service = $service ?? new RelanceService($this->relances);
    }

    /** Tableau de bord : encours total, balance âgée et derniers courriers. */
    public function index(Request $request): Response
    {
        if (($denied = $this->requireView()) !== null) {
            return $denied;
        }
        $invoices = $this->enrichedInvoices();
        $rules = $this->relances->rules();

        return Response::html(View::renderModule('relances-factures', 'index', $this->context('relance.index_title') + [
            'totals' => $this->service->totals($invoices),
            'clients' => array_slice($this->service->byClient($invoices), 0, 50),
            'invoices' => array_slice($invoices, 0, 12),
            'rulesCount' => count($rules),
            'events' => $this->relances->events(null, null, 6),
            'exclusions' => $this->relances->exclusions(),
            'canManage' => $this->canManage(),
            'notice' => Session::pullFlash('relance.notice'),
            'error' => Session::pullFlash('relance.error'),
        ]));
    }

    /** File de relances : factures impayées, retard, tonalité suggérée. */
    public function unpaid(Request $request): Response
    {
        if (($denied = $this->requireView()) !== null) {
            return $denied;
        }
        $filters = [
            'q' => self::queryText($request, 'q', 100),
            'bucket' => self::queryText($request, 'bucket', 10),
            'hide_excluded' => (string) $request->query('hide_excluded', '1') === '1',
        ];
        if (!array_key_exists($filters['bucket'], RelanceService::BUCKETS)) {
            $filters['bucket'] = '';
        }

        $rules = $this->relances->rules();
        $invoices = $this->enrichedInvoices();
        $rows = [];
        foreach ($invoices as $invoice) {
            if ($filters['hide_excluded'] && (bool) $invoice['is_excluded']) {
                continue;
            }
            if ($filters['bucket'] !== '' && $invoice['bucket'] !== $filters['bucket']) {
                continue;
            }
            if ($filters['q'] !== '') {
                $haystack = mb_strtolower(
                    (string) ($invoice['client_name'] ?? '') . ' ' . (string) ($invoice['document_number'] ?? '')
                    . ' ' . (string) ($invoice['title'] ?? '') . ' ' . (string) ($invoice['project_name'] ?? ''),
                    'UTF-8'
                );
                if (!str_contains($haystack, mb_strtolower($filters['q'], 'UTF-8'))) {
                    continue;
                }
            }
            $rule = $this->service->applicableRule(
                $rules,
                (int) $invoice['days_late'],
                RelanceService::decimal($invoice['outstanding'])
            );
            $invoice['suggested_rule'] = $rule;
            $invoice['suggested_tone'] = is_array($rule) ? (string) $rule['tone'] : 'courtesy';
            $rows[] = $invoice;
        }

        return Response::html(View::renderModule('relances-factures', 'unpaid', $this->context('relance.unpaid_title') + [
            'invoices' => $rows,
            'filters' => $filters,
            'totals' => $this->service->totals($rows),
            'buckets' => RelanceService::BUCKETS,
            'canManage' => $this->canManage(),
            'notice' => Session::pullFlash('relance.notice'),
            'error' => Session::pullFlash('relance.error'),
        ]));
    }

    /** Courrier de relance prêt à copier, avec l’historique de la facture. */
    public function letterForm(Request $request, string $documentId): Response
    {
        if (($denied = $this->requireView()) !== null) {
            return $denied;
        }
        $invoice = ctype_digit($documentId) ? $this->relances->findInvoice((int) $documentId) : null;
        if ($invoice === null) {
            return Response::html(__('relance.not_found'), 404);
        }
        $enriched = $this->service->enrich([$invoice]);
        if ($enriched === []) {
            return Response::html(__('relance.already_paid'), 409);
        }
        $invoice = $enriched[0];

        $tone = self::queryText($request, 'tone', 20);
        if (!in_array($tone, RelanceService::TONES, true)) {
            $rule = $this->service->applicableRule(
                $this->relances->rules(),
                (int) $invoice['days_late'],
                RelanceService::decimal($invoice['outstanding'])
            );
            $tone = is_array($rule) ? (string) $rule['tone'] : 'courtesy';
        }
        $letter = $this->service->letter($invoice, $tone, $this->relances->templates(), Translator::locale());

        return Response::html(View::renderModule('relances-factures', 'letter', $this->context('relance.letter_title') + [
            'invoice' => $invoice,
            'tone' => $tone,
            'tones' => RelanceService::TONES,
            'letter' => $letter,
            'events' => $this->relances->events((int) $invoice['id'], null, 50),
            'canManage' => $this->canManage(),
            'notice' => Session::pullFlash('relance.notice'),
            'error' => Session::pullFlash('relance.error'),
            'eventErrors' => Session::pullFlash('relance.event_errors', []),
            'eventValues' => Session::pullFlash('relance.event_values', []),
            'channels' => RelanceService::CHANNELS,
            'outcomes' => RelanceService::OUTCOMES,
        ]));
    }

    /** Consigne une relance (courrier, appel, promesse de paiement, litige). */
    public function record(Request $request, string $documentId): Response
    {
        if (($denied = $this->requireManage()) !== null) {
            return $denied;
        }
        $invoice = ctype_digit($documentId) ? $this->relances->findInvoice((int) $documentId) : null;
        if ($invoice === null) {
            return Response::html(__('relance.not_found'), 404);
        }

        $validated = $this->service->validateEvent($request->all());
        if ($validated['errors'] !== []) {
            Session::flash('relance.event_errors', $validated['errors']);
            Session::flash('relance.event_values', $validated['values']);

            return Response::redirect(url('/relances/factures/' . (int) $invoice['id']));
        }

        $values = $validated['values'];
        $values['regle_id'] = $values['regle_id'] !== '' ? (int) $values['regle_id'] : null;
        $values['promise_on'] = $values['promise_on'] !== '' ? $values['promise_on'] : null;
        $values['promise_amount'] = $values['promise_amount'] !== '' ? $values['promise_amount'] : null;
        $values['note'] = $values['note'] !== '' ? $values['note'] : null;

        $eventId = $this->relances->addEvent((int) $invoice['id'], (int) $invoice['client_id'], $values, Auth::id());
        (new AuditLogger())->record('relance.event_recorded', 'document', (int) $invoice['id'], [
            'event_id' => $eventId,
            'tone' => $values['tone'],
            'outcome' => $values['outcome'],
        ], Auth::id());
        Session::flash('relance.notice', __('relance.event_saved'));

        return Response::redirect(url('/relances/factures/' . (int) $invoice['id']));
    }

    /** Historique complet des relances, filtrable par client ou par facture. */
    public function history(Request $request): Response
    {
        if (($denied = $this->requireView()) !== null) {
            return $denied;
        }
        $filters = ['q' => self::queryText($request, 'q', 100)];
        $events = $this->relances->events(null, null, 500);
        if ($filters['q'] !== '') {
            $needle = mb_strtolower($filters['q'], 'UTF-8');
            $events = array_values(array_filter($events, static function (array $event) use ($needle): bool {
                $haystack = mb_strtolower(
                    (string) ($event['client_name'] ?? '') . ' ' . (string) ($event['document_number'] ?? '')
                    . ' ' . (string) ($event['note'] ?? ''),
                    'UTF-8'
                );

                return str_contains($haystack, $needle);
            }));
        }

        return Response::html(View::renderModule('relances-factures', 'history', $this->context('relance.history_title') + [
            'events' => $events,
            'filters' => $filters,
            'canManage' => $this->canManage(),
            'notice' => Session::pullFlash('relance.notice'),
            'error' => Session::pullFlash('relance.error'),
        ]));
    }

    /** Règles de relance et modèles de courrier FR/AR. */
    public function rules(Request $request): Response
    {
        if (($denied = $this->requireView()) !== null) {
            return $denied;
        }
        $templates = $this->relances->templates();

        return Response::html(View::renderModule('relances-factures', 'rules', $this->context('relance.rules_title') + [
            'rules' => $this->relances->rules(),
            'tones' => RelanceService::TONES,
            'templates' => $templates,
            'defaults' => $this->defaultTemplates(),
            'exclusions' => $this->relances->exclusions(),
            'canManage' => $this->canManage(),
            'notice' => Session::pullFlash('relance.notice'),
            'error' => Session::pullFlash('relance.error'),
            'ruleErrors' => Session::pullFlash('relance.rule_errors', []),
            'ruleValues' => Session::pullFlash('relance.rule_values', []),
            'templateErrors' => Session::pullFlash('relance.template_errors', []),
            'templateValues' => Session::pullFlash('relance.template_values', []),
        ]));
    }

    /** Crée une règle (ou la met à jour si un identifiant est fourni). */
    public function saveRule(Request $request): Response
    {
        if (($denied = $this->requireManage()) !== null) {
            return $denied;
        }
        $validated = $this->service->validateRule($request->all());
        $ruleId = $request->input('id');
        $currentId = is_string($ruleId) && ctype_digit($ruleId) ? (int) $ruleId : null;

        if ($validated['errors'] !== []) {
            Session::flash('relance.rule_errors', $validated['errors']);
            Session::flash('relance.rule_values', $validated['values']);

            return Response::redirect(url('/relances/regles') . '#regles');
        }

        try {
            if ($currentId !== null) {
                $this->relances->updateRule($currentId, $validated['values']);
                (new AuditLogger())->record('relance.rule_updated', 'module', $currentId, [], Auth::id());
                Session::flash('relance.notice', __('relance.rule_updated'));
            } else {
                $newId = $this->relances->createRule($validated['values'], Auth::id());
                (new AuditLogger())->record('relance.rule_created', 'module', $newId, [], Auth::id());
                Session::flash('relance.notice', __('relance.rule_added'));
            }
        } catch (PDOException) {
            // Contrainte d’unicité sur le nom : message explicite plutôt qu’une erreur 500.
            Session::flash('relance.error', __('relance.rule_name_taken'));
            Session::flash('relance.rule_values', $validated['values']);

            return Response::redirect(url('/relances/regles') . '#regles');
        }

        return Response::redirect(url('/relances/regles') . '#regles');
    }

    /** Bascule ou supprime une règle existante. */
    public function updateRule(Request $request, string $ruleId): Response
    {
        if (($denied = $this->requireManage()) !== null) {
            return $denied;
        }
        $rule = ctype_digit($ruleId) ? $this->relances->findRule((int) $ruleId) : null;
        if ($rule === null) {
            Session::flash('relance.error', __('relance.rule_not_found'));

            return Response::redirect(url('/relances/regles') . '#regles');
        }

        $action = (string) $request->input('action', 'toggle');
        if ($action === 'delete') {
            $this->relances->deleteRule((int) $rule['id']);
            (new AuditLogger())->record('relance.rule_deleted', 'module', (int) $rule['id'], [], Auth::id());
            Session::flash('relance.notice', __('relance.rule_deleted'));
        } else {
            $values = [
                'name' => (string) $rule['name'],
                'delay_days' => (string) $rule['delay_days'],
                'min_amount' => (string) $rule['min_amount'],
                'tone' => (string) $rule['tone'],
                'sort_order' => (string) $rule['sort_order'],
                'is_active' => (bool) $rule['is_active'] ? '0' : '1',
            ];
            $this->relances->updateRule((int) $rule['id'], $this->service->validateRule($values)['values']);
            Session::flash('relance.notice', (bool) $rule['is_active'] ? __('relance.rule_paused_notice') : __('relance.rule_resumed_notice'));
        }

        return Response::redirect(url('/relances/regles') . '#regles');
    }

    /** Enregistre ou réinitialise un modèle de courrier (tonalité + langue). */
    public function saveTemplate(Request $request): Response
    {
        if (($denied = $this->requireManage()) !== null) {
            return $denied;
        }
        $action = (string) $request->input('action', 'save');
        $validated = $this->service->validateTemplate($request->all());

        if ($validated['errors'] !== []) {
            Session::flash('relance.template_errors', $validated['errors']);
            Session::flash('relance.template_values', $validated['values']);

            return Response::redirect(url('/relances/regles') . '#modeles');
        }

        $values = $validated['values'];
        if ($action === 'reset') {
            $this->relances->deleteTemplate($values['tone'], $values['locale']);
            Session::flash('relance.notice', __('relance.template_reset'));
        } else {
            $this->relances->saveTemplate(
                $values['tone'],
                $values['locale'],
                $values['subject'],
                $values['body'],
                Auth::id()
            );
            Session::flash('relance.notice', __('relance.template_saved'));
        }

        return Response::redirect(url('/relances/regles') . '#modeles');
    }

    /** Exclut un client des relances, ou lève l’exclusion. */
    public function excludeClient(Request $request, string $clientId): Response
    {
        if (($denied = $this->requireManage()) !== null) {
            return $denied;
        }
        if (!ctype_digit($clientId)) {
            return Response::html(__('relance.client_not_found'), 404);
        }
        $id = (int) $clientId;
        $action = (string) $request->input('action', 'exclude');

        if ($action === 'include') {
            $this->relances->includeClient($id);
            (new AuditLogger())->record('relance.client_included', 'client', $id, [], Auth::id());
            Session::flash('relance.notice', __('relance.client_included'));
        } else {
            $reason = $request->input('reason');
            $this->relances->excludeClient($id, is_string($reason) ? mb_substr(trim($reason), 0, 255, 'UTF-8') : '', Auth::id());
            (new AuditLogger())->record('relance.client_excluded', 'client', $id, [], Auth::id());
            Session::flash('relance.notice', __('relance.client_excluded'));
        }

        $back = (string) $request->input('back', '/relances');
        if (preg_match('#^/relances(/[a-z0-9/-]*)?$#i', $back) !== 1) {
            $back = '/relances';
        }

        return Response::redirect(url($back));
    }

    /** @return array<int,array<string,mixed>> */
    private function enrichedInvoices(): array
    {
        return $this->service->enrich($this->relances->unpaidInvoices());
    }

    /**
     * Textes par défaut affichés à titre d’exemple sous chaque modèle.
     *
     * @return array<string,array{subject:string,body:string}>
     */
    private function defaultTemplates(): array
    {
        $defaults = [];
        foreach (['fr', 'ar'] as $locale) {
            $file = dirname(__DIR__) . '/lang/' . $locale . '.php';
            $messages = is_file($file) ? (array) require $file : [];
            foreach (RelanceService::TONES as $tone) {
                $defaults[$tone . '|' . $locale] = [
                    'subject' => (string) ($messages['relance.modele.' . $tone . '.subject'] ?? ''),
                    'body' => (string) ($messages['relance.modele.' . $tone . '.body'] ?? ''),
                ];
            }
        }

        return $defaults;
    }

    /** @return array<string,mixed> */
    private function context(string $titleKey): array
    {
        return (new ViewContext())->authenticated(__($titleKey), 'relances-factures');
    }

    private function requireView(): ?Response
    {
        return Auth::can('documents.view') ? null : Response::html(__('common.forbidden'), 403);
    }

    private function requireManage(): ?Response
    {
        return $this->canManage() ? null : Response::html(__('common.forbidden'), 403);
    }

    private function canManage(): bool
    {
        return Auth::can('documents.manage');
    }

    private static function queryText(Request $request, string $key, int $maxLength): string
    {
        $value = $request->query($key);

        return is_string($value) ? mb_substr(trim($value), 0, $maxLength, 'UTF-8') : '';
    }
}
