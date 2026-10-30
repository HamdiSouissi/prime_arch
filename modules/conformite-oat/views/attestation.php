<?php
declare(strict_types=1);

use App\Core\Translator;

/**
 * Attestation d’assurance imprimable, rendue hors du layout applicatif.
 *
 * Le document reprend uniquement les données saisies par le cabinet : il ne remplace pas
 * l’attestation originale de l’assureur, ce que rappelle la mention en bas de page.
 */
$project = is_array($project ?? null) ? $project : [];
$assurance = is_array($assurance ?? null) ? $assurance : [];
$cabinet = is_array($cabinet ?? null) ? $cabinet : [];
$today = (string) ($today ?? date('Y-m-d'));
$coverage = $coverage ?? null;
$dateLabel = static fn (?string $value): string => $value !== null && $value !== ''
    ? date('d/m/Y', (int) strtotime($value))
    : '—';
$cabinetName = trim((string) ($cabinet['name'] ?? ''));
$cabinetAddress = trim((string) ($cabinet['address'] ?? ''));
?>
<!DOCTYPE html>
<html lang="<?= e(Translator::locale()) ?>" dir="<?= e(Translator::direction()) ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title><?= e(__('conformite.attestation_title')) ?> — <?= e((string) ($project['name'] ?? '')) ?></title>
    <link rel="stylesheet" href="<?= e(asset('css/app.css')) ?>">
</head>
<body>
<main class="app-content" style="max-inline-size: 52rem; margin-inline: auto; padding-block: var(--space-6)">
    <div class="cluster cluster--between no-print">
        <a class="button button--ghost" href="<?= e(url('/conformite/attestations')) ?>"><?= e(__('conformite.back_to_attestations')) ?></a>
        <button class="button button--primary" type="button" data-print>
            <svg class="icon icon--small" aria-hidden="true"><use href="<?= e(asset('img/icons.svg')) ?>#printer"></use></svg><?= e(__('conformite.print')) ?>
        </button>
    </div>

    <header class="card stack-md">
        <div class="cluster cluster--between">
            <div>
                <p class="page-header__eyebrow"><?= $cabinetName !== '' ? e($cabinetName) : e(__('conformite.cabinet_fallback')) ?></p>
                <h1 class="page-header__title"><?= e(__('conformite.attestation_title')) ?></h1>
            </div>
            <div class="u-text-end">
                <p class="detail-label"><?= e(__('conformite.attestation_date')) ?></p>
                <p><strong><?= e($dateLabel($today)) ?></strong></p>
            </div>
        </div>
        <?php if ($cabinetAddress !== ''): ?>
            <p class="table-meta"><?= e($cabinetAddress) ?></p>
        <?php endif; ?>
    </header>

    <section class="card stack-md">
        <h2 class="card-header__title"><?= e(__('conformite.attestation_project')) ?></h2>
        <dl class="detail-list">
            <div class="detail-list__item"><dt><?= e(__('conformite.project')) ?></dt><dd><?= e((string) ($project['name'] ?? '')) ?></dd></div>
            <div class="detail-list__item"><dt><?= e(__('conformite.reference')) ?></dt><dd><?= e((string) ($project['reference'] ?? '—')) ?></dd></div>
            <div class="detail-list__item"><dt><?= e(__('conformite.client')) ?></dt><dd><?= e((string) ($project['client_name'] ?? '')) ?></dd></div>
            <div class="detail-list__item"><dt><?= e(__('conformite.city')) ?></dt><dd><?= ($project['city'] ?? '') !== '' ? e((string) $project['city']) : '—' ?></dd></div>
        </dl>
    </section>

    <section class="card stack-md">
        <h2 class="card-header__title"><?= e(__('conformite.attestation_policy')) ?></h2>
        <dl class="detail-list">
            <div class="detail-list__item"><dt><?= e(__('conformite.insurer')) ?></dt><dd><?= e((string) ($assurance['insurer'] ?? '')) ?></dd></div>
            <div class="detail-list__item"><dt><?= e(__('conformite.policy')) ?></dt><dd><?= ($assurance['policy_number'] ?? '') !== '' ? e((string) $assurance['policy_number']) : '—' ?></dd></div>
            <div class="detail-list__item">
                <dt><?= e(__('conformite.coverage')) ?></dt>
                <dd><?= $coverage !== null ? e(money_format_tnd((string) $coverage)) : '—' ?></dd>
            </div>
            <div class="detail-list__item">
                <dt><?= e(__('conformite.validity')) ?></dt>
                <dd><?= e($dateLabel($assurance['starts_on'] === null ? null : (string) $assurance['starts_on'])) ?> → <?= e($dateLabel((string) ($assurance['ends_on'] ?? ''))) ?></dd>
            </div>
        </dl>

        <div class="document-text-block">
            <h3><?= e(__('conformite.attestation_declaration_title')) ?></h3>
            <p><?= e(__('conformite.attestation_declaration', [
                'cabinet' => $cabinetName !== '' ? $cabinetName : __('conformite.cabinet_fallback'),
                'project' => (string) ($project['name'] ?? ''),
            ])) ?></p>
        </div>

        <div class="cluster cluster--between" style="margin-block-start: var(--space-6)">
            <div>
                <p class="detail-label"><?= e(__('conformite.attestation_place')) ?></p>
                <p style="min-inline-size: 14rem; min-block-size: 3rem; border-block-end: 1px solid var(--color-border-strong)">&nbsp;</p>
            </div>
            <div class="u-text-end">
                <p class="detail-label"><?= e(__('conformite.attestation_signature')) ?></p>
                <p style="min-inline-size: 14rem; min-block-size: 3rem; border-block-end: 1px solid var(--color-border-strong)">&nbsp;</p>
            </div>
        </div>

        <p class="table-meta" role="note"><?= e(__('conformite.attestation_disclaimer')) ?></p>
    </section>
</main>
</body>
</html>
