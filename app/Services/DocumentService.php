<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Models\DocumentRepository;
use App\Models\DocumentTemplateRepository;
use DateTimeImmutable;
use PDO;
use RuntimeException;
use Throwable;

/** Validation, totaux décimaux, émission immuable et règlements des documents. */
final class DocumentService
{
    private const TYPES = ['quote', 'contract', 'invoice', 'credit_note'];
    private const LOCALES = ['fr', 'ar'];
    private const BILLING_KINDS = ['standard', 'deposit', 'phase'];
    private const PAYMENT_METHODS = ['cash', 'bank_transfer', 'cheque', 'card', 'other'];

    private PDO $pdo;
    private DocumentRepository $documents;
    private DocumentTemplateRepository $templates;
    private CabinetSettings $settings;
    private DocumentStorage $storage;
    private DocumentPdfService $pdf;

    public function __construct(
        ?PDO $pdo = null,
        ?DocumentRepository $documents = null,
        ?DocumentTemplateRepository $templates = null,
        ?CabinetSettings $settings = null,
        ?DocumentStorage $storage = null,
        ?DocumentPdfService $pdf = null
    ) {
        $this->pdo = $pdo ?? Database::connection();
        $this->documents = $documents ?? new DocumentRepository($this->pdo);
        $this->templates = $templates ?? new DocumentTemplateRepository($this->pdo);
        $this->settings = $settings ?? new CabinetSettings();
        $this->storage = $storage ?? new DocumentStorage();
        $this->pdf = $pdf ?? new DocumentPdfService($this->storage);
    }

    /** @return array<string,mixed> */
    public function draftDefaults(string $type, string $locale, ?int $referenceId = null): array
    {
        if (!in_array($type, self::TYPES, true)) {
            $type = 'quote';
        }
        if (!in_array($locale, self::LOCALES, true)) {
            $locale = 'fr';
        }
        $template = $this->templates->findDefault($type, $locale);
        $settings = $this->settings->get();
        $reference = $referenceId !== null && $referenceId > 0
            ? $this->documents->findById($referenceId)
            : null;
        $validReference = is_array($reference)
            && $reference['status'] === 'issued'
            && (($type === 'credit_note' && $reference['document_type'] === 'invoice')
                || ($type === 'invoice' && in_array($reference['document_type'], ['quote', 'contract'], true)));
        if (!$validReference) {
            $reference = null;
        }

        $taxRate = (string) ($settings['vat_default'] ?? config('tunisia.vat.default', 19));
        $stampAmount = in_array($type, ['invoice', 'credit_note'], true)
            ? (string) ($settings['stamp_duty'] ?? '0.000')
            : '0.000';
        $withholdingRate = in_array($type, ['invoice', 'credit_note'], true)
            ? (string) ($settings['withholding_rate'] ?? '0.000')
            : '0.000';
        if ($type === 'credit_note' && $reference !== null) {
            $taxRate = (string) $reference['tax_rate'];
            $stampAmount = (string) $reference['stamp_amount'];
            $withholdingRate = (string) $reference['withholding_rate'];
        }
        $lines = [];
        if (in_array($type, ['invoice', 'credit_note'], true) && $reference !== null) {
            $lines = array_map(static fn (array $line): array => [
                'description' => (string) $line['description'],
                'quantity' => (string) $line['quantity'],
                'unit' => (string) $line['unit'],
                'unit_price' => (string) $line['unit_price'],
            ], $this->documents->lines((int) $reference['id']));
        }

        return [
            'document_type' => $type,
            'locale' => $locale,
            'template_id' => $template === null ? '' : (string) $template['id'],
            'title' => (string) ($template['title'] ?? ''),
            'client_id' => $reference === null ? '' : (string) $reference['client_id'],
            'project_id' => $reference === null || $reference['project_id'] === null ? '' : (string) $reference['project_id'],
            'project_phase_assignment_id' => '',
            'reference_document_id' => $reference === null ? '' : (string) $reference['id'],
            'billing_kind' => $type === 'invoice' ? 'standard' : '',
            'tax_rate' => $taxRate,
            'stamp_amount' => $stampAmount,
            'withholding_rate' => $withholdingRate,
            'validity_days' => (string) ($template['validity_days'] ?? 0),
            'payment_terms_days' => (string) ($template['payment_terms_days'] ?? 0),
            'terms_text' => (string) ($template['terms_text'] ?? ''),
            'legal_notice' => (string) ($template['legal_notice'] ?? ''),
            'notes' => '',
            'lines' => $lines,
        ];
    }

