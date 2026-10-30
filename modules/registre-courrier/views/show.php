<?php
declare(strict_types=1);

use App\Core\View;
use Modules\RegistreCourrier\Services\CourrierService;

$entry = is_array($entry ?? null) ? $entry : [];
$statuts = is_array($statuts ?? null) && $statuts !== [] ? $statuts : CourrierService::STATUTS;
$statut = (string) ($entry['statut'] ?? 'todo');
$sens = (string) ($entry['sens'] ?? 'in');
$overdue = CourrierService::isOverdue(isset($entry['echeance']) ? (string) $entry['echeance'] : null, $statut);
$daysLeft = CourrierService::daysLeft(isset($entry['echeance']) ? (string) $entry['echeance'] : null);
$formatDate = static function (?string $date): string {
    if ($date === null || $date === '') {
        return '—';
    }
    $time = strtotime($date);

    return $time === false ? '—' : date('d/m/Y', $time);
};
$formatDateTime = static function (?string $value): string {
    if ($value === null || $value === '') {
        return '—';
    }
    $time = strtotime($value);

    return $time === false ? '—' : date('d/m/Y H:i', $time);
};
?>
<?php View::partial('partials/page-header', [
    'pageTitle' => (string) ($entry['numero'] ?? ''),
    'eyebrow' => __('courrier.sens.' . $sens) . ' · ' . __('courrier.detail_eyebrow'),
    'subtitle' => (string) ($entry['objet'] ?? ''),
    'secondaryAction' => ['label' => __('courrier.back'), 'icon' => 'arrow-left', 'url' => url('/courrier')],
    'primaryAction' => !empty($canManage) ? ['label' => __('courrier.edit'), 'icon' => 'edit', 'url' => url('/courrier/' . (int) $entry['id'] . '/edit')] : null,
]) ?>

<?php if (!empty($notice)): ?><p class="form-feedback form-feedback--success" role="status"><?= e($notice) ?></p><?php endif; ?>
<?php if (!empty($error)): ?><p class="form-feedback form-feedback--error" role="alert"><?= e($error) ?></p><?php endif; ?>

<p class="table-meta" role="note"><?= e(__('courrier.echeance_notice')) ?></p>

