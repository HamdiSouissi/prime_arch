<?php
declare(strict_types=1);

/**
 * Barre d'onglets mobile : quatre destinations quotidiennes puis « Plus ».
 *
 * Le cinquième onglet n'est pas une destination mais l'ouverture de `partials/mobile-menu`,
 * la feuille qui contient **toute** la navigation, groupée par métier et filtrable. Il prend
 * `aria-current` quand la page courante n'est pas dans les quatre onglets (Temps, Réglages,
 * module…), pour que l'utilisateur sache toujours où il est.
 *
 * @var array<int,array{label:string,url:string,icon:string,key?:string,short?:string,available?:bool}> $items
 * @var string $activeNav
 */
$tabKeys = ['dashboard', 'clients', 'projects', 'documents'];
$byKey = [];
foreach (($items ?? []) as $item) {
    $byKey[(string) ($item['key'] ?? '')] = $item;
}

$tabs = [];
foreach ($tabKeys as $key) {
    $item = $byKey[$key] ?? null;
    if ($item === null) {
        continue;
    }
    if (array_key_exists('available', $item) && !$item['available']) {
        continue;
    }
    $tabs[] = $item;
}

$isMoreCurrent = !in_array((string) ($activeNav ?? ''), $tabKeys, true);
?>
<nav class="mobile-tabbar" aria-label="<?= e(__('common.mobile_navigation')) ?>">
    <?php foreach ($tabs as $item): ?>
        <?php $isCurrent = ($item['key'] ?? '') === ($activeNav ?? ''); ?>
        <div class="mobile-tabbar__item">
            <a class="mobile-tabbar__link" href="<?= e($item['url']) ?>"<?= $isCurrent ? ' aria-current="page"' : '' ?> aria-label="<?= e($item['label']) ?>">
                <svg class="icon mobile-tabbar__icon" aria-hidden="true"><use href="<?= e(asset('img/icons.svg')) ?>#<?= e($item['icon'] ?? 'grid') ?>"></use></svg>
                <span><?= e($item['short'] ?? $item['label']) ?></span>
            </a>
        </div>
    <?php endforeach; ?>
    <div class="mobile-tabbar__item">
        <button
            class="mobile-tabbar__link mobile-tabbar__link--more"
            type="button"
            data-modal-open="#mobile-more-menu"
            aria-haspopup="dialog"
            aria-controls="mobile-more-menu"
            aria-expanded="false"
            aria-label="<?= e(__('nav.more_label')) ?>"
            title="<?= e(__('nav.more')) ?>"
            <?= $isMoreCurrent ? 'aria-current="page"' : '' ?>
        >
            <svg class="icon mobile-tabbar__icon" aria-hidden="true"><use href="<?= e(asset('img/icons.svg')) ?>#more"></use></svg>
            <span><?= e(__('nav.more')) ?></span>
        </button>
    </div>
</nav>
