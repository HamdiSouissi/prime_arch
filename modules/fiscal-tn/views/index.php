<?php
declare(strict_types=1);

use App\Core\View;
use Modules\FiscalTn\Services\FiscalService;

$rows = is_array($rows ?? null) ? $rows : [];
$all = is_array($all ?? null) ? $all : [];
$upcoming = is_array($upcoming ?? null) ? $upcoming : [];
$byPeriod = is_array($byPeriod ?? null) ? $byPeriod : [];
$summary = is_array($summary ?? null) ? $summary : [];
$totals = is_array($totals ?? null) ? $totals : [];
$years = is_array($years ?? null) ? $years : [];
$kinds = is_array($kinds ?? null) ? $kinds : FiscalService::KINDS;
$statuses = is_array($statuses ?? null) ? $statuses : FiscalService::STATUSES;
$echeanceErrors = is_array($echeanceErrors ?? null) ? $echeanceErrors : [];
$echeanceValues = is_array($echeanceValues ?? null) ? $echeanceValues : [];
$declarationErrors = is_array($declarationErrors ?? null) ? $declarationErrors : [];
$declarationValues = is_array($declarationValues ?? null) ? $declarationValues : [];
$selectedId = (int) ($selectedId ?? 0);
$year = (int) ($year ?? (int) date('Y'));
$horizon = (int) ($horizon ?? FiscalService::HORIZON_DEFAULT);
$today = (string) ($today ?? date('Y-m-d'));
$kind = (string) ($kind ?? '');
$status = (string) ($status ?? '');

$dateLabel = static function (?string $value): string {
    $timestamp = $value !== null && $value !== '' ? strtotime($value) : false;

    return $timestamp === false ? '—' : date('d/m/Y', $timestamp);
};
$badge = static fn (string $value): string => match ($value) {
    'paid' => 'badge--success',
    'filed' => 'badge--info',
    'late' => 'badge--error',
    default => 'badge--warning',
};
$daysLabel = static function (array $row) use ($today): string {
    $days = $row['days_left'] ?? FiscalService::daysLeft(
        isset($row['due_on']) ? (string) $row['due_on'] : null,
        $today
    );
    if ($days === null) {
        return '';
    }

    return (int) $days < 0
        ? __('fiscal.late_by', ['days' => (string) abs((int) $days)])
        : __('fiscal.due_in', ['days' => (string) $days]);
};
$errorHint = static fn (array $errors, string $field): string => (string) ($errors[$field] ?? '');
$exportQuery = array_filter([
    'year' => (string) $year,
    'kind' => $kind,
    'status' => $status,
], static fn (string $value): bool => $value !== '');
$selected = null;
foreach ($all as $candidate) {
    if ((int) $candidate['id'] === $selectedId) {
        $selected = $candidate;
        break;
    }
}
$declaration = $declarationValues !== [] ? $declarationValues : ($selected ?? []);
?>
<?php View::partial('partials/page-header', [
    'pageTitle' => __('fiscal.title'),
    'eyebrow' => __('fiscal.eyebrow'),
    'subtitle' => __('fiscal.intro'),
    'secondaryAction' => [
        'label' => __('fiscal.export'),
        'icon' => 'download',
        'url' => url('/fiscal/export' . ($exportQuery !== [] ? '?' . http_build_query($exportQuery) : '')),
    ],
]) ?>

<?php if (!empty($notice)): ?><p class="form-feedback form-feedback--success" role="status"><?= e($notice) ?></p><?php endif; ?>
<?php if (!empty($error)): ?><p class="form-feedback form-feedback--error" role="alert"><?= e($error) ?></p><?php endif; ?>

<p class="form-feedback form-feedback--info" role="note"><?= e(__('fiscal.guardrail')) ?></p>

