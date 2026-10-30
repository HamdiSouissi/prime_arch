<?php
declare(strict_types=1);

use App\Core\Translator;
use App\Core\View;

$settings = is_array($settings ?? null) ? $settings : [];
$errors = is_array($errors ?? null) ? $errors : [];
$governorates = is_array($governorates ?? null) ? $governorates : [];
$delegations = is_array($delegations ?? null) ? $delegations : [];
$vatRates = array_map('trim', explode(',', (string) ($settings['vat_rates'] ?? '19,13,7,0')));
?>
<?php View::partial('partials/page-header', [
    'pageTitle' => __('settings.title'),
    'subtitle' => 'Identité du cabinet, adresse et paramètres applicables aux futurs documents.',
    'eyebrow' => 'Espace sécurisé',
    'secondaryAction' => !empty($canManageUsers) ? ['label' => __('settings.admin_users'), 'icon' => 'people', 'url' => url('/admin/users')] : null,
    'primaryAction' => !empty($canViewRates) ? ['label' => __('finance.rates_title'), 'icon' => 'currency', 'url' => url('/settings/hourly-rates')] : null,
]) ?>

<?php if (!empty($notice)): ?>
    <p class="form-feedback form-feedback--success" role="status"><?= e($notice) ?></p>
<?php endif; ?>
<?php if (!empty($errors['general'])): ?>
    <p class="form-feedback form-feedback--error" role="alert"><?= e($errors['general']) ?></p>
<?php endif; ?>

