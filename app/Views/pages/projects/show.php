<?php
declare(strict_types=1);

use App\Core\View;

$phases = [
    ['title' => 'Esquisse', 'meta' => 'Terminée le 18 août 2025 · 24 h saisies', 'complete' => true],
    ['title' => 'Avant-projet sommaire (APS)', 'meta' => 'Validée le 27 octobre 2025 · 46 h saisies', 'complete' => true],
    ['title' => 'Avant-projet détaillé (APD)', 'meta' => 'En cours · Livraison prévue le 15 novembre 2026', 'complete' => false],
    ['title' => 'Dossier de permis de bâtir', 'meta' => 'À venir · 3 semaines estimées', 'complete' => false],
    ['title' => 'Dossier de consultation (DCE)', 'meta' => 'À venir · Selon validation du permis', 'complete' => false],
    ['title' => 'Suivi de chantier & réception', 'meta' => 'À venir', 'complete' => false],
];
$documents = [
    ['name' => 'Devis d’honoraires · PA-2025-018', 'meta' => '14 octobre 2026 · 33 916,000 DT', 'icon' => 'quote'],
    ['name' => 'Plan masse · Révision 03', 'meta' => '08 octobre 2026 · PDF, 4,2 Mo', 'icon' => 'plan'],
    ['name' => 'Contrat de maîtrise d’œuvre', 'meta' => '21 septembre 2025 · Signé', 'icon' => 'file-text'],
];
?>
<?php View::partial('partials/page-header', [
    'pageTitle' => 'Villa contemporaine',
    'eyebrow' => 'Projet ' . ($recordId ?? 'PA-2025-014') . ' · Sidi Bou Saïd',
    'subtitle' => 'Hichem Ben Salem · Résidentiel · Démarrage le 12 août 2025',
    'secondaryAction' => ['label' => 'Partager', 'icon' => 'send', 'url' => '#project-sharing'],
    'primaryAction' => ['label' => 'Modifier le projet', 'icon' => 'edit', 'dialog' => '#project-edit-dialog'],
]) ?>

<section class="card project-summary" aria-label="Résumé du projet">
    <div class="project-summary__top">
        <div><p class="section-heading__note">Avancement global</p><p class="project-summary__value number">76<span> %</span></p></div>
        <div class="project-summary__facts"><span class="badge badge--info">En cours · APD</span><span class="muted text-footnote"><svg class="icon icon--small inline-icon"><use href="<?= e(asset('img/icons.svg')) ?>#map-pin"></use></svg> 18, rue de la Corniche, Sidi Bou Saïd</span></div>
        <div class="project-summary__facts"><span class="project-summary__fact-label">Budget honoraires</span><strong class="number">42 500,000 DT</strong><span class="project-summary__fact-label">Échéance estimée · 15.11.2026</span></div>
    </div>
    <?php View::partial('partials/progress', ['value' => 76, 'label' => 'Avancement de la mission']) ?>
</section>

<div class="tabs project-tabs" role="tablist" aria-label="Sections du projet" data-tab-group data-tab-panels="#project-tab-panels">
    <button class="tabs__item" id="project-tab-overview" role="tab" aria-selected="true" aria-controls="project-panel-overview" data-tab-target="project-panel-overview">Aperçu</button>
    <button class="tabs__item" id="project-tab-details" role="tab" aria-selected="false" aria-controls="project-panel-details" data-tab-target="project-panel-details" tabindex="-1">Détails</button>
    <button class="tabs__item" id="project-tab-phases" role="tab" aria-selected="false" aria-controls="project-panel-phases" data-tab-target="project-panel-phases" tabindex="-1">Phases</button>
    <button class="tabs__item" id="project-tab-time" role="tab" aria-selected="false" aria-controls="project-panel-time" data-tab-target="project-panel-time" tabindex="-1">Temps & coûts</button>
    <button class="tabs__item" id="project-tab-documents" role="tab" aria-selected="false" aria-controls="project-panel-documents" data-tab-target="project-panel-documents" tabindex="-1">Documents</button>
</div>

