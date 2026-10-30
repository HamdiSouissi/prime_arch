<?php
declare(strict_types=1);

/**
 * Messages destinés au JavaScript (public/assets/js/ui.js).
 * Le JSON est échappé pour ne jamais interrompre le document HTML.
 *
 * @var array<int,string> $keys Clés de traduction à exposer (préfixe js.).
 */
$keys = [
    'js.custom_field_required',
    'js.custom_field_added',
    'js.custom_field_removed',
    'js.remove_field_named',
    'js.timer_start',
    'js.timer_pause',
    'js.timer_running',
    'js.timer_ready',
    'js.timer_reset',
    'js.choose_delegation',
    'js.postal_unavailable',
    'js.delegations_error',
    'js.choose_rate',
    'js.upload_zip_only',
    'js.archive_zip_required',
    'js.archive_too_large',
    'js.archive_too_large_label',
    'js.archive_browse_hint',
    'js.archive_preview_ready',
    'js.archive_selected',
    'js.activated',
    'js.deactivated',
    'js.module_activate_label',
    'js.module_deactivate_label',
    'js.action_done',
    'js.demo_only',
    'js.copied',
    // Recherche globale (palette de commandes).
    'js.search_recents',
    'js.search_count',
    'js.search_none',
    'js.search_unavailable',
];

$payload = [];
foreach ($keys as $key) {
    $payload[$key] = __($key);
}
?>
<script type="application/json" id="app-i18n"><?= json_encode(
    $payload,
    JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
) ?></script>
