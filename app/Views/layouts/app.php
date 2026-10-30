<?php
declare(strict_types=1);

/** @var string $content */
use App\Core\Auth;
use App\Core\Translator;
use App\Core\View;
use App\Services\ViewContext;

$designEnabled = strtolower((string) config('app.env', 'production')) !== 'production';
$isDesignPreview = $designEnabled && !empty($devOnly);
$isAuthenticated = !empty($authenticated) || Auth::check();
$workspace = $workspace ?? [];
$userProfile = $userProfile ?? [];
$profileUrl = $profileUrl ?? '';
$navigation = $navigation ?? [];

// Barre d'onglets et en-tête identiques sur toutes les pages : les écrans qui ne
// fournissent pas de navigation (404, erreurs) héritent du menu standard.
if ($navigation === [] && $isAuthenticated && !$isDesignPreview) {
    try {
        $fallbackContext = (new ViewContext())->authenticated((string) ($title ?? ''), 'home');
        $navigation = $fallbackContext['navigation'];
        $workspace = $workspace !== [] ? $workspace : $fallbackContext['workspace'];
        $userProfile = $userProfile !== [] ? $userProfile : $fallbackContext['userProfile'];
        $profileUrl = $profileUrl !== '' ? $profileUrl : (string) $fallbackContext['profileUrl'];
    } catch (\Throwable) {
        // Base indisponible : la page d'erreur reste affichable avec un menu minimal.
        $navigation = [];
    }
}

if ($navigation === []) {
    $navigation = $isDesignPreview ? [
        ['label' => 'Tableau de bord', 'short' => 'Accueil', 'url' => url('/design/dashboard'), 'icon' => 'grid', 'key' => 'dashboard'],
        ['label' => 'Clients', 'short' => 'Clients', 'url' => url('/design/clients'), 'icon' => 'people', 'key' => 'clients'],
        ['label' => 'Projets', 'short' => 'Projets', 'url' => url('/design/projects'), 'icon' => 'briefcase', 'key' => 'projects'],
        ['label' => 'Documents', 'short' => 'Docs', 'url' => url('/design/documents'), 'icon' => 'file-text', 'key' => 'documents'],
        ['label' => 'Temps', 'short' => 'Temps', 'url' => url('/design/time'), 'icon' => 'clock', 'key' => 'time'],
        ['label' => 'Réglages', 'short' => 'Réglages', 'url' => url('/design/settings'), 'icon' => 'settings', 'key' => 'settings'],
    ] : [
        ['label' => __('nav.home'), 'short' => __('nav.home'), 'url' => url('/'), 'icon' => 'home', 'key' => 'home'],
    ];
}
$quickActions = $quickActions ?? ($isDesignPreview ? [
    ['label' => 'Nouveau client', 'description' => 'Consulter la gestion des clients', 'url' => url('/design/clients'), 'icon' => 'people'],
    ['label' => 'Nouveau projet', 'description' => 'Ouvrir le portefeuille de projets', 'url' => url('/design/projects'), 'icon' => 'briefcase'],
    ['label' => 'Nouveau devis', 'description' => 'Préparer une proposition d’honoraires', 'url' => url('/design/documents'), 'icon' => 'quote'],
    ['label' => 'Saisie du temps', 'description' => 'Démarrer le chronomètre de démo', 'url' => url('/design/time'), 'icon' => 'timer'],
] : []);
$commands = $commands ?? [];
if ($commands === []) {
    foreach ($navigation as $navigationItem) {
        if (array_key_exists('available', $navigationItem) && !$navigationItem['available']) {
            continue;
        }
        $commands[] = [
            'label' => $navigationItem['label'],
            'description' => __('common.open_section'),
            'url' => $navigationItem['url'],
            'icon' => $navigationItem['icon'],
            'group' => __('common.navigation'),
        ];
    }
}
if ($isDesignPreview) {
    $commands = array_merge($commands, [
        ['label' => 'Extensions et modules', 'description' => 'Activer les modules du cabinet', 'url' => url('/design/modules'), 'icon' => 'layers', 'group' => 'Outils'],
        ['label' => 'Bibliothèque de composants', 'description' => 'Voir le système de design', 'url' => url('/design/styleguide'), 'icon' => 'sparkles', 'group' => 'Outils'],
        ['label' => 'Écran de connexion', 'description' => 'Aperçu de l’authentification', 'url' => url('/design/login'), 'icon' => 'lock', 'group' => 'Outils'],
        ['label' => 'Hichem Ben Salem', 'description' => 'Client · Sidi Bou Saïd', 'url' => url('/design/clients/CL-2025-031'), 'icon' => 'people', 'group' => 'Clients'],
        ['label' => 'Villa contemporaine', 'description' => 'Projet PA-2025-014 · Sidi Bou Saïd', 'url' => url('/design/projects/PA-2025-014'), 'icon' => 'building', 'group' => 'Projets'],
        ['label' => 'Devis PA-2025-018', 'description' => 'Document · Hichem Ben Salem', 'url' => url('/design/documents/PA-2025-018'), 'icon' => 'quote', 'group' => 'Documents'],
    ]);
}
?>
<!DOCTYPE html>
<html lang="<?= e(Translator::locale()) ?>" dir="<?= e(Translator::direction()) ?>" data-icons="<?= e(asset('img/icons.svg')) ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="color-scheme" content="light dark">
    <meta name="theme-color" content="#F4F7FF" media="(prefers-color-scheme: light)">
    <meta name="theme-color" content="#111821" media="(prefers-color-scheme: dark)">
    <title><?= e(($title ?? '') !== '' ? $title . ' — ' : '') ?><?= e(config('app.name')) ?></title>
    <link rel="icon" type="image/png" sizes="32x32" href="<?= e(asset('img/favicon.png')) ?>">
    <link rel="apple-touch-icon" sizes="180x180" href="<?= e(asset('img/logo-prime-architecture.png')) ?>">
    <link rel="stylesheet" href="<?= e(asset('css/app.css')) ?>">
