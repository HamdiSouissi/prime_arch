<?php
declare(strict_types=1);

use App\Core\View;

$lines = [
    ['description' => 'Études préliminaires & esquisse', 'quantity' => '1 forfait', 'unit' => '6 500,000 DT', 'amount' => '6 500,000 DT'],
    ['description' => 'Avant-projet sommaire (APS)', 'quantity' => '1 forfait', 'unit' => '8 500,000 DT', 'amount' => '8 500,000 DT'],
    ['description' => 'Avant-projet détaillé (APD)', 'quantity' => '1 forfait', 'unit' => '10 500,000 DT', 'amount' => '10 500,000 DT'],
    ['description' => 'Dossier de permis & coordination', 'quantity' => '1 forfait', 'unit' => '3 000,000 DT', 'amount' => '3 000,000 DT'],
];
?>
<?php View::partial('partials/page-header', [
    'pageTitle' => 'Devis d’honoraires',
    'eyebrow' => 'Document ' . ($recordId ?? 'PA-2025-018'),
    'subtitle' => 'Préparé pour Hichem Ben Salem · Villa contemporaine, Sidi Bou Saïd',
    'secondaryAction' => ['label' => 'Régénérer', 'icon' => 'refresh', 'url' => '#regenerer'],
    'primaryAction' => ['label' => 'Télécharger PDF', 'icon' => 'download', 'url' => '#telecharger'],
]) ?>

<div class="document-toolbar cluster cluster--between margin-block-end-5">
    <span class="badge badge--info">Envoyé · 14 octobre 2026</span>
    <div class="cluster"><button class="button button--secondary" type="button" data-print><svg class="icon icon--small"><use href="<?= e(asset('img/icons.svg')) ?>#printer"></use></svg> Imprimer</button><button class="button button--secondary" type="button" data-toast-message="Une nouvelle version du devis a été préparée en démonstration." data-toast-tone="info"><svg class="icon icon--small"><use href="<?= e(asset('img/icons.svg')) ?>#refresh"></use></svg> Régénérer</button><button class="button button--primary" type="button" data-toast-message="Aucun fichier n'est téléchargé dans cette démonstration." data-toast-tone="info"><svg class="icon icon--small"><use href="<?= e(asset('img/icons.svg')) ?>#download"></use></svg> Télécharger PDF</button></div>
</div>

<article class="document-preview" aria-label="Aperçu du devis PA-2025-018">
    <div class="document-preview__top">
        <div class="document-preview__brand"><img src="<?= e(asset('img/logo-placeholder.svg')) ?>" alt="" width="48" height="48"><div><strong>ATELIER SOUISSI</strong><p class="table-primary__meta">Architecture · Urbanisme · Intérieur</p></div></div>
        <div class="u-text-end"><h2 class="document-preview__title">DEVIS</h2><p class="document-preview__number">N° PA-2025-018 · 14/10/2026</p></div>
    </div>
    <div class="document-preview__parties">
        <section><p class="document-preview__label">Émis par</p><strong>Atelier Souissi — Amel Trabelsi, architecte</strong><p class="muted text-footnote">18, avenue Habib Bourguiba, 1001 Tunis</p><p class="muted text-footnote">+216 71 245 880 · contact@ateliersouissi.tn</p><p class="muted text-footnote">Matricule fiscal : 1234567/A/M/000</p></section>
        <section><p class="document-preview__label">Adressé à</p><strong>Hichem Ben Salem</strong><p class="muted text-footnote">18, rue de la Corniche, Sidi Bou Saïd, 2026 Tunis</p><p class="muted text-footnote">+216 20 123 456 · hichem.bensalem@email.tn</p><p class="muted text-footnote">Projet : Villa contemporaine · 420 m²</p></section>
    </div>
    <div class="table-wrap document-lines-wrap"><table class="data-table document-lines"><thead><tr><th scope="col">Désignation</th><th scope="col">Quantité</th><th scope="col">Prix unitaire HT</th><th scope="col">Total HT</th></tr></thead><tbody>
        <?php foreach ($lines as $line): ?><tr><td><strong><?= e($line['description']) ?></strong></td><td><?= e($line['quantity']) ?></td><td class="number"><?= e($line['unit']) ?></td><td class="number"><?= e($line['amount']) ?></td></tr><?php endforeach; ?>
    </tbody></table></div>
    <div class="document-totals" aria-label="Totaux du document">
        <div class="document-totals__row"><span>Total hors taxes (HT)</span><strong>28 500,000 DT</strong></div>
        <div class="document-totals__row"><span>TVA 19 %</span><strong>5 415,000 DT</strong></div>
        <div class="document-totals__row"><span>Timbre fiscal</span><strong>1,000 DT</strong></div>
        <div class="document-totals__row"><span>Retenue à la source</span><strong>0,000 DT</strong></div>
        <div class="document-totals__row document-totals__row--total"><span>Net à payer TTC</span><strong>33 916,000 DT</strong></div>
    </div>
    <div class="document-legal"><p><strong>Conditions :</strong> validité du devis 30 jours. Acompte de 30 % à la signature. Les taux et mentions fiscales présentés sont indicatifs et doivent être validés par un expert-comptable.</p><p>Arrêté le présent devis à la somme de trente-trois mille neuf cent seize dinars et zéro millime.</p></div>
</article>
