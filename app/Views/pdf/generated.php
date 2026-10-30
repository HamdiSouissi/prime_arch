<?php
declare(strict_types=1);

use App\Core\Translator;

$snapshot = is_array($snapshot ?? null) ? $snapshot : [];
$document = is_array($snapshot['document'] ?? null) ? $snapshot['document'] : [];
$client = is_array($snapshot['client'] ?? null) ? $snapshot['client'] : [];
$project = is_array($snapshot['project'] ?? null) ? $snapshot['project'] : [];
$cabinet = is_array($snapshot['cabinet'] ?? null) ? $snapshot['cabinet'] : [];
$lines = is_array($document['lines'] ?? null) ? $document['lines'] : [];
$documentTitle = (string) ($document['title'] ?? __('documents.title'));
$documentNumber = (string) ($document['document_number'] ?? __('documents.draft'));
$documentDateValue = (string) ($document['issue_date'] ?? '');
$documentDate = $documentDateValue !== '' ? date((string) config('tunisia.date_format', 'd/m/Y'), strtotime($documentDateValue)) : '';
$projectName = trim((string) ($project['name'] ?? ''));
$projectName = $projectName !== '' ? $projectName : __('documents.no_project');
$legalNotice = (string) ($document['legal_notice'] ?? '');
$direction = Translator::direction();
$client['name'] = (string) ($client['name'] ?? '');
$client['address'] = (string) ($client['address'] ?? '');
$client['phone'] = (string) ($client['phone'] ?? '');
$client['email'] = (string) ($client['email'] ?? '');
$logoPath = is_string($logoPath ?? null) ? $logoPath : '';
$documentKinds = [
    'quote' => __('documents.type.quote'),
    'contract' => __('documents.type.contract'),
    'invoice' => __('documents.type.invoice'),
    'credit_note' => __('documents.type.credit_note'),
];
$dateLabel = static function (mixed $value): string {
    $date = is_string($value) ? $value : '';
    if ($date === '') {
        return '';
    }
    $timestamp = strtotime($date);
    return $timestamp === false ? '' : date((string) config('tunisia.date_format', 'd/m/Y'), $timestamp);
};
$formattedRate = static fn (mixed $value): string => str_replace('.', ',', (string) $value);
ob_start();
?>
<table class="pdf-document-meta">
    <tr>
        <td><span class="pdf-party-label"><?= e(__('documents.pdf.document_type')) ?></span><strong><?= e($documentKinds[$document['document_type'] ?? ''] ?? __('documents.title')) ?></strong></td>
        <td><span class="pdf-party-label"><?= e(__('documents.pdf.project')) ?></span><strong><?= e($projectName) ?></strong>
            <?php if (!empty($project['reference'])): ?><br><span class="pdf-muted"><?= e((string) $project['reference']) ?></span><?php endif; ?>
            <?php if (!empty($document['phase_name'])): ?><br><span class="pdf-muted"><?= e(__('documents.pdf.phase')) ?> : <?= e((string) $document['phase_name']) ?></span><?php endif; ?>
        </td>
    </tr>
</table>

<?php if (!empty($document['reference_document_number'])): ?>
    <p class="pdf-paragraph"><strong><?= e(__('documents.pdf.related_document')) ?> :</strong> <?= e((string) $document['reference_document_number']) ?></p>
<?php endif; ?>
<?php if (!empty($document['billing_kind']) && $document['billing_kind'] !== 'standard'): ?>
    <p class="pdf-paragraph"><strong><?= e(__('documents.pdf.billing_kind')) ?> :</strong> <?= e(__('documents.billing_kind.' . $document['billing_kind'])) ?></p>
<?php endif; ?>

<table class="pdf-table">
    <thead>
        <tr>
            <th scope="col"><?= e(__('documents.pdf.description')) ?></th>
            <th scope="col" class="pdf-quantity"><?= e(__('documents.pdf.quantity')) ?></th>
            <th scope="col" class="pdf-number-cell"><?= e(__('documents.pdf.unit_price')) ?></th>
            <th scope="col" class="pdf-number-cell"><?= e(__('documents.pdf.line_total')) ?></th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($lines as $line): ?>
            <tr>
                <td><?= nl2br(e($line['description'] ?? '')) ?></td>
                <td class="pdf-quantity"><?= e($formattedRate($line['quantity'] ?? '0.000')) ?><?= !empty($line['unit']) ? ' ' . e($line['unit']) : '' ?></td>
                <td class="pdf-number-cell"><?= e(money_format_tnd((string) ($line['unit_price'] ?? '0.000'))) ?></td>
                <td class="pdf-number-cell"><?= e(money_format_tnd((string) ($line['line_total'] ?? '0.000'))) ?></td>
            </tr>
        <?php endforeach; ?>
    </tbody>
</table>

<table class="pdf-totals">
    <tr><td><?= e(__('documents.pdf.subtotal')) ?></td><td><?= e(money_format_tnd((string) ($document['subtotal'] ?? '0.000'))) ?></td></tr>
    <tr><td><?= e(__('documents.pdf.vat', ['rate' => $formattedRate($document['tax_rate'] ?? '0.00')])) ?></td><td><?= e(money_format_tnd((string) ($document['tax_amount'] ?? '0.000'))) ?></td></tr>
    <?php if ((string) ($document['stamp_amount'] ?? '0.000') !== '0.000'): ?>
        <tr><td><?= e(__('documents.pdf.stamp_duty')) ?></td><td><?= e(money_format_tnd((string) $document['stamp_amount'])) ?></td></tr>
    <?php endif; ?>
    <?php if ((string) ($document['withholding_amount'] ?? '0.000') !== '0.000'): ?>
        <tr><td><?= e(__('documents.pdf.withholding', ['rate' => $formattedRate($document['withholding_rate'] ?? '0.000')])) ?></td><td>− <?= e(money_format_tnd((string) $document['withholding_amount'])) ?></td></tr>
    <?php endif; ?>
    <tr class="pdf-total-final"><td><?= e(__('documents.pdf.total')) ?></td><td><?= e(money_format_tnd((string) ($document['total_amount'] ?? '0.000'))) ?></td></tr>
</table>

<?php if (!empty($document['due_date'])): ?>
    <p class="pdf-paragraph"><strong><?= e(__('documents.pdf.due_date')) ?> :</strong> <?= e($dateLabel($document['due_date'])) ?></p>
<?php endif; ?>
<?php if (!empty($document['valid_until'])): ?>
    <p class="pdf-paragraph"><strong><?= e(__('documents.pdf.valid_until')) ?> :</strong> <?= e($dateLabel($document['valid_until'])) ?></p>
<?php endif; ?>
<?php if (!empty($document['terms_text'])): ?>
    <h2 class="pdf-section-title"><?= e(__('documents.pdf.terms')) ?></h2>
    <p class="pdf-paragraph"><?= nl2br(e($document['terms_text'])) ?></p>
<?php endif; ?>
<?php if (!empty($document['notes'])): ?>
    <h2 class="pdf-section-title"><?= e(__('documents.pdf.notes')) ?></h2>
    <p class="pdf-paragraph"><?= nl2br(e($document['notes'])) ?></p>
<?php endif; ?>
<?php
$documentBody = (string) ob_get_clean();
require __DIR__ . '/base.php';
