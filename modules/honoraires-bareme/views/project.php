<?php
declare(strict_types=1);

use App\Core\View;

$project = is_array($project ?? null) ? $project : [];
$decomptes = is_array($decomptes ?? null) ? $decomptes : [];
$missions = is_array($missions ?? null) ? $missions : [];
$phases = is_array($phases ?? null) ? $phases : [];
$decompteErrors = is_array($decompteErrors ?? null) ? $decompteErrors : [];
$decompteValues = is_array($decompteValues ?? null) ? $decompteValues : [];
$projectId = (int) ($project['id'] ?? 0);

$statusClass = static fn (string $status): string => match ($status) {
    'validated' => 'badge--info',
    'quoted' => 'badge--success',
    default => 'badge--neutral',
};
?>
<?php View::partial('partials/page-header', [
    'pageTitle' => (string) ($project['name'] ?? ''),
    'eyebrow' => __('honoraire.eyebrow'),
    'subtitle' => __('honoraire.project_intro', [
        'client' => (string) ($project['client_name'] ?? ''),
        'reference' => (string) ($project['reference'] ?? '—'),
    ]),
    'secondaryAction' => ['label' => __('honoraire.back'), 'icon' => 'arrow-left', 'url' => url('/honoraires')],
]) ?>

<?php if (!empty($notice)): ?><p class="form-feedback form-feedback--success" role="status"><?= e($notice) ?></p><?php endif; ?>
<?php if (!empty($error)): ?><p class="form-feedback form-feedback--error" role="alert"><?= e($error) ?></p><?php endif; ?>

<section class="stats-grid" aria-label="<?= e(__('honoraire.project_statistics')) ?>">
    <?php View::partial('partials/stat-card', ['label' => __('honoraire.decomptes'), 'value' => (string) count($decomptes), 'icon' => 'file-text', 'tone' => 'blue']) ?>
    <?php View::partial('partials/stat-card', ['label' => __('honoraire.basis_total'), 'value' => money_format_tnd((string) ($totalBasis ?? '0.000')), 'icon' => 'building', 'tone' => 'mint']) ?>
    <?php View::partial('partials/stat-card', ['label' => __('honoraire.fee_total'), 'value' => money_format_tnd((string) ($totalFee ?? '0.000')), 'icon' => 'wallet', 'tone' => 'pink']) ?>
</section>

<p class="text-callout">
    <svg class="icon icon--small" aria-hidden="true"><use href="<?= e(asset('img/icons.svg')) ?>#info"></use></svg>
    <?= e(__('honoraire.project_notice')) ?>
</p>

