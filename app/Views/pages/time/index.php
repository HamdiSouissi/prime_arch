<?php
declare(strict_types=1);

use App\Core\Auth;
use App\Core\View;

$entries = is_array($entries ?? null) ? $entries : [];
$summary = is_array($summary ?? null) ? $summary : [];
$filters = is_array($filters ?? null) ? $filters : [];
$projects = is_array($projects ?? null) ? $projects : [];
$users = is_array($users ?? null) ? $users : [];
$errors = is_array($errors ?? null) ? $errors : [];
$page = max(1, (int) ($page ?? 1));
$pageCount = max(1, (int) ($pageCount ?? 1));
?>
<?php View::partial('partials/page-header', [
    'pageTitle' => __('time.title'),
    'eyebrow' => __('time.period') . ' · ' . date('d/m/Y', strtotime((string) ($filters['date_from'] ?? 'today'))) . ' — ' . date('d/m/Y', strtotime((string) ($filters['date_to'] ?? 'today'))),
    'subtitle' => __('time.intro'),
    'primaryAction' => !empty($canCreate) ? ['label' => __('time.add'), 'icon' => 'plus', 'url' => url('/time/new')] : null,
]) ?>

<?php if (!empty($notice)): ?><p class="form-feedback form-feedback--success" role="status"><?= e($notice) ?></p><?php endif; ?>
<?php if (!empty($errors['general'])): ?><p class="form-feedback form-feedback--error" role="alert"><?= e($errors['general']) ?></p><?php endif; ?>

<section class="stats-grid<?= !empty($canViewCosts) ? '' : ' stats-grid--three' ?>" aria-label="<?= e(__('time.title')) ?>">
    <?php View::partial('partials/stat-card', ['label' => __('time.hours_total'), 'value' => str_replace('.', ',', (string) ($summary['hours'] ?? '0.00')) . ' h', 'icon' => 'clock', 'tone' => 'blue']) ?>
    <?php View::partial('partials/stat-card', ['label' => __('time.billable_hours'), 'value' => str_replace('.', ',', (string) ($summary['billable_hours'] ?? '0.00')) . ' h', 'icon' => 'currency', 'tone' => 'mint']) ?>
    <?php if (!empty($canViewCosts)): ?><?php View::partial('partials/stat-card', ['label' => __('time.cost_total'), 'value' => money_format_tnd((string) ($summary['cost'] ?? '0.000')), 'icon' => 'wallet', 'tone' => 'coral']) ?><?php endif; ?>
    <?php View::partial('partials/stat-card', ['label' => __('time.entries_total'), 'value' => (string) ($summary['entry_count'] ?? 0), 'icon' => 'briefcase', 'tone' => 'pink']) ?>
</section>

