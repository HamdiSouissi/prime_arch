<?php
declare(strict_types=1);

use App\Core\View;
use Modules\PortailClient\Services\PortailService;

$links = is_array($links ?? null) ? $links : [];
$statistics = is_array($statistics ?? null) ? $statistics : [];
$clients = is_array($clients ?? null) ? $clients : [];
$projects = is_array($projects ?? null) ? $projects : [];
$accesses = is_array($accesses ?? null) ? $accesses : [];
$filters = is_array($filters ?? null) ? $filters : [];
$errors = is_array($errors ?? null) ? $errors : [];
$values = is_array($values ?? null) ? $values : [];
$selected = is_array($selected ?? null) ? $selected : null;
$today = (string) ($today ?? date('Y-m-d'));
$defaultDays = (int) ($defaultDays ?? PortailService::DAYS_DEFAULT);
$maxDays = (int) ($maxDays ?? PortailService::DAYS_MAX);

$dateLabel = static function (mixed $value): string {
    $text = is_string($value) ? trim($value) : '';
    $timestamp = $text !== '' ? strtotime($text) : false;

    return $timestamp === false ? '—' : date('d/m/Y', $timestamp);
};
$dateTimeLabel = static function (mixed $value): string {
    $text = is_string($value) ? trim($value) : '';
    $timestamp = $text !== '' ? strtotime($text) : false;

    return $timestamp === false ? '—' : date('d/m/Y H:i', $timestamp);
};
$errorHint = static fn (array $list, string $field): string => (string) ($list[$field] ?? '');
$orDash = static fn (string $value): string => trim($value) !== '' ? $value : '—';
$stateBadge = static fn (string $state): string => match ($state) {
    'active' => 'badge--success',
    'expiring' => 'badge--warning',
    'expired' => 'badge--neutral',
    'revoked' => 'badge--error',
    default => 'badge--neutral',
};
$optionLabel = static function (string $reference, string $name): string {
    $reference = trim($reference);
    $name = trim($name);

    return $reference === '' ? $name : ($name === '' ? $reference : $reference . ' — ' . $name);
};
$scopeLabel = static fn (array $link): string => (string) $link['scope'] === 'project'
    ? __('portail.scope_project_value', ['project' => (string) ($link['project_name'] ?? '—')])
    : __('portail.scope_client_value');
$values = $values !== [] ? $values : [
    'label' => '',
    'client_id' => '',
    'scope' => 'project',
    'project_id' => '',
    'expires_days' => (string) $defaultDays,
    'can_view_documents' => '1',
    'can_view_amounts' => '0',
    'can_view_photos' => '1',
];
$selectedState = $selected === null ? '' : PortailService::state($selected, $today);
?>
<link rel="stylesheet" href="<?= e(url('/module-assets/portail-client?file=css/module.css')) ?>">
<?php View::partial('partials/page-header', [
    'pageTitle' => __('portail.title'),
    'eyebrow' => __('portail.eyebrow'),
    'subtitle' => __('portail.intro'),
]) ?>

<?php if (!empty($notice)): ?><p class="form-feedback form-feedback--success" role="status"><?= e($notice) ?></p><?php endif; ?>
<?php if (!empty($error)): ?><p class="form-feedback form-feedback--error" role="alert"><?= e($error) ?></p><?php endif; ?>

<p class="form-feedback form-feedback--info" role="note"><?= e(__('portail.guardrail')) ?></p>

<?php if (!empty($newUrl)): ?>
    <section class="card stack-md" aria-labelledby="portail-new-url-title">
        <div class="card-header">
            <div>
                <h2 class="card-header__title" id="portail-new-url-title"><?= e(__('portail.new_url_title')) ?></h2>
                <p class="section-heading__note"><?= e(__('portail.new_url_help', ['label' => (string) ($newLabel ?? '')])) ?></p>
            </div>
            <span class="badge badge--warning"><?= e(__('portail.new_url_badge')) ?></span>
        </div>
        <p class="form-feedback form-feedback--info" role="note"><?= e(__('portail.new_url_once')) ?></p>
        <div class="cluster">
            <code id="portail-new-url" class="portail-url"><?= e((string) $newUrl) ?></code>
            <button class="button button--secondary button--small" type="button" data-copy="#portail-new-url">
                <?= e(__('portail.copy_link')) ?>
            </button>
        </div>
    </section>
<?php endif; ?>

