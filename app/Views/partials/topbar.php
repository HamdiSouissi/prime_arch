<?php
declare(strict_types=1);

/** @var string $title */
$headerNow = new \DateTimeImmutable('now', new \DateTimeZone((string) config('tunisia.timezone', 'Africa/Tunis')));
/** @var array{name:string,location:string,plan:string} $workspace */
/** @var array{name:string,initials:string} $userProfile */
?>
<header class="topbar" aria-label="Barre d'outils">
    <div class="topbar__context">
        <?php if (!empty($workspace['name'])): ?>
            <span class="topbar__eyebrow"><?= e($workspace['name']) ?></span>
        <?php endif; ?>
        <span class="topbar__title"><?= e($title ?? '') ?></span>
    </div>
    <div class="topbar__actions">
        <button class="button button--icon button--ghost" type="button" data-mobile-menu-toggle aria-expanded="false" aria-label="Ouvrir le menu" title="Navigation">
            <svg class="icon"><use href="<?= e(asset('img/icons.svg')) ?>#menu"></use></svg>
        </button>
        <div class="topbar-info" role="group" aria-label="<?= e(__('app.local_time')) ?>">
            <span class="topbar-info__place"><svg class="icon icon--small" aria-hidden="true"><use href="<?= e(asset('img/icons.svg')) ?>#map-pin"></use></svg><?= e(__('app.tunis')) ?></span>
            <time class="topbar-info__clock" datetime="<?= e($headerNow->format(DATE_ATOM)) ?>" data-local-clock data-timezone="<?= e((string) config('tunisia.timezone', 'Africa/Tunis')) ?>" data-clock-label="<?= e(__('app.local_time')) ?>" aria-label="<?= e(__('app.local_time') . ' · ' . $headerNow->format('d/m/Y H:i')) ?>">
                <span class="topbar-info__date" data-clock-date><?= e($headerNow->format('d/m/Y')) ?></span>
                <strong data-clock-time><?= e($headerNow->format('H:i')) ?></strong>
            </time>
        </div>
        <?php if (!empty($showDesignActions) || !empty($showAppActions)): ?>
            <button class="topbar-search" type="button" data-command-open aria-haspopup="dialog" aria-controls="command-palette">
                <svg class="icon icon--small" aria-hidden="true"><use href="<?= e(asset('img/icons.svg')) ?>#search"></use></svg>
                <span class="topbar-search__label"><?= e('Rechercher dans ' . config('app.name') . '…') ?></span>
                <kbd class="topbar-search__keys"><span>⌘</span><span>K</span></kbd>
            </button>
        <?php endif; ?>
        <?php if (!empty($showDesignActions)): ?>
            <button class="button button--primary" type="button" data-modal-open="#quick-add-dialog" aria-haspopup="dialog">
                <svg class="icon icon--small" aria-hidden="true"><use href="<?= e(asset('img/icons.svg')) ?>#plus"></use></svg>
                <span class="u-desktop-only">Nouveau</span>
            </button>
            <button class="button button--icon button--secondary" type="button" data-toast-message="Vous êtes à jour : aucune nouvelle notification." aria-label="Notifications">
                <svg class="icon"><use href="<?= e(asset('img/icons.svg')) ?>#bell"></use></svg>
            </button>
            <button class="topbar-avatar" type="button" data-toast-message="<?= e('Profil de ' . ($userProfile['name'] ?? '')) ?>" aria-label="<?= e('Profil de ' . ($userProfile['name'] ?? '')) ?>"><?= e($userProfile['initials'] ?? '') ?></button>
        <?php elseif (!empty($showAppActions)): ?>
            <form class="topbar-logout" method="post" action="<?= e(url('/logout')) ?>">
                <?= csrf_field() ?>
                <button class="button button--secondary" type="submit" aria-label="<?= e(__('auth.logout')) ?>">
                    <svg class="icon icon--small" aria-hidden="true"><use href="<?= e(asset('img/icons.svg')) ?>#log-out"></use></svg>
                    <span class="u-desktop-only"><?= e(__('auth.logout')) ?></span>
                </button>
            </form>
            <span class="topbar-avatar" role="img" aria-label="<?= e($userProfile['name'] ?? '') ?>"><?= e($userProfile['initials'] ?? '') ?></span>
        <?php endif; ?>
    </div>
</header>