    /** @param array<string,mixed> $input
     *  @return array{values:array<string,mixed>,errors:array<string,string>}
     */
    public function validateDraft(array $input, bool $allowInactiveTemplate = false): array
    {
        $type = self::string($input['document_type'] ?? '');
        $locale = self::string($input['locale'] ?? 'fr');
        $values = [
            'document_type' => $type,
            'locale' => $locale,
            'template_id' => self::positiveId($input['template_id'] ?? ''),
            'title' => trim(self::string($input['title'] ?? '')),
            'client_id' => self::positiveId($input['client_id'] ?? ''),
            'project_id' => self::positiveId($input['project_id'] ?? ''),
            'project_phase_assignment_id' => self::positiveId($input['project_phase_assignment_id'] ?? ''),
            'reference_document_id' => self::positiveId($input['reference_document_id'] ?? ''),
            'billing_kind' => self::string($input['billing_kind'] ?? ''),
            'tax_rate' => self::decimal(self::string($input['tax_rate'] ?? ''), 2, 3),
            'stamp_amount' => self::decimal(self::string($input['stamp_amount'] ?? ''), 3, 9),
            'withholding_rate' => self::decimal(self::string($input['withholding_rate'] ?? ''), 3, 3),
            'validity_days' => self::integer($input['validity_days'] ?? '0', 3650),
            'payment_terms_days' => self::integer($input['payment_terms_days'] ?? '0', 3650),
            'terms_text' => trim(self::string($input['terms_text'] ?? '')),
            'legal_notice' => trim(self::string($input['legal_notice'] ?? '')),
            'notes' => trim(self::string($input['notes'] ?? '')),
            'lines' => [],
        ];
        $errors = [];

        if (!in_array($type, self::TYPES, true)) {
            $errors['document_type'] = __('documents.invalid_type');
        }
        if (!in_array($locale, self::LOCALES, true)) {
            $errors['locale'] = __('documents.invalid_language');
        }
        if ($values['title'] === '' || mb_strlen($values['title'], 'UTF-8') > 180) {
            $errors['title'] = __('documents.invalid_title');
        }
        if ($values['client_id'] < 1 || !$this->documents->clientExists($values['client_id'])) {
            $errors['client_id'] = __('documents.invalid_client');
        }
        if ($values['project_id'] > 0
            && !$this->documents->projectBelongsToClient($values['project_id'], $values['client_id'])
        ) {
            $errors['project_id'] = __('documents.invalid_project');
        }
        if ($values['project_phase_assignment_id'] > 0
            && ($values['project_id'] < 1
                || !$this->documents->phaseBelongsToProject($values['project_phase_assignment_id'], $values['project_id']))
        ) {
            $errors['project_phase_assignment_id'] = __('documents.invalid_phase');
        }

        $template = $values['template_id'] > 0 ? $this->templates->findById($values['template_id']) : null;
        if ($template === null || $template['document_type'] !== $type || $template['locale'] !== $locale
            || (!$allowInactiveTemplate && empty($template['is_active']))
        ) {
            $errors['template_id'] = __('documents.invalid_template');
        }
        $candidateReference = $values['reference_document_id'] > 0
            ? $this->documents->findById($values['reference_document_id'])
            : null;
        $creditSourceIsValid = $type === 'credit_note' && $candidateReference !== null
            && $candidateReference['document_type'] === 'invoice' && $candidateReference['status'] === 'issued';
        if ($creditSourceIsValid) {
            $values['tax_rate'] = (string) $candidateReference['tax_rate'];
            $values['stamp_amount'] = (string) $candidateReference['stamp_amount'];
            $values['withholding_rate'] = (string) $candidateReference['withholding_rate'];
        }
        if ($values['tax_rate'] === null || self::compareDecimal($values['tax_rate'], '100.00', 2) > 0
            || ($type !== 'credit_note' && !$this->configuredTaxRate($values['tax_rate']))
        ) {
            $errors['tax_rate'] = __('documents.invalid_tax');
        }
        if ($values['stamp_amount'] === null) {
            $errors['stamp_amount'] = __('documents.invalid_tax');
        }
        if ($values['withholding_rate'] === null
            || self::compareDecimal($values['withholding_rate'], '100.000', 3) > 0
        ) {
            $errors['withholding_rate'] = __('documents.invalid_tax');
        }
        foreach (['validity_days', 'payment_terms_days'] as $field) {
            if ($values[$field] === null) {
                $errors[$field] = __('documents.invalid_days');
                $values[$field] = 0;
            }
        }
        if (mb_strlen($values['terms_text'], 'UTF-8') > 4000
            || mb_strlen($values['legal_notice'], 'UTF-8') > 2000
            || mb_strlen($values['notes'], 'UTF-8') > 4000
        ) {
            $errors['terms_text'] = __('documents.invalid_text');
        }

        if ($type === 'invoice') {
            if (!in_array($values['billing_kind'], self::BILLING_KINDS, true)) {
                $errors['billing_kind'] = __('documents.invalid_billing_kind');
            }
            if ($values['billing_kind'] === 'phase' && $values['project_phase_assignment_id'] < 1) {
                $errors['project_phase_assignment_id'] = __('documents.invalid_phase');
            }
            if ($values['billing_kind'] !== 'phase' && $values['project_phase_assignment_id'] > 0) {
                $errors['billing_kind'] = __('documents.invalid_billing_kind');
            }
        } else {
            $values['billing_kind'] = '';
            if ($values['project_phase_assignment_id'] > 0 && $type !== 'credit_note') {
                $errors['project_phase_assignment_id'] = __('documents.invalid_phase');
            }
        }

        $reference = $values['reference_document_id'] > 0
            ? $this->documents->findById($values['reference_document_id'])
            : null;
        if ($type === 'credit_note') {
            if ($reference === null || $reference['document_type'] !== 'invoice' || $reference['status'] !== 'issued') {
                $errors['reference_document_id'] = __('documents.invalid_reference');
            }
        } elseif ($type === 'invoice' && $values['reference_document_id'] > 0) {
            if ($reference === null || !in_array($reference['document_type'], ['quote', 'contract'], true)
                || $reference['status'] !== 'issued'
            ) {
                $errors['reference_document_id'] = __('documents.invalid_reference');
            }
        } elseif ($type !== 'invoice') {
            $values['reference_document_id'] = 0;
        }
        if ($reference !== null) {
            if ($reference['client_id'] !== $values['client_id']) {
                $errors['reference_document_id'] = __('documents.invalid_reference');
            }
            if ($values['project_id'] < 1 && $reference['project_id'] !== null) {
                $values['project_id'] = (int) $reference['project_id'];
            } elseif ($reference['project_id'] !== null && $values['project_id'] !== (int) $reference['project_id']) {
                $errors['reference_document_id'] = __('documents.invalid_reference');
            }
            if ($type === 'credit_note' && $reference['project_phase_assignment_id'] !== null) {
                $values['project_phase_assignment_id'] = (int) $reference['project_phase_assignment_id'];
            }
        }

        $lineInput = is_array($input['lines'] ?? null) ? array_values($input['lines']) : [];
        $formLines = [];
        if (count($lineInput) > 60) {
            $errors['lines'] = __('documents.too_many_lines');
        }
        foreach (array_slice($lineInput, 0, 60) as $line) {
            if (!is_array($line)) {
                $errors['lines'] = __('documents.invalid_line');
                $formLines[] = ['description' => '', 'quantity' => '', 'unit' => '', 'unit_price' => ''];
                continue;
            }
            $description = trim(self::string($line['description'] ?? ''));
            $quantityRaw = self::string($line['quantity'] ?? '');
            $unit = trim(self::string($line['unit'] ?? ''));
            $priceRaw = self::string($line['unit_price'] ?? '');
            $formLines[] = [
                'description' => $description,
                'quantity' => $quantityRaw,
                'unit' => $unit,
                'unit_price' => $priceRaw,
            ];
            if ($description === '' && $quantityRaw === '' && $unit === '' && $priceRaw === '') {
                continue;
            }
            $quantity = self::decimal($quantityRaw, 3, 6);
            $unitPrice = self::decimal($priceRaw, 3, 9);
            if ($description === '' || mb_strlen($description, 'UTF-8') > 255) {
                $errors['lines'] = __('documents.invalid_line');
            }
            if ($quantity === null || self::compareDecimal($quantity, '0.000', 3) <= 0) {
                $errors['lines'] = __('documents.invalid_line');
            }
            if ($unitPrice === null) {
                $errors['lines'] = __('documents.invalid_line');
            }
            if (mb_strlen($unit, 'UTF-8') > 40) {
                $errors['lines'] = __('documents.invalid_line');
            }
            if ($quantity !== null && $unitPrice !== null && $description !== ''
                && mb_strlen($description, 'UTF-8') <= 255 && mb_strlen($unit, 'UTF-8') <= 40
                && self::compareDecimal($quantity, '0.000', 3) > 0
            ) {
                $values['lines'][] = [
                    'description' => $description,
                    'quantity' => $quantity,
                    'unit' => $unit,
                    'unit_price' => $unitPrice,
                ];
            }
        }
        if ($values['lines'] === []) {
            $errors['lines'] = __('documents.required_line');
        }
        if (isset($errors['lines'])) {
            $values['lines'] = $formLines;
        }
        if ($values['template_id'] < 1) {
            $errors['template_id'] = __('documents.invalid_template');
        }
        return ['values' => $values, 'errors' => $errors];
    }