<section class="stats-grid" aria-label="<?= e(__('fiscal.statistics')) ?>">
    <?php View::partial('partials/stat-card', [
        'label' => __('fiscal.stat.obligations'),
        'value' => (string) ($summary['total'] ?? 0),
        'icon' => 'calendar',
        'tone' => 'blue',
        'note' => __('fiscal.stat.year_note', ['year' => (string) $year]),
    ]) ?>
    <?php View::partial('partials/stat-card', [
        'label' => __('fiscal.stat.to_prepare'),
        'value' => (string) ($summary['to_prepare'] ?? 0),
        'icon' => 'clock',
        'tone' => 'coral',
        'note' => __('fiscal.stat.horizon_note', ['days' => (string) $horizon]),
    ]) ?>
    <?php View::partial('partials/stat-card', [
        'label' => __('fiscal.stat.filed'),
        'value' => (string) ($summary['filed'] ?? 0),
        'icon' => 'file-text',
        'tone' => 'mint',
        'note' => __('fiscal.stat.amount_note', ['amount' => FiscalService::label((int) ($summary['amount_filed'] ?? 0))]),
    ]) ?>
    <?php View::partial('partials/stat-card', [
        'label' => __('fiscal.stat.paid'),
        'value' => (string) ($summary['paid'] ?? 0),
        'icon' => 'check-circle',
        'tone' => 'pink',
        'note' => __('fiscal.stat.late_note', ['count' => (string) ($summary['late'] ?? 0)]),
    ]) ?>
</section>

<section class="card stack-md" aria-labelledby="fiscal-filter-title">
    <div class="card-header">
        <div>
            <h2 class="card-header__title" id="fiscal-filter-title"><?= e(__('fiscal.filter_title')) ?></h2>
            <p class="section-heading__note"><?= e(__('fiscal.filter_help')) ?></p>
        </div>
    </div>
    <form class="filter-row" method="get" action="<?= e(url('/fiscal')) ?>">
        <div class="field">
            <label class="field__label" for="fiscal-year"><?= e(__('fiscal.year')) ?></label>
            <select class="field__control" id="fiscal-year" name="year">
                <?php foreach ($years as $option): ?>
                    <option value="<?= (int) $option ?>" <?= (int) $option === $year ? 'selected' : '' ?>><?= (int) $option ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="field">
            <label class="field__label" for="fiscal-kind"><?= e(__('fiscal.kind.label')) ?></label>
            <select class="field__control" id="fiscal-kind" name="kind">
                <option value=""><?= e(__('fiscal.all_kinds')) ?></option>
                <?php foreach ($kinds as $option): ?>
                    <option value="<?= e($option) ?>" <?= $kind === $option ? 'selected' : '' ?>><?= e(__('fiscal.kind.' . $option)) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="field">
            <label class="field__label" for="fiscal-status"><?= e(__('fiscal.status.label')) ?></label>
            <select class="field__control" id="fiscal-status" name="status">
                <option value=""><?= e(__('fiscal.all_statuses')) ?></option>
                <?php foreach ($statuses as $option): ?>
                    <option value="<?= e($option) ?>" <?= $status === $option ? 'selected' : '' ?>><?= e(__('fiscal.status.' . $option)) ?></option>
                <?php endforeach; ?>
                <option value="late" <?= $status === 'late' ? 'selected' : '' ?>><?= e(__('fiscal.status.late')) ?></option>
            </select>
        </div>
        <div class="field">
            <label class="field__label" for="fiscal-horizon"><?= e(__('fiscal.horizon')) ?></label>
            <input class="field__control" type="number" min="0" max="<?= FiscalService::HORIZON_MAX ?>" step="1"
                   id="fiscal-horizon" name="horizon" value="<?= (int) $horizon ?>">
        </div>
        <button class="button button--secondary" type="submit">
            <svg class="icon icon--small" aria-hidden="true"><use href="<?= e(asset('img/icons.svg')) ?>#filter"></use></svg><?= e(__('common.filter')) ?>
        </button>
    </form>
</section>

<section class="card stack-md" aria-labelledby="fiscal-upcoming-title">
    <div class="card-header">
        <div>
            <h2 class="card-header__title" id="fiscal-upcoming-title"><?= e(__('fiscal.upcoming_title')) ?></h2>
            <p class="section-heading__note"><?= e(__('fiscal.upcoming_help', ['days' => (string) $horizon])) ?></p>
        </div>
    </div>
    <?php if ($upcoming === []): ?>
        <p class="table-meta"><?= e(__('fiscal.no_upcoming')) ?></p>
    <?php else: ?>
        <div class="table-wrap">
            <table class="data-table">
                <thead>
                    <tr>
                        <th scope="col"><?= e(__('fiscal.due_on')) ?></th>
                        <th scope="col"><?= e(__('fiscal.kind.label')) ?></th>
                        <th scope="col"><?= e(__('fiscal.label')) ?></th>
                        <th scope="col"><?= e(__('fiscal.status.label')) ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($upcoming as $row): ?>
                        <tr>
                            <td>
                                <?= e($dateLabel((string) $row['due_on'])) ?>
                                <span class="table-meta"><?= e($daysLabel($row)) ?></span>
                            </td>
                            <td><?= e(__('fiscal.kind.' . (string) $row['kind'])) ?></td>
                            <td><?= e((string) $row['label']) ?></td>
                            <td>
                                <span class="badge <?= e($badge((string) $row['display_status'])) ?>">
                                    <?= e(__('fiscal.status.' . (string) $row['display_status'])) ?>
                                </span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>

