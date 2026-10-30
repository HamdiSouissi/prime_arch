<?php
declare(strict_types=1);

use App\Core\View;

$projects = is_array($projects ?? null) ? $projects : [];
$filters = is_array($filters ?? null) ? $filters : ['q' => '', 'only_open' => false];
$statistics = is_array($statistics ?? null) ? $statistics : [];
?>
<?php View::partial('partials/page-header', [
    'pageTitle' => __('chantier.title'),
    'eyebrow' => __('chantier.eyebrow'),
    'subtitle' => __('chantier.intro'),
]) ?>

<?php if (!empty($notice)): ?><p class="form-feedback form-feedback--success" role="status"><?= e($notice) ?></p><?php endif; ?>
<?php if (!empty($error)): ?><p class="form-feedback form-feedback--error" role="alert"><?= e($error) ?></p><?php endif; ?>

<section class="stats-grid" aria-label="<?= e(__('chantier.statistics')) ?>">
    <?php View::partial('partials/stat-card', ['label' => __('chantier.stat.projects'), 'value' => (string) ($statistics['projects'] ?? 0), 'icon' => 'plan', 'tone' => 'blue']) ?>
    <?php View::partial('partials/stat-card', ['label' => __('chantier.stat.visits'), 'value' => (string) ($statistics['visits'] ?? 0), 'icon' => 'calendar', 'tone' => 'mint']) ?>
    <?php View::partial('partials/stat-card', ['label' => __('chantier.stat.open_reserves'), 'value' => (string) ($statistics['open_reserves'] ?? 0), 'icon' => 'alert', 'tone' => 'coral']) ?>
    <?php View::partial('partials/stat-card', ['label' => __('chantier.stat.receptions'), 'value' => (string) ($statistics['receptions'] ?? 0), 'icon' => 'check-circle', 'tone' => 'pink']) ?>
</section>

<section class="card stack-md" aria-label="<?= e(__('chantier.projects_title')) ?>">
    <form class="filter-row" method="get" action="<?= e(url('/chantier')) ?>">
        <div class="input-with-icon">
            <svg class="icon icon--small" aria-hidden="true"><use href="<?= e(asset('img/icons.svg')) ?>#search"></use></svg>
            <input class="field__control" type="search" name="q" value="<?= e($filters['q']) ?>" placeholder="<?= e(__('chantier.search_placeholder')) ?>" aria-label="<?= e(__('common.search')) ?>">
        </div>
        <label class="check-label">
            <input type="checkbox" name="open" value="1" <?= $filters['only_open'] ? 'checked' : '' ?>>
            <?= e(__('chantier.only_open')) ?>
        </label>
        <button class="button button--secondary" type="submit"><svg class="icon icon--small" aria-hidden="true"><use href="<?= e(asset('img/icons.svg')) ?>#filter"></use></svg><?= e(__('common.filter')) ?></button>
        <?php if ($filters['q'] !== '' || $filters['only_open']): ?><a class="button button--ghost" href="<?= e(url('/chantier')) ?>"><?= e(__('common.clear')) ?></a><?php endif; ?>
    </form>

    <?php if ($projects === []): ?>
        <?php View::partial('partials/empty-state', [
            'title' => __('chantier.empty_title'),
            'description' => $filters['q'] !== '' || $filters['only_open'] ? __('chantier.no_results_help') : __('chantier.empty_help'),
            'actionLabel' => $filters['q'] !== '' || $filters['only_open'] ? null : __('chantier.browse_projects'),
            'actionUrl' => url('/projects'),
        ]) ?>
    <?php else: ?>
        <div class="table-wrap">
            <table class="data-table">
                <thead>
                    <tr>
                        <th scope="col"><?= e(__('chantier.project')) ?></th>
                        <th scope="col"><?= e(__('chantier.client')) ?></th>
                        <th scope="col"><?= e(__('chantier.last_visit')) ?></th>
                        <th scope="col"><?= e(__('chantier.visits')) ?></th>
                        <th scope="col"><?= e(__('chantier.open_reserves')) ?></th>
                        <th scope="col"><?= e(__('chantier.receptions')) ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($projects as $project): ?>
                        <tr>
                            <td>
                                <a class="table-primary" href="<?= e(url('/chantier/' . (int) $project['id'])) ?>">
                                    <span class="table-primary__text">
                                        <strong class="table-primary__name"><?= e((string) $project['name']) ?></strong>
                                        <span class="table-primary__meta"><?= e((string) ($project['reference'] ?? '')) ?><?= ($project['city'] ?? '') !== '' ? ' · ' . e((string) $project['city']) : '' ?></span>
                                    </span>
                                </a>
                            </td>
                            <td><?= e((string) $project['client_name']) ?></td>
                            <td><?= ($project['last_visit'] ?? null) !== null ? e(date('d/m/Y', strtotime((string) $project['last_visit']))) : '—' ?></td>
                            <td class="u-numeric"><?= e((string) $project['visits_count']) ?></td>
                            <td>
                                <?php if ((int) $project['open_reserves'] > 0): ?>
                                    <span class="badge badge--warning"><?= e((string) $project['open_reserves']) ?></span>
                                <?php else: ?>
                                    <span class="badge badge--success"><?= e(__('chantier.none')) ?></span>
                                <?php endif; ?>
                            </td>
                            <td class="u-numeric"><?= e((string) $project['receptions_count']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>
