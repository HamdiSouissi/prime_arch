<?php
declare(strict_types=1);

use App\Core\Auth;
use App\Core\View;
use Modules\GedVersions\Services\GedService;

$document = is_array($document ?? null) ? $document : [];
$versions = is_array($versions ?? null) ? $versions : [];
$categories = is_array($categories ?? null) ? $categories : GedService::CATEGORIES;
$clients = is_array($clients ?? null) ? $clients : [];
$projects = is_array($projects ?? null) ? $projects : [];
$documentErrors = is_array($documentErrors ?? null) ? $documentErrors : [];
$documentValues = is_array($documentValues ?? null) ? $documentValues : [];
$versionErrors = is_array($versionErrors ?? null) ? $versionErrors : [];
$versionValues = is_array($versionValues ?? null) ? $versionValues : [];

$dateLabel = static function (?string $value): string {
    $timestamp = $value !== null && $value !== '' ? strtotime($value) : false;

    return $timestamp === false ? '—' : date('d/m/Y', $timestamp);
};
$dateTimeLabel = static function (?string $value): string {
    $timestamp = $value !== null && $value !== '' ? strtotime($value) : false;

    return $timestamp === false ? '—' : date('d/m/Y H:i', $timestamp);
};
$errorHint = static fn (array $errors, string $field): string => (string) ($errors[$field] ?? '');
$orDash = static fn (string $value): string => trim($value) !== '' ? $value : '—';
$retentionBadge = static fn (string $state): string => match ($state) {
    'past' => 'badge--error',
    'due' => 'badge--warning',
    'soon' => 'badge--info',
    'running' => 'badge--success',
    default => 'badge--neutral',
};
$retention = static function (array $row): string {
    $state = (string) ($row['retention_state'] ?? 'none');
    $days = $row['retention_days'] ?? null;
    if ($state === 'none' || $days === null) {
        return __('ged.retention.none');
    }
    if ((int) $days < 0) {
        return __('ged.retention.past', ['days' => (string) abs((int) $days)]);
    }
    if ((int) $days === 0) {
        return __('ged.retention.due');
    }
    if ($state === 'running') {
        return __('ged.retention.scheduled');
    }

    return __('ged.retention.in_days', ['days' => (string) $days]);
};
$values = $documentValues !== [] ? $documentValues : [
    'title' => (string) ($document['title'] ?? ''),
    'category' => (string) ($document['category'] ?? 'other'),
    'client_id' => (string) ($document['client_id'] ?? ''),
    'project_id' => (string) ($document['project_id'] ?? ''),
    'tags' => (string) ($document['tags'] ?? ''),
    'description' => (string) ($document['description'] ?? ''),
    'retention_until' => (string) ($document['retention_until'] ?? ''),
];
$tags = GedService::tagList(isset($document['tags']) ? (string) $document['tags'] : null);
$archived = ($document['archived_at'] ?? null) !== null && (string) $document['archived_at'] !== '';
$documentId = (int) ($document['id'] ?? 0);
$versionCount = count($versions);
?>
<?php View::partial('partials/page-header', [
    'pageTitle' => (string) ($document['title'] ?? ''),
    'eyebrow' => trim((string) ($document['reference'] ?? '')) !== ''
        ? (string) $document['reference']
        : __('ged.detail_eyebrow'),
    'subtitle' => implode(' · ', array_filter([
        __('ged.category.' . (string) ($document['category'] ?? 'other')),
        (string) ($document['client_name'] ?? ''),
        (string) ($document['project_name'] ?? ''),
    ], static fn (string $part): bool => trim($part) !== '')),
    'secondaryAction' => [
        'label' => __('ged.back_to_list'),
        'icon' => 'arrow-left',
        'url' => url('/ged'),
    ],
]) ?>

<?php if (!empty($notice)): ?><p class="form-feedback form-feedback--success" role="status"><?= e($notice) ?></p><?php endif; ?>
<?php if (!empty($error)): ?><p class="form-feedback form-feedback--error" role="alert"><?= e($error) ?></p><?php endif; ?>

