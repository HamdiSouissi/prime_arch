<?php
declare(strict_types=1);

use App\Core\Auth;
use App\Core\View;
use Modules\CrmOpportunites\Services\CrmService;

$rows = is_array($rows ?? null) ? $rows : [];
$statistics = is_array($statistics ?? null) ? $statistics : [];
$stages = is_array($stages ?? null) ? $stages : CrmService::STAGES;
$origins = is_array($origins ?? null) ? $origins : CrmService::ORIGINS;
$owners = is_array($owners ?? null) ? $owners : [];
$filters = is_array($filters ?? null) ? $filters : [];
$opportunityErrors = is_array($opportunityErrors ?? null) ? $opportunityErrors : [];
$opportunityValues = is_array($opportunityValues ?? null) ? $opportunityValues : [];

$dateLabel = static function (?string $value): string {
    $timestamp = $value !== null && $value !== '' ? strtotime($value) : false;

    return $timestamp === false ? '—' : date('d/m/Y', $timestamp);
};
$errorHint = static fn (array $errors, string $field): string => (string) ($errors[$field] ?? '');
$orDash = static fn (string $value): string => trim($value) !== '' ? $value : '—';
$stageBadge = static fn (string $stage): string => match ($stage) {
    'won' => 'badge--success',
    'lost' => 'badge--error',
    'proposal', 'negotiation' => 'badge--warning',
    'contact', 'meeting' => 'badge--info',
    default => 'badge--neutral',
};
$followBadge = static fn (string $state): string => match ($state) {
    'late' => 'badge--error',
    'today' => 'badge--warning',
    'upcoming' => 'badge--info',
    default => 'badge--neutral',
};
$followLabel = static function (array $row): string {
    $state = (string) ($row['follow_state'] ?? 'none');
    $days = $row['follow_days'] ?? null;
    if ($state === 'none' || $days === null) {
        return __('crm.follow.none');
    }
    if ((int) $days < 0) {
        return __('crm.follow.late_by', ['days' => (string) abs((int) $days)]);
    }

    return (int) $days === 0 ? __('crm.follow.today') : __('crm.follow.in_days', ['days' => (string) $days]);
};
$values = $opportunityValues;
$formStage = (string) ($values['stage'] ?? 'new');
$byStage = is_array($statistics['by_stage'] ?? null) ? $statistics['by_stage'] : [];
$exportQuery = array_filter([
    'stage' => (string) ($filters['stage'] ?? ''),
    'origin' => (string) ($filters['origin'] ?? ''),
    'owner' => (string) ($filters['owner_id'] ?? ''),
    'follow' => (string) ($filters['follow'] ?? ''),
    'q' => (string) ($filters['search'] ?? ''),
], static fn (string $value): bool => $value !== '');
$filterFields = [
    'stage_filter' => (string) ($filters['stage'] ?? ''),
    'origin_filter' => (string) ($filters['origin'] ?? ''),
    'owner_filter' => (string) ($filters['owner_id'] ?? ''),
    'follow_filter' => (string) ($filters['follow'] ?? ''),
    'q_filter' => (string) ($filters['search'] ?? ''),
];
?>
<?php View::partial('partials/page-header', [
    'pageTitle' => __('crm.title'),
    'eyebrow' => __('crm.eyebrow'),
    'subtitle' => __('crm.intro'),
    'secondaryAction' => [
        'label' => __('crm.export'),
        'icon' => 'download',
        'url' => url('/crm/export' . ($exportQuery !== [] ? '?' . http_build_query($exportQuery) : '')),
    ],
]) ?>

<?php if (!empty($notice)): ?><p class="form-feedback form-feedback--success" role="status"><?= e($notice) ?></p><?php endif; ?>
<?php if (!empty($error)): ?><p class="form-feedback form-feedback--error" role="alert"><?= e($error) ?></p><?php endif; ?>

<p class="form-feedback form-feedback--info" role="note"><?= e(__('crm.guardrail')) ?></p>

