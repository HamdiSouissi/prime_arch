<?php
use App\Core\HookManager;

// Les modules ajoutent leurs entrées via le filtre "sidebar.items".
$items = HookManager::applyFilters('sidebar.items', [
    ['label' => __('nav.home'), 'url' => url('/'), 'icon' => '⌂'],
    // TODO : Clients, Projets, Devis & factures, Paramètres (toute destination à ≤ 3 clics)
]);
?>
<aside class="sidebar" aria-label="<?= e(__('nav.main')) ?>">
    <div class="sidebar-brand"><?= e(config('app.name')) ?></div>
    <nav>
        <ul class="sidebar-list">
            <?php foreach ($items as $item): ?>
                <li>
                    <a class="sidebar-link" href="<?= e($item['url']) ?>">
                        <span class="sidebar-icon" aria-hidden="true"><?= e($item['icon'] ?? '•') ?></span>
                        <span><?= e($item['label']) ?></span>
                    </a>
                </li>
            <?php endforeach; ?>
        </ul>
    </nav>
</aside>
