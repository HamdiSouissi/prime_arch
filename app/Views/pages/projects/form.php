<?php
declare(strict_types=1);

use App\Core\View;

$values = is_array($values ?? null) ? $values : [];
$errors = is_array($errors ?? null) ? $errors : [];
$clients = is_array($clients ?? null) ? $clients : [];
$isEdit = is_array($project ?? null);
$projectId = $isEdit ? (int) $project['id'] : 0;
$formAction = $isEdit ? url('/projects/' . $projectId) : url('/projects');
?>
<?php View::partial('partials/page-header', [
    'pageTitle' => $title,
    'eyebrow' => $isEdit ? (string) ($project['reference'] ?? '') : __('projects.new_record'),
    'subtitle' => __('projects.form_intro'),
    'secondaryAction' => ['label' => __('common.cancel'), 'icon' => 'arrow-left', 'url' => $isEdit ? url('/projects/' . $projectId) : url('/projects')],
]) ?>

<?php if (!empty($notice)): ?><p class="form-feedback form-feedback--success" role="status"><?= e($notice) ?></p><?php endif; ?>
<?php if (!empty($errors['general'])): ?><p class="form-feedback form-feedback--error" role="alert"><?= e($errors['general']) ?></p><?php endif; ?>

<form class="card settings-form project-form" method="post" action="<?= e($formAction) ?>" data-geography-form data-delegations-url="<?= e(url('/api/geography/delegations')) ?>" data-postal-codes-url="<?= e(url('/api/geography/postal-codes')) ?>">
    <?= csrf_field() ?>
    <section class="form-section" aria-labelledby="project-info-title">
        <div class="section-heading"><div><h2 class="section-heading__title" id="project-info-title"><?= e(__('projects.information')) ?></h2><p class="section-heading__note"><?= e(__('projects.information_help')) ?></p></div></div>
        <div class="form-grid">
            <div class="field form-grid__full">
                <label class="field__label" for="project-client"><?= e(__('clients.client')) ?></label>
                <select class="field__control" id="project-client" name="client_id" required>
                    <option value=""><?= e(__('projects.choose_client')) ?></option>
                    <?php foreach ($clients as $clientOption): ?>
                        <option value="<?= e((string) $clientOption['id']) ?>" <?= (string) ($values['client_id'] ?? '') === (string) $clientOption['id'] ? 'selected' : '' ?>><?= e($clientOption['display_name']) ?><?= $clientOption['reference'] !== '' ? ' · ' . e($clientOption['reference']) : '' ?></option>
                    <?php endforeach; ?>
                </select>
                <?php if (!empty($errors['client_id'])): ?><span class="field__hint field__hint--error"><?= e($errors['client_id']) ?></span><?php endif; ?>
                <?php if ($clients === []): ?><span class="field__hint"><?= e(__('projects.no_clients')) ?> <?php if (!empty($canManageClients)): ?><a href="<?= e(url('/clients/new')) ?>"><?= e(__('clients.create')) ?></a><?php endif; ?></span><?php endif; ?>
            </div>
            <div class="field form-grid__full">
                <label class="field__label" for="project-name"><?= e(__('projects.name')) ?></label>
                <input class="field__control" id="project-name" name="name" maxlength="180" required value="<?= e($values['name'] ?? '') ?>">
                <?php if (!empty($errors['name'])): ?><span class="field__hint field__hint--error"><?= e($errors['name']) ?></span><?php endif; ?>
            </div>
            <div class="field">
                <label class="field__label" for="project-type"><?= e(__('projects.type')) ?></label>
                <input class="field__control" id="project-type" name="project_type" maxlength="120" value="<?= e($values['project_type'] ?? '') ?>" placeholder="<?= e(__('projects.type_example')) ?>">
                <?php if (!empty($errors['project_type'])): ?><span class="field__hint field__hint--error"><?= e($errors['project_type']) ?></span><?php endif; ?>
            </div>
            <div class="field">
                <label class="field__label" for="project-status"><?= e(__('projects.status.label')) ?></label>
                <select class="field__control" id="project-status" name="status" required>
                    <?php foreach (['planning', 'active', 'on_hold', 'completed', 'cancelled'] as $status): ?>
                        <option value="<?= e($status) ?>" <?= ($values['status'] ?? 'planning') === $status ? 'selected' : '' ?>><?= e(__('projects.status.' . $status)) ?></option>
                    <?php endforeach; ?>
                </select>
                <?php if (!empty($errors['status'])): ?><span class="field__hint field__hint--error"><?= e($errors['status']) ?></span><?php endif; ?>
            </div>
            <div class="field"><label class="field__label" for="project-start-date"><?= e(__('projects.start_date')) ?></label><input class="field__control" id="project-start-date" name="start_date" type="date" value="<?= e($values['start_date'] ?? '') ?>"><?php if (!empty($errors['start_date'])): ?><span class="field__hint field__hint--error"><?= e($errors['start_date']) ?></span><?php endif; ?></div>
            <div class="field"><label class="field__label" for="project-target-date"><?= e(__('projects.target_end_date')) ?></label><input class="field__control" id="project-target-date" name="target_end_date" type="date" value="<?= e($values['target_end_date'] ?? '') ?>"><?php if (!empty($errors['target_end_date'])): ?><span class="field__hint field__hint--error"><?= e($errors['target_end_date']) ?></span><?php endif; ?></div>
            <div class="field form-grid__full"><label class="field__label" for="project-description"><?= e(__('projects.description')) ?></label><textarea class="field__control" id="project-description" name="description" rows="4" maxlength="5000"><?= e($values['description'] ?? '') ?></textarea><?php if (!empty($errors['description'])): ?><span class="field__hint field__hint--error"><?= e($errors['description']) ?></span><?php endif; ?></div>
        </div>
    </section>

    <section class="form-section" aria-labelledby="project-budget-title">
        <div class="section-heading"><div><h2 class="section-heading__title" id="project-budget-title"><?= e(__('projects.budget_title')) ?></h2><p class="section-heading__note"><?= e(__('projects.budget_hours_help')) ?></p></div></div>
        <div class="form-grid">
            <div class="field">
                <label class="field__label" for="project-budget-hours"><?= e(__('projects.budget_hours')) ?></label>
                <input class="field__control" id="project-budget-hours" name="budget_hours" type="number" min="0" step="0.01" inputmode="decimal" value="<?= e($values['budget_hours'] ?? '') ?>">
                <?php if (!empty($errors['budget_hours'])): ?><span class="field__hint field__hint--error"><?= e($errors['budget_hours']) ?></span><?php endif; ?>
            </div>
            <?php if (!empty($canManageFee)): ?>
                <div class="field">
                    <label class="field__label" for="project-fee-budget"><?= e(__('projects.fee_budget_amount')) ?></label>
                    <input class="field__control" id="project-fee-budget" name="fee_budget_amount" type="number" min="0" step="0.001" inputmode="decimal" value="<?= e($values['fee_budget_amount'] ?? '') ?>">
                    <?php if (!empty($errors['fee_budget_amount'])): ?><span class="field__hint field__hint--error"><?= e($errors['fee_budget_amount']) ?></span><?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </section>

    <section class="form-section" aria-labelledby="project-address-title">
        <div class="section-heading"><div><h2 class="section-heading__title" id="project-address-title"><?= e(__('projects.site_address')) ?></h2><p class="section-heading__note"><?= e(__('address.help')) ?></p></div></div>
        <?php View::partial('partials/tunisian-address-fields', [
            'addressPrefix' => 'project-address',
            'values' => $values,
            'errors' => $errors,
            'governorates' => $governorates,
            'delegations' => $delegations,
        ]) ?>
    </section>

    <section class="form-section" aria-labelledby="project-custom-title">
        <div class="section-heading"><div><h2 class="section-heading__title" id="project-custom-title"><?= e(__('custom_fields.title')) ?></h2><p class="section-heading__note"><?= e(__('custom_fields.project_help')) ?></p></div><?php if (!empty($canConfigureFields)): ?><a class="card-header__action" href="<?= e(url('/settings/custom-fields')) ?>"><?= e(__('custom_fields.manage_link')) ?></a><?php endif; ?></div>
        <?php View::partial('partials/custom-field-inputs', [
            'customFields' => $customFields,
            'values' => $values,
            'errors' => $errors,
            'customPrefix' => 'project-field',
            'canConfigureFields' => !empty($canConfigureFields),
            'customProjectId' => $projectId,
        ]) ?>
    </section>

    <div class="form-actions"><button class="button button--primary" type="submit"><?= e($isEdit ? __('projects.save') : __('projects.create')) ?></button></div>
</form>
