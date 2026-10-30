<?php
declare(strict_types=1);

use App\Core\Auth;
use App\Core\View;
use Modules\CrmOpportunites\Services\CrmService;

$row = is_array($row ?? null) ? $row : [];
$activities = is_array($activities ?? null) ? $activities : [];
$stages = is_array($stages ?? null) ? $stages : CrmService::STAGES;
$origins = is_array($origins ?? null) ? $origins : CrmService::ORIGINS;
$activityKinds = is_array($activityKinds ?? null) ? $activityKinds : CrmService::ACTIVITY_KINDS;
$owners = is_array($owners ?? null) ? $owners : [];
$clients = is_array($clients ?? null) ? $clients : [];
$projects = is_array($projects ?? null) ? $projects : [];
$projectClients = is_array($projectClients ?? null) ? $projectClients : [];

$stageErrors = is_array($stageErrors ?? null) ? $stageErrors : [];
$opportunityErrors = is_array($opportunityErrors ?? null) ? $opportunityErrors : [];
$opportunityValues = is_array($opportunityValues ?? null) ? $opportunityValues : [];
$conversionErrors = is_array($conversionErrors ?? null) ? $conversionErrors : [];
$conversionValues = is_array($conversionValues ?? null) ? $conversionValues : [];
$activityErrors = is_array($activityErrors ?? null) ? $activityErrors : [];
$activityValues = is_array($activityValues ?? null) ? $activityValues : [];
$today = (string) ($today ?? date('Y-m-d'));

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
$followLabel = static function (array $item): string {
    $state = (string) ($item['follow_state'] ?? 'none');
    $days = $item['follow_days'] ?? null;
    if ($state === 'none' || $days === null) {
        return __('crm.follow.none');
    }
    if ((int) $days < 0) {
        return __('crm.follow.late_by', ['days' => (string) abs((int) $days)]);
    }

    return (int) $days === 0 ? __('crm.follow.today') : __('crm.follow.in_days', ['days' => (string) $days]);
};
$values = $opportunityValues !== [] ? $opportunityValues : [
    'title' => (string) ($row['title'] ?? ''),
    'prospect_name' => (string) ($row['prospect_name'] ?? ''),
    'client_type' => (string) ($row['client_type'] ?? 'individual'),
    'email' => (string) ($row['email'] ?? ''),
    'phone' => (string) ($row['phone'] ?? ''),
    'city' => (string) ($row['city'] ?? ''),
    'origin' => (string) ($row['origin'] ?? 'other'),
    'stage' => (string) ($row['stage'] ?? 'new'),
    'estimated_amount' => (string) ($row['estimated_amount'] ?? ''),
    'expected_close_on' => (string) ($row['expected_close_on'] ?? ''),
    'next_action_on' => (string) ($row['next_action_on'] ?? ''),
    'owner_id' => (string) ($row['owner_id'] ?? ''),
    'lost_reason' => (string) ($row['lost_reason'] ?? ''),
    'notes' => (string) ($row['notes'] ?? ''),
];
$conversion = $conversionValues !== [] ? $conversionValues : [
    'client_id' => (string) ($row['client_id'] ?? ''),
    'project_id' => (string) ($row['project_id'] ?? ''),
    'mark_won' => (string) ($row['stage'] ?? '') === 'won' ? '1' : '0',
];
$activity = $activityValues !== [] ? $activityValues : [
    'kind' => 'call',
    'occurred_on' => $today,
    'summary' => '',
    'detail' => '',
];
$stageValue = (string) ($stageErrors === [] ? ($row['stage'] ?? 'new') : ($values['stage'] ?? 'new'));
$activityCount = count($activities);
?>
<?php View::partial('partials/page-header', [
    'pageTitle' => (string) ($row['title'] ?? ''),
    'eyebrow' => __('crm.detail_eyebrow'),
    'subtitle' => implode(' · ', array_filter([
        (string) ($row['prospect_name'] ?? ''),
        __('crm.client_type.' . (string) ($row['client_type'] ?? 'individual')),
        (string) ($row['city'] ?? ''),
    ], static fn (string $part): bool => trim($part) !== '')),
    'secondaryAction' => [
        'label' => __('crm.back_to_list'),
        'icon' => 'arrow-left',
        'url' => url('/crm'),
    ],
]) ?>

