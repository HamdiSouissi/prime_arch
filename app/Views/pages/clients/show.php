<?php
declare(strict_types=1);

use App\Core\Translator;
use App\Core\View;

$client = is_array($client ?? null) ? $client : [];
$contacts = is_array($contacts ?? null) ? $contacts : [];
$projects = is_array($projects ?? null) ? $projects : [];
$documents = is_array($documents ?? null) ? $documents : [];
$errors = is_array($errors ?? null) ? $errors : [];
// Champs créés puis désactivés : rappelés dans le bloc des champs personnalisés pour être
// activés, ou complétés par une création rapide, sans quitter la fiche client.
$availableFields = is_array($availableFields ?? null) ? $availableFields : [];
$customFieldNotice = is_string($customFieldNotice ?? null) ? $customFieldNotice : '';
$customFieldErrors = is_array($customFieldErrors ?? null) ? $customFieldErrors : [];
// Suppression : la zone de danger n'existe que pour les profils habilités, et annonce ce qui
// sera retiré avec la fiche (carnet de contacts) ou ce qui bloque l'opération.
$canDelete = !empty($canDelete);
$deleteImpact = is_array($deleteImpact ?? null) ? $deleteImpact : ['projects' => 0, 'documents' => 0, 'contacts' => 0, 'blocked' => false];
$contactValues = is_array($contactValues ?? null) ? $contactValues : [];
$contactErrors = is_array($errors['contact'] ?? null) ? $errors['contact'] : [];
$contactErrorId = (int) ($errors['contact_id'] ?? 0);
$addressParts = array_filter([
    $client['address_line'] ?? '',
    $client['city'] ?? '',
    Translator::locale() === 'ar' ? ($client['delegation_name_ar'] ?? '') : ($client['delegation_name'] ?? ''),
    Translator::locale() === 'ar' ? ($client['governorate_name_ar'] ?? '') : ($client['governorate_name'] ?? ''),
    $client['postal_code'] ?? '',
], static fn (mixed $part): bool => is_string($part) && $part !== '');
?>
<?php if (!empty($notice)): ?><p class="form-feedback form-feedback--success" role="status"><?= e($notice) ?></p><?php endif; ?>
<?php if (!empty($errors['general'])): ?><p class="form-feedback form-feedback--error" role="alert"><?= e($errors['general']) ?></p><?php endif; ?>
<?php $sections = is_array($sections ?? null) ? $sections : []; ?>
<?php View::partial('partials/page-header', [
    'pageTitle' => (string) $client['display_name'],
    'breadcrumb' => is_array($breadcrumb ?? null) ? $breadcrumb : [],
    'eyebrow' => __('clients.record') . ' · ' . (string) ($client['reference'] ?? ''),
    'subtitle' => __('clients.type.' . $client['client_type']) . ' · ' . __('clients.status.' . $client['status']),
    'secondaryAction' => !empty($canManageProjects) ? ['label' => __('projects.create'), 'icon' => 'plus', 'url' => url('/projects/new?client_id=' . (int) $client['id'])] : null,
    'primaryAction' => !empty($canManage) ? ['label' => __('clients.edit'), 'icon' => 'edit', 'url' => url('/clients/' . (int) $client['id'] . '/edit')] : null,
]) ?>

<?php View::partial('partials/context-links', ['links' => $sections]) ?>

