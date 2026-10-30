<?php
declare(strict_types=1);

use App\Core\View;
use Modules\ConformiteOat\Services\ConformiteService;

$inscriptions = is_array($inscriptions ?? null) ? $inscriptions : [];
$assurances = is_array($assurances ?? null) ? $assurances : [];
$formations = is_array($formations ?? null) ? $formations : [];
$partenaires = is_array($partenaires ?? null) ? $partenaires : [];
$alerts = is_array($alerts ?? null) ? $alerts : [];
$statistics = is_array($statistics ?? null) ? $statistics : [];
$horizon = (int) ($horizon ?? ConformiteService::HORIZON_DEFAULT);
$today = (string) ($today ?? date('Y-m-d'));

$inscriptionErrors = is_array($inscriptionErrors ?? null) ? $inscriptionErrors : [];
$inscriptionValues = is_array($inscriptionValues ?? null) ? $inscriptionValues : [];
$assuranceErrors = is_array($assuranceErrors ?? null) ? $assuranceErrors : [];
$assuranceValues = is_array($assuranceValues ?? null) ? $assuranceValues : [];
$formationErrors = is_array($formationErrors ?? null) ? $formationErrors : [];
$formationValues = is_array($formationValues ?? null) ? $formationValues : [];
$partenaireErrors = is_array($partenaireErrors ?? null) ? $partenaireErrors : [];
$partenaireValues = is_array($partenaireValues ?? null) ? $partenaireValues : [];

$dateLabel = static fn (?string $value): string => $value !== null && $value !== ''
    ? date('d/m/Y', (int) strtotime($value))
    : '—';
$statusBadge = static fn (string $status): string => match ($status) {
    'expired' => 'badge--error',
    'expiring' => 'badge--warning',
    'valid' => 'badge--success',
    default => 'badge--neutral',
};
$daysLabel = static function (array $row): string {
    $days = $row['days_left'] ?? null;
    if ($days === null) {
        return '';
    }

    return (int) $days < 0
        ? __('conformite.expired_since', ['days' => (string) abs((int) $days)])
        : __('conformite.expires_in', ['days' => (string) $days]);
};
$hoursLabel = static fn (mixed $hours): string => ConformiteService::hoursLabel(
    $hours === null ? null : ConformiteService::hours((string) $hours),
    __('conformite.unit_hours')
);
$errorHint = static fn (array $errors, string $field): string => (string) ($errors[$field] ?? '');
?>
<?php View::partial('partials/page-header', [
    'pageTitle' => __('conformite.title'),
    'eyebrow' => __('conformite.eyebrow'),
    'subtitle' => __('conformite.intro'),
    'secondaryAction' => ['label' => __('conformite.attestations_link'), 'icon' => 'file-text', 'url' => url('/conformite/attestations')],
]) ?>

<?php if (!empty($notice)): ?><p class="form-feedback form-feedback--success" role="status"><?= e($notice) ?></p><?php endif; ?>
<?php if (!empty($error)): ?><p class="form-feedback form-feedback--error" role="alert"><?= e($error) ?></p><?php endif; ?>

<p class="table-meta" role="note"><?= e(__('conformite.legal_notice')) ?></p>

<section class="card stack-md" aria-labelledby="conformite-horizon-title">
    <div class="card-header">
        <div>
            <h2 class="card-header__title" id="conformite-horizon-title"><?= e(__('conformite.horizon_title')) ?></h2>
            <p class="section-heading__note"><?= e(__('conformite.horizon_help')) ?></p>
        </div>
    </div>
    <form class="filter-row" method="get" action="<?= e(url('/conformite')) ?>">
        <div class="field">
            <label class="field__label" for="conformite-horizon"><?= e(__('conformite.horizon')) ?></label>
            <input class="field__control" type="number" min="0" max="<?= ConformiteService::HORIZON_MAX ?>" step="1"
                   id="conformite-horizon" name="horizon" value="<?= (int) $horizon ?>">
            <span class="field__hint"><?= e(__('conformite.horizon_hint')) ?></span>
        </div>
        <button class="button button--secondary" type="submit">
            <svg class="icon icon--small" aria-hidden="true"><use href="<?= e(asset('img/icons.svg')) ?>#filter"></use></svg><?= e(__('common.filter')) ?>
        </button>
    </form>
</section>

