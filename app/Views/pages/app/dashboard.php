<?php
declare(strict_types=1);

use App\Core\View;

/**
 * Tableau de bord (lot 4) : uniquement des données réelles.
 *
 * Chaque bloc arrive déjà filtré par permission (`$can`) et déjà formaté par
 * `DashboardService` ; la vue ne calcule rien, elle choisit seulement l'état vide ou la liste.
 *
 * @var array<int,array{label:string,value:string,icon:string,tone:string,note?:string,url?:string}> $cards
 * @var array<int,array<string,mixed>> $projects
 * @var array<int,array<string,mixed>> $deadlines
 * @var array{days:array<int,array<string,mixed>>,total:string,billable:string,label:string}|null $week
 * @var array<int,array<string,string|null>> $activity
 * @var array<string,bool> $can
 * @var bool $empty
 * @var int $horizon
 * @var array<int,array{label:string,url:string,icon:string}> $quickActions
 */
$cards = is_array($cards ?? null) ? $cards : [];
$projects = is_array($projects ?? null) ? $projects : [];
$deadlines = is_array($deadlines ?? null) ? $deadlines : [];
$activity = is_array($activity ?? null) ? $activity : [];
$week = is_array($week ?? null) ? $week : null;
$quickActions = is_array($quickActions ?? null) ? $quickActions : [];
$can = is_array($can ?? null) ? $can : [];
$today = new DateTimeImmutable('today');

// Les échéances portent leur propre tonalité : retard en rouge, proche en ambre, sinon bleu.
$badgeTone = static fn (string $tone): string => match ($tone) {
    'danger' => 'danger',
    'warning' => 'warning',
    default => 'info',
};
?>
<?php View::partial('partials/page-header', [
    'pageTitle' => __('dashboard.welcome', ['name' => $userName ?? '']),
    'subtitle' => __('dashboard.subtitle'),
    'eyebrow' => ($cabinetName ?? config('app.name')) . ' · ' . $today->format('d/m/Y'),
    'primaryAction' => $quickActions !== []
        ? ['label' => __('quick.new'), 'icon' => 'plus', 'dialog' => '#quick-add-dialog']
        : null,
    'secondaryAction' => !empty($can['projects'])
        ? ['label' => __('dashboard.kpi_projects'), 'icon' => 'arrow-up-right', 'url' => url('/projects')]
        : null,
]) ?>

<?php if (!empty($empty)): ?>
    <section class="card dashboard-start" aria-labelledby="dashboard-start-title">
        <span class="dashboard-start__icon" aria-hidden="true">
            <svg class="icon icon--large"><use href="<?= e(asset('img/icons.svg')) ?>#sparkles"></use></svg>
        </span>
        <div class="dashboard-start__copy">
            <h2 class="card-header__title" id="dashboard-start-title"><?= e(__('dashboard.empty_title')) ?></h2>
            <p class="section-heading__note"><?= e(__('dashboard.empty_intro')) ?></p>
        </div>
        <?php if (!empty($can['clients']) && $quickActions !== []): ?>
            <a class="button button--primary" href="<?= e(url('/clients/new')) ?>"><?= e(__('dashboard.empty_action')) ?></a>
        <?php endif; ?>
    </section>
<?php endif; ?>

<?php if ($cards !== []): ?>
    <section class="stats-grid" aria-label="<?= e(__('dashboard.kpi_label')) ?>">
        <?php foreach ($cards as $card): ?>
            <?php View::partial('partials/stat-card', $card) ?>
        <?php endforeach; ?>
    </section>
<?php endif; ?>

