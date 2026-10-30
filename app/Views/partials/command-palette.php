<?php
declare(strict_types=1);

use App\Controllers\SearchController;

/**
 * Palette de commandes (⌘K / Ctrl K) : navigation, actions et recherche globale.
 *
 * La liste est une `listbox` : le champ garde le focus et désigne l'élément actif par
 * `aria-activedescendant`, donc les flèches ne volent jamais le focus au clavier. Les résultats
 * de la recherche serveur sont ajoutés à chaud dans `[data-command-results]`, les cinq dernières
 * fiches consultées dans la section « Récents ».
 *
 * @var array<int,array{label:string,description:string,url:string,icon:string,group:string}> $commands
 * @var string|null $searchEndpoint
 * @var array{title:string,group:string,url:string,icon:string}|null $recentRecord
 */
$commandGroups = [];
foreach (($commands ?? []) as $command) {
    $commandGroups[$command['group']][] = $command;
}
$searchEndpoint = $searchEndpoint ?? url('/api/recherche');
$recentRecord = is_array($recentRecord ?? null) ? $recentRecord : null;
$groupIndex = 0;
?>
<dialog
    class="command-dialog"
    id="command-palette"
    data-command-palette
    data-search-endpoint="<?= e($searchEndpoint) ?>"
    data-search-min="<?= SearchController::MIN_LENGTH ?>"
    data-search-limit="<?= \App\Models\SearchRepository::LIMIT ?>"
    <?php if ($recentRecord !== null): ?>
        data-recent-record
        data-recent-title="<?= e($recentRecord['title']) ?>"
        data-recent-group="<?= e($recentRecord['group']) ?>"
        data-recent-url="<?= e($recentRecord['url']) ?>"
        data-recent-icon="<?= e($recentRecord['icon']) ?>"
    <?php endif; ?>
    aria-labelledby="command-palette-title"
>
    <h2 class="u-sr-only" id="command-palette-title"><?= e(__('common.global_search')) ?></h2>
    <div class="command-search">
        <svg class="icon icon--large" aria-hidden="true"><use href="<?= e(asset('img/icons.svg')) ?>#search"></use></svg>
        <input
            type="search"
            placeholder="<?= e(__('common.search_placeholder')) ?>"
            aria-label="<?= e(__('common.global_search')) ?>"
            role="combobox"
            aria-expanded="true"
            aria-controls="command-results"
            aria-autocomplete="list"
            aria-describedby="command-palette-hint"
            data-command-search
            autocomplete="off"
            spellcheck="false"
        >
        <button class="button button--small button--secondary" type="button" data-modal-close><?= e(__('common.escape')) ?></button>
    </div>
    <p class="u-sr-only" id="command-palette-hint"><?= e(__('search.hint')) ?></p>
    <div class="command-list" id="command-results" role="listbox" aria-label="<?= e(__('common.global_search')) ?>" data-command-list>
        <?php /* Section remplie par le JavaScript : résultats serveur ou fiches récentes. */ ?>
        <div class="command-results" data-command-results></div>
        <?php foreach ($commandGroups as $group => $groupCommands): ?>
            <?php $groupIndex++; $groupLabelId = 'command-group-label-' . $groupIndex; ?>
            <div class="command-group" role="group" aria-labelledby="<?= e($groupLabelId) ?>" data-command-group>
                <p class="command-list__label" id="<?= e($groupLabelId) ?>"><?= e((string) $group) ?></p>
                <?php foreach ($groupCommands as $index => $command): ?>
                    <a
                        class="command-item"
                        href="<?= e($command['url']) ?>"
                        id="command-static-<?= (int) $groupIndex ?>-<?= (int) $index ?>"
                        role="option"
                        aria-selected="false"
                        tabindex="-1"
                        data-command-item
                        data-command-static
                    >
                        <span class="command-item__icon"><svg class="icon icon--small" aria-hidden="true"><use href="<?= e(asset('img/icons.svg')) ?>#<?= e($command['icon']) ?>"></use></svg></span>
                        <span class="command-item__copy"><span class="command-item__title"><?= e($command['label']) ?></span><span class="command-item__meta"><?= e($command['description']) ?></span></span>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endforeach; ?>
    </div>
    <?php /* Messages hors de la listbox : seuls des groupes d'options y ont leur place. */ ?>
    <p class="command-empty" data-command-empty role="status" hidden><?= e(__('common.no_results')) ?></p>
    <p class="command-loading" data-command-loading hidden><?= e(__('search.loading')) ?></p>
    <p class="u-sr-only" data-command-status role="status" aria-live="polite"></p>
</dialog>