<section class="card stack-md" aria-labelledby="ged-detail-title">
    <div class="card-header">
        <div>
            <h2 class="card-header__title" id="ged-detail-title"><?= e(__('ged.detail_title')) ?></h2>
            <p class="section-heading__note"><?= e(__('ged.detail_help')) ?></p>
        </div>
        <div class="cluster">
            <span class="badge badge--neutral"><?= e(__('ged.category.' . (string) ($document['category'] ?? 'other'))) ?></span>
            <?php if ($archived): ?>
                <span class="badge badge--warning"><?= e(__('ged.archived')) ?></span>
            <?php endif; ?>
        </div>
    </div>

    <dl class="document-summary-grid">
        <div>
            <dt class="detail-label"><?= e(__('ged.reference')) ?></dt>
            <dd><?= e($orDash((string) ($document['reference'] ?? ''))) ?></dd>
        </div>
        <div>
            <dt class="detail-label"><?= e(__('ged.field.client')) ?></dt>
            <dd>
                <?php if (($document['client_id'] ?? null) !== null): ?>
                    <?php if (Auth::can('clients.view')): ?>
                        <a href="<?= e(url('/clients/' . (int) $document['client_id'])) ?>"><?= e((string) ($document['client_name'] ?? '')) ?></a>
                    <?php else: ?>
                        <?= e((string) ($document['client_name'] ?? '')) ?>
                    <?php endif; ?>
                <?php else: ?>
                    <span class="muted"><?= e(__('ged.not_linked')) ?></span>
                <?php endif; ?>
            </dd>
        </div>
        <div>
            <dt class="detail-label"><?= e(__('ged.field.project')) ?></dt>
            <dd>
                <?php if (($document['project_id'] ?? null) !== null): ?>
                    <?php if (Auth::can('projects.view')): ?>
                        <a href="<?= e(url('/projects/' . (int) $document['project_id'])) ?>"><?= e((string) ($document['project_name'] ?? '')) ?></a>
                    <?php else: ?>
                        <?= e((string) ($document['project_name'] ?? '')) ?>
                    <?php endif; ?>
                    <span class="table-meta"><?= e((string) ($document['project_reference'] ?? '')) ?></span>
                <?php else: ?>
                    <span class="muted"><?= e(__('ged.not_linked')) ?></span>
                <?php endif; ?>
            </dd>
        </div>
        <div>
            <dt class="detail-label"><?= e(__('ged.field.retention')) ?></dt>
            <dd>
                <?php if (($document['retention_until'] ?? null) !== null): ?>
                    <span class="badge <?= e($retentionBadge((string) ($document['retention_state'] ?? 'none'))) ?>"><?= e($retention($document)) ?></span>
                    <span class="table-meta"><?= e($dateLabel((string) $document['retention_until'])) ?></span>
                <?php else: ?>
                    <span class="muted"><?= e(__('ged.retention.none')) ?></span>
                <?php endif; ?>
            </dd>
        </div>
        <div>
            <dt class="detail-label"><?= e(__('ged.versions')) ?></dt>
            <dd class="u-numeric"><?= $versionCount ?></dd>
        </div>
        <div>
            <dt class="detail-label"><?= e(__('ged.total_size')) ?></dt>
            <dd class="u-numeric"><?= e((string) ($document['size_label'] ?? '')) ?></dd>
        </div>
        <div>
            <dt class="detail-label"><?= e(__('ged.field.created_by')) ?></dt>
            <dd><?= e($orDash((string) ($document['author_name'] ?? ''))) ?></dd>
        </div>
        <div>
            <dt class="detail-label"><?= e(__('ged.field.created_at')) ?></dt>
            <dd><?= e($dateTimeLabel(isset($document['created_at']) ? (string) $document['created_at'] : null)) ?></dd>
        </div>
        <div>
            <dt class="detail-label"><?= e(__('ged.field.updated_at')) ?></dt>
            <dd><?= e($dateTimeLabel(isset($document['updated_at']) ? (string) $document['updated_at'] : null)) ?></dd>
        </div>
        <div>
            <dt class="detail-label"><?= e(__('ged.field.last_upload')) ?></dt>
            <dd><?= e($dateTimeLabel(isset($document['last_uploaded_at']) ? (string) $document['last_uploaded_at'] : null)) ?></dd>
        </div>
    </dl>

    <?php if ($tags !== []): ?>
        <p class="cluster">
            <?php foreach ($tags as $tag): ?>
                <span class="badge badge--info"><?= e($tag) ?></span>
            <?php endforeach; ?>
        </p>
    <?php endif; ?>

    <?php if (trim((string) ($document['description'] ?? '')) !== ''): ?>
        <p class="text-callout"><?= e((string) $document['description']) ?></p>
    <?php endif; ?>

    <?php if (!empty($canManage)): ?>
        <form method="post" action="<?= e(url('/ged/' . $documentId . '/archiver')) ?>">
            <?= csrf_field() ?>
            <input type="hidden" name="archived" value="<?= $archived ? '0' : '1' ?>">
            <button class="button button--secondary" type="submit">
                <?= e($archived ? __('ged.unarchive_submit') : __('ged.archive_submit')) ?>
            </button>
        </form>
    <?php endif; ?>
