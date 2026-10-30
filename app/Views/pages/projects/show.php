<?php
declare(strict_types=1);

use App\Core\Auth;
use App\Core\Translator;
use App\Core\View;

$project = is_array($project ?? null) ? $project : [];
$phases = is_array($phases ?? null) ? $phases : [];
$customFields = is_array($customFields ?? null) ? $customFields : [];
$errors = is_array($errors ?? null) ? $errors : [];
$projectStatus = (string) ($project['status'] ?? 'planning');
$completedPhases = count(array_filter($phases, static fn (array $phase): bool => $phase['status'] === 'completed'));
$progress = $phases === [] ? 0 : (int) round($completedPhases / count($phases) * 100);
$addressParts = array_filter([
    $project['address_line'] ?? '',
    $project['city'] ?? '',
    Translator::locale() === 'ar' ? ($project['delegation_name_ar'] ?? '') : ($project['delegation_name'] ?? ''),
    Translator::locale() === 'ar' ? ($project['governorate_name_ar'] ?? '') : ($project['governorate_name'] ?? ''),
    $project['postal_code'] ?? '',
], static fn (mixed $part): bool => is_string($part) && $part !== '');
?>
<?php if (!empty($notice)): ?><p class="form-feedback form-feedback--success" role="status"><?= e($notice) ?></p><?php endif; ?>
<?php if (!empty($errors['general'])): ?><p class="form-feedback form-feedback--error" role="alert"><?= e($errors['general']) ?></p><?php endif; ?>
<?php View::partial('partials/page-header', [
    'pageTitle' => (string) $project['name'],
    'eyebrow' => __('projects.record') . ' · ' . (string) ($project['reference'] ?? ''),
    'subtitle' => __('projects.status.' . $projectStatus) . ' · ' . (string) ($project['project_type'] ?? __('projects.type_unspecified')),
    'secondaryAction' => ['label' => __('projects.back_to_list'), 'icon' => 'arrow-left', 'url' => url('/projects')],
    'primaryAction' => !empty($canManage) ? ['label' => __('projects.edit'), 'icon' => 'edit', 'url' => url('/projects/' . (int) $project['id'] . '/edit')] : null,
]) ?>

