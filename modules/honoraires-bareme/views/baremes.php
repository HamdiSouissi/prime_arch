<?php
declare(strict_types=1);

use App\Core\View;

$missions = is_array($missions ?? null) ? $missions : [];
$filters = is_array($filters ?? null) ? $filters : ['q' => '', 'active_only' => true];
$phases = is_array($phases ?? null) ? $phases : [];
$missionErrors = is_array($missionErrors ?? null) ? $missionErrors : [];
$missionValues = is_array($missionValues ?? null) ? $missionValues : [];
?>
<?php View::partial('partials/page-header', [
    'pageTitle' => __('honoraire.baremes_title'),
    'eyebrow' => __('honoraire.eyebrow'),
    'subtitle' => __('honoraire.baremes_intro'),
    'secondaryAction' => ['label' => __('honoraire.back'), 'icon' => 'arrow-left', 'url' => url('/honoraires')],
]) ?>

<?php if (!empty($notice)): ?><p class="form-feedback form-feedback--success" role="status"><?= e($notice) ?></p><?php endif; ?>
<?php if (!empty($error)): ?><p class="form-feedback form-feedback--error" role="alert"><?= e($error) ?></p><?php endif; ?>

<p class="text-callout">
    <svg class="icon icon--small" aria-hidden="true"><use href="<?= e(asset('img/icons.svg')) ?>#info"></use></svg>
    <?= e(__('honoraire.oat_warning')) ?>
</p>

<section class="card stack-md" id="baremes" aria-labelledby="honoraire-baremes-title">
    <div class="card-header">
        <div>
            <h2 class="card-header__title" id="honoraire-baremes-title"><?= e(__('honoraire.baremes_list_title')) ?></h2>
            <p class="section-heading__note"><?= e(__('honoraire.baremes_list_help')) ?></p>
        </div>
    </div>

    <form class="filter-row" method="get" action="<?= e(url('/honoraires/baremes')) ?>">
        <div class="input-with-icon">
            <svg class="icon icon--small" aria-hidden="true"><use href="<?= e(asset('img/icons.svg')) ?>#search"></use></svg>
            <input class="field__control" type="search" name="q" value="<?= e($filters['q']) ?>"
                   placeholder="<?= e(__('honoraire.baremes_search_placeholder')) ?>" aria-label="<?= e(__('common.search')) ?>">
        </div>
        <label class="check-label">
            <input type="checkbox" name="all" value="1" <?= !$filters['active_only'] ? 'checked' : '' ?>>
            <?= e(__('honoraire.show_inactive')) ?>
        </label>
        <button class="button button--secondary" type="submit">
            <svg class="icon icon--small" aria-hidden="true"><use href="<?= e(asset('img/icons.svg')) ?>#filter"></use></svg><?= e(__('common.filter')) ?>
        </button>
        <?php if ($filters['q'] !== '' || !$filters['active_only']): ?>
            <a class="button button--ghost" href="<?= e(url('/honoraires/baremes')) ?>"><?= e(__('common.clear')) ?></a>
        <?php endif; ?>
    </form>

    <?php if ($missions === []): ?>
        <?php View::partial('partials/empty-state', [
            'title' => __('honoraire.baremes_empty_title'),
            'description' => $filters['q'] !== '' ? __('honoraire.no_results_help') : __('honoraire.baremes_empty_help'),
        ]) ?>
    <?php else: ?>
        <div class="table-wrap">
            <table class="data-table">
                <thead>
                    <tr>
                        <th scope="col"><?= e(__('honoraire.mission')) ?></th>
                        <th scope="col"><?= e(__('honoraire.tranches')) ?></th>
                        <th scope="col"><?= e(__('honoraire.used_by')) ?></th>
                        <th scope="col"><?= e(__('honoraire.state')) ?></th>
                        <th scope="col"><?= e(__('common.actions')) ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($missions as $mission): ?>
                        <tr>
                            <td>
                                <a class="table-primary" href="<?= e(url('/honoraires/baremes/' . (int) $mission['id'])) ?>">
                                    <span class="table-primary__text">
                                        <strong class="table-primary__name"><?= e((string) $mission['name']) ?></strong>
                                        <?php if (trim((string) ($mission['description'] ?? '')) !== ''): ?>
                                            <span class="table-primary__meta"><?= e((string) $mission['description']) ?></span>
                                        <?php endif; ?>
                                    </span>
                                </a>
                            </td>
                            <td class="u-numeric"><?= e((string) $mission['tranches_count']) ?></td>
                            <td class="u-numeric"><?= e((string) $mission['decomptes_count']) ?></td>
                            <td>
                                <?php if ((int) $mission['is_active'] === 1): ?>
                                    <span class="badge badge--success"><?= e(__('honoraire.active')) ?></span>
                                <?php else: ?>
                                    <span class="badge badge--neutral"><?= e(__('honoraire.inactive')) ?></span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <a class="button button--small button--secondary" href="<?= e(url('/honoraires/baremes/' . (int) $mission['id'])) ?>">
                                    <?= e(__('honoraire.open_bareme')) ?>
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>

