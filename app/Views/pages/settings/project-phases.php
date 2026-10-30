<?php
declare(strict_types=1);

use App\Core\View;

$phases = is_array($phases ?? null) ? $phases : [];
$errors = is_array($errors ?? null) ? $errors : [];
$values = is_array($values ?? null) ? $values : [];
?>
<?php View::partial('partials/page-header', [
    'pageTitle' => __('project_phases.title'),
    'eyebrow' => __('settings.title'),
    'subtitle' => __('project_phases.intro'),
    'secondaryAction' => ['label' => __('custom_fields.title'), 'icon' => 'sliders', 'url' => url('/settings/custom-fields')],
]) ?>

<?php if (!empty($notice)): ?><p class="form-feedback form-feedback--success" role="status"><?= e($notice) ?></p><?php endif; ?>
<?php if (!empty($errors['general'])): ?><p class="form-feedback form-feedback--error" role="alert"><?= e($errors['general']) ?></p><?php elseif ($errors !== []): ?><p class="form-feedback form-feedback--error" role="alert"><?= e((string) reset($errors)) ?></p><?php endif; ?>

<div class="content-grid phase-settings-grid">
    <section class="card stack-md">
        <div class="card-header"><div><h2 class="card-header__title"><?= e(__('project_phases.configured')) ?></h2><p class="section-heading__note"><?= e(__('project_phases.configured_help')) ?></p></div></div>
        <?php if ($phases === []): ?><p class="muted"><?= e(__('project_phases.none')) ?></p><?php else: ?>
            <div class="phase-settings-list">
                <?php foreach ($phases as $phase): ?>
                    <form class="phase-settings-row" method="post" action="<?= e(url('/settings/project-phases/' . (int) $phase['id'])) ?>">
                        <?= csrf_field() ?>
                        <div class="phase-settings-row__title"><span class="phase-order" aria-hidden="true"><?= e((string) $phase['sort_order']) ?></span><div><strong><?= e($phase['name']) ?></strong><span class="table-primary__meta"><?= e($phase['phase_key']) ?><?= $phase['is_system'] ? ' · ' . e(__('project_phases.default_phase')) : '' ?></span></div></div>
                        <div class="field"><label class="field__label" for="phase-<?= e((string) $phase['id']) ?>-name"><?= e(__('project_phases.name')) ?></label><input class="field__control" id="phase-<?= e((string) $phase['id']) ?>-name" name="name" maxlength="150" required value="<?= e($phase['name']) ?>"></div>
                        <div class="field"><label class="field__label" for="phase-<?= e((string) $phase['id']) ?>-order"><?= e(__('project_phases.order')) ?></label><input class="field__control" id="phase-<?= e((string) $phase['id']) ?>-order" name="sort_order" type="number" min="0" max="9999" required value="<?= e((string) $phase['sort_order']) ?>"></div>
                        <div class="phase-settings-row__footer"><label class="check-label"><input type="hidden" name="is_active" value="0"><input type="checkbox" name="is_active" value="1" <?= $phase['is_active'] ? 'checked' : '' ?>> <?= e(__('project_phases.active')) ?></label><span class="badge badge--<?= $phase['is_active'] ? 'success' : 'neutral' ?>"><?= e($phase['is_active'] ? __('common.enabled') : __('common.disabled')) ?></span><button class="button button--small button--secondary" type="submit"><?= e(__('common.save')) ?></button></div>
                    </form>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>

    <aside class="card phase-create-card">
        <h2 class="card-header__title"><?= e(__('project_phases.add')) ?></h2>
        <p class="section-heading__note"><?= e(__('project_phases.add_help')) ?></p>
        <form class="settings-form" method="post" action="<?= e(url('/settings/project-phases')) ?>">
            <?= csrf_field() ?>
            <div class="field"><label class="field__label" for="new-phase-name"><?= e(__('project_phases.name')) ?></label><input class="field__control" id="new-phase-name" name="name" maxlength="150" required value="<?= e($values['name'] ?? '') ?>"><?php if (!empty($errors['name'])): ?><span class="field__hint field__hint--error"><?= e($errors['name']) ?></span><?php endif; ?></div>
            <div class="field"><label class="field__label" for="new-phase-order"><?= e(__('project_phases.order')) ?></label><input class="field__control" id="new-phase-order" name="sort_order" type="number" min="0" max="9999" value="<?= e($values['sort_order'] ?? '') ?>"><span class="field__hint"><?= e(__('project_phases.order_help')) ?></span><?php if (!empty($errors['sort_order'])): ?><span class="field__hint field__hint--error"><?= e($errors['sort_order']) ?></span><?php endif; ?></div>
            <button class="button button--primary" type="submit"><svg class="icon icon--small" aria-hidden="true"><use href="<?= e(asset('img/icons.svg')) ?>#plus"></use></svg><?= e(__('project_phases.add')) ?></button>
        </form>
    </aside>
</div>