    /** @param array<string,mixed> $values
     *  @param array<int,array<string,string>> $lines
     */
    public function createDraft(array $values, int $createdBy): int
    {
        return $this->documents->createDraft($values, $values['lines'], $createdBy);
    }

    /** @param array<string,mixed> $values
     *  @param array<int,array<string,string>> $lines
     */
    public function updateDraft(int $id, array $values): bool
    {
        return $this->documents->updateDraft($id, $values, $values['lines']);
    }

    /** @return array<string,mixed>|null */
    public function detail(int $id): ?array
    {
        $document = $this->documents->findById($id);
        if ($document === null) {
            return null;
        }
        $lines = $this->documents->lines($id);
        if ($document['status'] === 'issued') {
            $snapshot = $this->decodeSnapshot($document['issued_snapshot'] ?? null);
            $lines = array_values($snapshot['document']['lines']);
        } else {
            $snapshot = $this->buildSnapshot($document, $lines, null, null, null, null, false);
        }
        return [
            'document' => $document,
            'lines' => $lines,
            'snapshot' => $snapshot,
            'payments' => $document['document_type'] === 'invoice' && $document['status'] === 'issued'
                ? $this->documents->payments($id)
                : [],
            'financialPosition' => $document['document_type'] === 'invoice' && $document['status'] === 'issued'
                ? $this->documents->financialPosition($id)
                : ['balance_due' => '0.000', 'paid_amount' => '0.000', 'credited_amount' => '0.000'],
        ];
    }

