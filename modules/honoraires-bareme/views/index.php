<?php
declare(strict_types=1);

use App\Core\View;

$projects = is_array($projects ?? null) ? $projects : [];
$statistics = is_array($statistics ?? null) ? $statistics : [];
$filters = is_array($filters ?? null) ? $filters : ['q' => ''];
?>
<?php View::partial('partials/page-header', [
    'pageTitle' => __('honoraire.title'),
    'eyebrow' => __('honoraire.eyebrow'),
    'subtitle' => __('honoraire.intro'),
    'secondaryAction' => ['label' => __('honoraire.baremes_link'), 'icon' => 'currency', 'url' => url('/honoraires/baremes')],
]) ?>

<?php if (!empty($notice)): ?><p class="form-feedback form-feedback--success" role="status"><?= e($notice) ?></p><?php endif; ?>
<?php if (!empty($error)): ?><p class="form-feedback form-feedback--error" role="alert"><?= e($error) ?></p><?php endif; ?>

<p class="text-callout">
    <svg class="icon icon--small" aria-hidden="true"><use href="<?= e(asset('img/icons.svg')) ?>#info"></use></svg>
    <?= e(__('honoraire.oat_warning')) ?>
</p>

<section class="stats-grid" aria-label="<?= e(__('honoraire.statistics')) ?>">
    <?php View::partial('partials/stat-card', ['label' => __('honoraire.stat.missions'), 'value' => (string) ($statistics['missions'] ?? 0), 'icon' => 'currency', 'tone' => 'blue']) ?>
    <?php View::partial('partials/stat-card', ['label' => __('honoraire.stat.decomptes'), 'value' => (string) ($statistics['decomptes'] ?? 0), 'icon' => 'file-text', 'tone' => 'mint']) ?>
    <?php View::partial('partials/stat-card', ['label' => __('honoraire.stat.total'), 'value' => money_format_tnd((string) ($statistics['fee_total'] ?? '0.000')), 'icon' => 'wallet', 'tone' => 'pink']) ?>
    <?php View::partial('partials/stat-card', [
        'label' => __('honoraire.stat.documents'),
        'value' => (string) ($statistics['documents'] ?? 0),
        'icon' => 'quote',
        'tone' => 'coral',
        'note' => __('honoraire.stat.documents_note'),
    ]) ?>
</section>

<section class="card stack-md" aria-labelledby="honoraire-projects-title">
    <div class="card-header">
        <div>
            <h2 class="card-header__title" id="honoraire-projects-title"><?= e(__('honoraire.projects_title')) ?></h2>
            <p class="section-heading__note"><?= e(__('honoraire.projects_help')) ?></p>
        </div>
    </div>

    <form class="filter-row" method="get" action="<?= e(url('/honoraires')) ?>">
        <div class="input-with-icon">
            <svg class="icon icon--small" aria-hidden="true"><use href="<?= e(asset('img/icons.svg')) ?>#search"></use></svg>
            <input class="field__control" type="search" name="q" value="<?= e($filters['q']) ?>"
                   placeholder="<?= e(__('honoraire.search_placeholder')) ?>" aria-label="<?= e(__('common.search')) ?>">
        </div>
        <button class="button button--secondary" type="submit">
            <svg class="icon icon--small" aria-hidden="true"><use href="<?= e(asset('img/icons.svg')) ?>#filter"></use></svg><?= e(__('common.filter')) ?>
        </button>
        <?php if ($filters['q'] !== ''): ?>
            <a class="button button--ghost" href="<?= e(url('/honoraires')) ?>"><?= e(__('common.clear')) ?></a>
        <?php endif; ?>
    </form>

    <?php if ($projects === []): ?>
        <?php View::partial('partials/empty-state', [
            'title' => __('honoraire.empty_title'),
            'description' => $filters['q'] !== '' ? __('honoraire.no_results_help') : __('honoraire.empty_help'),
            'actionLabel' => $filters['q'] !== '' ? null : __('honoraire.open_projects'),
            'actionUrl' => url('/projects'),
        ]) ?>
    <?php else: ?>
        <div class="table-wrap">
            <table class="data-table">
                <thead>
                    <tr>
                        <th scope="col"><?= e(__('honoraire.project')) ?></th>
                        <th scope="col"><?= e(__('honoraire.client')) ?></th>
                        <th scope="col"><?= e(__('honoraire.decomptes')) ?></th>
                        <th scope="col"><?= e(__('honoraire.fee_total')) ?></th>
                        <th scope="col"><?= e(__('honoraire.last_decompte')) ?></th>
                        <th scope="col"><?= e(__('common.actions')) ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($projects as $project): ?>
                        <?php $hasDecomptes = (int) $project['decomptes_count'] > 0; ?>
                        <tr>
                            <td>
                                <a class="table-primary" href="<?= e(url('/honoraires/' . (int) $project['id'])) ?>">
                                    <span class="table-primary__text">
                                        <strong class="table-primary__name"><?= e((string) $project['name']) ?></strong>
                                        <span class="table-primary__meta">
                                            <?= e((string) ($project['reference'] ?? '')) ?><?= ($project['city'] ?? '') !== '' ? ' · ' . e((string) $project['city']) : '' ?>
                                        </span>
                                    </span>
                                </a>
                            </td>
                            <td><?= e((string) $project['client_name']) ?></td>
                            <td class="u-numeric"><?= e((string) $project['decomptes_count']) ?></td>
                            <td class="u-numeric">
                                <?php if ($hasDecomptes): ?>
                                    <strong><?= e(money_format_tnd((string) $project['fee_total'])) ?></strong>
                                <?php else: ?>
                                    <span class="muted"><?= e(__('honoraire.not_computed')) ?></span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if (($project['last_decompte'] ?? null) !== null): ?>
                                    <?= e(date('d/m/Y', (int) strtotime((string) $project['last_decompte']))) ?>
                                <?php else: ?>
                                    <span class="muted">—</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <a class="button button--small button--secondary" href="<?= e(url('/honoraires/' . (int) $project['id'])) ?>">
                                    <?= e($hasDecomptes ? __('honoraire.open_project') : __('honoraire.start_project')) ?>
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <p class="table-meta"><?= e(__('honoraire.projects_note')) ?></p>
    <?php endif; ?>
</section>
