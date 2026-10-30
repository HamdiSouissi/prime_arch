<?php
declare(strict_types=1);

use App\Core\View;

$document = is_array($document ?? null) ? $document : [];
$snapshot = is_array($snapshot ?? null) ? $snapshot : [];
$lines = is_array($lines ?? null) ? $lines : [];
$payments = is_array($payments ?? null) ? $payments : [];
$financialPosition = is_array($financialPosition ?? null) ? $financialPosition : [];
$errors = is_array($errors ?? null) ? $errors : [];
$paymentErrors = is_array($paymentErrors ?? null) ? $paymentErrors : [];
$paymentValues = is_array($paymentValues ?? null) ? $paymentValues : [];
$issued = ($document['status'] ?? '') === 'issued';
$draft = ($document['status'] ?? '') === 'draft';
$number = trim((string) ($document['document_number'] ?? '')) !== ''
    ? (string) $document['document_number']
    : __('documents.draft_number', ['id' => (string) ($document['id'] ?? '')]);
$snapshotDocument = is_array($snapshot['document'] ?? null) ? $snapshot['document'] : [];
$snapshotClient = is_array($snapshot['client'] ?? null) ? $snapshot['client'] : [];
$snapshotProject = is_array($snapshot['project'] ?? null) ? $snapshot['project'] : [];
$snapshotCabinet = is_array($snapshot['cabinet'] ?? null) ? $snapshot['cabinet'] : [];
$statusTone = match ((string) ($document['status'] ?? 'draft')) {
    'issued' => 'success',
    'void' => 'danger',
    default => 'warning',
};
$isPositiveAmount = static fn (mixed $amount): bool => !str_starts_with(trim((string) $amount), '-')
    && preg_match('/[1-9]/', (string) $amount) === 1;
$balanceIsPositive = $isPositiveAmount($financialPosition['balance_due'] ?? '0.000');
$balanceLabel = str_starts_with(trim((string) ($financialPosition['balance_due'] ?? '0.000')), '-')
    ? __('documents.credit_balance')
    : __('documents.balance_due');
?>
<?php View::partial('partials/page-header', [
    'pageTitle' => (string) ($document['title'] ?? __('documents.title')),
    'eyebrow' => $number . ' · ' . __('documents.type.' . ($document['document_type'] ?? 'quote')),
    'subtitle' => __('documents.detail_intro'),
    'primaryAction' => $draft && !empty($canManage) ? ['label' => __('documents.edit'), 'icon' => 'edit', 'url' => url('/documents/' . (int) $document['id'] . '/edit')] : null,
    'secondaryAction' => ['label' => __('documents.back'), 'icon' => 'arrow-left', 'url' => url('/documents')],
]) ?>

<?php if (!empty($notice)): ?><p class="form-feedback form-feedback--success" role="status"><?= e($notice) ?></p><?php endif; ?>
<?php if (!empty($errors['general'])): ?><p class="form-feedback form-feedback--error" role="alert"><?= e($errors['general']) ?></p><?php endif; ?>

