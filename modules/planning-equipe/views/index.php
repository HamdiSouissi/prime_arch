<?php
declare(strict_types=1);

use App\Core\View;
use Modules\PlanningEquipe\Services\PlanningService;

$rows = is_array($rows ?? null) ? $rows : [];
$days = is_array($days ?? null) ? $days : [];
$period = is_array($period ?? null) ? $period : ['start' => date('Y-m-d'), 'end' => date('Y-m-d')];
$scope = (string) ($scope ?? 'week');
$start = (string) ($start ?? $period['start']);
$capacity = (int) ($capacity ?? 800);
$affectations = is_array($affectations ?? null) ? $affectations : [];
$leaves = is_array($leaves ?? null) ? $leaves : [];
$people = is_array($people ?? null) ? $people : [];
$projects = is_array($projects ?? null) ? $projects : [];
$phases = is_array($phases ?? null) ? $phases : [];
$statistics = is_array($statistics ?? null) ? $statistics : [];
$kinds = is_array($kinds ?? null) ? $kinds : PlanningService::KINDS;
$affectationErrors = is_array($affectationErrors ?? null) ? $affectationErrors : [];
$affectationValues = is_array($affectationValues ?? null) ? $affectationValues : [];
$leaveErrors = is_array($leaveErrors ?? null) ? $leaveErrors : [];
$leaveValues = is_array($leaveValues ?? null) ? $leaveValues : [];
$canViewAll = !empty($canViewAll);
$currentUserId = (int) ($currentUserId ?? 0);

$anchor = new DateTimeImmutable($period['start']);
$previous = $scope === 'month'
    ? $anchor->modify('first day of previous month')->format('Y-m-d')
    : $anchor->modify('-7 days')->format('Y-m-d');
$next = $scope === 'month'
    ? $anchor->modify('first day of next month')->format('Y-m-d')
    : $anchor->modify('+7 days')->format('Y-m-d');
$periodUrl = static fn (string $date, string $view, int $threshold): string => url('/planning?' . http_build_query([
    'start' => $date,
    'scope' => $view,
    'capacity' => PlanningService::format($threshold),
]));
$hours = static fn (int $centi): string => PlanningService::hours($centi, __('planning.unit_hours'));
$canDeleteFor = static fn (array $row): bool => !empty($canManage) && ($canViewAll || (int) ($row['user_id'] ?? 0) === $currentUserId);
?>
<?php View::partial('partials/page-header', [
    'pageTitle' => __('planning.title'),
    'eyebrow' => __('planning.eyebrow'),
    'subtitle' => __('planning.intro'),
    'secondaryAction' => ['label' => __('planning.projects_link'), 'icon' => 'chart', 'url' => url('/planning/projets')],
]) ?>

<?php if (!empty($notice)): ?><p class="form-feedback form-feedback--success" role="status"><?= e($notice) ?></p><?php endif; ?>
<?php if (!empty($error)): ?><p class="form-feedback form-feedback--error" role="alert"><?= e($error) ?></p><?php endif; ?>

<section class="stats-grid" aria-label="<?= e(__('planning.statistics')) ?>">
    <?php View::partial('partials/stat-card', ['label' => __('planning.stat.people'), 'value' => (string) ($statistics['people'] ?? 0), 'icon' => 'people', 'tone' => 'blue']) ?>
    <?php View::partial('partials/stat-card', ['label' => __('planning.stat.planned'), 'value' => $hours((int) ($statistics['planned'] ?? 0)), 'icon' => 'calendar', 'tone' => 'mint']) ?>
    <?php View::partial('partials/stat-card', ['label' => __('planning.stat.realised'), 'value' => $hours((int) ($statistics['realised'] ?? 0)), 'icon' => 'timer', 'tone' => 'pink']) ?>
    <?php View::partial('partials/stat-card', [
        'label' => __('planning.stat.leave_days'),
        'value' => (string) ($statistics['leave_days'] ?? 0),
        'icon' => 'clock',
        'tone' => 'coral',
        'note' => __('planning.stat.overload_note', [
            'count' => (string) ($statistics['overload'] ?? 0),
            'threshold' => $hours($capacity),
        ]),
    ]) ?>
