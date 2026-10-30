<?php
declare(strict_types=1);

use App\Core\Auth;
use App\Core\View;
use Modules\GedVersions\Services\GedService;

$documents = is_array($documents ?? null) ? $documents : [];
$statistics = is_array($statistics ?? null) ? $statistics : [];
$categories = is_array($categories ?? null) ? $categories : GedService::CATEGORIES;
$clients = is_array($clients ?? null) ? $clients : [];
$projects = is_array($projects ?? null) ? $projects : [];
$filters = is_array($filters ?? null) ? $filters : [];
$documentErrors = is_array($documentErrors ?? null) ? $documentErrors : [];
$documentValues = is_array($documentValues ?? null) ? $documentValues : [];

$dateLabel = static function (?string $value): string {
    $timestamp = $value !== null && $value !== '' ? strtotime($value) : false;

    return $timestamp === false ? '—' : date('d/m/Y', $timestamp);
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
$retentionLabel = static function (array $row): string {
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
$values = $documentValues;
$byCategory = is_array($statistics['by_category'] ?? null) ? $statistics['by_category'] : [];
$exportQuery = array_filter([
    'q' => (string) ($filters['search'] ?? ''),
    'category' => (string) ($filters['category'] ?? ''),
    'client' => (string) ($filters['client_id'] ?? ''),
    'projet' => (string) ($filters['project_id'] ?? ''),
    'archive' => (string) ($filters['archive'] ?? ''),
], static fn (string $value): bool => $value !== '');
?>
<?php View::partial('partials/page-header', [
    'pageTitle' => __('ged.title'),
    'eyebrow' => __('ged.eyebrow'),
    'subtitle' => __('ged.intro'),
    'secondaryAction' => [
        'label' => __('ged.export'),
        'icon' => 'download',
        'url' => url('/ged/export' . ($exportQuery !== [] ? '?' . http_build_query($exportQuery) : '')),
    ],
]) ?>

<?php if (!empty($notice)): ?><p class="form-feedback form-feedback--success" role="status"><?= e($notice) ?></p><?php endif; ?>
<?php if (!empty($error)): ?><p class="form-feedback form-feedback--error" role="alert"><?= e($error) ?></p><?php endif; ?>

<p class="form-feedback form-feedback--info" role="note"><?= e(__('ged.guardrail')) ?></p>

<section class="stats-grid" aria-label="<?= e(__('ged.statistics')) ?>">
    <?php View::partial('partials/stat-card', [
        'label' => __('ged.stat.documents'),
        'value' => (string) ($statistics['documents'] ?? 0),
        'icon' => 'folder',
        'tone' => 'blue',
        'note' => __('ged.stat.archived_note', ['count' => (string) ($statistics['archived'] ?? 0)]),
    ]) ?>
    <?php View::partial('partials/stat-card', [
        'label' => __('ged.stat.versions'),
        'value' => (string) ($statistics['versions'] ?? 0),
        'icon' => 'file-text',
        'tone' => 'mint',
        'note' => __('ged.stat.versions_note'),
    ]) ?>
    <?php View::partial('partials/stat-card', [
        'label' => __('ged.stat.volume'),
        'value' => GedService::humanSize((int) ($statistics['total_bytes'] ?? 0)),
        'icon' => 'chart',
        'tone' => 'pink',
        'note' => __('ged.stat.volume_note'),
    ]) ?>
    <?php View::partial('partials/stat-card', [
        'label' => __('ged.stat.retention'),
        'value' => (string) ($statistics['retention_soon'] ?? 0),
        'icon' => 'clock',
        'tone' => 'coral',
        'note' => __('ged.stat.retention_note', ['count' => (string) ($statistics['retention_past'] ?? 0)]),
    ]) ?>
</section>

<section class="card stack-md" aria-labelledby="ged-filter-title">
    <div class="card-header">
        <div>
            <h2 class="card-header__title" id="ged-filter-title"><?= e(__('ged.filter_title')) ?></h2>
            <p class="section-heading__note"><?= e(__('ged.filter_help')) ?></p>
        </div>
    </div>
    <form class="filter-row" method="get" action="<?= e(url('/ged')) ?>">
        <div class="field field--search">
            <label class="field__label" for="ged-filter-search"><?= e(__('ged.search')) ?></label>
            <input class="field__control" type="search" id="ged-filter-search" name="q" maxlength="120"
                   value="<?= e((string) ($filters['search'] ?? '')) ?>" placeholder="<?= e(__('ged.search_placeholder')) ?>">
        </div>
        <div class="field">
            <label class="field__label" for="ged-filter-category"><?= e(__('ged.field.category')) ?></label>
            <select class="field__control" id="ged-filter-category" name="category">
                <option value=""><?= e(__('ged.all_categories')) ?></option>
                <?php foreach ($categories as $category): ?>
                    <option value="<?= e($category) ?>" <?= (string) ($filters['category'] ?? '') === $category ? 'selected' : '' ?>><?= e(__('ged.category.' . $category)) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="field">
            <label class="field__label" for="ged-filter-client"><?= e(__('ged.field.client')) ?></label>
            <select class="field__control" id="ged-filter-client" name="client">
                <option value=""><?= e(__('ged.all_clients')) ?></option>
                <?php foreach ($clients as $client): ?>
                    <option value="<?= (int) $client['id'] ?>" <?= (string) ($filters['client_id'] ?? '') === (string) (int) $client['id'] ? 'selected' : '' ?>><?= e((string) $client['label']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="field">
            <label class="field__label" for="ged-filter-project"><?= e(__('ged.field.project')) ?></label>
            <select class="field__control" id="ged-filter-project" name="projet">
                <option value=""><?= e(__('ged.all_projects')) ?></option>
                <?php foreach ($projects as $project): ?>
                    <option value="<?= (int) $project['id'] ?>" <?= (string) ($filters['project_id'] ?? '') === (string) (int) $project['id'] ? 'selected' : '' ?>><?= e((string) $project['label']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="field">
            <label class="field__label" for="ged-filter-archive"><?= e(__('ged.field.archived')) ?></label>
            <select class="field__control" id="ged-filter-archive" name="archive">
                <option value="active" <?= (string) ($filters['archive'] ?? 'active') === 'active' ? 'selected' : '' ?>><?= e(__('ged.archive.active_only')) ?></option>
                <option value="archived" <?= (string) ($filters['archive'] ?? '') === 'archived' ? 'selected' : '' ?>><?= e(__('ged.archive.archived_only')) ?></option>
                <option value="all" <?= (string) ($filters['archive'] ?? '') === 'all' ? 'selected' : '' ?>><?= e(__('ged.archive.all')) ?></option>
            </select>
        </div>
        <button class="button button--secondary" type="submit">
            <svg class="icon icon--small" aria-hidden="true"><use href="<?= e(asset('img/icons.svg')) ?>#filter"></use></svg><?= e(__('common.filter')) ?>
        </button>
        <a class="button button--ghost" href="<?= e(url('/ged')) ?>"><?= e(__('common.clear')) ?></a>
    </form>
</section>

<section class="card stack-md" aria-labelledby="ged-list-title">
    <div class="card-header">
        <div>
            <h2 class="card-header__title" id="ged-list-title"><?= e(__('ged.list_title')) ?></h2>
            <p class="section-heading__note"><?= e(__('ged.list_help')) ?></p>
        </div>
        <span class="badge badge--neutral"><?= e(__('common.results', ['count' => (string) count($documents)])) ?></span>
    </div>
    <?php if ($documents === []): ?>
        <?php View::partial('partials/empty-state', [
            'title' => __('ged.empty_title'),
            'description' => __('ged.empty_help'),
        ]) ?>
    <?php else: ?>
        <div class="table-wrap">
            <table class="data-table">
                <thead>
                    <tr>
                        <th scope="col"><?= e(__('ged.document')) ?></th>
                        <th scope="col"><?= e(__('ged.field.category')) ?></th>
                        <th scope="col"><?= e(__('ged.field.client')) ?></th>
                        <th scope="col"><?= e(__('ged.versions')) ?></th>
                        <th scope="col"><?= e(__('ged.total_size')) ?></th>
                        <th scope="col"><?= e(__('ged.field.retention')) ?></th>
                        <th scope="col"><?= e(__('common.actions')) ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($documents as $row): ?>
                        <tr>
                            <td>
                                <strong><?= e((string) $row['title']) ?></strong>
                                <span class="table-meta"><?= e($orDash((string) ($row['reference'] ?? ''))) ?></span>
                                <?php $tags = GedService::tagList(isset($row['tags']) ? (string) $row['tags'] : null); ?>
                                <?php if ($tags !== []): ?>
                                    <span class="table-meta"><?= e(implode(' · ', $tags)) ?></span>
                                <?php endif; ?>
                            </td>
                            <td><span class="badge badge--neutral"><?= e(__('ged.category.' . (string) $row['category'])) ?></span></td>
                            <td>
                                <?php if (($row['client_id'] ?? null) !== null): ?>
                                    <?php if (Auth::can('clients.view')): ?>
                                        <a href="<?= e(url('/clients/' . (int) $row['client_id'])) ?>"><?= e((string) ($row['client_name'] ?? '')) ?></a>
                                    <?php else: ?>
                                        <?= e((string) ($row['client_name'] ?? '')) ?>
                                    <?php endif; ?>
                                    <span class="table-meta"><?= e($orDash((string) ($row['project_name'] ?? ''))) ?></span>
                                <?php else: ?>
                                    <span class="muted"><?= e(__('ged.not_linked')) ?></span>
                                <?php endif; ?>
                            </td>
                            <td class="u-numeric">
                                <?= (int) $row['version_count'] ?>
                                <span class="table-meta"><?= e((string) $row['latest_version']) ?></span>
                            </td>
                            <td class="u-numeric"><?= e((string) $row['size_label']) ?></td>
                            <td>
                                <?php if (($row['retention_until'] ?? null) !== null): ?>
                                    <span class="badge <?= e($retentionBadge((string) $row['retention_state'])) ?>"><?= e($retentionLabel($row)) ?></span>
                                    <span class="table-meta"><?= e($dateLabel((string) $row['retention_until'])) ?></span>
                                <?php else: ?>
                                    <span class="muted"><?= e(__('ged.retention.none')) ?></span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="cluster">
                                    <a class="button button--small button--secondary" href="<?= e(url('/ged/' . (int) $row['id'])) ?>"><?= e(__('ged.open')) ?></a>
                                    <?php if (!empty($row['archived_at'])): ?>
                                        <span class="badge badge--warning"><?= e(__('ged.archived')) ?></span>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <p class="table-meta"><?= e(__('ged.list_note')) ?></p>
    <?php endif; ?>
</section>

<section class="card stack-md" aria-labelledby="ged-categories-title">
    <div class="card-header">
        <div>
            <h2 class="card-header__title" id="ged-categories-title"><?= e(__('ged.categories_title')) ?></h2>
            <p class="section-heading__note"><?= e(__('ged.categories_help')) ?></p>
        </div>
    </div>
    <div class="table-wrap">
        <table class="data-table">
            <thead>
                <tr>
                    <th scope="col"><?= e(__('ged.field.category')) ?></th>
                    <th scope="col"><?= e(__('ged.count')) ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($categories as $category): ?>
                    <tr>
                        <td><span class="badge badge--neutral"><?= e(__('ged.category.' . $category)) ?></span></td>
                        <td class="u-numeric"><?= (int) ($byCategory[$category] ?? 0) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
            <tfoot>
                <tr>
                    <th scope="row"><?= e(__('ged.totals')) ?></th>
                    <td class="u-numeric"><strong><?= (int) ($statistics['documents'] ?? 0) ?></strong></td>
                </tr>
            </tfoot>
        </table>
    </div>
</section>

<?php if (!empty($canManage)): ?>
    <section class="card stack-md" aria-labelledby="ged-new-title">
        <div class="card-header">
            <div>
                <h2 class="card-header__title" id="ged-new-title"><?= e(__('ged.add_title')) ?></h2>
                <p class="section-heading__note"><?= e(__('ged.add_help')) ?></p>
            </div>
        </div>

        <details class="stack-md" <?= $documentErrors !== [] ? 'open' : '' ?>>
            <summary class="text-callout"><?= e(__('ged.new_document')) ?></summary>
            <?php if ($documentErrors !== []): ?>
                <p class="form-feedback form-feedback--error" role="alert"><?= e(__('ged.fix_errors')) ?></p>
            <?php endif; ?>
            <form class="form-grid" method="post" action="<?= e(url('/ged')) ?>" enctype="multipart/form-data">
                <?= csrf_field() ?>
                <div class="field form-grid__full">
                    <label class="field__label" for="ged-new-title-field"><?= e(__('ged.field.title')) ?></label>
                    <input class="field__control" type="text" id="ged-new-title-field" name="title" maxlength="180" required
                           value="<?= e((string) ($values['title'] ?? '')) ?>" placeholder="<?= e(__('ged.title_placeholder')) ?>">
                    <?php if ($errorHint($documentErrors, 'title') !== ''): ?><span class="field__hint field__hint--error"><?= e($errorHint($documentErrors, 'title')) ?></span><?php endif; ?>
                </div>
                <div class="field">
                    <label class="field__label" for="ged-new-category"><?= e(__('ged.field.category')) ?></label>
                    <select class="field__control" id="ged-new-category" name="category">
                        <?php foreach ($categories as $category): ?>
                            <option value="<?= e($category) ?>" <?= (string) ($values['category'] ?? 'other') === $category ? 'selected' : '' ?>><?= e(__('ged.category.' . $category)) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="field">
                    <label class="field__label" for="ged-new-client"><?= e(__('ged.field.client')) ?></label>
                    <select class="field__control" id="ged-new-client" name="client_id">
                        <option value=""><?= e(__('ged.no_client')) ?></option>
                        <?php foreach ($clients as $client): ?>
                            <option value="<?= (int) $client['id'] ?>" <?= (string) ($values['client_id'] ?? '') === (string) (int) $client['id'] ? 'selected' : '' ?>><?= e((string) $client['label']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <?php if ($errorHint($documentErrors, 'client_id') !== ''): ?><span class="field__hint field__hint--error"><?= e($errorHint($documentErrors, 'client_id')) ?></span><?php endif; ?>
                </div>
                <div class="field">
                    <label class="field__label" for="ged-new-project"><?= e(__('ged.field.project')) ?></label>
                    <select class="field__control" id="ged-new-project" name="project_id">
                        <option value=""><?= e(__('ged.no_project')) ?></option>
                        <?php foreach ($projects as $project): ?>
                            <option value="<?= (int) $project['id'] ?>" <?= (string) ($values['project_id'] ?? '') === (string) (int) $project['id'] ? 'selected' : '' ?>><?= e((string) $project['label']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <span class="field__hint"><?= e(__('ged.project_help')) ?></span>
                    <?php if ($errorHint($documentErrors, 'project_id') !== ''): ?><span class="field__hint field__hint--error"><?= e($errorHint($documentErrors, 'project_id')) ?></span><?php endif; ?>
                </div>
                <div class="field">
                    <label class="field__label" for="ged-new-tags"><?= e(__('ged.field.tags')) ?></label>
                    <input class="field__control" type="text" id="ged-new-tags" name="tags" maxlength="255"
                           value="<?= e((string) ($values['tags'] ?? '')) ?>" placeholder="<?= e(__('ged.tags_placeholder')) ?>">
                    <span class="field__hint"><?= e(__('ged.tags_help')) ?></span>
                </div>
                <div class="field">
                    <label class="field__label" for="ged-new-retention"><?= e(__('ged.field.retention')) ?></label>
                    <input class="field__control" type="date" id="ged-new-retention" name="retention_until"
                           value="<?= e((string) ($values['retention_until'] ?? '')) ?>">
                    <span class="field__hint"><?= e(__('ged.retention_help')) ?></span>
                    <?php if ($errorHint($documentErrors, 'retention_until') !== ''): ?><span class="field__hint field__hint--error"><?= e($errorHint($documentErrors, 'retention_until')) ?></span><?php endif; ?>
                </div>
                <div class="field form-grid__full">
                    <label class="field__label" for="ged-new-file"><?= e(__('ged.field.file')) ?></label>
                    <input class="field__control" type="file" id="ged-new-file" name="file">
                    <span class="field__hint"><?= e(__('ged.upload_help', ['max' => (string) ($maxSize ?? '')])) ?></span>
                    <?php if ($errorHint($documentErrors, 'file') !== ''): ?><span class="field__hint field__hint--error"><?= e($errorHint($documentErrors, 'file')) ?></span><?php endif; ?>
                </div>
                <div class="field form-grid__full">
                    <label class="field__label" for="ged-new-description"><?= e(__('ged.field.description')) ?></label>
                    <input class="field__control" type="text" id="ged-new-description" name="description" maxlength="1000"
                           value="<?= e((string) ($values['description'] ?? '')) ?>">
                    <?php if ($errorHint($documentErrors, 'description') !== ''): ?><span class="field__hint field__hint--error"><?= e($errorHint($documentErrors, 'description')) ?></span><?php endif; ?>
                </div>
                <div class="form-actions form-grid__full">
                    <button class="button button--primary" type="submit"><?= e(__('ged.add_submit')) ?></button>
                </div>
            </form>
        </details>
    </section>
<?php endif; ?>
