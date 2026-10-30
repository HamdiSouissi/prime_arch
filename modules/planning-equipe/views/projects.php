<?php
declare(strict_types=1);

use App\Core\View;
use Modules\PlanningEquipe\Services\PlanningService;

$rows = is_array($rows ?? null) ? $rows : [];
$statistics = is_array($statistics ?? null) ? $statistics : [];
$hours = static fn (int $centi): string => PlanningService::hours($centi, __('planning.unit_hours'));
$gapLabel = static function (?int $gap) use ($hours): string {
    if ($gap === null) {
        return __('planning.no_budget');
    }

    return $gap > 0 ? '+' . $hours($gap) : $hours($gap);
};
?>
<?php View::partial('partials/page-header', [
    'pageTitle' => __('planning.projects_title'),
    'eyebrow' => __('planning.eyebrow'),
    'subtitle' => __('planning.projects_intro'),
    'secondaryAction' => ['label' => __('planning.back_to_planning'), 'icon' => 'calendar', 'url' => url('/planning')],
]) ?>

<?php if (!empty($notice)): ?><p class="form-feedback form-feedback--success" role="status"><?= e($notice) ?></p><?php endif; ?>
<?php if (!empty($error)): ?><p class="form-feedback form-feedback--error" role="alert"><?= e($error) ?></p><?php endif; ?>

<section class="stats-grid" aria-label="<?= e(__('planning.statistics')) ?>">
    <?php View::partial('partials/stat-card', ['label' => __('planning.stat.projects'), 'value' => (string) ($statistics['projects'] ?? 0), 'icon' => 'briefcase', 'tone' => 'blue']) ?>
    <?php View::partial('partials/stat-card', ['label' => __('planning.stat.budget'), 'value' => $hours((int) ($statistics['budget'] ?? 0)), 'icon' => 'wallet', 'tone' => 'mint']) ?>
    <?php View::partial('partials/stat-card', ['label' => __('planning.stat.planned'), 'value' => $hours((int) ($statistics['planned'] ?? 0)), 'icon' => 'calendar', 'tone' => 'pink']) ?>
    <?php View::partial('partials/stat-card', ['label' => __('planning.stat.realised'), 'value' => $hours((int) ($statistics['realised'] ?? 0)), 'icon' => 'timer', 'tone' => 'coral']) ?>
</section>

<p class="form-feedback form-feedback--info" role="note"><?= e(__('planning.budget_note')) ?></p>

<section class="card stack-md" aria-labelledby="planning-budget-title">
    <div class="card-header">
        <div>
            <h2 class="card-header__title" id="planning-budget-title"><?= e(__('planning.budget_title')) ?></h2>
            <p class="section-heading__note"><?= e(__('planning.budget_help')) ?></p>
        </div>
    </div>

    <?php if ($rows === []): ?>
        <?php View::partial('partials/empty-state', [
            'title' => __('planning.empty_projects_title'),
            'description' => __('planning.empty_projects_help'),
            'actionLabel' => __('planning.open_projects'),
            'actionUrl' => url('/projects'),
        ]) ?>
    <?php else: ?>
        <div class="table-wrap">
            <table class="data-table">
                <thead>
                    <tr>
                        <th scope="col"><?= e(__('planning.project')) ?></th>
                        <th scope="col"><?= e(__('planning.client')) ?></th>
                        <th scope="col"><?= e(__('planning.budget_hours')) ?></th>
                        <th scope="col"><?= e(__('planning.planned')) ?></th>
                        <th scope="col"><?= e(__('planning.realised')) ?></th>
                        <th scope="col"><?= e(__('planning.planned_gap')) ?></th>
                        <th scope="col"><?= e(__('planning.realised_gap')) ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($rows as $row): ?>
                        <?php
                        $project = is_array($row['project'] ?? null) ? $row['project'] : [];
                        $status = (string) ($project['project_status'] ?? '');
                        $budget = $row['budget'] ?? null;
                        ?>
                        <tr>
                            <td>
                                <a class="table-primary" href="<?= e(url('/projects/' . (int) ($project['id'] ?? 0))) ?>">
                                    <span class="table-primary__text">
                                        <strong class="table-primary__name"><?= e((string) ($project['name'] ?? '')) ?></strong>
                                        <span class="table-primary__meta">
                                            <?= e((string) ($project['reference'] ?? '')) ?><?= $status !== '' ? ' · ' . e(__('projects.status.' . $status)) : '' ?>
                                        </span>
                                    </span>
                                </a>
                            </td>
                            <td><?= e((string) ($project['client_name'] ?? '')) ?></td>
                            <td class="u-numeric">
                                <?php if ($budget === null): ?>
                                    <span class="muted"><?= e(__('planning.no_budget')) ?></span>
                                <?php else: ?>
                                    <?= e($hours((int) $budget)) ?>
                                <?php endif; ?>
                            </td>
                            <td class="u-numeric"><?= e($hours((int) ($row['planned'] ?? 0))) ?></td>
                            <td class="u-numeric"><?= e($hours((int) ($row['realised'] ?? 0))) ?></td>
                            <td class="u-numeric">
                                <?php if ($row['planned_gap'] === null): ?>
                                    <span class="muted">—</span>
                                <?php else: ?>
                                    <span class="<?= (int) $row['planned_gap'] > 0 ? 'badge badge--error' : '' ?>"><?= e($gapLabel((int) $row['planned_gap'])) ?></span>
                                <?php endif; ?>
                            </td>
                            <td class="u-numeric">
                                <?php if ($row['realised_gap'] === null): ?>
                                    <span class="muted">—</span>
                                <?php else: ?>
                                    <span class="<?= (int) $row['realised_gap'] > 0 ? 'badge badge--warning' : '' ?>"><?= e($gapLabel((int) $row['realised_gap'])) ?></span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <p class="table-meta"><?= e(__('planning.budget_legend')) ?></p>
    <?php endif; ?>
</section>
