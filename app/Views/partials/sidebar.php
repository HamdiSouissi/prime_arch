<?php
declare(strict_types=1);

use App\Core\HookManager;

/** @var array<int,array{label:string,url:string,icon:string,key?:string}> $items */
/** @var string $activeNav */
/** @var array{name:string,location:string,plan:string} $workspace */
$items = HookManager::applyFilters('sidebar.items', $items ?? []);
$brandUrl = !empty($showDesignNav) ? url('/design/dashboard') : url('/');
?>
<aside class="sidebar" aria-label="Navigation principale">
    <a class="sidebar-brand" href="<?= e($brandUrl) ?>" aria-label="Prime Arch, tableau de bord">
        <img class="sidebar-brand__logo" src="<?= e(asset('img/logo-placeholder.svg')) ?>" alt="" width="44" height="44">
        <span class="sidebar-brand__name">Prime Arch<span class="sidebar-brand__caption">Studio d'architecture</span></span>
    </a>
    <nav class="sidebar-nav" aria-label="Navigation principale">
        <p class="sidebar-section-label">Espace de travail</p>
        <ul class="sidebar-list" role="list">
            <?php foreach ($items as $item): ?>
                <?php $isCurrent = ($item['key'] ?? '') === ($activeNav ?? ''); ?>
                <li>
                    <a class="sidebar-link" href="<?= e($item['url']) ?>" aria-current="<?= e($isCurrent ? 'page' : 'false') ?>" title="<?= e($item['label']) ?>">
                        <svg class="icon sidebar-icon" aria-hidden="true" focusable="false"><use href="<?= e(asset('img/icons.svg')) ?>#<?= e($item['icon'] ?? 'grid') ?>"></use></svg>
                        <span class="sidebar-link__label"><?= e($item['label']) ?></span>
                    </a>
                </li>
            <?php endforeach; ?>
        </ul>
    </nav>
    <?php if (!empty($showDesignNav)): ?>
        <div class="sidebar-footer">
            <div class="sidebar-workspace" aria-label="<?= e('Espace ' . ($workspace['name'] ?? '')) ?>">
                <span class="sidebar-workspace__mark" aria-hidden="true">
                    <svg class="icon icon--small"><use href="<?= e(asset('img/icons.svg')) ?>#building"></use></svg>
                </span>
                <span class="sidebar-workspace__copy">
                    <span class="sidebar-workspace__name"><?= e($workspace['name'] ?? '') ?></span>
                    <span class="sidebar-workspace__meta"><?= e(($workspace['location'] ?? '') . ' · ' . ($workspace['plan'] ?? '')) ?></span>
                </span>
            </div>
        </div>
    <?php endif; ?>
</aside>
