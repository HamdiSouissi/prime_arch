<?php
declare(strict_types=1);

use App\Core\Auth;
use App\Core\View;

$projects = is_array($projects ?? null) ? $projects : [];
$filters = is_array($filters ?? null) ? $filters : ['q' => '', 'status' => '', 'client_id' => ''];
$clients = is_array($clients ?? null) ? $clients : [];
$page = max(1, (int) ($page ?? 1));
$pageCount = max(1, (int) ($pageCount ?? 1));
// Suppression : la sélection multiple n'apparaît qu'avec le droit `projects.delete`.
$canDelete = !empty($canDelete);
$notice = is_string($notice ?? null) ? $notice : '';
$errors = is_array($errors ?? null) ? $errors : [];
$selectionLabel = __('common.selection_count');
$bulkConfirm = __('projects.delete_selection_confirm', ['count' => ':count']);
?>
<?php View::partial('partials/page-header', [
    'pageTitle' => __('projects.title'),
    'eyebrow' => __('projects.portfolio'),
    'subtitle' => __('projects.intro'),
    'secondaryAction' => !empty($canConfigure) ? ['label' => __('project_phases.manage'), 'icon' => 'settings', 'url' => url('/settings/project-phases')] : null,
    'primaryAction' => !empty($canManage) ? ['label' => __('projects.create'), 'icon' => 'plus', 'url' => url('/projects/new')] : null,
]) ?>

<?php if ($notice !== ''): ?>
    <p class="form-feedback form-feedback--success" role="status"><?= e($notice) ?></p>
<?php endif; ?>
<?php foreach ($errors as $error): ?>
    <?php if (is_string($error)): ?><p class="form-feedback form-feedback--error" role="alert"><?= e($error) ?></p><?php endif; ?>
<?php endforeach; ?>