<section class="stats-grid" aria-label="<?= e(__('portail.statistics')) ?>">
    <?php View::partial('partials/stat-card', [
        'label' => __('portail.stat.active'),
        'value' => (string) ($statistics['active'] ?? 0),
        'icon' => 'lock',
        'tone' => 'blue',
        'note' => __('portail.stat.total_note', ['count' => (string) ($statistics['total'] ?? 0)]),
    ]) ?>
    <?php View::partial('partials/stat-card', [
        'label' => __('portail.stat.expiring'),
        'value' => (string) ($statistics['expiring'] ?? 0),
        'icon' => 'clock',
        'tone' => 'coral',
        'note' => __('portail.stat.expiring_note', ['days' => (string) PortailService::EXPIRING_DAYS]),
    ]) ?>
    <?php View::partial('partials/stat-card', [
        'label' => __('portail.stat.recent_accesses'),
        'value' => (string) ($recentAccesses ?? 0),
        'icon' => 'eye',
        'tone' => 'mint',
        'note' => __('portail.stat.recent_accesses_note'),
    ]) ?>
    <?php View::partial('partials/stat-card', [
        'label' => __('portail.stat.closed'),
        'value' => (string) ((int) ($statistics['expired'] ?? 0) + (int) ($statistics['revoked'] ?? 0)),
        'icon' => 'shield',
        'tone' => 'pink',
        'note' => __('portail.stat.closed_note'),
    ]) ?>
</section>

<section class="card stack-md" aria-labelledby="portail-filter-title">
    <div class="card-header">
        <div>
            <h2 class="card-header__title" id="portail-filter-title"><?= e(__('portail.filter_title')) ?></h2>
            <p class="section-heading__note"><?= e(__('portail.filter_help')) ?></p>
        </div>
    </div>
    <form class="filter-row" method="get" action="<?= e(url('/portail')) ?>">
        <div class="field">
            <label class="field__label" for="portail-filter-state"><?= e(__('portail.state_filter')) ?></label>
            <select class="field__control" id="portail-filter-state" name="state">
                <option value=""><?= e(__('portail.all_states')) ?></option>
                <option value="active" <?= (string) ($filters['state'] ?? '') === 'active' ? 'selected' : '' ?>><?= e(__('portail.state_filter_active')) ?></option>
                <option value="expired" <?= (string) ($filters['state'] ?? '') === 'expired' ? 'selected' : '' ?>><?= e(__('portail.state_filter_expired')) ?></option>
                <option value="revoked" <?= (string) ($filters['state'] ?? '') === 'revoked' ? 'selected' : '' ?>><?= e(__('portail.state_filter_revoked')) ?></option>
            </select>
        </div>
        <div class="field">
            <label class="field__label" for="portail-filter-client"><?= e(__('portail.client')) ?></label>
            <select class="field__control" id="portail-filter-client" name="client">
                <option value=""><?= e(__('portail.all_clients')) ?></option>
                <?php foreach ($clients as $client): ?>
                    <option value="<?= (int) $client['id'] ?>" <?= (string) ($filters['client'] ?? '') === (string) (int) $client['id'] ? 'selected' : '' ?>>
                        <?= e($optionLabel((string) $client['reference'], (string) $client['display_name'])) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <button class="button button--secondary" type="submit">
            <svg class="icon icon--small" aria-hidden="true"><use href="<?= e(asset('img/icons.svg')) ?>#filter"></use></svg><?= e(__('common.filter')) ?>
        </button>
        <a class="button button--ghost" href="<?= e(url('/portail')) ?>"><?= e(__('common.clear')) ?></a>
    </form>
</section>

