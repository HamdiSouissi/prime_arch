<?php
declare(strict_types=1);

use App\Core\View;
use Modules\ComptabiliteExport\Services\ComptabiliteService;

$documents = is_array($documents ?? null) ? $documents : [];
$payments = is_array($payments ?? null) ? $payments : [];
$salesTotals = is_array($salesTotals ?? null) ? $salesTotals : ['base' => 0, 'tax' => 0, 'stamp' => 0, 'withholding' => 0, 'total' => 0, 'documents' => 0];
$statistics = is_array($statistics ?? null) ? $statistics : [];
$vatBreakdown = is_array($vatBreakdown ?? null) ? $vatBreakdown : [];
$years = is_array($years ?? null) ? $years : [];
$period = is_array($period ?? null) ? $period : ['start' => date('Y-m-01'), 'end' => date('Y-m-t'), 'key' => date('Y-m')];
$year = (int) ($year ?? (int) date('Y'));
$month = (int) ($month ?? (int) date('n'));
$paymentsTotal = (int) ($paymentsTotal ?? 0);

$amount = static fn (int $millimes): string => ComptabiliteService::label($millimes);
$signed = static fn (array $row, string $field): int => ComptabiliteService::sign((string) $row['document_type'])
    * ComptabiliteService::millimes($row[$field] ?? '0');
$dateLabel = static function (string $value): string {
    $timestamp = strtotime($value);

    return $timestamp === false ? '—' : date('d/m/Y', $timestamp);
};
$exportUrl = static fn (string $journal): string => url('/comptabilite/export?' . http_build_query([
    'journal' => $journal,
    'year' => $year,
    'month' => $month,
]));
?>
<?php View::partial('partials/page-header', [
    'pageTitle' => __('comptabilite.title'),
    'eyebrow' => __('comptabilite.eyebrow'),
    'subtitle' => __('comptabilite.intro'),
]) ?>

<p class="table-meta" role="note"><?= e(__('comptabilite.readonly_notice')) ?></p>

<section class="card stack-md" aria-labelledby="comptabilite-period-title">
    <div class="card-header">
        <div>
            <h2 class="card-header__title" id="comptabilite-period-title"><?= e(__('comptabilite.period_title')) ?></h2>
            <p class="section-heading__note"><?= e(__('comptabilite.period_range', [
                'from' => $dateLabel($period['start']),
                'to' => $dateLabel($period['end']),
            ])) ?></p>
        </div>
    </div>
    <form class="filter-row" method="get" action="<?= e(url('/comptabilite')) ?>">
        <div class="field">
            <label class="field__label" for="comptabilite-year"><?= e(__('comptabilite.year')) ?></label>
            <select class="field__control" id="comptabilite-year" name="year">
                <?php foreach ($years as $option): ?>
                    <option value="<?= (int) $option ?>" <?= (int) $option === $year ? 'selected' : '' ?>><?= (int) $option ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="field">
            <label class="field__label" for="comptabilite-month"><?= e(__('comptabilite.month')) ?></label>
            <select class="field__control" id="comptabilite-month" name="month">
                <?php for ($option = 1; $option <= 12; $option++): ?>
                    <option value="<?= $option ?>" <?= $option === $month ? 'selected' : '' ?>><?= e(__('comptabilite.month.' . $option)) ?></option>
                <?php endfor; ?>
            </select>
        </div>
        <button class="button button--secondary" type="submit">
            <svg class="icon icon--small" aria-hidden="true"><use href="<?= e(asset('img/icons.svg')) ?>#filter"></use></svg><?= e(__('common.filter')) ?>
        </button>
    </form>
</section>

<section class="stats-grid" aria-label="<?= e(__('comptabilite.statistics')) ?>">
    <?php View::partial('partials/stat-card', [
        'label' => __('comptabilite.stat.sales'),
        'value' => $amount((int) $salesTotals['total']),
        'icon' => 'receipt',
        'tone' => 'blue',
        'note' => __('comptabilite.stat.documents_note', ['count' => (string) ($statistics['documents'] ?? 0)]),
    ]) ?>
    <?php View::partial('partials/stat-card', [
        'label' => __('comptabilite.stat.vat'),
        'value' => $amount((int) $salesTotals['tax']),
        'icon' => 'percent',
        'tone' => 'mint',
        'note' => __('comptabilite.stat.stamp_note', ['amount' => $amount((int) $salesTotals['stamp'])]),
    ]) ?>
    <?php View::partial('partials/stat-card', [
        'label' => __('comptabilite.stat.payments'),
        'value' => $amount($paymentsTotal),
        'icon' => 'wallet',
        'tone' => 'pink',
        'note' => __('comptabilite.stat.payments_note', ['count' => (string) ($statistics['payments'] ?? 0)]),
    ]) ?>
    <?php View::partial('partials/stat-card', [
        'label' => __('comptabilite.stat.outstanding'),
        'value' => $amount((int) ($statistics['outstanding'] ?? 0)),
        'icon' => 'currency',
        'tone' => 'coral',
        'note' => __('comptabilite.stat.outstanding_note'),
    ]) ?>
