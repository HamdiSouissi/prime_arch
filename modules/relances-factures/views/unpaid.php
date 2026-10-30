<?php
declare(strict_types=1);

use App\Core\View;

$invoices = is_array($invoices ?? null) ? $invoices : [];
$filters = is_array($filters ?? null) ? $filters : ['q' => '', 'bucket' => '', 'hide_excluded' => true];
$totals = is_array($totals ?? null) ? $totals : [];
$buckets = is_array($buckets ?? null) ? $buckets : [];

$formatDate = static function (?string $value): string {
    if ($value === null || $value === '') {
        return '—';
    }
    $time = strtotime($value);

    return $time === false ? '—' : date('d/m/Y', $time);
};
?>
<?php View::partial('partials/page-header', [
    'pageTitle' => __('relance.unpaid_title'),
    'eyebrow' => __('relance.eyebrow'),
    'subtitle' => __('relance.unpaid_intro'),
    'secondaryAction' => ['label' => __('relance.back'), 'icon' => 'arrow-left', 'url' => url('/relances')],
]) ?>

<?php if (!empty($notice)): ?><p class="form-feedback form-feedback--success" role="status"><?= e($notice) ?></p><?php endif; ?>
<?php if (!empty($error)): ?><p class="form-feedback form-feedback--error" role="alert"><?= e($error) ?></p><?php endif; ?>

<section class="card stack-md" aria-label="<?= e(__('relance.queue')) ?>">
    <form class="filter-row" method="get" action="<?= e(url('/relances/impayes')) ?>">
        <div class="input-with-icon">
            <svg class="icon icon--small" aria-hidden="true"><use href="<?= e(asset('img/icons.svg')) ?>#search"></use></svg>
            <input class="field__control" type="search" name="q" value="<?= e((string) $filters['q']) ?>"
                   placeholder="<?= e(__('relance.search_placeholder')) ?>" aria-label="<?= e(__('common.search')) ?>">
        </div>
        <div class="field">
            <label class="u-sr-only" for="relance-bucket-filter"><?= e(__('relance.bucket_filter')) ?></label>
            <select class="field__control" id="relance-bucket-filter" name="bucket">
                <option value=""><?= e(__('relance.all_buckets')) ?></option>
                <?php foreach (array_keys($buckets) as $key): ?>
                    <option value="<?= e($key) ?>" <?= $filters['bucket'] === $key ? 'selected' : '' ?>><?= e(__('relance.bucket.' . $key)) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <input type="hidden" name="hide_excluded" value="0">
        <label class="check-label">
            <input type="checkbox" name="hide_excluded" value="1" <?= $filters['hide_excluded'] ? 'checked' : '' ?>>
            <?= e(__('relance.hide_excluded')) ?>
        </label>
        <button class="button button--secondary" type="submit">
            <svg class="icon icon--small" aria-hidden="true"><use href="<?= e(asset('img/icons.svg')) ?>#filter"></use></svg><?= e(__('common.filter')) ?>
        </button>
        <?php if ($filters['q'] !== '' || $filters['bucket'] !== '' || !$filters['hide_excluded']): ?>
            <a class="button button--ghost" href="<?= e(url('/relances/impayes')) ?>"><?= e(__('common.clear')) ?></a>
        <?php endif; ?>
    </form>

    <p class="table-meta">
        <?= e(__('relance.queue_summary', [
            'count' => (string) ($totals['invoices'] ?? 0),
            'amount' => money_format_tnd((string) ($totals['total'] ?? '0.000')),
        ])) ?>
    </p>

    <?php if ($invoices === []): ?>
        <?php View::partial('partials/empty-state', [
            'title' => __('relance.queue_empty_title'),
            'description' => __('relance.queue_empty_help'),
        ]) ?>
    <?php else: ?>
        <div class="table-wrap">
            <table class="data-table">
                <thead>
                    <tr>
                        <th scope="col"><?= e(__('relance.client')) ?></th>
                        <th scope="col"><?= e(__('relance.invoice')) ?></th>
                        <th scope="col"><?= e(__('relance.due_date')) ?></th>
                        <th scope="col"><?= e(__('relance.delay')) ?></th>
                        <th scope="col"><?= e(__('relance.outstanding')) ?></th>
                        <th scope="col"><?= e(__('relance.last_relance')) ?></th>
                        <th scope="col"><?= e(__('relance.suggested_tone')) ?></th>
                        <th scope="col"><?= e(__('common.actions')) ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($invoices as $invoice): ?>
                        <tr>
                            <td>
                                <span class="table-primary__text">
                                    <strong class="table-primary__name"><?= e((string) $invoice['client_name']) ?></strong>
                                    <?php if ((bool) $invoice['is_excluded']): ?>
                                        <span class="badge badge--warning"><?= e(__('relance.excluded')) ?></span>
                                    <?php endif; ?>
                                    <?php if (($invoice['project_name'] ?? '') !== ''): ?>
                                        <span class="table-primary__meta"><?= e((string) $invoice['project_name']) ?></span>
                                    <?php endif; ?>
                                </span>
                            </td>
                            <td>
                                <a class="table-primary" href="<?= e(url('/relances/factures/' . (int) $invoice['id'])) ?>">
                                    <span class="table-primary__text">
                                        <strong class="table-primary__name"><?= e((string) ($invoice['document_number'] ?? '—')) ?></strong>
                                        <span class="table-primary__meta"><?= e((string) $invoice['title']) ?></span>
                                    </span>
                                </a>
                            </td>
                            <td><?= e($formatDate($invoice['due_date'] ?? $invoice['issue_date'] ?? null)) ?></td>
                            <td>
                                <?php if ((bool) $invoice['is_overdue']): ?>
                                    <span class="badge badge--error"><?= e(__('relance.days_late', ['days' => (string) $invoice['days_late']])) ?></span>
                                <?php else: ?>
                                    <span class="badge badge--success"><?= e(__('relance.not_due')) ?></span>
                                <?php endif; ?>
                            </td>
                            <td class="u-numeric"><strong><?= e(money_format_tnd((string) $invoice['outstanding'])) ?></strong></td>
                            <td>
                                <?php if (($invoice['last_relance_on'] ?? null) !== null): ?>
                                    <?= e($formatDate((string) $invoice['last_relance_on'])) ?>
                                    <span class="table-meta"><?= e(__('relance.tone_value.' . (string) $invoice['last_tone'])) ?></span>
                                <?php else: ?>
                                    <span class="muted"><?= e(__('relance.never')) ?></span>
                                <?php endif; ?>
                            </td>
                            <td><span class="badge badge--info"><?= e(__('relance.tone_value.' . (string) $invoice['suggested_tone'])) ?></span></td>
                            <td>
                                <a class="button button--small button--secondary" href="<?= e(url('/relances/factures/' . (int) $invoice['id'] . '?tone=' . rawurlencode((string) $invoice['suggested_tone']))) ?>">
                                    <?= e(__('relance.compose')) ?>
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <p class="table-meta"><?= e(__('relance.queue_note')) ?></p>
    <?php endif; ?>
</section>
