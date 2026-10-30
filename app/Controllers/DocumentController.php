<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\View;
use App\Models\DocumentRepository;
use App\Models\DocumentTemplateRepository;
use App\Services\AuditLogger;
use App\Services\DocumentService;
use App\Services\ViewContext;
use RuntimeException;
use Throwable;

/** Gestion des brouillons, de l'émission immuable et des règlements. */
final class DocumentController
{
    public function index(Request $request): Response
    {
        if (!self::canViewDocuments()) {
            return Response::html(__('common.forbidden'), 403);
        }
        $filters = [
            'search' => mb_substr(self::queryString($request, 'q'), 0, 100, 'UTF-8'),
            'document_type' => self::queryString($request, 'type'),
            'status' => self::queryString($request, 'status'),
            'client_id' => self::positiveId($request->query('client_id')),
            'project_id' => self::positiveId($request->query('project_id')),
        ];
        if (!in_array($filters['document_type'], ['', 'quote', 'contract', 'invoice', 'credit_note'], true)) {
            $filters['document_type'] = '';
        }
        if (!in_array($filters['status'], ['', 'draft', 'issued', 'void'], true)) {
            $filters['status'] = '';
        }
        $page = self::page($request->query('page'));
        $repository = new DocumentRepository();
        $result = $repository->paginate($filters, 20, ($page - 1) * 20);
        $context = (new ViewContext())->authenticated(__('documents.title'), 'documents');
        return Response::html(View::render('pages/documents/index', $context + [
            'documents' => $result['items'],
            'total' => $result['total'],
            'page' => $page,
            'pageCount' => max(1, (int) ceil($result['total'] / 20)),
            'filters' => $filters,
            'summary' => $repository->summary(),
            'notice' => Session::pullFlash('documents.notice'),
            'clients' => $repository->clientOptions(),
            'canCreate' => Auth::can('documents.create') || Auth::can('documents.manage'),
            'canManageTemplates' => Auth::can('documents.templates.manage') || Auth::can('settings.manage'),
        ]));
    }

    public function createForm(Request $request): Response
    {
        if (!Auth::can('documents.create') && !Auth::can('documents.manage')) {
            return Response::html(__('common.forbidden'), 403);
        }
        $type = self::queryString($request, 'type');
        $locale = self::queryString($request, 'locale');
        $referenceId = self::positiveId($request->query('reference_id'));
        $defaults = (new DocumentService())->draftDefaults(
            in_array($type, ['quote', 'contract', 'invoice', 'credit_note'], true) ? $type : 'quote',
            in_array($locale, ['fr', 'ar'], true) ? $locale : 'fr',
            $referenceId > 0 ? $referenceId : null
        );
        $values = Session::pullFlash('documents.values', []);
        if (!is_array($values) || $values === []) {
            $values = $defaults;
        }
        return $this->formResponse(
            $values,
            null,
            Session::pullFlash('documents.errors', []),
            __('documents.new'),
            false
        );
    }

    public function create(Request $request): Response
    {
        if (!Auth::can('documents.create') && !Auth::can('documents.manage')) {
            return Response::html(__('common.forbidden'), 403);
        }
        $service = new DocumentService();
        $validated = $service->validateDraft($request->all());
        if ($validated['errors'] !== []) {
            Session::flash('documents.errors', $validated['errors']);
            Session::flash('documents.values', $validated['values']);
            return Response::redirect(url('/documents/new'));
        }
        try {
            $id = $service->createDraft($validated['values'], (int) Auth::id());
        } catch (Throwable $error) {
            error_log((string) $error);
            Session::flash('documents.errors', ['general' => __('documents.save_failed')]);
            Session::flash('documents.values', $validated['values']);
            return Response::redirect(url('/documents/new'));
        }
        (new AuditLogger())->record('documents.created', 'document', $id, [
            'document_type' => (string) ($validated['values']['document_type'] ?? ''),
        ], Auth::id());
        Session::flash('documents.notice', __('documents.created'));
        return Response::redirect(url('/documents/' . $id));
    }

