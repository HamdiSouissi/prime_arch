<?php
declare(strict_types=1);

use App\Core\View;
use Modules\SuiviChantier\Services\ChantierService;

$project = is_array($project ?? null) ? $project : [];
$visits = is_array($visits ?? null) ? $visits : [];
$reserves = is_array($reserves ?? null) ? $reserves : [];
$receptions = is_array($receptions ?? null) ? $receptions : [];
$visitErrors = is_array($visitErrors ?? null) ? $visitErrors : [];
$visitValues = is_array($visitValues ?? null) ? $visitValues : [];
$reserveErrors = is_array($reserveErrors ?? null) ? $reserveErrors : [];
$reserveValues = is_array($reserveValues ?? null) ? $reserveValues : [];
$receptionErrors = is_array($receptionErrors ?? null) ? $receptionErrors : [];
$receptionValues = is_array($receptionValues ?? null) ? $receptionValues : [];
$openReserves = count(array_filter($reserves, static fn (array $reserve): bool => (string) $reserve['status'] === 'open'));
?>
<?php View::partial('partials/page-header', [
    'pageTitle' => (string) ($project['name'] ?? ''),
    'eyebrow' => __('chantier.title'),
    'subtitle' => __('chantier.show_intro', ['client' => (string) ($project['client_name'] ?? '')]),
    'secondaryAction' => ['label' => __('chantier.back'), 'icon' => 'arrow-left', 'url' => url('/chantier')],
    'primaryAction' => ['label' => __('chantier.open_project'), 'icon' => 'arrow-up-right', 'url' => url('/projects/' . (int) ($project['id'] ?? 0))],
]) ?>

<?php if (!empty($notice)): ?><p class="form-feedback form-feedback--success" role="status"><?= e($notice) ?></p><?php endif; ?>
<?php if (!empty($error)): ?><p class="form-feedback form-feedback--error" role="alert"><?= e($error) ?></p><?php endif; ?>

