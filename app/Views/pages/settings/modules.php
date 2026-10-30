<?php
declare(strict_types=1);

use App\Core\View;

$modules = [
    ['name' => 'Clients', 'slug' => 'clients', 'version' => '1.4.2', 'description' => 'Fiches clients, contacts, historique et suivi commercial.', 'icon' => 'people', 'enabled' => true, 'status' => 'Activé'],
    ['name' => 'Projets & phases', 'slug' => 'projects', 'version' => '2.1.0', 'description' => 'Portefeuille de projets, phases APS / APD / DCE et échéances.', 'icon' => 'briefcase', 'enabled' => true, 'status' => 'Activé'],
    ['name' => 'Documents & PDF', 'slug' => 'documents', 'version' => '1.2.8', 'description' => 'Devis, contrats, factures et modèles PDF pour le cabinet.', 'icon' => 'file-text', 'enabled' => true, 'status' => 'Activé'],
    ['name' => 'Temps & coûts', 'slug' => 'time-tracking', 'version' => '1.0.4', 'description' => 'Chronomètre, saisies par phase et rentabilité des missions.', 'icon' => 'clock', 'enabled' => false, 'status' => 'Désactivé'],
    ['name' => 'Bibliothèque BIM', 'slug' => 'bim-library', 'version' => '0.9.1', 'description' => 'Ressources, détails constructifs et objets de référence.', 'icon' => 'layers', 'enabled' => false, 'status' => 'Désactivé'],
    ['name' => 'Export comptable', 'slug' => 'accounting-export', 'version' => '1.1.0', 'description' => 'Préparation des écritures et export des pièces justificatives.', 'icon' => 'currency', 'enabled' => true, 'status' => 'Activé'],
];
?>
<?php View::partial('partials/page-header', [
    'pageTitle' => 'Modules',
    'eyebrow' => 'Extensions du cabinet',
    'subtitle' => 'Activez les outils dont votre équipe a besoin. Les modules sont isolés et ajoutent leurs propres vues et styles.',
    'secondaryAction' => ['label' => 'Réglages', 'icon' => 'settings', 'url' => url('/design/settings')],
]) ?>

<section class="card stack-md" aria-labelledby="upload-module-heading">
    <div class="card-header"><div><h2 class="card-header__title" id="upload-module-heading">Installer un module</h2><p class="section-heading__note">Prévisualisation d’une archive · aucun téléversement réel n’est effectué.</p></div><span class="badge badge--info">Format .zip</span></div>
    <label class="upload-dropzone" data-upload-zone for="module-zip">
        <span class="upload-dropzone__icon"><svg class="icon icon--large"><use href="<?= e(asset('img/icons.svg')) ?>#upload-cloud"></use></svg></span>
        <span class="upload-dropzone__title">Glissez-déposez un module ici</span>
        <span class="upload-dropzone__hint"><span data-upload-name>ou choisissez une archive .zip depuis votre appareil</span></span>
        <span class="button button--secondary button--small">Choisir un fichier</span>
        <input class="u-sr-only" id="module-zip" name="module_zip" type="file" accept=".zip,application/zip" aria-label="Choisir une archive ZIP de module">
    </label>
</section>

<section class="module-grid margin-block-start-6" aria-label="Modules installés">
    <?php foreach ($modules as $module): ?>
        <article class="module-card">
            <div class="module-card__top">
                <div class="module-card__brand"><span class="module-card__icon"><svg class="icon icon--large"><use href="<?= e(asset('img/icons.svg')) ?>#<?= e($module['icon']) ?>"></use></svg></span><span><h2 class="module-card__title"><?= e($module['name']) ?></h2><span class="module-card__version"><?= e($module['slug']) ?> · v<?= e($module['version']) ?></span></span></div>
                <button class="switch" type="button" role="switch" aria-label="<?= e(($module['enabled'] ? 'Désactiver' : 'Activer') . ' le module ' . $module['name']) ?>" aria-checked="<?= e($module['enabled'] ? 'true' : 'false') ?>" data-switch data-toast-message="L’état du module a été modifié uniquement dans cet aperçu." data-toast-tone="info"></button>
            </div>
            <p class="module-card__description"><?= e($module['description']) ?></p>
            <div class="module-card__footer"><span class="badge badge--<?= e($module['enabled'] ? 'success' : 'neutral') ?>"><?= e($module['status']) ?></span><button class="button button--small button--ghost" type="button" data-toast-message="Détails du module <?= e($module['name']) ?>" data-toast-tone="info">Détails <svg class="icon icon--small icon--directional"><use href="<?= e(asset('img/icons.svg')) ?>#arrow-up-right"></use></svg></button></div>
        </article>
    <?php endforeach; ?>
</section>

<section class="card module-note margin-block-start-6"><span class="module-note__icon"><svg class="icon"><use href="<?= e(asset('img/icons.svg')) ?>#shield"></use></svg></span><div><h2 class="text-headline">Une architecture modulaire, maîtrisée</h2><p class="muted text-footnote">Les extensions enregistrent leurs vues, traductions et migrations dans leur dossier. Activez uniquement les modules de confiance et vérifiez leur provenance avant installation.</p><a class="card-header__action" href="<?= e(url('/design/styleguide')) ?>">Guide d’extension UI <svg class="icon icon--small icon--directional"><use href="<?= e(asset('img/icons.svg')) ?>#chevron-right"></use></svg></a></div></section>
