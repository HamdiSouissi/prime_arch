<?php
declare(strict_types=1);

use App\Core\View;
use Modules\MetreQuantitatif\Services\Decimal;
use Modules\MetreQuantitatif\Services\MetreService;

$project = is_array($project ?? null) ? $project : [];
$summary = is_array($summary ?? null) ? $summary : [];
$library = is_array($library ?? null) ? $library : [];
$units = is_array($units ?? null) ? $units : MetreService::UNITS;
$suggestedLots = is_array($suggestedLots ?? null) ? $suggestedLots : [];
$lotErrors = is_array($lotErrors ?? null) ? $lotErrors : [];
$lotValues = is_array($lotValues ?? null) ? $lotValues : [];
$posteErrors = is_array($posteErrors ?? null) ? $posteErrors : [];
$posteValues = is_array($posteValues ?? null) ? $posteValues : [];
$lots = is_array($summary['lots'] ?? null) ? $summary['lots'] : [];
$projectId = (int) ($project['id'] ?? 0);
$hasPostes = (int) ($summary['postes_count'] ?? 0) > 0;
$quantityLabel = static function (string $value): string {
    $millimes = Decimal::parse($value);

    return $millimes === null ? $value : Decimal::label($millimes);
};
$rateLabel = static function (string $value): string {
    $rate = Decimal::parseRate($value);

    return $rate === null ? $value : Decimal::rateLabel($rate);
};
?>
<?php View::partial('partials/page-header', [
    'pageTitle' => (string) ($project['name'] ?? ''),
    'eyebrow' => __('metre.eyebrow'),
    'subtitle' => __('metre.show_intro', [
        'client' => (string) ($project['client_name'] ?? ''),
        'reference' => (string) ($project['reference'] ?? '—'),
    ]),
    'secondaryAction' => ['label' => __('metre.back'), 'icon' => 'arrow-left', 'url' => url('/metre')],
    'primaryAction' => $hasPostes
        ? ['label' => __('metre.open_dqe'), 'icon' => 'printer', 'url' => url('/metre/' . $projectId . '/dqe')]
        : null,
]) ?>

<?php if (!empty($notice)): ?><p class="form-feedback form-feedback--success" role="status"><?= e($notice) ?></p><?php endif; ?>
<?php if (!empty($error)): ?><p class="form-feedback form-feedback--error" role="alert"><?= e($error) ?></p><?php endif; ?>

<section class="stats-grid" aria-label="<?= e(__('metre.statistics')) ?>">
    <?php View::partial('partials/stat-card', ['label' => __('metre.total_ht'), 'value' => money_format_tnd((string) ($summary['total'] ?? '0.000')), 'icon' => 'wallet', 'tone' => 'mint']) ?>
    <?php View::partial('partials/stat-card', ['label' => __('metre.lots'), 'value' => (string) ($summary['lots_count'] ?? 0), 'icon' => 'layers', 'tone' => 'blue']) ?>
    <?php View::partial('partials/stat-card', ['label' => __('metre.postes'), 'value' => (string) ($summary['postes_count'] ?? 0), 'icon' => 'list', 'tone' => 'pink']) ?>
    <?php View::partial('partials/stat-card', [
        'label' => __('metre.estimated_margin'),
        'value' => money_format_tnd((string) ($summary['margin_amount'] ?? '0.000')),
        'icon' => 'chart',
        'tone' => 'coral',
        'note' => (bool) ($summary['has_margin'] ?? false)
            ? __('metre.cost_note', ['amount' => money_format_tnd((string) ($summary['cost'] ?? '0.000'))])
            : __('metre.no_margin'),
    ]) ?>
</section>

<div class="cluster">
    <a class="button button--secondary button--small" href="<?= e(url('/metre/' . $projectId . '/export-csv')) ?>">
        <svg class="icon icon--small" aria-hidden="true"><use href="<?= e(asset('img/icons.svg')) ?>#download"></use></svg><?= e(__('metre.export_csv')) ?>
    </a>
    <a class="button button--ghost button--small" href="<?= e(url('/metre/bibliotheque')) ?>"><?= e(__('metre.library_link')) ?></a>
</div>

<p class="table-meta" role="note"><?= e(__('metre.prices_notice')) ?></p>

