<?php
declare(strict_types=1);

namespace Tests;

use PHPUnit\Framework\TestCase;

/**
 * Suppression définitive des fiches clients et projets.
 *
 * Trois garanties structurent cette fonctionnalité :
 *
 * 1. **Droits** : seule l'administratrice (joker `*`) et l'architecte détiennent
 *    `clients.delete` / `projects.delete`. Un collaborateur, un comptable ou un profil en
 *    lecture seule ne voit ni la sélection multiple, ni la zone de danger.
 * 2. **Ce qui appartient à la fiche part avec elle** : images privées, plans techniques, phases
 *    affectées et temps saisi d'un projet ; le carnet de contacts d'un client.
 * 3. **Ce qui a une valeur propre est protégé** : une fiche portant des documents (devis,
 *    factures), ou un client portant des projets, n'est pas supprimable — le serveur refuse et
 *    explique, l'interface désactive le bouton.
 *
 * Tests statiques et de configuration, exécutables sans base de données.
 */
final class RecordDeletionTest extends TestCase
{
    private static function file(string $relativePath): string
    {
        $contents = file_get_contents(BASE_PATH . $relativePath);
        self::assertIsString($contents, 'Fichier introuvable : ' . $relativePath);

        return $contents;
    }

    /** @return array<string,array<string,mixed>> */
    private static function roles(): array
    {
        $roles = [];
        foreach (require BASE_PATH . '/database/seeds/roles.php' as $role) {
            $roles[(string) $role['slug']] = $role;
        }

        return $roles;
    }

    /** Seuls l'administrateur (joker) et l'architecte peuvent supprimer une fiche. */
    public function testOnlyAdministratorAndArchitectMayDeleteRecords(): void
    {
        $roles = self::roles();
        self::assertSame(['*'], $roles['administrator']['permissions']);
        self::assertContains('clients.delete', $roles['architect']['permissions']);
        self::assertContains('projects.delete', $roles['architect']['permissions']);
        foreach (['collaborator', 'accountant', 'read_only'] as $slug) {
            self::assertNotContains('clients.delete', $roles[$slug]['permissions'], 'Droit de suppression client accordé à ' . $slug);
            self::assertNotContains('projects.delete', $roles[$slug]['permissions'], 'Droit de suppression projet accordé à ' . $slug);
        }
    }

    /** Les bases déjà installées reçoivent ces droits par migration, sans doublon ni retrait. */
    public function testMigrationGrantsTheRightsIdempotently(): void
    {
        $migration = self::file('/database/migrations/0016_grant_record_delete_permissions.sql');
        self::assertStringContainsString("JSON_ARRAY_APPEND(permissions, '$', 'clients.delete')", $migration);
        self::assertStringContainsString("JSON_ARRAY_APPEND(permissions, '$', 'projects.delete')", $migration);
        self::assertStringContainsString("WHERE name = 'architect'", $migration);
        // Rejouable : la permission n'est ajoutée que si elle manque.
        self::assertSame(2, substr_count($migration, "AND JSON_CONTAINS(permissions, '\"clients.delete\"') = 0")
            + substr_count($migration, "AND JSON_CONTAINS(permissions, '\"projects.delete\"') = 0"));
        self::assertStringContainsString("permissions IS NOT NULL", $migration);
        self::assertStringNotContainsString('DELETE FROM roles', $migration);
        self::assertStringNotContainsString('DROP', $migration);
        // Aucun autre rôle n'est touché : les profils énumèrent leurs capacités une à une.
        self::assertStringNotContainsString("'collaborator'", $migration);
        self::assertStringNotContainsString("'accountant'", $migration);
    }