</section>

<section class="card stack-md" id="ged-versions" aria-labelledby="ged-versions-title">
    <div class="card-header">
        <div>
            <h2 class="card-header__title" id="ged-versions-title"><?= e(__('ged.versions_title')) ?></h2>
            <p class="section-heading__note"><?= e(__('ged.versions_help')) ?></p>
        </div>
        <span class="badge badge--neutral"><?= e(__('ged.versions_count', ['count' => (string) $versionCount])) ?></span>
    </div>

    <?php if ($versions === []): ?>
        <?php View::partial('partials/empty-state', [
            'title' => __('ged.versions_empty_title'),
            'description' => __('ged.versions_empty_help'),
        ]) ?>
    <?php else: ?>
        <div class="table-wrap">
            <table class="data-table">
                <thead>
                    <tr>
                        <th scope="col"><?= e(__('ged.version')) ?></th>
                        <th scope="col"><?= e(__('ged.field.file')) ?></th>
                        <th scope="col"><?= e(__('ged.field.size')) ?></th>
                        <th scope="col"><?= e(__('ged.field.checksum')) ?></th>
                        <th scope="col"><?= e(__('ged.field.uploaded_by')) ?></th>
                        <th scope="col"><?= e(__('common.actions')) ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($versions as $version): ?>
                        <tr>
                            <td>
                                <strong><?= e((string) $version['version_label']) ?></strong>
                                <span class="table-meta"><?= e($dateTimeLabel((string) $version['created_at'])) ?></span>
                            </td>
                            <td>
                                <?= e((string) $version['original_name']) ?>
                                <?php if (trim((string) ($version['note'] ?? '')) !== ''): ?>
                                    <span class="table-meta"><?= e((string) $version['note']) ?></span>
                                <?php endif; ?>
                            </td>
                            <td class="u-numeric"><?= e((string) $version['size_label']) ?></td>
                            <td>
                                <code class="table-meta" title="<?= e((string) $version['checksum']) ?>"><?= e((string) $version['checksum_short']) ?>…</code>
                            </td>
                            <td><?= e($orDash((string) ($version['author_name'] ?? ''))) ?></td>
                            <td>
                                <div class="cluster">
                                    <a class="button button--small button--secondary"
                                       href="<?= e(url('/ged/' . $documentId . '/versions/' . (int) $version['id'] . '/telecharger')) ?>"><?= e(__('ged.download')) ?></a>
                                    <?php if (!empty($canManage) && $versionCount > 1): ?>
                                        <form method="post"
                                              action="<?= e(url('/ged/' . $documentId . '/versions/' . (int) $version['id'] . '/supprimer')) ?>"
                                              data-confirm="<?= e(__('ged.confirm_delete_version')) ?>">
                                            <?= csrf_field() ?>
                                            <button class="button button--small button--ghost" type="submit"><?= e(__('common.delete')) ?></button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <p class="table-meta"><?= e(__('ged.versions_note')) ?></p>
    <?php endif; ?>

    <?php if (!empty($canManage)): ?>
        <details class="stack-md" <?= $versionErrors !== [] ? 'open' : '' ?>>
            <summary class="text-callout"><?= e(__('ged.version_add')) ?></summary>
            <?php if ($versionErrors !== []): ?>
                <p class="form-feedback form-feedback--error" role="alert"><?= e(__('ged.fix_errors')) ?></p>
            <?php endif; ?>
            <form class="form-grid" method="post" action="<?= e(url('/ged/' . $documentId . '/versions')) ?>" enctype="multipart/form-data">
                <?= csrf_field() ?>
                <div class="field form-grid__full">
                    <label class="field__label" for="ged-version-file"><?= e(__('ged.field.file')) ?></label>
                    <input class="field__control" type="file" id="ged-version-file" name="file" required>
                    <span class="field__hint"><?= e(__('ged.upload_help', ['max' => (string) ($maxSize ?? '')])) ?></span>
                    <?php if ($errorHint($versionErrors, 'file') !== ''): ?><span class="field__hint field__hint--error"><?= e($errorHint($versionErrors, 'file')) ?></span><?php endif; ?>
                </div>
                <div class="field form-grid__full">
                    <label class="field__label" for="ged-version-note"><?= e(__('ged.field.note')) ?></label>
                    <input class="field__control" type="text" id="ged-version-note" name="note" maxlength="500"
                           value="<?= e((string) ($versionValues['note'] ?? '')) ?>" placeholder="<?= e(__('ged.note_placeholder')) ?>">
                    <span class="field__hint"><?= e(__('ged.note_help')) ?></span>
                    <?php if ($errorHint($versionErrors, 'note') !== ''): ?><span class="field__hint field__hint--error"><?= e($errorHint($versionErrors, 'note')) ?></span><?php endif; ?>
                </div>
                <div class="form-actions form-grid__full">
                    <button class="button button--primary" type="submit"><?= e(__('ged.version_add_submit')) ?></button>
                </div>
            </form>
        </details>
    <?php endif; ?>
