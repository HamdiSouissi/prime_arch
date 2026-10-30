<?php
declare(strict_types=1);

use App\Core\View;

$documents = is_array($documents ?? null) ? $documents : [];
$filters = is_array($filters ?? null) ? $filters : [];
$summary = is_array($summary ?? null) ? $summary : [];
$clients = is_array($clients ?? null) ? $clients : [];
$page = max(1, (int) ($page ?? 1));
$pageCount = max(1, (int) ($pageCount ?? 1));
$query = array_filter([
    'q' => $filters['search'] ?? '',
    'type' => $filters['document_type'] ?? '',
    'status' => $filters['status'] ?? '',
    'client_id' => $filters['client_id'] ?? '',
    'project_id' => $filters['project_id'] ?? '',
    'page' => null,
], static fn (mixed $value): bool => $value !== '' && $value !== null);
?>
<?php if (!empty($notice)): ?><p class="form-feedback form-feedback--success" role="status"><?= e($notice) ?></p><?php endif; ?>
<?php View::partial('partials/page-header', [
    'pageTitle' => __('documents.title'),
    'eyebrow' => __('documents.eyebrow'),
    'subtitle' => __('documents.intro'),
    'primaryAction' => !empty($canCreate) ? ['label' => __('documents.new'), 'icon' => 'plus', 'url' => url('/documents/new')] : null,
    'secondaryAction' => !empty($canManageTemplates) ? ['label' => __('documents.templates_title'), 'icon' => 'file-text', 'url' => url('/settings/document-templates')] : null,
]) ?>

<div class="metric-grid metric-grid--four" aria-label="<?= e(__('documents.summary')) ?>">
    <article class="metric-card"><div class="metric-card__header"><span class="metric-card__label"><?= e(__('documents.total_documents')) ?></span><span class="metric-card__icon"><svg class="icon" aria-hidden="true"><use href="<?= e(asset('img/icons.svg')) ?>#file-text"></use></svg></span></div><p class="metric-card__value"><?= e((string) ($summary['document_count'] ?? 0)) ?></p><p class="metric-card__meta"><?= e(__('documents.all_documents')) ?></p></article>
    <article class="metric-card"><div class="metric-card__header"><span class="metric-card__label"><?= e(__('documents.draft_count')) ?></span><span class="metric-card__icon metric-card__icon--warning"><svg class="icon" aria-hidden="true"><use href="<?= e(asset('img/icons.svg')) ?>#clock"></use></svg></span></div><p class="metric-card__value"><?= e((string) ($summary['draft_count'] ?? 0)) ?></p><p class="metric-card__meta"><?= e(__('documents.awaiting_issue')) ?></p></article>
    <article class="metric-card"><div class="metric-card__header"><span class="metric-card__label"><?= e(__('documents.issued_quote_count')) ?></span><span class="metric-card__icon metric-card__icon--success"><svg class="icon" aria-hidden="true"><use href="<?= e(asset('img/icons.svg')) ?>#quote"></use></svg></span></div><p class="metric-card__value"><?= e((string) ($summary['issued_quote_count'] ?? 0)) ?></p><p class="metric-card__meta"><?= e(__('documents.issued_quotes')) ?></p></article>
    <article class="metric-card"><div class="metric-card__header"><span class="metric-card__label"><?= e(__('documents.outstanding_amount')) ?></span><span class="metric-card__icon metric-card__icon--info"><svg class="icon" aria-hidden="true"><use href="<?= e(asset('img/icons.svg')) ?>#wallet"></use></svg></span></div><p class="metric-card__value metric-card__value--currency"><?= e(money_format_tnd((string) ($summary['outstanding_amount'] ?? '0.000'))) ?></p><p class="metric-card__meta"><?= e(__('documents.invoices_balance')) ?></p></article>
</div>

