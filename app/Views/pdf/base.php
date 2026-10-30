<?php
declare(strict_types=1);

/** @var array<string,string>|null $cabinet */
/** @var array<string,string>|null $client */
/** @var string|null $documentTitle */
/** @var string|null $documentNumber */
/** @var string|null $documentDate */
/** @var string|null $projectName */
/** @var string|null $documentBody */
/** @var string|null $legalNotice */
$cabinet = $cabinet ?? [
    'name' => 'Atelier Souissi — Architecture & Urbanisme',
    'architect' => 'Amel Trabelsi, architecte',
    'address' => '18, avenue Habib Bourguiba, 1001 Tunis',
    'phone' => '+216 71 245 880',
    'email' => 'contact@ateliersouissi.tn',
    'tax_id' => '1234567/A/M/000',
];
$client = $client ?? [
    'name' => 'Hichem Ben Salem',
    'address' => '18, rue de la Corniche, Sidi Bou Saïd, 2026 Tunis',
    'phone' => '+216 20 123 456',
    'email' => 'hichem.bensalem@email.tn',
    'tax_id' => '—',
];
$cssPath = defined('BASE_PATH') ? BASE_PATH . '/public/assets/css/pdf.css' : '';
$pdfCss = isset($pdfCss) ? (string) $pdfCss : (is_file($cssPath) ? (string) file_get_contents($cssPath) : '');
$logoPath = isset($logoPath) && is_string($logoPath) && $logoPath !== ''
    ? $logoPath
    : (defined('BASE_PATH') ? BASE_PATH . '/public/assets/img/logo-placeholder.svg' : '');
$direction = $direction ?? 'ltr';
$documentTitle = $documentTitle ?? 'Document';
$documentNumber = $documentNumber ?? 'PA-2025-018';
$documentDate = $documentDate ?? '14/10/2026';
?>
<!doctype html>
<html lang="<?= e($direction === 'rtl' ? 'ar' : 'fr') ?>" dir="<?= e($direction) ?>">
<head>
    <meta charset="utf-8">
    <title><?= e($documentTitle) ?> · <?= e($documentNumber) ?></title>
    <style><?= $pdfCss ?></style>
</head>
<body>
<htmlpageheader name="PdfHeader">
    <table class="pdf-header">
        <tr>
            <td class="pdf-brand">
                <?php if ($logoPath !== '' && is_file($logoPath)): ?><img class="pdf-logo" src="<?= e($logoPath) ?>" alt=""><?php endif; ?>
                <span class="pdf-brand-copy"><span class="pdf-brand-name"><?= e($cabinet['name'] ?? '') ?></span><br><span class="pdf-brand-caption"><?= e($cabinet['architect'] ?? '') ?></span></span>
            </td>
            <td class="pdf-meta" style="text-align:end">
                <?= e($cabinet['address'] ?? '') ?><br>
                <?= e($cabinet['phone'] ?? '') ?> · <?= e($cabinet['email'] ?? '') ?><br>
                <?= e(__('documents.pdf.tax_id')) ?> : <?= e($cabinet['tax_id'] ?? '') ?>
            </td>
        </tr>
    </table>
</htmlpageheader>
<htmlpagefooter name="PdfFooter">
    <table class="pdf-footer"><tr><td><?= e($cabinet['name'] ?? '') ?> · <?= e($cabinet['phone'] ?? '') ?> · <?= e($cabinet['email'] ?? '') ?></td><td class="pdf-page-count"><?= e(__('documents.pdf.page')) ?> {PAGENO} / {nbpg}</td></tr></table>
</htmlpagefooter>
<sethtmlpageheader name="PdfHeader" value="on" show-this-page="1" />
<sethtmlpagefooter name="PdfFooter" value="on" />

<table class="pdf-parties">
    <tr>
        <td>
            <p class="pdf-party-label"><?= e(__('documents.pdf.recipient')) ?></p>
            <p class="pdf-party-name"><?= e($client['name'] ?? '') ?></p>
            <?= e($client['address'] ?? '') ?><br>
            <?= e($client['phone'] ?? '') ?><br>
            <?= e($client['email'] ?? '') ?>
        </td>
        <td>
            <p class="pdf-party-label"><?= e(__('documents.pdf.project')) ?></p>
            <p class="pdf-party-name"><?= e($projectName ?? 'Villa contemporaine · Sidi Bou Saïd') ?></p>
            <span class="pdf-muted"><?= e($documentTitle) ?> · <?= e($documentNumber) ?></span><br>
            <span class="pdf-muted"><?= e(__('documents.pdf.issued_on')) ?> <?= e($documentDate) ?></span>
        </td>
    </tr>
</table>

<h1 class="pdf-title"><?= e($documentTitle) ?></h1>
<p class="pdf-number"><?= e(__('documents.pdf.number')) ?> <?= e($documentNumber) ?> · <?= e(__('documents.pdf.issued_on')) ?> <?= e($documentDate) ?></p>
<div class="pdf-document-body">
    <?= $documentBody ?? '' ?>
</div>

<div class="pdf-legal">
    <?= e($legalNotice ?? 'Document de démonstration. Les taux et mentions fiscales sont indicatifs et doivent être validés par un expert-comptable avant toute utilisation.') ?>
</div>

</body>
</html>