<section class="card stack-md" aria-labelledby="document-status-heading">
    <div class="card-header"><div><h2 class="card-header__title" id="document-status-heading"><?= e(__('documents.status_label')) ?></h2><p class="section-heading__note"><?= e(__('documents.status.' . ($document['status'] ?? 'draft'))) ?> · <?= e(__('documents.type.' . ($document['document_type'] ?? 'quote'))) ?></p></div><span class="badge badge--<?= e($statusTone) ?>"><?= e(__('documents.status.' . ($document['status'] ?? 'draft'))) ?></span></div>
    <div class="document-actions">
        <?php if ($issued && !empty($canCreate) && in_array($document['document_type'], ['quote', 'contract'], true)): ?><a class="button button--primary" href="<?= e(url('/documents/new?' . http_build_query(['type' => 'invoice', 'locale' => $document['locale'], 'reference_id' => (int) $document['id']]))) ?>"><?= e(__('documents.create_invoice_from')) ?></a><?php endif; ?>
        <?php if ($issued && !empty($canCreate) && $document['document_type'] === 'invoice'): ?><a class="button button--secondary" href="<?= e(url('/documents/new?' . http_build_query(['type' => 'credit_note', 'locale' => $document['locale'], 'reference_id' => (int) $document['id']]))) ?>"><?= e(__('documents.create_credit_note_from')) ?></a><?php endif; ?>
        <a class="button button--secondary" href="<?= e(url('/documents/' . (int) $document['id'] . '/pdf')) ?>"><svg class="icon icon--small" aria-hidden="true"><use href="<?= e(asset('img/icons.svg')) ?>#download"></use></svg><?= e($issued ? __('documents.download_pdf') : __('documents.preview_pdf')) ?></a>
        <?php if ($issued && !empty($canIssue)): ?><a class="button button--ghost" href="<?= e(url('/documents/' . (int) $document['id'] . '/pdf?regenerate=1')) ?>"><?= e(__('documents.regenerate_pdf')) ?></a><?php endif; ?>
        <?php if ($draft && !empty($canIssue)): ?>
            <form method="post" action="<?= e(url('/documents/' . (int) $document['id'] . '/issue')) ?>" data-confirm-message="<?= e(__('documents.confirm_issue')) ?>">
                <?= csrf_field() ?><button class="button button--primary" type="submit"><?= e(__('documents.issue')) ?></button>
            </form>
        <?php endif; ?>
        <?php if ($draft && !empty($canManage)): ?>
            <form method="post" action="<?= e(url('/documents/' . (int) $document['id'] . '/delete')) ?>" data-confirm-message="<?= e(__('documents.confirm_delete')) ?>">
                <?= csrf_field() ?><button class="button button--danger" type="submit"><?= e(__('documents.delete_draft')) ?></button>
            </form>
        <?php endif; ?>
    </div>
    <div class="document-summary-grid">
        <div><span class="detail-label"><?= e(__('documents.number')) ?></span><strong><?= e($number) ?></strong></div>
        <div><span class="detail-label"><?= e(__('documents.client')) ?></span><strong><?php if (!empty($canViewClients) && (int) ($document['client_id'] ?? 0) > 0): ?><a href="<?= e(url('/clients/' . (int) $document['client_id'])) ?>"><?= e($snapshotClient['name'] ?? $document['client_name'] ?? '—') ?></a><?php else: ?><?= e($snapshotClient['name'] ?? $document['client_name'] ?? '—') ?><?php endif; ?></strong><?php if (($snapshotClient['tax_id'] ?? '') !== ''): ?><span class="table-meta"><?= e(__('settings.tax_id')) ?> · <?= e($snapshotClient['tax_id']) ?></span><?php endif; ?></div>
        <div><span class="detail-label"><?= e(__('documents.project')) ?></span><strong><?php if (!empty($canViewProjects) && (int) ($document['project_id'] ?? 0) > 0): ?><a href="<?= e(url('/projects/' . (int) $document['project_id'])) ?>"><?= e($snapshotProject['name'] ?? $document['project_name'] ?? '—') ?></a><?php else: ?><?= e($snapshotProject['name'] ?? $document['project_name'] ?? '—') ?><?php endif; ?></strong><?php if (($snapshotProject['reference'] ?? '') !== ''): ?><span class="table-meta"><?= e($snapshotProject['reference']) ?></span><?php endif; ?></div>
        <div><span class="detail-label"><?= e(__('documents.issue_date')) ?></span><strong><?= e($snapshotDocument['issue_date'] ?? $document['issue_date'] ?? '—') ?></strong></div>
        <?php if (($snapshotDocument['due_date'] ?? $document['due_date'] ?? null) !== null): ?><div><span class="detail-label"><?= e(__('documents.due_date')) ?></span><strong><?= e($snapshotDocument['due_date'] ?? $document['due_date']) ?></strong></div><?php endif; ?>
        <?php if (($snapshotDocument['valid_until'] ?? $document['valid_until'] ?? null) !== null): ?><div><span class="detail-label"><?= e(__('documents.valid_until')) ?></span><strong><?= e($snapshotDocument['valid_until'] ?? $document['valid_until']) ?></strong></div><?php endif; ?>
        <?php if (($snapshotDocument['reference_document_number'] ?? $document['reference_document_number'] ?? '') !== ''): ?><div><span class="detail-label"><?= e(__('documents.reference_document')) ?></span><strong><?= e($snapshotDocument['reference_document_number'] ?? $document['reference_document_number']) ?></strong></div><?php endif; ?>
        <?php if (($snapshotDocument['billing_kind'] ?? '') !== ''): ?><div><span class="detail-label"><?= e(__('documents.billing_kind_label')) ?></span><strong><?= e(__('documents.billing_kind.' . $snapshotDocument['billing_kind'])) ?></strong></div><?php endif; ?>
        <?php if (($snapshotDocument['phase_name'] ?? '') !== ''): ?><div><span class="detail-label"><?= e(__('documents.phase')) ?></span><strong><?= e($snapshotDocument['phase_name']) ?></strong></div><?php endif; ?>
    </div>
    <?php if ($issued): ?><p class="form-feedback form-feedback--info"><?= e(__('documents.snapshot_notice')) ?></p><?php endif; ?>
</section>