<form class="foundation-settings" method="post" action="<?= e(url('/settings')) ?>" enctype="multipart/form-data" data-geography-form
      data-delegations-url="<?= e(url('/api/geography/delegations')) ?>"
      data-postal-codes-url="<?= e(url('/api/geography/postal-codes')) ?>">
    <?= csrf_field() ?>
    <fieldset class="foundation-settings__fieldset" <?= empty($canManage) ? 'disabled' : '' ?>>
        <section class="card settings-panel" aria-labelledby="settings-cabinet-title">
            <div class="card-header">
                <div>
                    <h2 class="card-header__title" id="settings-cabinet-title"><?= e(__('settings.cabinet')) ?></h2>
                    <p class="section-heading__note">Ces informations seront reprises dans les documents du cabinet.</p>
                </div>
            </div>
            <div class="settings-form">
                <div class="field">
                    <label class="field__label" for="cabinet-name"><?= e(__('settings.name')) ?></label>
                    <input class="field__control" id="cabinet-name" name="name" maxlength="150" required value="<?= e($settings['name'] ?? '') ?>" autocomplete="organization">
                    <?php if (!empty($errors['name'])): ?><span class="field__hint field__hint--error"><?= e($errors['name']) ?></span><?php endif; ?>
                </div>
                <div class="field">
                    <label class="field__label" for="cabinet-address"><?= e(__('settings.address')) ?></label>
                    <textarea class="field__control" id="cabinet-address" name="address" maxlength="255" autocomplete="street-address"><?= e($settings['address'] ?? '') ?></textarea>
                    <?php if (!empty($errors['address'])): ?><span class="field__hint field__hint--error"><?= e($errors['address']) ?></span><?php endif; ?>
                </div>
                <div class="foundation-settings__grid">
                    <?php View::partial('partials/tunisian-phone-field', [
                        'inputId' => 'cabinet-phone',
                        'value' => $settings['phone'] ?? '',
                        'fieldError' => $errors['phone'] ?? '',
                    ]) ?>
                    <div class="field">
                        <label class="field__label" for="cabinet-email"><?= e(__('settings.email')) ?></label>
                        <input class="field__control" id="cabinet-email" name="email" type="email" maxlength="190" value="<?= e($settings['email'] ?? '') ?>" autocomplete="email">
                        <?php if (!empty($errors['email'])): ?><span class="field__hint field__hint--error"><?= e($errors['email']) ?></span><?php endif; ?>
                    </div>
                </div>
                <div class="foundation-settings__grid">
                    <div class="field">
                        <label class="field__label" for="cabinet-tax-id"><?= e(__('settings.tax_id')) ?></label>
                        <input class="field__control" id="cabinet-tax-id" name="tax_id" maxlength="32" value="<?= e($settings['tax_id'] ?? '') ?>" placeholder="1234567/A/M/000" autocomplete="off">
                        <?php if (!empty($errors['tax_id'])): ?><span class="field__hint field__hint--error"><?= e($errors['tax_id']) ?></span><?php endif; ?>
                    </div>
                    <div class="field">
                        <label class="field__label" for="cabinet-rib"><?= e(__('settings.rib')) ?></label>
                        <input class="field__control" id="cabinet-rib" name="rib" maxlength="50" value="<?= e($settings['rib'] ?? '') ?>" autocomplete="off">
                        <?php if (!empty($errors['rib'])): ?><span class="field__hint field__hint--error"><?= e($errors['rib']) ?></span><?php endif; ?>
                    </div>
                </div>
                <div class="foundation-settings__grid">
                    <div class="field">
                        <label class="field__label" for="cabinet-governorate"><?= e(__('settings.governorate')) ?></label>
                        <select class="field__control" id="cabinet-governorate" name="governorate_id" data-governorate-select>
                            <option value="">Choisir un gouvernorat</option>
                            <?php foreach ($governorates as $governorate): ?>
                                <option value="<?= e((string) $governorate['id']) ?>" <?= (string) ($settings['governorate_id'] ?? '') === (string) $governorate['id'] ? 'selected' : '' ?>><?= e(Translator::locale() === 'ar' ? $governorate['name_ar'] : $governorate['name_fr']) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <?php if (!empty($errors['governorate_id'])): ?><span class="field__hint field__hint--error"><?= e($errors['governorate_id']) ?></span><?php endif; ?>
                    </div>
                    <div class="field">
                        <label class="field__label" for="cabinet-delegation"><?= e(__('settings.delegation')) ?></label>
                        <select class="field__control" id="cabinet-delegation" name="delegation_id" data-delegation-select data-selected="<?= e($settings['delegation_id'] ?? '') ?>">
                            <option value="">Choisir une délégation</option>
                            <?php foreach ($delegations as $delegation): ?>
                                <option value="<?= e((string) $delegation['id']) ?>" <?= (string) ($settings['delegation_id'] ?? '') === (string) $delegation['id'] ? 'selected' : '' ?>><?= e(Translator::locale() === 'ar' ? $delegation['name_ar'] : $delegation['name_latin']) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <?php if (!empty($errors['delegation_id'])): ?><span class="field__hint field__hint--error"><?= e($errors['delegation_id']) ?></span><?php endif; ?>
                    </div>
                </div>
                <p class="field__hint"><?= e(__('settings.geography_source_note')) ?></p>
                <div class="field">
                    <label class="field__label" for="cabinet-postal-code"><?= e(__('settings.postal_code')) ?></label>
                    <input class="field__control" id="cabinet-postal-code" name="postal_code" inputmode="numeric" pattern="[0-9]{4}" maxlength="4" value="<?= e($settings['postal_code'] ?? '') ?>" placeholder="1000" list="cabinet-postal-options" data-postal-input>
                    <datalist id="cabinet-postal-options" data-postal-options></datalist>
                    <span class="field__hint"><?= e(__('settings.postal_code_help')) ?></span>
                    <?php if (!empty($errors['postal_code'])): ?><span class="field__hint field__hint--error"><?= e($errors['postal_code']) ?></span><?php endif; ?>
                </div>
                <div class="settings-logo-row">
                    <img src="<?= e($logoUrl ?? asset('img/logo-placeholder.svg')) ?>" alt="Logo du cabinet" width="64" height="64">
                    <div>
                        <strong><?= e(__('settings.logo')) ?></strong>
                        <p class="muted text-footnote"><?= e(__('settings.logo_help')) ?></p>
                        <input class="field__control" type="file" name="logo" accept="image/jpeg,image/png,image/webp" aria-label="<?= e(__('settings.logo')) ?>">
                        <?php if (!empty($errors['logo'])): ?><span class="field__hint field__hint--error"><?= e($errors['logo']) ?></span><?php endif; ?>
                    </div>
                    <?php if (!empty($settings['logo'])): ?>
                        <label class="check-label"><input type="checkbox" name="remove_logo" value="1"> <?= e(__('settings.remove_logo')) ?></label>
                    <?php endif; ?>
                </div>
            </div>
        </section>

        <section class="card settings-panel" aria-labelledby="settings-fiscal-title">
            <div class="card-header">
                <div>
                    <h2 class="card-header__title" id="settings-fiscal-title"><?= e(__('settings.fiscal')) ?></h2>
                    <p class="section-heading__note"><?= e(__('settings.legal_warning')) ?></p>
                </div>
            </div>
            <div class="settings-form">
                <div class="field">
                    <label class="field__label" for="vat-rates"><?= e(__('settings.vat_rates')) ?></label>
                    <input class="field__control" id="vat-rates" name="vat_rates" inputmode="numeric" maxlength="80" value="<?= e($settings['vat_rates'] ?? '19,13,7,0') ?>" required>
                    <span class="field__hint"><?= e('Exemple : 19,13,7,0. Chaque taux doit être un entier entre 0 et 100.') ?></span>
                    <?php if (!empty($errors['vat_rates'])): ?><span class="field__hint field__hint--error"><?= e($errors['vat_rates']) ?></span><?php endif; ?>
                </div>
                <div class="foundation-settings__grid">
                    <div class="field">
                        <label class="field__label" for="vat-default"><?= e(__('settings.vat_default')) ?></label>
                        <select class="field__control" id="vat-default" name="vat_default" required>
                            <?php foreach ($vatRates as $rate): ?>
                                <option value="<?= e((string) $rate) ?>" <?= (string) ($settings['vat_default'] ?? '') === (string) $rate ? 'selected' : '' ?>><?= e((string) $rate) ?> %</option>
                            <?php endforeach; ?>
                        </select>
                        <?php if (!empty($errors['vat_default'])): ?><span class="field__hint field__hint--error"><?= e($errors['vat_default']) ?></span><?php endif; ?>
                    </div>
                    <div class="field">
                        <label class="field__label" for="stamp-duty"><?= e(__('settings.stamp_duty')) ?></label>
                        <input class="field__control" id="stamp-duty" name="stamp_duty" inputmode="decimal" maxlength="13" value="<?= e($settings['stamp_duty'] ?? '1.000') ?>" required>
                        <?php if (!empty($errors['stamp_duty'])): ?><span class="field__hint field__hint--error"><?= e($errors['stamp_duty']) ?></span><?php endif; ?>
                    </div>
                    <div class="field">
                        <label class="field__label" for="withholding-rate"><?= e(__('settings.withholding')) ?></label>
                        <input class="field__control" id="withholding-rate" name="withholding_rate" inputmode="decimal" maxlength="7" value="<?= e($settings['withholding_rate'] ?? '0.000') ?>" required>
                        <?php if (!empty($errors['withholding_rate'])): ?><span class="field__hint field__hint--error"><?= e($errors['withholding_rate']) ?></span><?php endif; ?>
                    </div>
                </div>
            </div>
        </section>

        <?php if (!empty($canManage)): ?>
            <div class="form-actions"><button class="button button--primary" type="submit"><?= e(__('settings.save')) ?></button></div>
        <?php else: ?>
            <p class="muted text-footnote">Vous disposez d'un accès en lecture seule à ces réglages.</p>
        <?php endif; ?>
    </fieldset>
</form>

<?php if (!empty($canManageModules)): ?>
    <section class="card settings-panel margin-block-start-6" aria-labelledby="settings-modules-title">
        <div class="card-header">
            <div>
                <h2 class="card-header__title" id="settings-modules-title">Modules et extensions</h2>
                <p class="section-heading__note">Installez des extensions vérifiées et gérez leurs dépendances.</p>
            </div>
            <a class="button button--secondary button--small" href="<?= e(url('/settings/modules')) ?>">Gérer les modules</a>
        </div>
        <p class="muted text-footnote">Seuls les administrateurs peuvent téléverser et activer du code de module.</p>
    </section>
<?php endif; ?>
