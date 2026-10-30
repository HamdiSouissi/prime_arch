<?php
declare(strict_types=1);

use App\Core\View;

$entries = is_array($entries ?? null) ? $entries : [];
$eventLabels = [
    'auth.login.succeeded' => 'Connexion réussie',
    'auth.login.failed' => 'Échec de connexion',
    'auth.login.throttled' => 'Connexion limitée',
    'auth.logout' => 'Déconnexion',
    'users.created' => 'Compte créé',
    'users.access.updated' => 'Accès utilisateur modifié',
    'clients.created' => 'Client créé',
    'clients.updated' => 'Client modifié',
    'clients.contact.created' => 'Contact client ajouté',
    'clients.contact.updated' => 'Contact client modifié',
    'clients.contact.deleted' => 'Contact client supprimé',
    'projects.created' => 'Projet créé',
    'projects.updated' => 'Projet modifié',
    'projects.plan.updated' => 'Plan de projet modifié',
    'projects.phase.status.updated' => 'Statut de phase modifié',
    'projects.phase.progress.updated' => 'Progression de phase modifiée',
    'project_phases.created' => 'Phase de mission créée',
    'project_phases.updated' => 'Phase de mission modifiée',
    'custom_fields.created' => 'Champ personnalisé créé',
    'custom_fields.updated' => 'Champ personnalisé modifié',
    'custom_fields.toggled' => 'Champ personnalisé activé/désactivé',
    'finance.rates.created' => 'Taux horaire ajouté',
    'documents.templates.created' => 'Modèle de document créé',
    'documents.templates.updated' => 'Modèle de document modifié',
    'documents.created' => 'Brouillon créé',
    'documents.updated' => 'Brouillon modifié',
    'documents.issued' => 'Document émis',
    'documents.payment.added' => 'Règlement ajouté',
    'documents.draft.deleted' => 'Brouillon supprimé',
    'settings.updated' => 'Réglages du cabinet modifiés',
    'time.created' => 'Saisie de temps créée',
    'time.updated' => 'Saisie de temps modifiée',
    'modules.installed' => 'Module installé',
    'modules.activated' => 'Module activé',
    'modules.deactivated' => 'Module désactivé',
    'backup.created' => 'Sauvegarde créée',
    'backup.restored' => 'Sauvegarde restaurée',
];
$detailLabels = [
    'role' => 'Rôle',
    'is_active' => 'Compte actif',
    'result' => 'Résultat',
    'document_type' => 'Type de document',
    'status' => 'Statut',
    'module' => 'Module',
    'files' => 'Fichiers',
    'tables' => 'Tables',
    'client_id' => 'Client',
    'contact_action' => 'Action',
    'entity_type' => 'Type de fiche',
    'field_type' => 'Type de champ',
    'is_required' => 'Obligatoire',
    'sort_order' => 'Ordre',
    'project_id' => 'Projet',
    'phase_action' => 'Action de phase',
    'progress_percent' => 'Progression (%)',
    'user_id' => 'Compte utilisateur',
    'effective_from' => 'Date d’effet',
    'locale' => 'Langue',
];
?>
<?php View::partial('partials/page-header', [
    'pageTitle' => 'Journal d’audit',
    'subtitle' => 'Événements de sécurité et actions de gestion, sans contenu des formulaires ni adresse IP brute.',
    'eyebrow' => 'Sécurité du cabinet',
    'secondaryAction' => ['label' => __('users.title'), 'icon' => 'people', 'url' => url('/admin/users')],
]) ?>

<section class="card" aria-labelledby="audit-list-title">
    <div class="card-header">
        <div>
            <h2 class="card-header__title" id="audit-list-title">Événements récents</h2>
            <p class="section-heading__note">
                <?= e((string) ($total ?? 0)) ?> événement(s) · affichage de 50 par page
            </p>
        </div>
    </div>
    <?php if ($entries === []): ?>
        <?php View::partial('partials/empty-state', [
            'title' => 'Aucun événement enregistré',
            'description' => 'Les nouvelles actions de sécurité apparaîtront ici après la migration du journal.',
            'image' => asset('img/empty-projects.svg'),
            'imageAlt' => '',
        ]) ?>
    <?php else: ?>
        <div class="table-wrap" role="region" aria-label="Table des événements d’audit" tabindex="0">
            <table class="data-table audit-table">
                <thead><tr>
                    <th scope="col">Date et heure</th>
                    <th scope="col">Événement</th>
                    <th scope="col">Compte</th>
                    <th scope="col">Objet</th>
                    <th scope="col">Détails</th>
                </tr></thead>
                <tbody>
                <?php foreach ($entries as $entry): ?>
                    <?php
                    $timestamp = strtotime((string) ($entry['created_at'] ?? ''));
                    $eventKey = (string) ($entry['event_key'] ?? '');
                    $details = is_array($entry['details'] ?? null) ? $entry['details'] : [];
                    ?>
                    <tr>
                        <td><?= e($timestamp !== false ? date('d/m/Y H:i:s', $timestamp) : '—') ?></td>
                        <td><strong><?= e($eventLabels[$eventKey] ?? $eventKey) ?></strong></td>
                        <td><?= e((string) ($entry['actor_name'] ?? 'Système')) ?></td>
                        <td>
                            <?= e((string) ($entry['subject_type'] ?? '')) ?>
                            <?php if (!empty($entry['subject_id'])): ?>
                                #<?= e((string) $entry['subject_id']) ?>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($details === []): ?>
                                <span class="muted">—</span>
                            <?php else: ?>
                                <ul class="audit-details">
                                <?php foreach ($details as $key => $value): ?>
                                    <li>
                                        <span><?= e($detailLabels[$key] ?? (string) $key) ?> :</span>
                                        <?= e(is_bool($value) ? ($value ? 'Oui' : 'Non') : (string) $value) ?>
                                    </li>
                                <?php endforeach; ?>
                                </ul>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>

<?php if ((int) ($pageCount ?? 1) > 1): ?>
    <nav class="pagination" aria-label="Pages du journal d’audit">
        <?php if ((int) $page > 1): ?>
            <a class="button button--secondary button--small"
                href="<?= e(url('/admin/audit?page=' . ((int) $page - 1))) ?>">Précédent</a>
        <?php endif; ?>
        <span aria-current="page">Page <?= e((string) $page) ?> sur <?= e((string) $pageCount) ?></span>
        <?php if ((int) $page < (int) $pageCount): ?>
            <a class="button button--secondary button--small"
                href="<?= e(url('/admin/audit?page=' . ((int) $page + 1))) ?>">Suivant</a>
        <?php endif; ?>
    </nav>
<?php endif; ?>
