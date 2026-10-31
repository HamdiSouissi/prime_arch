<?php
declare(strict_types=1);

use App\Services\CustomFieldService;
use App\Services\ProjectFileStorage;

/**
 * Blocs « fichier » (plans AutoCAD) des champs personnalisés d'un projet.
 *
 * Un champ de type fichier affiche la liste de ses plans et son formulaire d'envoi. Les
 * fichiers vivent dans l'espace privé du projet : chaque ligne ne propose qu'un lien de
 * téléchargement authentifié, jamais une URL publique.
 *
 * @var array<string,mixed> $project
 * @var array<int,array<string,mixed>> $fileFields champs de type fichier, actifs
 * @var array<string,array<int,array<string,mixed>>> $customFiles fichiers groupés par clé
 * @var array<string,string> $customFileNotices
 * @var array<string,string> $customFileErrors
 * @var bool $canManage
 */
$fileFields = is_array($fileFields ?? null) ? $fileFields : [];
$customFiles = is_array($customFiles ?? null) ? $customFiles : [];
$customFileNotices = is_array($customFileNotices ?? null) ? $customFileNotices : [];
$customFileErrors = is_array($customFileErrors ?? null) ? $customFileErrors : [];
$canManageFiles = !empty($canManage);
$accept = '.' . implode(',.', ProjectFileStorage::EXTENSIONS);

/** Taille lisible, en français comme en arabe : « 2,4 Mo ». */
$readableSize = static function (int $bytes): string {
    if ($bytes >= 1_048_576) {
        return str_replace('.', ',', number_format($bytes / 1_048_576, 1, '.', ' ')) . ' ' . __('projects.file_size_mb');
    }

    return str_replace('.', ',', number_format(max(1, (int) round($bytes / 1024)), 0, '.', ' ')) . ' ' . __('projects.file_size_kb');
};
?>
<?php foreach ($fileFields as $field): ?>
    <?php
    $fieldKey = (string) $field['field_key'];
    $fieldFiles = is_array($customFiles[$fieldKey] ?? null) ? $customFiles[$fieldKey] : [];
    $notice = (string) ($customFileNotices[$fieldKey] ?? '');
    $error = (string) ($customFileErrors[$fieldKey] ?? '');
    $anchor = 'custom-field-' . $fieldKey;
    ?>
    <section class="card stack-md" id="<?= e($anchor) ?>" aria-labelledby="<?= e($anchor) ?>-title">
        <div class="card-header">
            <div>
                <h2 class="card-header__title" id="<?= e($anchor) ?>-title"><?= e($field['label']) ?></h2>
                <p class="section-heading__note"><?= e(__('projects.custom_file_help', [
                    'count' => (string) CustomFieldService::FILE_MAX_PER_FIELD,
                ])) ?></p>
            </div>
            <span class="badge badge--neutral"><?= e((string) count($fieldFiles)) ?></span>
        </div>
        <?php if ($notice !== ''): ?>
            <p class="form-feedback form-feedback--success" role="status"><?= e($notice) ?></p>
        <?php endif; ?>
        <?php if ($error !== ''): ?>
            <p class="form-feedback form-feedback--error" role="alert"><?= e($error) ?></p>
        <?php endif; ?>
        <?php if ($fieldFiles === []): ?>
            <p class="muted text-footnote"><?= e(__('projects.custom_file_empty')) ?></p>
        <?php else: ?>
            <ul class="project-file-list">
                <?php foreach ($fieldFiles as $file): ?>
                    <li class="project-file-item">
                        <div class="project-file-item__main">
                            <span class="badge badge--neutral"><?= e(strtoupper($file['extension'])) ?></span>
                            <a class="project-file-item__name"
                                href="<?= e(url('/projects/' . (int) $project['id'] . '/files/' . (int) $file['id'])) ?>"><?= e($file['original_name']) ?></a>
                            <span class="project-file-item__meta"><?= e($readableSize((int) $file['file_size'])) ?>
                                · <time datetime="<?= e($file['created_at']) ?>"><?= e(date('d/m/Y', strtotime($file['created_at']))) ?></time>
                            </span>
                        </div>
                        <div class="project-file-item__actions">
                            <a class="button button--small button--secondary"
                                href="<?= e(url('/projects/' . (int) $project['id'] . '/files/' . (int) $file['id'])) ?>"><?= e(__('projects.file_download')) ?></a>
                            <?php if ($canManageFiles): ?>
                                <form method="post"
                                    action="<?= e(url('/projects/' . (int) $project['id'] . '/custom-fields/' . $fieldKey . '/files/' . (int) $file['id'] . '/delete')) ?>"
                                    data-confirm="<?= e(__('projects.custom_file_delete_confirm', ['name' => $file['original_name']])) ?>">
                                    <?= csrf_field() ?>
                                    <button class="button button--small button--ghost" type="submit"><?= e(__('projects.custom_file_delete')) ?></button>
                                </form>
                            <?php endif; ?>
                        </div>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
        <?php if ($canManageFiles): ?>
            <form class="project-file-upload" method="post"
                action="<?= e(url('/projects/' . (int) $project['id'] . '/custom-fields/' . $fieldKey . '/files')) ?>"
                enctype="multipart/form-data">
                <?= csrf_field() ?>
                <div class="field">
                    <label class="field__label" for="<?= e($anchor) ?>-upload"><?= e(__('projects.custom_file_select')) ?></label>
                    <input class="field__control" id="<?= e($anchor) ?>-upload" name="files[]" type="file"
                        accept="<?= e($accept) ?>" multiple required>
                    <span class="field__hint"><?= e(__('projects.file_upload_help')) ?> · <?= e(__('projects.custom_file_batch_limit', [
                        'count' => (string) CustomFieldService::FILE_MAX_PER_UPLOAD,
                    ])) ?></span>
                </div>
                <button class="button button--secondary" type="submit"><?= e(__('projects.custom_file_upload')) ?></button>
            </form>
        <?php endif; ?>
    </section>
<?php endforeach; ?>