</head>
<body>
<a class="skip-link" href="#main"><?= e(__('common.skip_to_content')) ?></a>
<?php if (!empty($devOnly)): ?>
    <div class="dev-banner"><span class="dev-banner__dot" aria-hidden="true"></span><?= e(__('app.demo_banner')) ?></div>
<?php endif; ?>
<div class="app-shell" data-app-shell>
    <?php View::partial('partials/sidebar', [
        'items' => $navigation,
        'activeNav' => $activeNav ?? ($isDesignPreview ? 'dashboard' : 'home'),
        'showDesignNav' => $isDesignPreview,
        'showWorkspace' => $isDesignPreview || $isAuthenticated,
        'authenticated' => $isAuthenticated,
        'workspace' => $workspace,
    ]) ?>
    <div class="app-main">
        <?php View::partial('partials/topbar', [
            'title' => $title ?? '',
            'showDesignActions' => $isDesignPreview,
            'showAppActions' => !empty($showAppActions) || $isAuthenticated,
            'workspace' => $workspace,
            'userProfile' => $userProfile,
            'profileUrl' => $profileUrl,
        ]) ?>
        <main class="app-content" id="main" tabindex="-1">
            <?= $content ?>
        </main>
    </div>
    <button class="mobile-menu-backdrop" type="button" data-mobile-menu-backdrop aria-label="<?= e(__('common.close_navigation')) ?>" aria-hidden="true" tabindex="-1"></button>
</div>
<?php View::partial('partials/tabbar', ['items' => $navigation, 'activeNav' => $activeNav ?? ($isDesignPreview ? 'dashboard' : 'home')]) ?>
<?php if ($isDesignPreview): ?>
    <?php View::partial('partials/quick-add', ['quickActions' => $quickActions]) ?>
<?php endif; ?>
<?php if ($isDesignPreview || $isAuthenticated): ?>
    <?php View::partial('partials/command-palette', ['commands' => $commands]) ?>
<?php endif; ?>
<?php View::partial('partials/toast', ['toastMessage' => $toastMessage ?? null]) ?>
<?php \App\Core\View::partial('partials/i18n') ?>
<script type="module" src="<?= e(asset('js/app.js')) ?>"></script>
</body>
</html>
