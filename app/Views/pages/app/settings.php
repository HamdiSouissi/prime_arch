<?php
declare(strict_types=1);

use App\Core\Translator;
use App\Core\View;

$settings = is_array($settings ?? null) ? $settings : [];
$errors = is_array($errors ?? null) ? $errors : [];
$governorates = is_array($governorates ?? null) ? $governorates : [];
$delegations = is_array($delegations ?? null) ? $delegations : [];
$vatRates = array_map('trim', explode(',', (string) ($settings['vat_rates'] ?? '19,13,7,0')));
$backups = is_array($backups ?? null) ? $backups : [];
$backupErrors = is_array($backupErrors ?? null) ? $backupErrors : [];
$canBackup = !empty($canBackup);
$backupConfirmationWord = (string) ($backupConfirmationWord ?? 'RESTAURER');
$backupImportLimit = (string) ($backupImportLimit ?? '');
$backupStoragePath = (string) ($backupStoragePath ?? 'storage/backups/');
// Les archives sont horodatées en UTC ; l'affichage suit le fuseau de l'application.
$backupMoment = static function (array $backup): array {
    $timezone = new DateTimeZone(date_default_timezone_get());
    try {
        $moment = is_string($backup['created_at'] ?? null) && $backup['created_at'] !== ''
            ? new DateTimeImmutable((string) $backup['created_at'])
            : (new DateTimeImmutable('@' . (int) ($backup['modified_at'] ?? time())));
    } catch (Throwable) {
        $moment = new DateTimeImmutable('@' . (int) ($backup['modified_at'] ?? time()));
    }
    $moment = $moment->setTimezone($timezone);
    return ['date' => $moment->format('d/m/Y'), 'time' => $moment->format('H:i'), 'iso' => $moment->format('c')];
};
$backupSize = static function (int $bytes): string {
    if ($bytes >= 1_048_576) {
        return number_format($bytes / 1_048_576, 1, ',', ' ') . ' Mio';
    }
    if ($bytes >= 1024) {
        return number_format($bytes / 1024, 0, ',', ' ') . ' Kio';
    }
    return max(0, $bytes) . ' o';
};
$restorableBackups = array_values(array_filter(
    $backups,
    static fn (array $backup): bool => !empty($backup['valid'])
));
?>
<?php View::partial('partials/page-header', [
    'pageTitle' => __('settings.title'),
    'subtitle' => __('settings.subtitle'),
    'eyebrow' => __('app.secure_workspace'),
    'secondaryAction' => !empty($canManageUsers) ? ['label' => __('settings.admin_users'), 'icon' => 'people', 'url' => url('/admin/users')] : null,
    'primaryAction' => !empty($canViewRates) ? ['label' => __('finance.rates_title'), 'icon' => 'currency', 'url' => url('/settings/hourly-rates')] : null,
]) ?>

<?php if (!empty($notice)): ?>
    <p class="form-feedback form-feedback--success" role="status"><?= e($notice) ?></p>
<?php endif; ?>
<?php if (!empty($errors['general'])): ?>
    <p class="form-feedback form-feedback--error" role="alert"><?= e($errors['general']) ?></p>
<?php endif; ?>

<?php // Sans accès aux sauvegardes, la page reste une simple colonne : aucun onglet isolé. ?>
<div class="settings-layout<?= $canBackup ? '' : ' settings-layout--single' ?>">
    <?php if ($canBackup): ?>
        <nav class="settings-nav" role="tablist" aria-label="<?= e(__('settings.tabs_label')) ?>" data-tab-group data-tab-panels="#settings-panels">
            <button class="settings-nav__item" type="button" role="tab" id="settings-tab-cabinet" aria-selected="true" aria-controls="settings-panel-cabinet" data-tab-target="settings-panel-cabinet">
                <svg class="icon" aria-hidden="true"><use href="<?= e(asset('img/icons.svg')) ?>#building"></use></svg>
                <?= e(__('settings.tab_cabinet')) ?>
            </button>
            <button class="settings-nav__item" type="button" role="tab" id="settings-tab-sauvegardes" aria-selected="false" aria-controls="sauvegardes" data-tab-target="sauvegardes" tabindex="-1">
                <svg class="icon" aria-hidden="true"><use href="<?= e(asset('img/icons.svg')) ?>#shield"></use></svg>
                <?= e(__('settings.tab_backups')) ?>
            </button>
        </nav>
    <?php endif; ?>

    <div class="settings-panels" id="settings-panels">
        <section class="settings-panel-group" id="settings-panel-cabinet" role="tabpanel" aria-labelledby="settings-tab-cabinet" data-tab-panel>

