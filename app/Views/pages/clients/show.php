<?php
declare(strict_types=1);

use App\Core\View;

$projects = [
    ['name' => 'Villa contemporaine · Sidi Bou Saïd', 'ref' => 'PA-2025-014', 'phase' => 'Avant-projet détaillé (APD)', 'value' => 76, 'tone' => ''],
    ['name' => 'Maison de vacances · Gammarth', 'ref' => 'PA-2024-032', 'phase' => 'Suivi de chantier', 'value' => 88, 'tone' => 'mint'],
];
$documents = [
    ['name' => 'Devis d’honoraires · PA-2025-018', 'meta' => 'Créé le 14 octobre 2026 · 33 916,000 DT', 'icon' => 'quote'],
    ['name' => 'Contrat de maîtrise d’œuvre', 'meta' => 'Signé le 21 septembre 2025', 'icon' => 'file-text'],
    ['name' => 'Facture d’acompte · FA-2025-009', 'meta' => 'Émise le 02 septembre 2025 · 8 500,000 DT', 'icon' => 'receipt'],
];
?>
<?php View::partial('partials/page-header', [
    'pageTitle' => 'Hichem Ben Salem',
    'eyebrow' => 'Fiche client · ' . ($recordId ?? 'CL-2025-031'),
    'subtitle' => 'Particulier · Sidi Bou Saïd, Tunis',
    'secondaryAction' => ['label' => 'Envoyer un email', 'icon' => 'mail', 'url' => 'mailto:hichem.bensalem@email.tn'],
    'primaryAction' => ['label' => 'Nouveau projet', 'icon' => 'plus', 'dialog' => '#quick-add-dialog'],
]) ?>

<div class="content-grid">
    <div class="stack-lg">
        <section class="card" aria-labelledby="client-contact-heading">
            <div class="profile-card">
                <span class="avatar avatar--large" aria-hidden="true">HB</span>
                <div class="profile-card__copy">
                    <h2 class="profile-card__name" id="client-contact-heading">Hichem Ben Salem</h2>
                    <p class="profile-card__meta">Client depuis septembre 2024 · <span class="badge badge--success">Actif</span></p>
                </div>
            </div>
            <div class="stack-md margin-block-start-5">
                <h3 class="section-heading__title">Coordonnées & informations</h3>
                <dl class="detail-list">
                    <div class="detail-list__item"><dt><svg class="icon icon--small inline-icon"><use href="<?= e(asset('img/icons.svg')) ?>#mail"></use></svg> Email</dt><dd><a href="mailto:hichem.bensalem@email.tn">hichem.bensalem@email.tn</a></dd></div>
                    <div class="detail-list__item"><dt><svg class="icon icon--small inline-icon"><use href="<?= e(asset('img/icons.svg')) ?>#phone"></use></svg> Téléphone</dt><dd><a href="tel:+21620123456">+216 20 123 456</a></dd></div>
                    <div class="detail-list__item"><dt><svg class="icon icon--small inline-icon"><use href="<?= e(asset('img/icons.svg')) ?>#map-pin"></use></svg> Adresse</dt><dd>18, rue de la Corniche, 2026 Sidi Bou Saïd, Tunis</dd></div>
                    <div class="detail-list__item"><dt>Matricule fiscal</dt><dd class="number">1234567/A/M/000</dd></div>
                </dl>
            </div>
        </section>
        <section class="card" aria-labelledby="client-projects-heading">
            <div class="card-header"><h2 class="card-header__title" id="client-projects-heading">Projets du client</h2><span class="badge badge--info badge--plain">2 projets</span></div>
            <div class="project-list">
                <?php foreach ($projects as $project): ?>
                    <a class="project-progress" href="<?= e(url('/design/projects/PA-2025-014')) ?>">
                        <div class="project-progress__top"><div class="project-progress__copy"><span class="project-progress__name"><?= e($project['name']) ?></span><span class="project-progress__meta"><?= e($project['ref']) ?> · <?= e($project['phase']) ?></span></div><span class="project-progress__value"><?= e((string) $project['value']) ?> %</span></div>
                        <?php View::partial('partials/progress', ['value' => $project['value'], 'label' => 'Avancement du projet', 'tone' => $project['tone']]) ?>
                    </a>
                <?php endforeach; ?>
            </div>
        </section>
    </div>

    <div class="stack-lg">
        <section class="card" aria-labelledby="client-notes-heading">
            <div class="card-header"><h2 class="card-header__title" id="client-notes-heading">À propos</h2><button class="button button--icon button--ghost" type="button" data-toast-message="Notes modifiables dans la version connectée." aria-label="Modifier la note"><svg class="icon icon--small"><use href="<?= e(asset('img/icons.svg')) ?>#edit"></use></svg></button></div>
            <p class="muted">Projet résidentiel familial à Sidi Bou Saïd. Préfère les échanges le matin. Réunion de suivi prévue au cabinet le 6 novembre.</p>
            <div class="cluster margin-block-start-4"><span class="badge badge--info badge--plain">Résidentiel</span><span class="badge badge--neutral badge--plain">Recommandation</span></div>
        </section>
        <section class="card" aria-labelledby="client-documents-heading">
            <div class="card-header"><h2 class="card-header__title" id="client-documents-heading">Documents récents</h2><a class="card-header__action" href="<?= e(url('/design/documents')) ?>">Tout voir</a></div>
            <?php foreach ($documents as $document): ?>
                <a class="document-row" href="<?= e(url('/design/documents/PA-2025-018')) ?>">
                    <span class="document-row__main"><span class="document-row__icon"><svg class="icon icon--small"><use href="<?= e(asset('img/icons.svg')) ?>#<?= e($document['icon']) ?>"></use></svg></span><span class="document-row__copy"><span class="document-row__name"><?= e($document['name']) ?></span><span class="document-row__meta"><?= e($document['meta']) ?></span></span></span>
                    <svg class="icon icon--small icon--directional" aria-hidden="true"><use href="<?= e(asset('img/icons.svg')) ?>#chevron-right"></use></svg>
                </a>
            <?php endforeach; ?>
        </section>
    </div>
</div>