<section class="card stack-md" aria-labelledby="fiscal-calendar-title">
    <div class="card-header">
        <div>
            <h2 class="card-header__title" id="fiscal-calendar-title"><?= e(__('fiscal.calendar_title')) ?></h2>
            <p class="section-heading__note"><?= e(__('fiscal.calendar_help')) ?></p>
        </div>
    </div>
    <?php if ($rows === []): ?>
        <?php View::partial('partials/empty-state', [
            'title' => __('fiscal.empty_title'),
            'description' => __('fiscal.empty_help'),
        ]) ?>
    <?php else: ?>
        <div class="table-wrap">
            <table class="data-table">
                <thead>
                    <tr>
                        <th scope="col"><?= e(__('fiscal.due_on')) ?></th>
                        <th scope="col"><?= e(__('fiscal.kind.label')) ?></th>
                        <th scope="col"><?= e(__('fiscal.label')) ?></th>
                        <th scope="col"><?= e(__('fiscal.period')) ?></th>
                        <th scope="col"><?= e(__('fiscal.status.label')) ?></th>
                        <th scope="col"><?= e(__('fiscal.amount')) ?></th>
                        <th scope="col"><?= e(__('fiscal.filed_on')) ?></th>
                        <?php if (!empty($canManage)): ?><th scope="col"><?= e(__('common.actions')) ?></th><?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($rows as $row): ?>
                        <?php
                        $display = FiscalService::displayStatus(
                            isset($row['due_on']) ? (string) $row['due_on'] : null,
                            (string) $row['status'],
                            $today
                        );
                        $amount = FiscalService::millimes($row['amount'] ?? '');
                        ?>
                        <tr>
                            <td>
                                <?= e($dateLabel((string) $row['due_on'])) ?>
                                <?php if ($display !== 'paid'): ?>
                                    <span class="table-meta"><?= e($daysLabel($row)) ?></span>
                                <?php endif; ?>
                            </td>
                            <td><?= e(__('fiscal.kind.' . (string) $row['kind'])) ?></td>
                            <td>
                                <strong><?= e((string) $row['label']) ?></strong>
                                <?php if (trim((string) ($row['note'] ?? '')) !== ''): ?>
                                    <span class="table-meta"><?= e((string) $row['note']) ?></span>
                                <?php endif; ?>
                            </td>
                            <td><?= e($dateLabel((string) $row['period_start'])) ?> → <?= e($dateLabel((string) $row['period_end'])) ?></td>
                            <td><span class="badge <?= e($badge($display)) ?>"><?= e(__('fiscal.status.' . $display)) ?></span></td>
                            <td class="u-numeric"><?= e(FiscalService::label($amount)) ?></td>
                            <td>
                                <?= e($dateLabel($row['filed_on'] === null ? null : (string) $row['filed_on'])) ?>
                                <?php if ($row['paid_on'] !== null): ?>
                                    <span class="table-meta"><?= e(__('fiscal.paid_short', ['date' => $dateLabel((string) $row['paid_on'])])) ?></span>
                                <?php endif; ?>
                            </td>
                            <?php if (!empty($canManage)): ?>
                                <td>
                                    <div class="cluster">
                                        <a class="button button--small button--secondary"
                                           href="<?= e(url('/fiscal?' . http_build_query(['year' => (string) $year, 'echeance' => (string) (int) $row['id']]) . '#fiscal-declaration-title')) ?>">
                                            <?= e(__('fiscal.update')) ?>
                                        </a>
                                        <form method="post" action="<?= e(url('/fiscal/echeances/' . (int) $row['id'] . '/supprimer')) ?>"
                                              data-confirm="<?= e(__('fiscal.confirm_delete')) ?>">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="year" value="<?= (int) $year ?>">
                                            <input type="hidden" name="kind_filter" value="<?= e($kind) ?>">
                                            <input type="hidden" name="status_filter" value="<?= e($status) ?>">
                                            <input type="hidden" name="horizon" value="<?= (int) $horizon ?>">
                                            <button class="button button--small button--ghost" type="submit"><?= e(__('common.delete')) ?></button>
                                        </form>
                                    </div>
                                </td>
                            <?php endif; ?>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <p class="table-meta"><?= e(__('fiscal.calendar_note')) ?></p>
    <?php endif; ?>