    /** Émet un brouillon, réserve son numéro et conserve l'instantané et le PDF dans la même opération. */
    public function issue(int $id, int $issuedBy): void
    {
        $logoReference = null;
        $pdfStored = false;
        $this->pdo->beginTransaction();
        try {
            $locked = $this->documents->lockForIssue($id);
            if ($locked === null || $locked['status'] !== 'draft') {
                throw new RuntimeException(__('documents.issue_not_draft'));
            }
            $this->documents->recalculateForIssue($id);
            $document = $this->documents->findById($id);
            if ($document === null) {
                throw new RuntimeException(__('documents.not_found'));
            }
            $lines = $this->documents->lines($id);
            if ($lines === []) {
                throw new RuntimeException(__('documents.required_line'));
            }
            if ($document['document_type'] === 'credit_note') {
                $this->validateCreditLimit($document);
            }

            $issueDate = (new DateTimeImmutable('today'))->format('Y-m-d');
            $year = (int) substr($issueDate, 0, 4);
            $number = $this->documents->reserveNumber((string) $document['document_type'], $year);
            [$dueDate, $validUntil] = self::issueDates($document, $issueDate);
            $snapshot = $this->buildSnapshot($document, $lines, $number, $issueDate, $dueDate, $validUntil, true);
            $logoReference = $snapshot['cabinet']['logo_reference'] ?? null;
            $json = json_encode($snapshot, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            $pdfContents = $this->pdf->render($snapshot);
            $pdfPath = $this->storage->storeIssuedPdf($id, $pdfContents);
            $pdfStored = true;
            if (!$this->documents->markIssued(
                $id,
                $number,
                $issueDate,
                $dueDate,
                $validUntil,
                $issuedBy,
                $json,
                $pdfPath
            )) {
                throw new RuntimeException(__('documents.issue_failed'));
            }
            $this->pdo->commit();
        } catch (Throwable $error) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            if ($pdfStored) {
                $this->storage->removeIssuedPdf($id);
            }
            $this->storage->removeLogoReference($logoReference);
            throw $error;
        }
    }