<section class="card stack-md" aria-labelledby="portail-links-title">
    <div class="card-header">
        <div>
            <h2 class="card-header__title" id="portail-links-title"><?= e(__('portail.links_title')) ?></h2>
            <p class="section-heading__note"><?= e(__('portail.links_help')) ?></p>
        </div>
        <span class="badge badge--neutral"><?= e(__('common.results', ['count' => (string) count($links)])) ?></span>
    </div>

    <?php if ($links === []): ?>
        <?php View::partial('partials/empty-state', [
            'title' => __('portail.empty_title'),
            'description' => __('portail.empty_help'),
        ]) ?>
    <?php else: ?>
        <div class="table-wrap">
            <table class="data-table">
                <thead>
                    <tr>
                        <th scope="col"><?= e(__('portail.link_label')) ?></th>
                        <th scope="col"><?= e(__('portail.client')) ?></th>
                        <th scope="col"><?= e(__('portail.scope')) ?></th>
                        <th scope="col"><?= e(__('portail.permissions')) ?></th>
                        <th scope="col"><?= e(__('portail.expires_on')) ?></th>
                        <th scope="col"><?= e(__('portail.consultations')) ?></th>
                        <th scope="col"><?= e(__('common.actions')) ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($links as $link): ?>
                        <?php $state = (string) ($link['state'] ?? PortailService::state($link, $today)); ?>
                        <tr>
                            <td>
                                <strong><?= e((string) $link['label']) ?></strong>
                                <span class="table-meta"><?= e(__('portail.created_on', ['date' => $dateLabel($link['created_at'] ?? null)])) ?></span>
                            </td>
                            <td>
                                <?= e((string) $link['client_name']) ?>
                                <span class="table-meta"><?= e((string) ($link['client_reference'] ?? '')) ?></span>
                            </td>
                            <td><?= e($scopeLabel($link)) ?></td>
                            <td>
                                <span class="table-meta">
                                    <?= (bool) $link['can_view_documents'] ? e(__('portail.permission.documents')) : e(__('portail.permission.none')) ?>
                                    <?php if ((bool) $link['can_view_amounts']): ?> · <?= e(__('portail.permission.amounts')) ?><?php endif; ?>
                                    <?php if ((bool) $link['can_view_photos']): ?> · <?= e(__('portail.permission.photos')) ?><?php endif; ?>
                                </span>
                            </td>
                            <td>
                                <?= e($dateLabel($link['expires_on'] ?? null)) ?>
                                <span class="badge <?= e($stateBadge($state)) ?>"><?= e(__('portail.state.' . $state)) ?></span>
                                <?php if ($state === 'expiring'): ?>
                                    <span class="table-meta"><?= e(__('portail.expiring_soon')) ?></span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?= (int) ($link['access_count'] ?? 0) ?>
                                <span class="table-meta"><?= e(__('portail.last_seen', ['date' => $dateTimeLabel($link['last_seen_at'] ?? null)])) ?></span>
                            </td>
                            <td>
                                <div class="cluster">
                                    <a class="button button--small button--secondary" href="<?= e(url('/portail?jeton=' . (int) $link['id'])) ?>"><?= e(__('portail.journal')) ?></a>
                                    <?php if (!empty($canManage) && $link['revoked_at'] === null): ?>
                                        <form method="post" action="<?= e(url('/portail/liens/' . (int) $link['id'] . '/revoquer')) ?>"
                                              data-confirm="<?= e(__('portail.confirm_revoke')) ?>">
                                            <?= csrf_field() ?>
                                            <button class="button button--small button--ghost" type="submit"><?= e(__('portail.revoke')) ?></button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <p class="table-meta"><?= e(__('portail.links_note')) ?></p>
    <?php endif; ?>
</section>

