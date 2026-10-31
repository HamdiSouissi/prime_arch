<?php
declare(strict_types=1);

namespace Tests;

use App\Controllers\RecordCustomFieldController;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

/**
 * Champs personnalisés rappelés depuis la fiche client ou projet.
 *
 * Un champ créé puis désactivé disparaissait de partout : seule la page des réglages le
 * montrait encore. La fiche rappelle désormais les définitions en attente et permet de les
 * activer, ou d'en créer une, sans quitter la fiche. L'activation reste une modification de
 * schéma : mêmes droits que les réglages, même journal.
 *
 * Tests statiques et de calcul pur (sans base de données).
 */
final class CustomFieldPickerTest extends TestCase
{
    private static function file(string $relativePath): string
    {
        $contents = file_get_contents(BASE_PATH . $relativePath);
        self::assertIsString($contents, 'Fichier introuvable : ' . $relativePath);

        return $contents;
    }

    /** Appelle un helper privé statique du contrôleur (clé technique, ordre d'affichage). */
    private static function helper(string $name, mixed ...$arguments): mixed
    {
        $method = new ReflectionMethod(RecordCustomFieldController::class, $name);
        $method->setAccessible(true);

        return $method->invoke(null, ...$arguments);
    }

    /** Les deux fiches rappellent les définitions inactives, même quand aucun champ n'est actif. */
    public function testInactiveFieldsAreRecalledOnBothRecords(): void
    {
        $projectController = self::file('/app/Controllers/ProjectController.php');
        self::assertStringContainsString("(new CustomFieldRepository())->all('project')", $projectController);
        self::assertStringContainsString("'availableFields' => array_values(array_filter(\$projectFields", $projectController);

        $clientController = self::file('/app/Controllers/ClientController.php');
        self::assertStringContainsString("(new CustomFieldRepository())->all('client')", $clientController);
        self::assertStringContainsString("'availableFields' => array_values(array_filter(\$clientFields", $clientController);

        $projectView = self::file('/app/Views/pages/projects/show.php');
        self::assertStringContainsString("View::partial('partials/custom-field-picker'", $projectView);
        self::assertStringContainsString("'entityType' => 'project'", $projectView);
        self::assertStringContainsString("'recordId' => (int) (\$project['id'] ?? 0)", $projectView);
        // Le bloc apparaît dès qu'on peut configurer, même sans aucun champ : c'est le seul
        // chemin vers la création tant qu'aucune définition n'existe.
        self::assertStringContainsString("|| !empty(\$canConfigure)", $projectView);

        $clientView = self::file('/app/Views/pages/clients/show.php');
        self::assertStringContainsString("View::partial('partials/custom-field-picker'", $clientView);
        self::assertStringContainsString("'entityType' => 'client'", $clientView);
        self::assertStringContainsString("'recordId' => (int) (\$client['id'] ?? 0)", $clientView);
        self::assertStringContainsString("|| !empty(\$canManage)", $clientView);
    }

    /** Le rappel liste libellé, clé et type, avec une activation immédiate par bouton. */
    public function testPendingFieldsAreListedWithTheirKeyAndType(): void
    {
        $partial = self::file('/app/Views/partials/custom-field-picker.php');
        self::assertStringContainsString('custom_fields.available_title', $partial);
        self::assertStringContainsString('custom_fields.available_help', $partial);
        self::assertStringContainsString("'/custom-fields/' . \$availableId . '/activate'", $partial);
        self::assertStringContainsString('csrf_field()', $partial, 'L’activation est un POST protégé.');
        self::assertStringContainsString('custom_fields.activate', $partial);
        self::assertStringContainsString('custom_fields.type_invalid', $partial);
        // Un téléversement n'existe que sur les fiches projets : le sélecteur de type suit.
        self::assertStringContainsString('$isClient ? CustomFieldService::VALUE_TYPES : CustomFieldService::TYPES', $partial);
        // Création rapide : le formulaire ne demande que libellé, type, choix et obligatoire.
        self::assertStringContainsString('name="label"', $partial);
        self::assertStringContainsString('name="field_type"', $partial);
        self::assertStringContainsString('name="options"', $partial);
        self::assertStringNotContainsString('name="field_key"', $partial, 'La clé est calculée côté serveur.');
        self::assertStringNotContainsString('name="entity_type"', $partial);
    }

