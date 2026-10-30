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
        $settingsAvailable = Auth::can('settings.view')
            || Auth::can('clients.manage')
            || Auth::can('projects.configure');
        $settingsUrl = Auth::can('settings.view')
            ? url('/settings')
            : (Auth::can('projects.configure') ? url('/settings/project-phases') : url('/settings/custom-fields'));
        $items = [
            ['label' => __('nav.dashboard'), 'short' => __('nav.home'), 'url' => url('/dashboard'), 'icon' => 'grid', 'key' => 'dashboard', 'available' => true],
            ['label' => __('nav.clients'), 'short' => __('nav.clients'), 'url' => url('/clients'), 'icon' => 'people', 'key' => 'clients', 'available' => $clientsAvailable],
            ['label' => __('nav.projects'), 'short' => __('nav.projects'), 'url' => url('/projects'), 'icon' => 'briefcase', 'key' => 'projects', 'available' => $projectsAvailable],
            ['label' => __('nav.documents'), 'short' => __('nav.documents'), 'url' => url('/documents'), 'icon' => 'file-text', 'key' => 'documents', 'available' => false],
            ['label' => __('nav.time'), 'short' => __('nav.time'), 'url' => url('/time'), 'icon' => 'clock', 'key' => 'time', 'available' => false],
            ['label' => __('nav.settings'), 'short' => __('nav.settings'), 'url' => $settingsUrl, 'icon' => 'settings', 'key' => 'settings', 'available' => $settingsAvailable],
        ];
        $commands = [];
        foreach ($items as $item) {
            if ($item['available']) {
                $commands[] = [
                    'label' => $item['label'],
                    'description' => 'Ouvrir la section',
                    'url' => $item['url'],
                    'icon' => $item['icon'],
                    'group' => 'Navigation',
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
                'location' => $settings['postal_code'] !== '' ? $settings['postal_code'] : 'Espace sécurisé',
                'plan' => (string) ($user['role_label'] ?? ''),
            ],
            'userProfile' => [
                'name' => (string) ($user['name'] ?? ''),
                'initials' => self::initials((string) ($user['name'] ?? '')),
            ],
            'authenticated' => true,
            'showAppActions' => true,
            'showDesignActions' => false,
            'canManageUsers' => Auth::can('users.manage'),
            'commands' => $commands,
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