<section class="card stack-md" aria-label="<?= e(__('projects.title')) ?>">
    <form class="filter-row" method="get" action="<?= e(url('/projects')) ?>">
        <div class="input-with-icon"><svg class="icon icon--small" aria-hidden="true"><use href="<?= e(asset('img/icons.svg')) ?>#search"></use></svg><input class="field__control" type="search" name="q" value="<?= e($filters['q']) ?>" placeholder="<?= e(__('projects.search_placeholder')) ?>" aria-label="<?= e(__('common.search')) ?>"></div>
        <div class="field"><label class="u-sr-only" for="project-filter-status"><?= e(__('projects.status.label')) ?></label><select class="field__control" id="project-filter-status" name="status"><option value=""><?= e(__('projects.all_statuses')) ?></option><?php foreach (['planning', 'active', 'on_hold', 'completed', 'cancelled'] as $status): ?><option value="<?= e($status) ?>" <?= $filters['status'] === $status ? 'selected' : '' ?>><?= e(__('projects.status.' . $status)) ?></option><?php endforeach; ?></select></div>
        <?php if ($clients !== []): ?><div class="field"><label class="u-sr-only" for="project-filter-client"><?= e(__('clients.client')) ?></label><select class="field__control" id="project-filter-client" name="client_id"><option value=""><?= e(__('projects.all_clients')) ?></option><?php foreach ($clients as $client): ?><option value="<?= e((string) $client['id']) ?>" <?= $filters['client_id'] === (string) $client['id'] ? 'selected' : '' ?>><?= e($client['display_name']) ?><?= $client['reference'] !== '' ? ' · ' . e($client['reference']) : '' ?></option><?php endforeach; ?></select></div><?php endif; ?>
        <button class="button button--secondary" type="submit"><svg class="icon icon--small" aria-hidden="true"><use href="<?= e(asset('img/icons.svg')) ?>#filter"></use></svg><?= e(__('common.filter')) ?></button>
        <?php if ($filters['q'] !== '' || $filters['status'] !== '' || $filters['client_id'] !== ''): ?><a class="button button--ghost" href="<?= e(url('/projects')) ?>"><?= e(__('common.clear')) ?></a><?php endif; ?>
    </form>

    <?php View::partial('partials/csv-transfer', [
        'entity' => 'projects',
        'filters' => $filters,
        'importResult' => $importResult ?? null,
        'importFileError' => $importFileError ?? '',
        'canManage' => !empty($canManage),
    ]) ?>

    <?php if ($projects === []): ?>
        <?php View::partial('partials/empty-state', [
            'title' => $filters['q'] !== '' || $filters['status'] !== '' || $filters['client_id'] !== '' ? __('projects.no_results') : __('projects.no_projects'),
            'description' => $filters['q'] !== '' || $filters['status'] !== '' || $filters['client_id'] !== '' ? __('projects.no_results_help') : __('projects.no_projects_help'),
            'actionLabel' => !empty($canManage) && $filters['q'] === '' && $filters['status'] === '' && $filters['client_id'] === '' ? __('projects.create') : null,
            'actionUrl' => !empty($canManage) && $filters['q'] === '' && $filters['status'] === '' && $filters['client_id'] === '' ? url('/projects/new') : null,
        ]) ?>
    <?php else: ?>
        <?php if ($canDelete): ?>
            <form class="bulk-form" method="post" action="<?= e(url('/projects/delete')) ?>" data-confirm="<?= e($bulkConfirm) ?>" data-confirm-selection>
                <?= csrf_field() ?>
            <div class="bulk-actions">
                <span class="bulk-actions__count" data-selection-count data-label="<?= e($selectionLabel) ?>"><?= e(str_replace(':count', '0', $selectionLabel)) ?></span>
                <button class="button button--small button--danger" type="submit" data-selection-submit disabled><?= e(__('common.delete_selection')) ?></button>
            </div>
        <?php endif; ?>
        <div class="table-wrap"><table class="data-table">
            <thead><tr><?php if ($canDelete): ?><th scope="col" class="bulk-select"><input type="checkbox" data-select-all aria-label="<?= e(__('common.select_all')) ?>"></th><?php endif; ?><th scope="col"><?= e(__('projects.name')) ?></th><th scope="col"><?= e(__('clients.client')) ?></th><th scope="col"><?= e(__('projects.location')) ?></th><th scope="col"><?= e(__('project_phases.current')) ?></th><th scope="col"><?= e(__('projects.weighted_progress')) ?></th><?php if (!empty($canViewTime) || !empty($canViewCosts)): ?><th scope="col"><?= e(__('projects.actual_hours')) ?></th><?php endif; ?><?php if (!empty($canViewCosts)): ?><th scope="col"><?= e(__('projects.actual_cost')) ?></th><?php endif; ?><th scope="col"><?= e(__('projects.dates')) ?></th><th scope="col"><?= e(__('projects.status.label')) ?></th></tr></thead>
            <tbody>
                <?php foreach ($projects as $project): ?>
                    <tr>
                        <?php if ($canDelete): ?><td class="bulk-select"><input type="checkbox" name="ids[]" value="<?= (int) $project['id'] ?>" aria-label="<?= e(__('projects.select_record', ['name' => $project['name']])) ?>"></td><?php endif; ?>
                        <td><a class="table-primary" href="<?= e(url('/projects/' . (int) $project['id'])) ?>"><span class="table-primary__text"><strong class="table-primary__name"><?= e($project['name']) ?></strong><span class="table-primary__meta"><?= e($project['reference'] ?? '') ?><?= !empty($project['project_type']) ? ' · ' . e($project['project_type']) : '' ?></span></span></a></td>
                        <td><?php if (Auth::can('clients.view')): ?><a href="<?= e(url('/clients/' . (int) $project['client_id'])) ?>"><?= e($project['client_name']) ?></a><?php else: ?><?= e($project['client_name']) ?><?php endif; ?></td>
                        <td><?= e(implode(', ', array_filter([(string) ($project['city'] ?? ''), (string) ($project['postal_code'] ?? '')]))) ?: '—' ?></td>
                        <td><?= e(!empty($project['current_phase']) ? $project['current_phase'] : '—') ?></td>
                        <td><?= e(number_format((float) ($project['weighted_progress'] ?? 0), 1, ',', ' ')) ?> %</td>
                        <?php if (!empty($canViewTime) || !empty($canViewCosts)): ?><td><?= e((string) ($project['actual_hours'] ?? '0.00')) ?> h<?php if (!empty($project['budget_hours'])): ?> / <?= e((string) $project['budget_hours']) ?> h<?php endif; ?></td><?php endif; ?>
                        <?php if (!empty($canViewCosts)): ?><td><?= e(money_format_tnd((string) ($project['actual_cost'] ?? '0.000'))) ?><?php if (!empty($project['fee_budget_amount'])): ?><span class="table-primary__meta"><?= e(money_format_tnd((string) $project['fee_budget_amount'])) ?> <?= e(__('projects.fee_budget')) ?></span><?php endif; ?></td><?php endif; ?>
                        <td><?php if ($project['start_date'] !== null && $project['start_date'] !== ''): ?><?= e(date('d/m/Y', strtotime((string) $project['start_date']))) ?><?php else: ?><?= e(__('projects.not_scheduled')) ?><?php endif; ?></td>
                        <td><span class="badge badge--<?= e(match ($project['status']) { 'active' => 'info', 'completed' => 'success', 'on_hold' => 'warning', 'cancelled' => 'neutral', default => 'neutral' }) ?>"><?= e(__('projects.status.' . $project['status'])) ?></span></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table></div>
        <div class="table-footer"><span><?= e(__('common.results', ['count' => (string) ($total ?? 0)])) ?></span><?php if ($pageCount > 1): ?><div class="cluster"><a class="button button--small button--secondary<?= $page <= 1 ? ' is-disabled' : '' ?>" href="<?= e(url('/projects?' . http_build_query($filters + ['page' => max(1, $page - 1)]))) ?>" <?= $page <= 1 ? 'aria-disabled="true"' : '' ?>><?= e(__('common.previous')) ?></a><span class="muted"><?= e(__('common.page', ['current' => (string) $page, 'total' => (string) $pageCount])) ?></span><a class="button button--small button--secondary<?= $page >= $pageCount ? ' is-disabled' : '' ?>" href="<?= e(url('/projects?' . http_build_query($filters + ['page' => min($pageCount, $page + 1)]))) ?>" <?= $page >= $pageCount ? 'aria-disabled="true"' : '' ?>><?= e(__('common.next')) ?></a></div><?php endif; ?></div>
        <?php if ($canDelete): ?>
            </form>
        <?php endif; ?>
    <?php endif; ?>
</section>
