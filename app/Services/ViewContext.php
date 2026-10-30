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
        $settingsAvailable = Auth::can('settings.view')
            || Auth::can('users.manage')
            || Auth::can('clients.manage')
            || Auth::can('projects.configure')
            || $rateSettingsAvailable;
        $settingsUrl = Auth::can('settings.manage')
            ? url('/settings')
            : ($rateSettingsAvailable
                ? url('/settings/hourly-rates')
                : (Auth::can('settings.view')
                    ? url('/settings')
                    : (Auth::can('projects.configure')
                        ? url('/settings/project-phases')
                        : (Auth::can('users.manage') ? url('/settings/modules') : url('/settings/custom-fields')))));
        $items = [
            ['label' => __('nav.dashboard'), 'short' => __('nav.home'), 'url' => url('/dashboard'), 'icon' => 'grid', 'key' => 'dashboard', 'available' => true],
            ['label' => __('nav.clients'), 'short' => __('nav.clients'), 'url' => url('/clients'), 'icon' => 'people', 'key' => 'clients', 'available' => $clientsAvailable],
            ['label' => __('nav.projects'), 'short' => __('nav.projects'), 'url' => url('/projects'), 'icon' => 'briefcase', 'key' => 'projects', 'available' => $projectsAvailable],
            ['label' => __('nav.documents'), 'short' => __('nav.documents'), 'url' => url('/documents'), 'icon' => 'file-text', 'key' => 'documents', 'available' => $documentsAvailable],
            ['label' => __('nav.time'), 'short' => __('nav.time'), 'url' => url('/time'), 'icon' => 'clock', 'key' => 'time', 'available' => $timeAvailable],
            ['label' => __('nav.settings'), 'short' => __('nav.settings'), 'url' => $settingsUrl, 'icon' => 'settings', 'key' => 'settings', 'available' => $settingsAvailable],
        ];
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
            'profileUrl' => $settingsAvailable ? $settingsUrl : url('/dashboard'),
            'authenticated' => true,
            'showAppActions' => true,
            'showDesignActions' => false,
            'canManageUsers' => Auth::can('users.manage'),
            'canManageModules' => Auth::can('users.manage'),
            'canViewRates' => $rateSettingsAvailable,
            'commands' => $commands,
            // Recherche globale de la palette (GET JSON, filtrée par les droits).
            'searchEndpoint' => url('/api/recherche'),
            'quickActions' => [],
        ];
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
