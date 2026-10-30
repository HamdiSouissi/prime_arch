<?php
declare(strict_types=1);

/** @var string|null $toastMessage */
?>
<div class="toast-region" aria-live="polite" aria-atomic="true">
    <div class="toast" data-toast hidden role="status">
        <span class="toast__icon" data-toast-icon aria-hidden="true"><svg class="icon icon--small"><use href="<?= e(asset('img/icons.svg')) ?>#check"></use></svg></span>
        <span data-toast-copy><?= e($toastMessage ?? '') ?></span>
        <button class="button button--icon button--ghost" type="button" data-toast-close aria-label="Fermer la notification">
            <svg class="icon icon--small" aria-hidden="true"><use href="<?= e(asset('img/icons.svg')) ?>#x"></use></svg>
        </button>
    </div>
</div>
