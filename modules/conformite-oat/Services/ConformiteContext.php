<?php
declare(strict_types=1);

namespace Modules\ConformiteOat\Services;

use App\Core\Auth;
use App\Core\HookManager;

/**
 * Passerelle « fiche projet → attestation de conformité ».
 *
 * L'attestation est calculée par le module à l'ouverture de son écran ; la fiche projet se
 * contente d'y mener, sans reprendre d'information réglementaire à son compte.
 */
final class ConformiteContext
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
            $sections[] = [
                'key' => 'conformite-oat',
                'label' => __('conformite.title'),
                'meta' => __('conformite.context_meta'),
                'url' => url('/conformite/attestations/' . $projectId),
                'icon' => 'check-circle',
                'tone' => 'neutral',
            ];

            return $sections;
        });
    }
}