<section class="stats-grid" aria-label="<?= e(__('crm.statistics')) ?>">
    <?php View::partial('partials/stat-card', [
        'label' => __('crm.stat.open'),
        'value' => (string) ($statistics['open'] ?? 0),
        'icon' => 'briefcase',
        'tone' => 'blue',
        'note' => __('crm.stat.total_note', ['count' => (string) ($statistics['total'] ?? 0)]),
    ]) ?>
    <?php View::partial('partials/stat-card', [
        'label' => __('crm.stat.pipeline'),
        'value' => CrmService::label((int) ($statistics['pipeline'] ?? 0)),
        'icon' => 'currency',
        'tone' => 'mint',
        'note' => __('crm.stat.pipeline_note'),
    ]) ?>
    <?php View::partial('partials/stat-card', [
        'label' => __('crm.stat.late'),
        'value' => (string) ($statistics['late'] ?? 0),
        'icon' => 'clock',
        'tone' => 'coral',
        'note' => __('crm.stat.late_note', ['count' => (string) ($statistics['today'] ?? 0)]),
    ]) ?>
    <?php View::partial('partials/stat-card', [
        'label' => __('crm.stat.won'),
        'value' => CrmService::label((int) ($statistics['won_amount'] ?? 0)),
        'icon' => 'check-circle',
        'tone' => 'pink',
        'note' => __('crm.stat.won_note', ['count' => (string) ($statistics['won'] ?? 0)]),
    ]) ?>
</section>