<section class="stats-grid" aria-label="<?= e(__('conformite.statistics')) ?>">
    <?php View::partial('partials/stat-card', [
        'label' => __('conformite.stat.inscriptions'),
        'value' => (string) (($statistics['inscriptions']['total'] ?? 0)),
        'icon' => 'check-circle',
        'tone' => 'blue',
        'note' => __('conformite.stat.expired_note', ['count' => (string) ($statistics['inscriptions']['expired'] ?? 0)]),
    ]) ?>
    <?php View::partial('partials/stat-card', [
        'label' => __('conformite.stat.assurances'),
        'value' => (string) (($statistics['assurances']['total'] ?? 0)),
        'icon' => 'shield',
        'tone' => 'mint',
        'note' => __('conformite.stat.expiring_note', ['count' => (string) ($statistics['assurances']['expiring'] ?? 0)]),
    ]) ?>
    <?php View::partial('partials/stat-card', [
        'label' => __('conformite.stat.formations'),
        'value' => (string) (($statistics['formations']['total'] ?? 0)),
        'icon' => 'sparkles',
        'tone' => 'pink',
        'note' => __('conformite.stat.expired_note', ['count' => (string) ($statistics['formations']['expired'] ?? 0)]),
    ]) ?>
    <?php View::partial('partials/stat-card', [
        'label' => __('conformite.stat.partenaires'),
        'value' => (string) ($statistics['partenaires'] ?? 0),
        'icon' => 'building',
        'tone' => 'coral',
        'note' => __('conformite.stat.partenaires_note', ['total' => (string) ($statistics['partenaires_total'] ?? 0)]),
    ]) ?>
</section>

<section class="card stack-md" aria-labelledby="conformite-alerts-title">
    <div class="card-header">
        <div>
            <h2 class="card-header__title" id="conformite-alerts-title"><?= e(__('conformite.alerts_title')) ?></h2>
            <p class="section-heading__note"><?= e(__('conformite.alerts_help', ['days' => (string) $horizon])) ?></p>
        </div>
    </div>
    <?php if ($alerts === []): ?>
        <p class="table-meta"><?= e(__('conformite.no_alerts')) ?></p>
    <?php else: ?>
        <div class="table-wrap">
            <table class="data-table">
                <thead>
                    <tr>
                        <th scope="col"><?= e(__('conformite.source')) ?></th>
                        <th scope="col"><?= e(__('conformite.label')) ?></th>
                        <th scope="col"><?= e(__('conformite.expiry')) ?></th>
                        <th scope="col"><?= e(__('conformite.status.label')) ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($alerts as $alert): ?>
                        <tr>
                            <td><?= e(__('conformite.source.' . (string) $alert['source'])) ?></td>
                            <td>
                                <strong><?= e((string) $alert['label']) ?></strong>
                                <?php if ((string) $alert['detail'] !== ''): ?>
                                    <span class="table-meta"><?= e((string) $alert['detail']) ?></span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?= e($dateLabel((string) $alert['expires_on'])) ?>
                                <span class="table-meta"><?= e($daysLabel($alert)) ?></span>
                            </td>
                            <td>
                                <span class="badge <?= e($statusBadge((string) $alert['status'])) ?>">
                                    <?= e(__('conformite.status.' . (string) $alert['status'])) ?>
                                </span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>

