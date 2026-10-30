<?php
declare(strict_types=1);

/** @var array<int,array{label:string,description:string,url:string,icon:string}> $quickActions */
?>
<dialog class="dialog--sheet dialog--wide" id="quick-add-dialog" aria-labelledby="quick-add-title">
    <div class="dialog__header">
        <div>
            <p class="page-header__eyebrow"><?= e(__('common.quick_action')) ?></p>
            <h2 class="dialog__title" id="quick-add-title"><?= e(__('common.create_item')) ?></h2>
        </div>
        <button class="button button--icon button--ghost" type="button" data-modal-close aria-label="<?= e(__('common.close')) ?>">
            <svg class="icon"><use href="<?= e(asset('img/icons.svg')) ?>#x"></use></svg>
        </button>
    </div>
    <div class="dialog__body quick-add-list">
        <?php foreach (($quickActions ?? []) as $action): ?>
            <a class="quick-add-item" href="<?= e($action['url']) ?>">
                <span class="quick-add-item__icon"><svg class="icon"><use href="<?= e(asset('img/icons.svg')) ?>#<?= e($action['icon']) ?>"></use></svg></span>
                <span class="quick-add-item__copy"><strong><?= e($action['label']) ?></strong><small><?= e($action['description']) ?></small></span>
                <svg class="icon icon--small quick-add-item__arrow icon--directional" aria-hidden="true"><use href="<?= e(asset('img/icons.svg')) ?>#chevron-right"></use></svg>
            </a>
        <?php endforeach; ?>
    </div>
</dialog>