<div id="project-tab-panels" class="project-tab-panels">
    <section class="content-grid tab-panel" id="project-panel-overview" role="tabpanel" aria-labelledby="project-tab-overview" data-tab-panel>
        <article class="card"><div class="card-header"><h2 class="card-header__title">Prochaine étape</h2><span class="badge badge--warning">À valider</span></div><h3 class="text-headline">Finaliser le dossier APD</h3><p class="muted text-footnote">Échéance indicative : 15 novembre 2026. Prévoir une revue des coupes et des matériaux avec le client.</p><div class="cluster margin-block-start-4"><span class="badge badge--info badge--plain">Amel Trabelsi</span><span class="badge badge--neutral badge--plain">6 tâches ouvertes</span></div></article>
        <article class="card"><div class="card-header"><h2 class="card-header__title">Temps consommé</h2><a class="card-header__action" href="<?= e(url('/design/time')) ?>">Saisir du temps</a></div><p class="document-preview__amount number">128,5 h</p><p class="muted text-footnote">sur 240 h estimées · 53,5 % du budget temps</p><?php View::partial('partials/progress', ['value' => 54, 'label' => 'Budget temps consommé', 'tone' => 'mint']) ?></article>
        <article class="card span-full"><div class="card-header"><h2 class="card-header__title">Activité du projet</h2><a class="card-header__action" href="<?= e(url('/design/documents')) ?>">Voir les documents</a></div><div class="activity-list"><div class="activity-item"><span class="activity-item__icon"><svg class="icon icon--small"><use href="<?= e(asset('img/icons.svg')) ?>#check-circle"></use></svg></span><span class="activity-item__copy"><span class="activity-item__text">Hichem Ben Salem a validé l’APS</span><span class="activity-item__time">Hier à 15:28</span></span></div><div class="activity-item"><span class="activity-item__icon"><svg class="icon icon--small"><use href="<?= e(asset('img/icons.svg')) ?>#file-plus"></use></svg></span><span class="activity-item__copy"><span class="activity-item__text">Devis <strong>PA-2025-018</strong> envoyé au client</span><span class="activity-item__time">14 octobre 2026</span></span></div></div></article>
    </section>
    <section class="content-grid tab-panel" id="project-panel-details" role="tabpanel" aria-labelledby="project-tab-details" data-tab-panel hidden>
        <article class="card"><h2 class="card-header__title margin-block-end-5">Informations du projet</h2><dl class="detail-list"><div class="detail-list__item"><dt>Client</dt><dd>Hichem Ben Salem</dd></div><div class="detail-list__item"><dt>Type de mission</dt><dd>Mission complète · neuf</dd></div><div class="detail-list__item"><dt>Surface</dt><dd>420 m² habitables · terrain 760 m²</dd></div><div class="detail-list__item"><dt>Adresse du chantier</dt><dd>18, rue de la Corniche, Sidi Bou Saïd, Tunis</dd></div><div class="detail-list__item"><dt>Budget travaux estimé</dt><dd class="number">685 000,000 DT</dd></div></dl></article>
        <article class="card"><div class="card-header"><h2 class="card-header__title">Champs personnalisés</h2><button class="button button--small button--secondary" type="button" data-modal-open="#custom-field-dialog"><svg class="icon icon--small"><use href="<?= e(asset('img/icons.svg')) ?>#plus"></use></svg> Ajouter</button></div><div class="custom-field-list" data-custom-field-list><div class="custom-field" data-custom-field><span class="custom-field__label">Style architectural</span><span class="custom-field__value">Méditerranéen contemporain</span><button class="button button--icon button--ghost" type="button" data-custom-field-remove aria-label="Supprimer le champ Style architectural"><svg class="icon icon--small"><use href="<?= e(asset('img/icons.svg')) ?>#trash"></use></svg></button></div><div class="custom-field" data-custom-field><span class="custom-field__label">Référence cadastrale</span><span class="custom-field__value">TN-TS-014-2025</span><button class="button button--icon button--ghost" type="button" data-custom-field-remove aria-label="Supprimer le champ Référence cadastrale"><svg class="icon icon--small"><use href="<?= e(asset('img/icons.svg')) ?>#trash"></use></svg></button></div></div></article>
    </section>
    <section class="content-grid tab-panel" id="project-panel-phases" role="tabpanel" aria-labelledby="project-tab-phases" data-tab-panel hidden>
        <article class="card span-full"><div class="card-header"><div><h2 class="card-header__title">Phases de la mission</h2><p class="section-heading__note">Avancement et principales dates de suivi</p></div><button class="button button--secondary button--small" type="button" data-toast-message="L'ordre des phases est personnalisable dans les réglages du module Projets.">Gérer les phases</button></div><ol class="timeline">
            <?php foreach ($phases as $phase): ?>
                <li class="timeline__item <?= e($phase['complete'] ? 'is-complete' : '') ?>"><span class="timeline__marker"><svg class="icon icon--small" aria-hidden="true"><use href="<?= e(asset('img/icons.svg')) ?>#<?= e($phase['complete'] ? 'check' : 'plan') ?>"></use></svg></span><span class="timeline__content"><strong class="timeline__title"><?= e($phase['title']) ?></strong><span class="timeline__meta"><?= e($phase['meta']) ?></span></span></li>
            <?php endforeach; ?>
        </ol></article>
    </section>
    <section class="content-grid tab-panel" id="project-panel-time" role="tabpanel" aria-labelledby="project-tab-time" data-tab-panel hidden>
        <article class="card"><h2 class="card-header__title margin-block-end-5">Répartition du temps</h2><dl class="detail-list"><div class="detail-list__item"><dt>Esquisse & APS</dt><dd>70,0 h · 12 250,000 DT</dd></div><div class="detail-list__item"><dt>APD</dt><dd>42,5 h · 7 437,500 DT</dd></div><div class="detail-list__item"><dt>Coordination</dt><dd>16,0 h · 2 800,000 DT</dd></div><div class="detail-list__item"><dt>Total à date</dt><dd><strong>128,5 h · 22 487,500 DT</strong></dd></div></dl></article>
        <article class="card"><h2 class="card-header__title margin-block-end-5">Budget prévisionnel</h2><p class="document-preview__amount number">42 500,000 DT</p><p class="muted text-footnote margin-block-end-4">Honoraires contractuels HT</p><?php View::partial('partials/progress', ['value' => 53, 'label' => 'Consommé à date', 'tone' => 'coral']) ?><a class="button button--secondary margin-block-start-4" href="<?= e(url('/design/time')) ?>">Ouvrir le suivi du temps</a></article>
    </section>
    <section class="content-grid tab-panel" id="project-panel-documents" role="tabpanel" aria-labelledby="project-tab-documents" data-tab-panel hidden>
        <article class="card span-full"><div class="card-header"><h2 class="card-header__title">Documents associés</h2><a class="button button--small button--secondary" href="<?= e(url('/design/documents')) ?>"><svg class="icon icon--small"><use href="<?= e(asset('img/icons.svg')) ?>#plus"></use></svg> Ajouter un document</a></div><?php foreach ($documents as $document): ?><a class="document-row" href="<?= e(url('/design/documents/PA-2025-018')) ?>"><span class="document-row__main"><span class="document-row__icon"><svg class="icon icon--small"><use href="<?= e(asset('img/icons.svg')) ?>#<?= e($document['icon']) ?>"></use></svg></span><span class="document-row__copy"><span class="document-row__name"><?= e($document['name']) ?></span><span class="document-row__meta"><?= e($document['meta']) ?></span></span></span><svg class="icon icon--small icon--directional"><use href="<?= e(asset('img/icons.svg')) ?>#chevron-right"></use></svg></a><?php endforeach; ?></article>
    </section>
