<?php
declare(strict_types=1);

use App\Core\View;

$projects = [
    ['name' => 'Villa contemporaine', 'client' => 'Hichem Ben Salem', 'ref' => 'PA-2025-014', 'city' => 'Sidi Bou Saïd', 'type' => 'Résidentiel', 'phase' => 'APD', 'value' => 76, 'status' => 'En cours', 'tone' => 'info', 'budget' => '42 500,000 DT'],
    ['name' => 'Résidence Les Jasmins', 'client' => 'Société El Amana', 'ref' => 'PA-2025-021', 'city' => 'Sfax', 'type' => 'Logement collectif', 'phase' => 'APS', 'value' => 54, 'status' => 'En cours', 'tone' => 'info', 'budget' => '86 000,000 DT'],
    ['name' => 'Clinique Al Yasmine', 'client' => 'Dr. Hatem Gharbi', 'ref' => 'PA-2025-008', 'city' => 'Sousse', 'type' => 'Santé', 'phase' => 'DCE', 'value' => 32, 'status' => 'À valider', 'tone' => 'warning', 'budget' => '58 750,000 DT'],
    ['name' => 'Maison de vacances', 'client' => 'Nadia Khelifi', 'ref' => 'PA-2024-032', 'city' => 'Gammarth', 'type' => 'Résidentiel', 'phase' => 'Chantier', 'value' => 88, 'status' => 'En cours', 'tone' => 'success', 'budget' => '31 200,000 DT'],
    ['name' => 'Bureaux Cap Bon', 'client' => 'Groupe Cap Bon', 'ref' => 'PA-2025-003', 'city' => 'Nabeul', 'type' => 'Tertiaire', 'phase' => 'Permis', 'value' => 41, 'status' => 'En cours', 'tone' => 'info', 'budget' => '64 800,000 DT'],
    ['name' => 'Maison de ville', 'client' => 'Sana Gharbi', 'ref' => 'PA-2024-026', 'city' => 'La Marsa', 'type' => 'Résidentiel', 'phase' => 'Réception', 'value' => 100, 'status' => 'Terminé', 'tone' => 'success', 'budget' => '28 400,000 DT'],
];
?>
<?php View::partial('partials/page-header', [
    'pageTitle' => 'Projets',
    'eyebrow' => 'Portefeuille de l’agence',
    'subtitle' => 'Suivez les phases, échéances et budgets de vos projets architecturaux.',
    'primaryAction' => ['label' => 'Nouveau projet', 'icon' => 'plus', 'dialog' => '#quick-add-dialog'],
]) ?>

<section class="card stack-md">
    <div class="filter-row">
        <div class="input-with-icon"><svg class="icon icon--small" aria-hidden="true"><use href="<?= e(asset('img/icons.svg')) ?>#search"></use></svg><input type="search" placeholder="Nom, client ou référence…" aria-label="Rechercher un projet" data-card-search="#project-cards"></div>
        <div class="field"><label class="u-sr-only" for="project-status">Statut du projet</label><select id="project-status" aria-label="Filtrer les projets" data-filter-select="#project-cards"><option value="">Tous les statuts</option><option>En cours</option><option>À valider</option><option>Terminé</option></select></div>
        <div class="view-toggle" data-view-toggle="#project-cards" role="group" aria-label="Mode d'affichage">
            <button class="view-toggle__button" type="button" data-view-mode="grid" aria-pressed="true" aria-label="Afficher en grille"><svg class="icon icon--small"><use href="<?= e(asset('img/icons.svg')) ?>#grid-view"></use></svg></button>
            <button class="view-toggle__button" type="button" data-view-mode="list" aria-pressed="false" aria-label="Afficher en liste"><svg class="icon icon--small"><use href="<?= e(asset('img/icons.svg')) ?>#list"></use></svg></button>
        </div>
    </div>
    <div class="project-cards" id="project-cards">
        <?php foreach ($projects as $project): ?>
            <a class="project-card" href="<?= e(url('/design/projects/PA-2025-014')) ?>" data-project-card data-filter-value="<?= e($project['status']) ?>">
                <div class="project-card__top"><span class="project-card__symbol"><svg class="icon icon--large"><use href="<?= e(asset('img/icons.svg')) ?>#building"></use></svg></span><span class="badge badge--<?= e($project['tone']) ?>"><?= e($project['status']) ?></span></div>
                <div><h2 class="project-card__title"><?= e($project['name']) ?></h2><p class="project-card__client"><?= e($project['client']) ?></p></div>
                <div class="project-card__details"><span><?= e($project['ref']) ?></span><span><?= e($project['city']) ?></span></div>
                <?php View::partial('partials/progress', ['value' => $project['value'], 'label' => 'Phase ' . $project['phase'], 'tone' => $project['value'] >= 85 ? 'mint' : ($project['value'] < 40 ? 'coral' : '')]) ?>
                <div class="project-card__details"><span><?= e($project['type']) ?></span><strong class="number"><?= e($project['budget']) ?></strong></div>
            </a>
        <?php endforeach; ?>
    </div>
    <div class="cluster cluster--between"><span class="muted text-footnote">6 projets affichés sur 12 actifs</span><button class="button button--small button--secondary" type="button" data-toast-message="Tous les projets de démonstration sont déjà affichés.">Charger plus</button></div>
</section>
