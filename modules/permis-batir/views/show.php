<?php
declare(strict_types=1);

use App\Core\View;
use Modules\PermisBatir\Services\DossierService;

$dossier = is_array($dossier ?? null) ? $dossier : [];
$pieces = is_array($pieces ?? null) ? $pieces : [];
$events = is_array($events ?? null) ? $events : [];
$eventErrors = is_array($eventErrors ?? null) ? $eventErrors : [];
$eventValues = is_array($eventValues ?? null) ? $eventValues : [];
$status = (string) ($dossier['status'] ?? 'preparation');
$daysLeft = DossierService::daysLeft(isset($dossier['deadline_date']) ? (string) $dossier['deadline_date'] : null);
$deposited = count(array_filter($pieces, static fn (array $piece): bool => (int) $piece['is_deposited'] === 1));
$missingRequired = count(array_filter(
    $pieces,
    static fn (array $piece): bool => (int) $piece['is_required'] === 1 && (int) $piece['is_deposited'] === 0
));
$formatDate = static fn (?string $date): string => $date !== null && $date !== '' ? date('d/m/Y', strtotime($date)) : '—';
$pieceLabel = static function (array $piece): string {
    $code = (string) ($piece['code'] ?? '');
    $key = 'permis.pieces.' . $code;

    return $code !== '' && \App\Core\Translator::has($key) ? __($key) : (string) $piece['label'];
};
?>
<?php View::partial('partials/page-header', [
    'pageTitle' => (string) ($dossier['reference'] ?? ''),
    'eyebrow' => __('permis.dossier'),
    'subtitle' => (string) ($dossier['project_name'] ?? ''),
    'secondaryAction' => ['label' => __('permis.back'), 'icon' => 'arrow-left', 'url' => url('/permis')],
    'primaryAction' => !empty($canManage) ? ['label' => __('permis.edit'), 'icon' => 'edit', 'url' => url('/permis/' . (int) $dossier['id'] . '/edit')] : null,
]) ?>

<?php if (!empty($notice)): ?><p class="form-feedback form-feedback--success" role="status"><?= e($notice) ?></p><?php endif; ?>
<?php if (!empty($error)): ?><p class="form-feedback form-feedback--error" role="alert"><?= e($error) ?></p><?php endif; ?>