<section class="card stack-md" aria-labelledby="documents-list-title">
    <div class="card-header"><div><h2 class="card-header__title" id="documents-list-title"><?= e(__('documents.list_title')) ?></h2><p class="section-heading__note"><?= e(__('documents.list_intro')) ?></p></div><span class="badge badge--neutral"><?= e((string) ($total ?? 0)) ?> <?= e(__('documents.results')) ?></span></div>
    <form class="filter-bar" method="get" action="<?= e(url('/documents')) ?>">
        <div class="field field--search"><label class="u-sr-only" for="documents-search"><?= e(__('documents.search')) ?></label><input class="field__control" id="documents-search" name="q" type="search" value="<?= e($filters['search'] ?? '') ?>" placeholder="<?= e(__('documents.search_placeholder')) ?>"></div>
        <div class="field"><label class="u-sr-only" for="documents-type"><?= e(__('documents.type_label')) ?></label><select class="field__control" id="documents-type" name="type"><option value=""><?= e(__('documents.all_types')) ?></option><?php foreach (['quote', 'contract', 'invoice', 'credit_note'] as $type): ?><option value="<?= e($type) ?>" <?= ($filters['document_type'] ?? '') === $type ? 'selected' : '' ?>><?= e(__('documents.type.' . $type)) ?></option><?php endforeach; ?></select></div>
        <div class="field"><label class="u-sr-only" for="documents-status"><?= e(__('documents.status_label')) ?></label><select class="field__control" id="documents-status" name="status"><option value=""><?= e(__('documents.all_statuses')) ?></option><?php foreach (['draft', 'issued', 'void'] as $status): ?><option value="<?= e($status) ?>" <?= ($filters['status'] ?? '') === $status ? 'selected' : '' ?>><?= e(__('documents.status.' . $status)) ?></option><?php endforeach; ?></select></div>
        <div class="field"><label class="u-sr-only" for="documents-client"><?= e(__('documents.client')) ?></label><select class="field__control" id="documents-client" name="client_id"><option value=""><?= e(__('documents.all_clients')) ?></option><?php foreach ($clients as $client): ?><option value="<?= e((string) $client['id']) ?>" <?= (string) ($filters['client_id'] ?? '') === (string) $client['id'] ? 'selected' : '' ?>><?= e($client['display_name']) ?></option><?php endforeach; ?></select></div>
        <button class="button button--secondary" type="submit"><?= e(__('common.filter')) ?></button>
        <?php if ($query !== []): ?><a class="button button--ghost" href="<?= e(url('/documents')) ?>"><?= e(__('common.reset')) ?></a><?php endif; ?>
    </form>

    <?php if ($documents === []): ?>
        <div class="empty-state"><span class="empty-state__icon"><svg class="icon" aria-hidden="true"><use href="<?= e(asset('img/icons.svg')) ?>#file-text"></use></svg></span><h3 class="empty-state__title"><?= e(__('documents.empty_title')) ?></h3><p class="empty-state__copy"><?= e(__('documents.empty_body')) ?></p><?php if (!empty($canCreate)): ?><a class="button button--primary" href="<?= e(url('/documents/new')) ?>"><?= e(__('documents.new')) ?></a><?php endif; ?></div>
    <?php else: ?>
        <div class="table-wrap"><table class="data-table"><thead><tr><th><?= e(__('documents.number')) ?></th><th><?= e(__('documents.title_field')) ?></th><th><?= e(__('documents.client')) ?></th><th><?= e(__('documents.project')) ?></th><th><?= e(__('documents.issue_date')) ?></th><th><?= e(__('documents.total')) ?></th><th><?= e(__('documents.status_label')) ?></th><th><span class="u-sr-only"><?= e(__('common.actions')) ?></span></th></tr></thead><tbody>
            <?php foreach ($documents as $item): ?>
                <?php
                $number = trim((string) ($item['document_number'] ?? '')) !== ''
                    ? (string) $item['document_number']
                    : __('documents.draft_number', ['id' => (string) $item['id']]);
                $statusTone = match ((string) $item['status']) {
                    'issued' => 'success',
                    'void' => 'error',
                    default => 'warning',
                };
                ?>
                <tr>
                    <td><a class="table-link" href="<?= e(url('/documents/' . (int) $item['id'])) ?>"><?= e($number) ?></a><span class="table-meta"><?= e(__('documents.type.' . $item['document_type'])) ?></span></td>
                    <td><?= e($item['title']) ?></td>
                    <td><?= e($item['client_name']) ?></td>
                    <td><?= e(($item['project_name'] ?? '') !== '' ? $item['project_name'] : '—') ?></td>
                    <td><?= e($item['issue_date'] ?? '—') ?></td>
                    <td class="u-numeric"><?= e(money_format_tnd((string) $item['total_amount'])) ?></td>
                    <td><span class="badge badge--<?= e($statusTone) ?>"><?= e(__('documents.status.' . $item['status'])) ?></span></td>
                    <td><a class="button button--icon button--ghost" href="<?= e(url('/documents/' . (int) $item['id'])) ?>" aria-label="<?= e(__('documents.open_document')) ?>"><svg class="icon" aria-hidden="true"><use href="<?= e(asset('img/icons.svg')) ?>#arrow-right"></use></svg></a></td>
                </tr>
            <?php endforeach; ?>
        </tbody></table></div>
        <?php if ($pageCount > 1): ?>
            <nav class="pagination" aria-label="<?= e(__('common.pagination')) ?>"><span class="pagination__summary"><?= e(__('common.page_of', ['page' => (string) $page, 'pages' => (string) $pageCount])) ?></span><div class="pagination__actions">
                <?php if ($page > 1): ?><a class="button button--secondary" href="<?= e(url('/documents?' . http_build_query($query + ['page' => $page - 1]))) ?>"><?= e(__('common.previous')) ?></a><?php endif; ?>
                <?php if ($page < $pageCount): ?><a class="button button--secondary" href="<?= e(url('/documents?' . http_build_query($query + ['page' => $page + 1]))) ?>"><?= e(__('common.next')) ?></a><?php endif; ?>
            </div></nav>
        <?php endif; ?>
    <?php endif; ?>
</section>
