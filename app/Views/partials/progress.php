<?php
declare(strict_types=1);

/** @var int|float $value */
/** @var string|null $label */
/** @var string|null $tone */
$progressValue = max(0, min(100, (float) ($value ?? 0)));
$progressLabel = $label ?? (number_format($progressValue, 0, ',', ' ') . ' %');
?>
<div class="progress-meta">
    <span><?= e($progressLabel) ?></span>
    <span class="number"><?= e(number_format($progressValue, 0, ',', ' ')) ?> %</span>
</div>
<div class="progress <?= e(!empty($tone) ? 'progress--' . $tone : '') ?>" role="progressbar" aria-label="<?= e($label ?? 'Avancement') ?>" aria-valuemin="0" aria-valuemax="100" aria-valuenow="<?= e((string) round($progressValue)) ?>">
    <div class="progress__bar" style="--progress: <?= e((string) $progressValue) ?>%"></div>
</div>
