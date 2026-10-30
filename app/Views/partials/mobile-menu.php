<?php
declare(strict_types=1);

use App\Services\NavigationSections;

/**
 * Feuille « Plus » de la navigation mobile (lot 3).
 *
 * Elle reprend exactement les sections du menu latéral, avec un champ de filtre : sur un
 * téléphone, une entrée de module reste à deux gestes (Plus, puis l'entrée), sans tiroir
 * latéral ni perte de contexte. Les entrées indisponibles sont omises.
 *
 * @var array<int,array{label:string,url:string,icon:string,key?:string,available?:bool,section?:string}> $items
 * @var string $activeNav
 * @var array{name:string,location:string,plan:string} $workspace
 */
$menuItems = is_array($items ?? null) ? $items : [];
$menuItems = array_values(array_filter(
    $menuItems,
    static fn (array $item): bool => !array_key_exists('available', $item) || (bool) $item['available']
));
$menuSections = NavigationSections::group($menuItems);
?>
<dialog class="mobile-menu" id="mobile-more-menu" aria-labelledby="mobile-more-title" data-mobile-menu>
    <div class="mobile-menu__header">
        <div>
            <p class="page-header__eyebrow"><?= e(__('nav.more_intro')) ?></p>
            <h2 class="mobile-menu__title" id="mobile-more-title"><?= e(__('nav.more')) ?></h2>
        </div>
        <button class="button button--icon button--ghost" type="button" data-modal-close aria-label="<?= e(__('common.close')) ?>">
            <svg class="icon"><use href="<?= e(asset('img/icons.svg')) ?>#x"></use></svg>
        </button>
    </div>
    <div class="mobile-menu__search">
        <svg class="icon icon--small" aria-hidden="true"><use href="<?= e(asset('img/icons.svg')) ?>#search"></use></svg>
        <input
            type="search"
            placeholder="<?= e(__('common.search')) ?>"
            aria-label="<?= e(__('common.search')) ?>"
            data-mobile-menu-search
            autocomplete="off"
            spellcheck="false"
        >
    </div>
    <div class="mobile-menu__body" data-mobile-menu-list>
        <?php foreach ($menuSections as $menuSection): ?>
            <?php $headingId = 'mobile-menu-section-' . $menuSection['key']; ?>
            <section class="mobile-menu__section" aria-labelledby="<?= e($headingId) ?>" data-mobile-menu-group>
                <p class="mobile-menu__label" id="<?= e($headingId) ?>"><?= e($menuSection['label']) ?></p>
                <ul class="mobile-menu__list" role="list" aria-labelledby="<?= e($headingId) ?>">
                    <?php foreach ($menuSection['items'] as $menuItem): ?>
                        <?php
                        if (array_key_exists('available', $menuItem) && !$menuItem['available']) {
                            continue;
                        }
                        $isCurrent = ($menuItem['key'] ?? '') === ($activeNav ?? '');
                        ?>
                        <li>
                            <a class="mobile-menu__link" href="<?= e($menuItem['url']) ?>"<?= $isCurrent ? ' aria-current="page"' : '' ?> data-mobile-menu-item>
                                <span class="mobile-menu__icon"><svg class="icon icon--small" aria-hidden="true"><use href="<?= e(asset('img/icons.svg')) ?>#<?= e($menuItem['icon'] ?? 'grid') ?>"></use></svg></span>
                                <span class="mobile-menu__link-label"><?= e($menuItem['label']) ?></span>
                                <svg class="icon icon--small mobile-menu__chevron icon--directional" aria-hidden="true"><use href="<?= e(asset('img/icons.svg')) ?>#chevron-right"></use></svg>
                            </a>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </section>
        <?php endforeach; ?>
        <p class="mobile-menu__empty" data-mobile-menu-empty role="status" hidden><?= e(__('common.no_results')) ?></p>
    </div>
    <?php
    // L'en-tête mobile masque l'horloge pour rester sur une seule ligne : elle reste
    // consultable ici, avec le même fuseau et la même mise à jour.
    $menuNow = new \DateTimeImmutable('now', new \DateTimeZone((string) config('tunisia.timezone', 'Africa/Tunis')));
    ?>
    <div class="mobile-menu__footer">
        <?php if (!empty($workspace['name'])): ?>
            <span class="mobile-menu__workspace"><?= e($workspace['name']) ?></span>
            <span class="mobile-menu__workspace-meta"><?= e(trim(($workspace['location'] ?? '') . ' · ' . ($workspace['plan'] ?? ''), ' ·')) ?></span>
        <?php endif; ?>
        <time
            class="mobile-menu__clock"
            dir="ltr"
            datetime="<?= e($menuNow->format(DATE_ATOM)) ?>"
            data-local-clock
            data-timezone="<?= e((string) config('tunisia.timezone', 'Africa/Tunis')) ?>"
            data-clock-label="<?= e(__('app.local_time')) ?>"
            aria-label="<?= e(__('app.local_time') . ' · ' . $menuNow->format('d/m/Y H:i')) ?>"
        ><?= e($menuNow->format('d/m/Y H:i')) ?></time>
    </div>
</dialog>