<div class="content-grid project-detail-grid">
    <div class="stack-lg">
        <section class="card" aria-labelledby="project-summary-title">
            <div class="card-header"><div><h2 class="card-header__title" id="project-summary-title"><?= e(__('projects.information')) ?></h2><p class="section-heading__note"><?= e(__('projects.client_label')) ?> <?php if (Auth::can('clients.view')): ?><a href="<?= e(url('/clients/' . (int) $project['client_id'])) ?>"><?= e($project['client_name']) ?></a><?php else: ?><?= e($project['client_name']) ?><?php endif; ?></p></div><span class="badge badge--<?= e(match ($projectStatus) { 'active' => 'info', 'completed' => 'success', 'on_hold' => 'warning', 'cancelled' => 'neutral', default => 'neutral' }) ?>"><?= e(__('projects.status.' . $projectStatus)) ?></span></div>
            <dl class="detail-list">
                <div class="detail-list__item"><dt><?= e(__('projects.type')) ?></dt><dd><?= e($project['project_type'] !== '' ? $project['project_type'] : '—') ?></dd></div>
                <div class="detail-list__item"><dt><?= e(__('projects.site_address')) ?></dt><dd><?= e($addressParts !== [] ? implode(', ', $addressParts) : '—') ?></dd></div>
                <div class="detail-list__item"><dt><?= e(__('projects.start_date')) ?></dt><dd><?= e(!empty($project['start_date']) ? date('d/m/Y', strtotime((string) $project['start_date'])) : __('projects.not_scheduled')) ?></dd></div>
                <div class="detail-list__item"><dt><?= e(__('projects.target_end_date')) ?></dt><dd><?= e(!empty($project['target_end_date']) ? date('d/m/Y', strtotime((string) $project['target_end_date'])) : __('projects.not_scheduled')) ?></dd></div>
                <div class="detail-list__item detail-list__item--stack"><dt><?= e(__('projects.description')) ?></dt><dd><?= !empty($project['description']) ? nl2br(e($project['description'])) : '—' ?></dd></div>
            </dl>
        </section>

        <section class="card phase-card" aria-labelledby="project-phases-title">
            <div class="card-header"><div><h2 class="card-header__title" id="project-phases-title"><?= e(__('project_phases.title')) ?></h2><p class="section-heading__note"><?= e(__('project_phases.progress', ['done' => (string) $completedPhases, 'total' => (string) count($phases)])) ?></p></div><?php if (!empty($canConfigure)): ?><a class="button button--small button--secondary" href="<?= e(url('/settings/project-phases')) ?>"><?= e(__('project_phases.manage')) ?></a><?php endif; ?></div>
            <?php if ($phases === []): ?>
                <?php View::partial('partials/empty-state', ['title' => __('project_phases.none'), 'description' => __('project_phases.none_help')]) ?>
            <?php else: ?>
                <div class="progress progress--compact" role="progressbar" aria-valuenow="<?= e((string) $progress) ?>" aria-valuemin="0" aria-valuemax="100" aria-label="<?= e(__('project_phases.progress', ['done' => (string) $completedPhases, 'total' => (string) count($phases)])) ?>"><span class="progress__bar" style="--progress: <?= e((string) $progress) ?>%"></span></div>
                <ol class="timeline project-phase-timeline">
                    <?php foreach ($phases as $phase): ?>
                        <?php $phaseStatus = $phase['status']; ?>
                        <li class="timeline__item<?= $phaseStatus === 'completed' ? ' is-complete' : '' ?><?= $phaseStatus === 'in_progress' ? ' is-current' : '' ?>">
                            <span class="timeline__marker" aria-hidden="true"><svg class="icon icon--small"><use href="<?= e(asset('img/icons.svg')) ?>#<?= e(match ($phaseStatus) { 'completed' => 'check', default => 'plan' }) ?>"></use></svg></span>
                            <span class="timeline__content"><strong class="timeline__title"><?= e($phase['phase_name']) ?></strong><span class="timeline__meta"><?= e(__('project_phases.status.' . $phaseStatus)) ?><?php if ($phase['started_at'] !== ''): ?> · <?= e(__('project_phases.started_on', ['date' => date('d/m/Y', strtotime($phase['started_at']))])) ?><?php endif; ?><?php if ($phase['completed_at'] !== ''): ?> · <?= e(__('project_phases.completed_on', ['date' => date('d/m/Y', strtotime($phase['completed_at']))])) ?><?php endif; ?></span></span>
                            <?php if (!empty($canManage) && !in_array($projectStatus, ['completed', 'cancelled'], true)): ?>
                                <?php if ($phaseStatus === 'pending'): ?><form method="post" action="<?= e(url('/projects/' . (int) $project['id'] . '/phases/' . (int) $phase['id'])) ?>"><?= csrf_field() ?><input type="hidden" name="action" value="start"><button class="button button--small button--secondary" type="submit"><?= e(__('project_phases.start')) ?></button></form><?php elseif ($phaseStatus === 'in_progress'): ?><form method="post" action="<?= e(url('/projects/' . (int) $project['id'] . '/phases/' . (int) $phase['id'])) ?>"><?= csrf_field() ?><input type="hidden" name="action" value="complete"><button class="button button--small button--primary" type="submit"><?= e(__('project_phases.complete')) ?></button></form><?php endif; ?>
                            <?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                </ol>
            <?php endif; ?>
        </section>
    </div>

    <aside class="stack-lg">
        <section class="card" aria-labelledby="project-client-title"><div class="card-header"><h2 class="card-header__title" id="project-client-title"><?= e(__('projects.client_label')) ?></h2></div><p class="text-headline"><?php if (Auth::can('clients.view')): ?><a href="<?= e(url('/clients/' . (int) $project['client_id'])) ?>"><?= e($project['client_name']) ?></a><?php else: ?><?= e($project['client_name']) ?><?php endif; ?></p><?php if (!empty($project['client_reference'])): ?><p class="muted text-footnote"><?= e($project['client_reference']) ?></p><?php endif; ?></section>
        <?php if ($customFields !== []): ?>
            <section class="card" aria-labelledby="project-custom-title"><div class="card-header"><h2 class="card-header__title" id="project-custom-title"><?= e(__('custom_fields.title')) ?></h2><?php if (!empty($canConfigure)): ?><a class="card-header__action" href="<?= e(url('/settings/custom-fields')) ?>"><?= e(__('custom_fields.manage_link')) ?></a><?php endif; ?></div><dl class="detail-list">
                <?php foreach ($customFields as $field): ?>
                    <?php $value = $project['custom_fields'][$field['field_key']] ?? null; ?>
                    <?php if ($value !== null && $value !== ''): ?><div class="detail-list__item"><dt><?= e($field['label']) ?></dt><dd><?= e(is_bool($value) ? ($value ? __('common.yes') : __('common.no')) : (is_scalar($value) ? (string) $value : '')) ?></dd></div><?php endif; ?>
                <?php endforeach; ?>
            </dl></section>
        <?php endif; ?>
    </aside>
</div>
