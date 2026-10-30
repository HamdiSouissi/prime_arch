<?php
declare(strict_types=1);

namespace Modules\GedVersions\Services;

use App\Core\Auth;
use App\Core\HookManager;
use Modules\GedVersions\Models\GedRepository;

/**
 * Passerelles « fiche projet / fiche client → GED ».
 *
 * Une seule requête de comptage par fiche ; aucune donnée de fichier n'est exposée ici.
 */
final class GedContext
{
    public static function register(): void
    {
        $visible = static fn (): bool => Auth::can('documents.view') || Auth::can('documents.manage')
            || Auth::can('projects.view') || Auth::can('clients.view');

        HookManager::addFilter('project.detail.sections', static function (array $sections, array $project) use ($visible): array {
            if (!$visible()) {
                return $sections;
            }
            $projectId = (int) ($project['id'] ?? 0);
            if ($projectId < 1) {
                return $sections;
            }
            $count = (new GedRepository())->contextCount('project_id', $projectId);
            $sections[] = [
                'key' => 'ged-versions',
                'label' => __('ged.title'),
                'meta' => __('ged.context_meta', ['count' => (string) $count]),
                'url' => url('/ged?projet=' . $projectId),
                'icon' => 'folder',
                'tone' => 'neutral',
            ];

            return $sections;
        });

        HookManager::addFilter('client.detail.sections', static function (array $sections, array $client) use ($visible): array {
            if (!$visible()) {
                return $sections;
            }
            $clientId = (int) ($client['id'] ?? 0);
            if ($clientId < 1) {
                return $sections;
            }
            $count = (new GedRepository())->contextCount('client_id', $clientId);
            $sections[] = [
                'key' => 'ged-versions',
                'label' => __('ged.title'),
                'meta' => __('ged.context_meta', ['count' => (string) $count]),
                'url' => url('/ged?client=' . $clientId),
                'icon' => 'folder',
                'tone' => 'neutral',
            ];

            return $sections;
        });
    }
}
