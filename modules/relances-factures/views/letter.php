<?php
declare(strict_types=1);

use App\Core\View;

$invoice = is_array($invoice ?? null) ? $invoice : [];
$letter = is_array($letter ?? null) ? $letter : ['subject' => '', 'body' => '', 'is_custom' => false];
$events = is_array($events ?? null) ? $events : [];
$tones = is_array($tones ?? null) ? $tones : [];
$channels = is_array($channels ?? null) ? $channels : [];
$outcomes = is_array($outcomes ?? null) ? $outcomes : [];
$tone = (string) ($tone ?? 'courtesy');
$eventErrors = is_array($eventErrors ?? null) ? $eventErrors : [];
$eventValues = is_array($eventValues ?? null) ? $eventValues : [];
$invoiceId = (int) ($invoice['id'] ?? 0);

$formatDate = static function (?string $value): string {
    if ($value === null || $value === '') {
        return '—';
    }
    $time = strtotime($value);

    return $time === false ? '—' : date('d/m/Y', $time);
};
$copyText = __('relance.letter_subject') . ' : ' . (string) $letter['subject'] . "\n\n" . (string) $letter['body'];
?>
<?php View::partial('partials/page-header', [
    'pageTitle' => __('relance.letter_title'),
    'eyebrow' => (string) ($invoice['client_name'] ?? ''),
    'subtitle' => __('relance.letter_intro', [
        'invoice' => (string) ($invoice['document_number'] ?? ''),
        'amount' => money_format_tnd((string) ($invoice['outstanding'] ?? '0.000')),
    ]),
    'secondaryAction' => ['label' => __('relance.back_to_queue'), 'icon' => 'arrow-left', 'url' => url('/relances/impayes')],
]) ?>

<?php if (!empty($notice)): ?><p class="form-feedback form-feedback--success" role="status"><?= e($notice) ?></p><?php endif; ?>
<?php if (!empty($error)): ?><p class="form-feedback form-feedback--error" role="alert"><?= e($error) ?></p><?php endif; ?>

<?php if ((bool) ($invoice['is_excluded'] ?? false)): ?>
    <p class="table-meta" role="note">
        <span class="badge badge--warning"><?= e(__('relance.excluded')) ?></span>
        <?= e(__('relance.excluded_reason', ['reason' => (string) ($invoice['exclusion_reason'] ?? '—')])) ?>
    </p>
<?php endif; ?>

<section class="card stack-md no-print" aria-label="<?= e(__('relance.tone')) ?>">
    <div class="cluster">
        <span class="detail-label"><?= e(__('relance.tone')) ?></span>
        <?php foreach ($tones as $option): ?>
            <a class="button button--small <?= $option === $tone ? 'button--primary' : 'button--secondary' ?>"
               href="<?= e(url('/relances/factures/' . $invoiceId . '?tone=' . rawurlencode($option))) ?>">
                <?= e(__('relance.tone_value.' . $option)) ?>
            </a>
        <?php endforeach; ?>
        <span class="badge badge--neutral">
            <?= e((bool) $letter['is_custom'] ? __('relance.custom_model') : __('relance.default_model')) ?>
        </span>
    </div>
    <p class="table-meta"><?= e(__('relance.tone_help')) ?></p>
</section>

