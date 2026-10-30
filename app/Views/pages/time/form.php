<?php
declare(strict_types=1);

use App\Core\View;

$values = is_array($values ?? null) ? $values : [];
$entry = is_array($entry ?? null) ? $entry : null;
$projects = is_array($projects ?? null) ? $projects : [];
$errors = is_array($errors ?? null) ? $errors : [];
$entryId = $entry === null ? 0 : (int) $entry['id'];
$selectedProjectId = (string) ($values['project_id'] ?? '');
$selectedPhaseId = (string) ($values['phase_assignment_id'] ?? $values['project_phase_assignment_id'] ?? '');
$formAction = $entry === null ? url('/time') : url('/time/' . $entryId);
?>
<?php View::partial('partials/page-header', [
    'pageTitle' => $title,
    'eyebrow' => $entry === null ? __('time.title') : (string) $entry['project_name'],
    'subtitle' => __('time.intro'),
    'secondaryAction' => ['label' => __('common.cancel'), 'icon' => 'arrow-left', 'url' => url('/time')],
]) ?>

<?php if (!empty($errors['general'])): ?><p class="form-feedback form-feedback--error" role="alert"><?= e($errors['general']) ?></p><?php endif; ?>
<?php if (!empty($errors['rate'])): ?><p class="form-feedback form-feedback--error" role="alert"><?= e($errors['rate']) ?></p><?php endif; ?>
<?php if ($projects === []): ?>
    <?php View::partial('partials/empty-state', ['title' => __('time.no_projects'), 'description' => __('time.no_projects_help')]) ?>
<?php else: ?>
    <form class="card settings-form" method="post" action="<?= e($formAction) ?>" data-time-entry-form>
        <?= csrf_field() ?>
        <div class="field">
            <label class="field__label" for="time-project"><?= e(__('time.project')) ?></label>
            <select class="field__control" id="time-project" name="project_id" data-time-project-select required>
                <option value=""><?= e(__('time.choose_project')) ?></option>
                <?php foreach ($projects as $project): ?>
                    <option value="<?= e((string) $project['id']) ?>" <?= $selectedProjectId === (string) $project['id'] ? 'selected' : '' ?>><?= e($project['name']) ?><?= $project['reference'] !== '' ? ' · ' . e($project['reference']) : '' ?> · <?= e($project['client_name']) ?></option>
                <?php endforeach; ?>
            </select>
            <?php if (!empty($errors['project_id'])): ?><span class="field__hint field__hint--error"><?= e($errors['project_id']) ?></span><?php endif; ?>
        </div>
        <div class="field">
            <label class="field__label" for="time-phase"><?= e(__('time.phase')) ?></label>
            <select class="field__control" id="time-phase" name="phase_assignment_id" data-time-phase-select required>
                <option value=""><?= e(__('time.choose_phase')) ?></option>
                <?php foreach ($projects as $project): ?>
                    <optgroup label="<?= e($project['name'] . ' · ' . $project['client_name']) ?>">
                        <?php foreach ($project['phases'] as $phase): ?>
                            <option value="<?= e((string) $phase['id']) ?>" data-project-id="<?= e((string) $project['id']) ?>" <?= $selectedPhaseId === (string) $phase['id'] ? 'selected' : '' ?>><?= e($phase['name']) ?></option>
                        <?php endforeach; ?>
                    </optgroup>
                <?php endforeach; ?>
            </select>
            <?php if (!empty($errors['phase_assignment_id'])): ?><span class="field__hint field__hint--error"><?= e($errors['phase_assignment_id']) ?></span><?php endif; ?>
        </div>
        <div class="field">
            <label class="field__label" for="time-description"><?= e(__('time.description')) ?></label>
            <textarea class="field__control" id="time-description" name="description" rows="3" maxlength="500" required><?= e($values['description'] ?? '') ?></textarea>
            <span class="field__hint"><?= e(__('time.description_help')) ?></span>
            <?php if (!empty($errors['description'])): ?><span class="field__hint field__hint--error"><?= e($errors['description']) ?></span><?php endif; ?>
        </div>
        <div class="form-grid">
            <div class="field">
                <label class="field__label" for="time-duration"><?= e(__('time.duration')) ?></label>
                <input class="field__control" id="time-duration" type="number" name="duration_hours" inputmode="decimal" step="0.25" min="0.25" max="24" required value="<?= e($values['duration_hours'] ?? '1.00') ?>">
                <span class="field__hint"><?= e(__('time.duration_help')) ?></span>
                <?php if (!empty($errors['duration_hours'])): ?><span class="field__hint field__hint--error"><?= e($errors['duration_hours']) ?></span><?php endif; ?>
            </div>
            <div class="field">
                <label class="field__label" for="time-work-date"><?= e(__('time.work_date')) ?></label>
                <input class="field__control" id="time-work-date" type="date" name="work_date" max="<?= e(date('Y-m-d')) ?>" required value="<?= e($values['work_date'] ?? '') ?>">
                <?php if (!empty($errors['work_date'])): ?><span class="field__hint field__hint--error"><?= e($errors['work_date']) ?></span><?php endif; ?>
            </div>
        </div>
        <div class="field">
            <input type="hidden" name="is_billable" value="0">
            <label class="check-label"><input type="checkbox" name="is_billable" value="1" <?= !array_key_exists('is_billable', $values) || in_array($values['is_billable'], [true, 1, '1', 'on'], true) ? 'checked' : '' ?>> <?= e(__('time.billable')) ?></label>
            <?php if (!empty($errors['is_billable'])): ?><span class="field__hint field__hint--error"><?= e($errors['is_billable']) ?></span><?php endif; ?>
        </div>
        <div class="form-actions"><button class="button button--primary" type="submit"><?= e(__('time.save')) ?></button></div>
    </form>
<?php endif; ?>