    /** Chaque action est une route POST avec jeton CSRF et contrôle d'accès dans le contrôleur. */
    public function testQuickAddAndActivationAreGuardedPostRoutes(): void
    {
        $routes = self::file('/config/routes.php');
        foreach ([
            "/clients/{id}/custom-fields",
            "/clients/{id}/custom-fields/{fieldId}/activate",
            "/projects/{id}/custom-fields",
            "/projects/{id}/custom-fields/{fieldId}/activate",
        ] as $route) {
            $line = '';
            foreach (explode("\n", $routes) as $candidate) {
                if (str_contains($candidate, "'" . $route . "'")) {
                    $line = $candidate;
                    break;
                }
            }
            self::assertStringContainsString('RecordCustomFieldController::class', $line, 'Route absente : ' . $route);
            self::assertStringContainsString('CsrfMiddleware::class', $line, 'CSRF manquant : ' . $route);
            self::assertStringContainsString('AuthMiddleware::class', $line, 'Authentification manquante : ' . $route);
        }

        $controller = self::file('/app/Controllers/RecordCustomFieldController.php');
        // Une action par type de fiche, chacune derrière le droit du schéma correspondant.
        self::assertSame(2, substr_count($controller, "Auth::can('clients.manage')"));
        self::assertSame(2, substr_count($controller, "Auth::can('projects.configure')"));
        // Une définition d'un autre type de fiche est traitée comme introuvable.
        self::assertStringContainsString("(string) \$field['entity_type'] !== \$entityType", $controller);
        self::assertStringContainsString('existingClientId($id)', $controller);
        self::assertStringContainsString('existingProjectId($id)', $controller);
    }

    /** La clé technique dérivée du libellé reste lisible, valide et unique. */
    public function testGeneratedKeyIsReadableValidAndUnique(): void
    {
        self::assertSame('typologie_du_projet', self::helper('slug', 'Typologie du projet'));
        self::assertSame('etage', self::helper('slug', 'Étage'));
        self::assertSame('travaux_d_ete', self::helper('slug', "Travaux d’été"));
        self::assertSame('c_3_pieces', self::helper('slug', '3 pièces'));
        // Un libellé entièrement arabe ne produit aucun caractère latin exploitable.
        self::assertSame('', self::helper('slug', 'ملاحظة'));

        self::assertSame('champ', self::helper('generateKey', 'ملاحظة', []));
        self::assertSame(
            'typologie',
            self::helper('generateKey', 'Typologie', [['field_key' => 'champ']])
        );
        self::assertSame(
            'typologie_2',
            self::helper('generateKey', 'Typologie', [['field_key' => 'typologie']])
        );
        self::assertSame(
            'typologie_3',
            self::helper('generateKey', 'Typologie', [['field_key' => 'typologie'], ['field_key' => 'typologie_2']])
        );

        $taken = [];
        for ($index = 0; $index < 5; $index++) {
            $key = (string) self::helper('generateKey', 'Surface du terrain', array_map(
                static fn (string $existing): array => ['field_key' => $existing],
                $taken
            ));
            $taken[] = $key;
        }
        self::assertSame(
            ['surface_du_terrain', 'surface_du_terrain_2', 'surface_du_terrain_3', 'surface_du_terrain_4', 'surface_du_terrain_5'],
            $taken
        );
        foreach ($taken as $key) {
            // Même format que la validation et que les chemins JSON de suppression.
            self::assertSame(1, preg_match('/^[a-z][a-z0-9_]{1,79}$/', $key), 'Clé invalide : ' . $key);
        }
    }

