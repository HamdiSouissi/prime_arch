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
$stageErrors = is_array($stageErrors ?? null) ? $stageErrors : [];
$opportunityErrors = is_array($opportunityErrors ?? null) ? $opportunityErrors : [];
$opportunityValues = is_array($opportunityValues ?? null) ? $opportunityValues : [];
$conversionErrors = is_array($conversionErrors ?? null) ? $conversionErrors : [];
$conversionValues = is_array($conversionValues ?? null) ? $conversionValues : [];
$activityErrors = is_array($activityErrors ?? null) ? $activityErrors : [];
$activityValues = is_array($activityValues ?? null) ? $activityValues : [];
$today = (string) ($today ?? date('Y-m-d'));
$id = (int) ($row['id'] ?? 0);

$dateLabel = static function (mixed $value): string {
    $text = is_string($value) ? trim($value) : '';
    $timestamp = $text !== '' ? strtotime($text) : false;

    return $timestamp === false ? '—' : date('d/m/Y', $timestamp);
};
$errorHint = static fn (array $errors, string $field): string => (string) ($errors[$field] ?? '');
$orDash = static fn (string $value): string => trim($value) !== '' ? $value : '—';
// Libellé lisible d’une option : référence puis nom, séparés par un tiret cadratin.
$optionLabel = static function (string $reference, string $name): string {
    $reference = trim($reference);
    $name = trim($name);

    return $reference === '' ? $name : ($name === '' ? $reference : $reference . ' — ' . $name);
};
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
$state = (string) ($row['follow_state'] ?? 'none');
$days = $row['follow_days'] ?? null;
$followLabel = __('crm.follow.none');
if ($state !== 'none' && $days !== null) {
    $followLabel = (int) $days < 0
        ? __('crm.follow.late_by', ['days' => (string) abs((int) $days)])
        : ((int) $days === 0 ? __('crm.follow.today') : __('crm.follow.in_days', ['days' => (string) $days]));
}

// Formulaire d’édition : valeurs revalidées si une erreur a été renvoyée, sinon fiche en base.
$edit = $opportunityValues !== [] ? $opportunityValues : $row;
$stageValue = (string) ($edit['stage'] ?? 'new');
$conversion = $conversionValues;
$activity = $activityValues !== [] ? $activityValues : ['kind' => 'call', 'occurred_on' => $today, 'summary' => '', 'detail' => ''];
$linkedClientId = $row['client_id'] === null ? '' : (string) (int) $row['client_id'];
$linkedProjectId = $row['project_id'] === null ? '' : (string) (int) $row['project_id'];
?>
<?php View::partial('partials/page-header', [
    'pageTitle' => (string) ($row['title'] ?? ''),
    'eyebrow' => __('crm.eyebrow'),
    'subtitle' => trim(implode(' · ', array_filter([
        (string) ($row['prospect_name'] ?? ''),
        __('crm.client_type.' . (string) ($row['client_type'] ?? 'individual')),
        (string) ($row['city'] ?? ''),
    ]))),
    'secondaryAction' => [
        'label' => __('crm.back_to_list'),
        'icon' => 'arrow-left',
        'url' => url('/crm'),
    ],
]) ?>

<?php if (!empty($notice)): ?><p class="form-feedback form-feedback--success" role="status"><?= e($notice) ?></p><?php endif; ?>
<?php if (!empty($error)): ?><p class="form-feedback form-feedback--error" role="alert"><?= e($error) ?></p><?php endif; ?>