<section class="card stack-md" id="decomptes" aria-labelledby="honoraire-decomptes-title">
    <div class="card-header">
        <div>
            <h2 class="card-header__title" id="honoraire-decomptes-title"><?= e(__('honoraire.decomptes_title')) ?></h2>
            <p class="section-heading__note"><?= e(__('honoraire.decomptes_help')) ?></p>
        </div>
    </div>

    <?php if ($decomptes === []): ?>
        <?php View::partial('partials/empty-state', [
            'title' => __('honoraire.no_decomptes_title'),
            'description' => __('honoraire.no_decomptes_help'),
        ]) ?>
    <?php else: ?>
        <div class="table-wrap">
            <table class="data-table">
                <thead>
                    <tr>
                        <th scope="col"><?= e(__('honoraire.date')) ?></th>
                        <th scope="col"><?= e(__('honoraire.mission')) ?></th>
                        <th scope="col"><?= e(__('honoraire.basis')) ?></th>
                        <th scope="col"><?= e(__('honoraire.fee')) ?></th>
                        <th scope="col"><?= e(__('honoraire.status')) ?></th>
                        <th scope="col"><?= e(__('honoraire.document')) ?></th>
                        <th scope="col"><?= e(__('common.actions')) ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($decomptes as $decompte): ?>
                        <?php $decompteId = (int) $decompte['id']; ?>
                        <tr>
                            <td><?= e(date('d/m/Y', (int) strtotime((string) $decompte['created_at']))) ?></td>
                            <td><?= e((string) $decompte['mission_name']) ?></td>
                            <td class="u-numeric"><?= e(money_format_tnd((string) $decompte['basis_amount'])) ?></td>
                            <td class="u-numeric"><strong><?= e(money_format_tnd((string) $decompte['fee_amount'])) ?></strong></td>
                            <td>
                                <span class="badge <?= e($statusClass((string) $decompte['status'])) ?>">
                                    <?= e(__('honoraire.status.' . (string) $decompte['status'])) ?>
                                </span>
                            </td>
                            <td>
                                <?php if ((int) ($decompte['document_id'] ?? 0) > 0): ?>
                                    <a class="table-primary" href="<?= e(url('/documents/' . (int) $decompte['document_id'])) ?>">
                                        <span class="table-primary__text">
                                            <span class="table-primary__meta"><?= e((string) ($decompte['document_number'] ?? $decompte['document_reference'] ?? '')) ?></span>
                                        </span>
                                    </a>
                                <?php else: ?>
                                    <span class="muted">—</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <a class="button button--small button--secondary" href="<?= e(url('/honoraires/decomptes/' . $decompteId)) ?>">
                                    <?= e(__('honoraire.open_decompte')) ?>
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>

    <?php if (!empty($canManage)): ?>
        <h3 class="section-heading__title"><?= e(__('honoraire.add_decompte')) ?></h3>
        <?php if ($missions === []): ?>
            <p class="table-meta"><?= e(__('honoraire.no_mission_help')) ?></p>
            <a class="button button--secondary button--small" href="<?= e(url('/honoraires/baremes')) ?>"><?= e(__('honoraire.baremes_link')) ?></a>
        <?php else: ?>
            <?php if ($decompteErrors !== []): ?>
                <p class="form-feedback form-feedback--error" role="alert"><?= e(__('honoraire.fix_errors')) ?></p>
            <?php endif; ?>
            <form class="form-grid" method="post" action="<?= e(url('/honoraires/' . $projectId . '/decomptes')) ?>">
                <?= csrf_field() ?>
                <div class="field form-grid__full">
                    <label class="field__label" for="decompte-mission"><?= e(__('honoraire.mission')) ?></label>
                    <select class="field__control" id="decompte-mission" name="mission_id" required>
                        <?php foreach ($missions as $mission): ?>
                            <option value="<?= (int) $mission['id'] ?>" <?= (int) ($decompteValues['mission_id'] ?? 0) === (int) $mission['id'] ? 'selected' : '' ?>>
                                <?= e((string) $mission['name']) ?> · <?= e(__('honoraire.tranches_count', ['count' => (string) $mission['tranches_count']])) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <span class="field__hint"><?= e(__('honoraire.mission_help')) ?></span>
                </div>
                <div class="field">
                    <label class="field__label" for="decompte-basis"><?= e(__('honoraire.basis')) ?></label>
                    <input class="field__control" type="text" inputmode="decimal" id="decompte-basis" name="basis_amount"
                           value="<?= e((string) ($decompteValues['basis_amount'] ?? '')) ?>" placeholder="0.000" required>
                    <span class="field__hint"><?= e(__('honoraire.basis_help')) ?></span>
                    <?php if (!empty($decompteErrors['basis_amount'])): ?><span class="field__hint field__hint--error"><?= e($decompteErrors['basis_amount']) ?></span><?php endif; ?>
                </div>
                <div class="field form-grid__full">
                    <label class="field__label" for="decompte-notes"><?= e(__('honoraire.notes')) ?></label>
                    <input class="field__control" type="text" id="decompte-notes" name="notes" maxlength="2000"
                           value="<?= e((string) ($decompteValues['notes'] ?? '')) ?>">
                    <span class="field__hint"><?= e(__('honoraire.notes_help')) ?></span>
                    <?php if (!empty($decompteErrors['notes'])): ?><span class="field__hint field__hint--error"><?= e($decompteErrors['notes']) ?></span><?php endif; ?>
                </div>
                <div class="form-actions form-grid__full">
                    <button class="button button--primary" type="submit"><?= e(__('honoraire.add_decompte_submit')) ?></button>
                </div>
            </form>
        <?php endif; ?>
    <?php endif; ?>
</section>

<section class="card stack-md" aria-labelledby="honoraire-phases-title">
    <div class="card-header">
        <div>
            <h2 class="card-header__title" id="honoraire-phases-title"><?= e(__('honoraire.phases_title')) ?></h2>
            <p class="section-heading__note"><?= e(__('honoraire.phases_help')) ?></p>
        </div>
    </div>
    <?php if ($phases === []): ?>
        <p class="table-meta"><?= e(__('honoraire.phases_empty')) ?></p>
    <?php else: ?>
        <div class="table-wrap">
            <table class="data-table">
                <thead>
                    <tr>
                        <th scope="col"><?= e(__('honoraire.phase')) ?></th>
                        <th scope="col"><?= e(__('honoraire.phase_key')) ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($phases as $key => $name): ?>
                        <tr>
                            <td><?= e((string) $name) ?></td>
                            <td><code><?= e((string) $key) ?></code></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>