    /** Le nouveau champ se place après les existants, dans les bornes du formulaire complet. */
    public function testNewFieldSortsAfterExistingOnes(): void
    {
        self::assertSame(40, self::helper('nextSortOrder', [['sort_order' => 10], ['sort_order' => 30]]));
        self::assertSame(10, self::helper('nextSortOrder', []));
        self::assertSame(9999, self::helper('nextSortOrder', [['sort_order' => 9999]]));
    }

    /** La création rapide applique les règles des réglages puis active le champ. */
    public function testQuickAddReusesSettingsRulesAndActivatesTheField(): void
    {
        $controller = self::file('/app/Controllers/RecordCustomFieldController.php');
        self::assertStringContainsString('validateDefinition($input)', $controller);
        self::assertStringContainsString("'sort_order' => (string) self::nextSortOrder(\$definitions)", $controller);
        // Mêmes garde-fous que la page de réglages : type absent de la base, clé en double.
        self::assertStringContainsString('acceptedTypes()', $controller);
        self::assertStringContainsString('custom_fields.type_requires_migration', $controller);
        self::assertStringContainsString('custom_fields.key_exists', $controller);
        // Créer n'est pas assez : sans activation la fiche ne montrerait rien de neuf.
        self::assertStringContainsString('$repository->setActive($fieldId, true);', $controller);
        self::assertStringContainsString("'custom_fields.created'", $controller);
        self::assertStringContainsString("'custom_fields.toggled'", $controller);
        self::assertStringContainsString("'source' => 'record'", $controller);
        self::assertStringNotContainsString("input('field_key')", $controller, 'La clé n’est jamais relue du formulaire.');
        self::assertStringNotContainsString("input('entity_type')", $controller);
        self::assertStringNotContainsString("input('sort_order')", $controller);
    }

    /** Retour sur la fiche affichée, au bloc des champs personnalisés, avec un message lisible. */
    public function testFeedbackReturnsToTheRecordCustomFieldsBlock(): void
    {
        $controller = self::file('/app/Controllers/RecordCustomFieldController.php');
        self::assertStringContainsString("'project-custom-title'", $controller);
        self::assertStringContainsString("'client-custom-title'", $controller);
        self::assertStringContainsString("custom_field_notice", $controller);
        self::assertStringContainsString("custom_field_errors", $controller);

        // Les contrôleurs de fiche relisent ces messages pour les afficher dans le bloc.
        self::assertStringContainsString("Session::pullFlash('projects.custom_field_notice')", self::file('/app/Controllers/ProjectController.php'));
        self::assertStringContainsString("Session::pullFlash('clients.custom_field_notice')", self::file('/app/Controllers/ClientController.php'));
    }

    /** Les messages existent en français et en arabe, sans laisser de clé orpheline. */
    public function testMessagesExistInBothLanguages(): void
    {
        $french = require BASE_PATH . '/lang/fr.php';
        $arabic = require BASE_PATH . '/lang/ar.php';
        foreach ([
            'custom_fields.available_title',
            'custom_fields.available_help',
            'custom_fields.activate',
            'custom_fields.activated',
            'custom_fields.quick_add',
            'custom_fields.quick_add_help',
            'custom_fields.quick_add_example',
            'custom_fields.quick_add_submit',
            'custom_fields.quick_added',
        ] as $key) {
            self::assertArrayHasKey($key, $french, 'Clé française absente : ' . $key);
            self::assertArrayHasKey($key, $arabic, 'Clé arabe absente : ' . $key);
            self::assertNotSame('', trim((string) $french[$key]));
            self::assertNotSame('', trim((string) $arabic[$key]));
        }
        // Le compte et le libellé sont interpolés : les deux langues portent les jetons.
        self::assertStringContainsString(':count', (string) $french['custom_fields.available_title']);
        self::assertStringContainsString(':count', (string) $arabic['custom_fields.available_title']);
        self::assertStringContainsString(':label', (string) $french['custom_fields.activated']);
        self::assertStringContainsString(':label', (string) $arabic['custom_fields.activated']);
    }
}
