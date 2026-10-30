<?php
declare(strict_types=1);

use App\Core\View;

$projects = is_array($projects ?? null) ? $projects : [];
$statistics = is_array($statistics ?? null) ? $statistics : [];
$filters = is_array($filters ?? null) ? $filters : ['q' => ''];
?>
<?php View::partial('partials/page-header', [
    'pageTitle' => __('metre.title'),
    'eyebrow' => __('metre.eyebrow'),
    'subtitle' => __('metre.intro'),
    'secondaryAction' => ['label' => __('metre.library_link'), 'icon' => 'layers', 'url' => url('/metre/bibliotheque')],
]) ?>

<?php if (!empty($notice)): ?><p class="form-feedback form-feedback--success" role="status"><?= e($notice) ?></p><?php endif; ?>
<?php if (!empty($error)): ?><p class="form-feedback form-feedback--error" role="alert"><?= e($error) ?></p><?php endif; ?>

<section class="stats-grid" aria-label="<?= e(__('metre.statistics')) ?>">
    <?php View::partial('partials/stat-card', ['label' => __('metre.stat.projects'), 'value' => (string) ($statistics['projects'] ?? 0), 'icon' => 'briefcase', 'tone' => 'blue']) ?>
    <?php View::partial('partials/stat-card', ['label' => __('metre.stat.postes'), 'value' => (string) ($statistics['postes'] ?? 0), 'icon' => 'list', 'tone' => 'mint']) ?>
    <?php View::partial('partials/stat-card', ['label' => __('metre.stat.total'), 'value' => money_format_tnd((string) ($statistics['total_amount'] ?? '0.000')), 'icon' => 'wallet', 'tone' => 'pink']) ?>
</section>

<section class="card stack-md" aria-labelledby="metre-projects-title">
    <div class="card-header">
        <div>
            <h2 class="card-header__title" id="metre-projects-title"><?= e(__('metre.projects_title')) ?></h2>
            <p class="section-heading__note"><?= e(__('metre.projects_help')) ?></p>
        </div>
    </div>

    <form class="filter-row" method="get" action="<?= e(url('/metre')) ?>">
        <div class="input-with-icon">
            <svg class="icon icon--small" aria-hidden="true"><use href="<?= e(asset('img/icons.svg')) ?>#search"></use></svg>
            <input class="field__control" type="search" name="q" value="<?= e($filters['q']) ?>"
                   placeholder="<?= e(__('metre.search_placeholder')) ?>" aria-label="<?= e(__('common.search')) ?>">
        </div>
        <button class="button button--secondary" type="submit">
            <svg class="icon icon--small" aria-hidden="true"><use href="<?= e(asset('img/icons.svg')) ?>#filter"></use></svg><?= e(__('common.filter')) ?>
        </button>
        <?php if ($filters['q'] !== ''): ?>
            <a class="button button--ghost" href="<?= e(url('/metre')) ?>"><?= e(__('common.clear')) ?></a>
        <?php endif; ?>
    </form>

    <?php if ($projects === []): ?>
        <?php View::partial('partials/empty-state', [
            'title' => __('metre.empty_title'),
            'description' => $filters['q'] !== '' ? __('metre.no_results_help') : __('metre.empty_help'),
            'actionLabel' => $filters['q'] !== '' ? null : __('metre.open_projects'),
            'actionUrl' => url('/projects'),
        ]) ?>
    <?php else: ?>
        <div class="table-wrap">
            <table class="data-table">
                <thead>
                    <tr>
                        <th scope="col"><?= e(__('metre.project')) ?></th>
                        <th scope="col"><?= e(__('metre.client')) ?></th>
                        <th scope="col"><?= e(__('metre.lots')) ?></th>
                        <th scope="col"><?= e(__('metre.postes')) ?></th>
                        <th scope="col"><?= e(__('metre.total_ht')) ?></th>
                        <th scope="col"><?= e(__('common.actions')) ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($projects as $project): ?>
                        <?php $hasPostes = (int) $project['postes_count'] > 0; ?>
                        <tr>
                            <td>
                                <a class="table-primary" href="<?= e(url('/metre/' . (int) $project['id'])) ?>">
                                    <span class="table-primary__text">
                                        <strong class="table-primary__name"><?= e((string) $project['name']) ?></strong>
                                        <span class="table-primary__meta">
                                            <?= e((string) ($project['reference'] ?? '')) ?><?= ($project['city'] ?? '') !== '' ? ' · ' . e((string) $project['city']) : '' ?>
                                        </span>
                                    </span>
                                </a>
                            </td>
                            <td><?= e((string) $project['client_name']) ?></td>
                            <td class="u-numeric"><?= e((string) $project['lots_count']) ?></td>
                            <td class="u-numeric"><?= e((string) $project['postes_count']) ?></td>
                            <td class="u-numeric">
                                <?php if ($hasPostes): ?>
                                    <strong><?= e(money_format_tnd((string) $project['total_amount'])) ?></strong>
                                <?php else: ?>
                                    <span class="muted"><?= e(__('metre.not_metred')) ?></span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="cluster">
                                    <a class="button button--small button--secondary" href="<?= e(url('/metre/' . (int) $project['id'])) ?>">
                                        <?= e($hasPostes ? __('metre.open_metre') : __('metre.start_metre')) ?>
                                    </a>
                                    <?php if ($hasPostes): ?>
                                        <a class="button button--small button--ghost" href="<?= e(url('/metre/' . (int) $project['id'] . '/dqe')) ?>">
                                            <?= e(__('metre.open_dqe')) ?>
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <p class="table-meta"><?= e(__('metre.projects_note')) ?></p>
    <?php endif; ?>
</section>