<div class="content-grid">
    <div class="stack-lg">
        <section class="card stack-md" id="journal" aria-labelledby="chantier-journal-title">
            <div class="card-header">
                <div>
                    <h2 class="card-header__title" id="chantier-journal-title"><?= e(__('chantier.journal')) ?></h2>
                    <p class="section-heading__note"><?= e(__('chantier.journal_help')) ?></p>
                </div>
                <span class="badge badge--neutral"><?= e(__('chantier.visit_count', ['count' => (string) count($visits)])) ?></span>
            </div>

            <?php if ($visits === []): ?>
                <p class="muted"><?= e(__('chantier.no_visits')) ?></p>
            <?php else: ?>
                <ol class="timeline" role="list">
                    <?php foreach ($visits as $visit): ?>
                        <li class="timeline__item is-complete">
                            <span class="timeline__marker" aria-hidden="true"></span>
                            <div class="timeline__content">
                                <p class="timeline__title">
                                    <?= e(date('d/m/Y', strtotime((string) $visit['visit_date']))) ?>
                                    · <?= e(__('chantier.weather.' . (string) $visit['weather'])) ?>
                                    <?php if (($visit['workforce'] ?? null) !== null): ?> · <?= e(__('chantier.workforce_value', ['count' => (string) $visit['workforce']])) ?><?php endif; ?>
                                </p>
                                <p class="timeline__meta">
                                    <?php if (($visit['author_name'] ?? '') !== ''): ?><?= e((string) $visit['author_name']) ?> · <?php endif; ?>
                                    <?= e(__('chantier.reserve_count', ['count' => (string) $visit['reserves_count']])) ?>
                                </p>
                                <p class="table-meta"><?= nl2br(e((string) $visit['observations'])) ?></p>
                                <?php if (!empty($canManage)): ?>
                                    <form method="post" action="<?= e(url('/chantier/' . (int) $project['id'] . '/visites/' . (int) $visit['id'] . '/delete')) ?>" data-confirm="<?= e(__('chantier.confirm_delete_visit')) ?>">
                                        <?= csrf_field() ?>
                                        <button class="button button--small button--ghost" type="submit">
                                            <svg class="icon icon--small" aria-hidden="true"><use href="<?= e(asset('img/icons.svg')) ?>#trash"></use></svg><?= e(__('common.delete')) ?>
                                        </button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </li>
                    <?php endforeach; ?>
                </ol>
            <?php endif; ?>

            <?php if (!empty($canManage)): ?>
                <form class="stack-md" method="post" action="<?= e(url('/chantier/' . (int) $project['id'] . '/visites')) ?>">
                    <?= csrf_field() ?>
                    <h3 class="section-heading__title"><?= e(__('chantier.add_visit')) ?></h3>
                    <?php if (!empty($visitErrors)): ?><p class="form-feedback form-feedback--error" role="alert"><?= e(__('chantier.fix_errors')) ?></p><?php endif; ?>
                    <div class="form-grid">
                        <div class="field">
                            <label class="field__label" for="chantier-visit-date"><?= e(__('chantier.visit_date')) ?></label>
                            <input class="field__control" type="date" id="chantier-visit-date" name="visit_date" value="<?= e((string) ($visitValues['visit_date'] ?? date('Y-m-d'))) ?>">
                            <?php if (!empty($visitErrors['visit_date'])): ?><span class="field__hint field__hint--error"><?= e($visitErrors['visit_date']) ?></span><?php endif; ?>
                        </div>
                        <div class="field">
                            <label class="field__label" for="chantier-visit-weather"><?= e(__('chantier.weather.label')) ?></label>
                            <select class="field__control" id="chantier-visit-weather" name="weather">
                                <?php foreach ($weathers as $weather): ?>
                                    <option value="<?= e($weather) ?>" <?= (string) ($visitValues['weather'] ?? 'clear') === $weather ? 'selected' : '' ?>><?= e(__('chantier.weather.' . $weather)) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="field">
                            <label class="field__label" for="chantier-visit-workforce"><?= e(__('chantier.workforce')) ?></label>
                            <input class="field__control" type="number" min="0" max="9999" id="chantier-visit-workforce" name="workforce" value="<?= e((string) ($visitValues['workforce'] ?? '')) ?>" inputmode="numeric">
                            <?php if (!empty($visitErrors['workforce'])): ?><span class="field__hint field__hint--error"><?= e($visitErrors['workforce']) ?></span><?php endif; ?>
                        </div>
                        <div class="field form-grid__full">
                            <label class="field__label" for="chantier-visit-observations"><?= e(__('chantier.observations')) ?></label>
                            <textarea class="field__control" id="chantier-visit-observations" name="observations" rows="3" maxlength="4000" required><?= e((string) ($visitValues['observations'] ?? '')) ?></textarea>
                            <?php if (!empty($visitErrors['observations'])): ?><span class="field__hint field__hint--error"><?= e($visitErrors['observations']) ?></span><?php endif; ?>
                        </div>
                    </div>
                    <div class="form-actions">
                        <button class="button button--primary" type="submit"><?= e(__('chantier.add_visit_submit')) ?></button>
                    </div>
                </form>
            <?php endif; ?>
        </section>

        <section class="card stack-md" id="reserves" aria-labelledby="chantier-reserves-title">
            <div class="card-header">
                <div>
                    <h2 class="card-header__title" id="chantier-reserves-title"><?= e(__('chantier.reserves')) ?></h2>
                    <p class="section-heading__note"><?= e(__('chantier.reserves_help')) ?></p>
                </div>
                <?php if ($openReserves > 0): ?>
                    <span class="badge badge--warning"><?= e(__('chantier.open_count', ['count' => (string) $openReserves])) ?></span>
                <?php else: ?>
                    <span class="badge badge--success"><?= e(__('chantier.all_lifted')) ?></span>
                <?php endif; ?>
            </div>

            <?php if ($reserves === []): ?>
                <p class="muted"><?= e(__('chantier.no_reserves')) ?></p>
            <?php else: ?>
                <div class="table-wrap">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th scope="col"><?= e(__('chantier.reference')) ?></th>
                                <th scope="col"><?= e(__('chantier.description')) ?></th>
                                <th scope="col"><?= e(__('chantier.severity')) ?></th>
                                <th scope="col"><?= e(__('chantier.opened_on')) ?></th>
                                <th scope="col"><?= e(__('chantier.reserve_status')) ?></th>
                                <th scope="col"><?= e(__('common.actions')) ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($reserves as $reserve): ?>
                                <?php $status = (string) $reserve['status']; ?>
                                <tr>
                                    <td><strong><?= e((string) $reserve['reference']) ?></strong></td>
                                    <td>
                                        <?= e((string) $reserve['description']) ?>
                                        <?php if (($reserve['lift_note'] ?? '') !== ''): ?><span class="table-meta"><?= e((string) $reserve['lift_note']) ?></span><?php endif; ?>
                                    </td>
                                    <td><span class="badge badge--<?= e(ChantierService::severityVariant((string) $reserve['severity'])) ?>"><?= e(__('chantier.severity_value.' . (string) $reserve['severity'])) ?></span></td>
                                    <td>
                                        <?= e(date('d/m/Y', strtotime((string) $reserve['opened_on']))) ?>
                                        <?php if ($status === 'open'): ?>
                                            <span class="table-meta"><?= e(__('chantier.open_for', ['days' => (string) ChantierService::openDays((string) $reserve['opened_on'])])) ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="badge badge--<?= e(ChantierService::reserveVariant($status)) ?>"><?= e(__('chantier.reserve_status_value.' . $status)) ?></span>
                                        <?php if (($reserve['lifted_on'] ?? null) !== null): ?><span class="table-meta"><?= e(date('d/m/Y', strtotime((string) $reserve['lifted_on']))) ?></span><?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if (!empty($canManage)): ?>
                                            <div class="cluster">
                                                <?php if ($status !== 'lifted'): ?>
                                                    <form method="post" action="<?= e(url('/chantier/' . (int) $project['id'] . '/reserves/' . (int) $reserve['id'])) ?>">
                                                        <?= csrf_field() ?>
                                                        <input type="hidden" name="status" value="lifted">
                                                        <button class="button button--small button--secondary" type="submit"><?= e(__('chantier.mark_lifted')) ?></button>
                                                    </form>
                                                <?php endif; ?>
                                                <?php if ($status !== 'contested'): ?>
                                                    <form method="post" action="<?= e(url('/chantier/' . (int) $project['id'] . '/reserves/' . (int) $reserve['id'])) ?>">
                                                        <?= csrf_field() ?>
                                                        <input type="hidden" name="status" value="contested">
                                                        <button class="button button--small button--ghost" type="submit"><?= e(__('chantier.mark_contested')) ?></button>
                                                    </form>
                                                <?php endif; ?>
                                                <?php if ($status !== 'open'): ?>
                                                    <form method="post" action="<?= e(url('/chantier/' . (int) $project['id'] . '/reserves/' . (int) $reserve['id'])) ?>">
                                                        <?= csrf_field() ?>
                                                        <input type="hidden" name="status" value="open">
                                                        <button class="button button--small button--ghost" type="submit"><?= e(__('chantier.reopen')) ?></button>
                                                    </form>
                                                <?php endif; ?>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>

            <?php if (!empty($canManage)): ?>
                <form class="stack-md" method="post" action="<?= e(url('/chantier/' . (int) $project['id'] . '/reserves')) ?>">
                    <?= csrf_field() ?>
                    <h3 class="section-heading__title"><?= e(__('chantier.add_reserve')) ?></h3>
                    <?php if (!empty($reserveErrors)): ?><p class="form-feedback form-feedback--error" role="alert"><?= e(__('chantier.fix_errors')) ?></p><?php endif; ?>
                    <div class="form-grid">
                        <div class="field form-grid__full">
                            <label class="field__label" for="chantier-reserve-description"><?= e(__('chantier.description')) ?></label>
                            <textarea class="field__control" id="chantier-reserve-description" name="description" rows="2" maxlength="2000" required><?= e((string) ($reserveValues['description'] ?? '')) ?></textarea>
                            <?php if (!empty($reserveErrors['description'])): ?><span class="field__hint field__hint--error"><?= e($reserveErrors['description']) ?></span><?php endif; ?>
                        </div>
                        <div class="field">
                            <label class="field__label" for="chantier-reserve-severity"><?= e(__('chantier.severity')) ?></label>
                            <select class="field__control" id="chantier-reserve-severity" name="severity">
                                <?php foreach ($severities as $severity): ?>
                                    <option value="<?= e($severity) ?>" <?= (string) ($reserveValues['severity'] ?? 'minor') === $severity ? 'selected' : '' ?>><?= e(__('chantier.severity_value.' . $severity)) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="field">
                            <label class="field__label" for="chantier-reserve-date"><?= e(__('chantier.opened_on')) ?></label>
                            <input class="field__control" type="date" id="chantier-reserve-date" name="opened_on" value="<?= e((string) ($reserveValues['opened_on'] ?? date('Y-m-d'))) ?>">
                            <?php if (!empty($reserveErrors['opened_on'])): ?><span class="field__hint field__hint--error"><?= e($reserveErrors['opened_on']) ?></span><?php endif; ?>
                        </div>
                        <?php if ($visits !== []): ?>
                            <div class="field">
                                <label class="field__label" for="chantier-reserve-visit"><?= e(__('chantier.related_visit')) ?></label>
                                <select class="field__control" id="chantier-reserve-visit" name="visit_id">
                                    <option value=""><?= e(__('chantier.no_visit')) ?></option>
                                    <?php foreach ($visits as $visit): ?>
                                        <option value="<?= e((string) $visit['id']) ?>" <?= (string) ($reserveValues['visit_id'] ?? '') === (string) $visit['id'] ? 'selected' : '' ?>><?= e(date('d/m/Y', strtotime((string) $visit['visit_date']))) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        <?php endif; ?>
                    </div>
                    <div class="form-actions">
                        <button class="button button--primary" type="submit"><?= e(__('chantier.add_reserve_submit')) ?></button>
                    </div>
                </form>
            <?php endif; ?>
        </section>

        <section class="card stack-md" id="receptions" aria-labelledby="chantier-receptions-title">
            <div class="card-header">
                <div>
                    <h2 class="card-header__title" id="chantier-receptions-title"><?= e(__('chantier.receptions')) ?></h2>
                    <p class="section-heading__note"><?= e(__('chantier.receptions_help')) ?></p>
                </div>
            </div>

            <?php if ($receptions === []): ?>
                <p class="muted"><?= e(__('chantier.no_receptions')) ?></p>
            <?php else: ?>
                <ul class="stack-md" role="list">
                    <?php foreach ($receptions as $reception): ?>
                        <li class="cluster cluster--between">
                            <span>
                                <strong><?= e(__('chantier.kind.' . (string) $reception['kind'])) ?></strong>
                                <span class="table-meta"><?= e(date('d/m/Y', strtotime((string) $reception['reception_date']))) ?></span>
                            </span>
                            <a class="button button--small button--secondary" href="<?= e(url('/chantier/' . (int) $project['id'] . '/receptions/' . (int) $reception['id'])) ?>">
                                <svg class="icon icon--small" aria-hidden="true"><use href="<?= e(asset('img/icons.svg')) ?>#printer"></use></svg><?= e(__('chantier.open_pv')) ?>
                            </a>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>

            <?php if (!empty($canManage)): ?>
                <form class="stack-md" method="post" action="<?= e(url('/chantier/' . (int) $project['id'] . '/receptions')) ?>">
                    <?= csrf_field() ?>
                    <h3 class="section-heading__title"><?= e(__('chantier.add_reception')) ?></h3>
                    <?php if (!empty($receptionErrors)): ?><p class="form-feedback form-feedback--error" role="alert"><?= e(__('chantier.fix_errors')) ?></p><?php endif; ?>
                    <div class="form-grid">
                        <div class="field">
                            <label class="field__label" for="chantier-reception-kind"><?= e(__('chantier.kind.label')) ?></label>
                            <select class="field__control" id="chantier-reception-kind" name="kind">
                                <option value="provisional" <?= (string) ($receptionValues['kind'] ?? 'provisional') === 'provisional' ? 'selected' : '' ?>><?= e(__('chantier.kind.provisional')) ?></option>
                                <option value="final" <?= (string) ($receptionValues['kind'] ?? '') === 'final' ? 'selected' : '' ?>><?= e(__('chantier.kind.final')) ?></option>
                            </select>
                        </div>
                        <div class="field">
                            <label class="field__label" for="chantier-reception-date"><?= e(__('chantier.reception_date')) ?></label>
                            <input class="field__control" type="date" id="chantier-reception-date" name="reception_date" value="<?= e((string) ($receptionValues['reception_date'] ?? date('Y-m-d'))) ?>">
                            <?php if (!empty($receptionErrors['reception_date'])): ?><span class="field__hint field__hint--error"><?= e($receptionErrors['reception_date']) ?></span><?php endif; ?>
                        </div>
                        <div class="field form-grid__full">
                            <label class="field__label" for="chantier-reception-notes"><?= e(__('chantier.reception_notes')) ?></label>
                            <textarea class="field__control" id="chantier-reception-notes" name="notes" rows="2" maxlength="4000"><?= e((string) ($receptionValues['notes'] ?? '')) ?></textarea>
                        </div>
                    </div>
                    <div class="form-actions">
                        <button class="button button--primary" type="submit"><?= e(__('chantier.add_reception_submit')) ?></button>
                    </div>
                </form>
            <?php endif; ?>
        </section>
    </div>

    <aside class="stack-md" aria-label="<?= e(__('chantier.summary')) ?>">
        <section class="card stack-md">
            <h2 class="card-header__title"><?= e(__('chantier.summary')) ?></h2>
            <dl class="detail-list">
                <div class="detail-list__item"><dt><?= e(__('chantier.client')) ?></dt><dd><?= e((string) ($project['client_name'] ?? '')) ?></dd></div>
                <div class="detail-list__item"><dt><?= e(__('chantier.reference')) ?></dt><dd><?= e((string) ($project['reference'] ?? '—')) ?></dd></div>
                <div class="detail-list__item"><dt><?= e(__('chantier.city')) ?></dt><dd><?= ($project['city'] ?? '') !== '' ? e((string) $project['city']) : '—' ?></dd></div>
                <div class="detail-list__item"><dt><?= e(__('chantier.visits')) ?></dt><dd><?= e((string) count($visits)) ?></dd></div>
                <div class="detail-list__item"><dt><?= e(__('chantier.open_reserves')) ?></dt><dd><?= e((string) $openReserves) ?></dd></div>
            </dl>
        </section>
    </aside>
</div>