<div class="content-grid">
    <section class="card stack-md" aria-labelledby="relance-letter-heading">
        <div class="card-header">
            <div>
                <h2 class="card-header__title" id="relance-letter-heading"><?= e(__('relance.letter_card')) ?></h2>
                <p class="section-heading__note"><?= e(__('relance.letter_help')) ?></p>
            </div>
            <div class="cluster no-print">
                <button class="button button--small button--secondary" type="button" data-copy="#relance-letter-copy">
                    <svg class="icon icon--small" aria-hidden="true"><use href="<?= e(asset('img/icons.svg')) ?>#copy"></use></svg><?= e(__('relance.copy')) ?>
                </button>
                <button class="button button--small button--secondary" type="button" data-print>
                    <svg class="icon icon--small" aria-hidden="true"><use href="<?= e(asset('img/icons.svg')) ?>#printer"></use></svg><?= e(__('relance.print')) ?>
                </button>
            </div>
        </div>

        <div class="document-text-block">
            <p class="detail-label"><?= e(__('relance.letter_subject')) ?></p>
            <p><strong><?= e((string) $letter['subject']) ?></strong></p>
            <p><?= nl2br(e((string) $letter['body'])) ?></p>
        </div>

        <textarea class="u-sr-only" id="relance-letter-copy" readonly aria-hidden="true" tabindex="-1"><?= e($copyText) ?></textarea>
        <p class="table-meta"><?= e(__('relance.no_mail_notice')) ?></p>
    </section>

    <section class="card stack-md" aria-labelledby="relance-invoice-heading">
        <h2 class="card-header__title" id="relance-invoice-heading"><?= e(__('relance.invoice')) ?></h2>
        <dl class="detail-list">
            <div class="detail-list__item"><dt><?= e(__('relance.client')) ?></dt><dd><?= e((string) ($invoice['client_name'] ?? '')) ?></dd></div>
            <div class="detail-list__item"><dt><?= e(__('relance.project')) ?></dt><dd><?= e((string) ($invoice['project_name'] ?? '—')) ?></dd></div>
            <div class="detail-list__item"><dt><?= e(__('relance.invoice_number')) ?></dt><dd><?= e((string) ($invoice['document_number'] ?? '—')) ?></dd></div>
            <div class="detail-list__item"><dt><?= e(__('relance.issue_date')) ?></dt><dd><?= e($formatDate($invoice['issue_date'] ?? null)) ?></dd></div>
            <div class="detail-list__item"><dt><?= e(__('relance.due_date')) ?></dt><dd><?= e($formatDate($invoice['due_date'] ?? null)) ?></dd></div>
            <div class="detail-list__item"><dt><?= e(__('relance.delay')) ?></dt><dd><?= e(__('relance.days_late', ['days' => (string) ($invoice['days_late'] ?? 0)])) ?></dd></div>
            <div class="detail-list__item"><dt><?= e(__('relance.invoiced')) ?></dt><dd class="u-numeric"><?= e(money_format_tnd((string) ($invoice['total_amount'] ?? '0.000'))) ?></dd></div>
            <div class="detail-list__item"><dt><?= e(__('relance.paid')) ?></dt><dd class="u-numeric"><?= e(money_format_tnd((string) ($invoice['paid_amount'] ?? '0.000'))) ?></dd></div>
            <div class="detail-list__item"><dt><?= e(__('relance.outstanding')) ?></dt><dd class="u-numeric"><strong><?= e(money_format_tnd((string) ($invoice['outstanding'] ?? '0.000'))) ?></strong></dd></div>
        </dl>

        <?php if (!empty($canManage)): ?>
            <form method="post" action="<?= e(url('/relances/clients/' . (int) ($invoice['client_id'] ?? 0) . '/exclusion')) ?>" class="stack-md no-print">
                <?= csrf_field() ?>
                <input type="hidden" name="back" value="<?= e('/relances/factures/' . $invoiceId) ?>">
                <?php if ((bool) ($invoice['is_excluded'] ?? false)): ?>
                    <input type="hidden" name="action" value="include">
                    <button class="button button--small button--secondary" type="submit"><?= e(__('relance.include_client')) ?></button>
                <?php else: ?>
                    <input type="hidden" name="action" value="exclude">
                    <div class="field">
                        <label class="field__label" for="relance-exclusion-reason"><?= e(__('relance.exclusion_reason')) ?></label>
                        <input class="field__control" type="text" id="relance-exclusion-reason" name="reason" maxlength="255"
                               placeholder="<?= e(__('relance.exclusion_placeholder')) ?>">
                    </div>
                    <button class="button button--small button--ghost" type="submit"><?= e(__('relance.exclude_client')) ?></button>
                <?php endif; ?>
            </form>
        <?php endif; ?>
    </section>
</div>

