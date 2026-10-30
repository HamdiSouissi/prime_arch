<?php
declare(strict_types=1);

use App\Core\View;
use Modules\PermisBatir\Services\DossierService;

$values = is_array($values ?? null) ? $values : [];
$errors = is_array($errors ?? null) ? $errors : [];
$projects = is_array($projects ?? null) ? $projects : [];
$dossier = is_array($dossier ?? null) ? $dossier : null;
$isEdit = $dossier !== null;
$action = (string) ($action ?? url('/permis'));
$cancelUrl = (string) ($cancelUrl ?? url('/permis'));
$fieldError = static function (string $field) use ($errors): string {
    return (string) ($errors[$field] ?? '');
};
?>
<?php View::partial('partials/page-header', [
    'pageTitle' => $isEdit ? __('permis.edit') : __('permis.create'),
    'eyebrow' => $isEdit ? (string) ($dossier['reference'] ?? '') : __('permis.new_dossier'),
    'subtitle' => __('permis.form_intro'),
    'secondaryAction' => ['label' => __('common.cancel'), 'icon' => 'arrow-left', 'url' => $cancelUrl],
]) ?>

<?php if (!empty($errors)): ?>
    <p class="form-feedback form-feedback--error" role="alert"><?= e(__('permis.fix_errors')) ?></p>
<?php endif; ?>

<form class="card settings-form" method="post" action="<?= e($action) ?>">
    <?= csrf_field() ?>

    <section class="form-section" aria-labelledby="permis-identity-title">
        <div class="section-heading">
            <div>
                <h2 class="section-heading__title" id="permis-identity-title"><?= e(__('permis.section_identity')) ?></h2>
                <p class="section-heading__note"><?= e(__('permis.section_identity_help')) ?></p>
            </div>
        </div>
        <div class="form-grid">
            <?php if ($isEdit): ?>
                <div class="field form-grid__full">
                    <span class="field__label"><?= e(__('permis.project')) ?></span>
                    <p class="field__control field__control--display">
                        <a href="<?= e(url('/projects/' . (int) $dossier['project_id'])) ?>"><?= e((string) $dossier['project_name']) ?></a>
                        <span class="table-meta"><?= e((string) $dossier['client_name']) ?></span>
                    </p>
                </div>
            <?php else: ?>
                <div class="field form-grid__full">
                    <label class="field__label" for="permis-project"><?= e(__('permis.project')) ?></label>
                    <select class="field__control" id="permis-project" name="project_id" required aria-describedby="<?= $fieldError('project_id') !== '' ? 'permis-project-error' : '' ?>">
                        <option value=""><?= e(__('permis.choose_project')) ?></option>
                        <?php foreach ($projects as $project): ?>
                            <option value="<?= e((string) $project['id']) ?>" <?= (string) ($values['project_id'] ?? '') === (string) $project['id'] ? 'selected' : '' ?>><?= e((string) $project['name']) ?> — <?= e((string) $project['client_name']) ?><?= ($project['reference'] ?? '') !== '' ? ' · ' . e((string) $project['reference']) : '' ?></option>
                        <?php endforeach; ?>
                    </select>
                    <?php if ($fieldError('project_id') !== ''): ?><span class="field__hint field__hint--error" id="permis-project-error"><?= e($fieldError('project_id')) ?></span><?php endif; ?>
                    <?php if ($projects === []): ?><span class="field__hint"><?= e(__('permis.no_projects')) ?></span><?php endif; ?>
                </div>
            <?php endif; ?>

            <div class="field">
                <label class="field__label" for="permis-commune"><?= e(__('permis.commune')) ?></label>
                <input class="field__control" type="text" id="permis-commune" name="commune" value="<?= e((string) ($values['commune'] ?? '')) ?>" maxlength="150" required>
                <?php if ($fieldError('commune') !== ''): ?><span class="field__hint field__hint--error"><?= e($fieldError('commune')) ?></span><?php endif; ?>
            </div>

            <div class="field">
                <label class="field__label" for="permis-status"><?= e(__('permis.status.label')) ?></label>
                <select class="field__control" id="permis-status" name="status">
                    <?php foreach (DossierService::STATUSES as $status): ?>
                        <option value="<?= e($status) ?>" <?= (string) ($values['status'] ?? 'preparation') === $status ? 'selected' : '' ?>><?= e(__('permis.status.' . $status)) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
    </section>

    <section class="form-section" aria-labelledby="permis-deposit-title">
        <div class="section-heading">
            <div>
                <h2 class="section-heading__title" id="permis-deposit-title"><?= e(__('permis.section_deposit')) ?></h2>
                <p class="section-heading__note"><?= e(__('permis.section_deposit_help')) ?></p>
            </div>
        </div>
        <div class="form-grid">
            <div class="field">
                <label class="field__label" for="permis-deposit-number"><?= e(__('permis.deposit_number')) ?></label>
                <input class="field__control" type="text" id="permis-deposit-number" name="deposit_number" value="<?= e((string) ($values['deposit_number'] ?? '')) ?>" maxlength="60">
            </div>
            <div class="field">
                <label class="field__label" for="permis-deposit-date"><?= e(__('permis.deposit_date')) ?></label>
                <input class="field__control" type="date" id="permis-deposit-date" name="deposit_date" value="<?= e((string) ($values['deposit_date'] ?? '')) ?>">
                <?php if ($fieldError('deposit_date') !== ''): ?><span class="field__hint field__hint--error"><?= e($fieldError('deposit_date')) ?></span><?php endif; ?>
            </div>
            <div class="field">
                <label class="field__label" for="permis-decision-number"><?= e(__('permis.decision_number')) ?></label>
                <input class="field__control" type="text" id="permis-decision-number" name="decision_number" value="<?= e((string) ($values['decision_number'] ?? '')) ?>" maxlength="60">
            </div>
            <div class="field">
                <label class="field__label" for="permis-decision-date"><?= e(__('permis.decision_date')) ?></label>
                <input class="field__control" type="date" id="permis-decision-date" name="decision_date" value="<?= e((string) ($values['decision_date'] ?? '')) ?>">
                <?php if ($fieldError('decision_date') !== ''): ?><span class="field__hint field__hint--error"><?= e($fieldError('decision_date')) ?></span><?php endif; ?>
            </div>
            <div class="field">
                <label class="field__label" for="permis-deadline-date"><?= e(__('permis.deadline_date')) ?></label>
                <input class="field__control" type="date" id="permis-deadline-date" name="deadline_date" value="<?= e((string) ($values['deadline_date'] ?? '')) ?>">
                <span class="field__hint"><?= e(__('permis.deadline_help')) ?></span>
                <?php if ($fieldError('deadline_date') !== ''): ?><span class="field__hint field__hint--error"><?= e($fieldError('deadline_date')) ?></span><?php endif; ?>
            </div>
            <div class="field form-grid__full">
                <label class="field__label" for="permis-notes"><?= e(__('permis.notes')) ?></label>
                <textarea class="field__control" id="permis-notes" name="notes" rows="4" maxlength="4000"><?= e((string) ($values['notes'] ?? '')) ?></textarea>
                <?php if ($fieldError('notes') !== ''): ?><span class="field__hint field__hint--error"><?= e($fieldError('notes')) ?></span><?php endif; ?>
            </div>
        </div>
    </section>

    <div class="form-actions">
        <div class="cluster">
            <a class="button button--ghost" href="<?= e($cancelUrl) ?>"><?= e(__('common.cancel')) ?></a>
            <button class="button button--primary" type="submit"><?= e($isEdit ? __('common.save') : __('permis.create_submit')) ?></button>
        </div>
    </div>
</form>