</section>

<section class="card stack-md" aria-labelledby="comptabilite-export-title">
    <div class="card-header">
        <div>
            <h2 class="card-header__title" id="comptabilite-export-title"><?= e(__('comptabilite.export_title')) ?></h2>
            <p class="section-heading__note"><?= e(__('comptabilite.export_help')) ?></p>
        </div>
    </div>
    <div class="cluster">
        <a class="button button--primary" href="<?= e($exportUrl('ventes')) ?>">
            <svg class="icon icon--small" aria-hidden="true"><use href="<?= e(asset('img/icons.svg')) ?>#download"></use></svg><?= e(__('comptabilite.export_sales')) ?>
        </a>
        <a class="button button--secondary" href="<?= e($exportUrl('encaissements')) ?>">
            <svg class="icon icon--small" aria-hidden="true"><use href="<?= e(asset('img/icons.svg')) ?>#download"></use></svg><?= e(__('comptabilite.export_payments')) ?>
        </a>
    </div>
    <p class="table-meta"><?= e(__('comptabilite.export_format')) ?></p>
</section>

<section class="card stack-md" aria-labelledby="comptabilite-sales-title">
    <div class="card-header">
        <div>
            <h2 class="card-header__title" id="comptabilite-sales-title"><?= e(__('comptabilite.sales_title')) ?></h2>
            <p class="section-heading__note"><?= e(__('comptabilite.sales_help')) ?></p>
        </div>
    </div>
    <?php if ($documents === []): ?>
        <?php View::partial('partials/empty-state', [
            'title' => __('comptabilite.empty_sales_title'),
            'description' => __('comptabilite.empty_sales_help'),
        ]) ?>
    <?php else: ?>
        <div class="table-wrap">
            <table class="data-table">
                <thead>
                    <tr>
                        <th scope="col"><?= e(__('comptabilite.date')) ?></th>
                        <th scope="col"><?= e(__('comptabilite.document')) ?></th>
                        <th scope="col"><?= e(__('comptabilite.type')) ?></th>
                        <th scope="col"><?= e(__('comptabilite.client')) ?></th>
                        <th scope="col"><?= e(__('comptabilite.base')) ?></th>
                        <th scope="col"><?= e(__('comptabilite.vat')) ?></th>
                        <th scope="col"><?= e(__('comptabilite.stamp')) ?></th>
                        <th scope="col"><?= e(__('comptabilite.withholding')) ?></th>
                        <th scope="col"><?= e(__('comptabilite.total')) ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($documents as $document): ?>
                        <tr>
                            <td><?= e($dateLabel((string) ($document['issue_date'] ?? ''))) ?></td>
                            <td>
                                <a class="table-primary" href="<?= e(url('/documents/' . (int) $document['id'])) ?>">
                                    <span class="table-primary__text">
                                        <strong class="table-primary__name"><?= e((string) ($document['document_number'] ?? '')) ?></strong>
                                        <span class="table-primary__meta"><?= e(trim((string) ($document['project_reference'] ?? ''))) ?></span>
                                    </span>
                                </a>
                            </td>
                            <td><?= e(__('comptabilite.document_type.' . (string) $document['document_type'])) ?></td>
                            <td>
                                <?= e((string) $document['client_name']) ?>
                                <?php if (trim((string) ($document['client_tax_id'] ?? '')) !== ''): ?>
                                    <span class="table-meta"><?= e((string) $document['client_tax_id']) ?></span>
                                <?php endif; ?>
                            </td>
                            <td class="u-numeric"><?= e($amount($signed($document, 'subtotal'))) ?></td>
                            <td class="u-numeric"><?= e($amount($signed($document, 'tax_amount'))) ?></td>
                            <td class="u-numeric"><?= e($amount($signed($document, 'stamp_amount'))) ?></td>
                            <td class="u-numeric"><?= e($amount($signed($document, 'withholding_amount'))) ?></td>
                            <td class="u-numeric"><strong><?= e($amount($signed($document, 'total_amount'))) ?></strong></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr>
                        <th scope="row" colspan="4"><?= e(__('comptabilite.totals')) ?></th>
                        <td class="u-numeric"><strong><?= e($amount((int) $salesTotals['base'])) ?></strong></td>
                        <td class="u-numeric"><strong><?= e($amount((int) $salesTotals['tax'])) ?></strong></td>
                        <td class="u-numeric"><strong><?= e($amount((int) $salesTotals['stamp'])) ?></strong></td>
                        <td class="u-numeric"><strong><?= e($amount((int) $salesTotals['withholding'])) ?></strong></td>
                        <td class="u-numeric"><strong><?= e($amount((int) $salesTotals['total'])) ?></strong></td>
                    </tr>
                </tfoot>
            </table>
        </div>
        <p class="table-meta"><?= e(__('comptabilite.sales_note')) ?></p>
    <?php endif; ?>
