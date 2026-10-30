<?php
declare(strict_types=1);

use App\Core\View;

$clients = [
    ['name' => 'Hichem Ben Salem', 'initials' => 'HB', 'company' => 'Particulier · Sidi Bou Saïd', 'email' => 'hichem.bensalem@email.tn', 'phone' => '+216 20 123 456', 'projects' => '2 projets', 'status' => 'Actif', 'tone' => 'success', 'last' => 'Aujourd’hui'],
    ['name' => 'Société El Amana', 'initials' => 'EA', 'company' => 'Promoteur · Sfax', 'email' => 'contact@elamana.tn', 'phone' => '+216 74 221 908', 'projects' => '4 projets', 'status' => 'Actif', 'tone' => 'success', 'last' => 'Hier'],
    ['name' => 'Dr. Hatem Gharbi', 'initials' => 'HG', 'company' => 'Profession libérale · Sousse', 'email' => 'h.gharbi@cabinet.tn', 'phone' => '+216 98 456 123', 'projects' => '1 projet', 'status' => 'En attente', 'tone' => 'warning', 'last' => '28 oct. 2026'],
    ['name' => 'Nadia Khelifi', 'initials' => 'NK', 'company' => 'Particulier · La Marsa', 'email' => 'nadia.khelifi@email.tn', 'phone' => '+216 22 654 321', 'projects' => '1 projet', 'status' => 'Actif', 'tone' => 'success', 'last' => '24 oct. 2026'],
    ['name' => 'Groupe Cap Bon', 'initials' => 'CB', 'company' => 'Promoteur · Nabeul', 'email' => 'projets@capbon.tn', 'phone' => '+216 72 300 614', 'projects' => '3 projets', 'status' => 'À relancer', 'tone' => 'info', 'last' => '20 oct. 2026'],
];
?>
<?php View::partial('partials/page-header', [
    'pageTitle' => 'Clients',
    'eyebrow' => 'Relations & contacts',
    'subtitle' => 'Retrouvez les coordonnées, projets et échanges de vos clients.',
    'primaryAction' => ['label' => 'Ajouter un client', 'icon' => 'plus', 'dialog' => '#quick-add-dialog'],
]) ?>

<section class="card stack-md" aria-label="Répertoire des clients">
    <div class="filter-row">
        <div class="input-with-icon">
            <svg class="icon icon--small" aria-hidden="true"><use href="<?= e(asset('img/icons.svg')) ?>#search"></use></svg>
            <input type="search" name="client-search" placeholder="Rechercher un nom, un email…" aria-label="Rechercher un client" data-table-search="#clients-table">
        </div>
        <div class="field">
            <label class="u-sr-only" for="client-type">Type de client</label>
            <select id="client-type" aria-label="Filtrer par type" data-filter-select="#clients-table">
                <option value="">Tous les types</option>
                <option value="particulier">Particulier</option>
                <option value="promoteur">Promoteur</option>
                <option value="profession">Profession libérale</option>
            </select>
        </div>
        <button class="button button--secondary" type="button" data-toast-message="Les filtres avancés sont prêts pour la connexion au module Clients.">
            <svg class="icon icon--small" aria-hidden="true"><use href="<?= e(asset('img/icons.svg')) ?>#filter"></use></svg> Filtres
        </button>
    </div>

    <div class="table-wrap">
        <table class="data-table" id="clients-table">
            <thead><tr><th scope="col">Client</th><th scope="col">Coordonnées</th><th scope="col">Projets</th><th scope="col">Statut</th><th scope="col">Dernier contact</th><th scope="col"><span class="u-sr-only">Actions</span></th></tr></thead>
            <tbody>
                <?php foreach ($clients as $client): ?>
                    <?php $filterType = str_contains(strtolower($client['company']), 'particulier') ? 'particulier' : (str_contains(strtolower($client['company']), 'promoteur') ? 'promoteur' : 'profession'); ?>
                    <tr data-filter-row data-filter-value="<?= e($filterType) ?>">
                        <td>
                            <a class="table-primary" href="<?= e(url('/design/clients/CL-2025-031')) ?>">
                                <span class="avatar avatar--small" aria-hidden="true"><?= e($client['initials']) ?></span>
                                <span class="table-primary__text"><span class="table-primary__name"><?= e($client['name']) ?></span><span class="table-primary__meta"><?= e($client['company']) ?></span></span>
                            </a>
                        </td>
                        <td><span class="table-primary__text"><a href="mailto:<?= e($client['email']) ?>"><?= e($client['email']) ?></a><a class="table-primary__meta" href="tel:<?= e(str_replace(' ', '', $client['phone'])) ?>"><?= e($client['phone']) ?></a></span></td>
                        <td><?= e($client['projects']) ?></td>
                        <td><span class="badge badge--<?= e($client['tone']) ?>"><?= e($client['status']) ?></span></td>
                        <td><?= e($client['last']) ?></td>
                        <td><a class="button button--icon button--ghost" href="<?= e(url('/design/clients/CL-2025-031')) ?>" aria-label="Ouvrir la fiche de <?= e($client['name']) ?>"><svg class="icon icon--small icon--directional"><use href="<?= e(asset('img/icons.svg')) ?>#arrow-up-right"></use></svg></a></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <div class="table-footer"><span>Affichage de 5 clients sur 28</span><div class="cluster"><button class="button button--small button--secondary" type="button" disabled>Précédent</button><button class="button button--small button--secondary" type="button" data-toast-message="Page 2 de la liste de démonstration.">Suivant</button></div></div>
</section>