<section class="card stack-md" aria-labelledby="conformite-inscriptions-title">
    <div class="card-header">
        <div>
            <h2 class="card-header__title" id="conformite-inscriptions-title"><?= e(__('conformite.inscriptions_title')) ?></h2>
            <p class="section-heading__note"><?= e(__('conformite.inscriptions_help')) ?></p>
        </div>
    </div>

    <?php if ($inscriptions === []): ?>
        <p class="table-meta"><?= e(__('conformite.no_inscriptions')) ?></p>
    <?php else: ?>
        <div class="table-wrap">
            <table class="data-table">
                <thead>
                    <tr>
                        <th scope="col"><?= e(__('conformite.kind.label')) ?></th>
                        <th scope="col"><?= e(__('conformite.label')) ?></th>
                        <th scope="col"><?= e(__('conformite.reference')) ?></th>
                        <th scope="col"><?= e(__('conformite.issued_on')) ?></th>
                        <th scope="col"><?= e(__('conformite.expiry')) ?></th>
                        <th scope="col"><?= e(__('conformite.status.label')) ?></th>
                        <?php if (!empty($canManage)): ?><th scope="col"><?= e(__('common.actions')) ?></th><?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($inscriptions as $row): ?>
                        <tr>
                            <td><?= e(__('conformite.kind.' . (string) $row['kind'])) ?></td>
                            <td><strong><?= e((string) $row['label']) ?></strong></td>
                            <td><?= ($row['reference'] ?? '') !== '' ? e((string) $row['reference']) : '<span class="muted">—</span>' ?></td>
                            <td><?= e($dateLabel($row['issued_on'] === null ? null : (string) $row['issued_on'])) ?></td>
                            <td>
                                <?= e($dateLabel($row['expires_on'] === null ? null : (string) $row['expires_on'])) ?>
                                <span class="table-meta"><?= e($daysLabel($row)) ?></span>
                            </td>
                            <td><span class="badge <?= e($statusBadge((string) $row['status'])) ?>"><?= e(__('conformite.status.' . (string) $row['status'])) ?></span></td>
                            <?php if (!empty($canManage)): ?>
                                <td>
                                    <form method="post" action="<?= e(url('/conformite/inscriptions/' . (int) $row['id'] . '/supprimer')) ?>"
                                          data-confirm="<?= e(__('conformite.confirm_delete')) ?>">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="horizon" value="<?= (int) $horizon ?>">
                                        <button class="button button--small button--ghost" type="submit"><?= e(__('common.delete')) ?></button>
                                    </form>
                                </td>
                            <?php endif; ?>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>

    <?php if (!empty($canManage)): ?>
        <details class="stack-md" <?= $inscriptionErrors !== [] ? 'open' : '' ?>>
            <summary class="text-callout"><?= e(__('conformite.add_inscription')) ?></summary>
            <?php if ($inscriptionErrors !== []): ?>
                <p class="form-feedback form-feedback--error" role="alert"><?= e(__('conformite.fix_errors')) ?></p>
            <?php endif; ?>
            <form class="form-grid" method="post" action="<?= e(url('/conformite/inscriptions')) ?>">
                <?= csrf_field() ?>
                <input type="hidden" name="horizon" value="<?= (int) $horizon ?>">
                <div class="field">
                    <label class="field__label" for="inscription-kind"><?= e(__('conformite.kind.label')) ?></label>
                    <select class="field__control" id="inscription-kind" name="kind">
                        <?php foreach (ConformiteService::KINDS as $kind): ?>
                            <option value="<?= e($kind) ?>" <?= (string) ($inscriptionValues['kind'] ?? 'registration') === $kind ? 'selected' : '' ?>>
                                <?= e(__('conformite.kind.' . $kind)) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="field">
                    <label class="field__label" for="inscription-label"><?= e(__('conformite.label')) ?></label>
                    <input class="field__control" type="text" id="inscription-label" name="label" maxlength="150" required
                           value="<?= e((string) ($inscriptionValues['label'] ?? '')) ?>">
                    <?php if ($errorHint($inscriptionErrors, 'label') !== ''): ?><span class="field__hint field__hint--error"><?= e($errorHint($inscriptionErrors, 'label')) ?></span><?php endif; ?>
                </div>
                <div class="field">
                    <label class="field__label" for="inscription-reference"><?= e(__('conformite.reference')) ?></label>
                    <input class="field__control" type="text" id="inscription-reference" name="reference" maxlength="120"
                           value="<?= e((string) ($inscriptionValues['reference'] ?? '')) ?>">
                    <?php if ($errorHint($inscriptionErrors, 'reference') !== ''): ?><span class="field__hint field__hint--error"><?= e($errorHint($inscriptionErrors, 'reference')) ?></span><?php endif; ?>
                </div>
                <div class="field">
                    <label class="field__label" for="inscription-issued"><?= e(__('conformite.issued_on')) ?></label>
                    <input class="field__control" type="date" id="inscription-issued" name="issued_on"
                           value="<?= e((string) ($inscriptionValues['issued_on'] ?? '')) ?>">
                    <?php if ($errorHint($inscriptionErrors, 'issued_on') !== ''): ?><span class="field__hint field__hint--error"><?= e($errorHint($inscriptionErrors, 'issued_on')) ?></span><?php endif; ?>
                </div>
                <div class="field">
                    <label class="field__label" for="inscription-expires"><?= e(__('conformite.expiry')) ?></label>
                    <input class="field__control" type="date" id="inscription-expires" name="expires_on"
                           value="<?= e((string) ($inscriptionValues['expires_on'] ?? '')) ?>">
                    <span class="field__hint"><?= e(__('conformite.expiry_help')) ?></span>
                    <?php if ($errorHint($inscriptionErrors, 'expires_on') !== ''): ?><span class="field__hint field__hint--error"><?= e($errorHint($inscriptionErrors, 'expires_on')) ?></span><?php endif; ?>
                </div>
                <div class="field form-grid__full">
                    <label class="field__label" for="inscription-note"><?= e(__('conformite.note')) ?></label>
                    <input class="field__control" type="text" id="inscription-note" name="note" maxlength="500"
                           value="<?= e((string) ($inscriptionValues['note'] ?? '')) ?>">
                    <?php if ($errorHint($inscriptionErrors, 'note') !== ''): ?><span class="field__hint field__hint--error"><?= e($errorHint($inscriptionErrors, 'note')) ?></span><?php endif; ?>
                </div>
                <div class="form-actions form-grid__full">
                    <button class="button button--primary" type="submit"><?= e(__('conformite.add_inscription_submit')) ?></button>
                </div>
            </form>
        </details>
    <?php endif; ?>
