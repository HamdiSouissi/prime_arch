<?php
declare(strict_types=1);

use App\Core\View;
use Modules\RegistreCourrier\Services\CourrierService;

$values = is_array($values ?? null) ? $values : [];
$errors = is_array($errors ?? null) ? $errors : [];
$projects = is_array($projects ?? null) ? $projects : [];
$clients = is_array($clients ?? null) ? $clients : [];
$entry = is_array($entry ?? null) ? $entry : null;
$isEdit = $entry !== null;
$action = (string) ($action ?? url('/courrier'));
$cancelUrl = (string) ($cancelUrl ?? url('/courrier'));
$fieldError = static fn (string $field): string => (string) ($errors[$field] ?? '');
?>
<?php View::partial('partials/page-header', [
    'pageTitle' => $isEdit ? __('courrier.edit') : __('courrier.create'),
    'eyebrow' => $isEdit ? (string) ($entry['numero'] ?? '') : __('courrier.new_entry'),
    'subtitle' => __('courrier.form_intro'),
    'secondaryAction' => ['label' => __('common.cancel'), 'icon' => 'arrow-left', 'url' => $cancelUrl],
]) ?>

<?php if ($errors !== []): ?>
    <p class="form-feedback form-feedback--error" role="alert"><?= e(__('courrier.fix_errors')) ?></p>
<?php endif; ?>

<?php if ($isEdit): ?>
    <p class="table-meta" role="note"><?= e(__('courrier.number_locked')) ?></p>
<?php endif; ?>

