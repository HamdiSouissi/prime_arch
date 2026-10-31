<?php
declare(strict_types=1);

use App\Core\View;
use App\Services\CustomFieldService;

$clientFields = is_array($clientFields ?? null) ? $clientFields : [];
$projectFields = is_array($projectFields ?? null) ? $projectFields : [];
$errors = is_array($errors ?? null) ? $errors : [];
$values = is_array($values ?? null) ? $values : [];
$impacts = is_array($impacts ?? null) ? $impacts : [];
$canManageClients = !empty($canManageClients);
$canManageProjects = !empty($canManageProjects);
?>
<?php View::partial('partials/page-header', [
    'pageTitle' => __('custom_fields.title'),
    'eyebrow' => __('settings.title'),
    'subtitle' => __('custom_fields.intro'),
    'secondaryAction' => $canManageProjects ? ['label' => __('project_phases.manage'), 'icon' => 'briefcase', 'url' => url('/settings/project-phases')] : null,
]) ?>

<?php if (!empty($notice)): ?><p class="form-feedback form-feedback--success" role="status"><?= e($notice) ?></p><?php endif; ?>
<?php if (!empty($errors['general'])): ?><p class="form-feedback form-feedback--error" role="alert"><?= e($errors['general']) ?></p><?php elseif ($errors !== []): ?><p class="form-feedback form-feedback--error" role="alert"><?= e((string) reset($errors)) ?></p><?php endif; ?>