<section class="card stack-md" aria-labelledby="crm-filter-title">
    <div class="card-header">
        <div>
            <h2 class="card-header__title" id="crm-filter-title"><?= e(__('crm.filter_title')) ?></h2>
            <p class="section-heading__note"><?= e(__('crm.filter_help')) ?></p>
        </div>
    </div>
    <form class="filter-row" method="get" action="<?= e(url('/crm')) ?>">
        <div class="field">
            <label class="field__label" for="crm-filter-stage"><?= e(__('crm.stage.label')) ?></label>
            <select class="field__control" id="crm-filter-stage" name="stage">
                <option value=""><?= e(__('crm.all_stages')) ?></option>
                <?php foreach ($stages as $option): ?>
                    <option value="<?= e($option) ?>" <?= (string) ($filters['stage'] ?? '') === $option ? 'selected' : '' ?>><?= e(__('crm.stage.' . $option)) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="field">
            <label class="field__label" for="crm-filter-origin"><?= e(__('crm.origin.label')) ?></label>
            <select class="field__control" id="crm-filter-origin" name="origin">
                <option value=""><?= e(__('crm.all_origins')) ?></option>
                <?php foreach ($origins as $option): ?>
                    <option value="<?= e($option) ?>" <?= (string) ($filters['origin'] ?? '') === $option ? 'selected' : '' ?>><?= e(__('crm.origin.' . $option)) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="field">
            <label class="field__label" for="crm-filter-owner"><?= e(__('crm.owner')) ?></label>
            <select class="field__control" id="crm-filter-owner" name="owner">
                <option value=""><?= e(__('crm.all_owners')) ?></option>
                <option value="none" <?= (string) ($filters['owner_id'] ?? '') === 'none' ? 'selected' : '' ?>><?= e(__('crm.unassigned')) ?></option>
                <?php foreach ($owners as $owner): ?>
                    <option value="<?= (int) $owner['id'] ?>" <?= (string) ($filters['owner_id'] ?? '') === (string) (int) $owner['id'] ? 'selected' : '' ?>><?= e((string) $owner['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="field">
            <label class="field__label" for="crm-filter-follow"><?= e(__('crm.follow.label')) ?></label>
            <select class="field__control" id="crm-filter-follow" name="follow">
                <option value=""><?= e(__('crm.all_follows')) ?></option>
                <option value="late" <?= (string) ($filters['follow'] ?? '') === 'late' ? 'selected' : '' ?>><?= e(__('crm.follow.filter_late')) ?></option>
                <option value="today" <?= (string) ($filters['follow'] ?? '') === 'today' ? 'selected' : '' ?>><?= e(__('crm.follow.filter_today')) ?></option>
                <option value="week" <?= (string) ($filters['follow'] ?? '') === 'week' ? 'selected' : '' ?>><?= e(__('crm.follow.filter_week')) ?></option>
                <option value="none" <?= (string) ($filters['follow'] ?? '') === 'none' ? 'selected' : '' ?>><?= e(__('crm.follow.filter_none')) ?></option>
            </select>
        </div>
        <div class="field">
            <label class="field__label" for="crm-filter-search"><?= e(__('crm.search')) ?></label>
            <input class="field__control" type="search" id="crm-filter-search" name="q" maxlength="120"
                   value="<?= e((string) ($filters['search'] ?? '')) ?>" placeholder="<?= e(__('crm.search_placeholder')) ?>">
        </div>
        <button class="button button--secondary" type="submit">
            <svg class="icon icon--small" aria-hidden="true"><use href="<?= e(asset('img/icons.svg')) ?>#filter"></use></svg><?= e(__('common.filter')) ?>
        </button>
        <a class="button button--ghost" href="<?= e(url('/crm')) ?>"><?= e(__('common.clear')) ?></a>
    </form>
</section>

<section class="card stack-md" aria-labelledby="crm-list-title">
    <div class="card-header">
        <div>
            <h2 class="card-header__title" id="crm-list-title"><?= e(__('crm.list_title')) ?></h2>
            <p class="section-heading__note"><?= e(__('crm.list_help')) ?></p>
        </div>
        <span class="badge badge--neutral"><?= e(__('common.results', ['count' => (string) count($rows)])) ?></span>
    </div>
    <?php if ($rows === []): ?>
        <?php View::partial('partials/empty-state', [
            'title' => __('crm.empty_title'),
            'description' => __('crm.empty_help'),
        ]) ?>
    <?php else: ?>
        <div class="table-wrap">
            <table class="data-table">
                <thead>
                    <tr>
                        <th scope="col"><?= e(__('crm.opportunity')) ?></th>
                        <th scope="col"><?= e(__('crm.stage.label')) ?></th>
                        <th scope="col"><?= e(__('crm.estimated_amount')) ?></th>
                        <th scope="col"><?= e(__('crm.follow.label')) ?></th>
                        <th scope="col"><?= e(__('crm.owner')) ?></th>
                        <th scope="col"><?= e(__('crm.client')) ?></th>
                        <th scope="col"><?= e(__('common.actions')) ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($rows as $row): ?>
                        <tr>
                            <td>
                                <strong><?= e((string) $row['title']) ?></strong>
                                <span class="table-meta">
                                    <?= e((string) $row['prospect_name']) ?> ·
                                    <?= e(__('crm.client_type.' . (string) $row['client_type'])) ?><?php if (trim((string) ($row['city'] ?? '')) !== ''): ?> · <?= e((string) $row['city']) ?><?php endif; ?>
                                </span>
                                <span class="table-meta"><?= e(__('crm.origin.' . (string) $row['origin'])) ?></span>
                            </td>
                            <td>
                                <span class="badge <?= e($stageBadge((string) $row['stage'])) ?>"><?= e(__('crm.stage.' . (string) $row['stage'])) ?></span>
                                <?php if (($row['lost_reason'] ?? null) !== null && trim((string) $row['lost_reason']) !== ''): ?>
                                    <span class="table-meta"><?= e((string) $row['lost_reason']) ?></span>
                                <?php endif; ?>
                            </td>
                            <td class="u-numeric">
                                <?php if (($row['estimated_amount'] ?? null) !== null): ?>
                                    <?= e(money_format_tnd((string) $row['estimated_amount'])) ?>
                                <?php else: ?>
                                    <span class="muted">—</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if (($row['next_action_on'] ?? null) !== null): ?>
                                    <?= e($dateLabel((string) $row['next_action_on'])) ?>
                                    <span class="badge <?= e($followBadge((string) $row['follow_state'])) ?>"><?= e($followLabel($row)) ?></span>
                                <?php else: ?>
                                    <span class="muted"><?= e(__('crm.follow.none')) ?></span>
                                <?php endif; ?>
                            </td>
                            <td><?= e($orDash((string) ($row['owner_name'] ?? ''))) ?></td>
                            <td>
                                <?php if (($row['client_id'] ?? null) !== null): ?>
                                    <?php if (Auth::can('clients.view')): ?>
                                        <a href="<?= e(url('/clients/' . (int) $row['client_id'])) ?>"><?= e((string) ($row['client_name'] ?? '')) ?></a>
                                    <?php else: ?>
                                        <?= e((string) ($row['client_name'] ?? '')) ?>
                                    <?php endif; ?>
                                    <span class="table-meta"><?= e($orDash((string) ($row['client_reference'] ?? ''))) ?></span>
                                <?php else: ?>
                                    <span class="muted"><?= e(__('crm.not_linked')) ?></span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="cluster">
                                    <a class="button button--small button--secondary" href="<?= e(url('/crm/opportunites/' . (int) $row['id'])) ?>"><?= e(__('crm.open')) ?></a>
                                    <?php if (!empty($canManage)): ?>
                                        <form method="post" action="<?= e(url('/crm/opportunites/' . (int) $row['id'] . '/supprimer')) ?>"
                                              data-confirm="<?= e(__('crm.confirm_delete')) ?>">
                                            <?= csrf_field() ?>
                                            <?php foreach ($filterFields as $filterName => $filterValue): ?>
                                                <input type="hidden" name="<?= e($filterName) ?>" value="<?= e($filterValue) ?>">
                                            <?php endforeach; ?>
                                            <button class="button button--small button--ghost" type="submit"><?= e(__('common.delete')) ?></button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <p class="table-meta"><?= e(__('crm.list_note')) ?></p>
    <?php endif; ?>
</section>

<section class="card stack-md" aria-labelledby="crm-stages-title">
    <div class="card-header">
        <div>
            <h2 class="card-header__title" id="crm-stages-title"><?= e(__('crm.stage_summary_title')) ?></h2>
            <p class="section-heading__note"><?= e(__('crm.stage_summary_help')) ?></p>
        </div>
    </div>
    <div class="table-wrap">
        <table class="data-table">
            <thead>
                <tr>
                    <th scope="col"><?= e(__('crm.stage.label')) ?></th>
                    <th scope="col"><?= e(__('crm.count')) ?></th>
                    <th scope="col"><?= e(__('crm.estimated_amount')) ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($stages as $stageKey): ?>
                    <?php $entry = is_array($byStage[$stageKey] ?? null) ? $byStage[$stageKey] : ['count' => 0, 'amount' => 0]; ?>
                    <tr>
                        <td><span class="badge <?= e($stageBadge($stageKey)) ?>"><?= e(__('crm.stage.' . $stageKey)) ?></span></td>
                        <td class="u-numeric"><?= (int) $entry['count'] ?></td>
                        <td class="u-numeric"><?= e(CrmService::label((int) $entry['amount'])) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
            <tfoot>
                <tr>
                    <th scope="row"><?= e(__('crm.totals')) ?></th>
                    <td class="u-numeric"><strong><?= (int) ($statistics['total'] ?? 0) ?></strong></td>
                    <td class="u-numeric"><strong><?= e(CrmService::label((int) ($statistics['pipeline'] ?? 0) + (int) ($statistics['won_amount'] ?? 0))) ?></strong></td>
                </tr>
            </tfoot>
        </table>
    </div>
    <p class="table-meta"><?= e(__('crm.stage_summary_note')) ?></p>
</section>

<?php if (!empty($canManage)): ?>
    <section class="card stack-md" aria-labelledby="crm-new-title">
        <div class="card-header">
            <div>
                <h2 class="card-header__title" id="crm-new-title"><?= e(__('crm.add_title')) ?></h2>
                <p class="section-heading__note"><?= e(__('crm.add_help')) ?></p>
            </div>
        </div>

        <details class="stack-md" <?= $opportunityErrors !== [] ? 'open' : '' ?>>
            <summary class="text-callout"><?= e(__('crm.new_opportunity')) ?></summary>
            <?php if ($opportunityErrors !== []): ?>
                <p class="form-feedback form-feedback--error" role="alert"><?= e(__('crm.fix_errors')) ?></p>
            <?php endif; ?>
            <form class="form-grid" method="post" action="<?= e(url('/crm/opportunites')) ?>">
                <?= csrf_field() ?>
                <?php foreach ($filterFields as $filterName => $filterValue): ?>
                    <input type="hidden" name="<?= e($filterName) ?>" value="<?= e($filterValue) ?>">
                <?php endforeach; ?>
                <div class="field">
                    <label class="field__label" for="crm-new-title-field"><?= e(__('crm.title_label')) ?></label>
                    <input class="field__control" type="text" id="crm-new-title-field" name="title" maxlength="180" required
                           value="<?= e((string) ($values['title'] ?? '')) ?>" placeholder="<?= e(__('crm.title_placeholder')) ?>">
                    <?php if ($errorHint($opportunityErrors, 'title') !== ''): ?><span class="field__hint field__hint--error"><?= e($errorHint($opportunityErrors, 'title')) ?></span><?php endif; ?>
                </div>
                <div class="field">
                    <label class="field__label" for="crm-new-prospect"><?= e(__('crm.prospect_name')) ?></label>
                    <input class="field__control" type="text" id="crm-new-prospect" name="prospect_name" maxlength="180" required
                           value="<?= e((string) ($values['prospect_name'] ?? '')) ?>">
                    <?php if ($errorHint($opportunityErrors, 'prospect_name') !== ''): ?><span class="field__hint field__hint--error"><?= e($errorHint($opportunityErrors, 'prospect_name')) ?></span><?php endif; ?>
                </div>
                <div class="field">
                    <label class="field__label" for="crm-new-type"><?= e(__('crm.client_type.label')) ?></label>
                    <select class="field__control" id="crm-new-type" name="client_type">
                        <?php foreach (CrmService::CLIENT_TYPES as $option): ?>
                            <option value="<?= e($option) ?>" <?= (string) ($values['client_type'] ?? 'individual') === $option ? 'selected' : '' ?>><?= e(__('crm.client_type.' . $option)) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="field">
                    <label class="field__label" for="crm-new-origin"><?= e(__('crm.origin.label')) ?></label>
                    <select class="field__control" id="crm-new-origin" name="origin">
                        <?php foreach ($origins as $option): ?>
                            <option value="<?= e($option) ?>" <?= (string) ($values['origin'] ?? 'other') === $option ? 'selected' : '' ?>><?= e(__('crm.origin.' . $option)) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="field">
                    <label class="field__label" for="crm-new-email"><?= e(__('crm.email')) ?></label>
                    <input class="field__control" type="email" id="crm-new-email" name="email" maxlength="190"
                           value="<?= e((string) ($values['email'] ?? '')) ?>">
                    <?php if ($errorHint($opportunityErrors, 'email') !== ''): ?><span class="field__hint field__hint--error"><?= e($errorHint($opportunityErrors, 'email')) ?></span><?php endif; ?>
                </div>
                <div class="field">
                    <label class="field__label" for="crm-new-phone"><?= e(__('crm.phone')) ?></label>
                    <input class="field__control" type="tel" id="crm-new-phone" name="phone" maxlength="32"
                           value="<?= e((string) ($values['phone'] ?? '')) ?>">
                    <?php if ($errorHint($opportunityErrors, 'phone') !== ''): ?><span class="field__hint field__hint--error"><?= e($errorHint($opportunityErrors, 'phone')) ?></span><?php endif; ?>
                </div>
                <div class="field">
                    <label class="field__label" for="crm-new-city"><?= e(__('crm.city')) ?></label>
                    <input class="field__control" type="text" id="crm-new-city" name="city" maxlength="120"
                           value="<?= e((string) ($values['city'] ?? '')) ?>">
                    <?php if ($errorHint($opportunityErrors, 'city') !== ''): ?><span class="field__hint field__hint--error"><?= e($errorHint($opportunityErrors, 'city')) ?></span><?php endif; ?>
                </div>
                <div class="field">
                    <label class="field__label" for="crm-new-stage"><?= e(__('crm.stage.label')) ?></label>
                    <select class="field__control" id="crm-new-stage" name="stage">
                        <?php foreach ($stages as $option): ?>
                            <option value="<?= e($option) ?>" <?= $formStage === $option ? 'selected' : '' ?>><?= e(__('crm.stage.' . $option)) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <span class="field__hint"><?= e(__('crm.stage_help')) ?></span>
                    <?php if ($errorHint($opportunityErrors, 'stage') !== ''): ?><span class="field__hint field__hint--error"><?= e($errorHint($opportunityErrors, 'stage')) ?></span><?php endif; ?>
                </div>
                <div class="field">
                    <label class="field__label" for="crm-new-amount"><?= e(__('crm.estimated_amount')) ?></label>
                    <input class="field__control" type="text" inputmode="decimal" id="crm-new-amount" name="estimated_amount"
                           value="<?= e((string) ($values['estimated_amount'] ?? '')) ?>" placeholder="0.000">
                    <span class="field__hint"><?= e(__('crm.amount_help')) ?></span>
                    <?php if ($errorHint($opportunityErrors, 'estimated_amount') !== ''): ?><span class="field__hint field__hint--error"><?= e($errorHint($opportunityErrors, 'estimated_amount')) ?></span><?php endif; ?>
                </div>
                <div class="field">
                    <label class="field__label" for="crm-new-close"><?= e(__('crm.expected_close_on')) ?></label>
                    <input class="field__control" type="date" id="crm-new-close" name="expected_close_on"
                           value="<?= e((string) ($values['expected_close_on'] ?? '')) ?>">
                    <?php if ($errorHint($opportunityErrors, 'expected_close_on') !== ''): ?><span class="field__hint field__hint--error"><?= e($errorHint($opportunityErrors, 'expected_close_on')) ?></span><?php endif; ?>
                </div>
                <div class="field">
                    <label class="field__label" for="crm-new-follow"><?= e(__('crm.next_action_on')) ?></label>
                    <input class="field__control" type="date" id="crm-new-follow" name="next_action_on"
                           value="<?= e((string) ($values['next_action_on'] ?? '')) ?>">
                    <span class="field__hint"><?= e(__('crm.next_action_help')) ?></span>
                    <?php if ($errorHint($opportunityErrors, 'next_action_on') !== ''): ?><span class="field__hint field__hint--error"><?= e($errorHint($opportunityErrors, 'next_action_on')) ?></span><?php endif; ?>
                </div>
                <div class="field">
                    <label class="field__label" for="crm-new-owner"><?= e(__('crm.owner')) ?></label>
                    <select class="field__control" id="crm-new-owner" name="owner_id">
                        <option value=""><?= e(__('crm.unassigned')) ?></option>
                        <?php foreach ($owners as $owner): ?>
                            <option value="<?= (int) $owner['id'] ?>" <?= (string) ($values['owner_id'] ?? '') === (string) (int) $owner['id'] ? 'selected' : '' ?>><?= e((string) $owner['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <?php if ($errorHint($opportunityErrors, 'owner_id') !== ''): ?><span class="field__hint field__hint--error"><?= e($errorHint($opportunityErrors, 'owner_id')) ?></span><?php endif; ?>
                </div>
                <div class="field">
                    <label class="field__label" for="crm-new-lost"><?= e(__('crm.lost_reason')) ?></label>
                    <input class="field__control" type="text" id="crm-new-lost" name="lost_reason" maxlength="500"
                           value="<?= e((string) ($values['lost_reason'] ?? '')) ?>">
                    <span class="field__hint"><?= e(__('crm.lost_reason_help')) ?></span>
                    <?php if ($errorHint($opportunityErrors, 'lost_reason') !== ''): ?><span class="field__hint field__hint--error"><?= e($errorHint($opportunityErrors, 'lost_reason')) ?></span><?php endif; ?>
                </div>
                <div class="field form-grid__full">
                    <label class="field__label" for="crm-new-notes"><?= e(__('crm.notes')) ?></label>
                    <input class="field__control" type="text" id="crm-new-notes" name="notes" maxlength="1000"
                           value="<?= e((string) ($values['notes'] ?? '')) ?>">
                    <?php if ($errorHint($opportunityErrors, 'notes') !== ''): ?><span class="field__hint field__hint--error"><?= e($errorHint($opportunityErrors, 'notes')) ?></span><?php endif; ?>
                </div>
                <div class="form-actions form-grid__full">
                    <button class="button button--primary" type="submit"><?= e(__('crm.add_submit')) ?></button>
                </div>
            </form>
        </details>
    </section>
<?php endif; ?>