<section class="card stack-md" aria-labelledby="crm-summary-title">
    <div class="card-header">
        <div>
            <h2 class="card-header__title" id="crm-summary-title"><?= e(__('crm.summary_title')) ?></h2>
            <p class="section-heading__note"><?= e(__('crm.summary_help')) ?></p>
        </div>
        <div class="cluster">
            <span class="badge <?= e($stageBadge((string) ($row['stage'] ?? 'new'))) ?>"><?= e(__('crm.stage.' . (string) ($row['stage'] ?? 'new'))) ?></span>
            <?php if ($state !== 'none' && !($row['is_closed'] ?? false)): ?>
                <span class="badge <?= e($followBadge($state)) ?>"><?= e($followLabel) ?></span>
            <?php endif; ?>
        </div>
    </div>

    <div class="document-summary-grid">
        <div><span class="detail-label"><?= e(__('crm.prospect_name')) ?></span><strong><?= e((string) ($row['prospect_name'] ?? '')) ?></strong><span class="table-meta"><?= e(__('crm.client_type.' . (string) ($row['client_type'] ?? 'individual'))) ?></span></div>
        <div><span class="detail-label"><?= e(__('crm.email')) ?></span><strong><?= e($orDash((string) ($row['email'] ?? ''))) ?></strong></div>
        <div><span class="detail-label"><?= e(__('crm.phone')) ?></span><strong><?= e($orDash((string) ($row['phone'] ?? ''))) ?></strong></div>
        <div><span class="detail-label"><?= e(__('crm.city')) ?></span><strong><?= e($orDash((string) ($row['city'] ?? ''))) ?></strong></div>
        <div><span class="detail-label"><?= e(__('crm.origin.label')) ?></span><strong><?= e(__('crm.origin.' . (string) ($row['origin'] ?? 'other'))) ?></strong></div>
        <div><span class="detail-label"><?= e(__('crm.estimated_amount')) ?></span><strong><?= e(CrmService::label($row['amount_millimes'] ?? null)) ?></strong><span class="table-meta"><?= e(__('crm.amount_note')) ?></span></div>
        <div><span class="detail-label"><?= e(__('crm.expected_close_on')) ?></span><strong><?= e($dateLabel($row['expected_close_on'] ?? null)) ?></strong></div>
        <div><span class="detail-label"><?= e(__('crm.next_action_on')) ?></span><strong><?= e($dateLabel($row['next_action_on'] ?? null)) ?></strong><span class="table-meta"><?= e($followLabel) ?></span></div>
        <div><span class="detail-label"><?= e(__('crm.owner')) ?></span><strong><?= e($orDash((string) ($row['owner_name'] ?? ''))) ?></strong></div>
        <div>
            <span class="detail-label"><?= e(__('crm.client')) ?></span>
            <?php if (!empty($row['client_id'])): ?>
                <?php if (Auth::can('clients.view')): ?>
                    <strong><a href="<?= e(url('/clients/' . (int) $row['client_id'])) ?>"><?= e((string) ($row['client_name'] ?? '')) ?></a></strong>
                <?php else: ?>
                    <strong><?= e((string) ($row['client_name'] ?? '')) ?></strong>
                <?php endif; ?>
                <span class="table-meta"><?= e((string) ($row['client_reference'] ?? '')) ?></span>
            <?php else: ?>
                <strong class="muted"><?= e(__('crm.not_linked')) ?></strong>
            <?php endif; ?>
        </div>
        <div>
            <span class="detail-label"><?= e(__('crm.project')) ?></span>
            <?php if (!empty($row['project_id'])): ?>
                <?php if (Auth::can('projects.view')): ?>
                    <strong><a href="<?= e(url('/projects/' . (int) $row['project_id'])) ?>"><?= e((string) ($row['project_name'] ?? '')) ?></a></strong>
                <?php else: ?>
                    <strong><?= e((string) ($row['project_name'] ?? '')) ?></strong>
                <?php endif; ?>
                <span class="table-meta"><?= e((string) ($row['project_reference'] ?? '')) ?></span>
            <?php else: ?>
                <strong class="muted"><?= e(__('crm.no_project')) ?></strong>
            <?php endif; ?>
        </div>
    </div>

    <?php if (trim((string) ($row['notes'] ?? '')) !== ''): ?>
        <p><?= e((string) $row['notes']) ?></p>
    <?php endif; ?>
    <?php if (trim((string) ($row['lost_reason'] ?? '')) !== ''): ?>
        <p class="form-feedback form-feedback--info" role="note"><?= e(__('crm.lost_reason')) ?> · <?= e((string) $row['lost_reason']) ?></p>
    <?php endif; ?>
    <?php if (!empty($row['converted_at'])): ?>
        <p class="table-meta"><?= e(__('crm.converted_on', ['date' => $dateLabel($row['converted_at'])])) ?></p>
    <?php endif; ?>
</section>