    /** Quatre routes POST protégées par session et jeton CSRF, déclarées avant `/{id}`. */
    public function testDeletionRoutesAreGuardedPostRoutes(): void
    {
        $routes = self::file('/config/routes.php');
        foreach ([
            "/clients/delete" => 'deleteMany',
            "/clients/{id}/delete" => 'destroy',
            "/projects/delete" => 'deleteMany',
            "/projects/{id}/delete" => 'destroy',
        ] as $route => $action) {
            $line = '';
            foreach (explode("\n", $routes) as $candidate) {
                if (str_contains($candidate, "'" . $route . "'") && str_contains($candidate, $action)) {
                    $line = $candidate;
                    break;
                }
            }
            self::assertStringContainsString('AuthMiddleware::class', $line, 'Authentification manquante : ' . $route);
            self::assertStringContainsString('CsrfMiddleware::class', $line, 'CSRF manquant : ' . $route);
        }

        // `/clients/delete` doit précéder `/clients/{id}`, sinon « delete » serait lu comme un id.
        self::assertLessThan(
            strpos($routes, "'/clients/{id}'"),
            strpos($routes, "'/clients/delete'"),
            'La route de suppression en masse doit précéder /clients/{id}.'
        );
        self::assertLessThan(
            strpos($routes, "'/projects/{id}'"),
            strpos($routes, "'/projects/delete'"),
            'La route de suppression en masse doit précéder /projects/{id}.'
        );
    }

    /** Chaque point d'entrée vérifie le droit dédié, avant même de lire l'identifiant. */
    public function testControllersCheckTheDedicatedPermission(): void
    {
        $clients = self::file('/app/Controllers/ClientController.php');
        // 1 dans la liste, 2 sur la fiche (zone de danger + contenu), 2 dans les actions.
        self::assertSame(5, substr_count($clients, "Auth::can('clients.delete')"), 'Index, fiche, destroy() et deleteMany() contrôlent le droit.');
        self::assertStringContainsString('function destroy(Request $request, string $id)', $clients);
        self::assertStringContainsString('function deleteMany(Request $request)', $clients);
        self::assertStringContainsString("'clients.deleted'", $clients);
        self::assertStringContainsString("'source' => 'selection'", $clients);

        $projects = self::file('/app/Controllers/ProjectController.php');
        self::assertSame(5, substr_count($projects, "Auth::can('projects.delete')"));
        self::assertStringContainsString('function destroy(Request $request, string $id)', $projects);
        self::assertStringContainsString('function deleteMany(Request $request)', $projects);
        self::assertStringContainsString("'projects.deleted'", $projects);

        // L'interface ne propose l'action qu'aux profils autorisés.
        foreach (['clients', 'projects'] as $entity) {
            $index = self::file('/app/Views/pages/' . $entity . '/index.php');
            self::assertStringContainsString('<?php if ($canDelete): ?>', $index, 'Colonne de sélection non conditionnée : ' . $entity);
            $show = self::file('/app/Views/pages/' . $entity . '/show.php');
            self::assertStringContainsString('<?php if ($canDelete): ?>', $show, 'Zone de danger non conditionnée : ' . $entity);
        }
    }

    /** Un client qui porte des projets ou des documents est conservé, avec les compteurs. */
    public function testClientDeletionIsRefusedWithDependants(): void
    {
        $service = self::file('/app/Services/RecordDeletionService.php');
        self::assertStringContainsString("'blocked' => \$projects > 0 || \$documents > 0", $service);
        self::assertStringContainsString('return [\'deleted\' => false] + $impact;', $service);
        self::assertStringContainsString('$this->clientRepository()->delete($clientId)', $service);

        // Le carnet de contacts part avec la fiche, jamais les projets ni les documents.
        $repository = self::file('/app/Models/ClientRepository.php');
        self::assertStringContainsString('DELETE FROM client_contacts WHERE client_id = :client_id', $repository);
        self::assertStringContainsString('DELETE FROM clients WHERE id = :id', $repository);
        self::assertStringNotContainsString('DELETE FROM projects', $repository);
        self::assertStringNotContainsString('DELETE FROM documents', $repository);
    }