<form class="foundation-settings" method="post" action="<?= e(url('/settings')) ?>" enctype="multipart/form-data" data-geography-form
      data-delegations-url="<?= e(url('/api/geography/delegations')) ?>"
      data-postal-codes-url="<?= e(url('/api/geography/postal-codes')) ?>">
    <?= csrf_field() ?>
    <fieldset class="foundation-settings__fieldset" <?= empty($canManage) ? 'disabled' : '' ?>>
        <section class="card settings-panel" aria-labelledby="settings-cabinet-title">
            <div class="card-header">
                <div>
                    <h2 class="card-header__title" id="settings-cabinet-title"><?= e(__('settings.cabinet')) ?></h2>
                    <p class="section-heading__note"><?= e(__('settings.intro')) ?></p>
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
                            <option value=""><?= e(__('settings.choose_governorate')) ?></option>
                            <?php foreach ($governorates as $governorate): ?>
                                <option value="<?= e((string) $governorate['id']) ?>" <?= (string) ($settings['governorate_id'] ?? '') === (string) $governorate['id'] ? 'selected' : '' ?>><?= e(Translator::locale() === 'ar' ? $governorate['name_ar'] : $governorate['name_fr']) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <?php if (!empty($errors['governorate_id'])): ?><span class="field__hint field__hint--error"><?= e($errors['governorate_id']) ?></span><?php endif; ?>
                    </div>
                    <div class="field">
                        <label class="field__label" for="cabinet-delegation"><?= e(__('settings.delegation')) ?></label>
                        <select class="field__control" id="cabinet-delegation" name="delegation_id" data-delegation-select data-selected="<?= e($settings['delegation_id'] ?? '') ?>">
                            <option value=""><?= e(__('settings.choose_delegation')) ?></option>
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
                    <img src="<?= e($logoUrl ?? asset('img/logo-placeholder.svg')) ?>" alt="<?= e(__('settings.logo_alt')) ?>" width="64" height="64">
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
                    <span class="field__hint"><?= e(__('settings.vat_rates_help')) ?></span>
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
            <p class="muted text-footnote"><?= e(__('settings.readonly')) ?></p>
        <?php endif; ?>
    </fieldset>
</form>

<?php if (!empty($canManageModules)): ?>
    <section class="card settings-panel margin-block-start-6" aria-labelledby="settings-modules-title">
        <div class="card-header">
            <div>
                <h2 class="card-header__title" id="settings-modules-title"><?= e(__('settings.modules_title')) ?></h2>
                <p class="section-heading__note"><?= e(__('settings.modules_text')) ?></p>
            </div>
            <a class="button button--secondary button--small" href="<?= e(url('/settings/modules')) ?>"><?= e(__('settings.modules_action')) ?></a>
        </div>
        <p class="muted text-footnote"><?= e(__('settings.modules_admin_hint')) ?></p>
    </section>