<div class="dashboard-grid">
    <div class="stack-lg">
        <?php if (!empty($can['projects'])): ?>
            <section class="card" aria-labelledby="dashboard-projects-title">
                <div class="card-header">
                    <div>
                        <h2 class="card-header__title" id="dashboard-projects-title"><?= e(__('dashboard.projects_title')) ?></h2>
                        <p class="section-heading__note"><?= e(__('dashboard.projects_intro')) ?></p>
                    </div>
                    <a class="card-header__action" href="<?= e(url('/projects')) ?>">
                        <?= e(__('common.see_all')) ?>
                        <svg class="icon icon--small icon--directional" aria-hidden="true"><use href="<?= e(asset('img/icons.svg')) ?>#chevron-right"></use></svg>
                    </a>
                </div>
                <?php if ($projects === []): ?>
                    <?php View::partial('partials/empty-state', [
                        'title' => __('dashboard.projects_empty'),
                        'description' => __('dashboard.projects_empty_help'),
                        'actionLabel' => __('dashboard.projects_cta'),
                        'actionUrl' => url('/projects'),
                        'compact' => true,
                    ]) ?>
                <?php else: ?>
                    <div class="project-list">
                        <?php foreach ($projects as $project): ?>
                            <?php $progress = (float) ($project['weighted_progress'] ?? 0); ?>
                            <a class="project-progress project-progress--link" href="<?= e(url('/projects/' . (int) $project['id'])) ?>">
                                <span class="project-progress__top">
                                    <span class="project-progress__copy">
                                        <span class="project-progress__name"><?= e((string) $project['name']) ?></span>
                                        <span class="project-progress__meta">
                                            <?= e((string) $project['client_name']) ?>
                                            <?php if (!empty($project['target_end_date'])): ?>
                                                · <?= e(__('dashboard.due_on', ['date' => date('d/m/Y', strtotime((string) $project['target_end_date']))])) ?>
                                            <?php endif; ?>
                                        </span>
                                    </span>
                                    <span class="project-progress__value"><?= e(number_format($progress, 0, ',', ' ')) ?> %</span>
                                </span>
                                <?php View::partial('partials/progress', [
                                    'value' => $progress,
                                    'label' => __('dashboard.progress_label', ['name' => (string) $project['name']]),
                                    'showMeta' => false,
                                ]) ?>
                            </a>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </section>
        <?php endif; ?>

        <?php if ($week !== null): ?>
            <section class="card" aria-labelledby="dashboard-hours-title">
                <div class="card-header">
                    <div>
                        <h2 class="card-header__title" id="dashboard-hours-title"><?= e(__('dashboard.hours_title')) ?></h2>
                        <p class="section-heading__note">
                            <?php /* La plage de dates reste dans l'ordre latin, même en arabe. */ ?>
                            <?= e(__('dashboard.hours_note')) ?> <bdi dir="ltr"><?= e($week['label']) ?></bdi>
                            · <?= e(!empty($can['timeAll']) ? __('dashboard.hours_scope_all') : __('dashboard.hours_scope_self')) ?>
                        </p>
                    </div>
                    <?php if (!empty($week['hasHours'])): ?>
                        <span class="badge badge--info badge--plain"><?= e(__('dashboard.hours_badge', [
                            'total' => $week['total'],
                            'billable' => $week['billable'],
                        ])) ?></span>
                    <?php endif; ?>
                </div>
                <?php if (empty($week['hasHours'])): ?>
                    <p class="dashboard-hint"><?= e(__('dashboard.hours_empty')) ?></p>
                <?php endif; ?>
                <div class="week-chart" role="img" aria-label="<?= e(__('dashboard.hours_chart_label', ['range' => $week['label']])) ?>">
                    <?php foreach ($week['days'] as $day): ?>
                        <div class="week-chart__day<?= !empty($day['isToday']) ? ' week-chart__day--today' : '' ?>">
                            <div class="week-chart__track">
                                <?php if ((float) $day['hours'] > 0): ?>
                                    <span class="week-chart__bar" style="--chart-height: <?= e((string) $day['height']) ?>%"></span>
                                <?php endif; ?>
                            </div>
                            <span class="week-chart__hours"><?= e($day['hoursLabel']) ?></span>
                            <span class="week-chart__label"><?= e(__('common.weekday.short.' . $day['weekday'])) ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
            </section>
        <?php endif; ?>
    </div>

    <div class="stack-lg">
        <section class="card" aria-labelledby="dashboard-deadlines-title">
            <div class="card-header">
                <div>
                    <h2 class="card-header__title" id="dashboard-deadlines-title"><?= e(__('dashboard.deadlines_title')) ?></h2>
                    <p class="section-heading__note"><?= e(__('dashboard.deadlines_note', ['count' => (string) (int) $horizon])) ?></p>
                </div>
            </div>
            <?php if ($deadlines === []): ?>
                <p class="dashboard-hint"><?= e(__('dashboard.deadlines_empty', ['count' => (string) (int) $horizon])) ?></p>
            <?php else: ?>
                <div class="deadline-list">
                    <?php foreach ($deadlines as $deadline): ?>
                        <a class="deadline-item deadline-item--stacked" href="<?= e((string) $deadline['url']) ?>">
                            <span class="deadline-item__date" aria-hidden="true">
                                <span class="deadline-item__day"><?= e((string) $deadline['day']) ?></span>
                                <span><?= e((string) $deadline['month']) ?></span>
                            </span>
                            <span class="deadline-item__copy">
                                <span class="deadline-item__title"><?= e((string) $deadline['title']) ?></span>
                                <span class="deadline-item__meta">
                                    <?= e($deadline['type'] . ' · ' . $deadline['client']) ?>
                                    <span class="u-sr-only"><?= e(__('dashboard.due_date_label', ['date' => date('d/m/Y', strtotime((string) $deadline['dueDate']))])) ?></span>
                                </span>
                            </span>
                            <span class="badge badge--<?= e($badgeTone((string) $deadline['tone'])) ?> badge--plain"><?= e((string) $deadline['badge']) ?></span>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>

        <section class="card" aria-labelledby="dashboard-activity-title">
            <div class="card-header">
                <div>
                    <h2 class="card-header__title" id="dashboard-activity-title"><?= e(__('dashboard.activity_title')) ?></h2>
                    <p class="section-heading__note"><?= e(__('dashboard.activity_note')) ?></p>
                </div>
            </div>
            <?php if ($activity === []): ?>
                <p class="dashboard-hint"><?= e(__('dashboard.activity_empty')) ?></p>
            <?php else: ?>
                <div class="activity-list">
                    <?php foreach ($activity as $item): ?>
                        <?php $tag = !empty($item['url']) ? 'a' : 'div'; ?>
                        <<?= $tag ?> class="activity-item"<?= !empty($item['url']) ? ' href="' . e((string) $item['url']) . '"' : '' ?>>
                            <span class="activity-item__icon" aria-hidden="true">
                                <svg class="icon icon--small"><use href="<?= e(asset('img/icons.svg')) ?>#<?= e((string) ($item['icon'] ?? 'info')) ?>"></use></svg>
                            </span>
                            <span class="activity-item__copy">
                                <span class="activity-item__text"><?= e((string) $item['text']) ?></span>
                                <span class="activity-item__time"><?= e((string) $item['meta']) ?> · <?= e((string) $item['time']) ?></span>
                            </span>
                        </<?= $tag ?>>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>
    </div>
</div>

<p class="foundation-footnote">
    <svg class="icon icon--small" aria-hidden="true"><use href="<?= e(asset('img/icons.svg')) ?>#info"></use></svg>
    <?= e(__('dashboard.fiscal_warning')) ?>
</p>