    /** Un projet sans document emporte ses fichiers, ses phases et son temps saisi. */
    public function testProjectDeletionRemovesItsOwnContentOnly(): void
    {
        $service = self::file('/app/Services/RecordDeletionService.php');
        self::assertStringContainsString("'blocked' => \$documents > 0", $service);
        // Le contenu est mesuré (impact) avant que quoi que ce soit ne soit supprimé.
        self::assertLessThan(
            strpos($service, '$impact[\'images\'] = $this->imageRepository()->deleteForProject($projectId);'),
            strpos($service, "'images' => count(\$this->imageRepository()->allForProject(\$projectId))"),
            'Le contenu doit être mesuré avant d’être supprimé.'
        );
        // Les fichiers quittent le disque avant leurs métadonnées.
        self::assertLessThan(
            strpos($service, '$impact[\'images\'] = $this->imageRepository()->deleteForProject($projectId);'),
            strpos($service, '$this->imageStorageService()->remove($projectId'),
            'Les fichiers doivent être retirés avant les métadonnées.'
        );
        self::assertStringContainsString('$this->imageStorageService()->remove($projectId', $service);
        self::assertStringContainsString('$this->fileStorageService()->remove($projectId', $service);
        // Une seule transaction pour les dépendances et la fiche.
        self::assertStringContainsString('$pdo->beginTransaction();', $service);
        self::assertStringContainsString('$pdo->rollBack();', $service);
        self::assertStringContainsString('$this->projects->delete($projectId)', str_replace('->projectRepository()', '->projects', $service));

        foreach ([
            '/app/Models/ProjectRepository.php' => 'DELETE FROM project_phase_assignments WHERE project_id = :project_id',
            '/app/Models/TimeEntryRepository.php' => 'DELETE FROM time_entries WHERE project_id = :project_id',
            '/app/Models/ProjectImageRepository.php' => 'DELETE FROM project_images WHERE project_id = :project_id',
            '/app/Models/ProjectFileRepository.php' => 'DELETE FROM project_files WHERE project_id = :project_id',
        ] as $file => $statement) {
            self::assertStringContainsString($statement, self::file($file), 'Suppression manquante : ' . $file);
        }
        // Chaque suppression reste bornée au projet concerné.
        self::assertSame(1, substr_count(self::file('/app/Models/ProjectRepository.php'), 'DELETE FROM projects WHERE id = :id'));
    }

    /** La sélection multiple est bornée, dédoublonnée et n'agit que sur des fiches existantes. */
    public function testSelectionIsBoundedAndDeduplicated(): void
    {
        foreach (['/app/Controllers/ClientController.php', '/app/Controllers/ProjectController.php'] as $file) {
            $controller = self::file($file);
            self::assertStringContainsString('private static function selectedIds(mixed $raw, int $limit = 200)', $controller);
            self::assertStringContainsString("filter_var(is_scalar(\$value) ? (string) \$value : '', FILTER_VALIDATE_INT)", $controller);
            self::assertStringContainsString('!in_array((int) $id, $ids, true)', $controller);
            self::assertStringContainsString('if (count($ids) >= $limit)', $controller);
            // Une fiche protégée n'annule pas les suppressions déjà faites.
            self::assertStringContainsString('$blocked++;', $controller);
        }
    }

    /** Le tableau propose tout sélectionner, un compteur vivant et une action désactivée à vide. */
    public function testSelectionToolbarIsProgressive(): void
    {
        foreach (['clients', 'projects'] as $entity) {
            $index = self::file('/app/Views/pages/' . $entity . '/index.php');
            self::assertStringContainsString('<form class="bulk-form" method="post"', $index);
            self::assertStringContainsString('csrf_field()', $index);
            self::assertStringContainsString('name="ids[]"', $index);
            self::assertStringContainsString('data-select-all', $index);
            self::assertStringContainsString('data-selection-count', $index);
            self::assertStringContainsString('data-selection-submit disabled', $index);
            self::assertStringContainsString('data-confirm-selection', $index);
        }

        $javascript = self::file('/public/assets/js/ui.js');
        self::assertStringContainsString('function initSelectionTools()', $javascript);
        self::assertStringContainsString('initSelectionTools();', $javascript);
        // Une sélection vide ne part pas, et le message annonce le nombre réel.
        self::assertStringContainsString("if (selected === 0) {", $javascript);
        self::assertStringContainsString('message.replace(":count", String(selected))', $javascript);
        self::assertStringContainsString('toggleAll.indeterminate = selected > 0 && selected < boxes.length;', $javascript);
    }