</section>

<section class="card stack-md" aria-labelledby="planning-period-title">
    <div class="card-header">
        <div>
            <h2 class="card-header__title" id="planning-period-title"><?= e(__('planning.period_title')) ?></h2>
            <p class="section-heading__note">
                <?= e(__('planning.period_range', [
                    'from' => date('d/m/Y', (int) strtotime($period['start'])),
                    'to' => date('d/m/Y', (int) strtotime($period['end'])),
                ])) ?>
            </p>
        </div>
        <div class="cluster">
            <a class="button button--small button--ghost" href="<?= e($periodUrl($previous, $scope, $capacity)) ?>">
                <svg class="icon icon--small" aria-hidden="true"><use href="<?= e(asset('img/icons.svg')) ?>#chevron-left"></use></svg><?= e(__('planning.previous')) ?>
            </a>
            <a class="button button--small button--ghost" href="<?= e($periodUrl($next, $scope, $capacity)) ?>"><?= e(__('planning.next')) ?>
                <svg class="icon icon--small" aria-hidden="true"><use href="<?= e(asset('img/icons.svg')) ?>#chevron-right"></use></svg>
            </a>
        </div>
    </div>

    <form class="filter-row" method="get" action="<?= e(url('/planning')) ?>">
        <div class="field">
            <label class="field__label" for="planning-start"><?= e(__('planning.start_date')) ?></label>
            <input class="field__control" type="date" id="planning-start" name="start" value="<?= e($start) ?>">
        </div>
        <div class="field">
            <label class="field__label" for="planning-scope"><?= e(__('planning.scope')) ?></label>
            <select class="field__control" id="planning-scope" name="scope">
                <?php foreach (PlanningService::SCOPES as $option): ?>
                    <option value="<?= e($option) ?>" <?= $scope === $option ? 'selected' : '' ?>><?= e(__('planning.scope.' . $option)) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="field">
            <label class="field__label" for="planning-capacity"><?= e(__('planning.capacity')) ?></label>
            <input class="field__control" type="text" inputmode="decimal" id="planning-capacity" name="capacity"
                   value="<?= e(PlanningService::format($capacity)) ?>">
            <span class="field__hint"><?= e(__('planning.capacity_help')) ?></span>
        </div>
        <button class="button button--secondary" type="submit">
            <svg class="icon icon--small" aria-hidden="true"><use href="<?= e(asset('img/icons.svg')) ?>#filter"></use></svg><?= e(__('common.filter')) ?>
        </button>
    </form>
</section>

