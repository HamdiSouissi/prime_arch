<?php
declare(strict_types=1);

use App\Services\CustomFieldService;

/**
 * Blocs « image » des champs personnalisés d'un projet.
 *
 * Un champ de type image affiche sa propre galerie et son formulaire d'envoi : les fichiers
 * sont stockés dans l'espace privé du projet et rattachés au champ par sa clé technique.
 *
 * @var array<string,mixed> $project
 * @var array<int,array<string,mixed>> $imageFields champs de type image, actifs
 * @var array<string,array<int,array<string,mixed>>> $customImages images groupées par clé
 * @var array<string,string> $customImageNotices
 * @var array<string,string> $customImageErrors
 * @var bool $canManage
 */
$imageFields = is_array($imageFields ?? null) ? $imageFields : [];
$customImages = is_array($customImages ?? null) ? $customImages : [];
$customImageNotices = is_array($customImageNotices ?? null) ? $customImageNotices : [];
$customImageErrors = is_array($customImageErrors ?? null) ? $customImageErrors : [];
$canManageImages = !empty($canManage);
?>
<?php foreach ($imageFields as $field): ?>
    <?php
    $fieldKey = (string) $field['field_key'];
    $fieldImages = is_array($customImages[$fieldKey] ?? null) ? $customImages[$fieldKey] : [];
    $notice = (string) ($customImageNotices[$fieldKey] ?? '');
    $error = (string) ($customImageErrors[$fieldKey] ?? '');
    $anchor = 'custom-field-' . $fieldKey;
    ?>
    <section class="card stack-md" id="<?= e($anchor) ?>" aria-labelledby="<?= e($anchor) ?>-title">
        <div class="card-header">
            <div>
                <h2 class="card-header__title" id="<?= e($anchor) ?>-title"><?= e($field['label']) ?></h2>
                <p class="section-heading__note"><?= e(__('projects.custom_image_help', [
                    'count' => (string) CustomFieldService::IMAGE_MAX_PER_FIELD,
                ])) ?></p>
            </div>
            <span class="badge badge--neutral"><?= e((string) count($fieldImages)) ?></span>
        </div>
        <?php if ($notice !== ''): ?>
            <p class="form-feedback form-feedback--success" role="status"><?= e($notice) ?></p>
        <?php endif; ?>
        <?php if ($error !== ''): ?>
            <p class="form-feedback form-feedback--error" role="alert"><?= e($error) ?></p>
        <?php endif; ?>
        <?php if ($fieldImages === []): ?>
            <p class="muted text-footnote"><?= e(__('projects.custom_image_empty')) ?></p>
        <?php else: ?>
            <div class="project-image-grid">
                <?php foreach ($fieldImages as $image): ?>
                    <figure class="project-image-card">
                        <a class="project-image-link" href="<?= e(url('/projects/' . (int) $project['id'] . '/images/' . (int) $image['id'])) ?>"
                            target="_blank" rel="noopener noreferrer"
                            aria-label="<?= e(__('projects.image_open', ['name' => $image['original_name']])) ?>">
                            <img src="<?= e(url('/projects/' . (int) $project['id'] . '/images/' . (int) $image['id'])) ?>"
                                alt="<?= e($image['original_name']) ?>" loading="lazy" decoding="async"
                                width="<?= e((string) $image['width']) ?>" height="<?= e((string) $image['height']) ?>">
                        </a>
                        <figcaption class="project-image-caption">
                            <strong><?= e($image['original_name']) ?></strong>
                            <span><?= e(__('projects.image_dimensions', [
                                'width' => (string) $image['width'],
                                'height' => (string) $image['height'],
                            ])) ?></span>
                            <time datetime="<?= e($image['created_at']) ?>"><?= e(date('d/m/Y', strtotime($image['created_at']))) ?></time>
                        </figcaption>
                        <?php if ($canManageImages): ?>
                            <form method="post" class="project-image-delete"
                                action="<?= e(url('/projects/' . (int) $project['id'] . '/custom-fields/' . $fieldKey . '/images/' . (int) $image['id'] . '/delete')) ?>"
                                data-confirm="<?= e(__('projects.custom_image_delete_confirm', ['name' => $image['original_name']])) ?>">
                                <?= csrf_field() ?>
                                <button class="button button--small button--ghost" type="submit"><?= e(__('projects.custom_image_delete')) ?></button>
                            </form>
                        <?php endif; ?>
                    </figure>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
        <?php if ($canManageImages): ?>
            <form class="project-image-upload" method="post"
                action="<?= e(url('/projects/' . (int) $project['id'] . '/custom-fields/' . $fieldKey . '/images')) ?>"
                enctype="multipart/form-data">
                <?= csrf_field() ?>
                <div class="field">
                    <label class="field__label" for="<?= e($anchor) ?>-upload"><?= e(__('projects.custom_image_select')) ?></label>
                    <input class="field__control" id="<?= e($anchor) ?>-upload" name="images[]" type="file"
                        accept="image/jpeg,image/png,image/webp" multiple required>
                    <span class="field__hint"><?= e(__('projects.image_upload_help')) ?> · <?= e(__('projects.custom_image_batch_limit', [
                        'count' => (string) CustomFieldService::IMAGE_MAX_PER_UPLOAD,
                    ])) ?></span>
                </div>
                <button class="button button--secondary" type="submit"><?= e(__('projects.custom_image_upload')) ?></button>
            </form>
        <?php endif; ?>
    </section>
<?php endforeach; ?>
