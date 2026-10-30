<?php
declare(strict_types=1);

namespace Modules\PortailClient\Services;

use App\Core\Auth;
use App\Core\HookManager;
use Modules\PortailClient\Models\PortailRepository;

/**
 * Passerelle « fiche client → portail ».
 *
 * Seul le nombre de liens encore utilisables est affiché : un lien expiré ou révoqué ne doit
 * pas laisser croire que le client a encore accès.
 */
final class PortailContext
{
    public static function register(): void
    {
        HookManager::addFilter('client.detail.sections', static function (array $sections, array $client): array {
            if (!Auth::can('clients.view') && !Auth::can('projects.view')) {
                return $sections;
            }
            $clientId = (int) ($client['id'] ?? 0);
            if ($clientId < 1) {
                return $sections;
            }
            $active = (new PortailRepository())->activeLinkCount($clientId, date('Y-m-d'));
            $sections[] = [
                'key' => 'portail-client',
                'label' => __('portail.title'),
                'meta' => __('portail.context_meta', ['count' => (string) $active]),
                'url' => url('/portail?client=' . $clientId),
                'icon' => 'globe',
                'tone' => $active > 0 ? 'success' : 'neutral',
            ];

            return $sections;
        });
    }
}
