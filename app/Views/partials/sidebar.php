<?php
declare(strict_types=1);

use App\Services\NavigationSections;

/**
 * Menu latéral groupé par métier (lot 3).
 *
 * Les entrées arrivent **déjà filtrées** par `sidebar.items` (appliqué une seule fois dans le
 * layout, pour que le menu, la barre d'onglets et la feuille mobile montrent exactement la même
 * navigation). Chaque section est une liste nommée : les lecteurs d'écran annoncent
 * « Production, liste de 3 éléments », et une section vide n'est jamais rendue.
 *
 * @var array<int,array{label:string,url:string,icon:string,key?:string,available?:bool,section?:string}> $items
 * @var string $activeNav
 * @var array{name:string,location:string,plan:string} $workspace
 */
$navigationItems = is_array($items ?? null) ? $items : [];
$sections = NavigationSections::group($navigationItems);

$brandUrl = !empty($showDesignNav)
    ? url('/design/dashboard')
    : (!empty($authenticated) ? url('/dashboard') : url('/'));

/** Rendu d'une entrée de menu ; le même balisage sert à toutes les sections. */
$renderItem = static function (array $item) use ($activeNav): void {
    $isCurrent = ($item['key'] ?? '') === ($activeNav ?? '');
    $isAvailable = !array_key_exists('available', $item) || (bool) $item['available'];
    $label = (string) ($item['label'] ?? '');
    ?>
    <li>
        <?php if ($isAvailable): ?>
            <a class="sidebar-link" href="<?= e($item['url']) ?>"<?= $isCurrent ? ' aria-current="page"' : '' ?> title="<?= e($label) ?>">
                <svg class="icon sidebar-icon" aria-hidden="true" focusable="false"><use href="<?= e(asset('img/icons.svg')) ?>#<?= e($item['icon'] ?? 'grid') ?>"></use></svg>
                <span class="sidebar-link__label"><?= e($label) ?></span>
            </a>
        <?php else: ?>
            <span class="sidebar-link is-disabled" aria-disabled="true" title="<?= e(__('common.not_available')) ?>">
                <svg class="icon sidebar-icon" aria-hidden="true" focusable="false"><use href="<?= e(asset('img/icons.svg')) ?>#<?= e($item['icon'] ?? 'grid') ?>"></use></svg>
                <span class="sidebar-link__label"><?= e($label) ?></span>
            </span>
        <?php endif; ?>
    </li>
    <?php
};
?>
<aside class="sidebar" aria-label="<?= e(__('common.main_navigation')) ?>">
    <?php /* Marque du produit seule : l'éditeur, le site et le copyright sont en pied de page. */ ?>
    <div class="sidebar-brand-block">
        <a class="sidebar-brand" href="<?= e($brandUrl) ?>" aria-label="<?= e(__('app.brand_home')) ?>">
            <img class="sidebar-brand__logo" src="<?= e(asset((string) config('brand.logo', 'img/logo-prime-architecture.png'))) ?>" alt="" width="40" height="40" decoding="async">
            <span class="sidebar-brand__product"><?= e((string) config('brand.product', 'Prime Architecture')) ?></span>
        </a>
    </div>
    <nav class="sidebar-nav" aria-label="<?= e(__('common.main_navigation')) ?>">
        <?php foreach ($sections as $section): ?>
            <?php $labelId = 'sidebar-section-' . $section['key']; ?>
            <p
                class="sidebar-section-label<?= $section['modules'] ? ' sidebar-section-label--modules' : '' ?>"
                id="<?= e($labelId) ?>"
            ><?= e($section['label']) ?></p>
            <ul
                class="sidebar-list<?= $section['modules'] ? ' sidebar-list--modules' : '' ?>"
                role="list"
                aria-labelledby="<?= e($labelId) ?>"
            >
                <?php foreach ($section['items'] as $navigationItem): ?>
                    <?php $renderItem($navigationItem); ?>
                <?php endforeach; ?>
            </ul>
        <?php endforeach; ?>
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
