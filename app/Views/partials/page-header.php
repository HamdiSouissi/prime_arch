<?php
declare(strict_types=1);

/** @var string $pageTitle */
/** @var string|null $subtitle */
/** @var string|null $eyebrow */
/** @var array<string,string>|null $primaryAction */
/** @var array<string,string>|null $secondaryAction */
?>
<header class="page-header" data-large-title>
    <div class="page-header__copy">
        <?php if (!empty($eyebrow)): ?>
            <p class="page-header__eyebrow"><?= e($eyebrow) ?></p>
        <?php endif; ?>
        <h1 class="page-header__title"><?= e($pageTitle ?? '') ?></h1>
        <?php if (!empty($subtitle)): ?>
            <p class="page-header__subtitle"><?= e($subtitle) ?></p>
        <?php endif; ?>
    </div>
    <?php if (!empty($primaryAction) || !empty($secondaryAction)): ?>
        <div class="page-header__actions">
            <?php if (!empty($secondaryAction)): ?>
                <a class="button button--secondary" href="<?= e($secondaryAction['url'] ?? '#') ?>">
                    <?php if (!empty($secondaryAction['icon'])): ?><svg class="icon icon--small<?= e(in_array($secondaryAction['icon'], ['arrow-up-right', 'arrow-right', 'arrow-left', 'chevron-right', 'chevron-left'], true) ? ' icon--directional' : '') ?>" aria-hidden="true"><use href="<?= e(asset('img/icons.svg')) ?>#<?= e($secondaryAction['icon']) ?>"></use></svg><?php endif; ?>
                    <?= e($secondaryAction['label'] ?? '') ?>
                </a>
            <?php endif; ?>
            <?php if (!empty($primaryAction)): ?>
                <?php if (!empty($primaryAction['dialog'])): ?>
                    <button class="button button--primary" type="button" data-modal-open="<?= e($primaryAction['dialog']) ?>">
                        <?php if (!empty($primaryAction['icon'])): ?><svg class="icon icon--small<?= e(in_array($primaryAction['icon'], ['arrow-up-right', 'arrow-right', 'arrow-left', 'chevron-right', 'chevron-left'], true) ? ' icon--directional' : '') ?>" aria-hidden="true"><use href="<?= e(asset('img/icons.svg')) ?>#<?= e($primaryAction['icon']) ?>"></use></svg><?php endif; ?>
                        <?= e($primaryAction['label'] ?? '') ?>
                    </button>
                <?php else: ?>
                    <a class="button button--primary" href="<?= e($primaryAction['url'] ?? '#') ?>">
                        <?php if (!empty($primaryAction['icon'])): ?><svg class="icon icon--small<?= e(in_array($primaryAction['icon'], ['arrow-up-right', 'arrow-right', 'arrow-left', 'chevron-right', 'chevron-left'], true) ? ' icon--directional' : '') ?>" aria-hidden="true"><use href="<?= e(asset('img/icons.svg')) ?>#<?= e($primaryAction['icon']) ?>"></use></svg><?php endif; ?>
                        <?= e($primaryAction['label'] ?? '') ?>
                    </a>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</header>
