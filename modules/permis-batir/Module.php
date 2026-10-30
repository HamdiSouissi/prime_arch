<?php
declare(strict_types=1);

namespace Modules\PermisBatir;

use App\Core\Auth;
use App\Core\HookManager;
use App\Core\Router;
use App\Middleware\AuthMiddleware;
use App\Middleware\CsrfMiddleware;
use App\Models\SearchRepository;
use Modules\PermisBatir\Models\DossierRepository;
use Modules\PermisBatir\Controllers\DossierController;
use Modules\PermisBatir\Services\PermisContext;
use Throwable;

/**
 * Module « permis-batir » : dossiers de permis de bâtir.
 *
 * Le module ne modifie aucune table du cœur : il lit les projets et les clients du noyau
 * et stocke ses données dans ses propres tables `permis_batir_*`.
 */
final class Module
{
    /** Ajoute l'entrée de navigation, avant les réglages, si l'utilisateur peut voir les projets. */
    public function boot(): void
    {
        HookManager::addFilter('sidebar.items', static function (array $items): array {
            if (!Auth::can('projects.view')) {
                return $items;
            }
            $entry = [
                'label' => __('permis.title'),
                'url' => url('/permis'),
                'icon' => 'shield',
                'key' => 'permis-batir',
                'section' => 'production',
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
        PermisContext::register();

        // Recherche globale : le module complète la palette du noyau sans la modifier.
        HookManager::addFilter('search.results', static function (array $groups, string $query = ''): array {
            if (!Auth::can('projects.view') || mb_strlen(trim($query), 'UTF-8') < 2) {
                return $groups;
            }

            try {
                $rows = (new DossierRepository())->search($query);
            } catch (Throwable $error) {
                // Table du module absente ou base indisponible : la palette reste utilisable.
                error_log('Recherche permis-batir indisponible : ' . $error->getMessage());

                return $groups;
            }

            $items = [];
            foreach ($rows as $row) {
                $meta = array_values(array_filter([
                    trim((string) ($row['commune'] ?? '')),
                    trim((string) ($row['project_name'] ?? '')),
                    trim((string) ($row['client_name'] ?? '')),
                ], static fn (string $value): bool => $value !== ''));

                $items[] = [
                    'title' => (string) ($row['reference'] ?? ''),
                    'meta' => implode(' · ', array_unique($meta)),
                    'url' => url('/permis/' . (int) ($row['id'] ?? 0)),
                    'icon' => 'shield',
                    'type' => 'permis',
                ];
            }

            if ($items === []) {
                return $groups;
            }

            $groups[] = [
                'key' => 'permis',
                'label' => __('permis.search_group'),
                'icon' => 'shield',
                'items' => $items,
                'truncated' => count($items) >= SearchRepository::LIMIT,
            ];

            return $groups;
        });
    }

    /** Déclare les routes du module ; l'ordre compte, `/permis/new` précède `/permis/{id}`. */
    public function routes(Router $router): void
    {
        $auth = [AuthMiddleware::class];
        $write = [AuthMiddleware::class, CsrfMiddleware::class];

        $router->get('/permis', [DossierController::class, 'index'], $auth);
        $router->get('/permis/export-csv', [DossierController::class, 'exportCsv'], $auth);
        $router->get('/permis/new', [DossierController::class, 'createForm'], $auth);
        $router->post('/permis', [DossierController::class, 'create'], $write);
        $router->get('/permis/{id}', [DossierController::class, 'show'], $auth);
        $router->get('/permis/{id}/edit', [DossierController::class, 'editForm'], $auth);
        $router->post('/permis/{id}', [DossierController::class, 'update'], $write);
        $router->post('/permis/{id}/pieces/{pieceId}', [DossierController::class, 'togglePiece'], $write);
        $router->post('/permis/{id}/evenements', [DossierController::class, 'addEvent'], $write);
    }

    /** Exécuté une seule fois, après les migrations d'activation. Aucun jeu de données imposé. */
    public function install(): void
    {
    }

    /** Réservé à une future désinstallation explicite ; jamais appelé à la désactivation. */
    public function uninstall(): void
    {
    }
}
