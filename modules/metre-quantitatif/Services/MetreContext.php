<?php
declare(strict_types=1);

namespace Modules\MetreQuantitatif\Services;

use App\Core\Auth;
use App\Core\HookManager;
use Modules\MetreQuantitatif\Models\MetreRepository;

/** Passerelle « fiche projet → métré quantitatif » : nombre de postes saisis. */
final class MetreContext
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
            $postes = (new MetreRepository())->contextCounts($projectId);
            $sections[] = [
                'key' => 'metre-quantitatif',
                'label' => __('metre.title'),
                'meta' => __('metre.context_meta', ['count' => (string) $postes]),
                'url' => url('/metre/' . $projectId),
                'icon' => 'layers',
                'tone' => 'neutral',
            ];

            return $sections;
        });
    }
}
