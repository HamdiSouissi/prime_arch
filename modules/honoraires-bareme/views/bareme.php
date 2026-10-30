<?php
declare(strict_types=1);

use App\Core\View;

$mission = is_array($mission ?? null) ? $mission : [];
$tranches = is_array($tranches ?? null) ? $tranches : [];
$phases = is_array($phases ?? null) ? $phases : [];
$missionErrors = is_array($missionErrors ?? null) ? $missionErrors : [];
$missionValues = is_array($missionValues ?? null) ? $missionValues : [];
$trancheErrors = is_array($trancheErrors ?? null) ? $trancheErrors : [];
$trancheValues = is_array($trancheValues ?? null) ? $trancheValues : [];
$missionId = (int) ($mission['id'] ?? 0);

$formValue = static fn (string $key, mixed $default = ''): string => (string) ($missionValues[$key] ?? $mission[$key] ?? $default);
?>
<?php View::partial('partials/page-header', [
    'pageTitle' => (string) ($mission['name'] ?? ''),
    'eyebrow' => __('honoraire.eyebrow'),
    'subtitle' => __('honoraire.bareme_intro'),
    'secondaryAction' => ['label' => __('honoraire.back_baremes'), 'icon' => 'arrow-left', 'url' => url('/honoraires/baremes')],
]) ?>

<?php if (!empty($notice)): ?><p class="form-feedback form-feedback--success" role="status"><?= e($notice) ?></p><?php endif; ?>
<?php if (!empty($error)): ?><p class="form-feedback form-feedback--error" role="alert"><?= e($error) ?></p><?php endif; ?>

<p class="text-callout">
    <svg class="icon icon--small" aria-hidden="true"><use href="<?= e(asset('img/icons.svg')) ?>#info"></use></svg>
    <?= e(__('honoraire.oat_warning')) ?>
</p>

<section class="card stack-md" id="bareme" aria-labelledby="honoraire-bareme-title">
    <div class="card-header">
        <div>
            <h2 class="card-header__title" id="honoraire-bareme-title"><?= e(__('honoraire.bareme_details')) ?></h2>
            <p class="section-heading__note"><?= e(__('honoraire.bareme_details_help')) ?></p>
        </div>
    </div>

    <?php if ($missionErrors !== []): ?>
        <p class="form-feedback form-feedback--error" role="alert"><?= e(__('honoraire.fix_errors')) ?></p>
    <?php endif; ?>

    <?php if (!empty($canManage)): ?>
    <form class="form-grid" method="post" action="<?= e(url('/honoraires/baremes/' . $missionId)) ?>">
        <?= csrf_field() ?>
        <div class="field form-grid__full">
            <label class="field__label" for="bareme-name"><?= e(__('honoraire.mission_name')) ?></label>
            <input class="field__control" type="text" id="bareme-name" name="name" maxlength="150"
                   value="<?= e($formValue('name')) ?>" required>
            <?php if (!empty($missionErrors['name'])): ?><span class="field__hint field__hint--error"><?= e($missionErrors['name']) ?></span><?php endif; ?>
        </div>
        <div class="field form-grid__full">
            <label class="field__label" for="bareme-description"><?= e(__('honoraire.description')) ?></label>
            <input class="field__control" type="text" id="bareme-description" name="description" maxlength="2000"
                   value="<?= e($formValue('description')) ?>">
            <?php if (!empty($missionErrors['description'])): ?><span class="field__hint field__hint--error"><?= e($missionErrors['description']) ?></span><?php endif; ?>
        </div>
        <div class="field">
            <label class="field__label" for="bareme-order"><?= e(__('honoraire.sort_order')) ?></label>
            <input class="field__control" type="number" min="0" max="999" id="bareme-order" name="sort_order"
                   value="<?= e($formValue('sort_order', 0)) ?>">
        </div>
        <div class="field">
            <input type="hidden" name="is_active" value="0">
            <label class="check-label">
                <input type="checkbox" name="is_active" value="1" <?= (int) $formValue('is_active', 1) === 1 ? 'checked' : '' ?>>
                <?= e(__('honoraire.active')) ?>
            </label>
        </div>
        <div class="form-actions form-grid__full">
            <button class="button button--primary" type="submit"><?= e(__('common.save')) ?></button>
        </div>
    </form>
    <?php else: ?>
        <dl class="detail-list">
            <div class="detail-list__item"><dt><?= e(__('honoraire.mission_name')) ?></dt><dd><?= e($formValue('name')) ?></dd></div>
            <div class="detail-list__item"><dt><?= e(__('honoraire.description')) ?></dt><dd><?= e($formValue('description')) !== '' ? e($formValue('description')) : '—' ?></dd></div>
            <div class="detail-list__item"><dt><?= e(__('honoraire.state')) ?></dt><dd><?= (int) $formValue('is_active', 1) === 1 ? e(__('honoraire.active')) : e(__('honoraire.inactive')) ?></dd></div>
        </dl>
    <?php endif; ?>
