<?php
declare(strict_types=1);

/**
 * Page d’état des liens : aucune donnée du cabinet, aucun détail sur l’existence d’un jeton.
 *
 * @var string $locale
 * @var string $direction
 * @var array<string,string> $cabinet
 * @var string $state  invalid | expired | revoked | throttled
 */
$cabinet = is_array($cabinet ?? null) ? $cabinet : [];
$locale = (string) ($locale ?? 'fr');
$direction = (string) ($direction ?? 'ltr');
$state = in_array((string) ($state ?? 'invalid'), ['invalid', 'expired', 'revoked', 'throttled'], true)
    ? (string) $state
    : 'invalid';

$titles = [
    'invalid' => __('portail.notice.invalid_title'),
    'expired' => __('portail.notice.expired_title'),
    'revoked' => __('portail.notice.revoked_title'),
    'throttled' => __('portail.notice.throttled_title'),
];
$texts = [
    'invalid' => __('portail.notice.invalid_text'),
    'expired' => __('portail.notice.expired_text'),
    'revoked' => __('portail.notice.revoked_text'),
    'throttled' => __('portail.notice.throttled_text'),
];

$pageTitle = $titles[$state];
include __DIR__ . '/partials/head.php';
?>
        <section class="pa-card pa-notice" aria-labelledby="pa-notice-title">
            <p class="pa-eyebrow"><?= e(__('portail.portal')) ?></p>
            <h1 class="pa-notice__title" id="pa-notice-title"><?= e($titles[$state]) ?></h1>
            <p class="pa-notice__text"><?= e($texts[$state]) ?></p>
        </section>
<?php
$generatedAt = '';
include __DIR__ . '/partials/foot.php';
