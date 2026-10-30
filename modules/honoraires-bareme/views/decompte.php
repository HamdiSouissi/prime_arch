<?php
declare(strict_types=1);

use App\Core\View;

$decompte = is_array($decompte ?? null) ? $decompte : [];
$project = is_array($project ?? null) ? $project : [];
$breakdown = is_array($breakdown ?? null) ? $breakdown : [];
$decompteId = (int) ($decompte['id'] ?? 0);
$projectId = (int) ($project['id'] ?? ($decompte['project_id'] ?? 0));
$status = (string) ($decompte['status'] ?? 'draft');
$hasDocument = (int) ($decompte['document_id'] ?? 0) > 0;

$statusClass = static fn (string $value): string => match ($value) {
    'validated' => 'badge--info',
    'quoted' => 'badge--success',
    default => 'badge--neutral',
};
?>
<?php View::partial('partials/page-header', [
    'pageTitle' => (string) ($decompte['mission_name'] ?? ''),
    'eyebrow' => __('honoraire.decompte_eyebrow'),
    'subtitle' => __('honoraire.decompte_intro', [
        'project' => (string) ($project['name'] ?? ''),
        'date' => date('d/m/Y', (int) strtotime((string) ($decompte['created_at'] ?? 'now'))),
    ]),
    'secondaryAction' => [
        'label' => __('honoraire.back_project'),
        'icon' => 'arrow-left',
        'url' => url('/honoraires/' . $projectId),
    ],
]) ?>

<?php if (!empty($notice)): ?><p class="form-feedback form-feedback--success" role="status"><?= e($notice) ?></p><?php endif; ?>
<?php if (!empty($error)): ?><p class="form-feedback form-feedback--error" role="alert"><?= e($error) ?></p><?php endif; ?>

<section class="stats-grid" aria-label="<?= e(__('honoraire.decompte_statistics')) ?>">
    <?php View::partial('partials/stat-card', ['label' => __('honoraire.basis'), 'value' => money_format_tnd((string) ($decompte['basis_amount'] ?? '0.000')), 'icon' => 'building', 'tone' => 'mint']) ?>
    <?php View::partial('partials/stat-card', ['label' => __('honoraire.fee'), 'value' => money_format_tnd((string) ($decompte['fee_amount'] ?? '0.000')), 'icon' => 'wallet', 'tone' => 'pink']) ?>
    <?php View::partial('partials/stat-card', [
        'label' => __('honoraire.status'),
        'value' => __('honoraire.status.' . $status),
        'icon' => 'check-circle',
        'tone' => 'blue',
        'note' => $hasDocument ? __('honoraire.document_linked') : __('honoraire.document_none'),
    ]) ?>
</section>

<p class="text-callout">
    <svg class="icon icon--small" aria-hidden="true"><use href="<?= e(asset('img/icons.svg')) ?>#info"></use></svg>
    <?= e(__('honoraire.decompte_disclaimer')) ?>
</p>