<div class="content-grid">
    <div class="stack-lg">
        <section class="card stack-md" aria-labelledby="courrier-summary-title">
            <div class="card-header">
                <div>
                    <h2 class="card-header__title" id="courrier-summary-title"><?= e(__('courrier.summary')) ?></h2>
                    <p class="section-heading__note"><?= e(__('courrier.summary_help')) ?></p>
                </div>
                <span class="badge badge--<?= e(CourrierService::badgeVariant($statut)) ?>"><?= e(__('courrier.statut.' . $statut)) ?></span>
            </div>
            <dl class="detail-list">
                <div class="detail-list__item"><dt><?= e(__('courrier.sens_label')) ?></dt><dd><?= e(__('courrier.sens.' . $sens)) ?></dd></div>
                <div class="detail-list__item"><dt><?= e(__('courrier.date')) ?></dt><dd><?= e($formatDate((string) ($entry['date_courrier'] ?? ''))) ?></dd></div>
                <div class="detail-list__item"><dt><?= e(__('courrier.correspondant')) ?></dt><dd><?= e((string) ($entry['correspondant'] ?? '')) ?></dd></div>
                <div class="detail-list__item"><dt><?= e(__('courrier.objet')) ?></dt><dd><?= e((string) ($entry['objet'] ?? '')) ?></dd></div>
                <div class="detail-list__item"><dt><?= e(__('courrier.reference')) ?></dt><dd><?= ($entry['reference'] ?? '') !== '' ? e((string) $entry['reference']) : '—' ?></dd></div>
                <div class="detail-list__item"><dt><?= e(__('courrier.mode_label')) ?></dt><dd><?= e(__('courrier.mode.' . (string) ($entry['mode'] ?? 'other'))) ?></dd></div>
                <div class="detail-list__item">
                    <dt><?= e(__('courrier.echeance')) ?></dt>
                    <dd>
                        <?= e($formatDate(isset($entry['echeance']) ? (string) $entry['echeance'] : null)) ?>
                        <?php if ($overdue): ?>
                            <span class="badge badge--error badge--plain"><?= e(__('courrier.overdue')) ?></span>
                        <?php elseif ($daysLeft !== null && in_array($statut, CourrierService::OPEN_STATUTS, true)): ?>
                            <span class="badge badge--neutral badge--plain"><?= e(__('courrier.in_days', ['days' => (string) $daysLeft])) ?></span>
                        <?php endif; ?>
                    </dd>
                </div>
                <div class="detail-list__item">
                    <dt><?= e(__('courrier.projet')) ?></dt>
                    <dd>
                        <?php if ((int) ($entry['project_id'] ?? 0) > 0): ?>
                            <a href="<?= e(url('/projects/' . (int) $entry['project_id'])) ?>"><?= e((string) ($entry['project_name'] ?? '')) ?></a>
                            <?php if (($entry['project_reference'] ?? '') !== ''): ?><span class="table-meta"><?= e((string) $entry['project_reference']) ?></span><?php endif; ?>
                        <?php else: ?>
                            —
                        <?php endif; ?>
                    </dd>
                </div>
                <div class="detail-list__item">
                    <dt><?= e(__('courrier.client')) ?></dt>
                    <dd>
                        <?php if ((int) ($entry['client_id'] ?? 0) > 0): ?>
                            <a href="<?= e(url('/clients/' . (int) $entry['client_id'])) ?>"><?= e((string) ($entry['client_name'] ?? '')) ?></a>
                        <?php else: ?>
                            —
                        <?php endif; ?>
                    </dd>
                </div>
            </dl>
            <?php if (($entry['notes'] ?? '') !== ''): ?>
                <div class="document-text-block">
                    <h3><?= e(__('courrier.notes')) ?></h3>
                    <p><?= nl2br(e((string) $entry['notes'])) ?></p>
                </div>
            <?php endif; ?>
        </section>

        <?php if (!empty($canManage)): ?>
            <section class="card stack-md" aria-labelledby="courrier-status-title">
                <div class="card-header">
                    <div>
                        <h2 class="card-header__title" id="courrier-status-title"><?= e(__('courrier.change_statut')) ?></h2>
                        <p class="section-heading__note"><?= e(__('courrier.change_statut_help')) ?></p>
                    </div>
                </div>
                <form class="cluster" method="post" action="<?= e(url('/courrier/' . (int) $entry['id'] . '/statut')) ?>">
                    <?= csrf_field() ?>
                    <?php foreach ($statuts as $candidate): ?>
                        <?php if ($candidate === $statut) { continue; } ?>
                        <button class="button button--small <?= $candidate === 'done' ? 'button--primary' : 'button--secondary' ?>" type="submit" name="statut" value="<?= e($candidate) ?>">
                            <?= e(__('courrier.statut.' . $candidate)) ?>
                        </button>
                    <?php endforeach; ?>
                </form>
            </section>
        <?php endif; ?>
    </div>

    <aside class="stack-md" aria-label="<?= e(__('courrier.shortcuts')) ?>">
        <section class="card stack-md">
            <h2 class="card-header__title"><?= e(__('courrier.shortcuts')) ?></h2>
            <?php if ((int) ($entry['project_id'] ?? 0) > 0): ?>
                <a class="button button--secondary" href="<?= e(url('/projects/' . (int) $entry['project_id'])) ?>"><?= e(__('courrier.open_project')) ?></a>
            <?php endif; ?>
            <?php if ((int) ($entry['client_id'] ?? 0) > 0): ?>
                <a class="button button--secondary" href="<?= e(url('/clients/' . (int) $entry['client_id'])) ?>"><?= e(__('courrier.open_client')) ?></a>
            <?php endif; ?>
            <a class="button button--secondary" href="<?= e(url('/courrier?annee=' . (int) ($entry['annee'] ?? 0) . '&sens=' . rawurlencode($sens))) ?>"><?= e(__('courrier.same_year')) ?></a>
            <a class="button button--secondary" href="<?= e(url('/courrier/imprimer?annee=' . (int) ($entry['annee'] ?? 0) . '&sens=' . rawurlencode($sens))) ?>"><?= e(__('courrier.print')) ?></a>
        </section>
        <section class="card stack-md">
            <h2 class="card-header__title"><?= e(__('courrier.traceability')) ?></h2>
            <dl class="detail-list">
                <div class="detail-list__item"><dt><?= e(__('courrier.created_at')) ?></dt><dd><?= e($formatDateTime(isset($entry['created_at']) ? (string) $entry['created_at'] : null)) ?></dd></div>
                <div class="detail-list__item"><dt><?= e(__('courrier.updated_at')) ?></dt><dd><?= e($formatDateTime(isset($entry['updated_at']) ? (string) $entry['updated_at'] : null)) ?></dd></div>
                <div class="detail-list__item"><dt><?= e(__('courrier.author')) ?></dt><dd><?= ($entry['author_name'] ?? '') !== '' ? e((string) $entry['author_name']) : '—' ?></dd></div>
            </dl>
        </section>
    </aside>
</div>
