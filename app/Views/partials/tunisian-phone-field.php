<?php
declare(strict_types=1);

/** @var string $inputId */
/** @var string $value */
/** @var string|null $fieldError */
$inputId = (string) ($inputId ?? 'phone');
$inputName = (string) ($inputName ?? 'phone');
$fieldLabel = (string) ($fieldLabel ?? __('settings.phone'));
$value = is_scalar($value ?? null) ? (string) $value : '';
$fieldError = is_string($fieldError ?? null) ? $fieldError : '';
$autocomplete = (string) ($autocomplete ?? 'tel');
$required = !empty($required);
$fieldClass = trim((string) ($fieldClass ?? ''));
$phonePattern = (string) config('tunisia.phone.pattern', '');
$patternDelimiter = strrpos($phonePattern, '/');
$browserPattern = str_starts_with($phonePattern, '/') && $patternDelimiter > 0
    ? substr($phonePattern, 1, $patternDelimiter - 1)
    : $phonePattern;
?>
<div class="field phone-field<?= $fieldClass !== '' ? ' ' . e($fieldClass) : '' ?><?= $fieldError !== '' ? ' is-invalid' : '' ?>">
    <label class="field__label" for="<?= e($inputId) ?>"><?= e($fieldLabel) ?></label>
    <input class="field__control" id="<?= e($inputId) ?>" name="<?= e($inputName) ?>" type="tel" inputmode="tel"
        maxlength="32" autocomplete="<?= e($autocomplete) ?>" placeholder="<?= e((string) config('tunisia.phone.example', '+216 20 123 456')) ?>"
        value="<?= e($value) ?>" data-tunisian-phone data-phone-pattern="<?= e($browserPattern) ?>"
        data-phone-invalid="<?= e(__('settings.invalid_phone')) ?>"
        data-phone-valid="<?= e(__('settings.phone_valid')) ?>"
        aria-describedby="<?= e($inputId) ?>-help <?= e($inputId) ?>-status"
        aria-invalid="<?= $fieldError !== '' ? 'true' : 'false' ?>" <?= $required ? 'required' : '' ?>>
    <span class="field__hint" id="<?= e($inputId) ?>-help"><?= e(__('settings.phone_help')) ?></span>
    <span class="field__hint phone-validation<?= $fieldError !== '' ? ' field__hint--error' : '' ?>"
        id="<?= e($inputId) ?>-status" data-phone-validation aria-live="polite" <?= $fieldError !== '' ? 'role="alert"' : 'role="status"' ?>><?= e($fieldError) ?></span>
</div>
