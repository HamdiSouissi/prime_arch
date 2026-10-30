<?php
declare(strict_types=1);

use App\Core\Translator;

/** @var array<string,mixed> $values */
/** @var array<string,mixed> $errors */
/** @var array<int,array<string,mixed>> $governorates */
/** @var array<int,array<string,mixed>> $delegations */
$addressPrefix = (string) ($addressPrefix ?? 'address');
$selectedGovernorate = (string) ($values['governorate_id'] ?? '');
$selectedDelegation = (string) ($values['delegation_id'] ?? '');
?>
<div class="form-grid">
    <div class="field form-grid__full">
        <label class="field__label" for="<?= e($addressPrefix) ?>-line"><?= e(__('address.line')) ?></label>
        <input class="field__control" id="<?= e($addressPrefix) ?>-line" name="address_line" maxlength="255" autocomplete="street-address" value="<?= e($values['address_line'] ?? '') ?>">
        <?php if (!empty($errors['address_line'])): ?><span class="field__hint field__hint--error"><?= e($errors['address_line']) ?></span><?php endif; ?>
    </div>
    <div class="field">
        <label class="field__label" for="<?= e($addressPrefix) ?>-city"><?= e(__('address.city')) ?></label>
        <input class="field__control" id="<?= e($addressPrefix) ?>-city" name="city" maxlength="120" autocomplete="address-level2" value="<?= e($values['city'] ?? '') ?>">
        <?php if (!empty($errors['city'])): ?><span class="field__hint field__hint--error"><?= e($errors['city']) ?></span><?php endif; ?>
    </div>
    <div class="field">
        <label class="field__label" for="<?= e($addressPrefix) ?>-governorate"><?= e(__('settings.governorate')) ?></label>
        <select class="field__control" id="<?= e($addressPrefix) ?>-governorate" name="governorate_id" data-governorate-select>
            <option value=""><?= e(__('address.choose_governorate')) ?></option>
            <?php foreach ($governorates as $governorate): ?>
                <option value="<?= e((string) $governorate['id']) ?>" <?= $selectedGovernorate === (string) $governorate['id'] ? 'selected' : '' ?>><?= e(Translator::locale() === 'ar' ? $governorate['name_ar'] : $governorate['name_fr']) ?></option>
            <?php endforeach; ?>
        </select>
        <?php if (!empty($errors['governorate_id'])): ?><span class="field__hint field__hint--error"><?= e($errors['governorate_id']) ?></span><?php endif; ?>
    </div>
    <div class="field">
        <label class="field__label" for="<?= e($addressPrefix) ?>-delegation"><?= e(__('settings.delegation')) ?></label>
        <select class="field__control" id="<?= e($addressPrefix) ?>-delegation" name="delegation_id" data-delegation-select data-selected="<?= e($selectedDelegation) ?>" <?= $selectedGovernorate === '' ? 'disabled' : '' ?>>
            <option value=""><?= e(__('address.choose_delegation')) ?></option>
            <?php foreach ($delegations as $delegation): ?>
                <option value="<?= e((string) $delegation['id']) ?>" <?= $selectedDelegation === (string) $delegation['id'] ? 'selected' : '' ?>><?= e(Translator::locale() === 'ar' ? $delegation['name_ar'] : $delegation['name_latin']) ?></option>
            <?php endforeach; ?>
        </select>
        <?php if (!empty($errors['delegation_id'])): ?><span class="field__hint field__hint--error"><?= e($errors['delegation_id']) ?></span><?php endif; ?>
    </div>
    <div class="field">
        <label class="field__label" for="<?= e($addressPrefix) ?>-postal-code"><?= e(__('settings.postal_code')) ?></label>
        <input class="field__control" id="<?= e($addressPrefix) ?>-postal-code" name="postal_code" inputmode="numeric" pattern="[0-9]{4}" maxlength="4" placeholder="1000" list="<?= e($addressPrefix) ?>-postal-options" data-postal-input value="<?= e($values['postal_code'] ?? '') ?>">
        <datalist id="<?= e($addressPrefix) ?>-postal-options" data-postal-options></datalist>
        <span class="field__hint"><?= e(__('address.postal_help')) ?></span>
        <?php if (!empty($errors['postal_code'])): ?><span class="field__hint field__hint--error"><?= e($errors['postal_code']) ?></span><?php endif; ?>
    </div>
</div>