</section>

<section class="card stack-md" aria-labelledby="fiscal-summary-title">
    <div class="card-header">
        <div>
            <h2 class="card-header__title" id="fiscal-summary-title"><?= e(__('fiscal.summary_title')) ?></h2>
            <p class="section-heading__note"><?= e(__('fiscal.summary_help')) ?></p>
        </div>
    </div>
    <?php if ($byPeriod === []): ?>
        <p class="table-meta"><?= e(__('fiscal.no_summary')) ?></p>
    <?php else: ?>
        <div class="table-wrap">
            <table class="data-table">
                <thead>
                    <tr>
                        <th scope="col"><?= e(__('fiscal.period')) ?></th>
                        <th scope="col"><?= e(__('fiscal.obligations')) ?></th>
                        <th scope="col"><?= e(__('fiscal.stat.paid')) ?></th>
                        <th scope="col"><?= e(__('fiscal.stat.late')) ?></th>
                        <th scope="col"><?= e(__('fiscal.amount')) ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($byPeriod as $period): ?>
                        <tr>
                            <td><?= e($dateLabel((string) $period['start'])) ?> → <?= e($dateLabel((string) $period['end'])) ?></td>
                            <td class="u-numeric"><?= (int) $period['obligations'] ?></td>
                            <td class="u-numeric"><?= (int) $period['paid'] ?></td>
                            <td class="u-numeric">
                                <?php if ((int) $period['late'] > 0): ?>
                                    <span class="badge badge--error"><?= (int) $period['late'] ?></span>
                                <?php else: ?>
                                    <span class="muted">0</span>
                                <?php endif; ?>
                            </td>
                            <td class="u-numeric"><strong><?= e(FiscalService::label((int) $period['amount'])) ?></strong></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr>
                        <th scope="row" colspan="4"><?= e(__('fiscal.totals')) ?></th>
                        <td class="u-numeric"><strong><?= e(FiscalService::label((int) ($summary['amount_filed'] ?? 0))) ?></strong></td>
                    </tr>
                </tfoot>
            </table>
        </div>
        <p class="table-meta"><?= e(__('fiscal.summary_note')) ?></p>
    <?php endif; ?>
</section>

