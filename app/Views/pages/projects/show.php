<?php
declare(strict_types=1);

use App\Core\Auth;
use App\Core\Translator;
use App\Core\View;
use App\Services\CustomFieldService;

$project = is_array($project ?? null) ? $project : [];
$projectImages = is_array($projectImages ?? null) ? $projectImages : [];
$imageErrors = is_array($imageErrors ?? null) ? $imageErrors : [];
$phases = is_array($phases ?? null) ? $phases : [];
$phaseSummaries = is_array($phaseSummaries ?? null) ? $phaseSummaries : [];
$projectImages = is_array($projectImages ?? null) ? $projectImages : [];
$imageErrors = is_array($imageErrors ?? null) ? $imageErrors : [];
$customFields = is_array($customFields ?? null) ? $customFields : [];
// Un champ « image » possède sa propre carte (galerie + envoi), un champ « fichier » sa propre
// liste de plans AutoCAD : aucun des deux n'apparaît dans la liste de valeurs, où il n'aurait
// qu'une case vide faute de valeur JSON.
$imageFields = array_values(array_filter(
    $customFields,
    static fn (array $field): bool => ($field['field_type'] ?? '') === 'image'
));
$fileFields = array_values(array_filter(
    $customFields,
    static fn (array $field): bool => ($field['field_type'] ?? '') === 'file'
));
$valueFields = array_values(array_filter(
    $customFields,
    static fn (array $field): bool => in_array($field['field_type'] ?? '', CustomFieldService::VALUE_TYPES, true)
));
// Définitions au type vide ou inconnu (base antérieure à la migration 0015) : signalées une
// fois, plutôt que rendues comme une valeur texte vide qui laisserait croire à une saisie.
$invalidFieldTypes = array_values(array_filter(
    $customFields,
    static fn (array $field): bool => !CustomFieldService::isKnownType((string) ($field['field_type'] ?? ''))
));
$customImages = is_array($customImages ?? null) ? $customImages : [];
$customImageNotices = is_array($customImageNotices ?? null) ? $customImageNotices : [];
$customImageErrors = is_array($customImageErrors ?? null) ? $customImageErrors : [];
$customFiles = is_array($customFiles ?? null) ? $customFiles : [];
$customFileNotices = is_array($customFileNotices ?? null) ? $customFileNotices : [];
$customFileErrors = is_array($customFileErrors ?? null) ? $customFileErrors : [];
$errors = is_array($errors ?? null) ? $errors : [];
$timeSummary = is_array($timeSummary ?? null) ? $timeSummary : null;
$planValues = is_array($planValues ?? null) ? $planValues : [];
$submittedPhases = is_array($planValues['phases'] ?? null)
    ? array_filter($planValues['phases'], 'is_array')
    : [];
$projectStatus = (string) ($project['status'] ?? 'planning');
$completedPhases = count(array_filter($phases, static fn (array $phase): bool => $phase['status'] === 'completed'));
$weightTotal = 0.0;
$weightedValue = 0.0;
foreach ($phases as $phase) {
    $weight = (float) $phase['weight_percent'];
    $weightTotal += $weight;
    $weightedValue += $weight * (float) $phase['progress_percent'];
}
$progress = $weightTotal > 0 ? (int) round($weightedValue / $weightTotal) : 0;
$phaseSummaryById = [];
foreach ($phaseSummaries as $phaseSummary) {
    $phaseSummaryById[(int) $phaseSummary['id']] = $phaseSummary;
}
$addressParts = array_filter([
    $project['address_line'] ?? '',
    $project['city'] ?? '',
    Translator::locale() === 'ar' ? ($project['delegation_name_ar'] ?? '') : ($project['delegation_name'] ?? ''),
    Translator::locale() === 'ar' ? ($project['governorate_name_ar'] ?? '') : ($project['governorate_name'] ?? ''),
    $project['postal_code'] ?? '',
], static fn (mixed $part): bool => is_string($part) && $part !== '');
$canEditPlan = !empty($canManage) && !in_array($projectStatus, ['completed', 'cancelled'], true);
?>
<?php if (!empty($notice)): ?><p class="form-feedback form-feedback--success" role="status"><?= e($notice) ?></p><?php endif; ?>
<?php if (!empty($errors['general'])): ?><p class="form-feedback form-feedback--error" role="alert"><?= e($errors['general']) ?></p><?php endif; ?>
<?php $sections = is_array($sections ?? null) ? $sections : []; ?>
<?php View::partial('partials/page-header', [
    'pageTitle' => (string) $project['name'],
    'breadcrumb' => is_array($breadcrumb ?? null) ? $breadcrumb : [],
    'eyebrow' => __('projects.record') . ' · ' . (string) ($project['reference'] ?? ''),
    'subtitle' => __('projects.status.' . $projectStatus) . ' · ' . (string) ($project['project_type'] ?? __('projects.type_unspecified')),
    'secondaryAction' => ['label' => __('projects.back_to_list'), 'icon' => 'arrow-left', 'url' => url('/projects')],
    'primaryAction' => !empty($canManage) ? ['label' => __('projects.edit'), 'icon' => 'edit', 'url' => url('/projects/' . (int) $project['id'] . '/edit')] : null,
]) ?>