<?php if (!empty($notice)): ?><p class="form-feedback form-feedback--success" role="status"><?= e($notice) ?></p><?php endif; ?>
<?php if (!empty($error)): ?><p class="form-feedback form-feedback--error" role="alert"><?= e($error) ?></p><?php endif; ?>

<section class="card stack-md" aria-labelledby="crm-detail-title">
    <div class="card-header">
        <div>
            <h2 class="card-header__title" id="crm-detail-title"><?= e(__('crm.detail_title')) ?></h2>
            <p class="section-heading__note"><?= e(__('crm.detail_help')) ?></p>
        </div>
        <div class="cluster">
            <span class="badge <?= e($stageBadge((string) ($row['stage'] ?? 'new'))) ?>"><?= e(__('crm.stage.' . (string) ($row['stage'] ?? 'new'))) ?></span>
            <span class="badge badge--neutral"><?= e(__('crm.origin.' . (string) ($row['origin'] ?? 'other'))) ?></span>
        </div>
    </div>

    <dl class="document-summary-grid">
        <div>
            <dt class="detail-label"><?= e(__('crm.prospect_name')) ?></dt>
            <dd><?= e($orDash((string) ($row['prospect_name'] ?? ''))) ?></dd>
        </div>
        <div>
            <dt class="detail-label"><?= e(__('crm.client_type.label')) ?></dt>
            <dd><?= e(__('crm.client_type.' . (string) ($row['client_type'] ?? 'individual'))) ?></dd>
        </div>
        <div>
            <dt class="detail-label"><?= e(__('crm.email')) ?></dt>
            <dd>
                <?php if (trim((string) ($row['email'] ?? '')) !== ''): ?>
                    <a href="mailto:<?= e((string) $row['email']) ?>"><?= e((string) $row['email']) ?></a>
                <?php else: ?>
                    <span class="muted">—</span>
                <?php endif; ?>
            </dd>
        </div>
        <div>
            <dt class="detail-label"><?= e(__('crm.phone')) ?></dt>
            <dd>
                <?php if (trim((string) ($row['phone'] ?? '')) !== ''): ?>
                    <a href="tel:<?= e((string) $row['phone']) ?>"><?= e((string) $row['phone']) ?></a>
                <?php else: ?>
                    <span class="muted">—</span>
                <?php endif; ?>
            </dd>
        </div>
        <div>
            <dt class="detail-label"><?= e(__('crm.city')) ?></dt>
            <dd><?= e($orDash((string) ($row['city'] ?? ''))) ?></dd>
        </div>
        <div>
            <dt class="detail-label"><?= e(__('crm.estimated_amount')) ?></dt>
            <dd class="u-numeric">
                <?php if (($row['estimated_amount'] ?? null) !== null): ?>
                    <?= e(money_format_tnd((string) $row['estimated_amount'])) ?>
                <?php else: ?>
                    <span class="muted">—</span>
                <?php endif; ?>
            </dd>
        </div>
        <div>
            <dt class="detail-label"><?= e(__('crm.expected_close_on')) ?></dt>
            <dd><?= e($dateLabel(isset($row['expected_close_on']) ? (string) $row['expected_close_on'] : null)) ?></dd>
        </div>
        <div>
            <dt class="detail-label"><?= e(__('crm.next_action_on')) ?></dt>
            <dd>
                <?= e($dateLabel(isset($row['next_action_on']) ? (string) $row['next_action_on'] : null)) ?>
                <?php if (($row['next_action_on'] ?? null) !== null): ?>
                    <span class="badge <?= e($followBadge((string) ($row['follow_state'] ?? 'none'))) ?>"><?= e($followLabel($row)) ?></span>
                <?php endif; ?>
            </dd>
        </div>
        <div>
            <dt class="detail-label"><?= e(__('crm.owner')) ?></dt>
            <dd><?= e($orDash((string) ($row['owner_name'] ?? ''))) ?></dd>
        </div>
        <div>
            <dt class="detail-label"><?= e(__('crm.client')) ?></dt>
            <dd>
                <?php if (($row['client_id'] ?? null) !== null): ?>
                    <?php if (Auth::can('clients.view')): ?>
                        <a href="<?= e(url('/clients/' . (int) $row['client_id'])) ?>"><?= e((string) ($row['client_name'] ?? '')) ?></a>
                    <?php else: ?>
                        <?= e((string) ($row['client_name'] ?? '')) ?>
                    <?php endif; ?>
                    <span class="table-meta"><?= e((string) ($row['client_reference'] ?? '')) ?></span>
                <?php else: ?>
                    <span class="muted"><?= e(__('crm.not_linked')) ?></span>
                <?php endif; ?>
            </dd>
        </div>
        <div>
            <dt class="detail-label"><?= e(__('crm.project')) ?></dt>
            <dd>
                <?php if (($row['project_id'] ?? null) !== null): ?>
                    <?php if (Auth::can('projects.view')): ?>
                        <a href="<?= e(url('/projects/' . (int) $row['project_id'])) ?>"><?= e((string) ($row['project_name'] ?? '')) ?></a>
                    <?php else: ?>
                        <?= e((string) ($row['project_name'] ?? '')) ?>
                    <?php endif; ?>
                    <span class="table-meta"><?= e((string) ($row['project_reference'] ?? '')) ?></span>
                <?php else: ?>
                    <span class="muted"><?= e(__('crm.not_linked')) ?></span>
                <?php endif; ?>
            </dd>
        </div>
        <div>
            <dt class="detail-label"><?= e(__('crm.last_activity')) ?></dt>
            <dd>
                <?= e($dateLabel(isset($row['last_activity_on']) ? (string) $row['last_activity_on'] : null)) ?>
                <span class="table-meta"><?= e(__('crm.activities_count', ['count' => (string) $activityCount])) ?></span>
            </dd>
        </div>
    </dl>

    <?php if (trim((string) ($row['notes'] ?? '')) !== ''): ?>
        <p class="text-callout"><?= e((string) $row['notes']) ?></p>
    <?php endif; ?>
    <?php if (trim((string) ($row['lost_reason'] ?? '')) !== ''): ?>
        <p class="form-feedback form-feedback--info" role="note"><?= e(__('crm.lost_reason_shown', ['reason' => (string) $row['lost_reason']])) ?></p>
    <?php endif; ?>
