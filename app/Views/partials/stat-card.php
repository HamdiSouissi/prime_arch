<?php
declare(strict_types=1);

/** @var string $label */
/** @var string|int|float $value */
/** @var string $icon */
/** @var string $tone */
/** @var string|null $note */
/** @var string|null $trend */
/** @var string|null $url */
// Un indicateur cliquable mène à la liste filtrée correspondante : un clic de moins pour
// atteindre les fiches, sans changer la carte quand aucune route n'est fournie.
$statTag = !empty($url) ? 'a' : 'article';
?>
<<?= $statTag ?> class="stat-card <?= e('stat-card--' . ($tone ?? 'blue')) ?><?= !empty($url) ? ' stat-card--link' : '' ?>"<?= !empty($url) ? ' href="' . e((string) $url) . '"' : '' ?>>
    <div class="stat-card__top">
        <span class="stat-card__label"><?= e($label ?? '') ?></span>
        <span class="stat-card__icon" aria-hidden="true"><svg class="icon"><use href="<?= e(asset('img/icons.svg')) ?>#<?= e($icon ?? 'chart') ?>"></use></svg></span>
    </div>
    <p class="stat-card__value number<?= mb_strlen((string) ($value ?? ''), 'UTF-8') > 9 ? ' stat-card__value--dense' : '' ?>"><?= e($value ?? '') ?></p>
    <?php if (!empty($note) || !empty($trend)): ?>
        <p class="stat-card__note">
            <?php if (!empty($trend)): ?><span class="stat-card__trend"><?= e($trend) ?></span><?php endif; ?>
            <?= e($note ?? '') ?>
        </p>
    <?php endif; ?>
</<?= $statTag ?>>
