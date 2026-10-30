<?php
declare(strict_types=1);

namespace Modules\ConformiteOat;

use App\Core\Auth;
use App\Core\HookManager;
use App\Core\Router;
use App\Middleware\AuthMiddleware;
use App\Middleware\CsrfMiddleware;
use Modules\ConformiteOat\Controllers\ConformiteController;
use Modules\ConformiteOat\Services\ConformiteContext;

/**
 * Module « conformite-oat » : dossier professionnel du cabinet et assurances.
 *
 * Le module ne modifie aucune table du cœur : il lit `projects`, `clients` et `users` et
 * stocke ses données dans ses propres tables `conformite_*`. Il n’affirme aucune validité
 * légale : seules les dates saisies par le cabinet déclenchent les alertes.
 */
final class Module
{
    /** Entrée de navigation avant les réglages, si l’utilisateur peut voir le dossier. */
    public function boot(): void
    {
        HookManager::addFilter('sidebar.items', static function (array $items): array {
            if (!Auth::can('projects.view') && !Auth::can('settings.view')) {
                return $items;
            }
            $entry = [
                'label' => __('conformite.title'),
                'url' => url('/conformite'),
                'icon' => 'check-circle',
                'key' => 'conformite-oat',
                'section' => 'conformite',
            ];
            foreach ($items as $index => $existing) {
                if (($existing['key'] ?? '') === 'settings') {
                    array_splice($items, $index, 0, [$entry]);

                    return $items;
                }
            }
            $items[] = $entry;

            return $items;
        });

        // Passerelle vers la fiche projet / client / document du noyau.
        ConformiteContext::register();
    }

    /** Routes du module ; les chemins fixes sont déclarés avant les routes dynamiques. */
    public function routes(Router $router): void
    {
        $auth = [AuthMiddleware::class];
        $write = [AuthMiddleware::class, CsrfMiddleware::class];

        $router->get('/conformite', [ConformiteController::class, 'index'], $auth);
        $router->get('/conformite/attestations', [ConformiteController::class, 'attestations'], $auth);
        $router->get('/conformite/attestations/{projectId}', [ConformiteController::class, 'attestation'], $auth);

        $router->post('/conformite/inscriptions', [ConformiteController::class, 'storeInscription'], $write);
        $router->post('/conformite/inscriptions/{inscriptionId}/supprimer', [ConformiteController::class, 'deleteInscription'], $write);
        $router->post('/conformite/assurances', [ConformiteController::class, 'storeAssurance'], $write);
        $router->post('/conformite/assurances/{assuranceId}/supprimer', [ConformiteController::class, 'deleteAssurance'], $write);
        $router->post('/conformite/formations', [ConformiteController::class, 'storeFormation'], $write);
        $router->post('/conformite/formations/{formationId}/supprimer', [ConformiteController::class, 'deleteFormation'], $write);
        $router->post('/conformite/partenaires', [ConformiteController::class, 'storePartenaire'], $write);
        $router->post('/conformite/partenaires/{partenaireId}/basculer', [ConformiteController::class, 'togglePartenaire'], $write);
        $router->post('/conformite/partenaires/{partenaireId}/supprimer', [ConformiteController::class, 'deletePartenaire'], $write);
    }

    /** Aucune donnée de conformité préremplie : le cabinet saisit ses propres pièces. */
    public function install(): void
    {
    }

    /** Réservé à une future désinstallation explicite ; jamais appelé à la désactivation. */
    public function uninstall(): void
    {
    }
}
