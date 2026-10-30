<?php
declare(strict_types=1);

namespace Modules\HonorairesBareme\Services;

use App\Core\Auth;
use App\Core\HookManager;
use Modules\HonorairesBareme\Models\HonoraireRepository;

/** Passerelle « fiche projet → honoraires » : nombre de décomptes établis. */
final class HonorairesContext
{
    public static function register(): void
    {
        HookManager::addFilter('project.detail.sections', static function (array $sections, array $project): array {
            if (!Auth::can('projects.view') && !Auth::can('documents.view')) {
                return $sections;
            }
            $projectId = (int) ($project['id'] ?? 0);
            if ($projectId < 1) {
                return $sections;
            }
            $decomptes = (new HonoraireRepository())->contextCounts($projectId);
            $sections[] = [
                'key' => 'honoraires-bareme',
                'label' => __('honoraire.title'),
                'meta' => __('honoraire.context_meta', ['count' => (string) $decomptes]),
                'url' => url('/honoraires/' . $projectId),
                'icon' => 'currency',
                'tone' => 'neutral',
            ];

            return $sections;
        });
    }
}
