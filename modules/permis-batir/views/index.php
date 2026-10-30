<?php
declare(strict_types=1);

use App\Core\View;
use Modules\PermisBatir\Services\DossierService;

$dossiers = is_array($dossiers ?? null) ? $dossiers : [];
$filters = is_array($filters ?? null) ? $filters : ['q' => '', 'status' => '', 'year' => ''];
$years = is_array($years ?? null) ? $years : [];
$statistics = is_array($statistics ?? null) ? $statistics : [];
$page = max(1, (int) ($page ?? 1));
$pageCount = max(1, (int) ($pageCount ?? 1));
$hasFilters = $filters['q'] !== '' || $filters['status'] !== '' || $filters['year'] !== '';
$query = http_build_query(array_filter($filters, static fn ($value) => $value !== ''));
?>
<?php View::partial('partials/page-header', [
    'pageTitle' => __('permis.title'),
    'eyebrow' => __('permis.eyebrow'),
    'subtitle' => __('permis.intro'),
    'secondaryAction' => ['label' => __('permis.export'), 'icon' => 'download', 'url' => url('/permis/export-csv' . ($query !== '' ? '?' . $query : ''))],
    'primaryAction' => !empty($canManage) ? ['label' => __('permis.create'), 'icon' => 'plus', 'url' => url('/permis/new')] : null,
]) ?>

<?php if (!empty($notice)): ?><p class="form-feedback form-feedback--success" role="status"><?= e($notice) ?></p><?php endif; ?>
<?php if (!empty($error)): ?><p class="form-feedback form-feedback--error" role="alert"><?= e($error) ?></p><?php endif; ?>

<section class="stats-grid" aria-label="<?= e(__('permis.statistics')) ?>">
    <?php View::partial('partials/stat-card', ['label' => __('permis.stat.instruction'), 'value' => (string) ($statistics['instruction'] ?? 0), 'icon' => 'plan', 'tone' => 'blue']) ?>
    <?php View::partial('partials/stat-card', ['label' => __('permis.stat.granted'), 'value' => (string) ($statistics['granted'] ?? 0), 'icon' => 'check-circle', 'tone' => 'mint']) ?>
    <?php View::partial('partials/stat-card', ['label' => __('permis.stat.refused'), 'value' => (string) ($statistics['refused'] ?? 0), 'icon' => 'alert', 'tone' => 'coral']) ?>
    <?php View::partial('partials/stat-card', ['label' => __('permis.stat.overdue'), 'value' => (string) ($statistics['overdue'] ?? 0), 'icon' => 'calendar', 'tone' => 'pink', 'note' => __('permis.stat.overdue_note')]) ?>
</section>

