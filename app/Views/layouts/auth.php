<?php
declare(strict_types=1);

use App\Core\Translator;
?>
<!DOCTYPE html>
<html lang="<?= e(Translator::locale()) ?>" dir="<?= e(Translator::direction()) ?>" data-icons="<?= e(asset('img/icons.svg')) ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="color-scheme" content="light dark">
    <meta name="theme-color" content="#F4F7FF" media="(prefers-color-scheme: light)">
    <meta name="theme-color" content="#111821" media="(prefers-color-scheme: dark)">
    <title><?= e(($title ?? '') !== '' ? $title . ' — ' : '') ?><?= e(config('app.name')) ?></title>
    <link rel="icon" type="image/png" sizes="32x32" href="<?= e(asset('img/favicon.png')) ?>">
    <link rel="apple-touch-icon" sizes="180x180" href="<?= e(asset('img/logo-prime-architecture.png')) ?>">
    <link rel="stylesheet" href="<?= e(asset('css/app.css')) ?>">
</head>
<body>
<a class="skip-link" href="#main"><?= e(__('common.skip_to_content')) ?></a>
<?php if (!empty($devOnly)): ?>
    <div class="dev-banner"><span class="dev-banner__dot" aria-hidden="true"></span><?= e(__('app.demo_banner_auth')) ?></div>
<?php endif; ?>
<div class="auth-locale">
    <?php \App\Core\View::partial('partials/language-switcher', ['variant' => 'labeled', 'returnTo' => '/login']) ?>
</div>
<div class="auth-shell">
    <main id="main" class="auth-layout">
        <?= $content ?>
    </main>
    <?php \App\Core\View::partial('partials/brand-footer') ?>
</div>
<?php \App\Core\View::partial('partials/toast') ?>
<?php \App\Core\View::partial('partials/i18n') ?>
<script type="module" src="<?= e(asset('js/app.js')) ?>"></script>
</body>
</html>
