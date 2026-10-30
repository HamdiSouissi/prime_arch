<?php
declare(strict_types=1);

use App\Core\View;
use Modules\ConformiteOat\Services\ConformiteService;

$projects = is_array($projects ?? null) ? $projects : [];
$assurance = is_array($assurance ?? null) ? $assurance : null;
$assuranceStatus = (string) ($assuranceStatus ?? 'none');
$assuranceDaysLeft = $assuranceDaysLeft ?? null;
$today = (string) ($today ?? date('Y-m-d'));

$dateLabel = static fn (?string $value): string => $value !== null && $value !== ''
    ? date('d/m/Y', (int) strtotime($value))
    : '—';
$statusBadge = static fn (string $status): string => match ($status) {
    'expired' => 'badge--error',
    'expiring' => 'badge--warning',
    'valid' => 'badge--success',
    default => 'badge--neutral',
};
$canPrint = $assurance !== null && $assuranceStatus !== 'expired';
?>
<?php View::partial('partials/page-header', [
    'pageTitle' => __('conformite.attestations_title'),
    'eyebrow' => __('conformite.eyebrow'),
    'subtitle' => __('conformite.attestations_intro'),
    'secondaryAction' => ['label' => __('conformite.back_to_dossier'), 'icon' => 'arrow-left', 'url' => url('/conformite')],
]) ?>

<?php if (!empty($notice)): ?><p class="form-feedback form-feedback--success" role="status"><?= e($notice) ?></p><?php endif; ?>
<?php if (!empty($error)): ?><p class="form-feedback form-feedback--error" role="alert"><?= e($error) ?></p><?php endif; ?>

<section class="card stack-md" aria-labelledby="conformite-current-assurance-title">
    <div class="card-header">
        <div>
            <h2 class="card-header__title" id="conformite-current-assurance-title"><?= e(__('conformite.current_assurance')) ?></h2>
            <p class="section-heading__note"><?= e(__('conformite.current_assurance_help')) ?></p>
        </div>
    </div>

    <?php if ($assurance === null): ?>
        <?php View::partial('partials/empty-state', [
            'title' => __('conformite.no_assurance_title'),
            'description' => __('conformite.no_assurance_help'),
            'actionLabel' => !empty($canManage) ? __('conformite.back_to_dossier') : null,
            'actionUrl' => !empty($canManage) ? url('/conformite') : null,
        ]) ?>
    <?php else: ?>
        <dl class="detail-list">
            <div class="detail-list__item">
                <dt><?= e(__('conformite.insurer')) ?></dt>
                <dd><?= e((string) $assurance['insurer']) ?></dd>
            </div>
            <div class="detail-list__item">
                <dt><?= e(__('conformite.policy')) ?></dt>
                <dd><?= ($assurance['policy_number'] ?? '') !== '' ? e((string) $assurance['policy_number']) : '—' ?></dd>
            </div>
            <div class="detail-list__item">
                <dt><?= e(__('conformite.coverage')) ?></dt>
                <dd><?= $assurance['coverage_amount'] !== null ? e(money_format_tnd((string) $assurance['coverage_amount'])) : '—' ?></dd>
            </div>
            <div class="detail-list__item">
                <dt><?= e(__('conformite.validity')) ?></dt>
                <dd>
                    <?= e($dateLabel($assurance['starts_on'] === null ? null : (string) $assurance['starts_on'])) ?>
                    →
                    <?= e($dateLabel((string) $assurance['ends_on'])) ?>
                </dd>
            </div>
            <div class="detail-list__item">
                <dt><?= e(__('conformite.status.label')) ?></dt>
                <dd>
                    <span class="badge <?= e($statusBadge($assuranceStatus)) ?>"><?= e(__('conformite.status.' . $assuranceStatus)) ?></span>
                    <?php if ($assuranceDaysLeft !== null && $assuranceStatus !== 'expired'): ?>
                        <span class="table-meta"><?= e(__('conformite.expires_in', ['days' => (string) $assuranceDaysLeft])) ?></span>
                    <?php elseif ($assuranceDaysLeft !== null): ?>
                        <span class="table-meta"><?= e(__('conformite.expired_since', ['days' => (string) abs((int) $assuranceDaysLeft)])) ?></span>
                    <?php endif; ?>
                </dd>
            </div>
        </dl>
        <?php if (!$canPrint): ?>
            <p class="form-feedback form-feedback--error" role="alert"><?= e(__('conformite.assurance_expired')) ?></p>
        <?php endif; ?>
    <?php endif; ?>
</section>

<section class="card stack-md" aria-labelledby="conformite-attestation-projects-title">
    <div class="card-header">
        <div>
            <h2 class="card-header__title" id="conformite-attestation-projects-title"><?= e(__('conformite.attestation_projects_title')) ?></h2>
            <p class="section-heading__note"><?= e(__('conformite.attestation_projects_help')) ?></p>
        </div>
    </div>

    <?php if ($projects === []): ?>
        <?php View::partial('partials/empty-state', [
            'title' => __('conformite.empty_projects_title'),
            'description' => __('conformite.empty_projects_help'),
            'actionLabel' => __('conformite.open_projects'),
            'actionUrl' => url('/projects'),
        ]) ?>
    <?php else: ?>
        <div class="table-wrap">
            <table class="data-table">
                <thead>
                    <tr>
                        <th scope="col"><?= e(__('conformite.project')) ?></th>
                        <th scope="col"><?= e(__('conformite.client')) ?></th>
                        <th scope="col"><?= e(__('conformite.project_status')) ?></th>
                        <th scope="col"><?= e(__('common.actions')) ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($projects as $project): ?>
                        <tr>
                            <td>
                                <span class="table-primary__text">
                                    <strong class="table-primary__name"><?= e((string) $project['name']) ?></strong>
                                    <span class="table-primary__meta"><?= e((string) ($project['reference'] ?? '')) ?></span>
                                </span>
                            </td>
                            <td><?= e((string) $project['client_name']) ?></td>
                            <td><?= e(__('projects.status.' . (string) $project['project_status'])) ?></td>
                            <td>
                                <?php if ($canPrint): ?>
                                    <a class="button button--small button--secondary"
                                       href="<?= e(url('/conformite/attestations/' . (int) $project['id'])) ?>">
                                        <svg class="icon icon--small" aria-hidden="true"><use href="<?= e(asset('img/icons.svg')) ?>#printer"></use></svg>
                                        <?= e(__('conformite.open_attestation')) ?>
                                    </a>
                                <?php else: ?>
                                    <span class="muted"><?= e(__('conformite.attestation_unavailable')) ?></span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <p class="table-meta"><?= e(__('conformite.attestation_note')) ?></p>
    <?php endif; ?>
</section>
