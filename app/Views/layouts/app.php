<?php
/** @var string $content */
use App\Core\Translator;
use App\Core\View;
?>
<!DOCTYPE html>
<html lang="<?= e(Translator::locale()) ?>" dir="<?= e(Translator::direction()) ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="color-scheme" content="light dark">
    <title><?= e(($title ?? '') !== '' ? $title . ' — ' : '') ?><?= e(config('app.name')) ?></title>
    <link rel="stylesheet" href="<?= e(asset('css/app.css')) ?>">
</head>
<body>
<div class="app-shell">
    <?php View::partial('partials/sidebar') ?>
    <div class="app-main">
        <?php View::partial('partials/topbar', ['title' => $title ?? '']) ?>
        <main class="app-content" id="main">
            <?= $content ?>
        </main>
    </div>
</div>
<script src="<?= e(asset('js/app.js')) ?>" defer></script>
</body>
</html>