<div class="content-grid custom-fields-settings-grid">
    <section class="card stack-md">
        <?php if ($canManageClients): ?>
            <section class="custom-fields-group" aria-labelledby="client-fields-title">
                <div class="card-header"><div><h2 class="card-header__title" id="client-fields-title"><?= e(__('custom_fields.client_fields')) ?></h2><p class="section-heading__note"><?= e(__('custom_fields.client_help')) ?></p></div><span class="badge badge--neutral"><?= e((string) count($clientFields)) ?></span></div>
                <?php if ($clientFields === []): ?><p class="muted text-footnote"><?= e(__('custom_fields.none_in_group')) ?></p><?php else: ?>
                    <div class="custom-definition-list">
                        <?php foreach ($clientFields as $field): ?>
                            <?php View::partial('partials/custom-field-definition', [
                                'field' => $field,
                                'impact' => $impacts[(int) $field['id']] ?? ['values' => 0, 'images' => 0, 'files' => 0, 'total' => 0],
                                'canManage' => true,
                            ]) ?>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </section>
        <?php endif; ?>

        <?php if ($canManageProjects): ?>
            <section class="custom-fields-group" aria-labelledby="project-fields-title">
                <div class="card-header"><div><h2 class="card-header__title" id="project-fields-title"><?= e(__('custom_fields.project_fields')) ?></h2><p class="section-heading__note"><?= e(__('custom_fields.project_help')) ?></p></div><span class="badge badge--neutral"><?= e((string) count($projectFields)) ?></span></div>
                <?php if ($projectFields === []): ?><p class="muted text-footnote"><?= e(__('custom_fields.none_in_group')) ?></p><?php else: ?>
                    <div class="custom-definition-list">
                        <?php foreach ($projectFields as $field): ?>
                            <?php View::partial('partials/custom-field-definition', [
                                'field' => $field,
                                'impact' => $impacts[(int) $field['id']] ?? ['values' => 0, 'images' => 0, 'files' => 0, 'total' => 0],
                                'canManage' => true,
                            ]) ?>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </section>
        <?php endif; ?>
    </section>

    <aside class="card custom-field-create-card">
        <h2 class="card-header__title"><?= e(__('custom_fields.add')) ?></h2>
        <p class="section-heading__note"><?= e(__('custom_fields.add_help')) ?></p>
        <form class="settings-form" method="post" action="<?= e(url('/settings/custom-fields')) ?>">
            <?= csrf_field() ?>
            <div class="field"><label class="field__label" for="field-entity"><?= e(__('custom_fields.entity')) ?></label>
                <?php if ($canManageClients && $canManageProjects): ?><select class="field__control" id="field-entity" name="entity_type" required><option value="client" <?= ($values['entity_type'] ?? 'client') === 'client' ? 'selected' : '' ?>><?= e(__('custom_fields.client_fields')) ?></option><option value="project" <?= ($values['entity_type'] ?? '') === 'project' ? 'selected' : '' ?>><?= e(__('custom_fields.project_fields')) ?></option></select><?php elseif ($canManageClients): ?><input type="hidden" id="field-entity" name="entity_type" value="client"><span class="field__control field__control--display"><?= e(__('custom_fields.client_fields')) ?></span><?php else: ?><input type="hidden" id="field-entity" name="entity_type" value="project"><span class="field__control field__control--display"><?= e(__('custom_fields.project_fields')) ?></span><?php endif; ?>
                <?php if (!empty($errors['entity_type'])): ?><span class="field__hint field__hint--error"><?= e($errors['entity_type']) ?></span><?php endif; ?>
            </div>
            <div class="field"><label class="field__label" for="field-key"><?= e(__('custom_fields.key')) ?></label><input class="field__control" id="field-key" name="field_key" maxlength="80" pattern="[a-z][a-z0-9_]{1,79}" required value="<?= e($values['field_key'] ?? '') ?>" placeholder="<?= e(__('custom_fields.key_example')) ?>"><span class="field__hint"><?= e(__('custom_fields.key_help')) ?></span><?php if (!empty($errors['field_key'])): ?><span class="field__hint field__hint--error"><?= e($errors['field_key']) ?></span><?php endif; ?></div>
            <div class="field"><label class="field__label" for="field-label"><?= e(__('custom_fields.label')) ?></label><input class="field__control" id="field-label" name="label" maxlength="120" required value="<?= e($values['label'] ?? '') ?>"><?php if (!empty($errors['label'])): ?><span class="field__hint field__hint--error"><?= e($errors['label']) ?></span><?php endif; ?></div>
            <div class="field"><label class="field__label" for="field-type"><?= e(__('custom_fields.type.label')) ?></label><select class="field__control" id="field-type" name="field_type" required><?php foreach (CustomFieldService::TYPES as $type): ?><option value="<?= e($type) ?>" <?= ($values['field_type'] ?? 'text') === $type ? 'selected' : '' ?>><?= e(__('custom_fields.type.' . $type)) ?></option><?php endforeach; ?></select><span class="field__hint"><?= e(__('custom_fields.image_help')) ?></span><span class="field__hint"><?= e(__('custom_fields.file_help')) ?></span><?php if (!empty($errors['field_type'])): ?><span class="field__hint field__hint--error"><?= e($errors['field_type']) ?></span><?php endif; ?></div>
            <div class="field"><label class="field__label" for="field-options"><?= e(__('custom_fields.options')) ?></label><textarea class="field__control" id="field-options" name="options" rows="4" placeholder="<?= e(__('custom_fields.options_example')) ?>"><?= e($values['options'] ?? '') ?></textarea><span class="field__hint"><?= e(__('custom_fields.options_help')) ?></span><?php if (!empty($errors['options'])): ?><span class="field__hint field__hint--error"><?= e($errors['options']) ?></span><?php endif; ?></div>
            <div class="form-grid">
                <div class="field"><label class="field__label" for="field-order"><?= e(__('custom_fields.order')) ?></label><input class="field__control" id="field-order" name="sort_order" type="number" min="0" max="9999" value="<?= e($values['sort_order'] ?? '10') ?>"></div>
                <div class="field field--align-end"><label class="check-label"><input type="hidden" name="is_required" value="0"><input type="checkbox" name="is_required" value="1" <?= in_array($values['is_required'] ?? null, ['1', 1, true, 'on'], true) ? 'checked' : '' ?>> <?= e(__('custom_fields.required_label')) ?></label></div>
            </div>
            <?php if (!empty($errors['sort_order'])): ?><span class="field__hint field__hint--error"><?= e($errors['sort_order']) ?></span><?php endif; ?>
            <button class="button button--primary" type="submit"><svg class="icon icon--small" aria-hidden="true"><use href="<?= e(asset('img/icons.svg')) ?>#plus"></use></svg><?= e(__('custom_fields.add')) ?></button>
        </form>
    </aside>
</div>
