<?php
declare(strict_types=1);

use App\Core\View;
use Modules\RelancesFactures\Services\RelanceService;

$totals = is_array($totals ?? null) ? $totals : [];
$clients = is_array($clients ?? null) ? $clients : [];
$invoices = is_array($invoices ?? null) ? $invoices : [];
$events = is_array($events ?? null) ? $events : [];
$exclusions = is_array($exclusions ?? null) ? $exclusions : [];
$buckets = RelanceService::BUCKETS;

$formatDate = static function (?string $value): string {
    if ($value === null || $value === '') {
        return '—';
    }
    $time = strtotime($value);

    return $time === false ? '—' : date('d/m/Y', $time);
};
?>
<?php View::partial('partials/page-header', [
    'pageTitle' => __('relance.title'),
    'eyebrow' => __('relance.eyebrow'),
    'subtitle' => __('relance.intro'),
    'primaryAction' => ['label' => __('relance.open_queue'), 'icon' => 'send', 'url' => url('/relances/impayes')],
    'secondaryAction' => ['label' => __('relance.rules_link'), 'icon' => 'sliders', 'url' => url('/relances/regles')],
]) ?>

<?php if (!empty($notice)): ?><p class="form-feedback form-feedback--success" role="status"><?= e($notice) ?></p><?php endif; ?>
<?php if (!empty($error)): ?><p class="form-feedback form-feedback--error" role="alert"><?= e($error) ?></p><?php endif; ?>

<section class="stats-grid" aria-label="<?= e(__('relance.statistics')) ?>">
    <?php View::partial('partials/stat-card', ['label' => __('relance.stat.outstanding'), 'value' => money_format_tnd((string) ($totals['total'] ?? '0.000')), 'icon' => 'wallet', 'tone' => 'mint']) ?>
    <?php View::partial('partials/stat-card', ['label' => __('relance.stat.invoices'), 'value' => (string) ($totals['invoices'] ?? 0), 'icon' => 'receipt', 'tone' => 'blue']) ?>
    <?php View::partial('partials/stat-card', ['label' => __('relance.stat.overdue'), 'value' => (string) ($totals['overdue'] ?? 0), 'icon' => 'alert', 'tone' => 'coral']) ?>
    <?php View::partial('partials/stat-card', ['label' => __('relance.stat.overdue_amount'), 'value' => money_format_tnd((string) ($totals['overdue_amount'] ?? '0.000')), 'icon' => 'clock', 'tone' => 'pink']) ?>
</section>

<?php if (!empty($exclusions)): ?>
    <p class="table-meta" role="note">
        <span class="badge badge--warning"><?= e(__('relance.excluded')) ?></span>
        <?= e(__('relance.excluded_notice', ['count' => (string) count($exclusions)])) ?>
    </p>
<?php endif; ?>

<section class="card stack-md" aria-labelledby="relance-aged-title">
    <div class="card-header">
        <div>
            <h2 class="card-header__title" id="relance-aged-title"><?= e(__('relance.aged_balance')) ?></h2>
            <p class="section-heading__note"><?= e(__('relance.aged_balance_help')) ?></p>
        </div>
        <div class="cluster">
            <?php foreach (array_keys($buckets) as $key): ?>
                <span class="badge badge--neutral"><?= e(__('relance.bucket.' . $key)) ?> · <?= e(money_format_tnd((string) ($totals['buckets'][$key] ?? '0.000'))) ?></span>
            <?php endforeach; ?>
        </div>
    </div>

    <?php if ($clients === []): ?>
        <?php View::partial('partials/empty-state', [
            'title' => __('relance.empty_title'),
            'description' => __('relance.empty_help'),
            'actionLabel' => __('relance.open_documents'),
            'actionUrl' => url('/documents'),
        ]) ?>
    <?php else: ?>
        <div class="table-wrap">
            <table class="data-table">
                <thead>
                    <tr>
                        <th scope="col"><?= e(__('relance.client')) ?></th>
                        <th scope="col"><?= e(__('relance.invoices')) ?></th>
                        <th scope="col"><?= e(__('relance.outstanding')) ?></th>
                        <?php foreach (array_keys($buckets) as $key): ?>
                            <th scope="col"><?= e(__('relance.bucket.' . $key)) ?></th>
                        <?php endforeach; ?>
                        <th scope="col"><?= e(__('relance.last_relance')) ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($clients as $client): ?>
                        <tr>
                            <td>
                                <span class="table-primary__text">
                                    <strong class="table-primary__name"><?= e((string) $client['client_name']) ?></strong>
                                    <?php if ((bool) $client['is_excluded']): ?>
                                        <span class="badge badge--warning"><?= e(__('relance.excluded')) ?></span>
                                    <?php endif; ?>
                                    <span class="table-primary__meta"><?= e(__('relance.oldest_days', ['days' => (string) $client['oldest']])) ?></span>
                                </span>
                            </td>
                            <td class="u-numeric"><?= e((string) $client['invoices']) ?></td>
                            <td class="u-numeric"><strong><?= e(money_format_tnd((string) $client['total'])) ?></strong></td>
                            <?php foreach (array_keys($buckets) as $key): ?>
                                <td class="u-numeric"><?= e(money_format_tnd((string) $client['buckets'][$key])) ?></td>
                            <?php endforeach; ?>
                            <td><?= e($formatDate($client['last_relance_on'] ?? null)) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <p class="table-meta"><?= e(__('relance.aged_balance_note')) ?></p>
    <?php endif; ?>