<section class="card stack-md" aria-label="<?= e(__('time.entry_table')) ?>">
    <form class="filter-row" method="get" action="<?= e(url('/time')) ?>">
        <div class="field"><label class="field__label" for="time-date-from"><?= e(__('time.date_from')) ?></label><input class="field__control" id="time-date-from" type="date" name="date_from" value="<?= e($filters['date_from'] ?? '') ?>"></div>
        <div class="field"><label class="field__label" for="time-date-to"><?= e(__('time.date_to')) ?></label><input class="field__control" id="time-date-to" type="date" name="date_to" value="<?= e($filters['date_to'] ?? '') ?>"></div>
        <div class="field"><label class="u-sr-only" for="time-filter-project"><?= e(__('time.project')) ?></label><select class="field__control" id="time-filter-project" name="project_id"><option value=""><?= e(__('time.all_projects')) ?></option><?php foreach ($projects as $project): ?><option value="<?= e((string) $project['id']) ?>" <?= (string) ($filters['project_id'] ?? '') === (string) $project['id'] ? 'selected' : '' ?>><?= e($project['name']) ?> · <?= e($project['client_name']) ?></option><?php endforeach; ?></select></div>
        <?php if (!empty($canViewAll)): ?><div class="field"><label class="u-sr-only" for="time-filter-user"><?= e(__('time.user')) ?></label><select class="field__control" id="time-filter-user" name="user_id"><option value=""><?= e(__('time.all_users')) ?></option><?php foreach ($users as $user): ?><option value="<?= e((string) $user['id']) ?>" <?= (string) ($filters['user_id'] ?? '') === (string) $user['id'] ? 'selected' : '' ?>><?= e($user['name']) ?><?= empty($user['is_active']) ? ' · ' . e(__('common.disabled')) : '' ?></option><?php endforeach; ?></select></div><?php endif; ?>
        <button class="button button--secondary" type="submit"><svg class="icon icon--small" aria-hidden="true"><use href="<?= e(asset('img/icons.svg')) ?>#filter"></use></svg><?= e(__('common.filter')) ?></button>
    </form>

    <?php if ($entries === []): ?>
        <?php View::partial('partials/empty-state', [
            'title' => __('time.no_entries'),
            'description' => __('time.no_entries_help'),
            'actionLabel' => !empty($canCreate) ? __('time.add') : null,
            'actionUrl' => !empty($canCreate) ? url('/time/new') : null,
        ]) ?>
    <?php else: ?>
        <div class="table-wrap"><table class="data-table">
            <thead><tr><th scope="col"><?= e(__('time.work_date')) ?></th><?php if (!empty($canViewAll)): ?><th scope="col"><?= e(__('time.user')) ?></th><?php endif; ?><th scope="col"><?= e(__('time.project')) ?> / <?= e(__('time.phase')) ?></th><th scope="col"><?= e(__('time.description')) ?></th><th scope="col"><?= e(__('time.duration')) ?></th><th scope="col"><?= e(__('time.billable')) ?></th><?php if (!empty($canViewCosts)): ?><th scope="col"><?= e(__('time.cost_total')) ?></th><?php endif; ?><th scope="col"><?= e(__('common.actions')) ?></th></tr></thead>
            <tbody>
                <?php foreach ($entries as $entry): ?>
                    <tr>
                        <td><?= e(date('d/m/Y', strtotime((string) $entry['work_date']))) ?></td>
                        <?php if (!empty($canViewAll)): ?><td><?= e($entry['user_name']) ?></td><?php endif; ?>
                        <td><a href="<?= e(url('/projects/' . (int) $entry['project_id'])) ?>"><?= e($entry['project_name']) ?></a><span class="table-primary__meta"><?= e($entry['phase_name']) ?> · <?= e($entry['client_name']) ?></span></td>
                        <td><?= e($entry['description']) ?></td>
                        <td><?= e(str_replace('.', ',', (string) $entry['duration_hours'])) ?> h</td>
                        <td><span class="badge badge--<?= !empty($entry['is_billable']) ? 'success' : 'neutral' ?>"><?= e(!empty($entry['is_billable']) ? __('time.billable_yes') : __('time.billable_no')) ?></span></td>
                        <?php if (!empty($canViewCosts)): ?><td><?= e(money_format_tnd((string) $entry['cost_amount'])) ?></td><?php endif; ?>
                        <td><?php if (!empty($canCreate) && (Auth::can('time.manage_all') || Auth::id() === (int) $entry['user_id'])): ?><a class="button button--small button--secondary" href="<?= e(url('/time/' . (int) $entry['id'] . '/edit')) ?>"><?= e(__('common.edit')) ?></a><?php else: ?>—<?php endif; ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table></div>
        <div class="table-footer"><span><?= e(__('common.results', ['count' => (string) ($total ?? 0)])) ?></span><?php if ($pageCount > 1): ?><div class="cluster"><a class="button button--small button--secondary<?= $page <= 1 ? ' is-disabled' : '' ?>" href="<?= e(url('/time?' . http_build_query($filters + ['page' => max(1, $page - 1)]))) ?>" <?= $page <= 1 ? 'aria-disabled="true"' : '' ?>><?= e(__('common.previous')) ?></a><span class="muted"><?= e(__('common.page', ['current' => (string) $page, 'total' => (string) $pageCount])) ?></span><a class="button button--small button--secondary<?= $page >= $pageCount ? ' is-disabled' : '' ?>" href="<?= e(url('/time?' . http_build_query($filters + ['page' => min($pageCount, $page + 1)]))) ?>" <?= $page >= $pageCount ? 'aria-disabled="true"' : '' ?>><?= e(__('common.next')) ?></a></div><?php endif; ?></div>
    <?php endif; ?>
</section>
