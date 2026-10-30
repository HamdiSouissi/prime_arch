<?php
declare(strict_types=1);

use App\Core\View;

$values = is_array($values ?? null) ? $values : [];
$errors = is_array($errors ?? null) ? $errors : [];
$isEdit = is_array($client ?? null);
$clientId = $isEdit ? (int) $client['id'] : 0;
$formAction = $isEdit ? url('/clients/' . $clientId) : url('/clients');
?>
<?php View::partial('partials/page-header', [
    'pageTitle' => $title,
    'eyebrow' => $isEdit ? (string) ($client['reference'] ?? '') : __('clients.new_record'),
    'subtitle' => __('clients.form_intro'),
    'secondaryAction' => ['label' => __('common.cancel'), 'icon' => 'arrow-left', 'url' => $isEdit ? url('/clients/' . $clientId) : url('/clients')],
]) ?>

<?php if (!empty($notice)): ?><p class="form-feedback form-feedback--success" role="status"><?= e($notice) ?></p><?php endif; ?>
<?php if (!empty($errors['general'])): ?><p class="form-feedback form-feedback--error" role="alert"><?= e($errors['general']) ?></p><?php endif; ?>

<form class="card settings-form client-form" method="post" action="<?= e($formAction) ?>" data-geography-form data-delegations-url="<?= e(url('/api/geography/delegations')) ?>" data-postal-codes-url="<?= e(url('/api/geography/postal-codes')) ?>">
    <?= csrf_field() ?>
    <section class="form-section" aria-labelledby="client-identity-title">
        <div class="section-heading">
            <div><h2 class="section-heading__title" id="client-identity-title"><?= e(__('clients.identity')) ?></h2><p class="section-heading__note"><?= e(__('clients.identity_help')) ?></p></div>
        </div>
        <div class="form-grid">
            <div class="field">
                <label class="field__label" for="client-type"><?= e(__('clients.type')) ?></label>
                <select class="field__control" id="client-type" name="client_type" required>
                    <?php foreach (['individual', 'company', 'organization'] as $type): ?>
                        <option value="<?= e($type) ?>" <?= ($values['client_type'] ?? 'individual') === $type ? 'selected' : '' ?>><?= e(__('clients.type.' . $type)) ?></option>
                    <?php endforeach; ?>
                </select>
                <?php if (!empty($errors['client_type'])): ?><span class="field__hint field__hint--error"><?= e($errors['client_type']) ?></span><?php endif; ?>
            </div>
            <div class="field">
                <label class="field__label" for="client-status"><?= e(__('clients.status')) ?></label>
                <select class="field__control" id="client-status" name="status" required>
                    <?php foreach (['prospect', 'active', 'archived'] as $status): ?>
                        <option value="<?= e($status) ?>" <?= ($values['status'] ?? 'prospect') === $status ? 'selected' : '' ?>><?= e(__('clients.status.' . $status)) ?></option>
                    <?php endforeach; ?>
                </select>
                <?php if (!empty($errors['status'])): ?><span class="field__hint field__hint--error"><?= e($errors['status']) ?></span><?php endif; ?>
            </div>
            <div class="field">
                <label class="field__label" for="client-display-name"><?= e(__('clients.display_name')) ?></label>
                <input class="field__control" id="client-display-name" name="display_name" maxlength="180" required autocomplete="name" value="<?= e($values['display_name'] ?? '') ?>">
                <?php if (!empty($errors['display_name'])): ?><span class="field__hint field__hint--error"><?= e($errors['display_name']) ?></span><?php endif; ?>
            </div>
            <div class="field">
                <label class="field__label" for="client-legal-name"><?= e(__('clients.legal_name')) ?></label>
                <input class="field__control" id="client-legal-name" name="legal_name" maxlength="180" value="<?= e($values['legal_name'] ?? '') ?>">
                <?php if (!empty($errors['legal_name'])): ?><span class="field__hint field__hint--error"><?= e($errors['legal_name']) ?></span><?php endif; ?>
            </div>
            <div class="field">
                <label class="field__label" for="client-tax-id"><?= e(__('settings.tax_id')) ?></label>
                <input class="field__control" id="client-tax-id" name="tax_id" maxlength="32" autocomplete="off" value="<?= e($values['tax_id'] ?? '') ?>">
                <?php if (!empty($errors['tax_id'])): ?><span class="field__hint field__hint--error"><?= e($errors['tax_id']) ?></span><?php endif; ?>
            </div>
            <div class="field">
                <label class="field__label" for="client-email"><?= e(__('settings.email')) ?></label>
                <input class="field__control" id="client-email" name="email" type="email" maxlength="190" autocomplete="email" value="<?= e($values['email'] ?? '') ?>">
                <?php if (!empty($errors['email'])): ?><span class="field__hint field__hint--error"><?= e($errors['email']) ?></span><?php endif; ?>
            </div>
            <?php View::partial('partials/tunisian-phone-field', [
                'inputId' => 'client-phone',
                'value' => $values['phone'] ?? '',
                'fieldError' => $errors['phone'] ?? '',
            ]) ?>
        </div>
    </section>

    <section class="form-section" aria-labelledby="client-address-title">
        <div class="section-heading"><div><h2 class="section-heading__title" id="client-address-title"><?= e(__('address.title')) ?></h2><p class="section-heading__note"><?= e(__('address.help')) ?></p></div></div>
        <?php View::partial('partials/tunisian-address-fields', [
            'addressPrefix' => 'client-address',
            'values' => $values,
            'errors' => $errors,
            'governorates' => $governorates,
            'delegations' => $delegations,
        ]) ?>
    </section>

    <section class="form-section" aria-labelledby="client-notes-title">
        <div class="section-heading"><div><h2 class="section-heading__title" id="client-notes-title"><?= e(__('clients.notes')) ?></h2><p class="section-heading__note"><?= e(__('clients.notes_help')) ?></p></div></div>
        <div class="field"><label class="field__label" for="client-notes"><?= e(__('clients.notes')) ?></label><textarea class="field__control" id="client-notes" name="notes" rows="4" maxlength="5000"><?= e($values['notes'] ?? '') ?></textarea><?php if (!empty($errors['notes'])): ?><span class="field__hint field__hint--error"><?= e($errors['notes']) ?></span><?php endif; ?></div>
    </section>

    <section class="form-section" aria-labelledby="client-custom-title">
        <div class="section-heading"><div><h2 class="section-heading__title" id="client-custom-title"><?= e(__('custom_fields.title')) ?></h2><p class="section-heading__note"><?= e(__('custom_fields.client_help')) ?></p></div><?php if (!empty($canConfigureFields)): ?><a class="card-header__action" href="<?= e(url('/settings/custom-fields')) ?>"><?= e(__('custom_fields.manage_link')) ?></a><?php endif; ?></div>
        <?php View::partial('partials/custom-field-inputs', [
            'customFields' => $customFields,
            'values' => $values,
            'errors' => $errors,
            'customPrefix' => 'client-field',
            'canConfigureFields' => !empty($canConfigureFields),
        ]) ?>
    </section>

    <div class="form-actions"><button class="button button--primary" type="submit"><?= e($isEdit ? __('clients.save') : __('clients.create')) ?></button></div>
</form>
