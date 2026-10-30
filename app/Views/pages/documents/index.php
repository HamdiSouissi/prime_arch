<?php
declare(strict_types=1);

use App\Core\View;

$documents = [
    ['number' => 'PA-2025-018', 'title' => 'Devis d’honoraires', 'client' => 'Hichem Ben Salem', 'project' => 'Villa contemporaine · Tunis', 'date' => '14 oct. 2026', 'amount' => '33 916,000 DT', 'status' => 'Envoyé', 'tone' => 'info', 'icon' => 'quote', 'kind' => 'devis'],
    ['number' => 'FA-2025-012', 'title' => 'Facture de situation n° 03', 'client' => 'Société El Amana', 'project' => 'Résidence Les Jasmins · Sfax', 'date' => '10 oct. 2026', 'amount' => '18 446,000 DT', 'status' => 'Payée', 'tone' => 'success', 'icon' => 'receipt', 'kind' => 'facture'],
    ['number' => 'CT-2025-008', 'title' => 'Contrat de maîtrise d’œuvre', 'client' => 'Dr. Hatem Gharbi', 'project' => 'Clinique Al Yasmine · Sousse', 'date' => '02 oct. 2026', 'amount' => '58 750,000 DT', 'status' => 'À signer', 'tone' => 'warning', 'icon' => 'file-text', 'kind' => 'contrat'],
    ['number' => 'FA-2025-011', 'title' => 'Facture d’acompte', 'client' => 'Nadia Khelifi', 'project' => 'Maison de vacances · Gammarth', 'date' => '28 sept. 2026', 'amount' => '8 500,000 DT', 'status' => 'En retard', 'tone' => 'error', 'icon' => 'receipt', 'kind' => 'facture'],
    ['number' => 'DV-2025-015', 'title' => 'Devis extension de mission', 'client' => 'Groupe Cap Bon', 'project' => 'Bureaux Cap Bon · Nabeul', 'date' => '24 sept. 2026', 'amount' => '12 000,000 DT', 'status' => 'Brouillon', 'tone' => 'neutral', 'icon' => 'quote', 'kind' => 'devis'],
];
?>
<?php View::partial('partials/page-header', [
    'pageTitle' => 'Documents',
    'eyebrow' => 'Devis · contrats · factures',
    'subtitle' => 'Gérez vos documents commerciaux et suivez leur état en un coup d’œil.',
    'primaryAction' => ['label' => 'Créer un document', 'icon' => 'plus', 'dialog' => '#quick-add-dialog'],
]) ?>

<section class="stats-grid stats-grid--three" aria-label="Résumé des documents">
    <?php View::partial('partials/stat-card', ['label' => 'À encaisser', 'value' => '26 945,000 DT', 'icon' => 'wallet', 'tone' => 'blue', 'note' => '3 factures en attente']) ?>
    <?php View::partial('partials/stat-card', ['label' => 'Devis en attente', 'value' => '5', 'icon' => 'quote', 'tone' => 'coral', 'note' => '2 à relancer cette semaine']) ?>
    <?php View::partial('partials/stat-card', ['label' => 'Taux de conversion', 'value' => '68 %', 'icon' => 'chart', 'tone' => 'mint', 'trend' => '+6 %', 'note' => 'sur le trimestre']) ?>
</section>

<section class="card stack-md" aria-label="Documents du cabinet">
    <div class="filter-row">
        <div class="input-with-icon"><svg class="icon icon--small" aria-hidden="true"><use href="<?= e(asset('img/icons.svg')) ?>#search"></use></svg><input type="search" placeholder="Numéro, client ou projet…" aria-label="Rechercher un document" data-table-search="#documents-table"></div>
        <div class="field"><label class="u-sr-only" for="document-kind">Type de document</label><select id="document-kind" aria-label="Filtrer par type" data-filter-select="#documents-table"><option value="">Tous les types</option><option value="devis">Devis</option><option value="facture">Facture</option><option value="contrat">Contrat</option></select></div>
        <button class="button button--secondary" type="button" data-toast-message="Filtres supplémentaires disponibles dans la version connectée."><svg class="icon icon--small"><use href="<?= e(asset('img/icons.svg')) ?>#filter"></use></svg> Filtrer</button>
    </div>
    <div class="table-wrap">
        <table class="data-table" id="documents-table">
            <thead><tr><th scope="col">Document</th><th scope="col">Client / projet</th><th scope="col">Date</th><th scope="col">Montant TTC</th><th scope="col">Statut</th><th scope="col"><span class="u-sr-only">Ouvrir</span></th></tr></thead>
            <tbody>
                <?php foreach ($documents as $document): ?>
                    <tr data-filter-row data-filter-value="<?= e($document['kind']) ?>">
                        <td><a class="table-primary" href="<?= e(url('/design/documents/' . $document['number'])) ?>"><span class="document-row__icon"><svg class="icon icon--small"><use href="<?= e(asset('img/icons.svg')) ?>#<?= e($document['icon']) ?>"></use></svg></span><span class="table-primary__text"><span class="table-primary__name"><?= e($document['title']) ?></span><span class="table-primary__meta"><?= e($document['number']) ?></span></span></a></td>
                        <td><span class="table-primary__text"><span class="table-primary__name"><?= e($document['client']) ?></span><span class="table-primary__meta"><?= e($document['project']) ?></span></span></td>
                        <td><?= e($document['date']) ?></td><td class="number"><strong><?= e($document['amount']) ?></strong></td><td><span class="badge badge--<?= e($document['tone']) ?>"><?= e($document['status']) ?></span></td>
                        <td><a class="button button--icon button--ghost" href="<?= e(url('/design/documents/' . $document['number'])) ?>" aria-label="Voir le document <?= e($document['number']) ?>"><svg class="icon icon--small icon--directional"><use href="<?= e(asset('img/icons.svg')) ?>#chevron-right"></use></svg></a></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <div class="table-footer"><span>5 documents récents sur 42</span><button class="button button--small button--secondary" type="button" data-toast-message="Tous les documents de démonstration sont affichés.">Voir tous les documents</button></div>
</section>
