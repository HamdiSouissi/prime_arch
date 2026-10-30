<?php
declare(strict_types=1);

use App\Core\View;

$events = is_array($events ?? null) ? $events : [];
$filters = is_array($filters ?? null) ? $filters : ['q' => ''];

$formatDate = static function (?string $value): string {
    if ($value === null || $value === '') {
        return '—';
    }
    $time = strtotime($value);

    return $time === false ? '—' : date('d/m/Y', $time);
};
?>
<?php View::partial('partials/page-header', [
    'pageTitle' => __('relance.history_title'),
    'eyebrow' => __('relance.eyebrow'),
    'subtitle' => __('relance.history_intro'),
    'secondaryAction' => ['label' => __('relance.back'), 'icon' => 'arrow-left', 'url' => url('/relances')],
]) ?>

<?php if (!empty($notice)): ?><p class="form-feedback form-feedback--success" role="status"><?= e($notice) ?></p><?php endif; ?>
<?php if (!empty($error)): ?><p class="form-feedback form-feedback--error" role="alert"><?= e($error) ?></p><?php endif; ?>

<section class="card stack-md" aria-label="<?= e(__('relance.history_title')) ?>">
    <form class="filter-row" method="get" action="<?= e(url('/relances/historique')) ?>">
        <div class="input-with-icon">
            <svg class="icon icon--small" aria-hidden="true"><use href="<?= e(asset('img/icons.svg')) ?>#search"></use></svg>
            <input class="field__control" type="search" name="q" value="<?= e((string) $filters['q']) ?>"
                   placeholder="<?= e(__('relance.search_placeholder')) ?>" aria-label="<?= e(__('common.search')) ?>">
        </div>
        <button class="button button--secondary" type="submit">
            <svg class="icon icon--small" aria-hidden="true"><use href="<?= e(asset('img/icons.svg')) ?>#filter"></use></svg><?= e(__('common.filter')) ?>
        </button>
        <?php if ($filters['q'] !== ''): ?>
            <a class="button button--ghost" href="<?= e(url('/relances/historique')) ?>"><?= e(__('common.clear')) ?></a>
        <?php endif; ?>
    </form>

    <?php if ($events === []): ?>
        <?php View::partial('partials/empty-state', [
            'title' => __('relance.history_empty_title'),
            'description' => __('relance.history_empty_help'),
            'actionLabel' => __('relance.open_queue'),
            'actionUrl' => url('/relances/impayes'),
        ]) ?>
    <?php else: ?>
        <div class="table-wrap">
            <table class="data-table">
                <thead>
                    <tr>
                        <th scope="col"><?= e(__('relance.sent_on')) ?></th>
                        <th scope="col"><?= e(__('relance.client')) ?></th>
                        <th scope="col"><?= e(__('relance.invoice')) ?></th>
                        <th scope="col"><?= e(__('relance.tone')) ?></th>
                        <th scope="col"><?= e(__('relance.channel')) ?></th>
                        <th scope="col"><?= e(__('relance.outcome')) ?></th>
                        <th scope="col"><?= e(__('relance.promise_on')) ?></th>
                        <th scope="col"><?= e(__('relance.author')) ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($events as $event): ?>
                        <tr>
                            <td><?= e($formatDate((string) $event['sent_on'])) ?></td>
                            <td><?= e((string) $event['client_name']) ?></td>
                            <td>
                                <a class="table-primary" href="<?= e(url('/relances/factures/' . (int) $event['document_id'])) ?>">
                                    <span class="table-primary__text">
                                        <strong class="table-primary__name"><?= e((string) ($event['document_number'] ?? '—')) ?></strong>
                                        <?php if (($event['note'] ?? '') !== ''): ?>
                                            <span class="table-primary__meta"><?= e((string) $event['note']) ?></span>
                                        <?php endif; ?>
                                    </span>
                                </a>
                            </td>
                            <td><?= e(__('relance.tone_value.' . (string) $event['tone'])) ?></td>
                            <td><?= e(__('relance.channel_value.' . (string) $event['channel'])) ?></td>
                            <td><?= e(__('relance.outcome_value.' . (string) $event['outcome'])) ?></td>
                            <td>
                                <?= e($formatDate($event['promise_on'] ?? null)) ?>
                                <?php if (($event['promise_amount'] ?? null) !== null): ?>
                                    <span class="table-meta"><?= e(money_format_tnd((string) $event['promise_amount'])) ?></span>
                                <?php endif; ?>
                            </td>
                            <td><?= e((string) ($event['author_name'] ?? '—')) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <p class="table-meta"><?= e(__('relance.history_note')) ?></p>
    <?php endif; ?>
</section>
