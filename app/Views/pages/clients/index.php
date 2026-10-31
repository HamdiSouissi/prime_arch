<?php
declare(strict_types=1);

use App\Core\View;

$clients = is_array($clients ?? null) ? $clients : [];
$filters = is_array($filters ?? null) ? $filters : ['q' => '', 'status' => '', 'type' => ''];
$page = max(1, (int) ($page ?? 1));
$pageCount = max(1, (int) ($pageCount ?? 1));
// Suppression : la sélection multiple n'apparaît qu'avec le droit `clients.delete`.
$canDelete = !empty($canDelete);
$notice = is_string($notice ?? null) ? $notice : '';
$errors = is_array($errors ?? null) ? $errors : [];
$selectionLabel = __('common.selection_count');
$bulkConfirm = __('clients.delete_selection_confirm', ['count' => ':count']);
?>
<?php View::partial('partials/page-header', [
    'pageTitle' => __('clients.title'),
    'eyebrow' => __('clients.directory'),
    'subtitle' => __('clients.intro'),
    'primaryAction' => !empty($canManage) ? ['label' => __('clients.create'), 'icon' => 'plus', 'url' => url('/clients/new')] : null,
]) ?>

<?php if ($notice !== ''): ?>
    <p class="form-feedback form-feedback--success" role="status"><?= e($notice) ?></p>
<?php endif; ?>
<?php foreach ($errors as $error): ?>
    <?php if (is_string($error)): ?><p class="form-feedback form-feedback--error" role="alert"><?= e($error) ?></p><?php endif; ?>
<?php endforeach; ?>