<?php if ($selected !== null): ?>
    <section class="card stack-md" aria-labelledby="portail-journal-title">
        <div class="card-header">
            <div>
                <h2 class="card-header__title" id="portail-journal-title"><?= e(__('portail.journal_title', ['label' => (string) $selected['label']])) ?></h2>
                <p class="section-heading__note"><?= e(__('portail.journal_help')) ?></p>
            </div>
            <span class="badge <?= e($stateBadge($selectedState)) ?>"><?= e(__('portail.state.' . $selectedState)) ?></span>
        </div>

        <div class="document-summary-grid">
            <div><span class="detail-label"><?= e(__('portail.client')) ?></span><strong><?= e((string) $selected['client_name']) ?></strong><span class="table-meta"><?= e((string) ($selected['client_reference'] ?? '')) ?></span></div>
            <div><span class="detail-label"><?= e(__('portail.scope')) ?></span><strong><?= e($scopeLabel($selected)) ?></strong></div>
            <div><span class="detail-label"><?= e(__('portail.expires_on')) ?></span><strong><?= e($dateLabel($selected['expires_on'] ?? null)) ?></strong></div>
            <div><span class="detail-label"><?= e(__('portail.created_by')) ?></span><strong><?= e($orDash((string) ($selected['author_name'] ?? ''))) ?></strong><span class="table-meta"><?= e($dateTimeLabel($selected['created_at'] ?? null)) ?></span></div>
            <div><span class="detail-label"><?= e(__('portail.consultations')) ?></span><strong><?= (int) ($selected['access_count'] ?? 0) ?></strong><span class="table-meta"><?= e(__('portail.last_seen', ['date' => $dateTimeLabel($selected['last_seen_at'] ?? null)])) ?></span></div>
            <div><span class="detail-label"><?= e(__('portail.permissions')) ?></span><strong><?= e(__('portail.permissions_summary')) ?></strong><span class="table-meta"><?= e(__('portail.permissions_note')) ?></span></div>
        </div>

        <?php if (!empty($canManage) && $selected['revoked_at'] === null): ?>
            <div class="content-grid">
                <form class="form-grid" method="post" action="<?= e(url('/portail/liens/' . (int) $selected['id'] . '/prolonger')) ?>">
                    <?= csrf_field() ?>
                    <div class="field">
                        <label class="field__label" for="portail-extend-days"><?= e(__('portail.extend_days')) ?></label>
                        <input class="field__control" type="number" id="portail-extend-days" name="expires_days"
                               min="<?= PortailService::DAYS_MIN ?>" max="<?= $maxDays ?>" step="1" value="<?= $defaultDays ?>" required>
                        <span class="field__hint"><?= e(__('portail.extend_help', ['max' => (string) $maxDays])) ?></span>
                    </div>
                    <div class="form-actions">
                        <button class="button button--secondary" type="submit"><?= e(__('portail.extend')) ?></button>
                    </div>
                </form>
                <div class="stack-md">
                    <form method="post" action="<?= e(url('/portail/liens/' . (int) $selected['id'] . '/revoquer')) ?>"
                          data-confirm="<?= e(__('portail.confirm_revoke')) ?>">
                        <?= csrf_field() ?>
                        <button class="button button--secondary" type="submit"><?= e(__('portail.revoke')) ?></button>
                    </form>
                    <form method="post" action="<?= e(url('/portail/liens/' . (int) $selected['id'] . '/supprimer')) ?>"
                          data-confirm="<?= e(__('portail.confirm_delete')) ?>">
                        <?= csrf_field() ?>
                        <button class="button button--small button--ghost" type="submit"><?= e(__('portail.delete_link')) ?></button>
                    </form>
                </div>
            </div>
        <?php elseif (!empty($canManage)): ?>
            <form method="post" action="<?= e(url('/portail/liens/' . (int) $selected['id'] . '/supprimer')) ?>"
                  data-confirm="<?= e(__('portail.confirm_delete')) ?>">
                <?= csrf_field() ?>
                <button class="button button--small button--ghost" type="submit"><?= e(__('portail.delete_link')) ?></button>
            </form>
        <?php endif; ?>

        <?php if ($accesses === []): ?>
            <p class="table-meta"><?= e(__('portail.no_accesses')) ?></p>
        <?php else: ?>
            <div class="table-wrap">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th scope="col"><?= e(__('portail.accessed_at')) ?></th>
                            <th scope="col"><?= e(__('portail.page')) ?></th>
                            <th scope="col"><?= e(__('portail.device')) ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($accesses as $access): ?>
                            <tr>
                                <td><?= e($dateTimeLabel($access['accessed_at'] ?? null)) ?></td>
                                <td><?= e(__('portail.page.' . (string) $access['page'])) ?></td>
                                <td class="table-meta"><?= e($orDash((string) ($access['user_agent'] ?? ''))) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <p class="table-meta"><?= e(__('portail.journal_note')) ?></p>
        <?php endif; ?>
    </section>
<?php endif; ?>

