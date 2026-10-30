<?php
declare(strict_types=1);

use App\Core\View;

$modules = is_array($modules ?? null) ? $modules : [];
$errors = is_array($errors ?? null) ? $errors : [];
?>
<?php View::partial('partials/page-header', [
    'pageTitle' => 'Modules',
    'eyebrow' => 'Extensions du cabinet',
    'subtitle' => 'Installez et gérez des modules de confiance sans modifier les fonctionnalités du noyau.',
    'secondaryAction' => ['label' => $modulesBackLabel ?? 'Réglages du cabinet', 'icon' => 'settings', 'url' => $modulesBackUrl ?? url('/settings')],
]) ?>

<?php if (!empty($notice)): ?>
    <p class="form-feedback form-feedback--success" role="status"><?= e($notice) ?></p>
<?php endif; ?>
<?php if (!empty($warning)): ?>
    <p class="form-feedback badge--warning" role="status"><?= e($warning) ?></p>
<?php endif; ?>
<?php if (!empty($errors['general'])): ?>
    <p class="form-feedback form-feedback--error" role="alert"><?= e($errors['general']) ?></p>
<?php endif; ?>

<section class="card stack-md" aria-labelledby="upload-module-heading">
    <div class="card-header">
        <div>
            <h2 class="card-header__title" id="upload-module-heading">Installer un module</h2>
            <p class="section-heading__note">Archive ZIP avec un dossier racine unique, un manifeste module.json et un point d’entrée PHP.</p>
        </div>
        <span class="badge badge--info">25 Mo maximum</span>
    </div>
    <form method="post" action="<?= e(url('/settings/modules/install')) ?>" enctype="multipart/form-data">
        <?= csrf_field() ?>
        <label class="upload-dropzone" data-upload-zone data-upload-real for="module-zip">
            <span class="upload-dropzone__icon"><svg class="icon icon--large" aria-hidden="true"><use href="<?= e(asset('img/icons.svg')) ?>#upload-cloud"></use></svg></span>
            <span class="upload-dropzone__title">Choisir une archive de module</span>
            <span class="upload-dropzone__hint"><span data-upload-name>Fichier .zip uniquement · extraction privée dans storage/modules/</span></span>
            <span class="button button--secondary button--small">Parcourir</span>
            <input class="u-sr-only" id="module-zip" name="module_zip" type="file" accept=".zip,application/zip" required aria-label="Choisir une archive ZIP de module">
        </label>
        <div class="stack-md margin-block-start-4">
            <label class="check-label">
                <input type="checkbox" name="trusted_code" value="1" required>
                Je confirme que cette archive vient d’une source de confiance et j’accepte l’exécution de son code PHP.
            </label>
            <button class="button button--primary" type="submit">Vérifier et installer</button>
        </div>
    </form>
</section>

<section class="module-grid margin-block-start-6" aria-label="Modules installés">
    <?php if ($modules === []): ?>
        <section class="card empty-state">
            <h2 class="text-headline">Aucun module installé</h2>
            <p class="muted text-footnote">Les modules vérifiés apparaîtront ici après leur installation.</p>
        </section>
    <?php endif; ?>

    <?php foreach ($modules as $module): ?>
        <?php
        $available = empty($module['availability_error']);
        $compatible = empty($module['compatibility_error']);
        $missingDependencies = is_array($module['missing_dependencies'] ?? null) ? $module['missing_dependencies'] : [];
        $enabled = (bool) ($module['is_enabled'] ?? false);
        $canActivate = $available && $compatible && $missingDependencies === [];
        $status = !$available
            ? 'Code indisponible'
            : (!$compatible
                ? 'Version incompatible'
                : ($missingDependencies !== []
                    ? 'Dépendance requise'
                    : ($enabled ? 'Activé' : 'Désactivé')));
        $tone = !$available || !$compatible
            ? 'error'
            : ($missingDependencies !== [] ? 'warning' : ($enabled ? 'success' : 'neutral'));
        ?>
        <article class="module-card">
            <div class="module-card__top">
                <div class="module-card__brand">
                    <span class="module-card__icon"><svg class="icon icon--large" aria-hidden="true"><use href="<?= e(asset('img/icons.svg')) ?>#grid"></use></svg></span>
                    <span>
                        <h2 class="module-card__title"><?= e($module['display_name'] ?? $module['name']) ?></h2>
                        <span class="module-card__version"><?= e($module['slug']) ?> · v<?= e($module['display_version'] ?? $module['version']) ?></span>
                    </span>
                </div>
                <span class="badge badge--<?= e($tone) ?>"><?= e($status) ?></span>
            </div>
            <p class="module-card__description"><?= e($module['description'] !== '' ? $module['description'] : 'Aucune description fournie.') ?></p>
            <div class="stack-md">
                <?php if (!empty($module['author'])): ?><p class="muted text-footnote">Auteur : <?= e($module['author']) ?></p><?php endif; ?>
                <?php if (!empty($module['dependencies'])): ?><p class="muted text-footnote">Dépendances : <?= e(implode(', ', $module['dependencies'])) ?></p><?php endif; ?>
                <?php if ($missingDependencies !== []): ?><p class="field__hint field__hint--error">À activer d’abord : <?= e(implode(', ', $missingDependencies)) ?></p><?php endif; ?>
                <?php if (!$available): ?><p class="field__hint field__hint--error"><?= e($module['availability_error']) ?></p><?php endif; ?>
                <?php if (!$compatible): ?><p class="field__hint field__hint--error"><?= e($module['compatibility_error']) ?></p><?php endif; ?>
                <?php if (!empty($module['archive_sha256'])): ?><p class="muted text-footnote">SHA-256 : <code><?= e(substr((string) $module['archive_sha256'], 0, 16)) ?>…</code></p><?php endif; ?>
            </div>
            <div class="module-card__footer">
                <span class="muted text-footnote">Les migrations réussies et les données sont conservées à la désactivation.</span>
                <?php if ($enabled): ?>
                    <form method="post" action="<?= e(url('/settings/modules/' . rawurlencode((string) $module['slug']) . '/deactivate')) ?>">
                        <?= csrf_field() ?>
                        <button class="button button--small button--secondary" type="submit">Désactiver</button>
                    </form>
                <?php else: ?>
                    <form method="post" action="<?= e(url('/settings/modules/' . rawurlencode((string) $module['slug']) . '/activate')) ?>">
                        <?= csrf_field() ?>
                        <button class="button button--small button--primary" type="submit" <?= $canActivate ? '' : 'disabled' ?>>Activer</button>
                    </form>
                <?php endif; ?>
            </div>
        </article>
    <?php endforeach; ?>
</section>

<section class="card module-note margin-block-start-6">
    <span class="module-note__icon"><svg class="icon" aria-hidden="true"><use href="<?= e(asset('img/icons.svg')) ?>#shield"></use></svg></span>
    <div>
        <h2 class="text-headline">Sécurité des extensions</h2>
        <p class="muted text-footnote">Une extension PHP s’exécute avec les droits du serveur : elle n’est pas sandboxée. Les contrôles ZIP bloquent les chemins traversants, liens symboliques, archives chiffrées et tailles excessives, mais ne peuvent pas rendre sûr un code malveillant. Installez uniquement des archives dont vous connaissez la provenance et dont vous avez vérifié le contenu.</p>
        <p class="muted text-footnote">Désactiver un module arrête son chargement au prochain démarrage de requête; cela ne désinstalle pas son code, ne supprime pas ses tables et ne détruit aucune donnée.</p>
    </div>
</section>
