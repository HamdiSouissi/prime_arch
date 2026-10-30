<?php
declare(strict_types=1);

namespace Modules\PermisBatir\Services;

use App\Core\Auth;
use App\Core\HookManager;
use Modules\PermisBatir\Models\DossierRepository;

/**
 * Passerelle « fiche projet → permis de bâtir ».
 *
 * Deux informations seulement : le nombre de dossiers et l'état du plus récent. Aucun délai
 * légal n'est affiché ici : le module reste un suivi saisi, pas une promesse réglementaire.
 */
final class PermisContext
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
            $summary = (new DossierRepository())->contextSummary($projectId);
            if ($summary === null) {
                return $sections;
            }
            $statusKey = 'permis.status.' . $summary['status'];
            $statusLabel = __($statusKey);
            $sections[] = [
                'key' => 'permis-batir',
                'label' => __('permis.title'),
                'meta' => $statusLabel === $statusKey
                    ? $summary['reference']
                    : $summary['reference'] . ' · ' . $statusLabel,
                'url' => url('/permis?q=' . rawurlencode($summary['reference'])),
                'icon' => 'shield',
                'tone' => match ($summary['status']) {
                    'granted' => 'success',
                    'refused', 'withdrawn' => 'error',
                    'complement' => 'warning',
                    default => 'info',
                },
            ];

            return $sections;
        });
    }
}
