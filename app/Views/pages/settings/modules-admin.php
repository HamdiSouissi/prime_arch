<?php
declare(strict_types=1);

use App\Core\View;

$modules = is_array($modules ?? null) ? $modules : [];
$errors = is_array($errors ?? null) ? $errors : [];
?>
<?php View::partial('partials/page-header', [
    'pageTitle' => __('modules.title'),
    'eyebrow' => __('settings.modules_title'),
    'subtitle' => __('settings.modules_text'),
    'secondaryAction' => ['label' => $modulesBackLabel ?? __('settings.title'), 'icon' => 'settings', 'url' => $modulesBackUrl ?? url('/settings')],
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
            <h2 class="card-header__title" id="upload-module-heading"><?= e(__('modules.install_title')) ?></h2>
            <p class="section-heading__note"><?= e(__('modules.install_help')) ?></p>
        </div>
        <span class="badge badge--info"><?= e(__('modules.max_size')) ?></span>
    </div>
    <form method="post" action="<?= e(url('/settings/modules/install')) ?>" enctype="multipart/form-data">
        <?= csrf_field() ?>
        <label class="upload-dropzone" data-upload-zone data-upload-real for="module-zip">
            <span class="upload-dropzone__icon"><svg class="icon icon--large" aria-hidden="true"><use href="<?= e(asset('img/icons.svg')) ?>#upload-cloud"></use></svg></span>
            <span class="upload-dropzone__title"><?= e(__('modules.choose_archive')) ?></span>
            <span class="upload-dropzone__hint"><span data-upload-name><?= e(__('modules.file_help')) ?></span></span>
            <span class="button button--secondary button--small"><?= e(__('modules.browse')) ?></span>
            <input class="u-sr-only" id="module-zip" name="module_zip" type="file" accept=".zip,application/zip" required aria-label="<?= e(__('modules.choose_archive_label')) ?>">
        </label>
        <div class="stack-md margin-block-start-4">
            <label class="check-label">
                <input type="checkbox" name="trusted_code" value="1" required>
                <?= e(__('modules.confirm_source')) ?>
            </label>
            <button class="button button--primary" type="submit"><?= e(__('modules.verify_install')) ?></button>
        </div>
    </form>
</section>

<section class="module-grid margin-block-start-6" aria-label="<?= e(__('modules.installed_label')) ?>">
    <?php if ($modules === []): ?>
        <section class="card empty-state">
            <h2 class="text-headline"><?= e(__('modules.none_title')) ?></h2>
            <p class="muted text-footnote"><?= e(__('modules.none_help')) ?></p>
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
            ? __('modules.status_unavailable')
            : (!$compatible
                ? __('modules.status_incompatible')
                : ($missingDependencies !== []
                    ? __('modules.status_dependency')
                    : ($enabled ? __('common.enabled') : __('common.disabled'))));
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
            <p class="module-card__description"><?= e($module['description'] !== '' ? $module['description'] : __('modules.no_description')) ?></p>
            <div class="stack-md">
                <?php if (!empty($module['author'])): ?><p class="muted text-footnote"><?= e(__('modules.author')) ?> : <?= e($module['author']) ?></p><?php endif; ?>
                <?php if (!empty($module['dependencies'])): ?><p class="muted text-footnote"><?= e(__('modules.dependencies')) ?> : <?= e(implode(', ', $module['dependencies'])) ?></p><?php endif; ?>
                <?php if ($missingDependencies !== []): ?><p class="field__hint field__hint--error"><?= e(__('modules.activate_first')) ?> : <?= e(implode(', ', $missingDependencies)) ?></p><?php endif; ?>
                <?php if (!$available): ?><p class="field__hint field__hint--error"><?= e($module['availability_error']) ?></p><?php endif; ?>
                <?php if (!$compatible): ?><p class="field__hint field__hint--error"><?= e($module['compatibility_error']) ?></p><?php endif; ?>
                <?php if (!empty($module['archive_sha256'])): ?><p class="muted text-footnote"><?= e(__('modules.checksum')) ?> : <code><?= e(substr((string) $module['archive_sha256'], 0, 16)) ?>…</code></p><?php endif; ?>
            </div>
            <div class="module-card__footer">
                <span class="muted text-footnote"><?= e(__('modules.data_retained')) ?></span>
                <?php if ($enabled): ?>
                    <form method="post" action="<?= e(url('/settings/modules/' . rawurlencode((string) $module['slug']) . '/deactivate')) ?>">
                        <?= csrf_field() ?>
                        <button class="button button--small button--secondary" type="submit"><?= e(__('modules.deactivate')) ?></button>
                    </form>
                <?php else: ?>
                    <form method="post" action="<?= e(url('/settings/modules/' . rawurlencode((string) $module['slug']) . '/activate')) ?>">
                        <?= csrf_field() ?>
                        <button class="button button--small button--primary" type="submit" <?= $canActivate ? '' : 'disabled' ?>><?= e(__('modules.activate')) ?></button>
                    </form>
                <?php endif; ?>
            </div>
        </article>
    <?php endforeach; ?>
</section>

<section class="card module-note margin-block-start-6">
    <span class="module-note__icon"><svg class="icon" aria-hidden="true"><use href="<?= e(asset('img/icons.svg')) ?>#shield"></use></svg></span>
    <div>
        <h2 class="text-headline"><?= e(__('modules.security_title')) ?></h2>
        <p class="muted text-footnote"><?= e(__('modules.security_text')) ?></p>
        <p class="muted text-footnote"><?= e(__('modules.deactivate_help')) ?></p>
    </div>
</section>