</section>

<section class="card stack-md" aria-labelledby="conformite-assurances-title">
    <div class="card-header">
        <div>
            <h2 class="card-header__title" id="conformite-assurances-title"><?= e(__('conformite.assurances_title')) ?></h2>
            <p class="section-heading__note"><?= e(__('conformite.assurances_help')) ?></p>
        </div>
    </div>

    <?php if ($assurances === []): ?>
        <p class="table-meta"><?= e(__('conformite.no_assurances')) ?></p>
    <?php else: ?>
        <div class="table-wrap">
            <table class="data-table">
                <thead>
                    <tr>
                        <th scope="col"><?= e(__('conformite.insurer')) ?></th>
                        <th scope="col"><?= e(__('conformite.policy')) ?></th>
                        <th scope="col"><?= e(__('conformite.coverage')) ?></th>
                        <th scope="col"><?= e(__('conformite.validity')) ?></th>
                        <th scope="col"><?= e(__('conformite.status.label')) ?></th>
                        <?php if (!empty($canManage)): ?><th scope="col"><?= e(__('common.actions')) ?></th><?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($assurances as $row): ?>
                        <tr>
                            <td><strong><?= e((string) $row['insurer']) ?></strong></td>
                            <td><?= ($row['policy_number'] ?? '') !== '' ? e((string) $row['policy_number']) : '<span class="muted">—</span>' ?></td>
                            <td class="u-numeric">
                                <?= $row['coverage_amount'] !== null ? e(money_format_tnd((string) $row['coverage_amount'])) : '<span class="muted">—</span>' ?>
                            </td>
                            <td>
                                <?= e($dateLabel($row['starts_on'] === null ? null : (string) $row['starts_on'])) ?>
                                →
                                <?= e($dateLabel((string) $row['ends_on'])) ?>
                                <span class="table-meta"><?= e($daysLabel($row)) ?></span>
                            </td>
                            <td><span class="badge <?= e($statusBadge((string) $row['status'])) ?>"><?= e(__('conformite.status.' . (string) $row['status'])) ?></span></td>
                            <?php if (!empty($canManage)): ?>
                                <td>
                                    <form method="post" action="<?= e(url('/conformite/assurances/' . (int) $row['id'] . '/supprimer')) ?>"
                                          data-confirm="<?= e(__('conformite.confirm_delete')) ?>">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="horizon" value="<?= (int) $horizon ?>">
                                        <button class="button button--small button--ghost" type="submit"><?= e(__('common.delete')) ?></button>
                                    </form>
                                </td>
                            <?php endif; ?>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>

    <?php if (!empty($canManage)): ?>
        <details class="stack-md" <?= $assuranceErrors !== [] ? 'open' : '' ?>>
            <summary class="text-callout"><?= e(__('conformite.add_assurance')) ?></summary>
            <?php if ($assuranceErrors !== []): ?>
                <p class="form-feedback form-feedback--error" role="alert"><?= e(__('conformite.fix_errors')) ?></p>
            <?php endif; ?>
            <form class="form-grid" method="post" action="<?= e(url('/conformite/assurances')) ?>">
                <?= csrf_field() ?>
                <input type="hidden" name="horizon" value="<?= (int) $horizon ?>">
                <div class="field">
                    <label class="field__label" for="assurance-insurer"><?= e(__('conformite.insurer')) ?></label>
                    <input class="field__control" type="text" id="assurance-insurer" name="insurer" maxlength="150" required
                           value="<?= e((string) ($assuranceValues['insurer'] ?? '')) ?>">
                    <?php if ($errorHint($assuranceErrors, 'insurer') !== ''): ?><span class="field__hint field__hint--error"><?= e($errorHint($assuranceErrors, 'insurer')) ?></span><?php endif; ?>
                </div>
                <div class="field">
                    <label class="field__label" for="assurance-policy"><?= e(__('conformite.policy')) ?></label>
                    <input class="field__control" type="text" id="assurance-policy" name="policy_number" maxlength="120"
                           value="<?= e((string) ($assuranceValues['policy_number'] ?? '')) ?>">
                    <?php if ($errorHint($assuranceErrors, 'policy_number') !== ''): ?><span class="field__hint field__hint--error"><?= e($errorHint($assuranceErrors, 'policy_number')) ?></span><?php endif; ?>
                </div>
                <div class="field">
                    <label class="field__label" for="assurance-coverage"><?= e(__('conformite.coverage')) ?></label>
                    <input class="field__control" type="text" inputmode="decimal" id="assurance-coverage" name="coverage_amount"
                           value="<?= e((string) ($assuranceValues['coverage_amount'] ?? '')) ?>" placeholder="150000.000">
                    <span class="field__hint"><?= e(__('conformite.coverage_help')) ?></span>
                    <?php if ($errorHint($assuranceErrors, 'coverage_amount') !== ''): ?><span class="field__hint field__hint--error"><?= e($errorHint($assuranceErrors, 'coverage_amount')) ?></span><?php endif; ?>
                </div>
                <div class="field">
                    <label class="field__label" for="assurance-starts"><?= e(__('conformite.starts_on')) ?></label>
                    <input class="field__control" type="date" id="assurance-starts" name="starts_on"
                           value="<?= e((string) ($assuranceValues['starts_on'] ?? '')) ?>">
                    <?php if ($errorHint($assuranceErrors, 'starts_on') !== ''): ?><span class="field__hint field__hint--error"><?= e($errorHint($assuranceErrors, 'starts_on')) ?></span><?php endif; ?>
                </div>
                <div class="field">
                    <label class="field__label" for="assurance-ends"><?= e(__('conformite.expiry')) ?></label>
                    <input class="field__control" type="date" id="assurance-ends" name="ends_on" required
                           value="<?= e((string) ($assuranceValues['ends_on'] ?? '')) ?>">
                    <?php if ($errorHint($assuranceErrors, 'ends_on') !== ''): ?><span class="field__hint field__hint--error"><?= e($errorHint($assuranceErrors, 'ends_on')) ?></span><?php endif; ?>
                </div>
                <div class="field form-grid__full">
                    <label class="field__label" for="assurance-note"><?= e(__('conformite.note')) ?></label>
                    <input class="field__control" type="text" id="assurance-note" name="note" maxlength="500"
                           value="<?= e((string) ($assuranceValues['note'] ?? '')) ?>">
                    <?php if ($errorHint($assuranceErrors, 'note') !== ''): ?><span class="field__hint field__hint--error"><?= e($errorHint($assuranceErrors, 'note')) ?></span><?php endif; ?>
                </div>
                <div class="form-actions form-grid__full">
                    <button class="button button--primary" type="submit"><?= e(__('conformite.add_assurance_submit')) ?></button>
                </div>
            </form>
        </details>
    <?php endif; ?>
