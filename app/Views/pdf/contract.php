<?php
declare(strict_types=1);

$documentTitle = 'Contrat de maîtrise d’œuvre';
$documentNumber = 'CT-2025-008';
$documentDate = '21/09/2025';
$projectName = 'Villa contemporaine · Sidi Bou Saïd';
$legalNotice = 'Le présent modèle est fourni à titre de démonstration et ne constitue pas un conseil juridique. Il doit être adapté et relu par les parties et leurs conseils avant signature.';
$clauses = [
    ['title' => 'Objet de la mission', 'body' => 'Le maître d’ouvrage confie à l’architecte une mission de conception et de suivi pour la réalisation d’une villa individuelle d’environ 420 m² à Sidi Bou Saïd.'],
    ['title' => 'Étendue des prestations', 'body' => 'La mission comprend les études préliminaires, l’APS, l’APD, le dossier de permis de bâtir, le DCE et le suivi architectural du chantier selon le calendrier convenu.'],
    ['title' => 'Honoraires et modalités', 'body' => 'Les honoraires sont fixés à 42 500,000 DT HT, hors taxes et débours. Les règlements interviennent par étapes, sur présentation des factures correspondantes.'],
    ['title' => 'Délais et responsabilités', 'body' => 'Le calendrier prévisionnel est établi conjointement. Chaque partie s’engage à communiquer les informations et validations nécessaires à la bonne exécution de la mission.'],
    ['title' => 'Documents et propriété intellectuelle', 'body' => 'Les documents de conception sont remis pour le projet défini au présent contrat. Toute réutilisation ou modification hors de ce cadre nécessite l’accord écrit de l’architecte.'],
];
ob_start();
?>
<p class="pdf-paragraph">Entre les soussignés, <strong>Atelier Souissi — Architecture & Urbanisme</strong>, représenté par Amel Trabelsi, architecte, ci-après dénommé « l’Architecte », et <strong>Hichem Ben Salem</strong>, maître d’ouvrage, il est convenu ce qui suit :</p>
<table class="pdf-clause-table"><tbody><?php foreach ($clauses as $index => $clause): ?><tr><td class="pdf-clause-number"><?= e((string) ($index + 1)) ?>.</td><td><strong><?= e($clause['title']) ?></strong><br><?= e($clause['body']) ?></td></tr><?php endforeach; ?></tbody></table>
<p class="pdf-paragraph">Fait à Tunis, en deux exemplaires originaux, le <?= e($documentDate) ?>.</p>
<table class="pdf-signatures"><tr><td><strong>Le maître d’ouvrage</strong><br>Hichem Ben Salem<br><br>Signature précédée de la mention « Lu et approuvé »</td><td><strong>L’architecte</strong><br>Amel Trabelsi<br><br>Signature et cachet professionnel</td></tr></table>
<?php
$documentBody = (string) ob_get_clean();
require __DIR__ . '/base.php';