</section>

<?php if (!empty($canManage)): ?>
    <section class="card stack-md" aria-labelledby="ged-edit-title">
        <div class="card-header">
            <div>
                <h2 class="card-header__title" id="ged-edit-title"><?= e(__('ged.edit_title')) ?></h2>
                <p class="section-heading__note"><?= e(__('ged.edit_help')) ?></p>
            </div>
        </div>
        <details class="stack-md" <?= $documentErrors !== [] ? 'open' : '' ?>>
            <summary class="text-callout"><?= e(__('ged.edit_toggle')) ?></summary>
            <?php if ($documentErrors !== []): ?>
                <p class="form-feedback form-feedback--error" role="alert"><?= e(__('ged.fix_errors')) ?></p>
            <?php endif; ?>
            <form class="form-grid" method="post" action="<?= e(url('/ged/' . $documentId . '/modifier')) ?>">
                <?= csrf_field() ?>
                <div class="field form-grid__full">
                    <label class="field__label" for="ged-edit-title-field"><?= e(__('ged.field.title')) ?></label>
                    <input class="field__control" type="text" id="ged-edit-title-field" name="title" maxlength="180" required
                           value="<?= e((string) ($values['title'] ?? '')) ?>">
                    <?php if ($errorHint($documentErrors, 'title') !== ''): ?><span class="field__hint field__hint--error"><?= e($errorHint($documentErrors, 'title')) ?></span><?php endif; ?>
                </div>
                <div class="field">
                    <label class="field__label" for="ged-edit-category"><?= e(__('ged.field.category')) ?></label>
                    <select class="field__control" id="ged-edit-category" name="category">
                        <?php foreach ($categories as $category): ?>
                            <option value="<?= e($category) ?>" <?= (string) ($values['category'] ?? 'other') === $category ? 'selected' : '' ?>><?= e(__('ged.category.' . $category)) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="field">
                    <label class="field__label" for="ged-edit-client"><?= e(__('ged.field.client')) ?></label>
                    <select class="field__control" id="ged-edit-client" name="client_id">
                        <option value=""><?= e(__('ged.no_client')) ?></option>
                        <?php foreach ($clients as $client): ?>
                            <option value="<?= (int) $client['id'] ?>" <?= (string) ($values['client_id'] ?? '') === (string) (int) $client['id'] ? 'selected' : '' ?>><?= e((string) $client['label']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <?php if ($errorHint($documentErrors, 'client_id') !== ''): ?><span class="field__hint field__hint--error"><?= e($errorHint($documentErrors, 'client_id')) ?></span><?php endif; ?>
                </div>
                <div class="field">
                    <label class="field__label" for="ged-edit-project"><?= e(__('ged.field.project')) ?></label>
                    <select class="field__control" id="ged-edit-project" name="project_id">
                        <option value=""><?= e(__('ged.no_project')) ?></option>
                        <?php foreach ($projects as $project): ?>
                            <option value="<?= (int) $project['id'] ?>" <?= (string) ($values['project_id'] ?? '') === (string) (int) $project['id'] ? 'selected' : '' ?>><?= e((string) $project['label']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <span class="field__hint"><?= e(__('ged.project_help')) ?></span>
                    <?php if ($errorHint($documentErrors, 'project_id') !== ''): ?><span class="field__hint field__hint--error"><?= e($errorHint($documentErrors, 'project_id')) ?></span><?php endif; ?>
                </div>
                <div class="field">
                    <label class="field__label" for="ged-edit-tags"><?= e(__('ged.field.tags')) ?></label>
                    <input class="field__control" type="text" id="ged-edit-tags" name="tags" maxlength="255"
                           value="<?= e((string) ($values['tags'] ?? '')) ?>">
                    <span class="field__hint"><?= e(__('ged.tags_help')) ?></span>
                </div>
                <div class="field">
                    <label class="field__label" for="ged-edit-retention"><?= e(__('ged.field.retention')) ?></label>
                    <input class="field__control" type="date" id="ged-edit-retention" name="retention_until"
                           value="<?= e((string) ($values['retention_until'] ?? '')) ?>">
                    <span class="field__hint"><?= e(__('ged.retention_help')) ?></span>
                    <?php if ($errorHint($documentErrors, 'retention_until') !== ''): ?><span class="field__hint field__hint--error"><?= e($errorHint($documentErrors, 'retention_until')) ?></span><?php endif; ?>
                </div>
                <div class="field form-grid__full">
                    <label class="field__label" for="ged-edit-description"><?= e(__('ged.field.description')) ?></label>
                    <input class="field__control" type="text" id="ged-edit-description" name="description" maxlength="1000"
                           value="<?= e((string) ($values['description'] ?? '')) ?>">
                    <?php if ($errorHint($documentErrors, 'description') !== ''): ?><span class="field__hint field__hint--error"><?= e($errorHint($documentErrors, 'description')) ?></span><?php endif; ?>
                </div>
                <div class="form-actions form-grid__full">
                    <button class="button button--primary" type="submit"><?= e(__('ged.edit_submit')) ?></button>
                </div>
            </form>
        </details>
    </section>

    <section class="card stack-md" aria-labelledby="ged-danger-title">
        <div class="card-header">
            <div>
                <h2 class="card-header__title" id="ged-danger-title"><?= e(__('ged.danger_title')) ?></h2>
                <p class="section-heading__note"><?= e(__('ged.danger_help')) ?></p>
            </div>
        </div>
        <form method="post" action="<?= e(url('/ged/' . $documentId . '/supprimer')) ?>"
              data-confirm="<?= e(__('ged.confirm_delete_document')) ?>">
            <?= csrf_field() ?>
            <button class="button button--ghost" type="submit"><?= e(__('ged.delete_document')) ?></button>
        </form>
    </section>
<?php endif; ?>