<section class="card stack-md" aria-labelledby="planning-grid-title">
    <div class="card-header">
        <div>
            <h2 class="card-header__title" id="planning-grid-title"><?= e(__('planning.grid_title')) ?></h2>
            <p class="section-heading__note"><?= e(__('planning.grid_help')) ?></p>
        </div>
    </div>

    <?php if ($rows === []): ?>
        <?php View::partial('partials/empty-state', [
            'title' => __('planning.empty_title'),
            'description' => $canViewAll ? __('planning.empty_help') : __('planning.empty_help_own'),
        ]) ?>
    <?php else: ?>
        <div class="table-wrap">
            <table class="data-table">
                <thead>
                    <tr>
                        <th scope="col"><?= e(__('planning.collaborator')) ?></th>
                        <?php foreach ($days as $day): ?>
                            <th scope="col" class="u-numeric<?= $day['is_weekend'] ? ' muted' : '' ?>">
                                <?= e($day['label']) ?><br>
                                <span class="table-meta"><?= e(date('d/m', (int) strtotime($day['date']))) ?></span>
                            </th>
                        <?php endforeach; ?>
                        <th scope="col" class="u-numeric"><?= e(__('planning.planned')) ?></th>
                        <th scope="col" class="u-numeric"><?= e(__('planning.realised')) ?></th>
                        <th scope="col" class="u-numeric"><?= e(__('planning.leave_days')) ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($rows as $row): ?>
                        <tr>
                            <td>
                                <span class="table-primary__text">
                                    <strong class="table-primary__name"><?= e((string) $row['user']['name']) ?></strong>
                                    <span class="table-primary__meta"><?= e((string) ($row['user']['role_label'] ?? '')) ?></span>
                                </span>
                            </td>
                            <?php foreach ($row['cells'] as $cell): ?>
                                <td class="u-numeric<?= $cell['is_weekend'] ? ' muted' : '' ?>">
                                    <?php if ($cell['planned'] > 0): ?>
                                        <strong><?= e($hours((int) $cell['planned'])) ?></strong>
                                    <?php endif; ?>
                                    <?php if ($cell['realised'] > 0): ?>
                                        <span class="table-meta"><?= e(__('planning.cell_realised', ['hours' => $hours((int) $cell['realised'])])) ?></span>
                                    <?php endif; ?>
                                    <?php if ($cell['planned'] === 0 && $cell['realised'] === 0 && !$cell['leave']): ?>
                                        <span class="muted">—</span>
                                    <?php endif; ?>
                                    <?php if ($cell['leave']): ?>
                                        <span class="badge badge--info"><?= e(__('planning.leave_short')) ?></span>
                                    <?php endif; ?>
                                    <?php if ($cell['overloaded']): ?>
                                        <span class="badge badge--warning"><?= e(__('planning.overload_short')) ?></span>
                                    <?php endif; ?>
                                </td>
                            <?php endforeach; ?>
                            <td class="u-numeric"><strong><?= e($hours((int) $row['planned'])) ?></strong></td>
                            <td class="u-numeric"><?= e($hours((int) $row['realised'])) ?></td>
                            <td class="u-numeric"><?= (int) $row['leave_days'] ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <p class="table-meta"><?= e(__('planning.grid_note')) ?></p>
    <?php endif; ?>
</section>