<section class="card stack-md" aria-label="<?= e(__('permis.list_title')) ?>">
    <form class="filter-row" method="get" action="<?= e(url('/permis')) ?>">
        <div class="input-with-icon">
            <svg class="icon icon--small" aria-hidden="true"><use href="<?= e(asset('img/icons.svg')) ?>#search"></use></svg>
            <input class="field__control" type="search" name="q" value="<?= e($filters['q']) ?>" placeholder="<?= e(__('permis.search_placeholder')) ?>" aria-label="<?= e(__('common.search')) ?>">
        </div>
        <div class="field">
            <label class="u-sr-only" for="permis-filter-status"><?= e(__('permis.status.label')) ?></label>
            <select class="field__control" id="permis-filter-status" name="status">
                <option value=""><?= e(__('permis.all_statuses')) ?></option>
                <?php foreach (DossierService::STATUSES as $status): ?>
                    <option value="<?= e($status) ?>" <?= $filters['status'] === $status ? 'selected' : '' ?>><?= e(__('permis.status.' . $status)) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <?php if ($years !== []): ?>
            <div class="field">
                <label class="u-sr-only" for="permis-filter-year"><?= e(__('permis.year')) ?></label>
                <select class="field__control" id="permis-filter-year" name="year">
                    <option value=""><?= e(__('permis.all_years')) ?></option>
                    <?php foreach ($years as $year): ?>
                        <option value="<?= e((string) $year) ?>" <?= $filters['year'] === (string) $year ? 'selected' : '' ?>><?= e((string) $year) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        <?php endif; ?>
        <button class="button button--secondary" type="submit"><svg class="icon icon--small" aria-hidden="true"><use href="<?= e(asset('img/icons.svg')) ?>#filter"></use></svg><?= e(__('common.filter')) ?></button>
        <?php if ($hasFilters): ?><a class="button button--ghost" href="<?= e(url('/permis')) ?>"><?= e(__('common.clear')) ?></a><?php endif; ?>
    </form>

    <?php if ($dossiers === []): ?>
        <?php View::partial('partials/empty-state', [
            'title' => $hasFilters ? __('permis.no_results') : __('permis.empty_title'),
            'description' => $hasFilters ? __('permis.no_results_help') : __('permis.empty_help'),
            'actionLabel' => !empty($canManage) && !$hasFilters ? __('permis.create') : null,
            'actionUrl' => !empty($canManage) && !$hasFilters ? url('/permis/new') : null,
        ]) ?>
    <?php else: ?>
        <div class="table-wrap">
            <table class="data-table">
                <thead>
                    <tr>
                        <th scope="col"><?= e(__('permis.reference')) ?></th>
                        <th scope="col"><?= e(__('permis.project')) ?></th>
                        <th scope="col"><?= e(__('permis.commune')) ?></th>
                        <th scope="col"><?= e(__('permis.deposit_date')) ?></th>
                        <th scope="col"><?= e(__('permis.deadline_date')) ?></th>
                        <th scope="col"><?= e(__('permis.pieces')) ?></th>
                        <th scope="col"><?= e(__('permis.status.label')) ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($dossiers as $dossier): ?>
                        <?php
                        $daysLeft = DossierService::daysLeft(isset($dossier['deadline_date']) ? (string) $dossier['deadline_date'] : null);
                        $isActive = in_array((string) $dossier['status'], ['deposited', 'instruction', 'complement'], true);
                        ?>
                        <tr>
                            <td>
                                <a class="table-primary" href="<?= e(url('/permis/' . (int) $dossier['id'])) ?>">
                                    <span class="table-primary__text">
                                        <strong class="table-primary__name"><?= e((string) $dossier['reference']) ?></strong>
                                        <span class="table-primary__meta"><?= e((string) $dossier['commune']) ?></span>
                                    </span>
                                </a>
                            </td>
                            <td>
                                <?= e((string) $dossier['project_name']) ?>
                                <span class="table-meta"><?= e((string) $dossier['client_name']) ?><?= ($dossier['project_reference'] ?? '') !== '' ? ' · ' . e((string) $dossier['project_reference']) : '' ?></span>
                            </td>
                            <td><?= e((string) $dossier['commune']) ?></td>
                            <td><?= ($dossier['deposit_date'] ?? null) !== null ? e(date('d/m/Y', strtotime((string) $dossier['deposit_date']))) : '—' ?></td>
                            <td>
                                <?php if (($dossier['deadline_date'] ?? null) !== null): ?>
                                    <?= e(date('d/m/Y', strtotime((string) $dossier['deadline_date']))) ?>
                                    <?php if ($isActive && $daysLeft !== null): ?>
                                        <span class="table-meta">
                                            <?php if ($daysLeft < 0): ?>
                                                <span class="badge badge--error badge--plain"><?= e(__('permis.late_by', ['days' => (string) abs($daysLeft)])) ?></span>
                                            <?php elseif ($daysLeft <= 30): ?>
                                                <span class="badge badge--warning badge--plain"><?= e(__('permis.in_days', ['days' => (string) $daysLeft])) ?></span>
                                            <?php endif; ?>
                                        </span>
                                    <?php endif; ?>
                                <?php else: ?>
                                    —
                                <?php endif; ?>
                            </td>
                            <td class="u-numeric"><?= e((string) $dossier['pieces_deposited']) ?> / <?= e((string) $dossier['pieces_total']) ?></td>
                            <td><span class="badge badge--<?= e(DossierService::badgeVariant((string) $dossier['status'])) ?>"><?= e(__('permis.status.' . (string) $dossier['status'])) ?></span></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <div class="table-footer">
            <span><?= e(__('common.results', ['count' => (string) ($total ?? 0)])) ?></span>
            <?php if ($pageCount > 1): ?>
                <div class="cluster">
                    <a class="button button--small button--secondary<?= $page <= 1 ? ' is-disabled' : '' ?>" href="<?= e(url('/permis?' . http_build_query($filters + ['page' => max(1, $page - 1)]))) ?>" <?= $page <= 1 ? 'aria-disabled="true"' : '' ?>><?= e(__('common.previous')) ?></a>
                    <span class="muted"><?= e(__('common.page', ['current' => (string) $page, 'total' => (string) $pageCount])) ?></span>
                    <a class="button button--small button--secondary<?= $page >= $pageCount ? ' is-disabled' : '' ?>" href="<?= e(url('/permis?' . http_build_query($filters + ['page' => min($pageCount, $page + 1)]))) ?>" <?= $page >= $pageCount ? 'aria-disabled="true"' : '' ?>><?= e(__('common.next')) ?></a>
                </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</section>
