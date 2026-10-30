<?php
/** @var string $phpVersion */
/** @var bool $phpOk */
/** @var bool $dbOk */
/** @var string $appVersion */
?>
<section class="card">
    <h2><?= e(__('app.install_ok')) ?> ✓</h2>
    <p class="muted"><?= e(__('app.welcome', ['name' => config('app.name')])) ?> — v<?= e($appVersion) ?></p>

    <ul class="status-list">
        <li class="<?= $phpOk ? 'ok' : 'ko' ?>">PHP <?= e($phpVersion) ?> <?= $phpOk ? '' : '(8.2+ requis)' ?></li>
        <li class="<?= $dbOk ? 'ok' : 'ko' ?>">
            <?= e($dbOk ? __('app.db_ok') : __('app.db_ko')) ?>
        </li>
    </ul>

    <?php if (!$dbOk): ?>
        <p class="muted"><?= e(__('app.db_hint')) ?></p>
    <?php endif; ?>
</section>