<?php View::partial('partials/context-links', ['links' => $sections]) ?>

<div class="content-grid project-detail-grid">
    <div class="stack-lg">
        <section class="card" aria-labelledby="project-summary-title">
            <div class="card-header"><div><h2 class="card-header__title" id="project-summary-title"><?= e(__('projects.information')) ?></h2><p class="section-heading__note"><?= e(__('projects.client_label')) ?> <?php if (Auth::can('clients.view')): ?><a href="<?= e(url('/clients/' . (int) $project['client_id'])) ?>"><?= e($project['client_name']) ?></a><?php else: ?><?= e($project['client_name']) ?><?php endif; ?></p></div><span class="badge badge--<?= e(match ($projectStatus) { 'active' => 'info', 'completed' => 'success', 'on_hold' => 'warning', 'cancelled' => 'neutral', default => 'neutral' }) ?>"><?= e(__('projects.status.' . $projectStatus)) ?></span></div>
            <dl class="detail-list">
                <div class="detail-list__item"><dt><?= e(__('projects.type')) ?></dt><dd><?= e($project['project_type'] !== '' ? $project['project_type'] : '—') ?></dd></div>
                <div class="detail-list__item"><dt><?= e(__('projects.site_address')) ?></dt><dd><?= e($addressParts !== [] ? implode(', ', $addressParts) : '—') ?></dd></div>
                <div class="detail-list__item"><dt><?= e(__('projects.start_date')) ?></dt><dd><?= e(!empty($project['start_date']) ? date('d/m/Y', strtotime((string) $project['start_date'])) : __('projects.not_scheduled')) ?></dd></div>
                <div class="detail-list__item"><dt><?= e(__('projects.target_end_date')) ?></dt><dd><?= e(!empty($project['target_end_date']) ? date('d/m/Y', strtotime((string) $project['target_end_date'])) : __('projects.not_scheduled')) ?></dd></div>
                <?php if (!empty($project['budget_hours'])): ?><div class="detail-list__item"><dt><?= e(__('projects.budget_hours')) ?></dt><dd><?= e((string) $project['budget_hours']) ?> h</dd></div><?php endif; ?>
                <?php if (!empty($canViewTime) && $timeSummary !== null): ?><div class="detail-list__item"><dt><?= e(__('projects.actual_hours')) ?></dt><dd><?= e($timeSummary['hours']) ?> h</dd></div><?php endif; ?>
                <?php if (!empty($canViewCosts) && $timeSummary !== null): ?>
                    <div class="detail-list__item"><dt><?= e(__('projects.actual_cost')) ?></dt><dd><?= e(money_format_tnd((string) ($timeSummary['cost'] ?? '0.000'))) ?></dd></div>
                    <?php if ($timeSummary['fee_budget'] !== null): ?><div class="detail-list__item"><dt><?= e(__('projects.fee_budget')) ?></dt><dd><?= e(money_format_tnd($timeSummary['fee_budget'])) ?></dd></div><?php endif; ?>
                <?php endif; ?>
                <div class="detail-list__item detail-list__item--stack"><dt><?= e(__('projects.description')) ?></dt><dd><?= !empty($project['description']) ? nl2br(e($project['description'])) : '—' ?></dd></div>
            </dl>
        </section>

        <section class="card stack-md" aria-labelledby="project-images-title">
            <div class="card-header">
                <div>
                    <h2 class="card-header__title" id="project-images-title"><?= e(__('projects.images')) ?></h2>
                    <p class="section-heading__note"><?= e(__('projects.images_help')) ?></p>
                </div>
                <span class="badge badge--neutral"><?= e((string) count($projectImages)) ?></span>
            </div>
            <?php if (!empty($imageNotice)): ?>
                <p class="form-feedback form-feedback--success" role="status"><?= e($imageNotice) ?></p>
            <?php endif; ?>
            <?php if (!empty($imageErrors['image'])): ?>
                <p class="form-feedback form-feedback--error" role="alert"><?= e($imageErrors['image']) ?></p>
            <?php endif; ?>
            <?php if ($projectImages === []): ?>
                <p class="muted text-footnote"><?= e(__('projects.images_empty')) ?></p>
            <?php else: ?>
                <div class="project-image-grid">
                    <?php foreach ($projectImages as $image): ?>
                        <figure class="project-image-card">
                            <a class="project-image-link" href="<?= e(url('/projects/' . (int) $project['id'] . '/images/' . (int) $image['id'])) ?>"
                                target="_blank" rel="noopener noreferrer"
                                aria-label="<?= e(__('projects.image_open', ['name' => $image['original_name']])) ?>">
                                <img src="<?= e(url('/projects/' . (int) $project['id'] . '/images/' . (int) $image['id'])) ?>"
                                    alt="<?= e($image['original_name']) ?>" loading="lazy" decoding="async"
                                    width="<?= e((string) $image['width']) ?>" height="<?= e((string) $image['height']) ?>">
                            </a>
                            <figcaption class="project-image-caption">
                                <strong><?= e($image['original_name']) ?></strong>
                                <span><?= e(__('projects.image_dimensions', [
                                    'width' => (string) $image['width'],
                                    'height' => (string) $image['height'],
                                ])) ?></span>
                                <time datetime="<?= e($image['created_at']) ?>"><?= e(date('d/m/Y', strtotime($image['created_at']))) ?></time>
                            </figcaption>
                        </figure>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            <?php if (!empty($canManage)): ?>
                <form class="project-image-upload" method="post"
                    action="<?= e(url('/projects/' . (int) $project['id'] . '/images')) ?>"
                    enctype="multipart/form-data">
                    <?= csrf_field() ?>
                    <div class="field">
                        <label class="field__label" for="project-image-upload"><?= e(__('projects.image_select')) ?></label>
                        <input class="field__control" id="project-image-upload" name="image" type="file"
                            accept="image/jpeg,image/png,image/webp" required>
                        <span class="field__hint"><?= e(__('projects.image_upload_help')) ?></span>
                    </div>
                    <button class="button button--secondary" type="submit"><?= e(__('projects.image_upload')) ?></button>
                </form>
            <?php endif; ?>
        </section>

        <?php if ($imageFields !== []): ?>
            <?php View::partial('partials/project-custom-images', [
                'project' => $project,
                'imageFields' => $imageFields,
                'customImages' => $customImages,
                'customImageNotices' => $customImageNotices,
                'customImageErrors' => $customImageErrors,
                'canManage' => !empty($canManage),
            ]) ?>
        <?php endif; ?>

        <?php if ($fileFields !== []): ?>
            <?php View::partial('partials/project-custom-files', [
                'project' => $project,
                'fileFields' => $fileFields,
                'customFiles' => $customFiles,
                'customFileNotices' => $customFileNotices,
                'customFileErrors' => $customFileErrors,
                'canManage' => !empty($canManage),
            ]) ?>
        <?php endif; ?>

        <?php if ($canEditPlan): ?>
            <section class="card stack-md" aria-labelledby="project-plan-title">
                <div class="card-header"><div><h2 class="card-header__title" id="project-plan-title"><?= e(__('projects.plan_title')) ?></h2><p class="section-heading__note"><?= e(__('projects.plan_help')) ?></p></div></div>
                <?php if (!empty($errors['plan'])): ?><p class="form-feedback form-feedback--error" role="alert"><?= e($errors['plan']) ?></p><?php endif; ?>
                <?php if (!empty($errors['weight_percent'])): ?><p class="form-feedback form-feedback--error" role="alert"><?= e($errors['weight_percent']) ?></p><?php endif; ?>
                <?php if (!empty($errors['budget_hours'])): ?><p class="form-feedback form-feedback--error" role="alert"><?= e($errors['budget_hours']) ?></p><?php endif; ?>
                <?php if (!empty($errors['phase_budget_hours'])): ?><p class="form-feedback form-feedback--error" role="alert"><?= e($errors['phase_budget_hours']) ?></p><?php endif; ?>
                <form class="settings-form" method="post" action="<?= e(url('/projects/' . (int) $project['id'] . '/plan')) ?>">
                    <?= csrf_field() ?>
                    <div class="field"><label class="field__label" for="project-plan-budget"><?= e(__('projects.budget_hours')) ?></label><input class="field__control" id="project-plan-budget" name="budget_hours" type="number" min="0" step="0.01" value="<?= e($planValues['budget_hours'] ?? $project['budget_hours'] ?? '') ?>"></div>
                    <?php foreach ($phases as $phase): ?>
                        <fieldset class="form-section">
                            <legend class="field__label"><?= e($phase['phase_name']) ?></legend>
                            <div class="form-grid">
                                <div class="field"><label class="field__label" for="phase-weight-<?= e((string) $phase['id']) ?>"><?= e(__('projects.phase_weight')) ?></label><input class="field__control" id="phase-weight-<?= e((string) $phase['id']) ?>" name="phases[<?= e((string) $phase['id']) ?>][weight_percent]" type="number" min="0" max="100" step="0.01" required value="<?= e($submittedPhases[$phase['id']]['weight_percent'] ?? $phase['weight_percent']) ?>"></div>
                                <div class="field"><label class="field__label" for="phase-budget-<?= e((string) $phase['id']) ?>"><?= e(__('projects.phase_budget_hours')) ?></label><input class="field__control" id="phase-budget-<?= e((string) $phase['id']) ?>" name="phases[<?= e((string) $phase['id']) ?>][budget_hours]" type="number" min="0" step="0.01" value="<?= e($submittedPhases[$phase['id']]['budget_hours'] ?? $phase['budget_hours'] ?? '') ?>"></div>
                            </div>
                        </fieldset>
                    <?php endforeach; ?>
                    <div class="form-actions"><button class="button button--primary" type="submit"><?= e(__('common.save')) ?></button></div>
                </form>
            </section>
        <?php endif; ?>

        <section class="card phase-card" aria-labelledby="project-phases-title">
            <div class="card-header"><div><h2 class="card-header__title" id="project-phases-title"><?= e(__('project_phases.title')) ?></h2><p class="section-heading__note"><?= e(__('project_phases.progress', ['done' => (string) $completedPhases, 'total' => (string) count($phases)])) ?> · <?= e(__('projects.weighted_progress')) ?> : <?= e((string) $progress) ?> %</p></div><?php if (!empty($canConfigure)): ?><a class="button button--small button--secondary" href="<?= e(url('/settings/project-phases')) ?>"><?= e(__('project_phases.manage')) ?></a><?php endif; ?></div>
            <?php if ($phases === []): ?>
                <?php View::partial('partials/empty-state', ['title' => __('project_phases.none'), 'description' => __('project_phases.none_help')]) ?>
            <?php else: ?>
                <div class="progress progress--compact" role="progressbar" aria-valuenow="<?= e((string) $progress) ?>" aria-valuemin="0" aria-valuemax="100" aria-label="<?= e(__('projects.weighted_progress')) ?>"><span class="progress__bar" style="--progress: <?= e((string) $progress) ?>%"></span></div>
                <ol class="timeline project-phase-timeline">
                    <?php foreach ($phases as $phase): ?>
                        <?php $phaseStatus = $phase['status']; $phaseSummary = $phaseSummaryById[(int) $phase['id']] ?? null; ?>
                        <li class="timeline__item<?= $phaseStatus === 'completed' ? ' is-complete' : '' ?><?= $phaseStatus === 'in_progress' ? ' is-current' : '' ?>">
                            <span class="timeline__marker" aria-hidden="true"><svg class="icon icon--small"><use href="<?= e(asset('img/icons.svg')) ?>#<?= e(match ($phaseStatus) { 'completed' => 'check', default => 'plan' }) ?>"></use></svg></span>
                            <span class="timeline__content"><strong class="timeline__title"><?= e($phase['phase_name']) ?></strong><span class="timeline__meta"><?= e(__('project_phases.status.' . $phaseStatus)) ?> · <?= e(__('projects.phase_weight')) ?> <?= e($phase['weight_percent']) ?> % · <?= e(__('projects.phase_progress')) ?> <?= e($phase['progress_percent']) ?> %<?php if ($phase['started_at'] !== ''): ?> · <?= e(__('project_phases.started_on', ['date' => date('d/m/Y', strtotime($phase['started_at']))])) ?><?php endif; ?><?php if ($phase['completed_at'] !== ''): ?> · <?= e(__('project_phases.completed_on', ['date' => date('d/m/Y', strtotime($phase['completed_at']))])) ?><?php endif; ?><?php if (!empty($canViewTime) && $phaseSummary !== null): ?> · <?= e(__('projects.phase_hours')) ?> <?= e($phaseSummary['actual_hours']) ?> h<?php if ($phaseSummary['budget_hours'] !== null): ?> / <?= e($phaseSummary['budget_hours']) ?> h<?php endif; ?><?php endif; ?><?php if (!empty($canViewCosts) && $phaseSummary !== null): ?> · <?= e(__('projects.phase_cost')) ?> <?= e(money_format_tnd($phaseSummary['actual_cost'])) ?><?php endif; ?></span></span>
                            <?php if (!empty($canManage) && !in_array($projectStatus, ['completed', 'cancelled'], true)): ?>
                                <?php if ($phaseStatus === 'in_progress'): ?>
                                    <form class="cluster" method="post" action="<?= e(url('/projects/' . (int) $project['id'] . '/phases/' . (int) $phase['id'] . '/progress')) ?>">
                                        <?= csrf_field() ?><label class="u-sr-only" for="phase-progress-<?= e((string) $phase['id']) ?>"><?= e(__('projects.phase_progress')) ?></label><input class="field__control" id="phase-progress-<?= e((string) $phase['id']) ?>" name="progress_percent" type="number" min="0" max="100" step="0.01" value="<?= e($phase['progress_percent']) ?>"><button class="button button--small button--secondary" type="submit"><?= e(__('common.save')) ?></button>
                                    </form>
                                <?php endif; ?>
                                <?php if ($phaseStatus === 'pending'): ?><form method="post" action="<?= e(url('/projects/' . (int) $project['id'] . '/phases/' . (int) $phase['id'])) ?>"><?= csrf_field() ?><input type="hidden" name="action" value="start"><button class="button button--small button--secondary" type="submit"><?= e(__('project_phases.start')) ?></button></form><?php elseif ($phaseStatus === 'in_progress'): ?><form method="post" action="<?= e(url('/projects/' . (int) $project['id'] . '/phases/' . (int) $phase['id'])) ?>"><?= csrf_field() ?><input type="hidden" name="action" value="complete"><button class="button button--small button--primary" type="submit"><?= e(__('project_phases.complete')) ?></button></form><?php endif; ?>
                            <?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                </ol>
                <?php if (!empty($errors['progress'])): ?><p class="form-feedback form-feedback--error" role="alert"><?= e($errors['progress']) ?></p><?php endif; ?>
            <?php endif; ?>
        </section>

        <?php if (($canViewTime ?? false) || ($canViewCosts ?? false)): ?>
            <section class="card stack-md" aria-labelledby="project-phase-metrics-title">
                <div class="card-header"><div><h2 class="card-header__title" id="project-phase-metrics-title"><?= e(__('projects.time_detail')) ?></h2><p class="section-heading__note"><?= e(__('projects.actual_hours')) ?>: <?= e($timeSummary['hours'] ?? '0.00') ?> h</p></div><?php if (!empty($canViewTime)): ?><a class="card-header__action" href="<?= e(url('/time?' . http_build_query(['project_id' => (int) $project['id']]))) ?>"><?= e(__('projects.time_detail_link')) ?></a><?php endif; ?></div>
                <?php if ($phaseSummaries !== []): ?>
                    <div class="table-wrap"><table class="data-table"><thead><tr><th scope="col"><?= e(__('projects.phase')) ?></th><th scope="col"><?= e(__('projects.phase_weight')) ?></th><th scope="col"><?= e(__('projects.phase_hours')) ?></th><?php if (!empty($canViewCosts)): ?><th scope="col"><?= e(__('projects.phase_cost')) ?></th><?php if ($timeSummary !== null && $timeSummary['fee_budget'] !== null): ?><th scope="col"><?= e(__('projects.phase_fee_share')) ?></th><th scope="col"><?= e(__('projects.phase_margin')) ?></th><th scope="col"><?= e(__('projects.phase_margin_rate')) ?></th><?php endif; ?><?php endif; ?></tr></thead><tbody>
                        <?php foreach ($phaseSummaries as $phaseSummary): ?>
                            <tr><td><?= e($phaseSummary['phase_name']) ?></td><td><?= e($phaseSummary['weight_percent']) ?> %</td><td><?= e($phaseSummary['actual_hours']) ?> h<?php if ($phaseSummary['budget_hours'] !== null): ?> / <?= e($phaseSummary['budget_hours']) ?> h<?php endif; ?></td><?php if (!empty($canViewCosts)): ?><td><?= e(money_format_tnd($phaseSummary['actual_cost'])) ?></td><?php if ($timeSummary !== null && $timeSummary['fee_budget'] !== null): ?><td><?= e(money_format_tnd($phaseSummary['allocated_fee'] ?? '0.000')) ?></td><td><?= $phaseSummary['phase_margin'] !== null ? e(money_format_tnd($phaseSummary['phase_margin'])) : '—' ?></td><td><?= $phaseSummary['phase_margin_rate'] !== null ? e(str_replace('.', ',', $phaseSummary['phase_margin_rate'])) . ' %' : '—' ?></td><?php endif; ?><?php endif; ?></tr>
                        <?php endforeach; ?>
                    </tbody></table></div>
                <?php endif; ?>
            </section>
        <?php endif; ?>
    </div>

    <aside class="stack-lg">
        <section class="card" aria-labelledby="project-client-title"><div class="card-header"><h2 class="card-header__title" id="project-client-title"><?= e(__('projects.client_label')) ?></h2></div><p class="text-headline"><?php if (Auth::can('clients.view')): ?><a href="<?= e(url('/clients/' . (int) $project['client_id'])) ?>"><?= e($project['client_name']) ?></a><?php else: ?><?= e($project['client_name']) ?><?php endif; ?></p><?php if (!empty($project['client_reference'])): ?><p class="muted text-footnote"><?= e($project['client_reference']) ?></p><?php endif; ?></section>
        <?php if (!empty($canViewCosts) && $timeSummary !== null): ?>
            <section class="card stack-md" aria-labelledby="project-profitability-title">
                <div class="card-header"><div><h2 class="card-header__title" id="project-profitability-title"><?= e(__('projects.gross_margin')) ?></h2><p class="section-heading__note"><?= e(__('projects.margin_rate')) ?></p></div></div>
                <dl class="detail-list">
                    <div class="detail-list__item"><dt><?= e(__('projects.fee_budget')) ?></dt><dd><?= $timeSummary['fee_budget'] !== null ? e(money_format_tnd($timeSummary['fee_budget'])) : '—' ?></dd></div>
                    <div class="detail-list__item"><dt><?= e(__('projects.actual_cost')) ?></dt><dd><?= e(money_format_tnd((string) ($timeSummary['cost'] ?? '0.000'))) ?></dd></div>
                    <div class="detail-list__item"><dt><?= e(__('projects.gross_margin')) ?></dt><dd><?= $timeSummary['gross_margin'] !== null ? e(money_format_tnd($timeSummary['gross_margin'])) : '—' ?></dd></div>
                    <div class="detail-list__item"><dt><?= e(__('projects.margin_rate')) ?></dt><dd><?= $timeSummary['margin_rate'] !== null ? e(str_replace('.', ',', $timeSummary['margin_rate'])) . ' %' : '—' ?></dd></div>
                </dl>
                <p class="field__hint"><?= e(__('finance.profitability')) ?></p>
            </section>
        <?php endif; ?>
        <?php if ($valueFields !== [] || $invalidFieldTypes !== []): ?>
            <section class="card" aria-labelledby="project-custom-title"><div class="card-header"><h2 class="card-header__title" id="project-custom-title"><?= e(__('custom_fields.title')) ?></h2><?php if (!empty($canConfigure)): ?><a class="card-header__action" href="<?= e(url('/settings/custom-fields')) ?>"><?= e(__('custom_fields.manage_link')) ?></a><?php endif; ?></div>
                <?php foreach ($invalidFieldTypes as $invalidField): ?>
                    <p class="form-feedback form-feedback--error" role="alert"><?= e(__('custom_fields.type_invalid_field', ['label' => $invalidField['label']])) ?></p>
                <?php endforeach; ?>
                <dl class="detail-list">
                <?php foreach ($valueFields as $field): ?>
                    <?php $value = $project['custom_fields'][$field['field_key']] ?? null; ?>
                    <?php if ($value !== null && $value !== ''): ?><div class="detail-list__item"><dt><?= e($field['label']) ?></dt><dd><?= e(is_bool($value) ? ($value ? __('common.yes') : __('common.no')) : (is_scalar($value) ? (string) $value : '')) ?></dd></div><?php endif; ?>
                <?php endforeach; ?>
            </dl></section>
        <?php endif; ?>
    </aside>
</div>