    public function edit(Request $request, string $id): Response
    {
        if (!Auth::can('documents.manage')) {
            return Response::html(__('common.forbidden'), 403);
        }
        $documentId = filter_var($id, FILTER_VALIDATE_INT);
        $service = new DocumentService();
        $detail = $documentId !== false && $documentId > 0 ? $service->detail((int) $documentId) : null;
        if ($detail === null) {
            return Response::html(View::render('pages/404', [], 'layouts/app'), 404);
        }
        if ($detail['document']['status'] !== 'draft') {
            return Response::html(__('common.forbidden'), 403);
        }
        $values = Session::pullFlash('documents.values', []);
        if (!is_array($values) || $values === []) {
            $values = $this->valuesFromDetail($detail);
        }
        return $this->formResponse(
            $values,
            $detail['document'],
            Session::pullFlash('documents.errors', []),
            __('documents.edit'),
            true
        );
    }

    public function update(Request $request, string $id): Response
    {
        if (!Auth::can('documents.manage')) {
            return Response::html(__('common.forbidden'), 403);
        }
        $documentId = filter_var($id, FILTER_VALIDATE_INT);
        if ($documentId === false || $documentId < 1) {
            return Response::html(View::render('pages/404', [], 'layouts/app'), 404);
        }
        $repository = new DocumentRepository();
        $existing = $repository->findById((int) $documentId);
        if ($existing === null) {
            return Response::html(View::render('pages/404', [], 'layouts/app'), 404);
        }
        if ($existing['status'] !== 'draft') {
            return Response::html(__('common.forbidden'), 403);
        }
        $service = new DocumentService();
        $validated = $service->validateDraft($request->all(), true);
        if ($validated['errors'] !== []) {
            Session::flash('documents.errors', $validated['errors']);
            Session::flash('documents.values', $validated['values']);
            return Response::redirect(url('/documents/' . $documentId . '/edit'));
        }
        try {
            if (!$service->updateDraft((int) $documentId, $validated['values'])) {
                Session::flash('documents.errors', ['general' => __('documents.not_draft')]);
                return Response::redirect(url('/documents/' . $documentId));
            }
        } catch (Throwable $error) {
            error_log((string) $error);
            Session::flash('documents.errors', ['general' => __('documents.save_failed')]);
            Session::flash('documents.values', $validated['values']);
            return Response::redirect(url('/documents/' . $documentId . '/edit'));
        }
        (new AuditLogger())->record('documents.updated', 'document', (int) $documentId, [
            'document_type' => (string) ($validated['values']['document_type'] ?? ''),
        ], Auth::id());
        Session::flash('documents.notice', __('documents.updated'));
        return Response::redirect(url('/documents/' . $documentId));
    }

    public function show(Request $request, string $id): Response
    {
        if (!self::canViewDocuments()) {
            return Response::html(__('common.forbidden'), 403);
        }
        $documentId = filter_var($id, FILTER_VALIDATE_INT);
        $detail = $documentId !== false && $documentId > 0
            ? (new DocumentService())->detail((int) $documentId)
            : null;
        if ($detail === null) {
            return Response::html(View::render('pages/404', [], 'layouts/app'), 404);
        }
        $context = (new ViewContext())->authenticated((string) $detail['document']['title'], 'documents');
        return Response::html(View::render('pages/documents/show', $context + [
            'document' => $detail['document'],
            'lines' => $detail['lines'],
            'snapshot' => $detail['snapshot'],
            'payments' => $detail['payments'],
            'financialPosition' => $detail['financialPosition'],
            'errors' => Session::pullFlash('documents.errors', []),
            'paymentErrors' => Session::pullFlash('documents.payment_errors', []),
            'paymentValues' => Session::pullFlash('documents.payment_values', []),
            'notice' => Session::pullFlash('documents.notice'),
            'canManage' => Auth::can('documents.manage'),
            'canIssue' => Auth::can('documents.issue'),
            'canManagePayments' => Auth::can('documents.payments.manage'),
            'canCreate' => Auth::can('documents.create') || Auth::can('documents.manage'),
        ]));
    }

