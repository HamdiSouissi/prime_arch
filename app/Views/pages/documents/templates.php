<?php
declare(strict_types=1);

use App\Core\View;

$templates = is_array($templates ?? null) ? $templates : [];
$values = is_array($values ?? null) ? $values : [];
$errors = is_array($errors ?? null) ? $errors : [];
$rowValues = is_array($rowValues ?? null) ? $rowValues : [];
$documentTypes = ['quote', 'contract', 'invoice', 'credit_note'];
?>
<?php View::partial('partials/page-header', [
    'pageTitle' => __('documents.templates_title'),
    'eyebrow' => __('documents.title'),
    'subtitle' => __('documents.templates_intro'),
    'secondaryAction' => ['label' => __('documents.back'), 'icon' => 'arrow-left', 'url' => url('/documents')],
]) ?>
<?php if (!empty($notice)): ?><p class="form-feedback form-feedback--success" role="status"><?= e($notice) ?></p><?php endif; ?>
<?php if (!empty($errors['general']) && empty($errors['template_id'])): ?><p class="form-feedback form-feedback--error" role="alert"><?= e($errors['general']) ?></p><?php endif; ?>

<section class="card stack-md" aria-labelledby="template-create-title">
    <div class="card-header"><div><h2 class="card-header__title" id="template-create-title"><?= e(__('documents.create_template')) ?></h2><p class="section-heading__note"><?= e(__('documents.create_template_intro')) ?></p></div></div>
    <?php if (!empty($errors['name']) || !empty($errors['title']) || !empty($errors['document_type'])): ?><p class="form-feedback form-feedback--error" role="alert"><?= e(__('documents.templates_invalid')) ?></p><?php endif; ?>
    <form class="stack-md" method="post" action="<?= e(url('/settings/document-templates')) ?>">
        <?= csrf_field() ?>
        <div class="foundation-settings__grid">
            <div class="field"><label class="field__label" for="new-template-type"><?= e(__('documents.type_label')) ?></label><select class="field__control" id="new-template-type" name="document_type" required><?php foreach ($documentTypes as $type): ?><option value="<?= e($type) ?>" <?= ($values['document_type'] ?? 'quote') === $type ? 'selected' : '' ?>><?= e(__('documents.type.' . $type)) ?></option><?php endforeach; ?></select><?php if (!empty($errors['document_type'])): ?><span class="field__hint field__hint--error"><?= e($errors['document_type']) ?></span><?php endif; ?></div>
            <div class="field"><label class="field__label" for="new-template-locale"><?= e(__('documents.language')) ?></label><select class="field__control" id="new-template-locale" name="locale" required><option value="fr" <?= ($values['locale'] ?? 'fr') === 'fr' ? 'selected' : '' ?>><?= e(__('documents.language_fr')) ?></option><option value="ar" <?= ($values['locale'] ?? 'fr') === 'ar' ? 'selected' : '' ?>><?= e(__('documents.language_ar')) ?></option></select></div>
            <div class="field"><label class="field__label" for="new-template-name"><?= e(__('documents.template_name')) ?></label><input class="field__control" id="new-template-name" name="name" maxlength="120" required value="<?= e($values['name'] ?? '') ?>"><?php if (!empty($errors['name'])): ?><span class="field__hint field__hint--error"><?= e($errors['name']) ?></span><?php endif; ?></div>
            <div class="field"><label class="field__label" for="new-template-title"><?= e(__('documents.title_field')) ?></label><input class="field__control" id="new-template-title" name="title" maxlength="180" required value="<?= e($values['title'] ?? '') ?>"><?php if (!empty($errors['title'])): ?><span class="field__hint field__hint--error"><?= e($errors['title']) ?></span><?php endif; ?></div>
        </div>
        <div class="foundation-settings__grid">
            <div class="field"><label class="field__label" for="new-template-validity"><?= e(__('documents.validity_days')) ?></label><input class="field__control" id="new-template-validity" name="validity_days" type="number" min="0" max="3650" value="<?= e((string) ($values['validity_days'] ?? '0')) ?>"><?php if (!empty($errors['validity_days'])): ?><span class="field__hint field__hint--error"><?= e($errors['validity_days']) ?></span><?php endif; ?></div>
            <div class="field"><label class="field__label" for="new-template-payment-days"><?= e(__('documents.payment_terms_days')) ?></label><input class="field__control" id="new-template-payment-days" name="payment_terms_days" type="number" min="0" max="3650" value="<?= e((string) ($values['payment_terms_days'] ?? '0')) ?>"><?php if (!empty($errors['payment_terms_days'])): ?><span class="field__hint field__hint--error"><?= e($errors['payment_terms_days']) ?></span><?php endif; ?></div>
        </div>
        <div class="field"><label class="field__label" for="new-template-terms"><?= e(__('documents.terms_text')) ?></label><textarea class="field__control" id="new-template-terms" name="terms_text" rows="4" maxlength="4000"><?= e($values['terms_text'] ?? '') ?></textarea><?php if (!empty($errors['terms_text'])): ?><span class="field__hint field__hint--error"><?= e($errors['terms_text']) ?></span><?php endif; ?></div>
        <div class="field"><label class="field__label" for="new-template-legal"><?= e(__('documents.legal_notice')) ?></label><textarea class="field__control" id="new-template-legal" name="legal_notice" rows="3" maxlength="2000"><?= e($values['legal_notice'] ?? '') ?></textarea></div>
        <div class="inline-options"><label class="checkbox-field"><input type="checkbox" name="is_default" value="1" <?= !empty($values['is_default']) ? 'checked' : '' ?>><span><?= e(__('documents.default_template')) ?></span></label><label class="checkbox-field"><input type="checkbox" name="is_active" value="1" <?= !array_key_exists('is_active', $values) || !empty($values['is_active']) ? 'checked' : '' ?>><span><?= e(__('documents.template_active')) ?></span></label></div>
        <div class="form-actions"><button class="button button--primary" type="submit"><?= e(__('documents.create_template')) ?></button></div>
    </form>