<?php if (!empty($canManage)): ?>
    <section class="card stack-md" aria-labelledby="portail-new-title">
        <div class="card-header">
            <div>
                <h2 class="card-header__title" id="portail-new-title"><?= e(__('portail.new_title')) ?></h2>
                <p class="section-heading__note"><?= e(__('portail.new_help')) ?></p>
            </div>
        </div>

        <?php if ($clients === []): ?>
            <p class="table-meta"><?= e(__('portail.no_client_available')) ?></p>
        <?php else: ?>
            <details class="stack-md" id="portail-new" <?= $errors !== [] ? 'open' : '' ?>>
                <summary class="text-callout"><?= e(__('portail.new_summary')) ?></summary>
                <?php if ($errors !== []): ?>
                    <p class="form-feedback form-feedback--error" role="alert"><?= e(__('portail.fix_errors')) ?></p>
                <?php endif; ?>
                <form class="form-grid" method="post" action="<?= e(url('/portail/liens')) ?>">
                    <?= csrf_field() ?>
                    <div class="field">
                        <label class="field__label" for="portail-label"><?= e(__('portail.link_label')) ?></label>
                        <input class="field__control" type="text" id="portail-label" name="label" maxlength="120" required
                               value="<?= e((string) ($values['label'] ?? '')) ?>" placeholder="<?= e(__('portail.label_placeholder')) ?>">
                        <span class="field__hint"><?= e(__('portail.label_help')) ?></span>
                        <?php if ($errorHint($errors, 'label') !== ''): ?><span class="field__hint field__hint--error"><?= e($errorHint($errors, 'label')) ?></span><?php endif; ?>
                    </div>
                    <div class="field">
                        <label class="field__label" for="portail-client"><?= e(__('portail.client')) ?></label>
                        <select class="field__control" id="portail-client" name="client_id" required>
                            <option value=""><?= e(__('common.choose')) ?></option>
                            <?php foreach ($clients as $client): ?>
                                <option value="<?= (int) $client['id'] ?>" <?= (string) ($values['client_id'] ?? '') === (string) (int) $client['id'] ? 'selected' : '' ?>>
                                    <?= e($optionLabel((string) $client['reference'], (string) $client['display_name'])) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <?php if ($errorHint($errors, 'client_id') !== ''): ?><span class="field__hint field__hint--error"><?= e($errorHint($errors, 'client_id')) ?></span><?php endif; ?>
                    </div>
                    <div class="field">
                        <label class="field__label" for="portail-scope"><?= e(__('portail.scope')) ?></label>
                        <select class="field__control" id="portail-scope" name="scope">
                            <option value="project" <?= (string) ($values['scope'] ?? 'project') === 'project' ? 'selected' : '' ?>><?= e(__('portail.scope_project')) ?></option>
                            <option value="client" <?= (string) ($values['scope'] ?? 'project') === 'client' ? 'selected' : '' ?>><?= e(__('portail.scope_client')) ?></option>
                        </select>
                        <span class="field__hint"><?= e(__('portail.scope_help')) ?></span>
                    </div>
                    <div class="field">
                        <label class="field__label" for="portail-project"><?= e(__('portail.project')) ?></label>
                        <select class="field__control" id="portail-project" name="project_id">
                            <option value=""><?= e(__('portail.no_project')) ?></option>
                            <?php foreach ($projects as $project): ?>
                                <option value="<?= (int) $project['id'] ?>" <?= (string) ($values['project_id'] ?? '') === (string) (int) $project['id'] ? 'selected' : '' ?>>
                                    <?= e((string) $project['client_name'] . ' · ' . $optionLabel((string) $project['reference'], (string) $project['name'])) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <span class="field__hint"><?= e(__('portail.project_help')) ?></span>
                        <?php if ($errorHint($errors, 'project_id') !== ''): ?><span class="field__hint field__hint--error"><?= e($errorHint($errors, 'project_id')) ?></span><?php endif; ?>
                    </div>
                    <div class="field">
                        <label class="field__label" for="portail-days"><?= e(__('portail.expires_days')) ?></label>
                        <input class="field__control" type="number" id="portail-days" name="expires_days"
                               min="<?= PortailService::DAYS_MIN ?>" max="<?= $maxDays ?>" step="1"
                               value="<?= e((string) ($values['expires_days'] ?? $defaultDays)) ?>" required>
                        <span class="field__hint"><?= e(__('portail.expires_days_help', ['default' => (string) $defaultDays, 'max' => (string) $maxDays])) ?></span>
                        <?php if ($errorHint($errors, 'expires_days') !== ''): ?><span class="field__hint field__hint--error"><?= e($errorHint($errors, 'expires_days')) ?></span><?php endif; ?>
                    </div>
                    <div class="field form-grid__full">
                        <span class="field__label"><?= e(__('portail.permissions')) ?></span>
                        <label class="cluster">
                            <input type="checkbox" name="can_view_documents" value="1" <?= (string) ($values['can_view_documents'] ?? '1') === '1' ? 'checked' : '' ?>>
                            <span><?= e(__('portail.permission.documents_help')) ?></span>
                        </label>
                        <label class="cluster">
                            <input type="checkbox" name="can_view_amounts" value="1" <?= (string) ($values['can_view_amounts'] ?? '0') === '1' ? 'checked' : '' ?>>
                            <span><?= e(__('portail.permission.amounts_help')) ?></span>
                        </label>
                        <label class="cluster">
                            <input type="checkbox" name="can_view_photos" value="1" <?= (string) ($values['can_view_photos'] ?? '1') === '1' ? 'checked' : '' ?>>
                            <span><?= e(__('portail.permission.photos_help')) ?></span>
                        </label>
                    </div>
                    <div class="form-actions form-grid__full">
                        <button class="button button--primary" type="submit"><?= e(__('portail.create_link')) ?></button>
                    </div>
                </form>
            </details>
        <?php endif; ?>
    </section>
<?php endif; ?>