</section>

<div class="content-grid">
    <section class="card stack-md" aria-labelledby="relance-next-title">
        <div class="card-header">
            <h2 class="card-header__title" id="relance-next-title"><?= e(__('relance.most_overdue')) ?></h2>
            <a class="button button--ghost button--small" href="<?= e(url('/relances/impayes')) ?>"><?= e(__('relance.see_all')) ?></a>
        </div>
        <?php if ($invoices === []): ?>
            <p class="muted"><?= e(__('relance.no_unpaid')) ?></p>
        <?php else: ?>
            <ul class="timeline">
                <?php foreach ($invoices as $invoice): ?>
                    <li class="timeline__item">
                        <div class="timeline__marker" aria-hidden="true"></div>
                        <div class="timeline__content">
                            <p class="timeline__title">
                                <a href="<?= e(url('/relances/factures/' . (int) $invoice['id'])) ?>">
                                    <?= e((string) ($invoice['document_number'] ?? $invoice['title'] ?? '')) ?> · <?= e((string) $invoice['client_name']) ?>
                                </a>
                            </p>
                            <p class="timeline__meta">
                                <?= e(__('relance.days_late', ['days' => (string) $invoice['days_late']])) ?>
                                · <?= e(money_format_tnd((string) $invoice['outstanding'])) ?>
                                <?php if (($invoice['last_relance_on'] ?? null) !== null): ?>
                                    · <?= e(__('relance.last_relance_on', ['date' => $formatDate((string) $invoice['last_relance_on'])])) ?>
                                <?php endif; ?>
                            </p>
                        </div>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </section>

    <section class="card stack-md" aria-labelledby="relance-history-title">
        <div class="card-header">
            <h2 class="card-header__title" id="relance-history-title"><?= e(__('relance.recent_events')) ?></h2>
            <a class="button button--ghost button--small" href="<?= e(url('/relances/historique')) ?>"><?= e(__('relance.see_history')) ?></a>
        </div>
        <?php if ($events === []): ?>
            <p class="muted"><?= e(__('relance.no_events')) ?></p>
        <?php else: ?>
            <ul class="timeline">
                <?php foreach ($events as $event): ?>
                    <li class="timeline__item">
                        <div class="timeline__marker" aria-hidden="true"></div>
                        <div class="timeline__content">
                            <p class="timeline__title"><?= e((string) $event['client_name']) ?> · <?= e((string) $event['document_number']) ?></p>
                            <p class="timeline__meta">
                                <?= e($formatDate((string) $event['sent_on'])) ?>
                                · <?= e(__('relance.tone_value.' . (string) $event['tone'])) ?>
                                · <?= e(__('relance.outcome_value.' . (string) $event['outcome'])) ?>
                            </p>
                        </div>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </section>
</div>

<section class="card stack-md" aria-labelledby="relance-rules-title">
    <div class="card-header">
        <div>
            <h2 class="card-header__title" id="relance-rules-title"><?= e(__('relance.rules_title')) ?></h2>
            <p class="section-heading__note"><?= e(__('relance.rules_summary', ['count' => (string) $rulesCount])) ?></p>
        </div>
        <a class="button button--secondary button--small" href="<?= e(url('/relances/regles')) ?>"><?= e(__('relance.manage_rules')) ?></a>
    </div>
    <p class="table-meta"><?= e(__('relance.rules_warning')) ?></p>
</section>