<section class="card stack-md" aria-label="<?= e(__('clients.title')) ?>">
    <form class="filter-row" method="get" action="<?= e(url('/clients')) ?>">
        <div class="input-with-icon">
            <svg class="icon icon--small" aria-hidden="true"><use href="<?= e(asset('img/icons.svg')) ?>#search"></use></svg>
            <input class="field__control" type="search" name="q" value="<?= e($filters['q']) ?>" placeholder="<?= e(__('clients.search_placeholder')) ?>" aria-label="<?= e(__('common.search')) ?>">
        </div>
        <div class="field"><label class="u-sr-only" for="client-filter-type"><?= e(__('clients.type')) ?></label><select class="field__control" id="client-filter-type" name="type"><option value=""><?= e(__('clients.all_types')) ?></option><?php foreach (['individual', 'company', 'organization'] as $type): ?><option value="<?= e($type) ?>" <?= $filters['type'] === $type ? 'selected' : '' ?>><?= e(__('clients.type.' . $type)) ?></option><?php endforeach; ?></select></div>
        <div class="field"><label class="u-sr-only" for="client-filter-status"><?= e(__('clients.status')) ?></label><select class="field__control" id="client-filter-status" name="status"><option value=""><?= e(__('clients.all_statuses')) ?></option><?php foreach (['prospect', 'active', 'archived'] as $status): ?><option value="<?= e($status) ?>" <?= $filters['status'] === $status ? 'selected' : '' ?>><?= e(__('clients.status.' . $status)) ?></option><?php endforeach; ?></select></div>
        <button class="button button--secondary" type="submit"><svg class="icon icon--small" aria-hidden="true"><use href="<?= e(asset('img/icons.svg')) ?>#filter"></use></svg><?= e(__('common.filter')) ?></button>
        <?php if ($filters['q'] !== '' || $filters['status'] !== '' || $filters['type'] !== ''): ?><a class="button button--ghost" href="<?= e(url('/clients')) ?>"><?= e(__('common.clear')) ?></a><?php endif; ?>
    </form>

    <?php View::partial('partials/csv-transfer', [
        'entity' => 'clients',
        'filters' => $filters,
        'importResult' => $importResult ?? null,
        'importFileError' => $importFileError ?? '',
        'canManage' => !empty($canManage),
    ]) ?>

    <?php if ($clients === []): ?>
        <?php View::partial('partials/empty-state', [
            'title' => $filters['q'] !== '' || $filters['status'] !== '' || $filters['type'] !== '' ? __('clients.no_results') : __('clients.no_clients'),
            'description' => $filters['q'] !== '' || $filters['status'] !== '' || $filters['type'] !== '' ? __('clients.no_results_help') : __('clients.no_clients_help'),
            'actionLabel' => !empty($canManage) && $filters['q'] === '' && $filters['status'] === '' && $filters['type'] === '' ? __('clients.create') : null,
            'actionUrl' => !empty($canManage) && $filters['q'] === '' && $filters['status'] === '' && $filters['type'] === '' ? url('/clients/new') : null,
        ]) ?>
    <?php else: ?>
        <?php if ($canDelete): ?>
            <form class="bulk-form" method="post" action="<?= e(url('/clients/delete')) ?>" data-confirm="<?= e($bulkConfirm) ?>" data-confirm-selection>
                <?= csrf_field() ?>
            <div class="bulk-actions">
                <span class="bulk-actions__count" data-selection-count data-label="<?= e($selectionLabel) ?>"><?= e(str_replace(':count', '0', $selectionLabel)) ?></span>
                <button class="button button--small button--danger" type="submit" data-selection-submit disabled><?= e(__('common.delete_selection')) ?></button>
            </div>
        <?php endif; ?>
        <div class="table-wrap">
            <table class="data-table">
                <thead><tr><?php if ($canDelete): ?><th scope="col" class="bulk-select"><input type="checkbox" data-select-all aria-label="<?= e(__('common.select_all')) ?>"></th><?php endif; ?><th scope="col"><?= e(__('clients.client')) ?></th><th scope="col"><?= e(__('clients.type')) ?></th><th scope="col"><?= e(__('clients.contact_details')) ?></th><th scope="col"><?= e(__('clients.projects')) ?></th><th scope="col"><?= e(__('clients.status')) ?></th><th scope="col"><?= e(__('common.updated')) ?></th><th scope="col"><span class="u-sr-only"><?= e(__('common.actions')) ?></span></th></tr></thead>
                <tbody>
                <?php foreach ($clients as $client): ?>
                    <?php $initials = mb_strtoupper(mb_substr((string) $client['display_name'], 0, 1, 'UTF-8'), 'UTF-8'); ?>
                    <tr>
                        <?php if ($canDelete): ?><td class="bulk-select"><input type="checkbox" name="ids[]" value="<?= (int) $client['id'] ?>" aria-label="<?= e(__('clients.select_record', ['name' => $client['display_name']])) ?>"></td><?php endif; ?>
                        <td><a class="table-primary" href="<?= e(url('/clients/' . (int) $client['id'])) ?>"><span class="avatar avatar--small" aria-hidden="true"><?= e($initials) ?></span><span class="table-primary__text"><strong class="table-primary__name"><?= e($client['display_name']) ?></strong><span class="table-primary__meta"><?= e($client['reference'] ?? '') ?><?= !empty($client['legal_name']) ? ' · ' . e($client['legal_name']) : '' ?></span></span></a></td>
                        <td><?= e(__('clients.type.' . $client['client_type'])) ?></td>
                        <td><span class="table-primary__text"><?php if (!empty($client['email'])): ?><a href="<?= e('mailto:' . $client['email']) ?>"><?= e($client['email']) ?></a><?php endif; ?><?php if (!empty($client['phone'])): ?><a class="table-primary__meta" href="<?= e('tel:' . preg_replace('/\s+/', '', (string) $client['phone'])) ?>"><?= e($client['phone']) ?></a><?php endif; ?><?php if (empty($client['email']) && empty($client['phone'])): ?>—<?php endif; ?></span></td>
                        <td><?= !empty($canViewProjects) ? e((string) $client['project_count']) : '—' ?></td>
                        <td><span class="badge badge--<?= e(match ($client['status']) { 'active' => 'success', 'archived' => 'neutral', default => 'info' }) ?>"><?= e(__('clients.status.' . $client['status'])) ?></span></td>
                        <td><?= e(date('d/m/Y', strtotime((string) $client['updated_at']))) ?></td>
                        <td><a class="button button--icon button--ghost" href="<?= e(url('/clients/' . (int) $client['id'])) ?>" aria-label="<?= e(__('clients.open_record', ['name' => $client['display_name']])) ?>"><svg class="icon icon--small icon--directional" aria-hidden="true"><use href="<?= e(asset('img/icons.svg')) ?>#arrow-up-right"></use></svg></a></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <div class="table-footer"><span><?= e(__('common.results', ['count' => (string) ($total ?? 0)])) ?></span><?php if ($pageCount > 1): ?><div class="cluster"><a class="button button--small button--secondary<?= $page <= 1 ? ' is-disabled' : '' ?>" href="<?= e(url('/clients?' . http_build_query($filters + ['page' => max(1, $page - 1)]))) ?>" <?= $page <= 1 ? 'aria-disabled="true"' : '' ?>><?= e(__('common.previous')) ?></a><span class="muted"><?= e(__('common.page', ['current' => (string) $page, 'total' => (string) $pageCount])) ?></span><a class="button button--small button--secondary<?= $page >= $pageCount ? ' is-disabled' : '' ?>" href="<?= e(url('/clients?' . http_build_query($filters + ['page' => min($pageCount, $page + 1)]))) ?>" <?= $page >= $pageCount ? 'aria-disabled="true"' : '' ?>><?= e(__('common.next')) ?></a></div><?php endif; ?></div>
        <?php if ($canDelete): ?>
            </form>
        <?php endif; ?>
    <?php endif; ?>
</section>
