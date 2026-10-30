<?php
declare(strict_types=1);

use App\Core\View;

$values = is_array($values ?? null) ? $values : [];
$document = is_array($document ?? null) ? $document : null;
$errors = is_array($errors ?? null) ? $errors : [];
$templates = is_array($templates ?? null) ? $templates : [];
$options = is_array($options ?? null) ? $options : ['clients' => [], 'projects' => [], 'phases' => [], 'references' => []];
$vatRates = is_array($vatRates ?? null) ? $vatRates : ['19', '13', '7', '0'];
$lines = is_array($values['lines'] ?? null) ? array_values($values['lines']) : [];
if ($lines === []) {
    $lines = [['description' => '', 'quantity' => '1.000', 'unit' => '', 'unit_price' => '0.000']];
}
$documentId = $document === null ? 0 : (int) $document['id'];
$formAction = $document === null ? url('/documents') : url('/documents/' . $documentId);
$type = (string) ($values['document_type'] ?? 'quote');
$locale = (string) ($values['locale'] ?? 'fr');
$referenceId = (string) ($values['reference_document_id'] ?? '');
$phaseRequired = $type === 'invoice' && ($values['billing_kind'] ?? '') === 'phase';
?>
<?php View::partial('partials/page-header', [
    'pageTitle' => $title,
    'eyebrow' => __('documents.title'),
    'subtitle' => __('documents.form_intro'),
    'secondaryAction' => ['label' => __('documents.back'), 'icon' => 'arrow-left', 'url' => $document === null ? url('/documents') : url('/documents/' . $documentId)],
]) ?>

