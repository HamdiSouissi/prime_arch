<?php
declare(strict_types=1);

use App\Core\View;

$entries = [
    ['project' => 'Villa contemporaine · Ben Salem', 'phase' => 'APD', 'description' => 'Révision des coupes et façade sud', 'day' => 'Aujourd’hui', 'hours' => '2,50 h', 'billable' => '437,500 DT'],
    ['project' => 'Résidence Les Jasmins · El Amana', 'phase' => 'APS', 'description' => 'Coordination structure et lots techniques', 'day' => 'Aujourd’hui', 'hours' => '1,75 h', 'billable' => '306,250 DT'],
    ['project' => 'Clinique Al Yasmine · Gharbi', 'phase' => 'DCE', 'description' => 'Revue du cahier des prescriptions', 'day' => 'Hier', 'hours' => '3,00 h', 'billable' => '525,000 DT'],
    ['project' => 'Bureaux Cap Bon · Groupe Cap Bon', 'phase' => 'Permis', 'description' => 'Dépôt administratif à Nabeul', 'day' => 'Mardi', 'hours' => '1,50 h', 'billable' => '262,500 DT'],
];
?>
<?php View::partial('partials/page-header', [
    'pageTitle' => 'Temps & coûts',
    'eyebrow' => 'Suivi de la semaine · 26 oct. – 1 nov.',
    'subtitle' => 'Transformez vos heures en visibilité claire sur la charge et la rentabilité.',
    'primaryAction' => ['label' => 'Ajouter une saisie', 'icon' => 'plus', 'dialog' => '#time-entry-dialog'],
]) ?>

<section class="stats-grid" aria-label="Récapitulatif de la semaine">
    <?php View::partial('partials/stat-card', ['label' => 'Temps saisi', 'value' => '31,5 h', 'icon' => 'clock', 'tone' => 'blue', 'note' => 'sur un objectif de 35 h']) ?>
    <?php View::partial('partials/stat-card', ['label' => 'Temps facturable', 'value' => '27,0 h', 'icon' => 'currency', 'tone' => 'mint', 'note' => '85,7 % du temps saisi']) ?>
    <?php View::partial('partials/stat-card', ['label' => 'Honoraires valorisés', 'value' => '5 512,500 DT', 'icon' => 'wallet', 'tone' => 'coral', 'note' => 'à 175,000 DT / heure']) ?>
    <?php View::partial('partials/stat-card', ['label' => 'Projets travaillés', 'value' => '6', 'icon' => 'briefcase', 'tone' => 'pink', 'note' => 'sur 12 projets actifs']) ?>
</section>

<div class="content-grid">
    <section class="timer-card" data-timer data-elapsed="0" aria-labelledby="timer-heading">
        <span class="timer-card__status"><span class="timer-card__dot" aria-hidden="true"></span><span data-timer-status>Prêt à démarrer</span></span>
        <h2 class="u-sr-only" id="timer-heading">Chronomètre</h2>
        <p class="timer-card__display number" data-timer-display aria-live="off">00:00:00</p>
        <div class="field timer-context"><label class="field__label" for="timer-project">Projet & phase</label><select id="timer-project"><option>Villa contemporaine · APD</option><option>Résidence Les Jasmins · APS</option><option>Clinique Al Yasmine · DCE</option></select></div>
        <div class="timer-card__actions"><button class="button button--primary" type="button" data-timer-toggle aria-pressed="false"><svg class="icon icon--small"><use href="<?= e(asset('img/icons.svg')) ?>#timer"></use></svg><span data-timer-label>Démarrer le chronomètre</span></button><button class="button button--secondary" type="button" data-timer-reset>Réinitialiser</button></div>
    </section>
    <section class="card" aria-labelledby="weekly-summary-heading">
        <div class="card-header"><div><h2 class="card-header__title" id="weekly-summary-heading">Cette semaine</h2><p class="section-heading__note">Objectif cabinet : 35 heures</p></div><span class="badge badge--success">À jour</span></div>
        <?php View::partial('partials/progress', ['value' => 90, 'label' => '31,5 h sur 35 h', 'tone' => 'mint']) ?>
        <div class="summary-metrics margin-block-start-5"><div><span class="muted text-footnote">Facturable</span><strong>27,0 h</strong></div><div><span class="muted text-footnote">Non facturable</span><strong>4,5 h</strong></div><div><span class="muted text-footnote">Équipe</span><strong>4 personnes</strong></div></div>
        <a class="card-header__action margin-block-start-4" href="<?= e(url('/design/projects')) ?>">Voir les projets <svg class="icon icon--small icon--directional"><use href="<?= e(asset('img/icons.svg')) ?>#chevron-right"></use></svg></a>
    </section>
</div>

<section class="card margin-block-start-6" aria-labelledby="time-entries-heading">
    <div class="card-header"><div><h2 class="card-header__title" id="time-entries-heading">Saisies récentes</h2><p class="section-heading__note">Par projet et par phase</p></div><button class="button button--secondary button--small" type="button" data-toast-message="Export CSV préparé en démonstration." data-toast-tone="info"><svg class="icon icon--small"><use href="<?= e(asset('img/icons.svg')) ?>#download"></use></svg> Exporter</button></div>
    <div class="table-wrap"><table class="data-table"><thead><tr><th scope="col">Projet / phase</th><th scope="col">Description</th><th scope="col">Jour</th><th scope="col">Durée</th><th scope="col">Valorisation</th></tr></thead><tbody>
        <?php foreach ($entries as $entry): ?><tr><td><span class="table-primary__text"><strong class="table-primary__name"><?= e($entry['project']) ?></strong><span class="table-primary__meta">Phase <?= e($entry['phase']) ?></span></span></td><td><?= e($entry['description']) ?></td><td><?= e($entry['day']) ?></td><td class="number"><strong><?= e($entry['hours']) ?></strong></td><td class="number"><?= e($entry['billable']) ?></td></tr><?php endforeach; ?>
    </tbody></table></div>
</section>

<dialog class="dialog--wide" id="time-entry-dialog" aria-labelledby="time-entry-title"><form method="post" action="#" data-demo-form>
    <?= csrf_field() ?>
    <div class="dialog__header"><h2 class="dialog__title" id="time-entry-title">Nouvelle saisie de temps</h2><button class="button button--icon button--ghost" type="button" data-modal-close aria-label="Fermer"><svg class="icon"><use href="<?= e(asset('img/icons.svg')) ?>#x"></use></svg></button></div>
    <div class="dialog__body"><div class="field"><label class="field__label" for="time-project">Projet</label><select id="time-project" name="project"><option>Villa contemporaine · APD</option><option>Résidence Les Jasmins · APS</option></select></div><div class="field"><label class="field__label" for="time-description">Description</label><input id="time-description" name="description" placeholder="Ex. Révision des plans d’étage"></div><div class="content-grid"><div class="field"><label class="field__label" for="time-duration">Durée (heures)</label><input id="time-duration" type="number" name="duration" inputmode="decimal" step="0.25" min="0.25" value="1.00"></div><div class="field"><label class="field__label" for="time-day">Date</label><input id="time-day" type="date" name="date" value="2026-10-30"></div></div></div>
    <div class="dialog__footer"><button class="button button--secondary" type="button" data-modal-close>Annuler</button><button class="button button--primary" type="submit">Enregistrer la saisie</button></div>
</form></dialog>
