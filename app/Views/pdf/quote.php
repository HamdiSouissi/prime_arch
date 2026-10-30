<?php
declare(strict_types=1);

$documentTitle = 'Devis d’honoraires';
$documentNumber = 'PA-2025-018';
$documentDate = '14/10/2026';
$projectName = 'Villa contemporaine · Sidi Bou Saïd';
$legalNotice = 'Devis valable 30 jours. Les taux fiscaux sont donnés à titre indicatif et doivent être validés par un expert-comptable. Conditions de règlement : acompte de 30 % à la signature, solde selon les étapes contractuelles.';
$lines = $lines ?? [
    ['description' => 'Études préliminaires & esquisse', 'quantity' => '1 forfait', 'unit' => '6 500,000 DT', 'amount' => '6 500,000 DT'],
    ['description' => 'Avant-projet sommaire (APS)', 'quantity' => '1 forfait', 'unit' => '8 500,000 DT', 'amount' => '8 500,000 DT'],
    ['description' => 'Avant-projet détaillé (APD)', 'quantity' => '1 forfait', 'unit' => '10 500,000 DT', 'amount' => '10 500,000 DT'],
    ['description' => 'Dossier de permis & coordination', 'quantity' => '1 forfait', 'unit' => '3 000,000 DT', 'amount' => '3 000,000 DT'],
];
ob_start();
?>
<table class="pdf-table">
    <thead><tr><th>Désignation</th><th class="pdf-quantity">Quantité</th><th class="pdf-number-cell">Prix unitaire HT</th><th class="pdf-number-cell">Total HT</th></tr></thead>
    <tbody><?php foreach ($lines as $line): ?><tr><td><?= e($line['description'] ?? '') ?></td><td class="pdf-quantity"><?= e($line['quantity'] ?? '') ?></td><td class="pdf-number-cell"><?= e($line['unit'] ?? '') ?></td><td class="pdf-number-cell"><?= e($line['amount'] ?? '') ?></td></tr><?php endforeach; ?></tbody>
</table>
<table class="pdf-totals">
    <tr><td>Total hors taxes (HT)</td><td>28 500,000 DT</td></tr>
    <tr><td>TVA 19 %</td><td>5 415,000 DT</td></tr>
    <tr><td>Timbre fiscal</td><td>1,000 DT</td></tr>
    <tr><td>Retenue à la source</td><td>0,000 DT</td></tr>
    <tr class="pdf-total-final"><td>Net à payer TTC</td><td>33 916,000 DT</td></tr>
</table>
<p class="pdf-paragraph"><strong>Arrêté le présent devis à la somme de :</strong> trente-trois mille neuf cent seize dinars et zéro millime.</p>
<?php
$documentBody = (string) ob_get_clean();
require __DIR__ . '/base.php';