<?php if (!empty($canManage)): ?>
    <section class="card stack-md" id="new-bareme" aria-labelledby="honoraire-new-bareme-title">
        <div class="card-header">
            <div>
                <h2 class="card-header__title" id="honoraire-new-bareme-title"><?= e(__('honoraire.add_bareme')) ?></h2>
                <p class="section-heading__note"><?= e(__('honoraire.add_bareme_help')) ?></p>
            </div>
        </div>
        <?php if ($missionErrors !== []): ?>
            <p class="form-feedback form-feedback--error" role="alert"><?= e(__('honoraire.fix_errors')) ?></p>
        <?php endif; ?>
        <form class="form-grid" method="post" action="<?= e(url('/honoraires/baremes')) ?>">
            <?= csrf_field() ?>
            <div class="field form-grid__full">
                <label class="field__label" for="mission-name"><?= e(__('honoraire.mission_name')) ?></label>
                <input class="field__control" type="text" id="mission-name" name="name" maxlength="150"
                       value="<?= e((string) ($missionValues['name'] ?? '')) ?>" required>
                <?php if (!empty($missionErrors['name'])): ?><span class="field__hint field__hint--error"><?= e($missionErrors['name']) ?></span><?php endif; ?>
            </div>
            <div class="field form-grid__full">
                <label class="field__label" for="mission-description"><?= e(__('honoraire.description')) ?></label>
                <input class="field__control" type="text" id="mission-description" name="description" maxlength="2000"
                       value="<?= e((string) ($missionValues['description'] ?? '')) ?>">
                <?php if (!empty($missionErrors['description'])): ?><span class="field__hint field__hint--error"><?= e($missionErrors['description']) ?></span><?php endif; ?>
            </div>
            <div class="field">
                <label class="field__label" for="mission-order"><?= e(__('honoraire.sort_order')) ?></label>
                <input class="field__control" type="number" min="0" max="999" id="mission-order" name="sort_order"
                       value="<?= e((string) ($missionValues['sort_order'] ?? 0)) ?>">
            </div>
            <div class="field">
                <input type="hidden" name="is_active" value="0">
                <label class="check-label">
                    <input type="checkbox" name="is_active" value="1" <?= (int) ($missionValues['is_active'] ?? 1) === 1 ? 'checked' : '' ?>>
                    <?= e(__('honoraire.active')) ?>
                </label>
            </div>
            <div class="form-actions form-grid__full">
                <button class="button button--primary" type="submit"><?= e(__('honoraire.add_bareme_submit')) ?></button>
            </div>
        </form>
        <?php if ($phases === []): ?>
            <p class="table-meta"><?= e(__('honoraire.phases_empty')) ?></p>
        <?php endif; ?>
    </section>
<?php endif; ?>