    public function issue(Request $request, string $id): Response
    {
        if (!Auth::can('documents.issue')) {
            return Response::html(__('common.forbidden'), 403);
        }
        $documentId = filter_var($id, FILTER_VALIDATE_INT);
        if ($documentId === false || $documentId < 1) {
            return Response::redirect(url('/documents'));
        }
        try {
            (new DocumentService())->issue((int) $documentId, (int) Auth::id());
        } catch (RuntimeException $error) {
            Session::flash('documents.errors', ['general' => $error->getMessage()]);
            return Response::redirect(url('/documents/' . $documentId));
        } catch (Throwable $error) {
            error_log((string) $error);
            Session::flash('documents.errors', ['general' => __('documents.issue_failed')]);
            return Response::redirect(url('/documents/' . $documentId));
        }
        (new AuditLogger())->record('documents.issued', 'document', (int) $documentId, [], Auth::id());
        Session::flash('documents.notice', __('documents.issued'));
        return Response::redirect(url('/documents/' . $documentId));
    }

    public function pdf(Request $request, string $id): Response
    {
        if (!self::canViewDocuments()) {
            return Response::html(__('common.forbidden'), 403);
        }
        $documentId = filter_var($id, FILTER_VALIDATE_INT);
        if ($documentId === false || $documentId < 1) {
            return Response::html(View::render('pages/404', [], 'layouts/app'), 404);
        }
        try {
            $service = new DocumentService();
            $document = (new DocumentRepository())->findById((int) $documentId);
            if ($document === null) {
                return Response::html(View::render('pages/404', [], 'layouts/app'), 404);
            }
            $regenerate = self::queryString($request, 'regenerate') === '1' && Auth::can('documents.issue');
            $contents = $service->pdfContents((int) $documentId, $regenerate);
            $base = (string) ($document['document_number'] ?? ('brouillon-' . $documentId));
            $filename = preg_replace('/[^a-zA-Z0-9_-]+/', '-', $base) ?: 'document-' . $documentId;
            return Response::download(
                $contents,
                $filename . '.pdf',
                'application/pdf',
                $document['status'] === 'draft' ? 'inline' : 'attachment'
            );
        } catch (Throwable $error) {
            error_log((string) $error);
            Session::flash('documents.errors', ['general' => __('documents.pdf_failed')]);
            return Response::redirect(url('/documents/' . $documentId));
        }
    }

    public function addPayment(Request $request, string $id): Response
    {
        if (!Auth::can('documents.payments.manage')) {
            return Response::html(__('common.forbidden'), 403);
        }
        $documentId = filter_var($id, FILTER_VALIDATE_INT);
        if ($documentId === false || $documentId < 1) {
            return Response::redirect(url('/documents'));
        }
        $service = new DocumentService();
        $validated = $service->validatePayment($request->all());
        if ($validated['errors'] !== []) {
            Session::flash('documents.payment_errors', $validated['errors']);
            Session::flash('documents.payment_values', $validated['values']);
            return Response::redirect(url('/documents/' . $documentId));
        }
        try {
            $errors = $service->addPayment((int) $documentId, $validated['values'], (int) Auth::id());
            if ($errors !== []) {
                Session::flash('documents.payment_errors', $errors);
                Session::flash('documents.payment_values', $validated['values']);
                return Response::redirect(url('/documents/' . $documentId));
            }
        } catch (Throwable $error) {
            error_log((string) $error);
            Session::flash('documents.payment_errors', ['general' => __('documents.payment_failed')]);
            Session::flash('documents.payment_values', $validated['values']);
            return Response::redirect(url('/documents/' . $documentId));
        }
        (new AuditLogger())->record('documents.payment.added', 'document', (int) $documentId, [], Auth::id());
        Session::flash('documents.notice', __('documents.payment_added'));
        return Response::redirect(url('/documents/' . $documentId));
    }

