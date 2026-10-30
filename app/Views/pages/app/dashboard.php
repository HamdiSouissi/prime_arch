<?php
declare(strict_types=1);

use App\Core\View;
?>
<?php View::partial('partials/page-header', [
    'pageTitle' => __('dashboard.welcome', ['name' => $userName ?? '']),
    'subtitle' => __('dashboard.foundation_intro'),
    'eyebrow' => $cabinetName ?? config('app.name'),
]) ?>

<section class="foundation-banner card" aria-labelledby="foundation-title">
    <span class="foundation-banner__icon" aria-hidden="true">
        <svg class="icon icon--large"><use href="<?= e(asset('img/icons.svg')) ?>#shield"></use></svg>
    </span>
    <div class="foundation-banner__copy">
        <p class="page-header__eyebrow"><?= e($roleLabel ?? '') ?></p>
        <h2 id="foundation-title"><?= e(__('dashboard.foundation_title')) ?></h2>
        <p><?= e(__('dashboard.foundation_intro')) ?></p>
    </div>
</section>

<div class="stats-grid stats-grid--three foundation-stats">
    <?php View::partial('partials/stat-card', [
        'label' => 'Compte connecté',
        'value' => $userName ?? '',
        'icon' => 'user',
        'tone' => 'blue',
        'note' => 'Session sécurisée et protégée contre les requêtes CSRF',
    ]) ?>
    <?php View::partial('partials/stat-card', [
        'label' => 'Rôle attribué',
        'value' => $roleLabel ?? '',
        'icon' => 'shield',
        'tone' => 'mint',
        'note' => 'Permissions appliquées côté serveur',
    ]) ?>
    <?php View::partial('partials/stat-card', [
        'label' => 'Référentiel géographique',
        'value' => ($geographyCounts['governorates'] ?? 0) . ' / ' . ($geographyCounts['delegations'] ?? 0),
        'icon' => 'map-pin',
        'tone' => 'coral',
        'note' => 'Gouvernorats / délégations préchargés',
    ]) ?>
</div>

<section class="foundation-grid" aria-label="Actions des fondations">
    <article class="card foundation-action">
        <span class="foundation-action__icon"><svg class="icon"><use href="<?= e(asset('img/icons.svg')) ?>#building"></use></svg></span>
        <div>
            <h2 class="card-header__title">Identité du cabinet</h2>
            <p class="muted text-footnote">Coordonnées, matricule fiscal, logo et paramètres fiscaux configurables.</p>
        </div>
        <a class="button button--secondary" href="<?= e(url('/settings')) ?>">Ouvrir les réglages</a>
    </article>
    <?php if (!empty($canManageUsers)): ?>
        <article class="card foundation-action">
            <span class="foundation-action__icon foundation-action__icon--mint"><svg class="icon"><use href="<?= e(asset('img/icons.svg')) ?>#people"></use></svg></span>
            <div>
                <h2 class="card-header__title">Utilisateurs et rôles</h2>
                <p class="muted text-footnote">Créez des comptes et attribuez uniquement les permissions nécessaires.</p>
            </div>
            <a class="button button--secondary" href="<?= e(url('/admin/users')) ?>">Gérer les accès</a>
        </article>
    <?php endif; ?>
</section>

<p class="foundation-footnote"><svg class="icon icon--small" aria-hidden="true"><use href="<?= e(asset('img/icons.svg')) ?>#info"></use></svg> Les taux fiscaux sont des paramètres indicatifs ; validez-les avec votre expert-comptable et l'OAT avant toute facturation.</p>
