<?php
declare(strict_types=1);

/** @var array<int,array{label:string,url:string,icon:string,key?:string,short?:string,available?:bool}> $items */
/** @var string $activeNav */
?>
<nav class="mobile-tabbar" aria-label="<?= e(__('common.mobile_navigation')) ?>">
    <?php foreach (($items ?? []) as $item): ?>
        <?php
        $isCurrent = ($item['key'] ?? '') === ($activeNav ?? '');
        $isAvailable = !array_key_exists('available', $item) || (bool) $item['available'];
        ?>
        <div class="mobile-tabbar__item">
            <?php if ($isAvailable): ?>
                <a class="mobile-tabbar__link" href="<?= e($item['url']) ?>"<?= $isCurrent ? ' aria-current="page"' : '' ?> aria-label="<?= e($item['label']) ?>">
                    <svg class="icon mobile-tabbar__icon" aria-hidden="true"><use href="<?= e(asset('img/icons.svg')) ?>#<?= e($item['icon'] ?? 'grid') ?>"></use></svg>
                    <span><?= e($item['short'] ?? $item['label']) ?></span>
                </a>
            <?php else: ?>
                <span class="mobile-tabbar__link is-disabled" aria-disabled="true" title="<?= e(__('common.not_available')) ?>">
                    <svg class="icon mobile-tabbar__icon" aria-hidden="true"><use href="<?= e(asset('img/icons.svg')) ?>#<?= e($item['icon'] ?? 'grid') ?>"></use></svg>
                    <span><?= e($item['short'] ?? $item['label']) ?></span>
                </span>
            <?php endif; ?>
        </div>
    <?php endforeach; ?>
</nav>
