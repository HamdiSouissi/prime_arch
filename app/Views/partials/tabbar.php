<?php
declare(strict_types=1);

/** @var array<int,array{label:string,url:string,icon:string,key?:string,short?:string}> $items */
/** @var string $activeNav */
?>
<nav class="mobile-tabbar" aria-label="Navigation mobile">
    <?php foreach (($items ?? []) as $item): ?>
        <?php $isCurrent = ($item['key'] ?? '') === ($activeNav ?? ''); ?>
        <a class="mobile-tabbar__link" href="<?= e($item['url']) ?>" aria-current="<?= e($isCurrent ? 'page' : 'false') ?>" aria-label="<?= e($item['label']) ?>">
            <svg class="icon mobile-tabbar__icon" aria-hidden="true"><use href="<?= e(asset('img/icons.svg')) ?>#<?= e($item['icon'] ?? 'grid') ?>"></use></svg>
            <span><?= e($item['short'] ?? $item['label']) ?></span>
        </a>
    <?php endforeach; ?>
</nav>