<section class="card stack-md" aria-labelledby="document-lines-heading">
    <div class="card-header"><div><h2 class="card-header__title" id="document-lines-heading"><?= e(__('documents.lines')) ?></h2><p class="section-heading__note"><?= e(__('documents.lines_help')) ?></p></div></div>
    <div class="table-wrap"><table class="data-table"><thead><tr><th scope="col"><?= e(__('documents.line_description')) ?></th><th scope="col"><?= e(__('documents.line_quantity')) ?></th><th scope="col"><?= e(__('documents.line_unit')) ?></th><th scope="col"><?= e(__('documents.line_unit_price')) ?></th><th scope="col"><?= e(__('documents.line_total')) ?></th></tr></thead><tbody>
        <?php foreach ($lines as $line): ?>
            <tr><td><?= e($line['description']) ?></td><td class="u-numeric"><?= e($line['quantity']) ?></td><td><?= e($line['unit'] !== '' ? $line['unit'] : '—') ?></td><td class="u-numeric"><?= e(money_format_tnd((string) $line['unit_price'])) ?></td><td class="u-numeric"><?= e(money_format_tnd((string) $line['line_total'])) ?></td></tr>
        <?php endforeach; ?>
    </tbody></table></div>
    <dl class="document-totals">
        <div><dt><?= e(__('documents.subtotal')) ?></dt><dd><?= e(money_format_tnd((string) ($snapshotDocument['subtotal'] ?? $document['subtotal'] ?? '0.000'))) ?></dd></div>
        <div><dt><?= e(__('documents.tax_rate')) ?> · <?= e((string) ($snapshotDocument['tax_rate'] ?? $document['tax_rate'] ?? '0.00')) ?> %</dt><dd><?= e(money_format_tnd((string) ($snapshotDocument['tax_amount'] ?? $document['tax_amount'] ?? '0.000'))) ?></dd></div>
        <?php if ($isPositiveAmount($snapshotDocument['stamp_amount'] ?? $document['stamp_amount'] ?? '0.000')): ?><div><dt><?= e(__('documents.stamp_duty')) ?></dt><dd><?= e(money_format_tnd((string) ($snapshotDocument['stamp_amount'] ?? $document['stamp_amount']))) ?></dd></div><?php endif; ?>
        <?php if ($isPositiveAmount($snapshotDocument['withholding_rate'] ?? $document['withholding_rate'] ?? '0.000')): ?><div><dt><?= e(__('documents.withholding')) ?> · <?= e((string) ($snapshotDocument['withholding_rate'] ?? $document['withholding_rate'])) ?> %</dt><dd>−<?= e(money_format_tnd((string) ($snapshotDocument['withholding_amount'] ?? $document['withholding_amount'] ?? '0.000'))) ?></dd></div><?php endif; ?>
        <div class="document-totals__grand"><dt><?= e(__('documents.total')) ?></dt><dd><?= e(money_format_tnd((string) ($snapshotDocument['total_amount'] ?? $document['total_amount'] ?? '0.000'))) ?></dd></div>
    </dl>
    <?php if (($snapshotDocument['notes'] ?? $document['notes'] ?? '') !== ''): ?><div class="document-text-block"><h3><?= e(__('documents.notes')) ?></h3><p><?= nl2br(e($snapshotDocument['notes'] ?? $document['notes'])) ?></p></div><?php endif; ?>
    <?php if (($snapshotDocument['terms_text'] ?? $document['terms_text'] ?? '') !== ''): ?><div class="document-text-block"><h3><?= e(__('documents.terms_text')) ?></h3><p><?= nl2br(e($snapshotDocument['terms_text'] ?? $document['terms_text'])) ?></p></div><?php endif; ?>
    <?php if (($snapshotDocument['legal_notice'] ?? $document['legal_notice'] ?? '') !== ''): ?><div class="document-text-block"><h3><?= e(__('documents.legal_notice')) ?></h3><p><?= nl2br(e($snapshotDocument['legal_notice'] ?? $document['legal_notice'])) ?></p></div><?php endif; ?>
    <?php if (($snapshotCabinet['name'] ?? '') !== '' && $issued): ?><p class="table-meta"><?= e(__('documents.issued_by_cabinet')) ?> · <?= e($snapshotCabinet['name']) ?></p><?php endif; ?>
</section>

