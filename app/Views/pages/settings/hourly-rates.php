<?php
declare(strict_types=1);

use App\Core\View;

$rates = is_array($rates ?? null) ? $rates : [];
$users = is_array($users ?? null) ? $users : [];
$values = is_array($values ?? null) ? $values : [];
$errors = is_array($errors ?? null) ? $errors : [];
?>
<?php View::partial('partials/page-header', [
    'pageTitle' => __('finance.rates_title'),
    'eyebrow' => __('nav.settings'),
    'subtitle' => __('finance.rates_intro'),
    'secondaryAction' => ['label' => __('nav.projects'), 'icon' => 'arrow-left', 'url' => url('/projects')],
]) ?>

<?php if (!empty($notice)): ?><p class="form-feedback form-feedback--success" role="status"><?= e($notice) ?></p><?php endif; ?>
<?php if (!empty($errors['general'])): ?><p class="form-feedback form-feedback--error" role="alert"><?= e($errors['general']) ?></p><?php endif; ?>

<div class="content-grid project-detail-grid">
    <?php if (!empty($canManage)): ?>
        <section class="card stack-md" aria-labelledby="hourly-rate-form-title">
            <div class="card-header"><div><h2 class="card-header__title" id="hourly-rate-form-title"><?= e(__('finance.rate_add')) ?></h2><p class="section-heading__note"><?= e(__('finance.rates_intro')) ?></p></div></div>
            <?php if ($users === []): ?>
                <?php View::partial('partials/empty-state', ['title' => __('finance.no_rates'), 'description' => __('finance.no_rates_help')]) ?>
            <?php else: ?>
                <form class="settings-form" method="post" action="<?= e(url('/settings/hourly-rates')) ?>">
                    <?= csrf_field() ?>
                    <div class="field"><label class="field__label" for="rate-user"><?= e(__('finance.rate_user')) ?></label><select class="field__control" id="rate-user" name="user_id" required><option value=""><?= e(__('common.choose')) ?></option><?php foreach ($users as $user): ?><?php if ($user['is_active']): ?><option value="<?= e((string) $user['id']) ?>" <?= (string) ($values['user_id'] ?? '') === (string) $user['id'] ? 'selected' : '' ?>><?= e($user['name']) ?> · <?= e($user['email']) ?></option><?php endif; ?><?php endforeach; ?></select><?php if (!empty($errors['user_id'])): ?><span class="field__hint field__hint--error"><?= e($errors['user_id']) ?></span><?php endif; ?></div>
                    <div class="field"><label class="field__label" for="rate-effective-from"><?= e(__('finance.effective_from')) ?></label><input class="field__control" id="rate-effective-from" type="date" name="effective_from" required value="<?= e($values['effective_from'] ?? date('Y-m-d')) ?>"><?php if (!empty($errors['effective_from'])): ?><span class="field__hint field__hint--error"><?= e($errors['effective_from']) ?></span><?php endif; ?></div>
                    <div class="field"><label class="field__label" for="hourly-cost-rate"><?= e(__('finance.hourly_rate')) ?></label><input class="field__control" id="hourly-cost-rate" type="number" name="cost_rate_per_hour" min="0" step="0.001" inputmode="decimal" required value="<?= e($values['cost_rate_per_hour'] ?? '') ?>"><?php if (!empty($errors['cost_rate_per_hour'])): ?><span class="field__hint field__hint--error"><?= e($errors['cost_rate_per_hour']) ?></span><?php endif; ?></div>
                    <div class="field"><label class="field__label" for="rate-note"><?= e(__('finance.rate_note')) ?></label><textarea class="field__control" id="rate-note" name="note" rows="3" maxlength="255"><?= e($values['note'] ?? '') ?></textarea><span class="field__hint"><?= e(__('finance.rate_note_help')) ?></span><?php if (!empty($errors['note'])): ?><span class="field__hint field__hint--error"><?= e($errors['note']) ?></span><?php endif; ?></div>
                    <div class="form-actions"><button class="button button--primary" type="submit"><?= e(__('finance.rate_add')) ?></button></div>
                </form>
            <?php endif; ?>
        </section>
    <?php endif; ?>

    <section class="card stack-md<?= empty($canManage) ? ' form-grid__full' : '' ?>" aria-labelledby="hourly-rate-history-title">
        <div class="card-header"><div><h2 class="card-header__title" id="hourly-rate-history-title"><?= e(__('finance.rate_history')) ?></h2><p class="section-heading__note"><?= e(__('common.results', ['count' => (string) count($rates)])) ?></p></div></div>
        <?php if ($rates === []): ?>
            <?php View::partial('partials/empty-state', ['title' => __('finance.no_rates'), 'description' => __('finance.no_rates_help')]) ?>
        <?php else: ?>
            <div class="table-wrap"><table class="data-table"><thead><tr><th scope="col"><?= e(__('finance.user')) ?></th><th scope="col"><?= e(__('finance.effective_from')) ?></th><th scope="col"><?= e(__('finance.hourly_rate')) ?></th><th scope="col"><?= e(__('finance.rate_note')) ?></th><th scope="col"><?= e(__('finance.rate_created_at')) ?></th></tr></thead><tbody>
                <?php foreach ($rates as $rate): ?>
                    <tr><td><?= e($rate['user_name']) ?></td><td><?= e(date('d/m/Y', strtotime((string) $rate['effective_from']))) ?></td><td><?= e(money_format_tnd((string) $rate['cost_rate_per_hour'])) ?></td><td><?= e($rate['note'] ?? '—') ?></td><td><?= e(date('d/m/Y', strtotime((string) $rate['created_at']))) ?><?php if (!empty($rate['created_by_name'])): ?><span class="table-primary__meta"><?= e($rate['created_by_name']) ?></span><?php endif; ?></td></tr>
                <?php endforeach; ?>
            </tbody></table></div>
        <?php endif; ?>
    </section>
</div>
