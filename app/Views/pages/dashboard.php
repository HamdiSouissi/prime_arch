<?php
declare(strict_types=1);

use App\Core\View;

$stats = [
    ['label' => 'Projets en cours', 'value' => '12', 'icon' => 'briefcase', 'tone' => 'blue', 'trend' => '+2', 'note' => 'vs. mois dernier'],
    ['label' => 'Clients actifs', 'value' => '28', 'icon' => 'people', 'tone' => 'turquoise', 'trend' => '+4', 'note' => 'sur les 30 derniers jours'],
    ['label' => 'Heures cette semaine', 'value' => '31,5 h', 'icon' => 'clock', 'tone' => 'coral', 'trend' => '+8 %', 'note' => 'par rapport à la cible'],
    ['label' => 'Honoraires facturés', 'value' => '48 750,000 DT', 'icon' => 'currency', 'tone' => 'mint', 'trend' => '+12 %', 'note' => 'ce trimestre'],
];
$projects = [
    ['name' => 'Villa contemporaine · Sidi Bou Saïd', 'client' => 'Famille Ben Salem', 'value' => 76, 'tone' => ''],
    ['name' => 'Résidence Les Jasmins · Sfax', 'client' => 'Promoteur El Amana', 'value' => 54, 'tone' => 'progress--mint'],
    ['name' => 'Clinique Al Yasmine · Sousse', 'client' => 'Dr. Hatem Gharbi', 'value' => 32, 'tone' => 'progress--coral'],
];
$deadlines = [
    ['day' => '06', 'month' => 'NOV', 'title' => 'Dépôt du permis de bâtir', 'project' => 'Villa Sidi Bou Saïd', 'tone' => 'warning', 'status' => 'Dans 7 jours'],
    ['day' => '09', 'month' => 'NOV', 'title' => 'Validation du dossier APD', 'project' => 'Résidence Les Jasmins', 'tone' => 'info', 'status' => 'Dans 10 jours'],
    ['day' => '12', 'month' => 'NOV', 'title' => 'Situation de travaux n° 04', 'project' => 'Clinique Al Yasmine', 'tone' => 'success', 'status' => 'Dans 13 jours'],
];
$activity = [
    ['icon' => 'file-plus', 'text' => 'Amel a préparé le devis PA-2025-018', 'time' => 'Il y a 18 min'],
    ['icon' => 'check-circle', 'text' => 'Le client Hichem Ben Salem a validé l’APS', 'time' => 'Il y a 1 h'],
    ['icon' => 'upload', 'text' => 'Nour a ajouté un plan au projet Les Jasmins', 'time' => 'Hier à 16:42'],
];
$week = [
    ['day' => 'Lun', 'hours' => '6,5', 'height' => 72],
    ['day' => 'Mar', 'hours' => '7,0', 'height' => 80],
    ['day' => 'Mer', 'hours' => '5,5', 'height' => 63],
    ['day' => 'Jeu', 'hours' => '8,0', 'height' => 92],
    ['day' => 'Ven', 'hours' => '4,5', 'height' => 52],
    ['day' => 'Sam', 'hours' => '0,0', 'height' => 3],
    ['day' => 'Dim', 'hours' => '0,0', 'height' => 3],
];
?>
<?php View::partial('partials/page-header', [
    'pageTitle' => 'Bonjour, Amel 👋',
    'eyebrow' => 'Vendredi 30 octobre 2026 · Tunis',
    'subtitle' => 'Voici un aperçu de l’activité de votre cabinet cette semaine.',
    'primaryAction' => ['label' => 'Nouvelle action', 'icon' => 'plus', 'dialog' => '#quick-add-dialog'],
    'secondaryAction' => ['label' => 'Voir les projets', 'icon' => 'arrow-up-right', 'url' => url('/design/projects')],
]) ?>

<section class="stats-grid" aria-label="Indicateurs clés">
    <?php foreach ($stats as $stat): ?>
        <?php View::partial('partials/stat-card', $stat) ?>
    <?php endforeach; ?>
</section>

