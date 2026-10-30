<?php
declare(strict_types=1);

namespace Modules\RegistreCourrier\Services;

use App\Core\Auth;
use App\Core\HookManager;
use Modules\RegistreCourrier\Models\CourrierRepository;

/**
 * Passerelles « fiche projet → courrier » et « fiche client → courrier » (lot 1 de l’audit).
 *
 * Deux informations seulement : le nombre de courriers et combien restent à traiter.
 * Le lien ouvre le registre déjà filtré, sans créer d’écran intermédiaire : la règle des
 * trois clics est respectée depuis n’importe quelle fiche.
 */
final class CourrierContext
{
    public static function register(): void
    {
        HookManager::addFilter('project.detail.sections', static function (array $sections, array $project): array {
            if (!Auth::can('clients.view')) {
                return $sections;
            }
            $projectId = (int) ($project['id'] ?? 0);
            if ($projectId < 1) {
                return $sections;
            }

            return self::append($sections, 'courrier-project-' . $projectId, static fn (CourrierRepository $repo): ?array => $repo->projectSummary($projectId), 'projet', $projectId);
        });

        HookManager::addFilter('client.detail.sections', static function (array $sections, array $client): array {
            if (!Auth::can('clients.view')) {
                return $sections;
            }
            $clientId = (int) ($client['id'] ?? 0);
            if ($clientId < 1) {
                return $sections;
            }

            return self::append($sections, 'courrier-client-' . $clientId, static fn (CourrierRepository $repo): ?array => $repo->clientSummary($clientId), 'client', $clientId);
        });
    }

    /**
     * @param array<int,array<string,mixed>> $sections
     * @param callable(CourrierRepository):(?array{total:int,open:int}) $summary
     * @return array<int,array<string,mixed>>
     */
    private static function append(array $sections, string $key, callable $summary, string $filter, int $id): array
    {
        try {
            $data = $summary(new CourrierRepository());
        } catch (\Throwable) {
            // Table du module absente ou base indisponible : la fiche du noyau reste intacte.
            return $sections;
        }
        if ($data === null) {
            return $sections;
        }

        $sections[] = [
            'key' => $key,
            'label' => __('courrier.title'),
            'meta' => __('courrier.context.meta', [
                'total' => (string) $data['total'],
                'open' => (string) $data['open'],
            ]),
            'url' => url('/courrier?' . $filter . '=' . $id),
            'icon' => 'mail',
            'tone' => $data['open'] > 0 ? 'warning' : 'info',
        ];

        return $sections;
    }
}
