<?php
declare(strict_types=1);

use App\Services\CustomFieldService;

/**
 * Carte d'un champ personnalisé : consultation, modification et suppression.
 *
 * La clé technique et le type de fiche sont figés (ils servent de clé de stockage) ; le libellé,
 * l'ordre, le caractère obligatoire, le type et les options sont modifiables. Le serveur refuse
 * un changement de type dès que le champ contient des valeurs ou des fichiers, et la suppression
 * annonce le contenu qui sera perdu.
 *
 * @var array<string,mixed> $field définition du champ
 * @var array{values:int,images:int,files:int,total:int} $impact contenu actuel
 * @var bool $canManage
 */
$field = is_array($field ?? null) ? $field : [];
$impact = is_array($impact ?? null) ? $impact : ['values' => 0, 'images' => 0, 'files' => 0, 'total' => 0];
$canManage = !empty($canManage);
$fieldId = (int) ($field['id'] ?? 0);
$fieldKey = (string) ($field['field_key'] ?? '');
$fieldType = (string) ($field['field_type'] ?? '');
$knownType = CustomFieldService::isKnownType($fieldType);
$isUpload = CustomFieldService::isUploadType($fieldType);
$anchor = 'custom-definition-' . $fieldId;

$confirm = __('custom_fields.delete_confirm', [
    'label' => (string) ($field['label'] ?? ''),
    'values' => (string) $impact['values'],
    'images' => (string) $impact['images'],
    'files' => (string) $impact['files'],
]);
?>
<article class="custom-definition" id="<?= e($anchor) ?>">
    <div class="custom-definition__heading">
        <div>
            <h3><?= e($field['label'] ?? '') ?></h3>
            <p class="table-primary__meta">
                <code><?= e($fieldKey) ?></code>
                · <?= e($knownType ? __('custom_fields.type.' . $fieldType) : __('custom_fields.type_invalid')) ?>
                <?php if (!empty($field['is_required'])): ?>· <?= e(__('custom_fields.required_label')) ?><?php endif; ?>
            </p>
            <?php if ($fieldType === 'select' && ($field['options'] ?? []) !== []): ?>
                <p class="field__hint"><?= e(implode(' · ', $field['options'])) ?></p>
            <?php endif; ?>
            <?php if ($isUpload): ?>
                <p class="field__hint"><?= e(__('custom_fields.' . ($fieldType === 'file' ? 'files' : 'images') . '_after_save')) ?></p>
                <p class="field__hint"><?= e(__('custom_fields.' . $fieldType . '_deactivated_note')) ?></p>
            <?php endif; ?>
            <?php if ($impact['total'] > 0): ?>
                <p class="field__hint"><?= e(__('custom_fields.usage', [
                    'values' => (string) $impact['values'],
                    'images' => (string) $impact['images'],
                    'files' => (string) $impact['files'],
                ])) ?></p>
            <?php endif; ?>
        </div>
        <span class="badge badge--<?= !empty($field['is_active']) ? 'success' : 'neutral' ?>"><?= e(!empty($field['is_active']) ? __('common.enabled') : __('common.disabled')) ?></span>
    </div>

    <?php if ($canManage): ?>
        <form class="custom-definition__edit" method="post" action="<?= e(url('/settings/custom-fields/' . $fieldId)) ?>">
            <?= csrf_field() ?>
            <div class="field">
                <label class="field__label" for="field-<?= e((string) $fieldId) ?>-label"><?= e(__('custom_fields.label')) ?></label>
                <input class="field__control" id="field-<?= e((string) $fieldId) ?>-label" name="label" maxlength="120" required value="<?= e($field['label'] ?? '') ?>">
            </div>
            <div class="field">
                <label class="field__label" for="field-<?= e((string) $fieldId) ?>-order"><?= e(__('custom_fields.order')) ?></label>
                <input class="field__control" id="field-<?= e((string) $fieldId) ?>-order" name="sort_order" type="number" min="0" max="9999" value="<?= e((string) ($field['sort_order'] ?? 0)) ?>" required>
            </div>
            <div class="field">
                <label class="field__label" for="field-<?= e((string) $fieldId) ?>-type"><?= e(__('custom_fields.type.label')) ?></label>
                <select class="field__control" id="field-<?= e((string) $fieldId) ?>-type" name="field_type">
                    <?php foreach (CustomFieldService::TYPES as $type): ?>
                        <option value="<?= e($type) ?>" <?= $type === $fieldType ? 'selected' : '' ?>><?= e(__('custom_fields.type.' . $type)) ?></option>
                    <?php endforeach; ?>
                </select>
                <span class="field__hint"><?= e($impact['total'] > 0 && $knownType
                    ? __('custom_fields.type_change_locked')
                    : __('custom_fields.type_change_hint')) ?></span>
            </div>
            <?php // Les choix n'ont de sens que pour une liste, ou pour un champ encore vide que
                  // l'on pourrait convertir en liste. ?>
            <?php if ($fieldType === 'select' || ($impact['total'] === 0 && !$isUpload)): ?>
                <div class="field custom-definition__wide">
                    <label class="field__label" for="field-<?= e((string) $fieldId) ?>-options"><?= e(__('custom_fields.options')) ?></label>
                    <textarea class="field__control" id="field-<?= e((string) $fieldId) ?>-options" name="options" rows="3" placeholder="<?= e(__('custom_fields.options_example')) ?>"><?= e(implode("\n", $field['options'] ?? [])) ?></textarea>
                    <span class="field__hint"><?= e(__('custom_fields.options_help')) ?></span>
                </div>
            <?php endif; ?>
            <?php if (!$knownType): ?>
                <p class="field__hint field__hint--error"><?= e(__('custom_fields.type_invalid_hint')) ?></p>
            <?php endif; ?>
            <div class="custom-definition__actions">
                <?php if ($isUpload): ?>
                    <span class="field__hint"><?= e(__('custom_fields.' . ($fieldType === 'file' ? 'file' : 'image') . '_not_required')) ?></span>
                <?php else: ?>
                    <label class="check-label">
                        <input type="hidden" name="is_required" value="0">
                        <input type="checkbox" name="is_required" value="1" <?= !empty($field['is_required']) ? 'checked' : '' ?>>
                        <?= e(__('custom_fields.required_label')) ?>
                    </label>
                <?php endif; ?>
                <button class="button button--small button--secondary" type="submit"><?= e(__('common.save')) ?></button>
            </div>
        </form>

        <div class="custom-definition__footer">
            <form class="custom-definition__toggle" method="post" action="<?= e(url('/settings/custom-fields/' . $fieldId . '/toggle')) ?>">
                <?= csrf_field() ?>
                <input type="hidden" name="is_active" value="<?= !empty($field['is_active']) ? '0' : '1' ?>">
                <button class="button button--small button--ghost" type="submit"><?= e(!empty($field['is_active']) ? __('custom_fields.deactivate') : __('custom_fields.activate')) ?></button>
            </form>
            <form class="custom-definition__delete" method="post"
                action="<?= e(url('/settings/custom-fields/' . $fieldId . '/delete')) ?>"
                data-confirm="<?= e($confirm) ?>">
                <?= csrf_field() ?>
                <button class="button button--small button--danger" type="submit"><?= e(__('custom_fields.delete')) ?></button>
            </form>
        </div>
    <?php endif; ?>
</article>
