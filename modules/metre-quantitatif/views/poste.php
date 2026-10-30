<?php
declare(strict_types=1);

use App\Core\View;
use Modules\MetreQuantitatif\Services\Decimal;
use Modules\MetreQuantitatif\Services\MetreService;

$project = is_array($project ?? null) ? $project : [];
$lot = is_array($lot ?? null) ? $lot : [];
$poste = is_array($poste ?? null) ? $poste : [];
$values = is_array($values ?? null) ? $values : $poste;
$errors = is_array($errors ?? null) ? $errors : [];
$units = is_array($units ?? null) ? $units : MetreService::UNITS;
$projectId = (int) ($project['id'] ?? 0);
$lotId = (int) ($lot['id'] ?? 0);
$posteId = (int) ($poste['id'] ?? 0);
$quantity = Decimal::parse($values['quantity'] ?? '0') ?? 0;
$unitPrice = Decimal::parse($values['unit_price'] ?? '0') ?? 0;
$lineTotal = Decimal::multiply($quantity, $unitPrice);
?>
<?php View::partial('partials/page-header', [
    'pageTitle' => (string) ($poste['designation'] ?? __('metre.poste_edit_title')),
    'eyebrow' => (string) ($lot['name'] ?? __('metre.eyebrow')),
    'subtitle' => (string) ($project['name'] ?? ''),
    'secondaryAction' => ['label' => __('metre.back_to_metre'), 'icon' => 'arrow-left', 'url' => url('/metre/' . $projectId . '#lot-' . $lotId)],
]) ?>

<?php if (!empty($notice)): ?><p class="form-feedback form-feedback--success" role="status"><?= e($notice) ?></p><?php endif; ?>
<?php if (!empty($error)): ?><p class="form-feedback form-feedback--error" role="alert"><?= e($error) ?></p><?php endif; ?>

<section class="card stack-md" aria-labelledby="metre-poste-title">
    <div class="card-header">
        <div>
            <h2 class="card-header__title" id="metre-poste-title"><?= e(__('metre.poste_edit_title')) ?></h2>
            <p class="section-heading__note"><?= e(__('metre.poste_edit_help')) ?></p>
        </div>
        <span class="badge badge--neutral"><?= e(__('metre.line_total')) ?> · <?= e(money_format_tnd(Decimal::format($lineTotal))) ?></span>
    </div>

    <?php if ($errors !== []): ?>
        <p class="form-feedback form-feedback--error" role="alert"><?= e(__('metre.fix_errors')) ?></p>
    <?php endif; ?>

    <form class="form-grid" method="post" action="<?= e(url('/metre/' . $projectId . '/postes/' . $posteId)) ?>">
        <?= csrf_field() ?>
        <div class="field">
            <label class="field__label" for="poste-code"><?= e(__('metre.code')) ?></label>
            <input class="field__control" type="text" id="poste-code" name="code" maxlength="40" value="<?= e((string) ($values['code'] ?? '')) ?>">
        </div>
        <div class="field form-grid__full">
            <label class="field__label" for="poste-designation"><?= e(__('metre.designation')) ?></label>
            <input class="field__control" type="text" id="poste-designation" name="designation" maxlength="180"
                   value="<?= e((string) ($values['designation'] ?? '')) ?>" required>
            <?php if (!empty($errors['designation'])): ?><span class="field__hint field__hint--error"><?= e($errors['designation']) ?></span><?php endif; ?>
        </div>
        <div class="field">
            <label class="field__label" for="poste-unit"><?= e(__('metre.unit')) ?></label>
            <select class="field__control" id="poste-unit" name="unit">
                <?php foreach ($units as $unit): ?>
                    <option value="<?= e($unit) ?>" <?= (string) ($values['unit'] ?? 'u') === $unit ? 'selected' : '' ?>><?= e(MetreService::unitLabel($unit)) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="field">
            <label class="field__label" for="poste-quantity"><?= e(__('metre.quantity')) ?></label>
            <input class="field__control" type="text" inputmode="decimal" id="poste-quantity" name="quantity"
                   value="<?= e((string) ($values['quantity'] ?? '')) ?>" required>
            <?php if (!empty($errors['quantity'])): ?><span class="field__hint field__hint--error"><?= e($errors['quantity']) ?></span><?php endif; ?>
        </div>
        <div class="field">
            <label class="field__label" for="poste-unit-price"><?= e(__('metre.unit_price')) ?></label>
            <input class="field__control" type="text" inputmode="decimal" id="poste-unit-price" name="unit_price"
                   value="<?= e((string) ($values['unit_price'] ?? '')) ?>" required>
            <?php if (!empty($errors['unit_price'])): ?><span class="field__hint field__hint--error"><?= e($errors['unit_price']) ?></span><?php endif; ?>
        </div>
        <div class="field">
            <label class="field__label" for="poste-margin"><?= e(__('metre.margin_percent')) ?></label>
            <input class="field__control" type="text" inputmode="decimal" id="poste-margin" name="margin_percent"
                   value="<?= e((string) ($values['margin_percent'] ?? '')) ?>">
            <span class="field__hint"><?= e(__('metre.margin_help')) ?></span>
            <?php if (!empty($errors['margin_percent'])): ?><span class="field__hint field__hint--error"><?= e($errors['margin_percent']) ?></span><?php endif; ?>
        </div>
        <div class="field">
            <label class="field__label" for="poste-order"><?= e(__('metre.sort_order')) ?></label>
            <input class="field__control" type="number" min="0" max="999" id="poste-order" name="sort_order"
                   value="<?= e((string) ($values['sort_order'] ?? '0')) ?>">
        </div>
        <div class="form-actions form-grid__full">
            <button class="button button--primary" type="submit"><?= e(__('common.save')) ?></button>
            <a class="button button--ghost" href="<?= e(url('/metre/' . $projectId . '#lot-' . $lotId)) ?>"><?= e(__('common.cancel')) ?></a>
        </div>
    </form>

    <?php if (!empty($canManage)): ?>
        <form method="post" action="<?= e(url('/metre/' . $projectId . '/postes/' . $posteId . '/delete')) ?>"
              data-confirm="<?= e(__('metre.confirm_delete_poste')) ?>">
            <?= csrf_field() ?>
            <button class="button button--small button--ghost" type="submit">
                <svg class="icon icon--small" aria-hidden="true"><use href="<?= e(asset('img/icons.svg')) ?>#trash"></use></svg><?= e(__('metre.delete_poste')) ?>
            </button>
        </form>
    <?php endif; ?>
</section>