<section class="card stack-md" aria-labelledby="relance-record-title">
    <div class="card-header">
        <div>
            <h2 class="card-header__title" id="relance-record-title"><?= e(__('relance.record_title')) ?></h2>
            <p class="section-heading__note"><?= e(__('relance.record_help')) ?></p>
        </div>
        <a class="button button--ghost button--small" href="<?= e(url('/relances/historique?q=' . rawurlencode((string) ($invoice['document_number'] ?? '')))) ?>"><?= e(__('relance.see_history')) ?></a>
    </div>

    <?php if (!empty($eventErrors)): ?>
        <p class="form-feedback form-feedback--error" role="alert"><?= e(__('relance.fix_errors')) ?></p>
    <?php endif; ?>

    <?php if (!empty($canManage)): ?>
        <form class="form-grid no-print" method="post" action="<?= e(url('/relances/factures/' . $invoiceId)) ?>">
            <?= csrf_field() ?>
            <div class="field">
                <label class="field__label" for="relance-event-tone"><?= e(__('relance.tone')) ?></label>
                <select class="field__control" id="relance-event-tone" name="tone">
                    <?php foreach ($tones as $option): ?>
                        <option value="<?= e($option) ?>" <?= (string) ($eventValues['tone'] ?? $tone) === $option ? 'selected' : '' ?>><?= e(__('relance.tone_value.' . $option)) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="field">
                <label class="field__label" for="relance-event-channel"><?= e(__('relance.channel')) ?></label>
                <select class="field__control" id="relance-event-channel" name="channel">
                    <?php foreach ($channels as $option): ?>
                        <option value="<?= e($option) ?>" <?= (string) ($eventValues['channel'] ?? 'letter') === $option ? 'selected' : '' ?>><?= e(__('relance.channel_value.' . $option)) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="field">
                <label class="field__label" for="relance-event-sent"><?= e(__('relance.sent_on')) ?></label>
                <input class="field__control" type="date" id="relance-event-sent" name="sent_on"
                       value="<?= e((string) ($eventValues['sent_on'] ?? date('Y-m-d'))) ?>">
                <?php if (!empty($eventErrors['sent_on'])): ?><span class="field__hint field__hint--error"><?= e($eventErrors['sent_on']) ?></span><?php endif; ?>
            </div>
            <div class="field">
                <label class="field__label" for="relance-event-outcome"><?= e(__('relance.outcome')) ?></label>
                <select class="field__control" id="relance-event-outcome" name="outcome">
                    <?php foreach ($outcomes as $option): ?>
                        <option value="<?= e($option) ?>" <?= (string) ($eventValues['outcome'] ?? 'sent') === $option ? 'selected' : '' ?>><?= e(__('relance.outcome_value.' . $option)) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="field">
                <label class="field__label" for="relance-event-promise-date"><?= e(__('relance.promise_on')) ?></label>
                <input class="field__control" type="date" id="relance-event-promise-date" name="promise_on"
                       value="<?= e((string) ($eventValues['promise_on'] ?? '')) ?>">
                <?php if (!empty($eventErrors['promise_on'])): ?><span class="field__hint field__hint--error"><?= e($eventErrors['promise_on']) ?></span><?php endif; ?>
            </div>
            <div class="field">
                <label class="field__label" for="relance-event-promise-amount"><?= e(__('relance.promise_amount')) ?></label>
                <input class="field__control" type="text" inputmode="decimal" id="relance-event-promise-amount" name="promise_amount"
                       value="<?= e((string) ($eventValues['promise_amount'] ?? '')) ?>" placeholder="0.000">
                <?php if (!empty($eventErrors['promise_amount'])): ?><span class="field__hint field__hint--error"><?= e($eventErrors['promise_amount']) ?></span><?php endif; ?>
            </div>
            <div class="field form-grid__full">
                <label class="field__label" for="relance-event-note"><?= e(__('relance.note')) ?></label>
                <input class="field__control" type="text" id="relance-event-note" name="note" maxlength="500"
                       value="<?= e((string) ($eventValues['note'] ?? '')) ?>">
                <?php if (!empty($eventErrors['note'])): ?><span class="field__hint field__hint--error"><?= e($eventErrors['note']) ?></span><?php endif; ?>
            </div>
            <div class="form-actions form-grid__full">
                <button class="button button--primary" type="submit"><?= e(__('relance.record_submit')) ?></button>
            </div>
        </form>
    <?php endif; ?>

    <?php if ($events === []): ?>
        <p class="muted"><?= e(__('relance.no_invoice_events')) ?></p>
    <?php else: ?>
        <div class="table-wrap">
            <table class="data-table">
                <thead>
                    <tr>
                        <th scope="col"><?= e(__('relance.sent_on')) ?></th>
                        <th scope="col"><?= e(__('relance.tone')) ?></th>
                        <th scope="col"><?= e(__('relance.channel')) ?></th>
                        <th scope="col"><?= e(__('relance.outcome')) ?></th>
                        <th scope="col"><?= e(__('relance.promise_on')) ?></th>
                        <th scope="col"><?= e(__('relance.note')) ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($events as $event): ?>
                        <tr>
                            <td><?= e($formatDate((string) $event['sent_on'])) ?></td>
                            <td><?= e(__('relance.tone_value.' . (string) $event['tone'])) ?></td>
                            <td><?= e(__('relance.channel_value.' . (string) $event['channel'])) ?></td>
                            <td><?= e(__('relance.outcome_value.' . (string) $event['outcome'])) ?></td>
                            <td><?= e($formatDate($event['promise_on'] ?? null)) ?><?= ($event['promise_amount'] ?? null) !== null ? ' · ' . e(money_format_tnd((string) $event['promise_amount'])) : '' ?></td>
                            <td><?= e((string) ($event['note'] ?? '—')) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>
