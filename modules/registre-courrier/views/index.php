<?php
declare(strict_types=1);

use App\Core\View;
use Modules\RegistreCourrier\Services\CourrierService;

$rows = is_array($courrier ?? null) ? $courrier : [];
$filters = is_array($filters ?? null) ? $filters : ['q' => '', 'sens' => '', 'statut' => '', 'annee' => '', 'projet' => ''];
$years = is_array($years ?? null) ? $years : [];
$statistics = is_array($statistics ?? null) ? $statistics : [];
$projectFilter = is_array($projectFilter ?? null) ? $projectFilter : null;
$page = max(1, (int) ($page ?? 1));
$pageCount = max(1, (int) ($pageCount ?? 1));
$currentYear = (int) date('Y');
$registerYear = $filters['annee'] !== '' ? $filters['annee'] : (string) $currentYear;
$hasFilters = $filters['q'] !== '' || $filters['sens'] !== '' || $filters['statut'] !== ''
    || $filters['annee'] !== '' || $filters['projet'] !== '';
$query = http_build_query(array_filter($filters, static fn ($value) => $value !== ''));
$formatDate = static function (?string $date): string {
    if ($date === null || $date === '') {
        return '—';
    }
    $time = strtotime($date);

    return $time === false ? '—' : date('d/m/Y', $time);
};
?>
<?php View::partial('partials/page-header', [
    'pageTitle' => __('courrier.title'),
    'eyebrow' => __('courrier.eyebrow'),
    'subtitle' => __('courrier.intro'),
    'secondaryAction' => [
        'label' => __('courrier.print'),
        'icon' => 'printer',
        'url' => url('/courrier/imprimer?' . http_build_query(array_filter([
            'annee' => $registerYear,
            'sens' => $filters['sens'],
        ], static fn ($value) => $value !== ''))),
    ],
    'primaryAction' => !empty($canManage) ? ['label' => __('courrier.create'), 'icon' => 'plus', 'url' => url('/courrier/new')] : null,
]) ?>

<?php if (!empty($notice)): ?><p class="form-feedback form-feedback--success" role="status"><?= e($notice) ?></p><?php endif; ?>
<?php if (!empty($error)): ?><p class="form-feedback form-feedback--error" role="alert"><?= e($error) ?></p><?php endif; ?>

<p class="table-meta" role="note"><?= e(__('courrier.echeance_notice')) ?></p>

<section class="stats-grid" aria-label="<?= e(__('courrier.statistics')) ?>">
    <?php View::partial('partials/stat-card', [
        'label' => __('courrier.stat.todo'),
        'value' => (string) ($statistics['todo'] ?? 0),
        'icon' => 'mail',
        'tone' => 'blue',
        'url' => url('/courrier?statut=todo'),
    ]) ?>
    <?php View::partial('partials/stat-card', [
        'label' => __('courrier.stat.in_progress'),
        'value' => (string) ($statistics['in_progress'] ?? 0),
        'icon' => 'timer',
        'tone' => 'turquoise',
        'url' => url('/courrier?statut=in_progress'),
    ]) ?>
    <?php View::partial('partials/stat-card', [
        'label' => __('courrier.stat.done'),
        'value' => (string) ($statistics['done'] ?? 0),
        'icon' => 'check-circle',
        'tone' => 'mint',
        'url' => url('/courrier?statut=done'),
    ]) ?>
    <?php View::partial('partials/stat-card', [
        'label' => __('courrier.stat.overdue'),
        'value' => (string) ($statistics['overdue'] ?? 0),
        'icon' => 'alert',
        'tone' => 'coral',
        'note' => __('courrier.stat.overdue_note'),
        'url' => url('/courrier?statut=todo'),
    ]) ?>
</section>

