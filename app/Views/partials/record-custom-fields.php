<?php
declare(strict_types=1);

use App\Services\CustomFieldService;

/**
 * Valeurs des champs personnalisés sur une fiche client ou projet.
 *
 * Tous les champs actifs apparaissent, y compris ceux qui n'ont pas encore de valeur : un
 * champ créé depuis la fiche (ou depuis les réglages) reste ainsi visible et se renseigne
 * directement ici, sans repasser par le formulaire complet de la fiche.
 *
 * Les champs « image » et « fichier » n'ont pas de valeur texte : ils disposent de leurs
 * propres cartes (galerie, plans AutoCAD) et sont donc exclus de cette liste par l'appelant.
 *
 * @var string $entityType « client » ou « project »
 * @var int $recordId identifiant de la fiche affichée
 * @var array<int,array<string,mixed>> $fields définitions actives de type valeur
 * @var array<string,mixed> $values valeurs enregistrées sur la fiche
 * @var bool $canEdit droits d'écriture sur la fiche (clients.manage / projects.manage)
 */
$entityType = ($entityType ?? '') === 'client' ? 'client' : 'project';
$recordId = (int) ($recordId ?? 0);
$fields = is_array($fields ?? null) ? $fields : [];
$values = is_array($values ?? null) ? $values : [];
$canEdit = !empty($canEdit);
$basePath = $entityType === 'client' ? '/clients/' : '/projects/';
$prefix = 'record-' . $entityType . '-' . $recordId;
// Seuls les types saisis dans un formulaire ont une valeur ici : « image » et « fichier »
// possèdent leurs propres cartes sur la fiche projet.
$renderable = array_values(array_filter(
    $fields,
    static fn (array $field): bool => (string) ($field['field_key'] ?? '') !== ''
        && in_array((string) ($field['field_type'] ?? ''), CustomFieldService::VALUE_TYPES, true)
));
?>
<?php if ($renderable === []): ?>
    <p class="field__hint"><?= e(__('custom_fields.none_active')) ?></p>
<?php else: ?>
    <dl class="detail-list">
        <?php foreach ($renderable as $field): ?>
            <?php
            $key = (string) ($field['field_key'] ?? '');
            $type = (string) ($field['field_type'] ?? '');
            $value = $values[$key] ?? null;
            $filled = $value !== null && $value !== '';
            $inputId = $prefix . '-' . $key;
            $label = (string) ($field['label'] ?? $key) . (!empty($field['is_required']) ? ' *' : '');
            ?>
            <div class="detail-list__item custom-value" id="custom-field-<?= e($key) ?>">
                <dt><?= e($label) ?></dt>
                <dd>
                    <span class="custom-value__display">
                        <?php if ($filled): ?>
                            <?= e(is_bool($value) ? ($value ? __('common.yes') : __('common.no')) : (is_scalar($value) ? (string) $value : '')) ?>
                        <?php else: ?>
                            <span class="muted"><?= e(__('custom_fields.not_filled')) ?></span>
                        <?php endif; ?>
                    </span>
                    <?php if ($canEdit): ?>
                        <details class="custom-value__edit">
                            <summary class="custom-value__summary">
                                <?= e($filled ? __('custom_fields.edit_value') : __('custom_fields.set_value')) ?>
                            </summary>
                            <form class="custom-value__form" method="post"
                                  action="<?= e(url($basePath . $recordId . '/custom-fields/' . rawurlencode($key) . '/value')) ?>">
                                <?= csrf_field() ?>
                                <?php if ($type === 'checkbox'): ?>
                                    <label class="check-label" for="<?= e($inputId) ?>">
                                        <input type="hidden" name="value" value="0">
                                        <input id="<?= e($inputId) ?>" type="checkbox" name="value" value="1" <?= !empty($value) ? 'checked' : '' ?>>
                                        <?= e(__('custom_fields.checkbox_checked')) ?>
                                    </label>
                                <?php elseif ($type === 'select'): ?>
                                    <label class="field__label" for="<?= e($inputId) ?>"><?= e(__('custom_fields.value_label')) ?></label>
                                    <select class="field__control" id="<?= e($inputId) ?>" name="value">
                                        <option value=""><?= e(__('custom_fields.not_filled')) ?></option>
                                        <?php foreach ((array) ($field['options'] ?? []) as $option): ?>
                                            <option value="<?= e((string) $option) ?>" <?= (string) $value === (string) $option ? 'selected' : '' ?>><?= e((string) $option) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                <?php else: ?>
                                    <label class="field__label" for="<?= e($inputId) ?>"><?= e(__('custom_fields.value_label')) ?></label>
                                    <input class="field__control" id="<?= e($inputId) ?>" name="value"
                                           type="<?= e($type) ?>" <?= $type === 'number' ? 'step="any"' : '' ?>
                                           <?= $type === 'text' ? 'maxlength="2000"' : '' ?>
                                           value="<?= e(is_scalar($value) ? (string) $value : '') ?>">
                                <?php endif; ?>
                                <button class="button button--small button--primary" type="submit">
                                    <?= e(__('custom_fields.save_value')) ?>
                                </button>
                            </form>
                        </details>
                    <?php endif; ?>
                </dd>
            </div>
        <?php endforeach; ?>
    </dl>
<?php endif; ?>