</section>

<section class="card stack-md" aria-labelledby="conformite-formations-title">
    <div class="card-header">
        <div>
            <h2 class="card-header__title" id="conformite-formations-title"><?= e(__('conformite.formations_title')) ?></h2>
            <p class="section-heading__note"><?= e(__('conformite.formations_help')) ?></p>
        </div>
    </div>

    <?php if ($formations === []): ?>
        <p class="table-meta"><?= e(__('conformite.no_formations')) ?></p>
    <?php else: ?>
        <div class="table-wrap">
            <table class="data-table">
                <thead>
                    <tr>
                        <th scope="col"><?= e(__('conformite.formation')) ?></th>
                        <th scope="col"><?= e(__('conformite.provider')) ?></th>
                        <th scope="col"><?= e(__('conformite.hours')) ?></th>
                        <th scope="col"><?= e(__('conformite.completed_on')) ?></th>
                        <th scope="col"><?= e(__('conformite.expiry')) ?></th>
                        <th scope="col"><?= e(__('conformite.status.label')) ?></th>
                        <?php if (!empty($canManage)): ?><th scope="col"><?= e(__('common.actions')) ?></th><?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($formations as $row): ?>
                        <tr>
                            <td>
                                <strong><?= e((string) $row['title']) ?></strong>
                                <?php if (($row['certificate_reference'] ?? '') !== ''): ?>
                                    <span class="table-meta"><?= e((string) $row['certificate_reference']) ?></span>
                                <?php endif; ?>
                            </td>
                            <td><?= ($row['provider'] ?? '') !== '' ? e((string) $row['provider']) : '<span class="muted">—</span>' ?></td>
                            <td class="u-numeric"><?= e($hoursLabel($row['hours'])) ?></td>
                            <td><?= e($dateLabel((string) $row['completed_on'])) ?></td>
                            <td>
                                <?= e($dateLabel($row['expires_on'] === null ? null : (string) $row['expires_on'])) ?>
                                <span class="table-meta"><?= e($daysLabel($row)) ?></span>
                            </td>
                            <td><span class="badge <?= e($statusBadge((string) $row['status'])) ?>"><?= e(__('conformite.status.' . (string) $row['status'])) ?></span></td>
                            <?php if (!empty($canManage)): ?>
                                <td>
                                    <form method="post" action="<?= e(url('/conformite/formations/' . (int) $row['id'] . '/supprimer')) ?>"
                                          data-confirm="<?= e(__('conformite.confirm_delete')) ?>">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="horizon" value="<?= (int) $horizon ?>">
                                        <button class="button button--small button--ghost" type="submit"><?= e(__('common.delete')) ?></button>
                                    </form>
                                </td>
                            <?php endif; ?>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>

    <?php if (!empty($canManage)): ?>
        <details class="stack-md" <?= $formationErrors !== [] ? 'open' : '' ?>>
            <summary class="text-callout"><?= e(__('conformite.add_formation')) ?></summary>
            <?php if ($formationErrors !== []): ?>
                <p class="form-feedback form-feedback--error" role="alert"><?= e(__('conformite.fix_errors')) ?></p>
            <?php endif; ?>
            <form class="form-grid" method="post" action="<?= e(url('/conformite/formations')) ?>">
                <?= csrf_field() ?>
                <input type="hidden" name="horizon" value="<?= (int) $horizon ?>">
                <div class="field form-grid__full">
                    <label class="field__label" for="formation-title"><?= e(__('conformite.formation')) ?></label>
                    <input class="field__control" type="text" id="formation-title" name="title" maxlength="180" required
                           value="<?= e((string) ($formationValues['title'] ?? '')) ?>">
                    <?php if ($errorHint($formationErrors, 'title') !== ''): ?><span class="field__hint field__hint--error"><?= e($errorHint($formationErrors, 'title')) ?></span><?php endif; ?>
                </div>
                <div class="field">
                    <label class="field__label" for="formation-provider"><?= e(__('conformite.provider')) ?></label>
                    <input class="field__control" type="text" id="formation-provider" name="provider" maxlength="150"
                           value="<?= e((string) ($formationValues['provider'] ?? '')) ?>">
                    <?php if ($errorHint($formationErrors, 'provider') !== ''): ?><span class="field__hint field__hint--error"><?= e($errorHint($formationErrors, 'provider')) ?></span><?php endif; ?>
                </div>
                <div class="field">
                    <label class="field__label" for="formation-hours"><?= e(__('conformite.hours')) ?></label>
                    <input class="field__control" type="text" inputmode="decimal" id="formation-hours" name="hours"
                           value="<?= e((string) ($formationValues['hours'] ?? '')) ?>" placeholder="14">
                    <span class="field__hint"><?= e(__('conformite.hours_help')) ?></span>
                    <?php if ($errorHint($formationErrors, 'hours') !== ''): ?><span class="field__hint field__hint--error"><?= e($errorHint($formationErrors, 'hours')) ?></span><?php endif; ?>
                </div>
                <div class="field">
                    <label class="field__label" for="formation-completed"><?= e(__('conformite.completed_on')) ?></label>
                    <input class="field__control" type="date" id="formation-completed" name="completed_on" required
                           value="<?= e((string) ($formationValues['completed_on'] ?? '')) ?>">
                    <?php if ($errorHint($formationErrors, 'completed_on') !== ''): ?><span class="field__hint field__hint--error"><?= e($errorHint($formationErrors, 'completed_on')) ?></span><?php endif; ?>
                </div>
                <div class="field">
                    <label class="field__label" for="formation-expires"><?= e(__('conformite.expiry')) ?></label>
                    <input class="field__control" type="date" id="formation-expires" name="expires_on"
                           value="<?= e((string) ($formationValues['expires_on'] ?? '')) ?>">
                    <?php if ($errorHint($formationErrors, 'expires_on') !== ''): ?><span class="field__hint field__hint--error"><?= e($errorHint($formationErrors, 'expires_on')) ?></span><?php endif; ?>
                </div>
                <div class="field">
                    <label class="field__label" for="formation-certificate"><?= e(__('conformite.certificate')) ?></label>
                    <input class="field__control" type="text" id="formation-certificate" name="certificate_reference" maxlength="120"
                           value="<?= e((string) ($formationValues['certificate_reference'] ?? '')) ?>">
                    <?php if ($errorHint($formationErrors, 'certificate_reference') !== ''): ?><span class="field__hint field__hint--error"><?= e($errorHint($formationErrors, 'certificate_reference')) ?></span><?php endif; ?>
                </div>
                <div class="field form-grid__full">
                    <label class="field__label" for="formation-note"><?= e(__('conformite.note')) ?></label>
                    <input class="field__control" type="text" id="formation-note" name="note" maxlength="500"
                           value="<?= e((string) ($formationValues['note'] ?? '')) ?>">
                    <?php if ($errorHint($formationErrors, 'note') !== ''): ?><span class="field__hint field__hint--error"><?= e($errorHint($formationErrors, 'note')) ?></span><?php endif; ?>
                </div>
                <div class="form-actions form-grid__full">
                    <button class="button button--primary" type="submit"><?= e(__('conformite.add_formation_submit')) ?></button>
                </div>
            </form>
        </details>
    <?php endif; ?>