    /** La fiche annonce ce qui sera supprimé, et désactive l'action quand elle est bloquée. */
    public function testDangerZoneExplainsConsequencesBeforeDeleting(): void
    {
        $clients = self::file('/app/Views/pages/clients/show.php');
        self::assertStringContainsString("__('records.danger_zone')", $clients);
        self::assertStringContainsString("'/delete'", $clients);
        self::assertStringContainsString("data-confirm=", $clients);
        self::assertStringContainsString("!empty(\$deleteImpact['blocked']) ? 'disabled' : ''", $clients);

        $projects = self::file('/app/Views/pages/projects/show.php');
        self::assertStringContainsString("__('records.danger_zone')", $projects);
        self::assertStringContainsString("!empty(\$deleteImpact['blocked']) ? 'disabled' : ''", $projects);

        // Le contenu est mesuré côté serveur, jamais déduit du formulaire.
        self::assertStringContainsString("'deleteImpact' => Auth::can('clients.delete')", self::file('/app/Controllers/ClientController.php'));
        self::assertStringContainsString("'deleteImpact' => Auth::can('projects.delete')", self::file('/app/Controllers/ProjectController.php'));
    }

    /** Les messages existent en français et en arabe, avec leurs jetons d'interpolation. */
    public function testMessagesExistInBothLanguages(): void
    {
        $french = require BASE_PATH . '/lang/fr.php';
        $arabic = require BASE_PATH . '/lang/ar.php';
        $keys = [
            'records.danger_zone', 'common.select_all', 'common.selection_count', 'common.delete_selection',
            'clients.delete', 'clients.delete_help', 'clients.delete_scope', 'clients.delete_confirm',
            'clients.delete_blocked', 'clients.deleted', 'clients.deleted_count', 'clients.delete_partial',
            'clients.delete_all_blocked', 'clients.delete_none', 'clients.delete_failed',
            'clients.select_record', 'clients.delete_selection_confirm',
            'projects.delete', 'projects.delete_help', 'projects.delete_scope', 'projects.delete_confirm',
            'projects.delete_blocked', 'projects.deleted', 'projects.deleted_count', 'projects.delete_partial',
            'projects.delete_all_blocked', 'projects.delete_none', 'projects.delete_failed',
            'projects.select_record', 'projects.delete_selection_confirm',
            'audit.event.clients.deleted', 'audit.event.projects.deleted',
        ];
        foreach ($keys as $key) {
            self::assertArrayHasKey($key, $french, 'Clé française absente : ' . $key);
            self::assertArrayHasKey($key, $arabic, 'Clé arabe absente : ' . $key);
            self::assertNotSame('', trim((string) $french[$key]));
            self::assertNotSame('', trim((string) $arabic[$key]));
        }
        // Les jetons d'interpolation sont présents dans les deux langues.
        foreach (['clients.delete_blocked' => [':projects', ':documents'], 'projects.delete_blocked' => [':documents']] as $key => $tokens) {
            foreach ($tokens as $token) {
                self::assertStringContainsString($token, (string) $french[$key]);
                self::assertStringContainsString($token, (string) $arabic[$key]);
            }
        }
        self::assertStringContainsString(':count', (string) $french['common.selection_count']);
        self::assertStringContainsString(':count', (string) $arabic['common.selection_count']);
    }
}