<section class="card stack-md" aria-label="<?= e(__('courrier.list_title')) ?>">
    <div class="card-header">
        <div>
            <h2 class="card-header__title"><?= e(__('courrier.list_title')) ?></h2>
            <p class="section-heading__note"><?= e(__('courrier.list_help')) ?></p>
        </div>
        <div class="cluster">
            <a class="button button--small button--ghost" href="<?= e(url('/courrier/reglages')) ?>">
                <svg class="icon icon--small" aria-hidden="true"><use href="<?= e(asset('img/icons.svg')) ?>#settings"></use></svg><?= e(__('courrier.numbering')) ?>
            </a>
            <a class="button button--small button--secondary" href="<?= e(url('/courrier/export-csv' . ($query !== '' ? '?' . $query : ''))) ?>">
                <svg class="icon icon--small" aria-hidden="true"><use href="<?= e(asset('img/icons.svg')) ?>#download"></use></svg><?= e(__('courrier.export')) ?>
            </a>
        </div>
    </div>

    <?php if ($projectFilter !== null): ?>
        <p class="cluster cluster--between">
            <span class="badge badge--neutral"><?= e(__('courrier.filtered_project', ['project' => (string) $projectFilter['name']])) ?></span>
            <a class="button button--small button--ghost" href="<?= e(url('/courrier')) ?>"><?= e(__('courrier.clear_filters')) ?></a>
        </p>
    <?php endif; ?>

    <form class="filter-row" method="get" action="<?= e(url('/courrier')) ?>">
        <?php if ($filters['projet'] !== ''): ?>
            <input type="hidden" name="projet" value="<?= e($filters['projet']) ?>">
        <?php endif; ?>
        <div class="input-with-icon">
            <svg class="icon icon--small" aria-hidden="true"><use href="<?= e(asset('img/icons.svg')) ?>#search"></use></svg>
            <input class="field__control" type="search" name="q" value="<?= e($filters['q']) ?>" placeholder="<?= e(__('courrier.search_placeholder')) ?>" aria-label="<?= e(__('common.search')) ?>">
        </div>
        <div class="field">
            <label class="u-sr-only" for="courrier-filter-sens"><?= e(__('courrier.sens_label')) ?></label>
            <select class="field__control" id="courrier-filter-sens" name="sens">
                <option value=""><?= e(__('courrier.all_sens')) ?></option>
                <?php foreach (CourrierService::SENS as $sens): ?>
                    <option value="<?= e($sens) ?>" <?= $filters['sens'] === $sens ? 'selected' : '' ?>><?= e(__('courrier.sens.' . $sens)) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="field">
            <label class="u-sr-only" for="courrier-filter-statut"><?= e(__('courrier.statut_label')) ?></label>
            <select class="field__control" id="courrier-filter-statut" name="statut">
                <option value=""><?= e(__('courrier.all_statuts')) ?></option>
                <?php foreach (CourrierService::STATUTS as $statut): ?>
                    <option value="<?= e($statut) ?>" <?= $filters['statut'] === $statut ? 'selected' : '' ?>><?= e(__('courrier.statut.' . $statut)) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <?php if ($years !== []): ?>
            <div class="field">
                <label class="u-sr-only" for="courrier-filter-annee"><?= e(__('courrier.year')) ?></label>
                <select class="field__control" id="courrier-filter-annee" name="annee">
                    <option value=""><?= e(__('courrier.all_years')) ?></option>
                    <?php foreach ($years as $year): ?>
                        <option value="<?= e((string) $year) ?>" <?= $filters['annee'] === (string) $year ? 'selected' : '' ?>><?= e((string) $year) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        <?php endif; ?>
        <button class="button button--secondary" type="submit">
            <svg class="icon icon--small" aria-hidden="true"><use href="<?= e(asset('img/icons.svg')) ?>#filter"></use></svg><?= e(__('common.filter')) ?>
        </button>
        <?php if ($hasFilters): ?><a class="button button--ghost" href="<?= e(url('/courrier')) ?>"><?= e(__('common.clear')) ?></a><?php endif; ?>
    </form>

    <?php if ($rows === []): ?>
        <?php View::partial('partials/empty-state', [
            'title' => $hasFilters ? __('courrier.no_results') : __('courrier.empty_title'),
            'description' => $hasFilters ? __('courrier.no_results_help') : __('courrier.empty_help'),
            'actionLabel' => !empty($canManage) && !$hasFilters ? __('courrier.create') : null,
            'actionUrl' => !empty($canManage) && !$hasFilters ? url('/courrier/new') : null,
        ]) ?>
    <?php else: ?>
        <div class="table-wrap">
            <table class="data-table">
                <thead>
                    <tr>
                        <th scope="col"><?= e(__('courrier.numero')) ?></th>
                        <th scope="col"><?= e(__('courrier.date')) ?></th>
                        <th scope="col"><?= e(__('courrier.correspondant')) ?></th>
                        <th scope="col"><?= e(__('courrier.objet')) ?></th>
                        <th scope="col"><?= e(__('courrier.statut_label')) ?></th>
                        <th scope="col"><?= e(__('courrier.echeance')) ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($rows as $row): ?>
                        <?php
                        $statut = (string) $row['statut'];
                        $overdue = CourrierService::isOverdue(
                            isset($row['echeance']) ? (string) $row['echeance'] : null,
                            $statut
                        );
                        ?>
                        <tr>
                            <td>
                                <a class="table-primary" href="<?= e(url('/courrier/' . (int) $row['id'])) ?>">
                                    <span class="table-primary__text">
                                        <strong class="table-primary__name"><?= e((string) $row['numero']) ?></strong>
                                        <span class="table-primary__meta"><?= e(__('courrier.sens.' . (string) $row['sens'])) ?></span>
                                    </span>
                                </a>
                            </td>
                            <td><?= e($formatDate((string) $row['date_courrier'])) ?></td>
                            <td><?= e((string) $row['correspondant']) ?></td>
                            <td>
                                <?= e((string) $row['objet']) ?>
                                <?php if (($row['project_name'] ?? '') !== '' || ($row['client_name'] ?? '') !== ''): ?>
                                    <span class="table-meta"><?= e((string) ($row['project_name'] ?? $row['client_name'] ?? '')) ?></span>
                                <?php endif; ?>
                            </td>
                            <td><span class="badge badge--<?= e(CourrierService::badgeVariant($statut)) ?>"><?= e(__('courrier.statut.' . $statut)) ?></span></td>
                            <td>
                                <?php if (($row['echeance'] ?? null) !== null): ?>
                                    <?= e($formatDate((string) $row['echeance'])) ?>
                                    <?php if ($overdue): ?>
                                        <span class="table-meta"><span class="badge badge--error badge--plain"><?= e(__('courrier.overdue')) ?></span></span>
                                    <?php endif; ?>
                                <?php else: ?>
                                    —
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <div class="table-footer">
            <span><?= e(__('common.results', ['count' => (string) ($total ?? 0)])) ?></span>
            <?php if ($pageCount > 1): ?>
                <div class="cluster">
                    <a class="button button--small button--secondary<?= $page <= 1 ? ' is-disabled' : '' ?>" href="<?= e(url('/courrier?' . http_build_query($filters + ['page' => max(1, $page - 1)]))) ?>" <?= $page <= 1 ? 'aria-disabled="true"' : '' ?>><?= e(__('common.previous')) ?></a>
                    <span class="muted"><?= e(__('common.page', ['current' => (string) $page, 'total' => (string) $pageCount])) ?></span>
                    <a class="button button--small button--secondary<?= $page >= $pageCount ? ' is-disabled' : '' ?>" href="<?= e(url('/courrier?' . http_build_query($filters + ['page' => min($pageCount, $page + 1)]))) ?>" <?= $page >= $pageCount ? 'aria-disabled="true"' : '' ?>><?= e(__('common.next')) ?></a>
                </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</section>