</section>

<section class="card stack-md" aria-labelledby="conformite-partenaires-title">
    <div class="card-header">
        <div>
            <h2 class="card-header__title" id="conformite-partenaires-title"><?= e(__('conformite.partenaires_title')) ?></h2>
            <p class="section-heading__note"><?= e(__('conformite.partenaires_help')) ?></p>
        </div>
    </div>

    <?php if ($partenaires === []): ?>
        <p class="table-meta"><?= e(__('conformite.no_partenaires')) ?></p>
    <?php else: ?>
        <div class="table-wrap">
            <table class="data-table">
                <thead>
                    <tr>
                        <th scope="col"><?= e(__('conformite.partner_name')) ?></th>
                        <th scope="col"><?= e(__('conformite.discipline')) ?></th>
                        <th scope="col"><?= e(__('conformite.contact')) ?></th>
                        <th scope="col"><?= e(__('conformite.agreement')) ?></th>
                        <th scope="col"><?= e(__('conformite.active')) ?></th>
                        <?php if (!empty($canManage)): ?><th scope="col"><?= e(__('common.actions')) ?></th><?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($partenaires as $row): ?>
                        <tr>
                            <td><strong><?= e((string) $row['name']) ?></strong></td>
                            <td><?= ($row['discipline'] ?? '') !== '' ? e((string) $row['discipline']) : '<span class="muted">—</span>' ?></td>
                            <td>
                                <?php if (($row['contact_name'] ?? '') !== ''): ?>
                                    <span><?= e((string) $row['contact_name']) ?></span>
                                <?php endif; ?>
                                <?php if (($row['phone'] ?? '') !== ''): ?>
                                    <span class="table-meta"><?= e((string) $row['phone']) ?></span>
                                <?php endif; ?>
                                <?php if (($row['email'] ?? '') !== ''): ?>
                                    <span class="table-meta"><?= e((string) $row['email']) ?></span>
                                <?php endif; ?>
                                <?php if (($row['contact_name'] ?? '') === '' && ($row['phone'] ?? '') === '' && ($row['email'] ?? '') === ''): ?>
                                    <span class="muted">—</span>
                                <?php endif; ?>
                            </td>
                            <td><?= ($row['agreement_reference'] ?? '') !== '' ? e((string) $row['agreement_reference']) : '<span class="muted">—</span>' ?></td>
                            <td>
                                <span class="badge <?= (int) $row['is_active'] === 1 ? 'badge--success' : 'badge--neutral' ?>">
                                    <?= e((int) $row['is_active'] === 1 ? __('conformite.active_yes') : __('conformite.active_no')) ?>
                                </span>
                            </td>
                            <?php if (!empty($canManage)): ?>
                                <td>
                                    <div class="cluster">
                                        <form method="post" action="<?= e(url('/conformite/partenaires/' . (int) $row['id'] . '/basculer')) ?>">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="horizon" value="<?= (int) $horizon ?>">
                                            <button class="button button--small button--ghost" type="submit">
                                                <?= e((int) $row['is_active'] === 1 ? __('conformite.deactivate') : __('conformite.activate')) ?>
                                            </button>
                                        </form>
                                        <form method="post" action="<?= e(url('/conformite/partenaires/' . (int) $row['id'] . '/supprimer')) ?>"
                                              data-confirm="<?= e(__('conformite.confirm_delete')) ?>">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="horizon" value="<?= (int) $horizon ?>">
                                            <button class="button button--small button--ghost" type="submit"><?= e(__('common.delete')) ?></button>
                                        </form>
                                    </div>
                                </td>
                            <?php endif; ?>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>

    <?php if (!empty($canManage)): ?>
        <details class="stack-md" <?= $partenaireErrors !== [] ? 'open' : '' ?>>
            <summary class="text-callout"><?= e(__('conformite.add_partenaire')) ?></summary>
            <?php if ($partenaireErrors !== []): ?>
                <p class="form-feedback form-feedback--error" role="alert"><?= e(__('conformite.fix_errors')) ?></p>
            <?php endif; ?>
            <form class="form-grid" method="post" action="<?= e(url('/conformite/partenaires')) ?>">
                <?= csrf_field() ?>
                <input type="hidden" name="horizon" value="<?= (int) $horizon ?>">
                <div class="field">
                    <label class="field__label" for="partenaire-name"><?= e(__('conformite.partner_name')) ?></label>
                    <input class="field__control" type="text" id="partenaire-name" name="name" maxlength="150" required
                           value="<?= e((string) ($partenaireValues['name'] ?? '')) ?>">
                    <?php if ($errorHint($partenaireErrors, 'name') !== ''): ?><span class="field__hint field__hint--error"><?= e($errorHint($partenaireErrors, 'name')) ?></span><?php endif; ?>
                </div>
                <div class="field">
                    <label class="field__label" for="partenaire-discipline"><?= e(__('conformite.discipline')) ?></label>
                    <input class="field__control" type="text" id="partenaire-discipline" name="discipline" maxlength="120"
                           value="<?= e((string) ($partenaireValues['discipline'] ?? '')) ?>">
                    <?php if ($errorHint($partenaireErrors, 'discipline') !== ''): ?><span class="field__hint field__hint--error"><?= e($errorHint($partenaireErrors, 'discipline')) ?></span><?php endif; ?>
                </div>
                <div class="field">
                    <label class="field__label" for="partenaire-contact"><?= e(__('conformite.contact')) ?></label>
                    <input class="field__control" type="text" id="partenaire-contact" name="contact_name" maxlength="150"
                           value="<?= e((string) ($partenaireValues['contact_name'] ?? '')) ?>">
                    <?php if ($errorHint($partenaireErrors, 'contact_name') !== ''): ?><span class="field__hint field__hint--error"><?= e($errorHint($partenaireErrors, 'contact_name')) ?></span><?php endif; ?>
                </div>
                <div class="field">
                    <label class="field__label" for="partenaire-phone"><?= e(__('conformite.phone')) ?></label>
                    <input class="field__control" type="tel" id="partenaire-phone" name="phone" maxlength="40"
                           value="<?= e((string) ($partenaireValues['phone'] ?? '')) ?>">
                    <?php if ($errorHint($partenaireErrors, 'phone') !== ''): ?><span class="field__hint field__hint--error"><?= e($errorHint($partenaireErrors, 'phone')) ?></span><?php endif; ?>
                </div>
                <div class="field">
                    <label class="field__label" for="partenaire-email"><?= e(__('conformite.email')) ?></label>
                    <input class="field__control" type="email" id="partenaire-email" name="email" maxlength="190"
                           value="<?= e((string) ($partenaireValues['email'] ?? '')) ?>">
                    <?php if ($errorHint($partenaireErrors, 'email') !== ''): ?><span class="field__hint field__hint--error"><?= e($errorHint($partenaireErrors, 'email')) ?></span><?php endif; ?>
                </div>
                <div class="field">
                    <label class="field__label" for="partenaire-agreement"><?= e(__('conformite.agreement')) ?></label>
                    <input class="field__control" type="text" id="partenaire-agreement" name="agreement_reference" maxlength="120"
                           value="<?= e((string) ($partenaireValues['agreement_reference'] ?? '')) ?>">
                    <?php if ($errorHint($partenaireErrors, 'agreement_reference') !== ''): ?><span class="field__hint field__hint--error"><?= e($errorHint($partenaireErrors, 'agreement_reference')) ?></span><?php endif; ?>
                </div>
                <div class="field form-grid__full">
                    <label class="field__label" for="partenaire-note"><?= e(__('conformite.note')) ?></label>
                    <input class="field__control" type="text" id="partenaire-note" name="note" maxlength="500"
                           value="<?= e((string) ($partenaireValues['note'] ?? '')) ?>">
                    <?php if ($errorHint($partenaireErrors, 'note') !== ''): ?><span class="field__hint field__hint--error"><?= e($errorHint($partenaireErrors, 'note')) ?></span><?php endif; ?>
                </div>
                <div class="field form-grid__full">
                    <input type="hidden" name="is_active" value="0">
                    <label class="cluster" for="partenaire-active">
                        <input type="checkbox" id="partenaire-active" name="is_active" value="1" <?= (string) ($partenaireValues['is_active'] ?? '1') === '1' ? 'checked' : '' ?>>
                        <span><?= e(__('conformite.active_help')) ?></span>
                    </label>
                </div>
                <div class="form-actions form-grid__full">
                    <button class="button button--primary" type="submit"><?= e(__('conformite.add_partenaire_submit')) ?></button>
                </div>
            </form>
        </details>
    <?php endif; ?>
</section>