</section>

<section class="stack-md" aria-labelledby="template-list-title">
    <div class="section-heading"><div><h2 class="section-heading__title" id="template-list-title"><?= e(__('documents.existing_templates')) ?></h2><p class="section-heading__note"><?= e(__('documents.templates_edit_help')) ?></p></div><span class="badge badge--neutral"><?= e((string) count($templates)) ?></span></div>
    <?php if ($templates === []): ?><div class="card empty-state"><p class="empty-state__copy"><?= e(__('documents.no_templates')) ?></p></div><?php endif; ?>
    <?php foreach ($templates as $template): ?>
        <?php
        $templateId = (int) $template['id'];
        $row = is_array($rowValues[(string) $templateId] ?? null) ? $rowValues[(string) $templateId] : $template;
        $rowError = (string) ($errors['template_id'] ?? '') === (string) $templateId;
        $isDefault = !empty($row['is_default']);
        $isActive = !empty($row['is_active']);
        ?>
        <details class="card template-editor" <?= $rowError ? 'open' : '' ?>>
            <summary class="template-editor__summary"><span><strong><?= e($template['name']) ?></strong><span class="table-meta"><?= e(__('documents.type.' . $template['document_type'])) ?> · <?= e($template['locale'] === 'ar' ? __('documents.language_ar') : __('documents.language_fr')) ?></span></span><span class="inline-options"><span class="badge badge--<?= $isActive ? 'success' : 'neutral' ?>"><?= e($isActive ? __('documents.template_active') : __('documents.template_inactive')) ?></span><?php if ($isDefault): ?><span class="badge badge--info"><?= e(__('documents.default_template')) ?></span><?php endif; ?></span></summary>
            <form class="stack-md template-editor__form" method="post" action="<?= e(url('/settings/document-templates/' . $templateId)) ?>">
                <?= csrf_field() ?>
                <input type="hidden" name="document_type" value="<?= e($row['document_type']) ?>">
                <input type="hidden" name="locale" value="<?= e($row['locale']) ?>">
                <?php if ($rowError && !empty($errors['general'])): ?><p class="form-feedback form-feedback--error" role="alert"><?= e($errors['general']) ?></p><?php endif; ?>
                <?php if ($rowError && !empty($errors['name'])): ?><p class="form-feedback form-feedback--error" role="alert"><?= e($errors['name']) ?></p><?php endif; ?>
                <?php if ($rowError && !empty($errors['title'])): ?><p class="form-feedback form-feedback--error" role="alert"><?= e($errors['title']) ?></p><?php endif; ?>
                <?php if ($rowError && !empty($errors['terms_text'])): ?><p class="form-feedback form-feedback--error" role="alert"><?= e($errors['terms_text']) ?></p><?php endif; ?>
                <?php if ($rowError && !empty($errors['validity_days'])): ?><p class="form-feedback form-feedback--error" role="alert"><?= e($errors['validity_days']) ?></p><?php endif; ?>
                <?php if ($rowError && !empty($errors['payment_terms_days'])): ?><p class="form-feedback form-feedback--error" role="alert"><?= e($errors['payment_terms_days']) ?></p><?php endif; ?>
                <div class="foundation-settings__grid">
                    <div class="field"><label class="field__label" for="template-name-<?= e((string) $templateId) ?>"><?= e(__('documents.template_name')) ?></label><input class="field__control" id="template-name-<?= e((string) $templateId) ?>" name="name" maxlength="120" required value="<?= e($row['name'] ?? '') ?>"></div>
                    <div class="field"><label class="field__label" for="template-title-<?= e((string) $templateId) ?>"><?= e(__('documents.title_field')) ?></label><input class="field__control" id="template-title-<?= e((string) $templateId) ?>" name="title" maxlength="180" required value="<?= e($row['title'] ?? '') ?>"></div>
                </div>
                <div class="foundation-settings__grid">
                    <div class="field"><label class="field__label" for="template-validity-<?= e((string) $templateId) ?>"><?= e(__('documents.validity_days')) ?></label><input class="field__control" id="template-validity-<?= e((string) $templateId) ?>" name="validity_days" type="number" min="0" max="3650" value="<?= e((string) ($row['validity_days'] ?? 0)) ?>"></div>
                    <div class="field"><label class="field__label" for="template-payment-days-<?= e((string) $templateId) ?>"><?= e(__('documents.payment_terms_days')) ?></label><input class="field__control" id="template-payment-days-<?= e((string) $templateId) ?>" name="payment_terms_days" type="number" min="0" max="3650" value="<?= e((string) ($row['payment_terms_days'] ?? 0)) ?>"></div>
                </div>
                <div class="field"><label class="field__label" for="template-terms-<?= e((string) $templateId) ?>"><?= e(__('documents.terms_text')) ?></label><textarea class="field__control" id="template-terms-<?= e((string) $templateId) ?>" name="terms_text" rows="4" maxlength="4000"><?= e($row['terms_text'] ?? '') ?></textarea></div>
                <div class="field"><label class="field__label" for="template-legal-<?= e((string) $templateId) ?>"><?= e(__('documents.legal_notice')) ?></label><textarea class="field__control" id="template-legal-<?= e((string) $templateId) ?>" name="legal_notice" rows="3" maxlength="2000"><?= e($row['legal_notice'] ?? '') ?></textarea></div>
                <div class="inline-options"><label class="checkbox-field"><input type="checkbox" name="is_default" value="1" <?= $isDefault ? 'checked' : '' ?>><span><?= e(__('documents.default_template')) ?></span></label><label class="checkbox-field"><input type="checkbox" name="is_active" value="1" <?= $isActive ? 'checked' : '' ?>><span><?= e(__('documents.template_active')) ?></span></label></div>
                <div class="form-actions"><button class="button button--primary" type="submit"><?= e(__('documents.save_template')) ?></button></div>
            </form>
        </details>
    <?php endforeach; ?>
</section>
