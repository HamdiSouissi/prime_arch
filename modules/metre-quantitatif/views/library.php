<?php
declare(strict_types=1);

use App\Core\View;
use Modules\MetreQuantitatif\Services\MetreService;

$items = is_array($items ?? null) ? $items : [];
$filters = is_array($filters ?? null) ? $filters : ['q' => '', 'active_only' => true];
$units = is_array($units ?? null) ? $units : MetreService::UNITS;
$libraryErrors = is_array($libraryErrors ?? null) ? $libraryErrors : [];
$libraryValues = is_array($libraryValues ?? null) ? $libraryValues : [];

$formValue = static function (string $key, string $default = '') use ($libraryValues): string {
    return (string) ($libraryValues[$key] ?? $default);
};
?>
<?php View::partial('partials/page-header', [
    'pageTitle' => __('metre.library_title'),
    'eyebrow' => __('metre.eyebrow'),
    'subtitle' => __('metre.library_intro'),
    'secondaryAction' => ['label' => __('metre.back'), 'icon' => 'arrow-left', 'url' => url('/metre')],
]) ?>

<?php if (!empty($notice)): ?><p class="form-feedback form-feedback--success" role="status"><?= e($notice) ?></p><?php endif; ?>
<?php if (!empty($error)): ?><p class="form-feedback form-feedback--error" role="alert"><?= e($error) ?></p><?php endif; ?>