<?php if (!empty($errors['general'])): ?><p class="form-feedback form-feedback--error" role="alert"><?= e($errors['general']) ?></p><?php endif; ?>
<form class="stack-lg" method="post" action="<?= e($formAction) ?>" data-document-form>
    <?= csrf_field() ?>
    <section class="card stack-md" aria-labelledby="document-details-heading">
        <div class="card-header"><div><h2 class="card-header__title" id="document-details-heading"><?= e(__('documents.details')) ?></h2><p class="section-heading__note"><?= e(__('documents.draft_note')) ?></p></div></div>
        <div class="foundation-settings__grid">
            <div class="field">
                <label class="field__label" for="document-type"><?= e(__('documents.type_label')) ?></label>
                <select class="field__control" id="document-type" name="document_type" required data-document-type>
                    <?php foreach (['quote', 'contract', 'invoice', 'credit_note'] as $kind): ?>
                        <option value="<?= e($kind) ?>" <?= $type === $kind ? 'selected' : '' ?>><?= e(__('documents.type.' . $kind)) ?></option>
                    <?php endforeach; ?>
                </select>
                <?php if (!empty($errors['document_type'])): ?><span class="field__hint field__hint--error"><?= e($errors['document_type']) ?></span><?php endif; ?>
            </div>
            <div class="field">
                <label class="field__label" for="document-locale"><?= e(__('documents.language')) ?></label>
                <select class="field__control" id="document-locale" name="locale" required data-document-locale>
                    <option value="fr" <?= $locale === 'fr' ? 'selected' : '' ?>><?= e(__('documents.language_fr')) ?></option>
                    <option value="ar" <?= $locale === 'ar' ? 'selected' : '' ?>><?= e(__('documents.language_ar')) ?></option>
                </select>
                <?php if (!empty($errors['locale'])): ?><span class="field__hint field__hint--error"><?= e($errors['locale']) ?></span><?php endif; ?>
            </div>
        </div>
        <div class="field">
            <label class="field__label" for="document-template"><?= e(__('documents.template')) ?></label>
            <select class="field__control" id="document-template" name="template_id" required data-document-template>
                <option value=""><?= e(__('documents.choose_template')) ?></option>
                <?php foreach ($templates as $template): ?>
                    <option value="<?= e((string) $template['id']) ?>"
                        data-document-type="<?= e($template['document_type']) ?>"
                        data-locale="<?= e($template['locale']) ?>"
                        data-default="<?= !empty($template['is_default']) ? '1' : '0' ?>"
                        data-title="<?= e($template['title']) ?>"
                        data-terms="<?= e($template['terms_text'] ?? '') ?>"
                        data-legal="<?= e($template['legal_notice'] ?? '') ?>"
                        data-validity-days="<?= e((string) $template['validity_days']) ?>"
                        data-payment-days="<?= e((string) $template['payment_terms_days']) ?>"
                        <?= (string) ($values['template_id'] ?? '') === (string) $template['id'] ? 'selected' : '' ?>><?= e($template['name']) ?><?= !empty($template['is_default']) ? ' · ' . e(__('documents.default_template')) : '' ?></option>
                <?php endforeach; ?>
            </select>
            <?php if (!empty($errors['template_id'])): ?><span class="field__hint field__hint--error"><?= e($errors['template_id']) ?></span><?php endif; ?>
        </div>
        <div class="field">
            <label class="field__label" for="document-title"><?= e(__('documents.title_field')) ?></label>
            <input class="field__control" id="document-title" name="title" maxlength="180" required value="<?= e($values['title'] ?? '') ?>" data-document-title>
            <?php if (!empty($errors['title'])): ?><span class="field__hint field__hint--error"><?= e($errors['title']) ?></span><?php endif; ?>
        </div>
        <div class="foundation-settings__grid">
            <div class="field">
                <label class="field__label" for="document-client"><?= e(__('documents.client')) ?></label>
                <select class="field__control" id="document-client" name="client_id" required data-document-client>
                    <option value=""><?= e(__('documents.choose_client')) ?></option>
                    <?php foreach ($options['clients'] as $client): ?>
                        <option value="<?= e((string) $client['id']) ?>" <?= (string) ($values['client_id'] ?? '') === (string) $client['id'] ? 'selected' : '' ?>><?= e($client['display_name']) ?></option>
                    <?php endforeach; ?>
                </select>
                <?php if (!empty($errors['client_id'])): ?><span class="field__hint field__hint--error"><?= e($errors['client_id']) ?></span><?php endif; ?>
            </div>
            <div class="field">
                <label class="field__label" for="document-project"><?= e(__('documents.project')) ?></label>
                <select class="field__control" id="document-project" name="project_id" data-document-project>
                    <option value=""><?= e(__('documents.no_project')) ?></option>
                    <?php foreach ($options['projects'] as $project): ?>
                        <option value="<?= e((string) $project['id']) ?>" data-client-id="<?= e((string) $project['client_id']) ?>" <?= (string) ($values['project_id'] ?? '') === (string) $project['id'] ? 'selected' : '' ?>><?= e($project['name']) ?><?= $project['reference'] !== '' ? ' · ' . e($project['reference']) : '' ?> · <?= e($project['client_name']) ?></option>
                    <?php endforeach; ?>
                </select>
                <?php if (!empty($errors['project_id'])): ?><span class="field__hint field__hint--error"><?= e($errors['project_id']) ?></span><?php endif; ?>
            </div>
        </div>
        <div class="foundation-settings__grid" data-invoice-fields <?= $type !== 'invoice' ? 'hidden' : '' ?>>
            <div class="field">
                <label class="field__label" for="document-billing-kind"><?= e(__('documents.billing_kind_label')) ?></label>
                <select class="field__control" id="document-billing-kind" name="billing_kind" data-document-billing-kind>
                    <?php foreach (['standard', 'deposit', 'phase'] as $kind): ?>
                        <option value="<?= e($kind) ?>" <?= (string) ($values['billing_kind'] ?? 'standard') === $kind ? 'selected' : '' ?>><?= e(__('documents.billing_kind.' . $kind)) ?></option>
                    <?php endforeach; ?>
                </select>
                <?php if (!empty($errors['billing_kind'])): ?><span class="field__hint field__hint--error"><?= e($errors['billing_kind']) ?></span><?php endif; ?>
            </div>
        </div>
        <div class="field" data-reference-field <?= !in_array($type, ['invoice', 'credit_note'], true) ? 'hidden' : '' ?>>
            <label class="field__label" for="document-reference" data-document-reference-label data-credit-note-label="<?= e(__('documents.credit_invoice')) ?>" data-reference-label="<?= e(__('documents.reference_document')) ?>"><?= e($type === 'credit_note' ? __('documents.credit_invoice') : __('documents.reference_document')) ?></label>
            <select class="field__control" id="document-reference" name="reference_document_id" data-document-reference <?= $type === 'credit_note' ? 'required' : '' ?>>
                <option value=""><?= e($type === 'credit_note' ? __('documents.choose_invoice') : __('documents.no_reference')) ?></option>
                <?php foreach ($options['references'] as $reference): ?>
                    <option value="<?= e((string) $reference['id']) ?>" data-reference-type="<?= e($reference['document_type']) ?>" data-client-id="<?= e((string) $reference['client_id']) ?>" data-project-id="<?= e((string) ($reference['project_id'] ?? '')) ?>" data-tax-rate="<?= e($reference['tax_rate']) ?>" data-stamp-amount="<?= e($reference['stamp_amount']) ?>" data-withholding-rate="<?= e($reference['withholding_rate']) ?>" <?= $referenceId === (string) $reference['id'] ? 'selected' : '' ?>><?= e($reference['document_number']) ?> · <?= e($reference['client_name']) ?><?= $reference['project_name'] !== '' ? ' · ' . e($reference['project_name']) : '' ?></option>
                <?php endforeach; ?>
            </select>
            <?php if (!empty($errors['reference_document_id'])): ?><span class="field__hint field__hint--error"><?= e($errors['reference_document_id']) ?></span><?php endif; ?>
        </div>
        <div class="field" data-phase-field <?= !$phaseRequired ? 'hidden' : '' ?>>
            <label class="field__label" for="document-phase"><?= e(__('documents.phase')) ?></label>
            <select class="field__control" id="document-phase" name="project_phase_assignment_id" data-document-phase>
                <option value=""><?= e(__('documents.choose_phase')) ?></option>
                <?php foreach ($options['phases'] as $phase): ?>
                    <option value="<?= e((string) $phase['id']) ?>" data-project-id="<?= e((string) $phase['project_id']) ?>" <?= (string) ($values['project_phase_assignment_id'] ?? '') === (string) $phase['id'] ? 'selected' : '' ?>><?= e($phase['project_name']) ?> · <?= e($phase['phase_name']) ?></option>
                <?php endforeach; ?>
            </select>
            <?php if (!empty($errors['project_phase_assignment_id'])): ?><span class="field__hint field__hint--error"><?= e($errors['project_phase_assignment_id']) ?></span><?php endif; ?>
        </div>
    </section>

    <section class="card stack-md" aria-labelledby="document-lines-heading">
        <div class="card-header"><div><h2 class="card-header__title" id="document-lines-heading"><?= e(__('documents.lines')) ?></h2><p class="section-heading__note"><?= e(__('documents.lines_help')) ?></p></div><button class="button button--secondary" type="button" data-document-add-line><?= e(__('documents.add_line')) ?></button></div>
        <?php if (!empty($errors['lines'])): ?><p class="form-feedback form-feedback--error" role="alert"><?= e($errors['lines']) ?></p><?php endif; ?>
        <div class="table-wrap"><table class="data-table"><thead><tr><th scope="col"><?= e(__('documents.line_description')) ?></th><th scope="col"><?= e(__('documents.line_quantity')) ?></th><th scope="col"><?= e(__('documents.line_unit')) ?></th><th scope="col"><?= e(__('documents.line_unit_price')) ?></th><th scope="col"><span class="u-sr-only"><?= e(__('documents.remove_line')) ?></span></th></tr></thead><tbody data-document-lines>
            <?php foreach ($lines as $index => $line): ?>
                <tr data-document-line>
                    <td><label class="u-sr-only" for="doc-line-description-<?= e((string) $index) ?>"><?= e(__('documents.line_description')) ?></label><input class="field__control" id="doc-line-description-<?= e((string) $index) ?>" name="lines[<?= e((string) $index) ?>][description]" maxlength="255" value="<?= e($line['description'] ?? '') ?>" required></td>
                    <td><label class="u-sr-only" for="doc-line-quantity-<?= e((string) $index) ?>"><?= e(__('documents.line_quantity')) ?></label><input class="field__control" id="doc-line-quantity-<?= e((string) $index) ?>" name="lines[<?= e((string) $index) ?>][quantity]" type="number" min="0.001" step="0.001" value="<?= e($line['quantity'] ?? '1.000') ?>" required></td>
                    <td><label class="u-sr-only" for="doc-line-unit-<?= e((string) $index) ?>"><?= e(__('documents.line_unit')) ?></label><input class="field__control" id="doc-line-unit-<?= e((string) $index) ?>" name="lines[<?= e((string) $index) ?>][unit]" maxlength="40" value="<?= e($line['unit'] ?? '') ?>"></td>
                    <td><label class="u-sr-only" for="doc-line-unit_price-<?= e((string) $index) ?>"><?= e(__('documents.line_unit_price')) ?></label><input class="field__control" id="doc-line-unit_price-<?= e((string) $index) ?>" name="lines[<?= e((string) $index) ?>][unit_price]" type="number" min="0" step="0.001" value="<?= e($line['unit_price'] ?? '0.000') ?>" required></td>
                    <td><button class="button button--icon button--ghost" type="button" data-document-remove-line aria-label="<?= e(__('documents.remove_line')) ?>">×</button></td>
                </tr>
            <?php endforeach; ?>
        </tbody></table></div>
        <template data-document-line-template><tr data-document-line>
            <td><label class="u-sr-only" data-line-label="description"><?= e(__('documents.line_description')) ?></label><input class="field__control" data-line-name="description" maxlength="255" required></td>
            <td><label class="u-sr-only" data-line-label="quantity"><?= e(__('documents.line_quantity')) ?></label><input class="field__control" data-line-name="quantity" type="number" min="0.001" step="0.001" value="1.000" required></td>
            <td><label class="u-sr-only" data-line-label="unit"><?= e(__('documents.line_unit')) ?></label><input class="field__control" data-line-name="unit" maxlength="40"></td>
            <td><label class="u-sr-only" data-line-label="unit_price"><?= e(__('documents.line_unit_price')) ?></label><input class="field__control" data-line-name="unit_price" type="number" min="0" step="0.001" value="0.000" required></td>
            <td><button class="button button--icon button--ghost" type="button" data-document-remove-line aria-label="<?= e(__('documents.remove_line')) ?>">×</button></td>
        </tr></template>
    </section>

    <section class="card stack-md" aria-labelledby="document-tax-heading">
        <div class="card-header"><div><h2 class="card-header__title" id="document-tax-heading"><?= e(__('documents.tax_title')) ?></h2><p class="section-heading__note"><?= e(__('documents.tax_warning')) ?></p></div></div>
        <p class="form-feedback form-feedback--info" data-credit-note-tax-note <?= $type !== 'credit_note' ? 'hidden' : '' ?>><?= e(__('documents.credit_note_tax_locked')) ?></p>
        <div class="foundation-settings__grid">
            <div class="field"><label class="field__label" for="document-vat-rate"><?= e(__('documents.tax_rate')) ?></label><select class="field__control" id="document-vat-rate" name="tax_rate" required data-document-tax-control <?= $type === 'credit_note' ? 'disabled' : '' ?>><?php foreach ($vatRates as $rate): ?><?php $normalizedRate = number_format((float) $rate, 2, '.', ''); ?><option value="<?= e($normalizedRate) ?>" <?= (string) ($values['tax_rate'] ?? '') === $normalizedRate || (string) ($values['tax_rate'] ?? '') === (string) $rate ? 'selected' : '' ?>><?= e($rate) ?> %</option><?php endforeach; ?></select><?php if (!empty($errors['tax_rate'])): ?><span class="field__hint field__hint--error"><?= e($errors['tax_rate']) ?></span><?php endif; ?></div>
            <div class="field"><label class="field__label" for="document-stamp-duty"><?= e(__('documents.stamp_duty')) ?></label><input class="field__control" id="document-stamp-duty" name="stamp_amount" type="number" min="0" step="0.001" data-document-tax-control <?= $type === 'credit_note' ? 'disabled' : '' ?> value="<?= e($values['stamp_amount'] ?? '0.000') ?>"><?php if (!empty($errors['stamp_amount'])): ?><span class="field__hint field__hint--error"><?= e($errors['stamp_amount']) ?></span><?php endif; ?></div>
            <div class="field"><label class="field__label" for="document-withholding-rate"><?= e(__('documents.withholding_rate')) ?></label><input class="field__control" id="document-withholding-rate" name="withholding_rate" type="number" min="0" max="100" step="0.001" data-document-tax-control <?= $type === 'credit_note' ? 'disabled' : '' ?> value="<?= e($values['withholding_rate'] ?? '0.000') ?>"><?php if (!empty($errors['withholding_rate'])): ?><span class="field__hint field__hint--error"><?= e($errors['withholding_rate']) ?></span><?php endif; ?></div>
        </div>
        <div class="foundation-settings__grid">
            <div class="field"><label class="field__label" for="document-validity-days"><?= e(__('documents.validity_days')) ?></label><input class="field__control" id="document-validity-days" name="validity_days" type="number" min="0" max="3650" value="<?= e($values['validity_days'] ?? '0') ?>"><?php if (!empty($errors['validity_days'])): ?><span class="field__hint field__hint--error"><?= e($errors['validity_days']) ?></span><?php endif; ?></div>
            <div class="field"><label class="field__label" for="document-payment-days"><?= e(__('documents.payment_terms_days')) ?></label><input class="field__control" id="document-payment-days" name="payment_terms_days" type="number" min="0" max="3650" value="<?= e($values['payment_terms_days'] ?? '0') ?>"><?php if (!empty($errors['payment_terms_days'])): ?><span class="field__hint field__hint--error"><?= e($errors['payment_terms_days']) ?></span><?php endif; ?></div>
        </div>
    </section>

    <section class="card stack-md" aria-labelledby="document-terms-heading">
        <div class="card-header"><div><h2 class="card-header__title" id="document-terms-heading"><?= e(__('documents.terms')) ?></h2><p class="section-heading__note"><?= e(__('documents.terms_help')) ?></p></div></div>
        <div class="field"><label class="field__label" for="document-terms"><?= e(__('documents.terms_text')) ?></label><textarea class="field__control" id="document-terms" name="terms_text" rows="4" maxlength="4000" data-document-terms><?= e($values['terms_text'] ?? '') ?></textarea><?php if (!empty($errors['terms_text'])): ?><span class="field__hint field__hint--error"><?= e($errors['terms_text']) ?></span><?php endif; ?></div>
        <div class="field"><label class="field__label" for="document-legal-notice"><?= e(__('documents.legal_notice')) ?></label><textarea class="field__control" id="document-legal-notice" name="legal_notice" rows="3" maxlength="2000" data-document-legal><?= e($values['legal_notice'] ?? '') ?></textarea><?php if (!empty($errors['legal_notice'])): ?><span class="field__hint field__hint--error"><?= e($errors['legal_notice']) ?></span><?php endif; ?></div>
        <div class="field"><label class="field__label" for="document-notes"><?= e(__('documents.notes')) ?></label><textarea class="field__control" id="document-notes" name="notes" rows="3" maxlength="4000"><?= e($values['notes'] ?? '') ?></textarea></div>
    </section>
    <div class="form-actions"><a class="button button--secondary" href="<?= e($document === null ? url('/documents') : url('/documents/' . $documentId)) ?>"><?= e(__('common.cancel')) ?></a><button class="button button--primary" type="submit"><?= e(__('documents.save_draft')) ?></button></div>
</form>