    /** Retourne un PDF émis depuis son JSON figé ou un aperçu actualisé de brouillon. */
    public function pdfContents(int $id, bool $regenerate = false): string
    {
        $document = $this->documents->findById($id);
        if ($document === null) {
            throw new RuntimeException(__('documents.not_found'));
        }
        if ($document['status'] === 'issued') {
            $snapshot = $this->decodeSnapshot($document['issued_snapshot'] ?? null);
            if (!$regenerate) {
                $saved = $this->storage->readIssuedPdf($id);
                if ($saved !== null) {
                    return $saved;
                }
            }
            $contents = $this->pdf->render($snapshot);
            $this->storage->storeIssuedPdf($id, $contents);
            return $contents;
        }
        $lines = $this->documents->lines($id);
        $snapshot = $this->buildSnapshot($document, $lines, null, null, null, null, false);
        return $this->pdf->render($snapshot);
    }

    /** @param array<string,mixed> $input
     *  @return array{values:array<string,mixed>,errors:array<string,string>}
     */
    public function validatePayment(array $input): array
    {
        $values = [
            'paid_on' => trim(self::string($input['paid_on'] ?? '')),
            'amount' => self::decimal(self::string($input['amount'] ?? ''), 3, 15),
            'payment_method' => self::string($input['payment_method'] ?? ''),
            'reference' => trim(self::string($input['reference'] ?? '')),
            'note' => trim(self::string($input['note'] ?? '')),
        ];
        $errors = [];
        $date = self::validDate($values['paid_on']);
        if ($date === null || $date > new DateTimeImmutable('today')) {
            $errors['paid_on'] = __('documents.invalid_payment_date');
        }
        if ($values['amount'] === null || self::compareDecimal($values['amount'], '0.000', 3) <= 0) {
            $errors['amount'] = __('documents.invalid_payment_amount');
            $values['amount'] = '0.000';
        }
        if (!in_array($values['payment_method'], self::PAYMENT_METHODS, true)) {
            $errors['payment_method'] = __('documents.invalid_payment_method');
        }
        if (mb_strlen($values['reference'], 'UTF-8') > 120 || mb_strlen($values['note'], 'UTF-8') > 255) {
            $errors['note'] = __('documents.invalid_payment_note');
        }
        return ['values' => $values, 'errors' => $errors];
    }