<?php if (!empty($posteErrors)): ?>
    <div class="form-feedback form-feedback--error" role="alert">
        <p><?= e(__('metre.fix_errors')) ?></p>
        <ul>
            <?php foreach ($posteErrors as $message): ?><li><?= e($message) ?></li><?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<section class="stack-lg" id="lots" aria-label="<?= e(__('metre.lots_title')) ?>">
    <?php if ($lots === []): ?>
        <?php View::partial('partials/empty-state', [
            'title' => __('metre.no_lots_title'),
            'description' => __('metre.no_lots_help'),
        ]) ?>
    <?php endif; ?>

    <?php foreach ($lots as $row): ?>
        <?php
        $lot = $row['lot'];
        $lotId = (int) $lot['id'];
        $postes = is_array($row['postes'] ?? null) ? $row['postes'] : [];
        ?>
        <section class="card stack-md" id="lot-<?= $lotId ?>" aria-labelledby="lot-title-<?= $lotId ?>">
            <div class="card-header">
                <div>
                    <h2 class="card-header__title" id="lot-title-<?= $lotId ?>"><?= e((string) $lot['name']) ?></h2>
                    <p class="section-heading__note"><?= e(count($postes) > 1 ? __('metre.postes_count', ['count' => (string) count($postes)]) : __('metre.poste_count', ['count' => (string) count($postes)])) ?></p>
                </div>
                <div class="cluster">
                    <span class="badge badge--neutral"><?= e(__('metre.subtotal')) ?> · <?= e(money_format_tnd((string) $row['total'])) ?></span>
                    <?php if (!empty($canManage)): ?>
                        <form method="post" action="<?= e(url('/metre/' . $projectId . '/lots/' . $lotId . '/delete')) ?>"
                              data-confirm="<?= e(__('metre.confirm_delete_lot')) ?>">
                            <?= csrf_field() ?>
                            <button class="button button--small button--ghost" type="submit">
                                <svg class="icon icon--small" aria-hidden="true"><use href="<?= e(asset('img/icons.svg')) ?>#trash"></use></svg><?= e(__('metre.delete_lot')) ?>
                            </button>
                        </form>
                    <?php endif; ?>
                </div>
            </div>

            <?php if ($postes === []): ?>
                <p class="muted"><?= e(__('metre.no_postes')) ?></p>
            <?php else: ?>
                <div class="table-wrap">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th scope="col"><?= e(__('metre.code')) ?></th>
                                <th scope="col"><?= e(__('metre.designation')) ?></th>
                                <th scope="col"><?= e(__('metre.unit')) ?></th>
                                <th scope="col"><?= e(__('metre.quantity')) ?></th>
                                <th scope="col"><?= e(__('metre.unit_price')) ?></th>
                                <th scope="col"><?= e(__('metre.line_total')) ?></th>
                                <th scope="col"><?= e(__('metre.margin')) ?></th>
                                <?php if (!empty($canManage)): ?><th scope="col"><?= e(__('common.actions')) ?></th><?php endif; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($postes as $poste): ?>
                                <tr>
                                    <td><?= e((string) ($poste['code'] ?? '')) !== '' ? e((string) $poste['code']) : '—' ?></td>
                                    <td><?= e((string) $poste['designation']) ?></td>
                                    <td><?= e(MetreService::unitLabel((string) $poste['unit'])) ?></td>
                                    <td class="u-numeric"><?= e($quantityLabel((string) $poste['quantity'])) ?></td>
                                    <td class="u-numeric"><?= e(money_format_tnd((string) $poste['unit_price'])) ?></td>
                                    <td class="u-numeric"><strong><?= e(money_format_tnd((string) $poste['total'])) ?></strong></td>
                                    <td class="u-numeric">
                                        <?= (string) $poste['margin_percent'] !== '0.000' ? e($rateLabel((string) $poste['margin_percent'])) . ' %' : '—' ?>
                                    </td>
                                    <?php if (!empty($canManage)): ?>
                                        <td>
                                            <div class="cluster">
                                                <a class="button button--small button--secondary"
                                                   href="<?= e(url('/metre/' . $projectId . '/postes/' . (int) $poste['id'])) ?>"><?= e(__('common.edit')) ?></a>
                                                <form method="post" action="<?= e(url('/metre/' . $projectId . '/postes/' . (int) $poste['id'] . '/delete')) ?>"
                                                      data-confirm="<?= e(__('metre.confirm_delete_poste')) ?>">
                                                    <?= csrf_field() ?>
                                                    <button class="button button--small button--ghost" type="submit"><?= e(__('common.delete')) ?></button>
                                                </form>
                                            </div>
                                        </td>
                                    <?php endif; ?>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>

            <?php if (!empty($canManage)): ?>
                <details class="stack-md">
                    <summary class="text-callout"><?= e(__('metre.add_poste')) ?></summary>
                    <form class="form-grid" method="post" action="<?= e(url('/metre/' . $projectId . '/lots/' . $lotId . '/postes')) ?>">
                        <?= csrf_field() ?>
                        <div class="field form-grid__full">
                            <label class="field__label" for="poste-library-<?= $lotId ?>"><?= e(__('metre.from_library')) ?></label>
                            <select class="field__control" id="poste-library-<?= $lotId ?>" name="library_id">
                                <option value=""><?= e(__('metre.no_library_item')) ?></option>
                                <?php foreach ($library as $item): ?>
                                    <option value="<?= (int) $item['id'] ?>">
                                        <?= e((string) $item['designation']) ?> · <?= e(MetreService::unitLabel((string) $item['unit'])) ?> · <?= e(money_format_tnd((string) $item['unit_price'])) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <span class="field__hint"><?= e(__('metre.from_library_help')) ?></span>
                        </div>
                        <div class="field">
                            <label class="field__label" for="poste-code-<?= $lotId ?>"><?= e(__('metre.code')) ?></label>
                            <input class="field__control" type="text" id="poste-code-<?= $lotId ?>" name="code" maxlength="40"
                                   value="<?= e((string) ($posteValues['code'] ?? '')) ?>">
                        </div>
                        <div class="field form-grid__full">
                            <label class="field__label" for="poste-designation-<?= $lotId ?>"><?= e(__('metre.designation')) ?></label>
                            <input class="field__control" type="text" id="poste-designation-<?= $lotId ?>" name="designation" maxlength="180"
                                   value="<?= e((string) ($posteValues['designation'] ?? '')) ?>" required>
                        </div>
                        <div class="field">
                            <label class="field__label" for="poste-unit-<?= $lotId ?>"><?= e(__('metre.unit')) ?></label>
                            <select class="field__control" id="poste-unit-<?= $lotId ?>" name="unit">
                                <?php foreach ($units as $unit): ?>
                                    <option value="<?= e($unit) ?>" <?= (string) ($posteValues['unit'] ?? 'u') === $unit ? 'selected' : '' ?>><?= e(MetreService::unitLabel($unit)) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="field">
                            <label class="field__label" for="poste-quantity-<?= $lotId ?>"><?= e(__('metre.quantity')) ?></label>
                            <input class="field__control" type="text" inputmode="decimal" id="poste-quantity-<?= $lotId ?>" name="quantity"
                                   value="<?= e((string) ($posteValues['quantity'] ?? '')) ?>" placeholder="0.000" required>
                        </div>
                        <div class="field">
                            <label class="field__label" for="poste-price-<?= $lotId ?>"><?= e(__('metre.unit_price')) ?></label>
                            <input class="field__control" type="text" inputmode="decimal" id="poste-price-<?= $lotId ?>" name="unit_price"
                                   value="<?= e((string) ($posteValues['unit_price'] ?? '')) ?>" placeholder="0.000" required>
                        </div>
                        <div class="field">
                            <label class="field__label" for="poste-margin-<?= $lotId ?>"><?= e(__('metre.margin_percent')) ?></label>
                            <input class="field__control" type="text" inputmode="decimal" id="poste-margin-<?= $lotId ?>" name="margin_percent"
                                   value="<?= e((string) ($posteValues['margin_percent'] ?? '')) ?>" placeholder="0.000">
                            <span class="field__hint"><?= e(__('metre.margin_help')) ?></span>
                        </div>
                        <div class="form-actions form-grid__full">
                            <button class="button button--secondary" type="submit"><?= e(__('metre.add_poste_submit')) ?></button>
                        </div>
                    </form>
                </details>
            <?php endif; ?>
        </section>
    <?php endforeach; ?>
