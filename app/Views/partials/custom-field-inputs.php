<?php
declare(strict_types=1);

use App\Services\CustomFieldService;

/** @var array<int,array<string,mixed>> $customFields */
/** @var array<string,mixed> $values */
/** @var array<string,mixed> $errors */
$customPrefix = (string) ($customPrefix ?? 'custom');
$customValues = is_array($values['custom_fields'] ?? null) ? $values['custom_fields'] : [];
$customErrors = is_array($errors['custom_fields'] ?? null) ? $errors['custom_fields'] : [];
// Un champ « image » ou « fichier » s'alimente depuis la fiche enregistrée : le formulaire de
// la fiche ne transporte pas de fichiers (il n'est pas en multipart) et le projet peut ne pas
// exister encore.
$customProjectId = (int) ($customProjectId ?? 0);
?>
<?php if ($customFields !== []): ?>
    <div class="form-grid">
        <?php foreach ($customFields as $field): ?>
            <?php
            $key = (string) $field['field_key'];
            $fieldId = $customPrefix . '-' . $key;
            $value = $customValues[$key] ?? '';
            $error = $customErrors[$key] ?? '';
            ?>
            <div class="field<?= in_array($field['field_type'], ['checkbox', 'image', 'file'], true) ? ' form-grid__full' : '' ?>">
                <?php if (in_array($field['field_type'], ['image', 'file'], true)): ?>
                    <?php $isFileField = $field['field_type'] === 'file'; ?>
                    <span class="field__label"><?= e($field['label']) ?></span>
                    <p class="field__hint">
                        <?= e(__('custom_fields.' . ($isFileField ? 'files_after_save' : 'images_after_save'))) ?>
                        <?php if ($customProjectId > 0): ?>
                            <a href="<?= e(url('/projects/' . $customProjectId) . '#custom-field-' . $key) ?>"><?= e(__('custom_fields.' . ($isFileField ? 'files_open_project' : 'images_open_project'))) ?></a>
                        <?php endif; ?>
                    </p>
                <?php elseif (!in_array($field['field_type'] ?? '', CustomFieldService::VALUE_TYPES, true)): ?>
                    <?php // Type inconnu (base antérieure à la migration 0015) : jamais rendu comme une
                          // zone de texte, qui ferait croire à une saisie alors qu'aucun fichier n'est
                          // possible. L'administrateur corrige le type dans les réglages. ?>
                    <span class="field__label"><?= e($field['label']) ?></span>
                    <p class="field__hint field__hint--error"><?= e(__('custom_fields.type_invalid_hint')) ?></p>
                <?php elseif ($field['field_type'] === 'checkbox'): ?>
                    <label class="check-label" for="<?= e($fieldId) ?>">
                        <input id="<?= e($fieldId) ?>" type="checkbox" name="custom_fields[<?= e($key) ?>]" value="1" <?= !empty($value) ? 'checked' : '' ?> <?= $field['is_required'] ? 'required' : '' ?>>
                        <?= e($field['label']) ?><?= $field['is_required'] ? ' *' : '' ?>
                    </label>
                <?php else: ?>
                    <label class="field__label" for="<?= e($fieldId) ?>"><?= e($field['label']) ?><?= $field['is_required'] ? ' *' : '' ?></label>
                    <?php if ($field['field_type'] === 'select'): ?>
                        <select class="field__control" id="<?= e($fieldId) ?>" name="custom_fields[<?= e($key) ?>]" <?= $field['is_required'] ? 'required' : '' ?>>
                            <option value=""><?= e(__('common.choose')) ?></option>
                            <?php foreach ($field['options'] as $option): ?>
                                <option value="<?= e($option) ?>" <?= (string) $value === $option ? 'selected' : '' ?>><?= e($option) ?></option>
                            <?php endforeach; ?>
                        </select>
                    <?php else: ?>
                        <input class="field__control" id="<?= e($fieldId) ?>" name="custom_fields[<?= e($key) ?>]" type="<?= e($field['field_type']) ?>" <?= $field['field_type'] === 'number' ? 'step="any"' : '' ?> <?= $field['field_type'] === 'text' ? 'maxlength="2000"' : '' ?> value="<?= e(is_scalar($value) ? (string) $value : '') ?>" <?= $field['is_required'] ? 'required' : '' ?>>
                    <?php endif; ?>
                <?php endif; ?>
                <?php if ($error !== ''): ?><span class="field__hint field__hint--error"><?= e($error) ?></span><?php endif; ?>
            </div>
        <?php endforeach; ?>
    </div>
<?php else: ?>
    <p class="field__hint"><?= e(__('custom_fields.none_configured')) ?> <?php if (!empty($canConfigureFields)): ?><a href="<?= e(url('/settings/custom-fields')) ?>"><?= e(__('custom_fields.manage_link')) ?></a><?php endif; ?></p>
<?php endif; ?>