<?php if ($issued && ($document['document_type'] ?? '') === 'invoice'): ?>
    <section class="card stack-md" aria-labelledby="payments-heading">
        <div class="card-header"><div><h2 class="card-header__title" id="payments-heading"><?= e(__('documents.payments')) ?></h2><p class="section-heading__note"><?= e(__('documents.payments_intro')) ?></p></div><span class="badge badge--<?= e($balanceIsPositive ? 'warning' : 'success') ?>"><?= e($balanceLabel) ?> · <?= e(money_format_tnd((string) ($financialPosition['balance_due'] ?? '0.000'))) ?></span></div>
        <div class="document-summary-grid document-summary-grid--compact"><div><span class="detail-label"><?= e(__('documents.paid_amount')) ?></span><strong><?= e(money_format_tnd((string) ($financialPosition['paid_amount'] ?? '0.000'))) ?></strong></div><div><span class="detail-label"><?= e(__('documents.credited_amount')) ?></span><strong><?= e(money_format_tnd((string) ($financialPosition['credited_amount'] ?? '0.000'))) ?></strong></div></div>
        <?php if (!empty($paymentErrors['general'])): ?><p class="form-feedback form-feedback--error" role="alert"><?= e($paymentErrors['general']) ?></p><?php endif; ?>
        <?php if ($payments !== []): ?>
            <div class="table-wrap"><table class="data-table"><thead><tr><th scope="col"><?= e(__('documents.payment_date')) ?></th><th scope="col"><?= e(__('documents.payment_method_label')) ?></th><th scope="col"><?= e(__('documents.payment_reference')) ?></th><th scope="col"><?= e(__('documents.payment_amount')) ?></th><th scope="col"><?= e(__('documents.recorded_by')) ?></th></tr></thead><tbody><?php foreach ($payments as $payment): ?><tr><td><?= e($payment['paid_on']) ?></td><td><?= e(__('documents.payment_method.' . $payment['payment_method'])) ?></td><td><?= e($payment['reference'] !== '' ? $payment['reference'] : '—') ?><?php if ($payment['note'] !== ''): ?><span class="table-meta"><?= e($payment['note']) ?></span><?php endif; ?></td><td class="u-numeric"><?= e(money_format_tnd((string) $payment['amount'])) ?></td><td><?= e($payment['created_by_name'] ?? '—') ?></td></tr><?php endforeach; ?></tbody></table></div>
        <?php else: ?><p class="empty-state__copy"><?= e(__('documents.no_payments')) ?></p><?php endif; ?>
        <?php if (!empty($canManagePayments) && $balanceIsPositive): ?>
            <form class="form-grid" method="post" action="<?= e(url('/documents/' . (int) $document['id'] . '/payments')) ?>">
                <?= csrf_field() ?>
                <div class="field"><label class="field__label" for="payment-date"><?= e(__('documents.payment_date')) ?></label><input class="field__control" id="payment-date" name="paid_on" type="date" max="<?= e(date('Y-m-d')) ?>" required value="<?= e($paymentValues['paid_on'] ?? date('Y-m-d')) ?>"><?php if (!empty($paymentErrors['paid_on'])): ?><span class="field__hint field__hint--error"><?= e($paymentErrors['paid_on']) ?></span><?php endif; ?></div>
                <div class="field"><label class="field__label" for="payment-amount"><?= e(__('documents.payment_amount')) ?></label><input class="field__control" id="payment-amount" name="amount" type="number" min="0.001" max="<?= e((string) ($financialPosition['balance_due'] ?? '0.000')) ?>" step="0.001" required value="<?= e($paymentValues['amount'] ?? '') ?>"><?php if (!empty($paymentErrors['amount'])): ?><span class="field__hint field__hint--error"><?= e($paymentErrors['amount']) ?></span><?php endif; ?></div>
                <div class="field"><label class="field__label" for="payment-method"><?= e(__('documents.payment_method_label')) ?></label><select class="field__control" id="payment-method" name="payment_method" required><?php foreach (['bank_transfer', 'cash', 'cheque', 'card', 'other'] as $method): ?><option value="<?= e($method) ?>" <?= ($paymentValues['payment_method'] ?? 'bank_transfer') === $method ? 'selected' : '' ?>><?= e(__('documents.payment_method.' . $method)) ?></option><?php endforeach; ?></select><?php if (!empty($paymentErrors['payment_method'])): ?><span class="field__hint field__hint--error"><?= e($paymentErrors['payment_method']) ?></span><?php endif; ?></div>
                <div class="field"><label class="field__label" for="payment-reference"><?= e(__('documents.payment_reference')) ?></label><input class="field__control" id="payment-reference" name="reference" maxlength="120" value="<?= e($paymentValues['reference'] ?? '') ?>"></div>
                <div class="field"><label class="field__label" for="payment-note"><?= e(__('documents.payment_note')) ?></label><input class="field__control" id="payment-note" name="note" maxlength="255" value="<?= e($paymentValues['note'] ?? '') ?>"><?php if (!empty($paymentErrors['note'])): ?><span class="field__hint field__hint--error"><?= e($paymentErrors['note']) ?></span><?php endif; ?></div>
                <div class="form-actions"><button class="button button--primary" type="submit"><?= e(__('documents.add_payment')) ?></button></div>
            </form>
        <?php endif; ?>
    </section>
<?php endif; ?>