<div class="client-record-layout">
    <div class="client-record-main">
        <div class="client-associated-grid">
            <?php if (!empty($canViewProjects)): ?>
            <section class="card client-associated-card client-projects-card" aria-labelledby="client-projects-title">
                <div class="card-header"><div><h2 class="card-header__title" id="client-projects-title"><?= e(__('clients.projects')) ?></h2><p class="section-heading__note"><?= e((string) count($projects)) ?> · <?= e(__('projects.title')) ?></p></div></div>
                <?php if ($projects === []): ?>
                    <?php View::partial('partials/empty-state', ['title' => __('clients.no_projects'), 'description' => __('clients.no_projects_help')]) ?>
                <?php else: ?>
                    <div class="table-wrap"><table class="data-table"><thead><tr><th scope="col"><?= e(__('projects.name')) ?></th><th scope="col"><?= e(__('projects.status.label')) ?></th><th scope="col"><?= e(__('project_phases.current')) ?></th></tr></thead><tbody>
                        <?php foreach ($projects as $project): ?>
                            <tr><td><a class="table-primary" href="<?= e(url('/projects/' . $project['id'])) ?>"><span class="table-primary__text"><span class="table-primary__name"><?= e($project['name']) ?></span><span class="table-primary__meta"><?= e($project['reference']) ?></span></span></a></td><td><span class="badge badge--<?= e(match ($project['status']) { 'active' => 'info', 'completed' => 'success', 'cancelled' => 'neutral', 'on_hold' => 'warning', default => 'neutral' }) ?>"><?= e(__('projects.status.' . $project['status'])) ?></span></td><td><?= e($project['current_phase'] !== '' ? $project['current_phase'] : '—') ?></td></tr>
                        <?php endforeach; ?>
                    </tbody></table></div>
                <?php endif; ?>
            </section>
            <?php endif; ?>

            <?php if (!empty($canViewDocuments)): ?>
                <section class="card client-associated-card client-documents-card" aria-labelledby="client-documents-title">
                    <div class="card-header">
                        <div>
                            <h2 class="card-header__title" id="client-documents-title"><?= e(__('clients.documents')) ?></h2>
                            <p class="section-heading__note"><?= e((string) count($documents)) ?> · <?= e(__('documents.title')) ?></p>
                            </div>
                        </div>
                        <?php if ($documents === []): ?>
                            <?php View::partial('partials/empty-state', [
                                'title' => __('clients.no_documents'),
                                'description' => __('clients.no_documents_help'),
                            ]) ?>
                        <?php else: ?>
                            <div class="table-wrap">
                                <table class="data-table">
                                    <thead>
                                        <tr>
                                            <th scope="col"><?= e(__('documents.number')) ?></th>
                                            <th scope="col"><?= e(__('documents.type_label')) ?></th>
                                            <th scope="col"><?= e(__('documents.status_label')) ?></th>
                                            <th scope="col"><?= e(__('documents.project')) ?></th>
                                            <th scope="col"><?= e(__('clients.document_date')) ?></th>
                                            <th scope="col" class="number"><?= e(__('documents.total')) ?></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($documents as $document): ?>
                                            <?php
                                            $documentNumber = $document['document_number'] !== ''
                                                ? $document['document_number']
                                                : __('documents.draft_number', ['id' => (string) $document['id']]);
                                            $documentDate = $document['issue_date'] !== ''
                                                ? $document['issue_date']
                                                : $document['created_at'];
                                            ?>
                                            <tr>
                                                <td>
                                                    <a class="table-primary" href="<?= e(url('/documents/' . (int) $document['id'])) ?>">
                                                        <span class="table-primary__text">
                                                            <span class="table-primary__name"><?= e($documentNumber) ?></span>
                                                            <span class="table-primary__meta"><?= e($document['title']) ?></span>
                                                        </span>
                                                    </a>
                                                </td>
                                                <td><?= e(__('documents.type.' . $document['document_type'])) ?></td>
                                                <td>
                                                    <span class="badge badge--<?= e(match ($document['status']) {
                                                        'issued' => 'success',
                                                        'void' => 'neutral',
                                                        default => 'warning',
                                                    }) ?>">
                                                        <?= e(__('documents.status.' . $document['status'])) ?>
                                                    </span>
                                                </td>
                                                <td><?= e($document['project_name'] !== '' ? $document['project_name'] : '—') ?></td>
                                                <td><?= e(date('d/m/Y', strtotime($documentDate))) ?></td>
                                                <td class="number"><?= e(money_format_tnd($document['total_amount'])) ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </section>
                <?php endif; ?>

                <?php // Sans droit de gestion, le bloc garde son rôle de lecture : il n'apparaît que s'il a des valeurs. ?>
                <?php if ($customFields !== [] || !empty($canManage)): ?>
                    <section class="card client-associated-card client-custom-fields-card" aria-labelledby="client-custom-title">
                        <div class="card-header"><h2 class="card-header__title" id="client-custom-title"><?= e(__('custom_fields.title')) ?></h2><?php if (!empty($canManage)): ?><a class="card-header__action" href="<?= e(url('/settings/custom-fields')) ?>"><?= e(__('custom_fields.manage_link')) ?></a><?php endif; ?></div>
                        <?php if ($customFields !== []): ?>
                            <dl class="detail-list">
                            <?php foreach ($customFields as $field): ?>
                                <?php $value = $client['custom_fields'][$field['field_key']] ?? null; ?>
                                <?php if ($value !== null && $value !== ''): ?><div class="detail-list__item"><dt><?= e($field['label']) ?></dt><dd><?= e(is_bool($value) ? ($value ? __('common.yes') : __('common.no')) : (is_scalar($value) ? (string) $value : '')) ?></dd></div><?php endif; ?>
                            <?php endforeach; ?>
                            </dl>
                        <?php endif; ?>
                        <?php if (!empty($canManage)): ?>
                            <?php View::partial('partials/custom-field-picker', [
                                'entityType' => 'client',
                                'recordId' => (int) ($client['id'] ?? 0),
                                'availableFields' => $availableFields,
                                'canManage' => true,
                                'notice' => $customFieldNotice,
                                'errors' => $customFieldErrors,
                                'activeCount' => count($customFields),
                            ]) ?>
                        <?php endif; ?>
                    </section>
                <?php endif; ?>
        </div>

        <?php if ($canDelete): ?>
            <section class="card danger-zone" aria-labelledby="client-danger-title">
                <div class="card-header">
                    <div>
                        <h2 class="card-header__title" id="client-danger-title"><?= e(__('records.danger_zone')) ?></h2>
                        <p class="section-heading__note"><?= e(__('clients.delete_help')) ?></p>
                    </div>
                </div>
                <?php if (!empty($deleteImpact['blocked'])): ?>
                    <p class="form-feedback form-feedback--error" role="alert"><?= e(__('clients.delete_blocked', [
                        'projects' => (string) ($deleteImpact['projects'] ?? 0),
                        'documents' => (string) ($deleteImpact['documents'] ?? 0),
                    ])) ?></p>
                <?php endif; ?>
                <form class="danger-zone__form" method="post" action="<?= e(url('/clients/' . (int) ($client['id'] ?? 0) . '/delete')) ?>" data-confirm="<?= e(__('clients.delete_confirm', [
                    'name' => (string) ($client['display_name'] ?? ''),
                    'contacts' => (string) ($deleteImpact['contacts'] ?? 0),
                ])) ?>">
                    <?= csrf_field() ?>
                    <p class="field__hint"><?= e(__('clients.delete_scope', ['contacts' => (string) ($deleteImpact['contacts'] ?? 0)])) ?></p>
                    <button class="button button--danger" type="submit" <?= !empty($deleteImpact['blocked']) ? 'disabled' : '' ?>><?= e(__('clients.delete')) ?></button>
                </form>
            </section>
        <?php endif; ?>
    </div>

    <aside class="client-record-side" aria-label="<?= e(__('clients.coordinates_and_contacts')) ?>">
        <div class="client-overview-grid">
            <section class="card" aria-labelledby="client-contact-title">
                <div class="card-header">
                    <div><h2 class="card-header__title" id="client-contact-title"><?= e(__('clients.contact_details')) ?></h2><p class="section-heading__note"><?= e(__('clients.created_at')) ?> <?= e(date('d/m/Y', strtotime((string) $client['created_at']))) ?></p></div>
                    <span class="badge badge--<?= e(match ($client['status']) { 'active' => 'success', 'archived' => 'neutral', default => 'info' }) ?>"><?= e(__('clients.status.' . $client['status'])) ?></span>
                    </div>
                    <dl class="detail-list">
                        <?php if (!empty($client['legal_name'])): ?><div class="detail-list__item"><dt><?= e(__('clients.legal_name')) ?></dt><dd><?= e($client['legal_name']) ?></dd></div><?php endif; ?>
                        <?php if (!empty($client['tax_id'])): ?><div class="detail-list__item"><dt><?= e(__('settings.tax_id')) ?></dt><dd class="number"><?= e($client['tax_id']) ?></dd></div><?php endif; ?>
                        <div class="detail-list__item"><dt><?= e(__('settings.email')) ?></dt><dd><?php if (!empty($client['email'])): ?><a href="<?= e('mailto:' . $client['email']) ?>"><?= e($client['email']) ?></a><?php else: ?>—<?php endif; ?></dd></div>
                        <div class="detail-list__item"><dt><?= e(__('settings.phone')) ?></dt><dd><?php if (!empty($client['phone'])): ?><a href="<?= e('tel:' . preg_replace('/\s+/', '', (string) $client['phone'])) ?>"><?= e($client['phone']) ?></a><?php else: ?>—<?php endif; ?></dd></div>
                        <div class="detail-list__item"><dt><?= e(__('address.title')) ?></dt><dd><?= e($addressParts !== [] ? implode(', ', $addressParts) : '—') ?></dd></div>
                        <div class="detail-list__item"><dt><?= e(__('clients.notes')) ?></dt><dd><?= nl2br(e($client['notes'] ?? '—')) ?></dd></div>
                    </dl>
                </section>

                <section class="card" aria-labelledby="client-contacts-title">
                    <div class="card-header"><div><h2 class="card-header__title" id="client-contacts-title"><?= e(__('clients.contacts')) ?></h2><p class="section-heading__note"><?= e(__('clients.contacts_count', ['count' => (string) count($contacts)])) ?></p></div></div>
                    <?php if ($contacts === [] && empty($canManage)): ?><p class="muted text-footnote"><?= e(__('clients.no_contacts')) ?></p><?php endif; ?>
                    <?php foreach ($contacts as $contact): ?>
                        <details class="contact-record" <?= $contactErrorId === (int) $contact['id'] ? 'open' : '' ?>>
                            <summary class="contact-record__summary"><span><strong><?= e($contact['full_name']) ?></strong><?php if (!empty($contact['job_title'])): ?><span class="table-primary__meta"><?= e($contact['job_title']) ?></span><?php endif; ?></span><?php if ($contact['is_primary']): ?><span class="badge badge--info badge--plain"><?= e(__('clients.primary_contact')) ?></span><?php endif; ?></summary>
                            <?php if (!empty($canManage)): ?>
                                <?php $contactFormValues = $contactErrorId === (int) $contact['id'] ? $contactValues : $contact; ?>
                                <form class="contact-edit-form" method="post" action="<?= e(url('/clients/' . $client['id'] . '/contacts/' . $contact['id'])) ?>">
                                    <?= csrf_field() ?>
                                    <div class="form-grid">
                                        <div class="field"><label class="field__label" for="contact-<?= e((string) $contact['id']) ?>-name"><?= e(__('clients.contact_name')) ?></label><input class="field__control" id="contact-<?= e((string) $contact['id']) ?>-name" name="full_name" maxlength="150" required value="<?= e($contactFormValues['full_name'] ?? '') ?>"><?php if ($contactErrorId === (int) $contact['id'] && !empty($contactErrors['full_name'])): ?><span class="field__hint field__hint--error"><?= e($contactErrors['full_name']) ?></span><?php endif; ?></div>
                                        <div class="field"><label class="field__label" for="contact-<?= e((string) $contact['id']) ?>-title"><?= e(__('clients.contact_role')) ?></label><input class="field__control" id="contact-<?= e((string) $contact['id']) ?>-title" name="job_title" maxlength="100" value="<?= e($contactFormValues['job_title'] ?? '') ?>"><?php if ($contactErrorId === (int) $contact['id'] && !empty($contactErrors['job_title'])): ?><span class="field__hint field__hint--error"><?= e($contactErrors['job_title']) ?></span><?php endif; ?></div>
                                        <div class="field"><label class="field__label" for="contact-<?= e((string) $contact['id']) ?>-email"><?= e(__('settings.email')) ?></label><input class="field__control" id="contact-<?= e((string) $contact['id']) ?>-email" name="email" type="email" maxlength="190" value="<?= e($contactFormValues['email'] ?? '') ?>"><?php if ($contactErrorId === (int) $contact['id'] && !empty($contactErrors['email'])): ?><span class="field__hint field__hint--error"><?= e($contactErrors['email']) ?></span><?php endif; ?></div>
                                        <?php View::partial('partials/tunisian-phone-field', [
                                            'inputId' => 'contact-' . (int) $contact['id'] . '-phone',
                                            'value' => $contactFormValues['phone'] ?? '',
                                            'fieldError' => $contactErrorId === (int) $contact['id'] ? ($contactErrors['phone'] ?? '') : '',
                                        ]) ?>
                                    </div>
                                    <div class="cluster cluster--between"><label class="check-label"><input type="checkbox" name="is_primary" value="1" <?= !empty($contactFormValues['is_primary']) ? 'checked' : '' ?>> <?= e(__('clients.primary_contact')) ?></label><div class="cluster"><button class="button button--small button--secondary" type="submit"><?= e(__('common.save')) ?></button></div></div>
                                </form>
                                <form method="post" action="<?= e(url('/clients/' . $client['id'] . '/contacts/' . $contact['id'] . '/delete')) ?>" data-confirm-message="<?= e(__('clients.confirm_delete_contact')) ?>">
                                    <?= csrf_field() ?><button class="button button--small button--ghost button--danger" type="submit"><?= e(__('common.delete')) ?></button>
                                </form>
                            <?php else: ?>
                                <dl class="detail-list"><div class="detail-list__item"><dt><?= e(__('settings.email')) ?></dt><dd><?= e($contact['email'] !== '' ? $contact['email'] : '—') ?></dd></div><div class="detail-list__item"><dt><?= e(__('settings.phone')) ?></dt><dd><?= e($contact['phone'] !== '' ? $contact['phone'] : '—') ?></dd></div></dl>
                            <?php endif; ?>
                        </details>
                    <?php endforeach; ?>
                    <?php if (!empty($canManage)): ?>
                        <?php $newContactValues = $contactErrorId === 0 ? $contactValues : []; ?>
                        <details class="contact-new-details" <?= $contactErrorId === 0 && $contactErrors !== [] ? 'open' : '' ?>>
                            <summary class="button button--secondary button--small contact-new-details__summary"><?= e(__('clients.add_contact_toggle')) ?></summary>
                            <form class="contact-new-form" method="post" action="<?= e(url('/clients/' . $client['id'] . '/contacts')) ?>">
                                <?= csrf_field() ?>
                                <h3 class="section-heading__title"><?= e(__('clients.add_contact')) ?></h3>
                                <div class="form-grid">
                                    <div class="field"><label class="field__label" for="new-contact-name"><?= e(__('clients.contact_name')) ?></label><input class="field__control" id="new-contact-name" name="full_name" maxlength="150" required value="<?= e($newContactValues['full_name'] ?? '') ?>"><?php if ($contactErrorId === 0 && !empty($contactErrors['full_name'])): ?><span class="field__hint field__hint--error"><?= e($contactErrors['full_name']) ?></span><?php endif; ?></div>
                                    <div class="field"><label class="field__label" for="new-contact-title"><?= e(__('clients.contact_role')) ?></label><input class="field__control" id="new-contact-title" name="job_title" maxlength="100" value="<?= e($newContactValues['job_title'] ?? '') ?>"><?php if ($contactErrorId === 0 && !empty($contactErrors['job_title'])): ?><span class="field__hint field__hint--error"><?= e($contactErrors['job_title']) ?></span><?php endif; ?></div>
                                    <div class="field"><label class="field__label" for="new-contact-email"><?= e(__('settings.email')) ?></label><input class="field__control" id="new-contact-email" name="email" type="email" maxlength="190" value="<?= e($newContactValues['email'] ?? '') ?>"><?php if ($contactErrorId === 0 && !empty($contactErrors['email'])): ?><span class="field__hint field__hint--error"><?= e($contactErrors['email']) ?></span><?php endif; ?></div>
                                    <?php View::partial('partials/tunisian-phone-field', [
                                        'inputId' => 'new-contact-phone',
                                        'value' => $newContactValues['phone'] ?? '',
                                        'fieldError' => $contactErrorId === 0 ? ($contactErrors['phone'] ?? '') : '',
                                    ]) ?>
                                </div>
                                <?php if (!empty($contactErrors['general'])): ?><p class="field__hint field__hint--error"><?= e($contactErrors['general']) ?></p><?php endif; ?>
                                <div class="cluster cluster--between"><label class="check-label"><input type="checkbox" name="is_primary" value="1" <?= !empty($newContactValues['is_primary']) ? 'checked' : '' ?>> <?= e(__('clients.primary_contact')) ?></label><button class="button button--secondary" type="submit"><?= e(__('clients.add_contact')) ?></button></div>
                                </form>
                        </details>
                    <?php endif; ?>
                </section>
        </div>
    </aside>
</div>