</section>

<?php if (!empty($canManage)): ?>
    <section class="card stack-md" id="add-lot" aria-labelledby="metre-add-lot-title">
        <h2 class="card-header__title" id="metre-add-lot-title"><?= e(__('metre.add_lot')) ?></h2>
        <?php if (!empty($lotErrors)): ?>
            <p class="form-feedback form-feedback--error" role="alert"><?= e(__('metre.fix_errors')) ?></p>
        <?php endif; ?>
        <form class="form-grid" method="post" action="<?= e(url('/metre/' . $projectId . '/lots')) ?>">
            <?= csrf_field() ?>
            <div class="field">
                <label class="field__label" for="lot-name"><?= e(__('metre.lot_name')) ?></label>
                <input class="field__control" type="text" id="lot-name" name="name" maxlength="150" list="lot-suggestions"
                       value="<?= e((string) ($lotValues['name'] ?? '')) ?>" required>
                <datalist id="lot-suggestions">
                    <?php foreach ($suggestedLots as $suggestion): ?>
                        <option value="<?= e(__('metre.lot_suggestion.' . $suggestion)) ?>"></option>
                    <?php endforeach; ?>
                </datalist>
                <?php if (!empty($lotErrors['name'])): ?><span class="field__hint field__hint--error"><?= e($lotErrors['name']) ?></span><?php endif; ?>
            </div>
            <div class="form-actions form-grid__full">
                <button class="button button--primary" type="submit"><?= e(__('metre.add_lot_submit')) ?></button>
            </div>
        </form>
    </section>
<?php endif; ?>