    /** @param array<string,mixed> $values
     *  @return array<string,string>
     */
    public function addPayment(int $documentId, array $values, int $createdBy): array
    {
        $this->pdo->beginTransaction();
        try {
            $locked = $this->documents->lockForIssue($documentId);
            if ($locked === null || $locked['document_type'] !== 'invoice' || $locked['status'] !== 'issued') {
                $this->pdo->rollBack();
                return ['document' => __('documents.payment_invoice_required')];
            }
            $position = $this->documents->financialPosition($documentId);
            if (self::compareDecimal($position['balance_due'], '0.000', 3) <= 0
                || self::compareDecimal($values['amount'], $position['balance_due'], 3) > 0
            ) {
                $this->pdo->rollBack();
                return ['amount' => __('documents.payment_exceeds_balance')];
            }
            $this->documents->addPayment($documentId, $values, $createdBy);
            $this->pdo->commit();
            return [];
        } catch (Throwable $error) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $error;
        }
    }

    /** Construit une réponse complète pour les formulaires d'édition. */
    public function formOptions(): array
    {
        return [
            'clients' => $this->documents->clientOptions(),
            'projects' => $this->documents->projectOptions(),
            'phases' => $this->documents->phaseOptions(),
            'references' => $this->documents->referenceOptions('all'),
        ];
    }

    /** @param array<string,mixed> $document */
    private function validateCreditLimit(array $document): void
    {
        $invoiceId = (int) ($document['reference_document_id'] ?? 0);
        $lockedInvoice = $invoiceId > 0 ? $this->documents->lockForIssue($invoiceId) : null;
        if ($lockedInvoice === null || $lockedInvoice['document_type'] !== 'invoice' || $lockedInvoice['status'] !== 'issued') {
            throw new RuntimeException(__('documents.invalid_reference'));
        }
        $limit = $this->documents->creditLimitRemaining($invoiceId);
        if ($limit === null || self::compareDecimal((string) $document['total_amount'], $limit, 3) > 0) {
            throw new RuntimeException(__('documents.credit_limit_exceeded'));
        }
    }

    /** @param array<string,mixed> $document
     *  @param array<int,array<string,mixed>> $lines
     *  @return array<string,mixed>
     */
    private function buildSnapshot(
        array $document,
        array $lines,
        ?string $number,
        ?string $issueDate,
        ?string $dueDate,
        ?string $validUntil,
        bool $freezeLogo
    ): array {
        $locale = in_array($document['locale'] ?? '', self::LOCALES, true) ? (string) $document['locale'] : 'fr';
        $cabinetSettings = $this->settings->get();
        $logoReference = (string) ($cabinetSettings['logo'] ?? '') !== ''
            ? 'cabinet/' . $cabinetSettings['logo']
            : null;
        if ($freezeLogo && $logoReference !== null) {
            $logoReference = $this->storage->copyCabinetLogo((int) $document['id'], (string) $cabinetSettings['logo']);
        }
        $date = $issueDate ?? (string) ($document['issue_date'] ?? (new DateTimeImmutable('today'))->format('Y-m-d'));
        if ($dueDate === null && $document['document_type'] === 'invoice') {
            $dueDate = self::dateAfterDays($date, (int) ($document['payment_terms_days'] ?? 0));
        }
        if ($validUntil === null && $document['document_type'] === 'quote') {
            $validUntil = self::dateAfterDays($date, (int) ($document['validity_days'] ?? 0));
        }

        $clientAddress = self::addressParts([
            $document['client_address_line'] ?? '',
            $document['client_city'] ?? '',
            $locale === 'ar' ? ($document['client_delegation_ar'] ?? '') : ($document['client_delegation_latin'] ?? ''),
            $locale === 'ar' ? ($document['client_governorate_ar'] ?? '') : ($document['client_governorate_fr'] ?? ''),
            $document['client_postal_code'] ?? '',
        ]);
        $projectAddress = self::addressParts([
            $document['project_address_line'] ?? '',
            $document['project_city'] ?? '',
            $locale === 'ar' ? ($document['project_delegation_ar'] ?? '') : ($document['project_delegation_latin'] ?? ''),
            $locale === 'ar' ? ($document['project_governorate_ar'] ?? '') : ($document['project_governorate_fr'] ?? ''),
            $document['project_postal_code'] ?? '',
        ]);
        $clientName = trim((string) ($document['client_legal_name'] ?? '')) !== ''
            && ($document['client_type'] ?? '') !== 'individual'
            ? (string) $document['client_legal_name']
            : (string) ($document['client_name'] ?? '');

        return [
            'snapshot_version' => 1,
            'locale' => $locale,
            'cabinet' => [
                'name' => (string) ($cabinetSettings['name'] ?? ''),
                'address' => (string) ($cabinetSettings['address'] ?? ''),
                'phone' => (string) ($cabinetSettings['phone'] ?? ''),
                'email' => (string) ($cabinetSettings['email'] ?? ''),
                'tax_id' => (string) ($cabinetSettings['tax_id'] ?? ''),
                'rib' => (string) ($cabinetSettings['rib'] ?? ''),
                'logo_reference' => $logoReference,
            ],
            'client' => [
                'name' => $clientName,
                'tax_id' => (string) ($document['client_tax_id'] ?? ''),
                'email' => (string) ($document['client_email'] ?? ''),
                'phone' => (string) ($document['client_phone'] ?? ''),
                'address' => $clientAddress,
            ],
            'project' => [
                'name' => (string) ($document['project_name'] ?? ''),
                'reference' => (string) ($document['project_reference'] ?? ''),
                'project_type' => (string) ($document['project_type'] ?? ''),
                'address' => $projectAddress,
            ],
            'document' => [
                'id' => (int) $document['id'],
                'document_type' => (string) $document['document_type'],
                'document_number' => $number ?? (string) ($document['document_number'] ?? __('documents.draft')),
                'title' => (string) $document['title'],
                'issue_date' => $date,
                'due_date' => $dueDate,
                'valid_until' => $validUntil,
                'reference_document_number' => (string) ($document['reference_document_number'] ?? ''),
                'phase_name' => (string) ($document['phase_name'] ?? ''),
                'billing_kind' => (string) ($document['billing_kind'] ?? ''),
                'tax_rate' => (string) ($document['tax_rate'] ?? '0.00'),
                'stamp_amount' => (string) ($document['stamp_amount'] ?? '0.000'),
                'withholding_rate' => (string) ($document['withholding_rate'] ?? '0.000'),
                'subtotal' => (string) ($document['subtotal'] ?? '0.000'),
                'tax_amount' => (string) ($document['tax_amount'] ?? '0.000'),
                'withholding_amount' => (string) ($document['withholding_amount'] ?? '0.000'),
                'total_amount' => (string) ($document['total_amount'] ?? '0.000'),
                'terms_text' => (string) ($document['terms_text'] ?? ''),
                'legal_notice' => (string) ($document['legal_notice'] ?? ''),
                'notes' => (string) ($document['notes'] ?? ''),
                'lines' => array_map(static fn (array $line): array => [
                    'description' => (string) $line['description'],
                    'quantity' => (string) $line['quantity'],
                    'unit' => (string) $line['unit'],
                    'unit_price' => (string) $line['unit_price'],
                    'line_total' => (string) $line['line_total'],
                ], $lines),
            ],
        ];
    }

    /** @return array<string,mixed> */
    private function decodeSnapshot(mixed $snapshot): array
    {
        if (!is_string($snapshot) || $snapshot === '') {
            throw new RuntimeException(__('documents.snapshot_missing'));
        }
        try {
            $decoded = json_decode($snapshot, true, 512, JSON_THROW_ON_ERROR);
        } catch (Throwable $error) {
            throw new RuntimeException(__('documents.snapshot_invalid'), 0, $error);
        }
        if (!is_array($decoded) || (int) ($decoded['snapshot_version'] ?? 0) !== 1
            || !is_array($decoded['document'] ?? null) || !is_array($decoded['document']['lines'] ?? null)
            || !is_array($decoded['client'] ?? null)
        ) {
            throw new RuntimeException(__('documents.snapshot_invalid'));
        }
        return $decoded;
    }

    /** @return array{?string,?string} */
    private static function issueDates(array $document, string $issueDate): array
    {
        $dueDate = $document['document_type'] === 'invoice'
            ? self::dateAfterDays($issueDate, (int) ($document['payment_terms_days'] ?? 0))
            : null;
        $validUntil = $document['document_type'] === 'quote'
            ? self::dateAfterDays($issueDate, (int) ($document['validity_days'] ?? 0))
            : null;
        return [$dueDate, $validUntil];
    }

    private static function dateAfterDays(string $date, int $days): ?string
    {
        return $days > 0 ? (new DateTimeImmutable($date))->modify('+' . $days . ' days')->format('Y-m-d') : null;
    }

    /** @param array<int,mixed> $parts */
    private static function addressParts(array $parts): string
    {
        $clean = array_filter(array_map(static fn (mixed $part): string => trim(is_string($part) ? $part : ''), $parts));
        return implode(', ', $clean);
    }

    private function configuredTaxRate(string $rate): bool
    {
        $allowed = array_map('trim', explode(',', (string) ($this->settings->get()['vat_rates'] ?? '')));
        foreach ($allowed as $candidate) {
            $normalized = self::decimal($candidate, 2, 3);
            if ($normalized !== null && self::compareDecimal($normalized, $rate, 2) === 0) {
                return true;
            }
        }
        return false;
    }

    private static function validDate(string $value): ?DateTimeImmutable
    {
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        $errors = DateTimeImmutable::getLastErrors();
        if ($date === false || ($errors !== false && ($errors['warning_count'] > 0 || $errors['error_count'] > 0))
            || $date->format('Y-m-d') !== $value
        ) {
            return null;
        }
        return $date;
    }

    private static function decimal(string $value, int $scale, int $maxWholeDigits): ?string
    {
        $value = str_replace(',', '.', trim($value));
        if (!preg_match('/^\d{1,' . $maxWholeDigits . '}(?:\.\d{1,' . $scale . '})?$/', $value)) {
            return null;
        }
        [$whole, $fraction] = array_pad(explode('.', $value, 2), 2, '');
        $whole = ltrim($whole, '0');
        $whole = $whole === '' ? '0' : $whole;
        return $whole . '.' . str_pad($fraction, $scale, '0');
    }

    private static function compareDecimal(string $left, string $right, int $scale): int
    {
        [$leftWhole, $leftFraction] = array_pad(explode('.', $left, 2), 2, '');
        [$rightWhole, $rightFraction] = array_pad(explode('.', $right, 2), 2, '');
        $leftWhole = ltrim($leftWhole, '0');
        $rightWhole = ltrim($rightWhole, '0');
        $leftWhole = $leftWhole === '' ? '0' : $leftWhole;
        $rightWhole = $rightWhole === '' ? '0' : $rightWhole;
        if (strlen($leftWhole) !== strlen($rightWhole)) {
            return strlen($leftWhole) <=> strlen($rightWhole);
        }
        $wholeComparison = strcmp($leftWhole, $rightWhole);
        if ($wholeComparison !== 0) {
            return $wholeComparison <=> 0;
        }
        $leftFraction = str_pad(substr($leftFraction, 0, $scale), $scale, '0');
        $rightFraction = str_pad(substr($rightFraction, 0, $scale), $scale, '0');
        return strcmp($leftFraction, $rightFraction) <=> 0;
    }

    private static function positiveId(mixed $value): int
    {
        $text = self::string($value);
        return ctype_digit($text) && (int) $text > 0 ? (int) $text : 0;
    }

    private static function integer(mixed $value, int $maximum): ?int
    {
        $text = self::string($value);
        if (!ctype_digit($text) || (int) $text > $maximum) {
            return null;
        }
        return (int) $text;
    }

    private static function string(mixed $value): string
    {
        return is_string($value) || is_int($value) ? trim((string) $value) : '';
    }
}