<div class="dashboard-grid">
    <div class="stack-lg">
        <section class="card" aria-labelledby="project-progress-title">
            <div class="card-header">
                <div>
                    <h2 class="card-header__title" id="project-progress-title">Avancement des projets</h2>
                    <p class="section-heading__note">3 projets suivis cette semaine</p>
                </div>
                <a class="card-header__action" href="<?= e(url('/design/projects')) ?>">Tout voir <svg class="icon icon--small icon--directional" aria-hidden="true"><use href="<?= e(asset('img/icons.svg')) ?>#chevron-right"></use></svg></a>
            </div>
            <div class="project-list">
                <?php foreach ($projects as $project): ?>
                    <div class="project-progress">
                        <div class="project-progress__top">
                            <div class="project-progress__copy">
                                <span class="project-progress__name"><?= e($project['name']) ?></span>
                                <span class="project-progress__meta"><?= e($project['client']) ?></span>
                            </div>
                            <span class="project-progress__value"><?= e((string) $project['value']) ?> %</span>
                        </div>
                        <?php View::partial('partials/progress', ['value' => $project['value'], 'label' => 'Avancement', 'tone' => str_replace('progress--', '', $project['tone'])]) ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </section>

        <section class="card" aria-labelledby="hours-title">
            <div class="card-header">
                <div>
                    <h2 class="card-header__title" id="hours-title">Heures de la semaine</h2>
                    <p class="section-heading__note">Du lundi 26 au dimanche 1er novembre</p>
                </div>
                <span class="badge badge--info badge--plain">31,5 h / 35 h</span>
            </div>
            <div class="week-chart" role="img" aria-label="Graphique des heures travaillées du lundi au dimanche">
                <?php foreach ($week as $day): ?>
                    <div class="week-chart__day">
                        <div class="week-chart__track"><span class="week-chart__bar" style="--chart-height: <?= e((string) $day['height']) ?>%"></span></div>
                        <span class="week-chart__hours"><?= e($day['hours']) ?> h</span>
                        <span class="week-chart__label"><?= e($day['day']) ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
        </section>
    </div>

    <div class="stack-lg">
        <section class="card" aria-labelledby="deadlines-title">
            <div class="card-header">
                <div>
                    <h2 class="card-header__title" id="deadlines-title">Prochaines échéances</h2>
                    <p class="section-heading__note">À ne pas manquer</p>
                </div>
                <button class="button button--icon button--ghost" type="button" data-toast-message="Calendrier synchronisé avec les échéances de démonstration." aria-label="Synchroniser le calendrier"><svg class="icon"><use href="<?= e(asset('img/icons.svg')) ?>#calendar"></use></svg></button>
            </div>
            <div class="deadline-list">
                <?php foreach ($deadlines as $deadline): ?>
                    <a class="deadline-item" href="<?= e(url('/design/projects/PA-2025-014')) ?>">
                        <span class="deadline-item__date"><span class="deadline-item__day"><?= e($deadline['day']) ?></span><span><?= e($deadline['month']) ?></span></span>
                        <span class="deadline-item__copy"><strong class="deadline-item__title"><?= e($deadline['title']) ?></strong><span class="deadline-item__meta"><?= e($deadline['project']) ?></span></span>
                        <span class="badge badge--<?= e($deadline['tone']) ?> badge--plain"><?= e($deadline['status']) ?></span>
                    </a>
                <?php endforeach; ?>
            </div>
        </section>

        <section class="card" aria-labelledby="activity-title">
            <div class="card-header">
                <div>
                    <h2 class="card-header__title" id="activity-title">Activité récente</h2>
                    <p class="section-heading__note">Les dernières nouvelles du cabinet</p>
                </div>
                <button class="button button--icon button--ghost" type="button" aria-label="Plus d'options" data-toast-message="Les options de l’activité seront disponibles dans l’application." ><svg class="icon"><use href="<?= e(asset('img/icons.svg')) ?>#more"></use></svg></button>
            </div>
            <div class="activity-list">
                <?php foreach ($activity as $item): ?>
                    <div class="activity-item">
                        <span class="activity-item__icon"><svg class="icon icon--small" aria-hidden="true"><use href="<?= e(asset('img/icons.svg')) ?>#<?= e($item['icon']) ?>"></use></svg></span>
                        <span class="activity-item__copy"><span class="activity-item__text"><?= e($item['text']) ?></span><span class="activity-item__time"><?= e($item['time']) ?></span></span>
                    </div>
                <?php endforeach; ?>
            </div>
        </section>
    </div>
</div>
