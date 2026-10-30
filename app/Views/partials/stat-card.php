<?php
declare(strict_types=1);

/** @var string $label */
/** @var string|int|float $value */
/** @var string $icon */
/** @var string $tone */
/** @var string|null $note */
/** @var string|null $trend */
?>
<article class="stat-card <?= e('stat-card--' . ($tone ?? 'blue')) ?>">
    <div class="stat-card__top">
        <span class="stat-card__label"><?= e($label ?? '') ?></span>
        <span class="stat-card__icon" aria-hidden="true"><svg class="icon"><use href="<?= e(asset('img/icons.svg')) ?>#<?= e($icon ?? 'chart') ?>"></use></svg></span>
    </div>
    <p class="stat-card__value number"><?= e($value ?? '') ?></p>
    <?php if (!empty($note) || !empty($trend)): ?>
        <p class="stat-card__note">
            <?php if (!empty($trend)): ?><span class="stat-card__trend"><?= e($trend) ?></span><?php endif; ?>
            <?= e($note ?? '') ?>
        </p>
    <?php endif; ?>
</article>
