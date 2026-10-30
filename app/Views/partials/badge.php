<?php
declare(strict_types=1);

/** @var string $label */
/** @var string $tone */
/** @var bool $plain */
?>
<span class="badge badge--<?= e($tone ?? 'neutral') ?><?= e(!empty($plain) ? ' badge--plain' : '') ?>"><?= e($label ?? '') ?></span>
