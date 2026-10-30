<?php
declare(strict_types=1);

namespace Modules\SuiviChantier\Services;

use App\Core\Auth;
use App\Core\HookManager;
use Modules\SuiviChantier\Models\ChantierRepository;

/**
 * Passerelle « fiche projet → suivi de chantier ».
 *
 * Le module expose une seule section, avec deux chiffres utiles (visites, réserves non levées).
 * Les droits sont ceux du noyau (`projects.view`) : aucun module n'invente de permission.
 */
final class ChantierContext
{
    public static function register(): void
    {
        HookManager::addFilter('project.detail.sections', static function (array $sections, array $project): array {
            if (!Auth::can('projects.view')) {
                return $sections;
            }
            $projectId = (int) ($project['id'] ?? 0);
            if ($projectId < 1) {
                return $sections;
            }
            $counts = (new ChantierRepository())->contextCounts($projectId);
            $meta = __('chantier.context_meta', [
                'visits' => (string) $counts['visits'],
                'reserves' => (string) $counts['open_reserves'],
            ]);
            $sections[] = [
                'key' => 'suivi-chantier',
                'label' => __('chantier.title'),
                'meta' => $meta,
                'url' => url('/chantier/' . $projectId),
                'icon' => 'plan',
                'tone' => $counts['open_reserves'] > 0 ? 'warning' : 'neutral',
            ];

            return $sections;
        });
    }
}
