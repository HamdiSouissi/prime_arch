<?php
declare(strict_types=1);

use App\Core\HookManager;

/** @var array<int,array{label:string,url:string,icon:string,key?:string,available?:bool}> $items */
/** @var string $activeNav */
/** @var array{name:string,location:string,plan:string} $workspace */

// Les entrées du noyau sont connues avant le filtre : ce qui arrive ensuite provient des
// modules installés. On peut donc séparer visuellement les deux familles sans changer le
// contrat `sidebar.items` (un module n'a rien de nouveau à déclarer).
$coreItems = is_array($items ?? null) ? $items : [];
$coreKeys = [];
foreach ($coreItems as $coreItem) {
    $coreKeys[(string) ($coreItem['key'] ?? '')] = true;
}

$allItems = HookManager::applyFilters('sidebar.items', $coreItems);
$coreNavigation = [];
$moduleNavigation = [];
foreach ($allItems as $navigationItem) {
    $itemKey = (string) ($navigationItem['key'] ?? '');
    if ($itemKey !== '' && isset($coreKeys[$itemKey])) {
        $coreNavigation[] = $navigationItem;
    } else {
        $moduleNavigation[] = $navigationItem;
    }
}

$brandUrl = !empty($showDesignNav)
    ? url('/design/dashboard')
    : (!empty($authenticated) ? url('/dashboard') : url('/'));

/** Rendu d'une entrée de menu ; le même balisage sert aux deux listes. */
$renderItem = static function (array $item) use ($activeNav): void {
    $isCurrent = ($item['key'] ?? '') === ($activeNav ?? '');
    $isAvailable = !array_key_exists('available', $item) || (bool) $item['available'];
    ?>
    <li>
        <?php if ($isAvailable): ?>
            <a class="sidebar-link" href="<?= e($item['url']) ?>"<?= $isCurrent ? ' aria-current="page"' : '' ?> title="<?= e($item['label']) ?>">
                <svg class="icon sidebar-icon" aria-hidden="true" focusable="false"><use href="<?= e(asset('img/icons.svg')) ?>#<?= e($item['icon'] ?? 'grid') ?>"></use></svg>
                <span class="sidebar-link__label"><?= e($item['label']) ?></span>
            </a>
        <?php else: ?>
            <span class="sidebar-link is-disabled" aria-disabled="true" title="<?= e(__('common.not_available')) ?>">
                <svg class="icon sidebar-icon" aria-hidden="true" focusable="false"><use href="<?= e(asset('img/icons.svg')) ?>#<?= e($item['icon'] ?? 'grid') ?>"></use></svg>
                <span class="sidebar-link__label"><?= e($item['label']) ?></span>
            </span>
        <?php endif; ?>
    </li>
    <?php
};
?>
<aside class="sidebar" aria-label="<?= e(__('common.main_navigation')) ?>">
    <?php /* Marque du produit : le nom, puis l'éditeur, puis l'adresse du site (lien distinct :
             deux liens ne peuvent pas être imbriqués). */ ?>
    <div class="sidebar-brand-block">
        <a class="sidebar-brand" href="<?= e($brandUrl) ?>" aria-label="<?= e(__('app.brand_home')) ?>">
            <img class="sidebar-brand__logo" src="<?= e(asset((string) config('brand.logo', 'img/logo-prime-architecture.png'))) ?>" alt="" width="40" height="40" decoding="async">
            <span class="sidebar-brand__product"><?= e((string) config('brand.product', 'Prime Architecture')) ?></span>
        </a>
        <p class="sidebar-brand__caption"><?= e(__('app.brand_vendor', ['vendor' => (string) config('brand.vendor', 'Prime Vision Solutions')])) ?></p>
        <a class="sidebar-brand__site" href="<?= e((string) config('brand.site_url', 'https://primevisionsolutions.fr')) ?>" target="_blank" rel="noopener noreferrer" title="<?= e(__('app.brand_site_label')) ?>"><?= e((string) config('brand.site', 'primevisionsolutions.fr')) ?></a>
    </div>
    <nav class="sidebar-nav" aria-label="<?= e(__('common.main_navigation')) ?>">
        <p class="sidebar-section-label"><?= e(__('common.workspace')) ?></p>
        <ul class="sidebar-list" role="list">
            <?php foreach ($coreNavigation as $navigationItem): ?>
                <?php $renderItem($navigationItem); ?>
            <?php endforeach; ?>
        </ul>
        <?php if ($moduleNavigation !== []): ?>
            <p class="sidebar-section-label sidebar-section-label--modules"><?= e(__('common.external_modules')) ?></p>
            <ul class="sidebar-list sidebar-list--modules" role="list" aria-label="<?= e(__('common.external_modules')) ?>">
                <?php foreach ($moduleNavigation as $navigationItem): ?>
                    <?php $renderItem($navigationItem); ?>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </nav>
    <?php if (!empty($showWorkspace)): ?>
        <div class="sidebar-footer">
            <div class="sidebar-workspace" aria-label="<?= e(__('app.workspace_label', ['name' => (string) ($workspace['name'] ?? '')])) ?>">
                <span class="sidebar-workspace__mark" aria-hidden="true">
                    <svg class="icon icon--small"><use href="<?= e(asset('img/icons.svg')) ?>#building"></use></svg>
                </span>
                <span class="sidebar-workspace__copy">
                    <span class="sidebar-workspace__name"><?= e($workspace['name'] ?? '') ?></span>
                    <span class="sidebar-workspace__meta"><?= e(trim(($workspace['location'] ?? '') . ' · ' . ($workspace['plan'] ?? ''), ' ·')) ?></span>
                </span>
            </div>
        </div>
    <?php endif; ?>
</aside>
