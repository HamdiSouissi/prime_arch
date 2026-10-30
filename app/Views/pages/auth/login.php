<?php
declare(strict_types=1);
?>
<section class="auth-aside" aria-label="<?= e(__('auth.aside_label')) ?>">
    <div class="auth-aside__brand-block">
        <a class="auth-aside__brand" href="<?= e(url(!empty($demoMode) ? '/design/dashboard' : '/')) ?>">
            <img src="<?= e(asset((string) config('brand.logo', 'img/logo-prime-architecture.png'))) ?>" alt="" width="48" height="48" decoding="async">
            <span class="auth-aside__brand-name">
                <span class="auth-aside__brand-product"><?= e((string) config('brand.product', 'Prime Architecture')) ?></span>
                <span class="auth-aside__brand-vendor"><?= e(__('app.brand_vendor', ['vendor' => (string) config('brand.vendor', 'Prime Vision Solutions')])) ?></span>
            </span>
        </a>
        <a class="auth-aside__brand-site" href="<?= e((string) config('brand.site_url', 'https://primevisionsolutions.fr')) ?>" target="_blank" rel="noopener noreferrer" title="<?= e(__('app.brand_site_label')) ?>"><?= e((string) config('brand.site', 'primevisionsolutions.fr')) ?></a>
    </div>
    <div class="auth-aside__message">
        <p class="page-header__eyebrow"><?= e(__('auth.hero_eyebrow')) ?></p>
        <h1><?= e(__('auth.hero_title')) ?></h1>
        <p><?= e(__('auth.hero_text')) ?></p>
    </div>
    <p class="auth-aside__footer"><?= e(__('auth.hero_footer')) ?></p>
</section>
<section class="auth-form-area">
    <div class="auth-card">
        <p class="page-header__eyebrow"><?= e(__('auth.account_access')) ?></p>
        <h2 class="auth-card__heading"><?= e(__('auth.login_heading')) ?></h2>
        <p class="auth-card__intro"><?= e(__('auth.login_intro')) ?></p>
        <?php if (!empty($errorMessage)): ?>
            <p class="form-feedback form-feedback--error" role="alert"><?= e($errorMessage) ?></p>
        <?php endif; ?>
        <form class="auth-card__form" method="post" action="<?= e(!empty($demoMode) ? '#' : url('/login')) ?>"<?= !empty($demoMode) ? ' data-demo-form' : '' ?>>
            <?= csrf_field() ?>
            <?php if (empty($demoMode)): ?>
                <input type="hidden" name="return_to" value="<?= e($returnTo ?? '/dashboard') ?>">
            <?php endif; ?>
            <div class="field">
                <label class="field__label" for="login-email"><?= e(__('auth.email')) ?></label>
                <input class="field__control" id="login-email" type="email" name="email" autocomplete="username" placeholder="vous@cabinet.tn" required maxlength="190">
            </div>
            <div class="field">
                <label class="field__label" for="login-password"><?= e(__('auth.password')) ?></label>
                <input class="field__control" id="login-password" type="password" name="password" autocomplete="current-password" placeholder="••••••••••••" required maxlength="1024">
            </div>
            <button class="button button--primary button--full" type="submit">
                <?= e(__('auth.login_submit')) ?>
                <svg class="icon icon--small icon--directional" aria-hidden="true"><use href="<?= e(asset('img/icons.svg')) ?>#arrow-right"></use></svg>
            </button>
        </form>
        <p class="auth-card__footer"><?= e(__('auth.help')) ?> <a href="mailto:support@primearch.tn"><?= e(__('auth.contact_support')) ?></a></p>
    </div>
</section>
