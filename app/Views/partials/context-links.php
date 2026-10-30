<?php
declare(strict_types=1);

/**
 * Passerelles contextuelles : une entrée par module qui touche la fiche affichée.
 *
 * Les sections proviennent du filtre `project.detail.sections`, `client.detail.sections`
 * ou `document.detail.actions` : un module décide seul de ce qu'il expose et vérifie ses
 * propres droits. Le noyau se contente de rendre ce qu'on lui donne, sans requête.
 *
 * @var array<int,array{label:string,url:string,icon?:string,meta?:string,tone?:string}> $links
 */
$links = is_array($links ?? null) ? $links : [];
$links = array_values(array_filter($links, static function (mixed $link): bool {
    return is_array($link)
        && trim((string) ($link['label'] ?? '')) !== ''
        && trim((string) ($link['url'] ?? '')) !== '';
}));
if ($links === []) {
    return;
}
$tones = ['info', 'success', 'warning', 'error', 'neutral'];
?>
<nav class="context-links" aria-label="<?= e(__('common.related_modules')) ?>">
    <ul class="context-links__list" role="list">
        <?php foreach ($links as $link): ?>
            <?php $tone = in_array($link['tone'] ?? '', $tones, true) ? (string) $link['tone'] : 'neutral'; ?>
            <li>
                <a class="context-link context-link--<?= e($tone) ?>" href="<?= e((string) $link['url']) ?>">
                    <?php if (($link['icon'] ?? '') !== ''): ?>
                        <svg class="icon icon--small" aria-hidden="true" focusable="false"><use href="<?= e(asset('img/icons.svg')) ?>#<?= e((string) $link['icon']) ?>"></use></svg>
                    <?php endif; ?>
                    <span class="context-link__label"><?= e((string) $link['label']) ?></span>
                    <?php if (($link['meta'] ?? '') !== ''): ?>
                        <span class="context-link__meta"><?= e((string) $link['meta']) ?></span>
                    <?php endif; ?>
                </a>
            </li>
        <?php endforeach; ?>
    </ul>
</nav>
