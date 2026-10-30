<?php
declare(strict_types=1);

/** @var array<int,array{label:string,description:string,url:string,icon:string,group:string}> $commands */
$commandGroups = [];
foreach (($commands ?? []) as $command) {
    $commandGroups[$command['group']][] = $command;
}
?>
<dialog class="command-dialog" id="command-palette" data-command-palette aria-labelledby="command-palette-title">
    <h2 class="u-sr-only" id="command-palette-title"><?= e(__('common.global_search')) ?></h2>
    <div class="command-search">
        <svg class="icon icon--large" aria-hidden="true"><use href="<?= e(asset('img/icons.svg')) ?>#search"></use></svg>
        <input type="search" placeholder="<?= e(__('common.search_placeholder')) ?>" aria-label="<?= e(__('common.global_search')) ?>" data-command-search autocomplete="off">
        <button class="button button--small button--secondary" type="button" data-modal-close><?= e(__('common.escape')) ?></button>
    </div>
    <div class="command-list" role="group" aria-label="<?= e(__('common.global_search')) ?>">
        <?php foreach ($commandGroups as $group => $groupCommands): ?>
            <p class="command-list__label"><?= e($group) ?></p>
            <?php foreach ($groupCommands as $command): ?>
                <a class="command-item" href="<?= e($command['url']) ?>" data-command-item>
                    <span class="command-item__icon"><svg class="icon icon--small" aria-hidden="true"><use href="<?= e(asset('img/icons.svg')) ?>#<?= e($command['icon']) ?>"></use></svg></span>
                    <span class="command-item__copy"><span class="command-item__title"><?= e($command['label']) ?></span><span class="command-item__meta"><?= e($command['description']) ?></span></span>
                </a>
            <?php endforeach; ?>
        <?php endforeach; ?>
        <p class="command-empty" data-command-empty role="status" hidden><?= e(__('common.no_results')) ?></p>
    </div>
</dialog>