<section class="card stack-md" aria-labelledby="honoraire-breakdown-title">
    <div class="card-header">
        <div>
            <h2 class="card-header__title" id="honoraire-breakdown-title"><?= e(__('honoraire.breakdown_title')) ?></h2>
            <p class="section-heading__note"><?= e(__('honoraire.breakdown_help')) ?></p>
        </div>
        <span class="badge <?= e($statusClass($status)) ?>"><?= e(__('honoraire.status.' . $status)) ?></span>
    </div>

    <?php if ($breakdown === []): ?>
        <p class="table-meta"><?= e(__('honoraire.breakdown_empty')) ?></p>
    <?php else: ?>
        <div class="table-wrap">
            <table class="data-table">
                <thead>
                    <tr>
                        <th scope="col"><?= e(__('honoraire.phase')) ?></th>
                        <th scope="col"><?= e(__('honoraire.from_amount')) ?></th>
                        <th scope="col"><?= e(__('honoraire.to_amount')) ?></th>
                        <th scope="col"><?= e(__('honoraire.rate')) ?></th>
                        <th scope="col"><?= e(__('honoraire.taxable_portion')) ?></th>
                        <th scope="col"><?= e(__('honoraire.fee')) ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($breakdown as $phase): ?>
                        <?php $phaseTranches = is_array($phase['tranches'] ?? null) ? $phase['tranches'] : []; ?>
                        <?php if ($phaseTranches === []): ?>
                            <tr>
                                <td><?= e((string) ($phase['phase_name'] ?? $phase['phase_key'] ?? '')) ?></td>
                                <td colspan="3" class="muted">—</td>
                                <td class="u-numeric"><?= e(money_format_tnd((string) ($phase['basis'] ?? '0.000'))) ?></td>
                                <td class="u-numeric"><strong><?= e(money_format_tnd((string) ($phase['fee'] ?? '0.000'))) ?></strong></td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($phaseTranches as $index => $tranche): ?>
                                <tr>
                                    <td><?= $index === 0 ? e((string) ($phase['phase_name'] ?? $phase['phase_key'] ?? '')) : '' ?></td>
                                    <td class="u-numeric"><?= e(money_format_tnd((string) ($tranche['min_amount'] ?? '0.000'))) ?></td>
                                    <td class="u-numeric">
                                        <?php if (($tranche['max_amount'] ?? null) === null): ?>
                                            <span class="muted"><?= e(__('honoraire.no_limit')) ?></span>
                                        <?php else: ?>
                                            <?= e(money_format_tnd((string) $tranche['max_amount'])) ?>
                                        <?php endif; ?>
                                    </td>
                                    <td class="u-numeric"><?= e((string) ($tranche['rate_percent'] ?? '0')) ?> %</td>
                                    <td class="u-numeric"><?= e(money_format_tnd((string) ($tranche['portion'] ?? '0.000'))) ?></td>
                                    <td class="u-numeric"><strong><?= e(money_format_tnd((string) ($tranche['fee'] ?? '0.000'))) ?></strong></td>
                                </tr>
                            <?php endforeach; ?>
                            <tr>
                                <td colspan="5" class="muted"><?= e(__('honoraire.phase_total', ['phase' => (string) ($phase['phase_name'] ?? $phase['phase_key'] ?? '')])) ?></td>
                                <td class="u-numeric"><strong><?= e(money_format_tnd((string) ($phase['fee'] ?? '0.000'))) ?></strong></td>
                            </tr>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr>
                        <th scope="row" colspan="5"><?= e(__('honoraire.grand_total')) ?></th>
                        <td class="u-numeric"><strong><?= e(money_format_tnd((string) ($decompte['fee_amount'] ?? '0.000'))) ?></strong></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    <?php endif; ?>

    <?php if (trim((string) ($decompte['notes'] ?? '')) !== ''): ?>
        <dl class="detail-list">
            <div class="detail-list__item">
                <dt><?= e(__('honoraire.notes')) ?></dt>
                <dd><?= nl2br(e((string) $decompte['notes'])) ?></dd>
            </div>
        </dl>
    <?php endif; ?>
</section>

<section class="card stack-md" aria-labelledby="honoraire-actions-title">
    <div class="card-header">
        <div>
            <h2 class="card-header__title" id="honoraire-actions-title"><?= e(__('honoraire.actions_title')) ?></h2>
            <p class="section-heading__note"><?= e(__('honoraire.actions_help')) ?></p>
        </div>
    </div>

    <div class="cluster">
        <?php if ($hasDocument): ?>
            <a class="button button--secondary" href="<?= e(url('/documents/' . (int) $decompte['document_id'])) ?>">
                <svg class="icon icon--small" aria-hidden="true"><use href="<?= e(asset('img/icons.svg')) ?>#file-text"></use></svg>
                <?= e(__('honoraire.open_document')) ?>
            </a>
        <?php elseif (!empty($canManage) && !empty($canCreateDocument)): ?>
            <form method="post" action="<?= e(url('/honoraires/decomptes/' . $decompteId . '/devis')) ?>"
                  data-confirm="<?= e(__('honoraire.confirm_quote')) ?>">
                <?= csrf_field() ?>
                <button class="button button--primary" type="submit">
                    <svg class="icon icon--small" aria-hidden="true"><use href="<?= e(asset('img/icons.svg')) ?>#quote"></use></svg>
                    <?= e(__('honoraire.create_document')) ?>
                </button>
            </form>
        <?php endif; ?>

        <?php if (!empty($canManage) && $status === 'draft'): ?>
            <form method="post" action="<?= e(url('/honoraires/decomptes/' . $decompteId . '/valider')) ?>">
                <?= csrf_field() ?>
                <button class="button button--secondary" type="submit"><?= e(__('honoraire.validate')) ?></button>
            </form>
        <?php endif; ?>

        <?php if (!empty($canManage) && !$hasDocument): ?>
            <form method="post" action="<?= e(url('/honoraires/decomptes/' . $decompteId . '/delete')) ?>"
                  data-confirm="<?= e(__('honoraire.confirm_delete_decompte')) ?>">
                <?= csrf_field() ?>
                <button class="button button--ghost" type="submit"><?= e(__('honoraire.delete_decompte')) ?></button>
            </form>
        <?php endif; ?>
    </div>

    <?php if ($hasDocument): ?>
        <p class="table-meta"><?= e(__('honoraire.document_exists_help')) ?></p>
    <?php elseif (!empty($canManage) && empty($canCreateDocument)): ?>
        <p class="table-meta"><?= e(__('honoraire.no_document_right')) ?></p>
    <?php endif; ?>
</section>
