<?php
declare(strict_types=1);

/** @var string $entity */
/** @var array<string,string> $filters */
/** @var array<string,mixed>|null $importResult */
/** @var string $importFileError */
/** @var bool $canManage */
$entity = in_array($entity ?? '', ['clients', 'projects'], true) ? $entity : 'clients';
$filters = is_array($filters ?? null) ? $filters : [];
$importResult = is_array($importResult ?? null) ? $importResult : null;
$importFileError = is_string($importFileError ?? null) ? $importFileError : '';
$canManage = !empty($canManage);
$queryFilters = array_filter($filters, static fn (mixed $value): bool => is_scalar($value) && (string) $value !== '');
$queryString = http_build_query($queryFilters);
$querySuffix = $queryString !== '' ? '?' . $queryString : '';
$listUrl = '/' . $entity;
$inputId = $entity . '-csv-file';
?>
<details class="data-transfer-panel" <?= $importResult !== null || $importFileError !== '' ? 'open' : '' ?>>
    <summary class="data-transfer-panel__summary">
        <span class="data-transfer-panel__icon"><svg class="icon" aria-hidden="true"><use href="<?= e(asset('img/icons.svg')) ?>#upload"></use></svg></span>
        <span class="data-transfer-panel__copy"><strong><?= e(__('common.csv_tools')) ?></strong><small><?= e(__('common.csv_tools_help')) ?></small></span>
        <span class="badge badge--info badge--plain">CSV · Excel</span>
        <svg class="icon data-transfer-panel__chevron" aria-hidden="true"><use href="<?= e(asset('img/icons.svg')) ?>#chevron-down"></use></svg>
    </summary>
    <div class="data-transfer-panel__body">
        <p class="muted text-footnote"><?= e(__('common.csv_max')) ?></p>
        <p class="muted text-footnote"><?= e(__('common.csv_help_' . $entity)) ?></p>
        <div class="cluster data-transfer-panel__downloads">
            <a class="button button--secondary" href="<?= e(url($listUrl . '/export-csv' . $querySuffix)) ?>" download>
                <svg class="icon icon--small" aria-hidden="true"><use href="<?= e(asset('img/icons.svg')) ?>#download"></use></svg><?= e(__('common.csv_export')) ?>
            </a>
            <a class="button button--ghost" href="<?= e(url($listUrl . '/import-template')) ?>" download>
                <svg class="icon icon--small" aria-hidden="true"><use href="<?= e(asset('img/icons.svg')) ?>#file-text"></use></svg><?= e(__('common.csv_template')) ?>
            </a>
        </div>
        <?php if ($canManage): ?>
            <form class="data-import-form" method="post" action="<?= e(url($listUrl . '/import' . $querySuffix)) ?>" enctype="multipart/form-data" data-csv-import-form data-file-max-bytes="5242880">
                <?= csrf_field() ?>
                <div class="field">
                    <label class="field__label" for="<?= e($inputId) ?>"><?= e(__('common.csv_file_label')) ?></label>
                    <input class="field__control" id="<?= e($inputId) ?>" name="csv_file" type="file" accept=".csv,text/csv" required aria-describedby="<?= e($inputId) ?>-help <?= e($inputId) ?>-status" data-csv-file-input data-file-invalid="<?= e(__('common.csv_file_invalid')) ?>" data-file-selected="<?= e(__('common.csv_file_selected')) ?>">
                    <span class="field__hint" id="<?= e($inputId) ?>-help"><?= e(__('common.csv_file_help')) ?></span>
                    <span class="field__hint" id="<?= e($inputId) ?>-status" data-csv-file-status aria-live="polite"></span>
                </div>
                <button class="button button--primary" type="submit"><svg class="icon icon--small" aria-hidden="true"><use href="<?= e(asset('img/icons.svg')) ?>#upload"></use></svg><?= e(__('common.csv_import')) ?></button>
            </form>
        <?php endif; ?>

        <?php if ($importFileError !== ''): ?>
            <p class="form-feedback form-feedback--error" role="alert"><?= e($importFileError) ?></p>
        <?php endif; ?>
        <?php if ($importResult !== null): ?>
            <div class="data-import-result" role="status" aria-live="polite">
                <p class="form-feedback <?= (int) ($importResult['failed'] ?? 0) > 0 ? 'form-feedback--info' : 'form-feedback--success' ?>"><?= e(__('common.csv_import_summary', [
                    'created' => (string) (int) ($importResult['created'] ?? 0),
                    'failed' => (string) (int) ($importResult['failed'] ?? 0),
                ])) ?></p>
                <?php $rowErrors = is_array($importResult['errors'] ?? null) ? $importResult['errors'] : []; ?>
                <?php if ($rowErrors !== []): ?>
                    <details class="data-import-errors">
                        <summary><?= e(__('common.csv_rows_rejected', ['count' => (string) (int) ($importResult['failed'] ?? count($rowErrors))])) ?></summary>
                        <ul>
                            <?php foreach ($rowErrors as $rowError): ?>
                                <?php if (is_array($rowError)): ?>
                                    <li><strong><?= e(__('common.line', ['line' => (string) (int) ($rowError['line'] ?? 0)])) ?></strong> — <?= e((string) ($rowError['message'] ?? '')) ?></li>
                                <?php endif; ?>
                            <?php endforeach; ?>
                            <?php if ((int) ($importResult['errors_remaining'] ?? 0) > 0): ?>
                                <li><?= e(__('common.csv_more_errors', ['count' => (string) (int) $importResult['errors_remaining']])) ?></li>
                            <?php endif; ?>
                        </ul>
                    </details>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
</details>