<?php if (!empty($canManage)): ?>
    <div class="content-grid">
        <section class="card stack-md" id="crm-stage" aria-labelledby="crm-stage-title">
            <div class="card-header">
                <div>
                    <h2 class="card-header__title" id="crm-stage-title"><?= e(__('crm.stage_change_title')) ?></h2>
                    <p class="section-heading__note"><?= e(__('crm.stage_change_help')) ?></p>
                </div>
            </div>
            <?php if ($stageErrors !== []): ?>
                <p class="form-feedback form-feedback--error" role="alert"><?= e(__('crm.fix_errors')) ?></p>
            <?php endif; ?>
            <form class="form-grid" method="post" action="<?= e(url('/crm/opportunites/' . $id . '/etape')) ?>">
                <?= csrf_field() ?>
                <div class="field">
                    <label class="field__label" for="crm-stage-select"><?= e(__('crm.stage.label')) ?></label>
                    <select class="field__control" id="crm-stage-select" name="stage">
                        <?php foreach ($stages as $option): ?>
                            <option value="<?= e($option) ?>" <?= (string) ($row['stage'] ?? 'new') === $option ? 'selected' : '' ?>><?= e(__('crm.stage.' . $option)) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <?php if ($errorHint($stageErrors, 'stage') !== ''): ?><span class="field__hint field__hint--error"><?= e($errorHint($stageErrors, 'stage')) ?></span><?php endif; ?>
                </div>
                <div class="field">
                    <label class="field__label" for="crm-stage-lost"><?= e(__('crm.lost_reason')) ?></label>
                    <input class="field__control" type="text" id="crm-stage-lost" name="lost_reason" maxlength="500"
                           value="<?= e((string) ($row['lost_reason'] ?? '')) ?>">
                    <span class="field__hint"><?= e(__('crm.lost_reason_help')) ?></span>
                    <?php if ($errorHint($stageErrors, 'lost_reason') !== ''): ?><span class="field__hint field__hint--error"><?= e($errorHint($stageErrors, 'lost_reason')) ?></span><?php endif; ?>
                </div>
                <div class="form-actions form-grid__full">
                    <button class="button button--primary" type="submit"><?= e(__('crm.stage_change_submit')) ?></button>
                </div>
            </form>
            <form method="post" action="<?= e(url('/crm/opportunites/' . $id . '/supprimer')) ?>"
                  data-confirm="<?= e(__('crm.confirm_delete')) ?>">
                <?= csrf_field() ?>
                <button class="button button--small button--ghost" type="submit"><?= e(__('crm.delete_opportunity')) ?></button>
            </form>
        </section>

        <section class="card stack-md" id="crm-conversion" aria-labelledby="crm-conversion-title">
            <div class="card-header">
                <div>
                    <h2 class="card-header__title" id="crm-conversion-title"><?= e(__('crm.conversion_title')) ?></h2>
                    <p class="section-heading__note"><?= e(__('crm.conversion_help')) ?></p>
                </div>
            </div>

            <?php if ($conversionErrors !== []): ?>
                <p class="form-feedback form-feedback--error" role="alert"><?= e(__('crm.fix_errors')) ?></p>
            <?php endif; ?>
            <?php if ($clients === []): ?>
                <p class="table-meta"><?= e(__('crm.conversion_no_client')) ?></p>
            <?php else: ?>
                <form class="form-grid" method="post" action="<?= e(url('/crm/opportunites/' . $id . '/rattacher')) ?>">
                    <?= csrf_field() ?>
                    <div class="field form-grid__full">
                        <label class="field__label" for="crm-conversion-client"><?= e(__('crm.client')) ?></label>
                        <select class="field__control" id="crm-conversion-client" name="client_id" required>
                            <option value=""><?= e(__('common.choose')) ?></option>
                            <?php foreach ($clients as $client): ?>
                                <option value="<?= (int) $client['id'] ?>" <?= (string) ($conversion['client_id'] ?? $linkedClientId) === (string) (int) $client['id'] ? 'selected' : '' ?>>
                                    <?= e($optionLabel((string) $client['reference'], (string) $client['display_name'])) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <span class="field__hint"><?= e(__('crm.conversion_client_help')) ?></span>
                        <?php if ($errorHint($conversionErrors, 'client_id') !== ''): ?><span class="field__hint field__hint--error"><?= e($errorHint($conversionErrors, 'client_id')) ?></span><?php endif; ?>
                    </div>
                    <div class="field form-grid__full">
                        <label class="field__label" for="crm-conversion-project"><?= e(__('crm.project')) ?></label>
                        <select class="field__control" id="crm-conversion-project" name="project_id">
                            <option value=""><?= e(__('crm.no_project')) ?></option>
                            <?php foreach ($projects as $project): ?>
                                <option value="<?= (int) $project['id'] ?>" <?= (string) ($conversion['project_id'] ?? $linkedProjectId) === (string) (int) $project['id'] ? 'selected' : '' ?>>
                                    <?= e((string) $project['client_name'] . ' · ' . $optionLabel((string) $project['reference'], (string) $project['name'])) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <span class="field__hint"><?= e(__('crm.conversion_project_help')) ?></span>
                        <?php if ($errorHint($conversionErrors, 'project_id') !== ''): ?><span class="field__hint field__hint--error"><?= e($errorHint($conversionErrors, 'project_id')) ?></span><?php endif; ?>
                    </div>
                    <div class="field form-grid__full">
                        <label class="field__label" for="crm-conversion-won"><?= e(__('crm.conversion_mark_won')) ?></label>
                        <label class="cluster">
                            <input type="checkbox" name="mark_won" value="1" <?= !empty($conversion['mark_won']) ? 'checked' : '' ?>>
                            <span><?= e(__('crm.conversion_mark_won_help')) ?></span>
                        </label>
                    </div>
                    <div class="form-actions form-grid__full">
                        <button class="button button--primary" type="submit"><?= e(__('crm.conversion_submit')) ?></button>
                    </div>
                </form>
            <?php endif; ?>

            <p class="table-meta">
                <?= e(__('crm.conversion_core_hint')) ?>
                <?php if (Auth::can('clients.manage')): ?>
                    <a href="<?= e(url('/clients/new')) ?>"><?= e(__('crm.conversion_create_client')) ?></a>
                <?php endif; ?>
            </p>
        </section>
    </div>

    <section class="card stack-md" id="crm-activities" aria-labelledby="crm-activities-title">
        <div class="card-header">
            <div>
                <h2 class="card-header__title" id="crm-activities-title"><?= e(__('crm.activities_title')) ?></h2>
                <p class="section-heading__note"><?= e(__('crm.activities_help')) ?></p>
            </div>
            <span class="badge badge--neutral"><?= e(__('common.results', ['count' => (string) count($activities)])) ?></span>
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
                            <th scope="col"><?= e(__('crm.occurred_on')) ?></th>
                            <th scope="col"><?= e(__('crm.activity_kind')) ?></th>
                            <th scope="col"><?= e(__('crm.summary')) ?></th>
                            <th scope="col"><?= e(__('crm.author')) ?></th>
                            <th scope="col"><?= e(__('common.actions')) ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($activities as $entry): ?>
                            <tr>
                                <td><?= e($dateLabel($entry['occurred_on'] ?? null)) ?></td>
                                <td><span class="badge badge--info"><?= e(__('crm.activity.' . (string) $entry['kind'])) ?></span></td>
                                <td>
                                    <strong><?= e((string) $entry['summary']) ?></strong>
                                    <?php if (trim((string) ($entry['detail'] ?? '')) !== ''): ?>
                                        <span class="table-meta"><?= e((string) $entry['detail']) ?></span>
                                    <?php endif; ?>
                                </td>
                                <td><?= e($orDash((string) ($entry['author_name'] ?? ''))) ?></td>
                                <td>
                                    <form method="post" action="<?= e(url('/crm/opportunites/' . $id . '/activites/' . (int) $entry['id'] . '/supprimer')) ?>"
                                          data-confirm="<?= e(__('crm.confirm_delete_activity')) ?>">
                                        <?= csrf_field() ?>
                                        <button class="button button--small button--ghost" type="submit"><?= e(__('common.delete')) ?></button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>

        <details class="stack-md" id="crm-activity-form" <?= $activityErrors !== [] ? 'open' : '' ?>>
            <summary class="text-callout"><?= e(__('crm.add_activity')) ?></summary>
            <?php if ($activityErrors !== []): ?>
                <p class="form-feedback form-feedback--error" role="alert"><?= e(__('crm.fix_errors')) ?></p>
            <?php endif; ?>
            <form class="form-grid" method="post" action="<?= e(url('/crm/opportunites/' . $id . '/activites')) ?>">
                <?= csrf_field() ?>
                <div class="field">
                    <label class="field__label" for="crm-activity-kind"><?= e(__('crm.activity_kind')) ?></label>
                    <select class="field__control" id="crm-activity-kind" name="kind">
                        <?php foreach ($activityKinds as $option): ?>
                            <option value="<?= e($option) ?>" <?= (string) ($activity['kind'] ?? 'call') === $option ? 'selected' : '' ?>><?= e(__('crm.activity.' . $option)) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="field">
                    <label class="field__label" for="crm-activity-date"><?= e(__('crm.occurred_on')) ?></label>
                    <input class="field__control" type="date" id="crm-activity-date" name="occurred_on" required
                           value="<?= e((string) ($activity['occurred_on'] ?? $today)) ?>">
                    <?php if ($errorHint($activityErrors, 'occurred_on') !== ''): ?><span class="field__hint field__hint--error"><?= e($errorHint($activityErrors, 'occurred_on')) ?></span><?php endif; ?>
                </div>
                <div class="field form-grid__full">
                    <label class="field__label" for="crm-activity-summary"><?= e(__('crm.summary')) ?></label>
                    <input class="field__control" type="text" id="crm-activity-summary" name="summary" maxlength="255" required
                           value="<?= e((string) ($activity['summary'] ?? '')) ?>" placeholder="<?= e(__('crm.summary_placeholder')) ?>">
                    <?php if ($errorHint($activityErrors, 'summary') !== ''): ?><span class="field__hint field__hint--error"><?= e($errorHint($activityErrors, 'summary')) ?></span><?php endif; ?>
                </div>
                <div class="field form-grid__full">
                    <label class="field__label" for="crm-activity-detail"><?= e(__('crm.detail')) ?></label>
                    <input class="field__control" type="text" id="crm-activity-detail" name="detail" maxlength="1000"
                           value="<?= e((string) ($activity['detail'] ?? '')) ?>">
                    <?php if ($errorHint($activityErrors, 'detail') !== ''): ?><span class="field__hint field__hint--error"><?= e($errorHint($activityErrors, 'detail')) ?></span><?php endif; ?>
                </div>
                <div class="form-actions form-grid__full">
                    <button class="button button--primary" type="submit"><?= e(__('crm.add_activity_submit')) ?></button>
                </div>
            </form>
        </details>
    </section>

    <section class="card stack-md" aria-labelledby="crm-edit-title">
        <div class="card-header">
            <div>
                <h2 class="card-header__title" id="crm-edit-title"><?= e(__('crm.edit_title')) ?></h2>
                <p class="section-heading__note"><?= e(__('crm.edit_help')) ?></p>
            </div>
        </div>
        <details class="stack-md" id="crm-edit" <?= $opportunityErrors !== [] ? 'open' : '' ?>>
            <summary class="text-callout"><?= e(__('crm.edit_opportunity')) ?></summary>
            <?php if ($opportunityErrors !== []): ?>
                <p class="form-feedback form-feedback--error" role="alert"><?= e(__('crm.fix_errors')) ?></p>
            <?php endif; ?>
            <form class="form-grid" method="post" action="<?= e(url('/crm/opportunites/' . $id . '/modifier')) ?>">
                <?= csrf_field() ?>
                <div class="field">
                    <label class="field__label" for="crm-edit-title-field"><?= e(__('crm.title_label')) ?></label>
                    <input class="field__control" type="text" id="crm-edit-title-field" name="title" maxlength="180" required
                           value="<?= e((string) ($edit['title'] ?? '')) ?>">
                    <?php if ($errorHint($opportunityErrors, 'title') !== ''): ?><span class="field__hint field__hint--error"><?= e($errorHint($opportunityErrors, 'title')) ?></span><?php endif; ?>
                </div>
                <div class="field">
                    <label class="field__label" for="crm-edit-prospect"><?= e(__('crm.prospect_name')) ?></label>
                    <input class="field__control" type="text" id="crm-edit-prospect" name="prospect_name" maxlength="180" required
                           value="<?= e((string) ($edit['prospect_name'] ?? '')) ?>">
                    <?php if ($errorHint($opportunityErrors, 'prospect_name') !== ''): ?><span class="field__hint field__hint--error"><?= e($errorHint($opportunityErrors, 'prospect_name')) ?></span><?php endif; ?>
                </div>
                <div class="field">
                    <label class="field__label" for="crm-edit-type"><?= e(__('crm.client_type.label')) ?></label>
                    <select class="field__control" id="crm-edit-type" name="client_type">
                        <?php foreach (CrmService::CLIENT_TYPES as $option): ?>
                            <option value="<?= e($option) ?>" <?= (string) ($edit['client_type'] ?? 'individual') === $option ? 'selected' : '' ?>><?= e(__('crm.client_type.' . $option)) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="field">
                    <label class="field__label" for="crm-edit-origin"><?= e(__('crm.origin.label')) ?></label>
                    <select class="field__control" id="crm-edit-origin" name="origin">
                        <?php foreach ($origins as $option): ?>
                            <option value="<?= e($option) ?>" <?= (string) ($edit['origin'] ?? 'other') === $option ? 'selected' : '' ?>><?= e(__('crm.origin.' . $option)) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="field">
                    <label class="field__label" for="crm-edit-email"><?= e(__('crm.email')) ?></label>
                    <input class="field__control" type="email" id="crm-edit-email" name="email" maxlength="190"
                           value="<?= e((string) ($edit['email'] ?? '')) ?>">
                    <?php if ($errorHint($opportunityErrors, 'email') !== ''): ?><span class="field__hint field__hint--error"><?= e($errorHint($opportunityErrors, 'email')) ?></span><?php endif; ?>
                </div>
                <div class="field">
                    <label class="field__label" for="crm-edit-phone"><?= e(__('crm.phone')) ?></label>
                    <input class="field__control" type="tel" id="crm-edit-phone" name="phone" maxlength="32"
                           value="<?= e((string) ($edit['phone'] ?? '')) ?>">
                    <?php if ($errorHint($opportunityErrors, 'phone') !== ''): ?><span class="field__hint field__hint--error"><?= e($errorHint($opportunityErrors, 'phone')) ?></span><?php endif; ?>
                </div>
                <div class="field">
                    <label class="field__label" for="crm-edit-city"><?= e(__('crm.city')) ?></label>
                    <input class="field__control" type="text" id="crm-edit-city" name="city" maxlength="120"
                           value="<?= e((string) ($edit['city'] ?? '')) ?>">
                    <?php if ($errorHint($opportunityErrors, 'city') !== ''): ?><span class="field__hint field__hint--error"><?= e($errorHint($opportunityErrors, 'city')) ?></span><?php endif; ?>
                </div>
                <div class="field">
                    <label class="field__label" for="crm-edit-stage"><?= e(__('crm.stage.label')) ?></label>
                    <select class="field__control" id="crm-edit-stage" name="stage">
                        <?php foreach ($stages as $option): ?>
                            <option value="<?= e($option) ?>" <?= $stageValue === $option ? 'selected' : '' ?>><?= e(__('crm.stage.' . $option)) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="field">
                    <label class="field__label" for="crm-edit-amount"><?= e(__('crm.estimated_amount')) ?></label>
                    <input class="field__control" type="text" inputmode="decimal" id="crm-edit-amount" name="estimated_amount"
                           value="<?= e((string) ($edit['estimated_amount'] ?? '')) ?>" placeholder="0.000">
                    <span class="field__hint"><?= e(__('crm.amount_help')) ?></span>
                    <?php if ($errorHint($opportunityErrors, 'estimated_amount') !== ''): ?><span class="field__hint field__hint--error"><?= e($errorHint($opportunityErrors, 'estimated_amount')) ?></span><?php endif; ?>
                </div>
                <div class="field">
                    <label class="field__label" for="crm-edit-close"><?= e(__('crm.expected_close_on')) ?></label>
                    <input class="field__control" type="date" id="crm-edit-close" name="expected_close_on"
                           value="<?= e((string) ($edit['expected_close_on'] ?? '')) ?>">
                    <?php if ($errorHint($opportunityErrors, 'expected_close_on') !== ''): ?><span class="field__hint field__hint--error"><?= e($errorHint($opportunityErrors, 'expected_close_on')) ?></span><?php endif; ?>
                </div>
                <div class="field">
                    <label class="field__label" for="crm-edit-follow"><?= e(__('crm.next_action_on')) ?></label>
                    <input class="field__control" type="date" id="crm-edit-follow" name="next_action_on"
                           value="<?= e((string) ($edit['next_action_on'] ?? '')) ?>">
                    <span class="field__hint"><?= e(__('crm.next_action_help')) ?></span>
                    <?php if ($errorHint($opportunityErrors, 'next_action_on') !== ''): ?><span class="field__hint field__hint--error"><?= e($errorHint($opportunityErrors, 'next_action_on')) ?></span><?php endif; ?>
                </div>
                <div class="field">
                    <label class="field__label" for="crm-edit-owner"><?= e(__('crm.owner')) ?></label>
                    <select class="field__control" id="crm-edit-owner" name="owner_id">
                        <option value=""><?= e(__('crm.unassigned')) ?></option>
                        <?php foreach ($owners as $owner): ?>
                            <option value="<?= (int) $owner['id'] ?>" <?= (string) ($edit['owner_id'] ?? '') === (string) (int) $owner['id'] ? 'selected' : '' ?>><?= e((string) $owner['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <?php if ($errorHint($opportunityErrors, 'owner_id') !== ''): ?><span class="field__hint field__hint--error"><?= e($errorHint($opportunityErrors, 'owner_id')) ?></span><?php endif; ?>
                </div>
                <div class="field">
                    <label class="field__label" for="crm-edit-lost"><?= e(__('crm.lost_reason')) ?></label>
                    <input class="field__control" type="text" id="crm-edit-lost" name="lost_reason" maxlength="500"
                           value="<?= e((string) ($edit['lost_reason'] ?? '')) ?>">
                    <span class="field__hint"><?= e(__('crm.lost_reason_help')) ?></span>
                    <?php if ($errorHint($opportunityErrors, 'lost_reason') !== ''): ?><span class="field__hint field__hint--error"><?= e($errorHint($opportunityErrors, 'lost_reason')) ?></span><?php endif; ?>
                </div>
                <div class="field form-grid__full">
                    <label class="field__label" for="crm-edit-notes"><?= e(__('crm.notes')) ?></label>
                    <input class="field__control" type="text" id="crm-edit-notes" name="notes" maxlength="1000"
                           value="<?= e((string) ($edit['notes'] ?? '')) ?>">
                    <?php if ($errorHint($opportunityErrors, 'notes') !== ''): ?><span class="field__hint field__hint--error"><?= e($errorHint($opportunityErrors, 'notes')) ?></span><?php endif; ?>
                </div>
                <div class="form-actions form-grid__full">
                    <button class="button button--primary" type="submit"><?= e(__('crm.update_submit')) ?></button>
                </div>
            </form>
        </details>
    </section>
<?php else: ?>
    <section class="card stack-md" aria-labelledby="crm-readonly-title">
        <div class="card-header">
            <div>
                <h2 class="card-header__title" id="crm-readonly-title"><?= e(__('crm.activities_title')) ?></h2>
                <p class="section-heading__note"><?= e(__('crm.readonly_help')) ?></p>
            </div>
        </div>
        <?php if ($activities === []): ?>
            <p class="table-meta"><?= e(__('crm.no_activities')) ?></p>
        <?php else: ?>
            <div class="table-wrap">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th scope="col"><?= e(__('crm.occurred_on')) ?></th>
                            <th scope="col"><?= e(__('crm.activity_kind')) ?></th>
                            <th scope="col"><?= e(__('crm.summary')) ?></th>
                            <th scope="col"><?= e(__('crm.author')) ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($activities as $entry): ?>
                            <tr>
                                <td><?= e($dateLabel($entry['occurred_on'] ?? null)) ?></td>
                                <td><span class="badge badge--info"><?= e(__('crm.activity.' . (string) $entry['kind'])) ?></span></td>
                                <td>
                                    <strong><?= e((string) $entry['summary']) ?></strong>
                                    <?php if (trim((string) ($entry['detail'] ?? '')) !== ''): ?>
                                        <span class="table-meta"><?= e((string) $entry['detail']) ?></span>
                                    <?php endif; ?>
                                </td>
                                <td><?= e($orDash((string) ($entry['author_name'] ?? ''))) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>
<?php endif; ?>