</section>

<section class="card stack-md" aria-labelledby="comptabilite-vat-title">
    <div class="card-header">
        <div>
            <h2 class="card-header__title" id="comptabilite-vat-title"><?= e(__('comptabilite.vat_title')) ?></h2>
            <p class="section-heading__note"><?= e(__('comptabilite.vat_help')) ?></p>
        </div>
    </div>
    <?php if ($vatBreakdown === []): ?>
        <p class="table-meta"><?= e(__('comptabilite.no_vat')) ?></p>
    <?php else: ?>
        <div class="table-wrap">
            <table class="data-table">
                <thead>
                    <tr>
                        <th scope="col"><?= e(__('comptabilite.vat_rate')) ?></th>
                        <th scope="col"><?= e(__('comptabilite.base')) ?></th>
                        <th scope="col"><?= e(__('comptabilite.vat')) ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($vatBreakdown as $bucket): ?>
                        <tr>
                            <td><?= e(str_replace('.', ',', (string) $bucket['rate'])) ?> %</td>
                            <td class="u-numeric"><?= e($amount((int) $bucket['base'])) ?></td>
                            <td class="u-numeric"><strong><?= e($amount((int) $bucket['tax'])) ?></strong></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>

<section class="card stack-md" aria-labelledby="comptabilite-payments-title">
    <div class="card-header">
        <div>
            <h2 class="card-header__title" id="comptabilite-payments-title"><?= e(__('comptabilite.payments_title')) ?></h2>
            <p class="section-heading__note"><?= e(__('comptabilite.payments_help')) ?></p>
        </div>
    </div>
    <?php if ($payments === []): ?>
        <?php View::partial('partials/empty-state', [
            'title' => __('comptabilite.empty_payments_title'),
            'description' => __('comptabilite.empty_payments_help'),
        ]) ?>
    <?php else: ?>
        <div class="table-wrap">
            <table class="data-table">
                <thead>
                    <tr>
                        <th scope="col"><?= e(__('comptabilite.date')) ?></th>
                        <th scope="col"><?= e(__('comptabilite.document')) ?></th>
                        <th scope="col"><?= e(__('comptabilite.client')) ?></th>
                        <th scope="col"><?= e(__('comptabilite.method')) ?></th>
                        <th scope="col"><?= e(__('comptabilite.reference')) ?></th>
                        <th scope="col"><?= e(__('comptabilite.amount')) ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($payments as $payment): ?>
                        <tr>
                            <td><?= e($dateLabel((string) ($payment['paid_on'] ?? ''))) ?></td>
                            <td><?= e((string) ($payment['document_number'] ?? '')) ?></td>
                            <td><?= e((string) $payment['client_name']) ?></td>
                            <td><?= e(__('comptabilite.method.' . (string) $payment['payment_method'])) ?></td>
                            <td><?= trim((string) ($payment['reference'] ?? '')) !== '' ? e((string) $payment['reference']) : '<span class="muted">—</span>' ?></td>
                            <td class="u-numeric"><strong><?= e($amount(ComptabiliteService::millimes($payment['amount'] ?? '0'))) ?></strong></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr>
                        <th scope="row" colspan="5"><?= e(__('comptabilite.totals')) ?></th>
                        <td class="u-numeric"><strong><?= e($amount($paymentsTotal)) ?></strong></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    <?php endif; ?>
</section>