</div>

<dialog class="dialog--wide" id="custom-field-dialog" aria-labelledby="custom-field-title">
    <form class="dialog__form" method="dialog" data-custom-field-form>
        <div class="dialog__header"><h2 class="dialog__title" id="custom-field-title">Ajouter un champ personnalisé</h2><button class="button button--icon button--ghost" type="button" data-modal-close aria-label="Fermer"><svg class="icon"><use href="<?= e(asset('img/icons.svg')) ?>#x"></use></svg></button></div>
        <div class="dialog__body"><div class="field"><label class="field__label" for="field-name">Nom du champ</label><input class="field__control" id="field-name" name="field_name" required maxlength="80" placeholder="Ex. Orientation du terrain"></div><div class="field"><label class="field__label" for="field-value">Valeur</label><input class="field__control" id="field-value" name="field_value" required maxlength="160" placeholder="Ex. Sud-ouest"></div></div>
        <div class="dialog__footer"><button class="button button--secondary" type="button" data-modal-close>Annuler</button><button class="button button--primary" type="submit">Ajouter le champ</button></div>
    </form>
</dialog>
<dialog id="project-edit-dialog" aria-labelledby="project-edit-title"><div class="dialog__header"><h2 class="dialog__title" id="project-edit-title">Modifier le projet</h2><button class="button button--icon button--ghost" type="button" data-modal-close aria-label="Fermer"><svg class="icon"><use href="<?= e(asset('img/icons.svg')) ?>#x"></use></svg></button></div><div class="dialog__body"><p class="muted">Aperçu statique — le formulaire d’édition sera fourni par le module Projets.</p></div></dialog>