<?php if (!empty($canManage)): ?>
    <section class="card stack-md" aria-labelledby="fiscal-declaration-title">
        <div class="card-header">
            <div>
                <h2 class="card-header__title" id="fiscal-declaration-title"><?= e(__('fiscal.declaration_title')) ?></h2>
                <p class="section-heading__note"><?= e(__('fiscal.declaration_help')) ?></p>
            </div>
        </div>

        <?php if ($all === []): ?>
            <p class="table-meta"><?= e(__('fiscal.declaration_no_echeance')) ?></p>
        <?php else: ?>
            <?php if ($declarationErrors !== []): ?>
                <p class="form-feedback form-feedback--error" role="alert"><?= e(__('fiscal.fix_errors')) ?></p>
            <?php endif; ?>
            <form class="form-grid" method="post" action="<?= e(url('/fiscal/declarations')) ?>">
                <?= csrf_field() ?>
                <input type="hidden" name="year" value="<?= (int) $year ?>">
                <input type="hidden" name="kind_filter" value="<?= e($kind) ?>">
                <input type="hidden" name="status_filter" value="<?= e($status) ?>">
                <input type="hidden" name="horizon" value="<?= (int) $horizon ?>">
                <div class="field form-grid__full">
                    <label class="field__label" for="declaration-echeance"><?= e(__('fiscal.obligation')) ?></label>
                    <select class="field__control" id="declaration-echeance" name="echeance_id" required>
                        <option value=""><?= e(__('common.choose')) ?></option>
                        <?php foreach ($all as $option): ?>
                            <option value="<?= (int) $option['id'] ?>" <?= (int) ($declaration['echeance_id'] ?? $selectedId) === (int) $option['id'] ? 'selected' : '' ?>>
                                <?= e($dateLabel((string) $option['due_on'])) ?> — <?= e((string) $option['label']) ?> (<?= e(__('fiscal.kind.' . (string) $option['kind'])) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <span class="field__hint"><?= e(__('fiscal.obligation_help')) ?></span>
                    <?php if ($errorHint($declarationErrors, 'echeance_id') !== ''): ?><span class="field__hint field__hint--error"><?= e($errorHint($declarationErrors, 'echeance_id')) ?></span><?php endif; ?>
                </div>
                <div class="field">
                    <label class="field__label" for="declaration-status"><?= e(__('fiscal.status.label')) ?></label>
                    <select class="field__control" id="declaration-status" name="status">
                        <?php foreach ($statuses as $option): ?>
                            <option value="<?= e($option) ?>" <?= (string) ($declaration['status'] ?? 'to_prepare') === $option ? 'selected' : '' ?>>
                                <?= e(__('fiscal.status.' . $option)) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <span class="field__hint"><?= e(__('fiscal.status_help')) ?></span>
                </div>
                <div class="field">
                    <label class="field__label" for="declaration-amount"><?= e(__('fiscal.amount')) ?></label>
                    <input class="field__control" type="text" inputmode="decimal" id="declaration-amount" name="amount"
                           value="<?= e((string) ($declaration['amount'] ?? '')) ?>" placeholder="0.000">
                    <span class="field__hint"><?= e(__('fiscal.amount_help')) ?></span>
                    <?php if ($errorHint($declarationErrors, 'amount') !== ''): ?><span class="field__hint field__hint--error"><?= e($errorHint($declarationErrors, 'amount')) ?></span><?php endif; ?>
                </div>
                <div class="field">
                    <label class="field__label" for="declaration-filed"><?= e(__('fiscal.filed_on')) ?></label>
                    <input class="field__control" type="date" id="declaration-filed" name="filed_on"
                           value="<?= e((string) ($declaration['filed_on'] ?? '')) ?>">
                    <span class="field__hint"><?= e(__('fiscal.filed_help')) ?></span>
                </div>
                <div class="field">
                    <label class="field__label" for="declaration-paid"><?= e(__('fiscal.paid_on')) ?></label>
                    <input class="field__control" type="date" id="declaration-paid" name="paid_on"
                           value="<?= e((string) ($declaration['paid_on'] ?? '')) ?>">
                    <span class="field__hint"><?= e(__('fiscal.paid_help')) ?></span>
                    <?php if ($errorHint($declarationErrors, 'paid_on') !== ''): ?><span class="field__hint field__hint--error"><?= e($errorHint($declarationErrors, 'paid_on')) ?></span><?php endif; ?>
                </div>
                <div class="field">
                    <label class="field__label" for="declaration-reference"><?= e(__('fiscal.reference')) ?></label>
                    <input class="field__control" type="text" id="declaration-reference" name="reference" maxlength="120"
                           value="<?= e((string) ($declaration['reference'] ?? '')) ?>">
                    <span class="field__hint"><?= e(__('fiscal.reference_help')) ?></span>
                    <?php if ($errorHint($declarationErrors, 'reference') !== ''): ?><span class="field__hint field__hint--error"><?= e($errorHint($declarationErrors, 'reference')) ?></span><?php endif; ?>
                </div>
                <div class="field form-grid__full">
                    <label class="field__label" for="declaration-note"><?= e(__('fiscal.note')) ?></label>
                    <input class="field__control" type="text" id="declaration-note" name="note" maxlength="500"
                           value="<?= e((string) ($declaration['note'] ?? '')) ?>">
                    <?php if ($errorHint($declarationErrors, 'note') !== ''): ?><span class="field__hint field__hint--error"><?= e($errorHint($declarationErrors, 'note')) ?></span><?php endif; ?>
                </div>
                <div class="form-actions form-grid__full">
                    <button class="button button--primary" type="submit"><?= e(__('fiscal.save_declaration')) ?></button>
                </div>
            </form>
        <?php endif; ?>
    </section>

    <section class="card stack-md" aria-labelledby="fiscal-echeance-title">
        <div class="card-header">
            <div>
                <h2 class="card-header__title" id="fiscal-echeance-title"><?= e(__('fiscal.add_title')) ?></h2>
                <p class="section-heading__note"><?= e(__('fiscal.add_help')) ?></p>
            </div>
        </div>

        <details class="stack-md" <?= $echeanceErrors !== [] ? 'open' : '' ?>>
            <summary class="text-callout"><?= e(__('fiscal.add_echeance')) ?></summary>
            <?php if ($echeanceErrors !== []): ?>
                <p class="form-feedback form-feedback--error" role="alert"><?= e(__('fiscal.fix_errors')) ?></p>
            <?php endif; ?>
            <form class="form-grid" method="post" action="<?= e(url('/fiscal/echeances')) ?>">
                <?= csrf_field() ?>
                <input type="hidden" name="year" value="<?= (int) $year ?>">
                <input type="hidden" name="kind_filter" value="<?= e($kind) ?>">
                <input type="hidden" name="status_filter" value="<?= e($status) ?>">
                <input type="hidden" name="horizon" value="<?= (int) $horizon ?>">
                <div class="field">
                    <label class="field__label" for="echeance-kind"><?= e(__('fiscal.kind.label')) ?></label>
                    <select class="field__control" id="echeance-kind" name="kind">
                        <?php foreach ($kinds as $option): ?>
                            <option value="<?= e($option) ?>" <?= (string) ($echeanceValues['kind'] ?? 'vat') === $option ? 'selected' : '' ?>>
                                <?= e(__('fiscal.kind.' . $option)) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="field">
                    <label class="field__label" for="echeance-label"><?= e(__('fiscal.label')) ?></label>
                    <input class="field__control" type="text" id="echeance-label" name="label" maxlength="150" required
                           value="<?= e((string) ($echeanceValues['label'] ?? '')) ?>" placeholder="<?= e(__('fiscal.label_placeholder')) ?>">
                    <?php if ($errorHint($echeanceErrors, 'label') !== ''): ?><span class="field__hint field__hint--error"><?= e($errorHint($echeanceErrors, 'label')) ?></span><?php endif; ?>
                </div>
                <div class="field">
                    <label class="field__label" for="echeance-period-start"><?= e(__('fiscal.period_start')) ?></label>
                    <input class="field__control" type="date" id="echeance-period-start" name="period_start" required
                           value="<?= e((string) ($echeanceValues['period_start'] ?? '')) ?>">
                    <?php if ($errorHint($echeanceErrors, 'period_start') !== ''): ?><span class="field__hint field__hint--error"><?= e($errorHint($echeanceErrors, 'period_start')) ?></span><?php endif; ?>
                </div>
                <div class="field">
                    <label class="field__label" for="echeance-period-end"><?= e(__('fiscal.period_end')) ?></label>
                    <input class="field__control" type="date" id="echeance-period-end" name="period_end" required
                           value="<?= e((string) ($echeanceValues['period_end'] ?? '')) ?>">
                    <?php if ($errorHint($echeanceErrors, 'period_end') !== ''): ?><span class="field__hint field__hint--error"><?= e($errorHint($echeanceErrors, 'period_end')) ?></span><?php endif; ?>
                </div>
                <div class="field">
                    <label class="field__label" for="echeance-due"><?= e(__('fiscal.due_on')) ?></label>
                    <input class="field__control" type="date" id="echeance-due" name="due_on" required
                           value="<?= e((string) ($echeanceValues['due_on'] ?? '')) ?>">
                    <span class="field__hint"><?= e(__('fiscal.due_help')) ?></span>
                    <?php if ($errorHint($echeanceErrors, 'due_on') !== ''): ?><span class="field__hint field__hint--error"><?= e($errorHint($echeanceErrors, 'due_on')) ?></span><?php endif; ?>
                </div>
                <div class="field">
                    <label class="field__label" for="echeance-note"><?= e(__('fiscal.note')) ?></label>
                    <input class="field__control" type="text" id="echeance-note" name="note" maxlength="500"
                           value="<?= e((string) ($echeanceValues['note'] ?? '')) ?>">
                    <?php if ($errorHint($echeanceErrors, 'note') !== ''): ?><span class="field__hint field__hint--error"><?= e($errorHint($echeanceErrors, 'note')) ?></span><?php endif; ?>
                </div>
                <div class="form-actions form-grid__full">
                    <button class="button button--primary" type="submit"><?= e(__('fiscal.add_submit')) ?></button>
                </div>
            </form>
        </details>
    </section>
<?php endif; ?>