<section class="card stack-md" id="articles" aria-labelledby="metre-library-title">
    <div class="card-header">
        <div>
            <h2 class="card-header__title" id="metre-library-title"><?= e(__('metre.library_list_title')) ?></h2>
            <p class="section-heading__note"><?= e(__('metre.library_list_help')) ?></p>
        </div>
    </div>

    <form class="filter-row" method="get" action="<?= e(url('/metre/bibliotheque')) ?>">
        <div class="input-with-icon">
            <svg class="icon icon--small" aria-hidden="true"><use href="<?= e(asset('img/icons.svg')) ?>#search"></use></svg>
            <input class="field__control" type="search" name="q" value="<?= e((string) $filters['q']) ?>"
                   placeholder="<?= e(__('metre.library_search_placeholder')) ?>" aria-label="<?= e(__('common.search')) ?>">
        </div>
        <input type="hidden" name="all" value="0">
        <label class="check-label">
            <input type="checkbox" name="all" value="1" <?= $filters['active_only'] ? '' : 'checked' ?>>
            <?= e(__('metre.show_inactive')) ?>
        </label>
        <button class="button button--secondary" type="submit">
            <svg class="icon icon--small" aria-hidden="true"><use href="<?= e(asset('img/icons.svg')) ?>#filter"></use></svg><?= e(__('common.filter')) ?>
        </button>
        <?php if ((string) $filters['q'] !== '' || !$filters['active_only']): ?>
            <a class="button button--ghost" href="<?= e(url('/metre/bibliotheque')) ?>"><?= e(__('common.clear')) ?></a>
        <?php endif; ?>
    </form>

    <?php if ($items === []): ?>
        <?php View::partial('partials/empty-state', [
            'title' => __('metre.library_empty_title'),
            'description' => __('metre.library_empty_help'),
        ]) ?>
    <?php else: ?>
        <div class="table-wrap">
            <table class="data-table">
                <thead>
                    <tr>
                        <th scope="col"><?= e(__('metre.code')) ?></th>
                        <th scope="col"><?= e(__('metre.designation')) ?></th>
                        <th scope="col"><?= e(__('metre.unit')) ?></th>
                        <th scope="col"><?= e(__('metre.unit_price')) ?></th>
                        <th scope="col"><?= e(__('metre.category')) ?></th>
                        <th scope="col"><?= e(__('metre.state')) ?></th>
                        <?php if (!empty($canManage)): ?><th scope="col"><?= e(__('common.actions')) ?></th><?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($items as $item): ?>
                        <tr>
                            <td><?= (string) ($item['code'] ?? '') !== '' ? e((string) $item['code']) : '—' ?></td>
                            <td><?= e((string) $item['designation']) ?></td>
                            <td><?= e(MetreService::unitLabel((string) $item['unit'])) ?></td>
                            <td class="u-numeric"><?= e(money_format_tnd((string) $item['unit_price'])) ?></td>
                            <td><?= (string) ($item['category'] ?? '') !== '' ? e((string) $item['category']) : '—' ?></td>
                            <td>
                                <?php if ((bool) $item['is_active']): ?>
                                    <span class="badge badge--success"><?= e(__('metre.active')) ?></span>
                                <?php else: ?>
                                    <span class="badge badge--neutral"><?= e(__('metre.inactive')) ?></span>
                                <?php endif; ?>
                            </td>
                            <?php if (!empty($canManage)): ?>
                                <td>
                                    <form method="post" action="<?= e(url('/metre/bibliotheque/' . (int) $item['id'] . '/delete')) ?>"
                                          data-confirm="<?= e(__('metre.confirm_delete_library')) ?>">
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

        <?php if (!empty($canManage)): ?>
            <?php foreach ($items as $item): ?>
                <details class="stack-md">
                    <summary class="text-callout"><?= e(__('metre.edit_item', ['name' => (string) $item['designation']])) ?></summary>
                    <form class="form-grid" method="post" action="<?= e(url('/metre/bibliotheque')) ?>">
                        <?= csrf_field() ?>
                        <input type="hidden" name="id" value="<?= (int) $item['id'] ?>">
                        <div class="field">
                            <label class="field__label" for="lib-code-<?= (int) $item['id'] ?>"><?= e(__('metre.code')) ?></label>
                            <input class="field__control" type="text" id="lib-code-<?= (int) $item['id'] ?>" name="code" maxlength="40"
                                   value="<?= e((string) ($item['code'] ?? '')) ?>">
                        </div>
                        <div class="field form-grid__full">
                            <label class="field__label" for="lib-designation-<?= (int) $item['id'] ?>"><?= e(__('metre.designation')) ?></label>
                            <input class="field__control" type="text" id="lib-designation-<?= (int) $item['id'] ?>" name="designation"
                                   maxlength="180" value="<?= e((string) $item['designation']) ?>" required>
                        </div>
                        <div class="field">
                            <label class="field__label" for="lib-unit-<?= (int) $item['id'] ?>"><?= e(__('metre.unit')) ?></label>
                            <select class="field__control" id="lib-unit-<?= (int) $item['id'] ?>" name="unit">
                                <?php foreach ($units as $unit): ?>
                                    <option value="<?= e($unit) ?>" <?= (string) $item['unit'] === $unit ? 'selected' : '' ?>><?= e(MetreService::unitLabel($unit)) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="field">
                            <label class="field__label" for="lib-price-<?= (int) $item['id'] ?>"><?= e(__('metre.unit_price')) ?></label>
                            <input class="field__control" type="text" inputmode="decimal" id="lib-price-<?= (int) $item['id'] ?>" name="unit_price"
                                   value="<?= e((string) $item['unit_price']) ?>" required>
                        </div>
                        <div class="field">
                            <label class="field__label" for="lib-category-<?= (int) $item['id'] ?>"><?= e(__('metre.category')) ?></label>
                            <input class="field__control" type="text" id="lib-category-<?= (int) $item['id'] ?>" name="category" maxlength="80"
                                   value="<?= e((string) ($item['category'] ?? '')) ?>">
                        </div>
                        <div class="field">
                            <input type="hidden" name="is_active" value="0">
                            <label class="check-label">
                                <input type="checkbox" name="is_active" value="1" <?= (bool) $item['is_active'] ? 'checked' : '' ?>>
                                <?= e(__('metre.active')) ?>
                            </label>
                        </div>
                        <div class="form-actions form-grid__full">
                            <button class="button button--secondary" type="submit"><?= e(__('common.save')) ?></button>
                        </div>
                    </form>
                </details>
            <?php endforeach; ?>
        <?php endif; ?>
    <?php endif; ?>

    <?php if (!empty($canManage)): ?>
        <form class="stack-md" method="post" action="<?= e(url('/metre/bibliotheque')) ?>" id="metre-add-library">
            <?= csrf_field() ?>
            <h3 class="section-heading__title"><?= e(__('metre.add_library_item')) ?></h3>
            <?php if (!empty($libraryErrors)): ?>
                <p class="form-feedback form-feedback--error" role="alert"><?= e(__('metre.fix_errors')) ?></p>
            <?php endif; ?>
            <div class="form-grid">
                <div class="field">
                    <label class="field__label" for="new-lib-code"><?= e(__('metre.code')) ?></label>
                    <input class="field__control" type="text" id="new-lib-code" name="code" maxlength="40" value="<?= e($formValue('code')) ?>">
                    <?php if (!empty($libraryErrors['code'])): ?><span class="field__hint field__hint--error"><?= e($libraryErrors['code']) ?></span><?php endif; ?>
                </div>
                <div class="field form-grid__full">
                    <label class="field__label" for="new-lib-designation"><?= e(__('metre.designation')) ?></label>
                    <input class="field__control" type="text" id="new-lib-designation" name="designation" maxlength="180"
                           value="<?= e($formValue('designation')) ?>" required>
                    <?php if (!empty($libraryErrors['designation'])): ?><span class="field__hint field__hint--error"><?= e($libraryErrors['designation']) ?></span><?php endif; ?>
                </div>
                <div class="field">
                    <label class="field__label" for="new-lib-unit"><?= e(__('metre.unit')) ?></label>
                    <select class="field__control" id="new-lib-unit" name="unit">
                        <?php foreach ($units as $unit): ?>
                            <option value="<?= e($unit) ?>" <?= $formValue('unit', 'u') === $unit ? 'selected' : '' ?>><?= e(MetreService::unitLabel($unit)) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="field">
                    <label class="field__label" for="new-lib-price"><?= e(__('metre.unit_price')) ?></label>
                    <input class="field__control" type="text" inputmode="decimal" id="new-lib-price" name="unit_price"
                           value="<?= e($formValue('unit_price')) ?>" placeholder="0.000" required>
                    <?php if (!empty($libraryErrors['unit_price'])): ?><span class="field__hint field__hint--error"><?= e($libraryErrors['unit_price']) ?></span><?php endif; ?>
                </div>
                <div class="field">
                    <label class="field__label" for="new-lib-category"><?= e(__('metre.category')) ?></label>
                    <input class="field__control" type="text" id="new-lib-category" name="category" maxlength="80" value="<?= e($formValue('category')) ?>">
                </div>
                <div class="field">
                    <input type="hidden" name="is_active" value="0">
                    <label class="check-label">
                        <input type="checkbox" name="is_active" value="1" checked>
                        <?= e(__('metre.active')) ?>
                    </label>
                </div>
            </div>
            <div class="form-actions">
                <button class="button button--primary" type="submit"><?= e(__('metre.add_library_submit')) ?></button>
            </div>
        </form>
    <?php endif; ?>
</section>
