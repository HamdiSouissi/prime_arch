<?php
declare(strict_types=1);

namespace Modules\CrmOpportunites\Services;

use App\Core\Auth;
use App\Core\HookManager;
use Modules\CrmOpportunites\Models\CrmRepository;

/** Passerelle « fiche client → CRM » : opportunités encore ouvertes. */
final class CrmContext
{
    public static function register(): void
    {
        HookManager::addFilter('client.detail.sections', static function (array $sections, array $client): array {
            if (!Auth::can('clients.view')) {
                return $sections;
            }
            $clientId = (int) ($client['id'] ?? 0);
            if ($clientId < 1) {
                return $sections;
            }
            $open = (new CrmRepository())->openCountForClient($clientId);
            $sections[] = [
                'key' => 'crm-opportunites',
                'label' => __('crm.title'),
                'meta' => __('crm.context_meta', ['count' => (string) $open]),
                'url' => url('/crm?client=' . $clientId),
                'icon' => 'chart',
                'tone' => $open > 0 ? 'info' : 'neutral',
            ];

            return $sections;
        });
    }
}