<form class="card settings-form" method="post" action="<?= e($action) ?>">
    <?= csrf_field() ?>

    <section class="form-section" aria-labelledby="courrier-identity-title">
        <div class="section-heading">
            <div>
                <h2 class="section-heading__title" id="courrier-identity-title"><?= e(__('courrier.section_identity')) ?></h2>
                <p class="section-heading__note"><?= e(__('courrier.section_identity_help')) ?></p>
            </div>
        </div>
        <div class="form-grid">
            <div class="field">
                <label class="field__label" for="courrier-sens"><?= e(__('courrier.sens_label')) ?></label>
                <select class="field__control" id="courrier-sens" name="sens">
                    <?php foreach (CourrierService::SENS as $sens): ?>
                        <option value="<?= e($sens) ?>" <?= (string) ($values['sens'] ?? 'in') === $sens ? 'selected' : '' ?>><?= e(__('courrier.sens.' . $sens)) ?></option>
                    <?php endforeach; ?>
                </select>
                <span class="field__hint"><?= e(__('courrier.sens_help')) ?></span>
            </div>
            <div class="field">
                <label class="field__label" for="courrier-date"><?= e(__('courrier.date')) ?></label>
                <input class="field__control" type="date" id="courrier-date" name="date_courrier" value="<?= e((string) ($values['date_courrier'] ?? '')) ?>" required>
                <?php if ($fieldError('date_courrier') !== ''): ?><span class="field__hint field__hint--error"><?= e($fieldError('date_courrier')) ?></span><?php endif; ?>
            </div>
            <div class="field form-grid__full">
                <label class="field__label" for="courrier-correspondant"><?= e(__('courrier.correspondant')) ?></label>
                <input class="field__control" type="text" id="courrier-correspondant" name="correspondant" value="<?= e((string) ($values['correspondant'] ?? '')) ?>" maxlength="150" required>
                <span class="field__hint"><?= e(__('courrier.correspondant_help')) ?></span>
                <?php if ($fieldError('correspondant') !== ''): ?><span class="field__hint field__hint--error"><?= e($fieldError('correspondant')) ?></span><?php endif; ?>
            </div>
            <div class="field form-grid__full">
                <label class="field__label" for="courrier-objet"><?= e(__('courrier.objet')) ?></label>
                <input class="field__control" type="text" id="courrier-objet" name="objet" value="<?= e((string) ($values['objet'] ?? '')) ?>" maxlength="200" required>
                <?php if ($fieldError('objet') !== ''): ?><span class="field__hint field__hint--error"><?= e($fieldError('objet')) ?></span><?php endif; ?>
            </div>
            <div class="field">
                <label class="field__label" for="courrier-reference"><?= e(__('courrier.reference')) ?></label>
                <input class="field__control" type="text" id="courrier-reference" name="reference" value="<?= e((string) ($values['reference'] ?? '')) ?>" maxlength="80">
                <span class="field__hint"><?= e(__('courrier.reference_help')) ?></span>
                <?php if ($fieldError('reference') !== ''): ?><span class="field__hint field__hint--error"><?= e($fieldError('reference')) ?></span><?php endif; ?>
            </div>
            <div class="field">
                <label class="field__label" for="courrier-mode"><?= e(__('courrier.mode_label')) ?></label>
                <select class="field__control" id="courrier-mode" name="mode">
                    <?php foreach (CourrierService::MODES as $mode): ?>
                        <option value="<?= e($mode) ?>" <?= (string) ($values['mode'] ?? 'other') === $mode ? 'selected' : '' ?>><?= e(__('courrier.mode.' . $mode)) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
    </section>

    <section class="form-section" aria-labelledby="courrier-tracking-title">
        <div class="section-heading">
            <div>
                <h2 class="section-heading__title" id="courrier-tracking-title"><?= e(__('courrier.section_tracking')) ?></h2>
                <p class="section-heading__note"><?= e(__('courrier.section_tracking_help')) ?></p>
            </div>
        </div>
        <div class="form-grid">
            <div class="field">
                <label class="field__label" for="courrier-statut"><?= e(__('courrier.statut_label')) ?></label>
                <select class="field__control" id="courrier-statut" name="statut">
                    <?php foreach (CourrierService::STATUTS as $statut): ?>
                        <option value="<?= e($statut) ?>" <?= (string) ($values['statut'] ?? 'todo') === $statut ? 'selected' : '' ?>><?= e(__('courrier.statut.' . $statut)) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="field">
                <label class="field__label" for="courrier-echeance"><?= e(__('courrier.echeance')) ?></label>
                <input class="field__control" type="date" id="courrier-echeance" name="echeance" value="<?= e((string) ($values['echeance'] ?? '')) ?>">
                <span class="field__hint"><?= e(__('courrier.echeance_help')) ?></span>
                <?php if ($fieldError('echeance') !== ''): ?><span class="field__hint field__hint--error"><?= e($fieldError('echeance')) ?></span><?php endif; ?>
            </div>
            <?php if ($projects !== []): ?>
                <div class="field">
                    <label class="field__label" for="courrier-project"><?= e(__('courrier.projet')) ?></label>
                    <select class="field__control" id="courrier-project" name="project_id">
                        <option value=""><?= e(__('courrier.choose_project')) ?></option>
                        <?php foreach ($projects as $project): ?>
                            <option value="<?= e((string) $project['id']) ?>" <?= (string) ($values['project_id'] ?? '') === (string) $project['id'] ? 'selected' : '' ?>><?= e((string) $project['name']) ?><?= ($project['client_name'] ?? '') !== '' ? ' — ' . e((string) $project['client_name']) : '' ?><?= ($project['reference'] ?? '') !== '' ? ' · ' . e((string) $project['reference']) : '' ?></option>
                        <?php endforeach; ?>
                    </select>
                    <?php if ($fieldError('project_id') !== ''): ?><span class="field__hint field__hint--error"><?= e($fieldError('project_id')) ?></span><?php endif; ?>
                </div>
            <?php endif; ?>
            <div class="field">
                <label class="field__label" for="courrier-client"><?= e(__('courrier.client')) ?></label>
                <select class="field__control" id="courrier-client" name="client_id">
                    <option value=""><?= e(__('courrier.choose_client')) ?></option>
                    <?php foreach ($clients as $client): ?>
                        <option value="<?= e((string) $client['id']) ?>" <?= (string) ($values['client_id'] ?? '') === (string) $client['id'] ? 'selected' : '' ?>><?= e((string) $client['display_name']) ?></option>
                    <?php endforeach; ?>
                </select>
                <span class="field__hint"><?= e(__('courrier.client_help')) ?></span>
                <?php if ($fieldError('client_id') !== ''): ?><span class="field__hint field__hint--error"><?= e($fieldError('client_id')) ?></span><?php endif; ?>
            </div>
            <div class="field form-grid__full">
                <label class="field__label" for="courrier-notes"><?= e(__('courrier.notes')) ?></label>
                <textarea class="field__control" id="courrier-notes" name="notes" rows="4" maxlength="4000"><?= e((string) ($values['notes'] ?? '')) ?></textarea>
                <?php if ($fieldError('notes') !== ''): ?><span class="field__hint field__hint--error"><?= e($fieldError('notes')) ?></span><?php endif; ?>
            </div>
        </div>
    </section>

    <div class="form-actions">
        <div class="cluster">
            <a class="button button--ghost" href="<?= e($cancelUrl) ?>"><?= e(__('common.cancel')) ?></a>
            <button class="button button--primary" type="submit"><?= e($isEdit ? __('common.save') : __('courrier.create_submit')) ?></button>
        </div>
    </div>
</form>
