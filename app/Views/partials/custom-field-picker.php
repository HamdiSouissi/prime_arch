<?php
declare(strict_types=1);

use App\Services\CustomFieldService;

/**
 * Rappel des champs personnalisés en attente et création rapide, depuis la fiche elle-même.
 *
 * Une définition désactivée ne s'affiche nulle part sur les fiches : sans ce rappel, créer un
 * champ puis le désactiver revenait à le perdre de vue jusqu'au prochain passage par les
 * réglages. Le bloc liste donc les définitions du même type de fiche restées inactives, avec
 * leur activation en un clic, puis propose la création d'un champ sans quitter la fiche.
 *
 * La clé technique et l'ordre sont calculés côté serveur : le formulaire rapide ne demande que
 * ce qu'un utilisateur connaît de son métier (libellé, type, choix éventuels).
 *
 * @var string $entityType « client » ou « project »
 * @var int $recordId identifiant de la fiche affichée
 * @var array<int,array<string,mixed>> $availableFields définitions inactives de ce type de fiche
 * @var bool $canManage droits de modification du schéma (clients.manage ou projects.configure)
 * @var string|null $notice message de confirmation
 * @var array<int,string> $errors messages d'erreur
 * @var int $activeCount nombre de champs déjà actifs sur la fiche
 */
$entityType = ($entityType ?? '') === 'client' ? 'client' : 'project';
$recordId = (int) ($recordId ?? 0);
$availableFields = is_array($availableFields ?? null) ? $availableFields : [];
$canManage = !empty($canManage);
$notice = is_string($notice ?? null) ? $notice : '';
$errors = is_array($errors ?? null) ? $errors : [];
$activeCount = (int) ($activeCount ?? 0);
$isClient = $entityType === 'client';
$basePath = $isClient ? '/clients/' : '/projects/';
$prefix = 'custom-picker-' . $entityType . '-' . $recordId;
// Un téléversement n'existe que sur les fiches projets : la liste des types en dépend.
$types = $isClient ? CustomFieldService::VALUE_TYPES : CustomFieldService::TYPES;
?>
<?php if ($notice !== ''): ?>
    <p class="form-feedback form-feedback--success" role="status"><?= e($notice) ?></p>
<?php endif; ?>
<?php foreach ($errors as $error): ?>
    <p class="form-feedback form-feedback--error" role="alert"><?= e($error) ?></p>
<?php endforeach; ?>

<?php if ($availableFields !== []): ?>
    <section class="custom-picker" aria-labelledby="<?= e($prefix) ?>-available-title">
        <h3 class="custom-picker__title" id="<?= e($prefix) ?>-available-title">
            <?= e(__('custom_fields.available_title', ['count' => (string) count($availableFields)])) ?>
        </h3>
        <ul class="custom-picker__list">
            <?php foreach ($availableFields as $available): ?>
                <?php
                $availableId = (int) ($available['id'] ?? 0);
                $availableType = (string) ($available['field_type'] ?? '');
                ?>
                <li class="custom-picker__item">
                    <span class="custom-picker__label">
                        <strong><?= e($available['label'] ?? '') ?></strong>
                        <span class="table-primary__meta">
                            <code><?= e((string) ($available['field_key'] ?? '')) ?></code>
                            · <?= e(CustomFieldService::isKnownType($availableType) ? __('custom_fields.type.' . $availableType) : __('custom_fields.type_invalid')) ?>
                        </span>
                    </span>
                    <?php if ($canManage): ?>
                        <form method="post" action="<?= e(url($basePath . $recordId . '/custom-fields/' . $availableId . '/activate')) ?>">
                            <?= csrf_field() ?>
                            <button class="button button--small button--secondary" type="submit"><?= e(__('custom_fields.activate')) ?></button>
                        </form>
                    <?php else: ?>
                        <span class="badge badge--neutral"><?= e(__('common.disabled')) ?></span>
                    <?php endif; ?>
                </li>
            <?php endforeach; ?>
        </ul>
        <p class="field__hint"><?= e(__('custom_fields.available_help')) ?></p>
    </section>
<?php endif; ?>

<?php if ($canManage): ?>
    <details class="custom-quick-add"<?= $activeCount === 0 && $availableFields === [] ? ' open' : '' ?>>
        <summary class="custom-quick-add__summary">
            <span class="custom-quick-add__plus" aria-hidden="true">+</span>
            <?= e(__('custom_fields.quick_add')) ?>
        </summary>
        <form class="custom-quick-add__form" method="post" action="<?= e(url($basePath . $recordId . '/custom-fields')) ?>">
            <?= csrf_field() ?>
            <div class="field">
                <label class="field__label" for="<?= e($prefix) ?>-new-label"><?= e(__('custom_fields.label')) ?></label>
                <input class="field__control" id="<?= e($prefix) ?>-new-label" name="label" maxlength="120" required placeholder="<?= e(__('custom_fields.quick_add_example')) ?>">
                <span class="field__hint"><?= e(__('custom_fields.quick_add_help')) ?></span>
            </div>
            <div class="field">
                <label class="field__label" for="<?= e($prefix) ?>-new-type"><?= e(__('custom_fields.type.label')) ?></label>
                <select class="field__control" id="<?= e($prefix) ?>-new-type" name="field_type">
                    <?php foreach ($types as $type): ?>
                        <option value="<?= e($type) ?>"><?= e(__('custom_fields.type.' . $type)) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="field custom-quick-add__wide">
                <label class="field__label" for="<?= e($prefix) ?>-new-options"><?= e(__('custom_fields.options')) ?></label>
                <textarea class="field__control" id="<?= e($prefix) ?>-new-options" name="options" rows="2" placeholder="<?= e(__('custom_fields.options_example')) ?>"></textarea>
                <span class="field__hint"><?= e(__('custom_fields.options_help')) ?></span>
            </div>
            <div class="custom-quick-add__actions">
                <label class="check-label" for="<?= e($prefix) ?>-new-required">
                    <input id="<?= e($prefix) ?>-new-required" type="checkbox" name="is_required" value="1">
                    <?= e(__('custom_fields.required_label')) ?>
                </label>
                <button class="button button--small button--primary" type="submit"><?= e(__('custom_fields.quick_add_submit')) ?></button>
            </div>
        </form>
    </details>
<?php endif; ?>
