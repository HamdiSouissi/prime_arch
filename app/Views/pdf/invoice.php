<?php
declare(strict_types=1);

$documentTitle = 'Facture de situation';
$documentNumber = 'FA-2025-012';
$documentDate = '10/10/2026';
$projectName = 'Résidence Les Jasmins · Sfax';
$legalNotice = 'Paiement selon les modalités prévues au contrat. Document de démonstration : les montants, taux et mentions fiscales doivent être vérifiés par un expert-comptable avant toute émission.';
$lines = $lines ?? [
    ['description' => 'Avancement de la phase APD — situation n° 03', 'quantity' => '1 forfait', 'unit' => '15 500,000 DT', 'amount' => '15 500,000 DT'],
];
ob_start();
?>
<table class="pdf-table">
    <thead><tr><th scope="col">Désignation</th><th scope="col" class="pdf-quantity">Quantité</th><th scope="col" class="pdf-number-cell">Prix unitaire HT</th><th scope="col" class="pdf-number-cell">Total HT</th></tr></thead>
    <tbody><?php foreach ($lines as $line): ?><tr><td><?= e($line['description'] ?? '') ?></td><td class="pdf-quantity"><?= e($line['quantity'] ?? '') ?></td><td class="pdf-number-cell"><?= e($line['unit'] ?? '') ?></td><td class="pdf-number-cell"><?= e($line['amount'] ?? '') ?></td></tr><?php endforeach; ?></tbody>
</table>
<table class="pdf-totals">
    <tr><td>Total hors taxes (HT)</td><td>15 500,000 DT</td></tr>
    <tr><td>TVA 19 %</td><td>2 945,000 DT</td></tr>
    <tr><td>Timbre fiscal</td><td>1,000 DT</td></tr>
    <tr><td>Retenue à la source</td><td>0,000 DT</td></tr>
    <tr class="pdf-total-final"><td>Net à payer TTC</td><td>18 446,000 DT</td></tr>
</table>
<p class="pdf-paragraph"><strong>Arrêtée la présente facture à la somme de :</strong> dix-huit mille quatre cent quarante-six dinars et zéro millime.</p>
<?php
$documentBody = (string) ob_get_clean();
require __DIR__ . '/base.php';