<div class="content-grid">
    <div class="stack-lg">
        <section class="card stack-md" aria-labelledby="permis-summary-title">
            <div class="card-header">
                <div>
                    <h2 class="card-header__title" id="permis-summary-title"><?= e(__('permis.summary')) ?></h2>
                    <p class="section-heading__note"><?= e(__('permis.summary_help')) ?></p>
                </div>
                <span class="badge badge--<?= e(DossierService::badgeVariant($status)) ?>"><?= e(__('permis.status.' . $status)) ?></span>
            </div>
            <dl class="detail-list">
                <div class="detail-list__item"><dt><?= e(__('permis.commune')) ?></dt><dd><?= e((string) ($dossier['commune'] ?? '')) ?></dd></div>
                <div class="detail-list__item"><dt><?= e(__('permis.project')) ?></dt><dd><a href="<?= e(url('/projects/' . (int) $dossier['project_id'])) ?>"><?= e((string) ($dossier['project_name'] ?? '')) ?></a></dd></div>
                <div class="detail-list__item"><dt><?= e(__('permis.client')) ?></dt><dd><?= e((string) ($dossier['client_name'] ?? '')) ?></dd></div>
                <div class="detail-list__item"><dt><?= e(__('permis.deposit_number')) ?></dt><dd><?= ($dossier['deposit_number'] ?? '') !== '' ? e((string) $dossier['deposit_number']) : '—' ?></dd></div>
                <div class="detail-list__item"><dt><?= e(__('permis.deposit_date')) ?></dt><dd><?= e($formatDate(isset($dossier['deposit_date']) ? (string) $dossier['deposit_date'] : null)) ?></dd></div>
                <div class="detail-list__item"><dt><?= e(__('permis.decision_number')) ?></dt><dd><?= ($dossier['decision_number'] ?? '') !== '' ? e((string) $dossier['decision_number']) : '—' ?></dd></div>
                <div class="detail-list__item"><dt><?= e(__('permis.decision_date')) ?></dt><dd><?= e($formatDate(isset($dossier['decision_date']) ? (string) $dossier['decision_date'] : null)) ?></dd></div>
                <div class="detail-list__item">
                    <dt><?= e(__('permis.deadline_date')) ?></dt>
                    <dd>
                        <?= e($formatDate(isset($dossier['deadline_date']) ? (string) $dossier['deadline_date'] : null)) ?>
                        <?php if ($daysLeft !== null && in_array($status, ['deposited', 'instruction', 'complement'], true)): ?>
                            <?php if ($daysLeft < 0): ?>
                                <span class="badge badge--error badge--plain"><?= e(__('permis.late_by', ['days' => (string) abs($daysLeft)])) ?></span>
                            <?php else: ?>
                                <span class="badge badge--neutral badge--plain"><?= e(__('permis.in_days', ['days' => (string) $daysLeft])) ?></span>
                            <?php endif; ?>
                        <?php endif; ?>
                    </dd>
                </div>
            </dl>
            <?php if (($dossier['notes'] ?? '') !== ''): ?>
                <div class="document-text-block">
                    <h3><?= e(__('permis.notes')) ?></h3>
                    <p><?= nl2br(e((string) $dossier['notes'])) ?></p>
                </div>
            <?php endif; ?>
        </section>

        <section class="card stack-md" id="pieces" aria-labelledby="permis-pieces-title">
            <div class="card-header">
                <div>
                    <h2 class="card-header__title" id="permis-pieces-title"><?= e(__('permis.pieces_title')) ?></h2>
                    <p class="section-heading__note"><?= e(__('permis.pieces_help', ['done' => (string) $deposited, 'total' => (string) count($pieces)])) ?></p>
                </div>
                <?php if ($missingRequired > 0): ?>
                    <span class="badge badge--warning"><?= e(__('permis.pieces_missing', ['count' => (string) $missingRequired])) ?></span>
                <?php else: ?>
                    <span class="badge badge--success"><?= e(__('permis.pieces_complete')) ?></span>
                <?php endif; ?>
            </div>
            <ul class="stack-md" role="list">
                <?php foreach ($pieces as $piece): ?>
                    <li class="cluster cluster--between">
                        <span>
                            <strong><?= e($pieceLabel($piece)) ?></strong>
                            <?php if ((int) $piece['is_required'] === 1): ?>
                                <span class="badge badge--neutral badge--plain"><?= e(__('permis.required')) ?></span>
                            <?php endif; ?>
                            <?php if (($piece['deposited_on'] ?? null) !== null): ?>
                                <span class="table-meta"><?= e(__('permis.deposited_on', ['date' => date('d/m/Y', strtotime((string) $piece['deposited_on']))])) ?></span>
                            <?php endif; ?>
                        </span>
                        <?php if (!empty($canManage)): ?>
                            <form method="post" action="<?= e(url('/permis/' . (int) $dossier['id'] . '/pieces/' . (int) $piece['id'])) ?>">
                                <?= csrf_field() ?>
                                <input type="hidden" name="deposited" value="<?= (int) $piece['is_deposited'] === 1 ? '0' : '1' ?>">
                                <button class="button button--small <?= (int) $piece['is_deposited'] === 1 ? 'button--secondary' : 'button--ghost' ?>" type="submit">
                                    <?php if ((int) $piece['is_deposited'] === 1): ?>
                                        <svg class="icon icon--small" aria-hidden="true"><use href="<?= e(asset('img/icons.svg')) ?>#check"></use></svg><?= e(__('permis.mark_missing')) ?>
                                    <?php else: ?>
                                        <?= e(__('permis.mark_deposited')) ?>
                                    <?php endif; ?>
                                </button>
                            </form>
                        <?php elseif ((int) $piece['is_deposited'] === 1): ?>
                            <span class="badge badge--success"><?= e(__('permis.deposited')) ?></span>
                        <?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ul>
        </section>

        <section class="card stack-md" id="history" aria-labelledby="permis-history-title">
            <div class="card-header">
                <div>
                    <h2 class="card-header__title" id="permis-history-title"><?= e(__('permis.history')) ?></h2>
                    <p class="section-heading__note"><?= e(__('permis.history_help')) ?></p>
                </div>
            </div>
            <?php if ($events === []): ?>
                <p class="muted"><?= e(__('permis.no_events')) ?></p>
            <?php else: ?>
                <ol class="timeline" role="list">
                    <?php foreach ($events as $event): ?>
                        <li class="timeline__item is-complete">
                            <span class="timeline__marker" aria-hidden="true"></span>
                            <div class="timeline__content">
                                <p class="timeline__title"><?= e((string) $event['summary']) ?></p>
                                <p class="timeline__meta">
                                    <?= e(date('d/m/Y', strtotime((string) $event['event_date']))) ?>
                                    · <?= e(__('permis.event.' . (string) $event['kind'])) ?>
                                    <?php if (($event['author_name'] ?? '') !== ''): ?> · <?= e((string) $event['author_name']) ?><?php endif; ?>
                                </p>
                                <?php if (($event['details'] ?? '') !== ''): ?><p class="table-meta"><?= nl2br(e((string) $event['details'])) ?></p><?php endif; ?>
                            </div>
                        </li>
                    <?php endforeach; ?>
                </ol>
            <?php endif; ?>

            <?php if (!empty($canManage)): ?>
                <form class="stack-md" method="post" action="<?= e(url('/permis/' . (int) $dossier['id'] . '/evenements')) ?>">
                    <?= csrf_field() ?>
                    <h3 class="section-heading__title"><?= e(__('permis.add_event')) ?></h3>
                    <div class="form-grid">
                        <div class="field">
                            <label class="field__label" for="permis-event-date"><?= e(__('permis.event_date')) ?></label>
                            <input class="field__control" type="date" id="permis-event-date" name="event_date" value="<?= e((string) ($eventValues['event_date'] ?? date('Y-m-d'))) ?>">
                            <?php if (!empty($eventErrors['event_date'])): ?><span class="field__hint field__hint--error"><?= e($eventErrors['event_date']) ?></span><?php endif; ?>
                        </div>
                        <div class="field">
                            <label class="field__label" for="permis-event-kind"><?= e(__('permis.event_kind')) ?></label>
                            <select class="field__control" id="permis-event-kind" name="kind">
                                <?php foreach ($eventKinds as $kind): ?>
                                    <option value="<?= e($kind) ?>" <?= (string) ($eventValues['kind'] ?? 'note') === $kind ? 'selected' : '' ?>><?= e(__('permis.event.' . $kind)) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="field form-grid__full">
                            <label class="field__label" for="permis-event-summary"><?= e(__('permis.event_summary')) ?></label>
                            <input class="field__control" type="text" id="permis-event-summary" name="summary" value="<?= e((string) ($eventValues['summary'] ?? '')) ?>" maxlength="180" required>
                            <?php if (!empty($eventErrors['summary'])): ?><span class="field__hint field__hint--error"><?= e($eventErrors['summary']) ?></span><?php endif; ?>
                        </div>
                        <div class="field form-grid__full">
                            <label class="field__label" for="permis-event-details"><?= e(__('permis.event_details')) ?></label>
                            <textarea class="field__control" id="permis-event-details" name="details" rows="3" maxlength="4000"><?= e((string) ($eventValues['details'] ?? '')) ?></textarea>
                        </div>
                    </div>
                    <div class="form-actions">
                        <button class="button button--primary" type="submit"><?= e(__('permis.event_submit')) ?></button>
                    </div>
                </form>
            <?php endif; ?>
        </section>
    </div>

    <aside class="stack-md" aria-label="<?= e(__('permis.progress')) ?>">
        <section class="card stack-md">
            <h2 class="card-header__title"><?= e(__('permis.progress')) ?></h2>
            <?php $percent = count($pieces) > 0 ? (int) round($deposited / count($pieces) * 100) : 0; ?>
            <div class="progress" role="progressbar" aria-valuenow="<?= e((string) $percent) ?>" aria-valuemin="0" aria-valuemax="100" aria-label="<?= e(__('permis.pieces_title')) ?>">
                <span class="progress__bar" style="--progress: <?= e((string) $percent) ?>%"></span>
            </div>
            <p class="table-meta"><?= e(__('permis.pieces_help', ['done' => (string) $deposited, 'total' => (string) count($pieces)])) ?></p>
        </section>
        <section class="card stack-md">
            <h2 class="card-header__title"><?= e(__('permis.shortcuts')) ?></h2>
            <a class="button button--secondary" href="<?= e(url('/projects/' . (int) $dossier['project_id'])) ?>"><?= e(__('permis.open_project')) ?></a>
            <a class="button button--ghost" href="<?= e(url('/chantier/' . (int) $dossier['project_id'])) ?>"><?= e(__('permis.open_site')) ?></a>
            <a class="button button--ghost" href="<?= e(url('/permis')) ?>"><?= e(__('permis.back_to_list')) ?></a>
        </section>
    </aside>
</div>
