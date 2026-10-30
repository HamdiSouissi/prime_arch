<?php
declare(strict_types=1);

/**
 * Fil d'Ariane : une seule règle, toujours la même — chaque écran déclare son parent, donc
 * « remonter d'un cran » coûte exactement un clic.
 *
 * @var array<int,array{label:string,url?:string}> $items
 */
$items = is_array($items ?? null) ? $items : [];
$items = array_values(array_filter($items, static fn (mixed $item): bool => is_array($item) && trim((string) ($item['label'] ?? '')) !== ''));
if (count($items) < 2) {
    return;
}
$lastIndex = count($items) - 1;
?>
<nav class="breadcrumb" aria-label="<?= e(__('common.breadcrumb')) ?>">
    <ol class="breadcrumb__list" role="list">
        <?php foreach ($items as $index => $item): ?>
            <?php $isLast = $index === $lastIndex; ?>
            <li class="breadcrumb__item">
                <?php if (!$isLast && trim((string) ($item['url'] ?? '')) !== ''): ?>
                    <a class="breadcrumb__link" href="<?= e((string) $item['url']) ?>"><?= e((string) $item['label']) ?></a>
                <?php else: ?>
                    <span class="breadcrumb__current"<?= $isLast ? ' aria-current="page"' : '' ?>><?= e((string) $item['label']) ?></span>
                <?php endif; ?>
            </li>
        <?php endforeach; ?>
    </ol>
</nav>