    public function deleteDraft(Request $request, string $id): Response
    {
        if (!Auth::can('documents.manage')) {
            return Response::html(__('common.forbidden'), 403);
        }
        $documentId = filter_var($id, FILTER_VALIDATE_INT);
        if ($documentId === false || $documentId < 1) {
            return Response::redirect(url('/documents'));
        }
        if (!(new DocumentRepository())->deleteDraft((int) $documentId)) {
            Session::flash('documents.errors', ['general' => __('documents.not_draft')]);
            return Response::redirect(url('/documents/' . $documentId));
        }
        (new AuditLogger())->record('documents.draft.deleted', 'document', (int) $documentId, [], Auth::id());
        Session::flash('documents.notice', __('documents.deleted'));
        return Response::redirect(url('/documents'));
    }

    private function formResponse(array $values, ?array $document, array $errors, string $title, bool $allowInactiveTemplate): Response
    {
        $type = in_array($values['document_type'] ?? '', ['quote', 'contract', 'invoice', 'credit_note'], true)
            ? (string) $values['document_type']
            : 'quote';
        $locale = in_array($values['locale'] ?? '', ['fr', 'ar'], true) ? (string) $values['locale'] : 'fr';
        $templates = (new DocumentTemplateRepository())->all(true);
        if ($allowInactiveTemplate && isset($values['template_id'])) {
            $existing = (new DocumentTemplateRepository())->findById((int) $values['template_id']);
            if ($existing !== null && $existing['document_type'] === $type && $existing['locale'] === $locale
                && !array_filter($templates, static fn (array $item): bool => $item['id'] === $existing['id'])
            ) {
                $templates[] = $existing;
            }
        }
        $context = (new ViewContext())->authenticated($title, 'documents');
        return Response::html(View::render('pages/documents/form', $context + [
            'title' => $title,
            'values' => $values,
            'document' => $document,
            'errors' => is_array($errors) ? $errors : [],
            'templates' => $templates,
            'vatRates' => array_map('trim', explode(',', (string) (new \App\Services\CabinetSettings())->get()['vat_rates'])),
            'options' => (new DocumentService())->formOptions(),
            'allowInactiveTemplate' => $allowInactiveTemplate,
        ]));
    }

    /** @param array<string,mixed> $detail
     *  @return array<string,mixed>
     */
    private function valuesFromDetail(array $detail): array
    {
        $document = $detail['document'];
        $values = [];
        foreach ([
            'document_type', 'locale', 'template_id', 'title', 'client_id', 'project_id',
            'project_phase_assignment_id', 'reference_document_id', 'billing_kind', 'tax_rate',
            'stamp_amount', 'withholding_rate', 'validity_days', 'payment_terms_days',
            'terms_text', 'legal_notice', 'notes',
        ] as $key) {
            $values[$key] = (string) ($document[$key] ?? '');
        }
        $values['lines'] = array_map(static fn (array $line): array => [
            'description' => (string) $line['description'],
            'quantity' => (string) $line['quantity'],
            'unit' => (string) $line['unit'],
            'unit_price' => (string) $line['unit_price'],
        ], $detail['lines']);
        return $values;
    }

    private static function canViewDocuments(): bool
    {
        return Auth::can('documents.view') || Auth::can('documents.manage')
            || Auth::can('documents.issue') || Auth::can('documents.create')
            || Auth::can('documents.payments.manage') || Auth::can('documents.templates.manage')
            || Auth::can('settings.manage');
    }

    private static function queryString(Request $request, string $key): string
    {
        $value = $request->query($key, '');
        return is_string($value) ? trim($value) : '';
    }

    private static function positiveId(mixed $value): string
    {
        if (!is_string($value) && !is_int($value)) {
            return '';
        }
        $text = (string) $value;
        return ctype_digit($text) && (int) $text > 0 ? (string) (int) $text : '';
    }

    private static function page(mixed $value): int
    {
        $page = filter_var($value, FILTER_VALIDATE_INT);
        return $page === false || $page < 1 ? 1 : min($page, 100000);
    }
}
