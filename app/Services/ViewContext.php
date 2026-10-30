<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Auth;

/**
 * Données de navigation partagées par les écrans authentifiés.
 */
final class ViewContext
{
    /** @return array<string,mixed> */
    public function authenticated(string $title, string $activeNav): array
    {
        $user = Auth::user() ?? [];
        $settings = (new CabinetSettings())->get();
        $clientsAvailable = Auth::can('clients.view');
        $projectsAvailable = Auth::can('projects.view');
        $timeAvailable = Auth::can('time.view') || Auth::can('time.manage')
            || Auth::can('time.view_all') || Auth::can('time.manage_all');
        $documentsAvailable = Auth::can('documents.view') || Auth::can('documents.create')
            || Auth::can('documents.manage') || Auth::can('documents.issue')
            || Auth::can('documents.payments.manage') || Auth::can('documents.templates.manage')
            || Auth::can('settings.manage');
        $rateSettingsAvailable = Auth::can('finance.rates.view') || Auth::can('finance.rates.manage');
        // Le menu ne propose que des destinations réellement ouvertes au rôle : « Réglages »
        // mène aux réglages du cabinet, aux taux horaires ou aux comptes. Les écrans de
        // configuration métier (phases, champs personnalisés, modèles de documents) restent
        // attachés à leur page Clients / Projets / Documents et n'apparaissent pas ici.
        $settingsUrl = self::settingsDestination(
            Auth::can('settings.view') || Auth::can('settings.manage'),
            $rateSettingsAvailable,
            Auth::can('users.manage')
        );
        $settingsAvailable = $settingsUrl !== null;
        // Chaque entrée du noyau déclare sa section : le menu se lit par métier et non plus
        // comme une liste unique (lot 3). Une extension fait de même, sinon elle rejoint
        // « Modules externes ».
        $items = [
            ['label' => __('nav.dashboard'), 'short' => __('nav.home'), 'url' => url('/dashboard'), 'icon' => 'grid', 'key' => 'dashboard', 'available' => true, 'section' => NavigationSections::PILOTAGE],
            ['label' => __('nav.clients'), 'short' => __('nav.clients'), 'url' => url('/clients'), 'icon' => 'people', 'key' => 'clients', 'available' => $clientsAvailable, 'section' => NavigationSections::PRODUCTION],
            ['label' => __('nav.projects'), 'short' => __('nav.projects'), 'url' => url('/projects'), 'icon' => 'briefcase', 'key' => 'projects', 'available' => $projectsAvailable, 'section' => NavigationSections::PRODUCTION],
            ['label' => __('nav.time'), 'short' => __('nav.time'), 'url' => url('/time'), 'icon' => 'clock', 'key' => 'time', 'available' => $timeAvailable, 'section' => NavigationSections::PRODUCTION],
            ['label' => __('nav.documents'), 'short' => __('nav.documents_short'), 'url' => url('/documents'), 'icon' => 'file-text', 'key' => 'documents', 'available' => $documentsAvailable, 'section' => NavigationSections::FINANCE],
            ['label' => __('nav.settings'), 'short' => __('nav.settings'), 'url' => $settingsUrl ?? '', 'icon' => 'settings', 'key' => 'settings', 'available' => $settingsAvailable, 'section' => NavigationSections::ADMINISTRATION],
        ];

        // Actions rapides réelles : les mêmes destinations que les écrans de création, jamais
        // des liens morts. Le bouton « Nouveau » n'est donc plus réservé au mode maquette.
        $quickActions = [];
        if ($clientsAvailable && Auth::can('clients.manage')) {
            $quickActions[] = [
                'label' => __('quick.client'),
                'description' => __('quick.client_help'),
                'url' => url('/clients/new'),
                'icon' => 'people',
                'key' => 'client',
            ];
        }
        if ($projectsAvailable && Auth::can('projects.manage')) {
            $quickActions[] = [
                'label' => __('quick.project'),
                'description' => __('quick.project_help'),
                'url' => url('/projects/new'),
                'icon' => 'briefcase',
                'key' => 'project',
            ];
        }
        if ($documentsAvailable && (Auth::can('documents.create') || Auth::can('documents.manage'))) {
            $quickActions[] = [
                'label' => __('quick.quote'),
                'description' => __('quick.quote_help'),
                'url' => url('/documents/new'),
                'icon' => 'quote',
                'key' => 'quote',
            ];
        }
        if ($timeAvailable) {
            $quickActions[] = [
                'label' => __('quick.time'),
                'description' => __('quick.time_help'),
                'url' => url('/time'),
                'icon' => 'timer',
                'key' => 'time',
            ];
        }

        $commands = [];
        foreach ($items as $item) {
            if ($item['available']) {
                $commands[] = [
                    'label' => $item['label'],
                    'description' => __('common.open_section'),
                    'url' => $item['url'],
                    'icon' => $item['icon'],
                    'group' => __('common.navigation'),
                ];
            }
        }

        return [
            'title' => $title,
            'pageTitle' => $title,
            'activeNav' => $activeNav,
            'navigation' => $items,
            'workspace' => [
                'name' => $settings['name'],
                'location' => $settings['postal_code'] !== '' ? $settings['postal_code'] : __('app.secure_workspace'),
                'plan' => role_label((string) ($user['role_slug'] ?? ''), (string) ($user['role_label'] ?? '')),
            ],
            'userProfile' => [
                'name' => (string) ($user['name'] ?? ''),
                'initials' => self::initials((string) ($user['name'] ?? '')),
            ],
            // La bulle d'initiales ouvre les réglages accessibles, sinon le tableau de bord.
            'profileUrl' => $settingsUrl ?? url('/dashboard'),
            'authenticated' => true,
            'showAppActions' => true,
            'showDesignActions' => false,
            'canManageUsers' => Auth::can('users.manage'),
            'canManageModules' => Auth::can('users.manage'),
            'canViewRates' => $rateSettingsAvailable,
            'commands' => $commands,
            // Recherche globale de la palette (GET JSON, filtrée par les droits).
            'searchEndpoint' => url('/api/recherche'),
            'quickActions' => $quickActions,
        ];
    }

    /**
     * Destination du bouton « Réglages » pour la combinaison de droits donnée.
     *
     * Renvoie `null` quand le rôle n'a accès à aucun écran de réglages : l'entrée est alors
     * absente du menu, plutôt que grisée ou dirigée vers une page interdite.
     */
    public static function settingsDestination(bool $cabinetSettings, bool $hourlyRates, bool $accounts): ?string
    {
        if ($cabinetSettings) {
            return url('/settings');
        }
        if ($hourlyRates) {
            return url('/settings/hourly-rates');
        }
        if ($accounts) {
            return url('/settings/modules');
        }

        return null;
    }

    private static function initials(string $name): string
    {
        $parts = preg_split('/\s+/u', trim($name), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        if ($parts === []) {
            return 'PA';
        }
        $first = mb_substr($parts[0], 0, 1, 'UTF-8');
        $last = count($parts) > 1 ? mb_substr($parts[count($parts) - 1], 0, 1, 'UTF-8') : '';
        return mb_strtoupper($first . $last, 'UTF-8');
    }
}