</section>

<?php if (!empty($canManage)): ?>
    <div class="content-grid">
        <section class="card stack-md" aria-labelledby="crm-stage-title">
            <div class="card-header">
                <div>
                    <h2 class="card-header__title" id="crm-stage-title"><?= e(__('crm.stage_change_title')) ?></h2>
                    <p class="section-heading__note"><?= e(__('crm.stage_change_help')) ?></p>
                </div>
            </div>
            <?php if ($stageErrors !== []): ?>
                <p class="form-feedback form-feedback--error" role="alert"><?= e(__('crm.fix_errors')) ?></p>
            <?php endif; ?>
            <form class="stack-md" method="post" action="<?= e(url('/crm/opportunites/' . (int) $row['id'] . '/etape')) ?>">
                <?= csrf_field() ?>
                <div class="field">
                    <label class="field__label" for="crm-stage-select"><?= e(__('crm.stage.label')) ?></label>
                    <select class="field__control" id="crm-stage-select" name="stage">
                        <?php foreach ($stages as $option): ?>
                            <option value="<?= e($option) ?>" <?= $stageValue === $option ? 'selected' : '' ?>><?= e(__('crm.stage.' . $option)) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <span class="field__hint"><?= e(__('crm.stage_help')) ?></span>
                    <?php if ($errorHint($stageErrors, 'stage') !== ''): ?><span class="field__hint field__hint--error"><?= e($errorHint($stageErrors, 'stage')) ?></span><?php endif; ?>
                </div>
                <div class="field">
                    <label class="field__label" for="crm-stage-lost"><?= e(__('crm.lost_reason')) ?></label>
                    <input class="field__control" type="text" id="crm-stage-lost" name="lost_reason" maxlength="500"
                           value="<?= e((string) ($row['lost_reason'] ?? '')) ?>">
                    <span class="field__hint"><?= e(__('crm.lost_reason_help')) ?></span>
                    <?php if ($errorHint($stageErrors, 'lost_reason') !== ''): ?><span class="field__hint field__hint--error"><?= e($errorHint($stageErrors, 'lost_reason')) ?></span><?php endif; ?>
                </div>
                <div class="form-actions">
                    <button class="button button--primary" type="submit"><?= e(__('crm.stage_change_submit')) ?></button>
                </div>
            </form>
        </section>

        <section class="card stack-md" aria-labelledby="crm-conversion-title">
            <div class="card-header">
                <div>
                    <h2 class="card-header__title" id="crm-conversion-title"><?= e(__('crm.conversion_title')) ?></h2>
                    <p class="section-heading__note"><?= e(__('crm.conversion_help')) ?></p>
                </div>
            </div>
            <?php if ($conversionErrors !== []): ?>
                <p class="form-feedback form-feedback--error" role="alert"><?= e(__('crm.fix_errors')) ?></p>
            <?php endif; ?>
            <p class="text-callout"><?= e(__('crm.conversion_note')) ?></p>
            <form class="stack-md" method="post" action="<?= e(url('/crm/opportunites/' . (int) $row['id'] . '/rattacher')) ?>">
                <?= csrf_field() ?>
                <div class="field">
                    <label class="field__label" for="crm-conv-client"><?= e(__('crm.client')) ?></label>
                    <select class="field__control" id="crm-conv-client" name="client_id" required>
                        <option value=""><?= e(__('crm.choose_client')) ?></option>
                        <?php foreach ($clients as $client): ?>
                            <option value="<?= (int) $client['id'] ?>" <?= (string) ($conversion['client_id'] ?? '') === (string) (int) $client['id'] ? 'selected' : '' ?>><?= e((string) $client['label']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <?php if ($errorHint($conversionErrors, 'client_id') !== ''): ?><span class="field__hint field__hint--error"><?= e($errorHint($conversionErrors, 'client_id')) ?></span><?php endif; ?>
                </div>
                <div class="field">
                    <label class="field__label" for="crm-conv-project"><?= e(__('crm.project')) ?></label>
                    <select class="field__control" id="crm-conv-project" name="project_id">
                        <option value=""><?= e(__('crm.no_project')) ?></option>
                        <?php foreach ($projects as $project): ?>
                            <option value="<?= (int) $project['id'] ?>" <?= (string) ($conversion['project_id'] ?? '') === (string) (int) $project['id'] ? 'selected' : '' ?>><?= e((string) $project['label']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <span class="field__hint"><?= e(__('crm.project_help')) ?></span>
                    <?php if ($errorHint($conversionErrors, 'project_id') !== ''): ?><span class="field__hint field__hint--error"><?= e($errorHint($conversionErrors, 'project_id')) ?></span><?php endif; ?>
                </div>
                <label class="checkbox-field" for="crm-conv-won">
                    <input type="checkbox" id="crm-conv-won" name="mark_won" value="1" <?= (string) ($conversion['mark_won'] ?? '0') === '1' ? 'checked' : '' ?>>
                    <span><?= e(__('crm.mark_won')) ?></span>
                </label>
                <div class="form-actions">
                    <button class="button button--primary" type="submit"><?= e(__('crm.conversion_submit')) ?></button>
                </div>
            </form>
            <p class="table-meta"><?= e(__('crm.conversion_links')) ?></p>
            <div class="cluster">
                <?php if (Auth::can('clients.manage')): ?>
                    <a class="button button--small button--secondary" href="<?= e(url('/clients/new')) ?>"><?= e(__('crm.new_client')) ?></a>
                <?php endif; ?>
                <?php if (Auth::can('projects.manage')): ?>
                    <a class="button button--small button--secondary" href="<?= e(url('/projects/new')) ?>"><?= e(__('crm.new_project')) ?></a>
                <?php endif; ?>
            </div>
        </section>
    </div>
<?php endif; ?>

<section class="card stack-md" id="crm-activities" aria-labelledby="crm-activities-title">
    <div class="card-header">
        <div>
            <h2 class="card-header__title" id="crm-activities-title"><?= e(__('crm.activities_title')) ?></h2>
            <p class="section-heading__note"><?= e(__('crm.activities_help')) ?></p>
        </div>
        <span class="badge badge--neutral"><?= e(__('crm.activities_count', ['count' => (string) $activityCount])) ?></span>
    </div>

    <?php if ($activities === []): ?>
        <?php View::partial('partials/empty-state', [
            'title' => __('crm.activities_empty_title'),
            'description' => __('crm.activities_empty_help'),
        ]) ?>
    <?php else: ?>
        <div class="table-wrap">
            <table class="data-table">
                <thead>
                    <tr>
                        <th scope="col"><?= e(__('crm.activity.date')) ?></th>
                        <th scope="col"><?= e(__('crm.activity.kind')) ?></th>
                        <th scope="col"><?= e(__('crm.activity.summary')) ?></th>
                        <th scope="col"><?= e(__('crm.activity.author')) ?></th>
                        <?php if (!empty($canManage)): ?><th scope="col"><?= e(__('common.actions')) ?></th><?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($activities as $item): ?>
                        <tr>
                            <td><?= e($dateLabel((string) $item['occurred_on'])) ?></td>
                            <td><span class="badge badge--neutral"><?= e(__('crm.activity.' . (string) $item['kind'])) ?></span></td>
                            <td>
                                <strong><?= e((string) $item['summary']) ?></strong>
                                <?php if (trim((string) ($item['detail'] ?? '')) !== ''): ?>
                                    <span class="table-meta"><?= e((string) $item['detail']) ?></span>
                                <?php endif; ?>
                            </td>
                            <td><?= e($orDash((string) ($item['author_name'] ?? ''))) ?></td>
                            <?php if (!empty($canManage)): ?>
                                <td>
                                    <form method="post" action="<?= e(url('/crm/opportunites/' . (int) $row['id'] . '/activites/' . (int) $item['id'] . '/supprimer')) ?>"
                                          data-confirm="<?= e(__('crm.activity.confirm_delete')) ?>">
                                        <?= csrf_field() ?>
                                        <button class="button button--small button--ghost" type="submit"><?= e(__('common.delete')) ?></button>
                                    </form>
                                </td>
                            <?php endif; ?>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>

    <?php if (!empty($canManage)): ?>
        <details class="stack-md" id="crm-activity-form" <?= $activityErrors !== [] ? 'open' : '' ?>>
            <summary class="text-callout"><?= e(__('crm.activity.add')) ?></summary>
            <?php if ($activityErrors !== []): ?>
                <p class="form-feedback form-feedback--error" role="alert"><?= e(__('crm.fix_errors')) ?></p>
            <?php endif; ?>
            <form class="form-grid" method="post" action="<?= e(url('/crm/opportunites/' . (int) $row['id'] . '/activites')) ?>">
                <?= csrf_field() ?>
                <div class="field">
                    <label class="field__label" for="crm-act-kind"><?= e(__('crm.activity.kind')) ?></label>
                    <select class="field__control" id="crm-act-kind" name="kind">
                        <?php foreach ($activityKinds as $kind): ?>
                            <option value="<?= e($kind) ?>" <?= (string) ($activity['kind'] ?? 'call') === $kind ? 'selected' : '' ?>><?= e(__('crm.activity.' . $kind)) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <?php if ($errorHint($activityErrors, 'kind') !== ''): ?><span class="field__hint field__hint--error"><?= e($errorHint($activityErrors, 'kind')) ?></span><?php endif; ?>
                </div>
                <div class="field">
                    <label class="field__label" for="crm-act-date"><?= e(__('crm.activity.date')) ?></label>
                    <input class="field__control" type="date" id="crm-act-date" name="occurred_on"
                           value="<?= e((string) ($activity['occurred_on'] ?? $today)) ?>">
                    <?php if ($errorHint($activityErrors, 'occurred_on') !== ''): ?><span class="field__hint field__hint--error"><?= e($errorHint($activityErrors, 'occurred_on')) ?></span><?php endif; ?>
                </div>
                <div class="field form-grid__full">
                    <label class="field__label" for="crm-act-summary"><?= e(__('crm.activity.summary')) ?></label>
                    <input class="field__control" type="text" id="crm-act-summary" name="summary" maxlength="255" required
                           value="<?= e((string) ($activity['summary'] ?? '')) ?>">
                    <?php if ($errorHint($activityErrors, 'summary') !== ''): ?><span class="field__hint field__hint--error"><?= e($errorHint($activityErrors, 'summary')) ?></span><?php endif; ?>
                </div>
                <div class="field form-grid__full">
                    <label class="field__label" for="crm-act-detail"><?= e(__('crm.activity.detail')) ?></label>
                    <input class="field__control" type="text" id="crm-act-detail" name="detail" maxlength="1000"
                           value="<?= e((string) ($activity['detail'] ?? '')) ?>">
                    <?php if ($errorHint($activityErrors, 'detail') !== ''): ?><span class="field__hint field__hint--error"><?= e($errorHint($activityErrors, 'detail')) ?></span><?php endif; ?>
                </div>
                <div class="form-actions form-grid__full">
                    <button class="button button--primary" type="submit"><?= e(__('crm.activity.submit')) ?></button>
                </div>
            </form>
        </details>
    <?php endif; ?>
</section>

<?php if (!empty($canManage)): ?>
    <section class="card stack-md" aria-labelledby="crm-edit-title">
        <div class="card-header">
            <div>
                <h2 class="card-header__title" id="crm-edit-title"><?= e(__('crm.edit_title')) ?></h2>
                <p class="section-heading__note"><?= e(__('crm.edit_help')) ?></p>
            </div>
        </div>
        <details class="stack-md" id="crm-edit" <?= $opportunityErrors !== [] ? 'open' : '' ?>>
            <summary class="text-callout"><?= e(__('crm.edit_toggle')) ?></summary>
            <?php if ($opportunityErrors !== []): ?>
                <p class="form-feedback form-feedback--error" role="alert"><?= e(__('crm.fix_errors')) ?></p>
            <?php endif; ?>
            <form class="form-grid" method="post" action="<?= e(url('/crm/opportunites/' . (int) $row['id'] . '/modifier')) ?>">
                <?= csrf_field() ?>
                <div class="field">
                    <label class="field__label" for="crm-edit-title-field"><?= e(__('crm.title_label')) ?></label>
                    <input class="field__control" type="text" id="crm-edit-title-field" name="title" maxlength="180" required
                           value="<?= e((string) ($values['title'] ?? '')) ?>">
                    <?php if ($errorHint($opportunityErrors, 'title') !== ''): ?><span class="field__hint field__hint--error"><?= e($errorHint($opportunityErrors, 'title')) ?></span><?php endif; ?>
                </div>
                <div class="field">
                    <label class="field__label" for="crm-edit-prospect"><?= e(__('crm.prospect_name')) ?></label>
                    <input class="field__control" type="text" id="crm-edit-prospect" name="prospect_name" maxlength="180" required
                           value="<?= e((string) ($values['prospect_name'] ?? '')) ?>">
                    <?php if ($errorHint($opportunityErrors, 'prospect_name') !== ''): ?><span class="field__hint field__hint--error"><?= e($errorHint($opportunityErrors, 'prospect_name')) ?></span><?php endif; ?>
                </div>
                <div class="field">
                    <label class="field__label" for="crm-edit-type"><?= e(__('crm.client_type.label')) ?></label>
                    <select class="field__control" id="crm-edit-type" name="client_type">
                        <?php foreach (CrmService::CLIENT_TYPES as $option): ?>
                            <option value="<?= e($option) ?>" <?= (string) ($values['client_type'] ?? 'individual') === $option ? 'selected' : '' ?>><?= e(__('crm.client_type.' . $option)) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="field">
                    <label class="field__label" for="crm-edit-origin"><?= e(__('crm.origin.label')) ?></label>
                    <select class="field__control" id="crm-edit-origin" name="origin">
                        <?php foreach ($origins as $option): ?>
                            <option value="<?= e($option) ?>" <?= (string) ($values['origin'] ?? 'other') === $option ? 'selected' : '' ?>><?= e(__('crm.origin.' . $option)) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="field">
                    <label class="field__label" for="crm-edit-email"><?= e(__('crm.email')) ?></label>
                    <input class="field__control" type="email" id="crm-edit-email" name="email" maxlength="190"
                           value="<?= e((string) ($values['email'] ?? '')) ?>">
                    <?php if ($errorHint($opportunityErrors, 'email') !== ''): ?><span class="field__hint field__hint--error"><?= e($errorHint($opportunityErrors, 'email')) ?></span><?php endif; ?>
                </div>
                <div class="field">
                    <label class="field__label" for="crm-edit-phone"><?= e(__('crm.phone')) ?></label>
                    <input class="field__control" type="tel" id="crm-edit-phone" name="phone" maxlength="32"
                           value="<?= e((string) ($values['phone'] ?? '')) ?>">
                    <?php if ($errorHint($opportunityErrors, 'phone') !== ''): ?><span class="field__hint field__hint--error"><?= e($errorHint($opportunityErrors, 'phone')) ?></span><?php endif; ?>
                </div>
                <div class="field">
                    <label class="field__label" for="crm-edit-city"><?= e(__('crm.city')) ?></label>
                    <input class="field__control" type="text" id="crm-edit-city" name="city" maxlength="120"
                           value="<?= e((string) ($values['city'] ?? '')) ?>">
                    <?php if ($errorHint($opportunityErrors, 'city') !== ''): ?><span class="field__hint field__hint--error"><?= e($errorHint($opportunityErrors, 'city')) ?></span><?php endif; ?>
                </div>
                <div class="field">
                    <label class="field__label" for="crm-edit-stage"><?= e(__('crm.stage.label')) ?></label>
                    <select class="field__control" id="crm-edit-stage" name="stage">
                        <?php foreach ($stages as $option): ?>
                            <option value="<?= e($option) ?>" <?= (string) ($values['stage'] ?? 'new') === $option ? 'selected' : '' ?>><?= e(__('crm.stage.' . $option)) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <?php if ($errorHint($opportunityErrors, 'stage') !== ''): ?><span class="field__hint field__hint--error"><?= e($errorHint($opportunityErrors, 'stage')) ?></span><?php endif; ?>
                </div>
                <div class="field">
                    <label class="field__label" for="crm-edit-amount"><?= e(__('crm.estimated_amount')) ?></label>
                    <input class="field__control" type="text" inputmode="decimal" id="crm-edit-amount" name="estimated_amount"
                           value="<?= e((string) ($values['estimated_amount'] ?? '')) ?>" placeholder="0.000">
                    <span class="field__hint"><?= e(__('crm.amount_help')) ?></span>
                    <?php if ($errorHint($opportunityErrors, 'estimated_amount') !== ''): ?><span class="field__hint field__hint--error"><?= e($errorHint($opportunityErrors, 'estimated_amount')) ?></span><?php endif; ?>
                </div>
                <div class="field">
                    <label class="field__label" for="crm-edit-close"><?= e(__('crm.expected_close_on')) ?></label>
                    <input class="field__control" type="date" id="crm-edit-close" name="expected_close_on"
                           value="<?= e((string) ($values['expected_close_on'] ?? '')) ?>">
                    <?php if ($errorHint($opportunityErrors, 'expected_close_on') !== ''): ?><span class="field__hint field__hint--error"><?= e($errorHint($opportunityErrors, 'expected_close_on')) ?></span><?php endif; ?>
                </div>
                <div class="field">
                    <label class="field__label" for="crm-edit-follow"><?= e(__('crm.next_action_on')) ?></label>
                    <input class="field__control" type="date" id="crm-edit-follow" name="next_action_on"
                           value="<?= e((string) ($values['next_action_on'] ?? '')) ?>">
                    <?php if ($errorHint($opportunityErrors, 'next_action_on') !== ''): ?><span class="field__hint field__hint--error"><?= e($errorHint($opportunityErrors, 'next_action_on')) ?></span><?php endif; ?>
                </div>
                <div class="field">
                    <label class="field__label" for="crm-edit-owner"><?= e(__('crm.owner')) ?></label>
                    <select class="field__control" id="crm-edit-owner" name="owner_id">
                        <option value=""><?= e(__('crm.unassigned')) ?></option>
                        <?php foreach ($owners as $owner): ?>
                            <option value="<?= (int) $owner['id'] ?>" <?= (string) ($values['owner_id'] ?? '') === (string) (int) $owner['id'] ? 'selected' : '' ?>><?= e((string) $owner['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <?php if ($errorHint($opportunityErrors, 'owner_id') !== ''): ?><span class="field__hint field__hint--error"><?= e($errorHint($opportunityErrors, 'owner_id')) ?></span><?php endif; ?>
                </div>
                <div class="field">
                    <label class="field__label" for="crm-edit-lost"><?= e(__('crm.lost_reason')) ?></label>
                    <input class="field__control" type="text" id="crm-edit-lost" name="lost_reason" maxlength="500"
                           value="<?= e((string) ($values['lost_reason'] ?? '')) ?>">
                    <span class="field__hint"><?= e(__('crm.lost_reason_help')) ?></span>
                    <?php if ($errorHint($opportunityErrors, 'lost_reason') !== ''): ?><span class="field__hint field__hint--error"><?= e($errorHint($opportunityErrors, 'lost_reason')) ?></span><?php endif; ?>
                </div>
                <div class="field form-grid__full">
                    <label class="field__label" for="crm-edit-notes"><?= e(__('crm.notes')) ?></label>
                    <input class="field__control" type="text" id="crm-edit-notes" name="notes" maxlength="1000"
                           value="<?= e((string) ($values['notes'] ?? '')) ?>">
                    <?php if ($errorHint($opportunityErrors, 'notes') !== ''): ?><span class="field__hint field__hint--error"><?= e($errorHint($opportunityErrors, 'notes')) ?></span><?php endif; ?>
                </div>
                <div class="form-actions form-grid__full">
                    <button class="button button--primary" type="submit"><?= e(__('crm.edit_submit')) ?></button>
                </div>
            </form>
        </details>
    </section>

    <section class="card stack-md" aria-labelledby="crm-danger-title">
        <div class="card-header">
            <div>
                <h2 class="card-header__title" id="crm-danger-title"><?= e(__('crm.danger_title')) ?></h2>
                <p class="section-heading__note"><?= e(__('crm.danger_help')) ?></p>
            </div>
        </div>
        <form method="post" action="<?= e(url('/crm/opportunites/' . (int) $row['id'] . '/supprimer')) ?>"
              data-confirm="<?= e(__('crm.confirm_delete')) ?>">
            <?= csrf_field() ?>
            <button class="button button--ghost" type="submit"><?= e(__('crm.delete_opportunity')) ?></button>
        </form>
    </section>
<?php endif; ?>
