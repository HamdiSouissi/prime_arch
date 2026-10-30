<?php
declare(strict_types=1);

/** @var string $title */
/** @var string $description */
/** @var string|null $image */
/** @var string|null $imageAlt */
/** @var string|null $actionLabel */
/** @var string|null $actionUrl */
/** @var bool|null $compact */
?>
<section class="empty-state<?= !empty($compact) ? ' empty-state--compact' : '' ?>" aria-labelledby="empty-state-title">
    <?php if (!empty($image)): ?>
        <img class="empty-state__image" src="<?= e($image) ?>" alt="<?= e($imageAlt ?? '') ?>" width="240" height="176" loading="lazy">
    <?php endif; ?>
    <h2 class="empty-state__title" id="empty-state-title"><?= e($title ?? '') ?></h2>
    <p class="empty-state__description"><?= e($description ?? '') ?></p>
    <?php if (!empty($actionLabel)): ?>
        <a class="button button--secondary" href="<?= e($actionUrl ?? '#') ?>"><?= e($actionLabel) ?></a>
    <?php endif; ?>
</section>