<?php if (!empty($canManage)): ?>
    <section class="card stack-md" aria-labelledby="planning-forms-title">
        <div class="card-header">
            <div>
                <h2 class="card-header__title" id="planning-forms-title"><?= e(__('planning.forms_title')) ?></h2>
                <p class="section-heading__note"><?= e(__('planning.forms_help')) ?></p>
            </div>
        </div>

        <details class="stack-md" <?= $affectationErrors !== [] ? 'open' : '' ?>>
            <summary class="text-callout"><?= e(__('planning.add_affectation')) ?></summary>
            <?php if ($affectationErrors !== []): ?>
                <p class="form-feedback form-feedback--error" role="alert"><?= e(__('planning.fix_errors')) ?></p>
            <?php endif; ?>
            <form class="form-grid" method="post" action="<?= e(url('/planning/affectations')) ?>">
                <?= csrf_field() ?>
                <input type="hidden" name="start" value="<?= e($start) ?>">
                <input type="hidden" name="scope" value="<?= e($scope) ?>">
                <input type="hidden" name="capacity" value="<?= e(PlanningService::format($capacity)) ?>">
                <div class="field">
                    <label class="field__label" for="affectation-user"><?= e(__('planning.collaborator')) ?></label>
                    <select class="field__control" id="affectation-user" name="user_id" required>
                        <?php foreach ($people as $person): ?>
                            <option value="<?= (int) $person['id'] ?>" <?= (int) ($affectationValues['user_id'] ?? 0) === (int) $person['id'] ? 'selected' : '' ?>>
                                <?= e((string) $person['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <?php if (!empty($affectationErrors['user_id'])): ?><span class="field__hint field__hint--error"><?= e($affectationErrors['user_id']) ?></span><?php endif; ?>
                </div>
                <div class="field">
                    <label class="field__label" for="affectation-project"><?= e(__('planning.project')) ?></label>
                    <select class="field__control" id="affectation-project" name="project_id" required>
                        <?php foreach ($projects as $project): ?>
                            <option value="<?= (int) $project['id'] ?>" <?= (int) ($affectationValues['project_id'] ?? 0) === (int) $project['id'] ? 'selected' : '' ?>>
                                <?= e((string) $project['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <?php if (!empty($affectationErrors['project_id'])): ?><span class="field__hint field__hint--error"><?= e($affectationErrors['project_id']) ?></span><?php endif; ?>
                </div>
                <div class="field form-grid__full">
                    <label class="field__label" for="affectation-phase"><?= e(__('planning.phase')) ?></label>
                    <select class="field__control" id="affectation-phase" name="project_phase_assignment_id">
                        <option value=""><?= e(__('planning.no_phase')) ?></option>
                        <?php foreach ($phases as $phase): ?>
                            <option value="<?= (int) $phase['id'] ?>" <?= (int) ($affectationValues['project_phase_assignment_id'] ?? 0) === (int) $phase['id'] ? 'selected' : '' ?>>
                                <?= e((string) $phase['project_name']) ?> — <?= e((string) $phase['phase_name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <span class="field__hint"><?= e(__('planning.phase_help')) ?></span>
                    <?php if (!empty($affectationErrors['project_phase_assignment_id'])): ?><span class="field__hint field__hint--error"><?= e($affectationErrors['project_phase_assignment_id']) ?></span><?php endif; ?>
                </div>
                <div class="field">
                    <label class="field__label" for="affectation-start"><?= e(__('planning.start_date')) ?></label>
                    <input class="field__control" type="date" id="affectation-start" name="start_date"
                           value="<?= e((string) ($affectationValues['start_date'] ?? $period['start'])) ?>" required>
                    <?php if (!empty($affectationErrors['start_date'])): ?><span class="field__hint field__hint--error"><?= e($affectationErrors['start_date']) ?></span><?php endif; ?>
                </div>
                <div class="field">
                    <label class="field__label" for="affectation-end"><?= e(__('planning.end_date')) ?></label>
                    <input class="field__control" type="date" id="affectation-end" name="end_date"
                           value="<?= e((string) ($affectationValues['end_date'] ?? $period['end'])) ?>" required>
                    <?php if (!empty($affectationErrors['end_date'])): ?><span class="field__hint field__hint--error"><?= e($affectationErrors['end_date']) ?></span><?php endif; ?>
                </div>
                <div class="field">
                    <label class="field__label" for="affectation-daily"><?= e(__('planning.daily_hours')) ?></label>
                    <input class="field__control" type="text" inputmode="decimal" id="affectation-daily" name="daily_hours"
                           value="<?= e((string) ($affectationValues['daily_hours'] ?? '')) ?>" placeholder="7.50" required>
                    <span class="field__hint"><?= e(__('planning.daily_hours_help')) ?></span>
                    <?php if (!empty($affectationErrors['daily_hours'])): ?><span class="field__hint field__hint--error"><?= e($affectationErrors['daily_hours']) ?></span><?php endif; ?>
                </div>
                <div class="field">
                    <label class="field__label" for="affectation-note"><?= e(__('planning.note')) ?></label>
                    <input class="field__control" type="text" id="affectation-note" name="note" maxlength="500"
                           value="<?= e((string) ($affectationValues['note'] ?? '')) ?>">
                    <?php if (!empty($affectationErrors['note'])): ?><span class="field__hint field__hint--error"><?= e($affectationErrors['note']) ?></span><?php endif; ?>
                </div>
                <div class="form-actions form-grid__full">
                    <button class="button button--primary" type="submit"><?= e(__('planning.add_affectation_submit')) ?></button>
                </div>
            </form>
        </details>

        <details class="stack-md" <?= $leaveErrors !== [] ? 'open' : '' ?>>
            <summary class="text-callout"><?= e(__('planning.add_leave')) ?></summary>
            <?php if ($leaveErrors !== []): ?>
                <p class="form-feedback form-feedback--error" role="alert"><?= e(__('planning.fix_errors')) ?></p>
            <?php endif; ?>
            <form class="form-grid" method="post" action="<?= e(url('/planning/conges')) ?>">
                <?= csrf_field() ?>
                <input type="hidden" name="start" value="<?= e($start) ?>">
                <input type="hidden" name="scope" value="<?= e($scope) ?>">
                <input type="hidden" name="capacity" value="<?= e(PlanningService::format($capacity)) ?>">
                <div class="field">
                    <label class="field__label" for="leave-user"><?= e(__('planning.collaborator')) ?></label>
                    <select class="field__control" id="leave-user" name="user_id" required>
                        <?php foreach ($people as $person): ?>
                            <option value="<?= (int) $person['id'] ?>" <?= (int) ($leaveValues['user_id'] ?? 0) === (int) $person['id'] ? 'selected' : '' ?>>
                                <?= e((string) $person['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <?php if (!empty($leaveErrors['user_id'])): ?><span class="field__hint field__hint--error"><?= e($leaveErrors['user_id']) ?></span><?php endif; ?>
                </div>
                <div class="field">
                    <label class="field__label" for="leave-kind"><?= e(__('planning.leave_kind')) ?></label>
                    <select class="field__control" id="leave-kind" name="kind">
                        <?php foreach ($kinds as $kind): ?>
                            <option value="<?= e($kind) ?>" <?= (string) ($leaveValues['kind'] ?? 'vacation') === $kind ? 'selected' : '' ?>>
                                <?= e(__('planning.kind.' . $kind)) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="field">
                    <label class="field__label" for="leave-start"><?= e(__('planning.start_date')) ?></label>
                    <input class="field__control" type="date" id="leave-start" name="start_date"
                           value="<?= e((string) ($leaveValues['start_date'] ?? $period['start'])) ?>" required>
                    <?php if (!empty($leaveErrors['start_date'])): ?><span class="field__hint field__hint--error"><?= e($leaveErrors['start_date']) ?></span><?php endif; ?>
                </div>
                <div class="field">
                    <label class="field__label" for="leave-end"><?= e(__('planning.end_date')) ?></label>
                    <input class="field__control" type="date" id="leave-end" name="end_date"
                           value="<?= e((string) ($leaveValues['end_date'] ?? $period['end'])) ?>" required>
                    <?php if (!empty($leaveErrors['end_date'])): ?><span class="field__hint field__hint--error"><?= e($leaveErrors['end_date']) ?></span><?php endif; ?>
                </div>
                <div class="field form-grid__full">
                    <label class="field__label" for="leave-note"><?= e(__('planning.note')) ?></label>
                    <input class="field__control" type="text" id="leave-note" name="note" maxlength="500"
                           value="<?= e((string) ($leaveValues['note'] ?? '')) ?>">
                    <?php if (!empty($leaveErrors['note'])): ?><span class="field__hint field__hint--error"><?= e($leaveErrors['note']) ?></span><?php endif; ?>
                </div>
                <div class="form-actions form-grid__full">
                    <button class="button button--primary" type="submit"><?= e(__('planning.add_leave_submit')) ?></button>
                </div>
            </form>
        </details>
    </section>
<?php endif; ?>

<section class="card stack-md" aria-labelledby="planning-affectations-title">
    <div class="card-header">
        <div>
            <h2 class="card-header__title" id="planning-affectations-title"><?= e(__('planning.affectations_title')) ?></h2>
            <p class="section-heading__note"><?= e(__('planning.affectations_help')) ?></p>
        </div>
    </div>
    <?php if ($affectations === []): ?>
        <p class="table-meta"><?= e(__('planning.no_affectations')) ?></p>
    <?php else: ?>
        <div class="table-wrap">
            <table class="data-table">
                <thead>
                    <tr>
                        <th scope="col"><?= e(__('planning.collaborator')) ?></th>
                        <th scope="col"><?= e(__('planning.project')) ?></th>
                        <th scope="col"><?= e(__('planning.phase')) ?></th>
                        <th scope="col"><?= e(__('planning.period')) ?></th>
                        <th scope="col"><?= e(__('planning.daily_hours')) ?></th>
                        <th scope="col"><?= e(__('planning.planned')) ?></th>
                        <?php if (!empty($canManage)): ?><th scope="col"><?= e(__('common.actions')) ?></th><?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($affectations as $affectation): ?>
                        <tr>
                            <td><?= e((string) $affectation['user_name']) ?></td>
                            <td><?= e((string) $affectation['project_name']) ?></td>
                            <td><?= ($affectation['phase_name'] ?? null) !== null ? e((string) $affectation['phase_name']) : '<span class="muted">—</span>' ?></td>
                            <td><?= e(date('d/m/Y', (int) strtotime((string) $affectation['start_date']))) ?> → <?= e(date('d/m/Y', (int) strtotime((string) $affectation['end_date']))) ?></td>
                            <td class="u-numeric"><?= e($hours(PlanningService::centi($affectation['daily_hours']) ?? 0)) ?></td>
                            <td class="u-numeric"><strong><?= e($hours((int) $affectation['planned'])) ?></strong></td>
                            <?php if (!empty($canManage)): ?>
                                <td>
                                    <?php if ($canDeleteFor($affectation)): ?>
                                        <form method="post" action="<?= e(url('/planning/affectations/' . (int) $affectation['id'] . '/delete')) ?>"
                                              data-confirm="<?= e(__('planning.confirm_delete_affectation')) ?>">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="start" value="<?= e($start) ?>">
                                            <input type="hidden" name="scope" value="<?= e($scope) ?>">
                                            <input type="hidden" name="capacity" value="<?= e(PlanningService::format($capacity)) ?>">
                                            <button class="button button--small button--ghost" type="submit"><?= e(__('common.delete')) ?></button>
                                        </form>
                                    <?php else: ?>
                                        <span class="muted">—</span>
                                    <?php endif; ?>
                                </td>
                            <?php endif; ?>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>

<section class="card stack-md" aria-labelledby="planning-leaves-title">
    <div class="card-header">
        <div>
            <h2 class="card-header__title" id="planning-leaves-title"><?= e(__('planning.leaves_title')) ?></h2>
            <p class="section-heading__note"><?= e(__('planning.leaves_help')) ?></p>
        </div>
    </div>
    <?php if ($leaves === []): ?>
        <p class="table-meta"><?= e(__('planning.no_leaves')) ?></p>
    <?php else: ?>
        <div class="table-wrap">
            <table class="data-table">
                <thead>
                    <tr>
                        <th scope="col"><?= e(__('planning.collaborator')) ?></th>
                        <th scope="col"><?= e(__('planning.leave_kind')) ?></th>
                        <th scope="col"><?= e(__('planning.period')) ?></th>
                        <th scope="col"><?= e(__('planning.note')) ?></th>
                        <?php if (!empty($canManage)): ?><th scope="col"><?= e(__('common.actions')) ?></th><?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($leaves as $leave): ?>
                        <tr>
                            <td><?= e((string) $leave['user_name']) ?></td>
                            <td><span class="badge badge--neutral"><?= e(__('planning.kind.' . (string) $leave['kind'])) ?></span></td>
                            <td><?= e(date('d/m/Y', (int) strtotime((string) $leave['start_date']))) ?> → <?= e(date('d/m/Y', (int) strtotime((string) $leave['end_date']))) ?></td>
                            <td><?= trim((string) ($leave['note'] ?? '')) !== '' ? e((string) $leave['note']) : '<span class="muted">—</span>' ?></td>
                            <?php if (!empty($canManage)): ?>
                                <td>
                                    <?php if ($canDeleteFor($leave)): ?>
                                        <form method="post" action="<?= e(url('/planning/conges/' . (int) $leave['id'] . '/delete')) ?>"
                                              data-confirm="<?= e(__('planning.confirm_delete_leave')) ?>">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="start" value="<?= e($start) ?>">
                                            <input type="hidden" name="scope" value="<?= e($scope) ?>">
                                            <input type="hidden" name="capacity" value="<?= e(PlanningService::format($capacity)) ?>">
                                            <button class="button button--small button--ghost" type="submit"><?= e(__('common.delete')) ?></button>
                                        </form>
                                    <?php else: ?>
                                        <span class="muted">—</span>
                                    <?php endif; ?>
                                </td>
                            <?php endif; ?>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>