<?php endif; ?>
        </section>

        <?php if ($canBackup): ?>
        <section class="settings-panel-group" id="sauvegardes" role="tabpanel" aria-labelledby="settings-tab-sauvegardes" data-tab-panel hidden>
            <?php if (!empty($backupNotice)): ?>
                <p class="form-feedback form-feedback--success" role="status"><?= e((string) $backupNotice) ?></p>
            <?php endif; ?>
            <?php foreach ($backupErrors as $backupError): ?>
                <p class="form-feedback form-feedback--error" role="alert"><?= e((string) $backupError) ?></p>
            <?php endforeach; ?>

            <section class="card settings-panel" aria-labelledby="settings-backup-create-title">
                <div class="card-header">
                    <div>
                        <h2 class="card-header__title" id="settings-backup-create-title"><?= e(__('backup.create_title')) ?></h2>
                        <p class="section-heading__note"><?= e(__('backup.create_help')) ?></p>
                    </div>
                    <span class="badge badge--neutral">.zip</span>
                </div>
                <div class="settings-form">
                    <form method="post" action="<?= e(url('/settings/backups')) ?>">
                        <?= csrf_field() ?>
                        <button class="button button--primary" type="submit">
                            <svg class="icon icon--small" aria-hidden="true"><use href="<?= e(asset('img/icons.svg')) ?>#shield"></use></svg>
                            <?= e(__('backup.create_action')) ?>
                        </button>
                    </form>
                    <p class="field__hint"><?= e(__('backup.create_note', ['path' => $backupStoragePath])) ?></p>
                </div>
            </section>

            <section class="card settings-panel margin-block-start-6" aria-labelledby="settings-backup-import-title">
                <div class="card-header">
                    <div>
                        <h2 class="card-header__title" id="settings-backup-import-title"><?= e(__('backup.import_title')) ?></h2>
                        <p class="section-heading__note"><?= e(__('backup.import_help', ['size' => $backupImportLimit])) ?></p>
                    </div>
                </div>
                <div class="settings-form">
                    <form class="backup-import" method="post" action="<?= e(url('/settings/backups/import')) ?>" enctype="multipart/form-data">
                        <?= csrf_field() ?>
                        <div class="field">
                            <label class="field__label" for="backup-archive"><?= e(__('backup.import_label')) ?></label>
                            <input class="field__control" id="backup-archive" type="file" name="archive" accept=".zip,application/zip" required>
                        </div>
                        <button class="button button--secondary" type="submit">
                            <svg class="icon icon--small" aria-hidden="true"><use href="<?= e(asset('img/icons.svg')) ?>#upload"></use></svg>
                            <?= e(__('backup.import_action')) ?>
                        </button>
                    </form>
                </div>
            </section>

            <section class="card settings-panel margin-block-start-6" aria-labelledby="settings-backup-list-title">
                <div class="card-header">
                    <div>
                        <h2 class="card-header__title" id="settings-backup-list-title"><?= e(__('backup.list_title')) ?></h2>
                        <p class="section-heading__note"><?= e(__('backup.list_help')) ?></p>
                    </div>
                    <span class="badge badge--neutral"><?= e(__('backup.count', ['count' => (string) count($backups)])) ?></span>
                </div>

                <?php if ($backups === []): ?>
                    <p class="empty-state__description"><?= e(__('backup.list_empty')) ?></p>
                <?php else: ?>
                    <form class="bulk-form backup-selection" method="post" action="<?= e(url('/settings/backups/download')) ?>"
                          data-selection-boxes='input[name="names[]"]' data-confirm-selection>
                        <?= csrf_field() ?>
                        <div class="bulk-actions">
                            <span class="bulk-actions__count" data-selection-count data-label="<?= e(__('backup.selected_count')) ?>"><?= e(str_replace(':count', '0', __('backup.selected_count'))) ?></span>
                            <button class="button button--secondary button--small" type="submit" data-selection-submit disabled
                                    formaction="<?= e(url('/settings/backups/download')) ?>">
                                <svg class="icon icon--small" aria-hidden="true"><use href="<?= e(asset('img/icons.svg')) ?>#download"></use></svg>
                                <?= e(__('backup.download_selection')) ?>
                            </button>
                            <button class="button button--danger button--small" type="submit" data-selection-submit disabled
                                    formaction="<?= e(url('/settings/backups/delete')) ?>"
                                    data-confirm="<?= e(__('backup.delete_confirm', ['count' => ':count'])) ?>">
                                <svg class="icon icon--small" aria-hidden="true"><use href="<?= e(asset('img/icons.svg')) ?>#trash"></use></svg>
                                <?= e(__('backup.delete_selection')) ?>
                            </button>
                        </div>

                        <div class="table-wrap">
                            <table class="data-table backup-table">
                                <thead>
                                    <tr>
                                        <th scope="col" class="bulk-select">
                                            <input type="checkbox" data-select-all aria-label="<?= e(__('common.select_all')) ?>">
                                        </th>
                                        <th scope="col"><?= e(__('backup.column_name')) ?></th>
                                        <th scope="col"><?= e(__('backup.column_date')) ?></th>
                                        <th scope="col"><?= e(__('backup.column_time')) ?></th>
                                        <th scope="col"><?= e(__('backup.column_size')) ?></th>
                                        <th scope="col"><?= e(__('backup.column_contents')) ?></th>
                                        <th scope="col"><?= e(__('backup.column_status')) ?></th>
                                        <th scope="col"><?= e(__('backup.column_actions')) ?></th>
                                    </tr>
                                </thead>
                                <tbody>
                                <?php foreach ($backups as $backup): ?>
                                    <?php $moment = $backupMoment($backup); ?>
                                    <tr>
                                        <td class="bulk-select" data-label="<?= e(__('backup.column_select')) ?>">
                                            <input type="checkbox" name="names[]" value="<?= e((string) $backup['name']) ?>"
                                                   aria-label="<?= e(__('backup.select_named', ['name' => (string) $backup['name']])) ?>">
                                        </td>
                                        <td data-label="<?= e(__('backup.column_name')) ?>"><code class="backup-table__name"><?= e((string) $backup['name']) ?></code></td>
                                        <td data-label="<?= e(__('backup.column_date')) ?>"><time datetime="<?= e($moment['iso']) ?>"><?= e($moment['date']) ?></time></td>
                                        <td data-label="<?= e(__('backup.column_time')) ?>"><?= e($moment['time']) ?></td>
                                        <td data-label="<?= e(__('backup.column_size')) ?>"><?= e($backupSize((int) $backup['bytes'])) ?></td>
                                        <td class="text-footnote" data-label="<?= e(__('backup.column_contents')) ?>">
                                            <?php if (!empty($backup['valid'])): ?>
                                                <?= e(__('backup.contents', [
                                                    'tables' => (string) ($backup['tables'] ?? 0),
                                                    'files' => (string) ($backup['files'] ?? 0),
                                                ])) ?>
                                            <?php else: ?>
                                                <span class="muted"><?= e(__('backup.size_unknown')) ?></span>
                                            <?php endif; ?>
                                        </td>
                                        <td data-label="<?= e(__('backup.column_status')) ?>">
                                            <?php if (!empty($backup['valid'])): ?>
                                                <span class="badge badge--success"><?= e(__('backup.status_valid')) ?></span>
                                            <?php else: ?>
                                                <span class="badge badge--warning"><?= e(__('backup.status_invalid')) ?></span>
                                            <?php endif; ?>
                                        </td>
                                        <td data-label="<?= e(__('backup.column_actions')) ?>">
                                            <a class="button button--ghost button--small"
                                               href="<?= e(url('/settings/backups/download/' . rawurlencode((string) $backup['name']))) ?>">
                                                <?= e(__('backup.download')) ?>
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <p class="field__hint"><?= e(__('backup.status_invalid_help')) ?></p>
                    </form>
                <?php endif; ?>
            </section>

            <section class="card settings-panel settings-panel--danger margin-block-start-6" aria-labelledby="settings-backup-restore-title">
                <div class="card-header">
                    <div>
                        <h2 class="card-header__title" id="settings-backup-restore-title"><?= e(__('backup.restore_title')) ?></h2>
                        <p class="section-heading__note"><?= e(__('backup.restore_help')) ?></p>
                    </div>
                    <span class="badge badge--error"><?= e(__('backup.restore_badge')) ?></span>
                </div>
                <?php if ($restorableBackups === []): ?>
                    <p class="empty-state__description"><?= e(__('backup.restore_no_archive')) ?></p>
                <?php else: ?>
                    <form class="settings-form" method="post" action="<?= e(url('/settings/backups/restore')) ?>"
                          data-confirm="<?= e(__('backup.restore_confirm_dialog')) ?>">
                        <?= csrf_field() ?>
                        <div class="field">
                            <label class="field__label" for="backup-restore-name"><?= e(__('backup.restore_label')) ?></label>
                            <select class="field__control" id="backup-restore-name" name="name" required>
                                <option value=""><?= e(__('backup.restore_choose')) ?></option>
                                <?php foreach ($restorableBackups as $backup): ?>
                                    <?php $moment = $backupMoment($backup); ?>
                                    <option value="<?= e((string) $backup['name']) ?>"><?= e((string) $backup['name']) ?> · <?= e($moment['date'] . ' ' . $moment['time']) ?> · <?= e($backupSize((int) $backup['bytes'])) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="field">
                            <label class="field__label" for="backup-restore-confirmation"><?= e(__('backup.restore_confirmation_label', ['word' => $backupConfirmationWord])) ?></label>
                            <input class="field__control" id="backup-restore-confirmation" name="confirmation" autocomplete="off" autocapitalize="characters" spellcheck="false" required placeholder="<?= e($backupConfirmationWord) ?>">
                        </div>
                        <p class="form-feedback form-feedback--warning"><?= e(__('backup.restore_warning')) ?></p>
                        <div class="form-actions">
                            <button class="button button--danger" type="submit"><?= e(__('backup.restore_action')) ?></button>
                        </div>
                    </form>
                <?php endif; ?>
            </section>
        </section>
<?php endif; ?>
    </div>
</div>
