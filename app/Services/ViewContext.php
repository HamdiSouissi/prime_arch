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
        $settingsAvailable = Auth::can('settings.view');
        $items = [
            ['label' => 'Tableau de bord', 'short' => 'Accueil', 'url' => url('/dashboard'), 'icon' => 'grid', 'key' => 'dashboard', 'available' => true],
            ['label' => 'Clients', 'short' => 'Clients', 'url' => url('/clients'), 'icon' => 'people', 'key' => 'clients', 'available' => false],
            ['label' => 'Projets', 'short' => 'Projets', 'url' => url('/projects'), 'icon' => 'briefcase', 'key' => 'projects', 'available' => false],
            ['label' => 'Documents', 'short' => 'Docs', 'url' => url('/documents'), 'icon' => 'file-text', 'key' => 'documents', 'available' => false],
            ['label' => 'Temps', 'short' => 'Temps', 'url' => url('/time'), 'icon' => 'clock', 'key' => 'time', 'available' => false],
            ['label' => 'Réglages', 'short' => 'Réglages', 'url' => url('/settings'), 'icon' => 'settings', 'key' => 'settings', 'available' => $settingsAvailable],
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
