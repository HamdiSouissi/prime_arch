<?php
declare(strict_types=1);

use App\Core\Translator;
?>
<!DOCTYPE html>
<html lang="<?= e(Translator::locale()) ?>" dir="<?= e(Translator::direction()) ?>" data-icons="<?= e(asset('img/icons.svg')) ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="color-scheme" content="light">
    <meta name="theme-color" content="#F4F7FF">
    <title><?= e(($title ?? '') !== '' ? $title . ' — ' : '') ?><?= e(config('app.name')) ?></title>
    <link rel="stylesheet" href="<?= e(asset('css/app.css')) ?>">
</head>
<body>
<a class="skip-link" href="#main">Aller au contenu principal</a>
<?php if (!empty($devOnly)): ?>
    <div class="dev-banner"><span class="dev-banner__dot" aria-hidden="true"></span>DEV ONLY · Écran de démonstration — aucune donnée n'est transmise</div>
<?php endif; ?>
<main id="main" class="auth-layout">
    <?= $content ?>
</main>
<?php \App\Core\View::partial('partials/toast') ?>
<script type="module" src="<?= e(asset('js/app.js')) ?>"></script>
</body>
</html>
