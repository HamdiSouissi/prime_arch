<?php
declare(strict_types=1);

use App\Core\View;
?>
<?php View::partial('partials/page-header', [
    'pageTitle' => __('dashboard.welcome', ['name' => $userName ?? '']),
    'subtitle' => __('dashboard.foundation_intro'),
    'eyebrow' => $cabinetName ?? config('app.name'),
]) ?>

<section class="foundation-banner card" aria-labelledby="foundation-title">
    <span class="foundation-banner__icon" aria-hidden="true">
        <svg class="icon icon--large"><use href="<?= e(asset('img/icons.svg')) ?>#shield"></use></svg>
    </span>
    <div class="foundation-banner__copy">
        <p class="page-header__eyebrow"><?= e($roleLabel ?? '') ?></p>
        <h2 id="foundation-title"><?= e(__('dashboard.foundation_title')) ?></h2>
        <p><?= e(__('dashboard.foundation_intro')) ?></p>
    </div>
</section>

<div class="stats-grid stats-grid--three foundation-stats">
    <?php View::partial('partials/stat-card', [
        'label' => __('dashboard.stat_account'),
        'value' => $userName ?? '',
        'icon' => 'user',
        'tone' => 'blue',
        'note' => __('dashboard.stat_account_note'),
    ]) ?>
    <?php View::partial('partials/stat-card', [
        'label' => __('dashboard.stat_role'),
        'value' => $roleLabel ?? '',
        'icon' => 'shield',
        'tone' => 'mint',
        'note' => __('dashboard.stat_role_note'),
    ]) ?>
    <?php View::partial('partials/stat-card', [
        'label' => __('dashboard.stat_geography'),
        'value' => ($geographyCounts['governorates'] ?? 0) . ' / ' . ($geographyCounts['delegations'] ?? 0),
        'icon' => 'map-pin',
        'tone' => 'coral',
        'note' => __('dashboard.stat_geography_note'),
    ]) ?>
</div>

<section class="foundation-grid" aria-label="<?= e(__('dashboard.actions_label')) ?>">
    <article class="card foundation-action">
        <span class="foundation-action__icon"><svg class="icon"><use href="<?= e(asset('img/icons.svg')) ?>#building"></use></svg></span>
        <div>
            <h2 class="card-header__title"><?= e(__('dashboard.settings_card_title')) ?></h2>
            <p class="muted text-footnote"><?= e(__('dashboard.settings_card_text')) ?></p>
        </div>
        <a class="button button--secondary" href="<?= e(url('/settings')) ?>"><?= e(__('dashboard.settings_card_action')) ?></a>
    </article>
    <?php if (!empty($canManageUsers)): ?>
        <article class="card foundation-action">
            <span class="foundation-action__icon foundation-action__icon--mint"><svg class="icon"><use href="<?= e(asset('img/icons.svg')) ?>#people"></use></svg></span>
            <div>
                <h2 class="card-header__title"><?= e(__('dashboard.users_card_title')) ?></h2>
                <p class="muted text-footnote"><?= e(__('dashboard.users_card_text')) ?></p>
            </div>
            <a class="button button--secondary" href="<?= e(url('/admin/users')) ?>"><?= e(__('dashboard.users_card_action')) ?></a>
        </article>
    <?php endif; ?>
</section>

<p class="foundation-footnote"><svg class="icon icon--small" aria-hidden="true"><use href="<?= e(asset('img/icons.svg')) ?>#info"></use></svg> <?= e(__('dashboard.fiscal_warning')) ?></p>