</section>

<section class="card stack-md" id="tranches" aria-labelledby="honoraire-tranches-title">
    <div class="card-header">
        <div>
            <h2 class="card-header__title" id="honoraire-tranches-title"><?= e(__('honoraire.tranches_title')) ?></h2>
            <p class="section-heading__note"><?= e(__('honoraire.tranches_help')) ?></p>
        </div>
    </div>

    <?php if ($tranches === []): ?>
        <?php View::partial('partials/empty-state', [
            'title' => __('honoraire.no_tranches_title'),
            'description' => __('honoraire.no_tranches_help'),
        ]) ?>
    <?php else: ?>
        <div class="table-wrap">
            <table class="data-table">
                <thead>
                    <tr>
                        <th scope="col"><?= e(__('honoraire.phase')) ?></th>
                        <th scope="col"><?= e(__('honoraire.from_amount')) ?></th>
                        <th scope="col"><?= e(__('honoraire.to_amount')) ?></th>
                        <th scope="col"><?= e(__('honoraire.rate')) ?></th>
                        <?php if (!empty($canManage)): ?><th scope="col"><?= e(__('common.actions')) ?></th><?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($tranches as $tranche): ?>
                        <tr>
                            <td><?= e($phases[(string) $tranche['phase_key']] ?? (string) $tranche['phase_key']) ?></td>
                            <td class="u-numeric"><?= e(money_format_tnd((string) $tranche['min_amount'])) ?></td>
                            <td class="u-numeric">
                                <?php if ($tranche['max_amount'] === null): ?>
                                    <span class="muted"><?= e(__('honoraire.no_limit')) ?></span>
                                <?php else: ?>
                                    <?= e(money_format_tnd((string) $tranche['max_amount'])) ?>
                                <?php endif; ?>
                            </td>
                            <td class="u-numeric"><strong><?= e((string) $tranche['rate_percent']) ?> %</strong></td>
                            <?php if (!empty($canManage)): ?>
                                <td>
                                    <form method="post"
                                          action="<?= e(url('/honoraires/baremes/' . $missionId . '/tranches/' . (int) $tranche['id'] . '/delete')) ?>"
                                          data-confirm="<?= e(__('honoraire.confirm_delete_tranche')) ?>">
                                        <?= csrf_field() ?>
                                        <button class="button button--small button--ghost" type="submit"><?= e(__('common.delete')) ?></button>
                                    </form>
                                </td>
                            <?php endif; ?>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>

    <?php if (!empty($canManage)): ?>
        <h3 class="section-heading__title"><?= e(__('honoraire.add_tranche')) ?></h3>
        <?php if ($trancheErrors !== []): ?>
            <p class="form-feedback form-feedback--error" role="alert"><?= e(__('honoraire.fix_errors')) ?></p>
        <?php endif; ?>
        <form class="form-grid" method="post" action="<?= e(url('/honoraires/baremes/' . $missionId . '/tranches')) ?>">
            <?= csrf_field() ?>
            <div class="field">
                <label class="field__label" for="tranche-phase"><?= e(__('honoraire.phase')) ?></label>
                <select class="field__control" id="tranche-phase" name="phase_key" required>
                    <?php foreach ($phases as $key => $name): ?>
                        <option value="<?= e((string) $key) ?>" <?= (string) ($trancheValues['phase_key'] ?? '') === (string) $key ? 'selected' : '' ?>>
                            <?= e((string) $name) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <?php if (!empty($trancheErrors['phase_key'])): ?><span class="field__hint field__hint--error"><?= e($trancheErrors['phase_key']) ?></span><?php endif; ?>
            </div>
            <div class="field">
                <label class="field__label" for="tranche-min"><?= e(__('honoraire.from_amount')) ?></label>
                <input class="field__control" type="text" inputmode="decimal" id="tranche-min" name="min_amount"
                       value="<?= e((string) ($trancheValues['min_amount'] ?? '')) ?>" placeholder="0.000">
                <span class="field__hint"><?= e(__('honoraire.from_amount_help')) ?></span>
                <?php if (!empty($trancheErrors['min_amount'])): ?><span class="field__hint field__hint--error"><?= e($trancheErrors['min_amount']) ?></span><?php endif; ?>
            </div>
            <div class="field">
                <label class="field__label" for="tranche-max"><?= e(__('honoraire.to_amount')) ?></label>
                <input class="field__control" type="text" inputmode="decimal" id="tranche-max" name="max_amount"
                       value="<?= e((string) ($trancheValues['max_amount'] ?? '')) ?>" placeholder="<?= e(__('honoraire.to_amount_placeholder')) ?>">
                <?php if (!empty($trancheErrors['max_amount'])): ?><span class="field__hint field__hint--error"><?= e($trancheErrors['max_amount']) ?></span><?php endif; ?>
            </div>
            <div class="field">
                <label class="field__label" for="tranche-rate"><?= e(__('honoraire.rate')) ?></label>
                <input class="field__control" type="text" inputmode="decimal" id="tranche-rate" name="rate_percent"
                       value="<?= e((string) ($trancheValues['rate_percent'] ?? '')) ?>" placeholder="0.000" required>
                <span class="field__hint"><?= e(__('honoraire.rate_help')) ?></span>
                <?php if (!empty($trancheErrors['rate_percent'])): ?><span class="field__hint field__hint--error"><?= e($trancheErrors['rate_percent']) ?></span><?php endif; ?>
            </div>
            <div class="field">
                <label class="field__label" for="tranche-order"><?= e(__('honoraire.sort_order')) ?></label>
                <input class="field__control" type="number" min="0" max="999" id="tranche-order" name="sort_order"
                       value="<?= e((string) ($trancheValues['sort_order'] ?? 0)) ?>">
            </div>
            <div class="form-actions form-grid__full">
                <button class="button button--primary" type="submit"><?= e(__('honoraire.add_tranche_submit')) ?></button>
            </div>
        </form>
    <?php endif; ?>
</section>

<?php if (!empty($canManage)): ?>
    <section class="card stack-md" aria-labelledby="honoraire-delete-bareme-title">
        <div class="card-header">
            <div>
                <h2 class="card-header__title" id="honoraire-delete-bareme-title"><?= e(__('honoraire.delete_bareme')) ?></h2>
                <p class="section-heading__note"><?= e(__('honoraire.delete_bareme_help')) ?></p>
            </div>
        </div>
        <form method="post" action="<?= e(url('/honoraires/baremes/' . $missionId . '/delete')) ?>"
              data-confirm="<?= e(__('honoraire.confirm_delete_bareme')) ?>">
            <?= csrf_field() ?>
            <button class="button button--ghost" type="submit"><?= e(__('honoraire.delete_bareme')) ?></button>
        </form>
    </section>
<?php endif; ?>
