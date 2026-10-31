<?php
declare(strict_types=1);

namespace Tests;

use App\Services\CustomFieldService;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * Cycle de vie des champs personnalisés : modification et suppression.
 *
 * Une définition ne vit jamais seule. Les types saisis stockent leur valeur dans la colonne JSON
 * `custom_fields` de chaque fiche ; les types « image » et « fichier » dans `project_images` et
 * `project_files`, avec les fichiers privés correspondants. Modifier ou supprimer un champ doit
 * donc traiter ce contenu : le retirer réellement, jamais le laisser orphelin.
 *
 * Les tests sont statiques (lecture du code livré) et exécutables sans base de données.
 */
final class CustomFieldLifecycleTest extends TestCase
{
    private static function file(string $relativePath): string
    {
        $contents = file_get_contents(BASE_PATH . $relativePath);
        self::assertIsString($contents, 'Fichier introuvable : ' . $relativePath);

        return $contents;
    }

    /** Service sans dépôt PDO : `validateDefinition()` est pure. */
    private static function service(): CustomFieldService
    {
        return (new ReflectionClass(CustomFieldService::class))->newInstanceWithoutConstructor();
    }

    /** @param array<string,mixed> $overrides
     *  @return array<string,mixed>
     */
    private static function definition(array $overrides = []): array
    {
        return self::service()->validateDefinition(array_merge([
            'entity_type' => 'project',
            'field_key' => 'typologie',
            'label' => 'Typologie du projet',
            'field_type' => 'select',
            'options' => "villa\nappartement",
            'is_required' => '0',
            'sort_order' => '10',
        ], $overrides));
    }

    /** La modification est une vraie mise à jour de la définition, pas un simple renommage. */
    public function testDefinitionCanBeEditedWithAllItsAttributes(): void
    {
        $repository = self::file('/app/Models/CustomFieldRepository.php');
        self::assertStringContainsString('function updateDefinition', $repository);
        self::assertStringContainsString('SET label = :label, field_type = :field_type, options_json = :options_json', $repository);
        self::assertStringContainsString('is_required = :is_required, sort_order = :sort_order', $repository);

        $controller = self::file('/app/Controllers/CustomFieldController.php');
        self::assertStringContainsString('->updateDefinition((int) $fieldId, $validated', $controller);
        // Le formulaire de modification réutilise les règles de création (options, types, obligatoire).
        self::assertStringContainsString('validateDefinition($input)', $controller);

        // Les options d'une liste restent validées : une liste sans choix est refusée.
        $empty = self::definition(['options' => '']);
        self::assertArrayHasKey('options', $empty['errors']);
        $ok = self::definition(['label' => 'Typologie rénovée', 'sort_order' => '30']);
        self::assertSame([], $ok['errors']);
        self::assertSame('Typologie rénovée', $ok['data']['label']);
    }

    /** La clé technique et le type de fiche ne changent jamais : ils portent le stockage. */
    public function testTechnicalKeyAndEntityStayFixed(): void
    {
        $controller = self::file('/app/Controllers/CustomFieldController.php');
        self::assertStringContainsString("'field_key' => (string) \$field['field_key']", $controller);
        self::assertStringContainsString("'entity_type' => (string) \$field['entity_type']", $controller);
        self::assertStringNotContainsString("input('field_key')", $controller, 'La clé n’est jamais relue depuis le formulaire.');
        self::assertStringNotContainsString("input('entity_type')", $controller);
    }

    /** Changer de type est refusé dès que le champ contient des données. */
    public function testTypeChangeIsRefusedWhenTheFieldHasContent(): void
    {
        $controller = self::file('/app/Controllers/CustomFieldController.php');
        self::assertStringContainsString('$typeChanged = $type !== $currentType', $controller);
        self::assertStringContainsString('custom_fields.type_change_blocked', $controller);
        self::assertStringContainsString('$impact[\'total\'] > 0', $controller, 'Le refus dépend du contenu réel.');

        $partial = self::file('/app/Views/partials/custom-field-definition.php');
        self::assertStringContainsString('custom_fields.type_change_locked', $partial);
        self::assertStringContainsString('custom_fields.type_change_hint', $partial);
        self::assertStringContainsString('name="field_type"', $partial, 'Le type reste modifiable tant que le champ est vide.');
    }

    /** Suppression : route POST protégée, permission existante, journalisation. */
    public function testDeletionRouteIsProtectedAndAudited(): void
    {
        $routes = self::file('/config/routes.php');
        self::assertStringContainsString(
            "\$router->post('/settings/custom-fields/{id}/delete', [CustomFieldController::class, 'destroy'], [AuthMiddleware::class, CsrfMiddleware::class])",
            $routes
        );
        self::assertStringNotContainsString("\$router->get('/settings/custom-fields/{id}/delete'", $routes, 'La suppression n’est jamais un GET.');

        $controller = self::file('/app/Controllers/CustomFieldController.php');
        self::assertStringContainsString('public function destroy', $controller);
        self::assertStringContainsString('$this->authorizeEntity($field[\'entity_type\'])', $controller);
        self::assertStringContainsString("'custom_fields.deleted'", $controller);
        self::assertStringContainsString("'values_deleted' => \$impact['values']", $controller);
        self::assertStringContainsString("'files_deleted' => \$impact['files']", $controller);
    }

    /** La suppression emporte les fichiers privés, leurs métadonnées et les valeurs saisies. */
    public function testDeletionRemovesFilesMetadataAndValues(): void
    {
        $service = self::file('/app/Services/CustomFieldLifecycleService.php');
        // Ordre prudent : fichiers du disque, puis métadonnées, puis valeurs JSON, puis définition.
        $storageCall = strpos($service, '$this->imageStorage()->remove(');
        $metadataCall = strpos($service, '->deleteForCustomField($key)');
        $jsonCall = strpos($service, '->removeStoredValues(');
        $definitionCall = strpos($service, '->delete($fieldId)');
        self::assertTrue(
            $storageCall !== false && $metadataCall !== false && $jsonCall !== false && $definitionCall !== false,
            'Les quatre étapes de la suppression sont présentes.'
        );
        self::assertTrue($storageCall < $metadataCall, 'Le fichier privé part avant ses métadonnées.');
        self::assertTrue($metadataCall < $definitionCall, 'La définition part en dernier.');

        $images = self::file('/app/Models/ProjectImageRepository.php');
        self::assertStringContainsString('function allForCustomField', $images);
        self::assertStringContainsString('function deleteForCustomField', $images);
        self::assertStringContainsString('WHERE custom_field_key = :field_key', $images);

        $files = self::file('/app/Models/ProjectFileRepository.php');
        self::assertStringContainsString('function allForCustomField', $files);
        self::assertStringContainsString('function deleteForCustomField', $files);

        $imagesStorage = self::file('/app/Services/ProjectImageStorage.php');
        self::assertStringContainsString('function remove', $imagesStorage);
        $filesStorage = self::file('/app/Services/ProjectFileStorage.php');
        self::assertStringContainsString('function remove', $filesStorage);
    }

    /** Les valeurs JSON sont retirées par chemin, sur une table en liste blanche. */
    public function testJsonValuesAreRemovedByPath(): void
    {
        $repository = self::file('/app/Models/CustomFieldRepository.php');
        self::assertStringContainsString('JSON_REMOVE(custom_fields, :path)', $repository);
        self::assertStringContainsString("JSON_CONTAINS_PATH(custom_fields, 'one', :presence)", $repository);
        self::assertStringContainsString("'client' => 'clients'", $repository);
        self::assertStringContainsString("'project' => 'projects'", $repository);
        self::assertStringContainsString("preg_match('/^[a-z][a-z0-9_]{1,79}$/', \$fieldKey)", $repository, 'Le chemin JSON est construit sur une clé validée.');
        // Deux paramètres distincts : PDO n'accepte pas un nom réutilisé.
        self::assertStringNotContainsString(":presence, :presence", $repository);
    }

    /** L'interface montre le contenu du champ et demande confirmation avant de supprimer. */
    public function testSettingsCardOffersEditAndConfirmedDeletion(): void
    {
        $partial = self::file('/app/Views/partials/custom-field-definition.php');
        self::assertStringContainsString('csrf_field()', $partial);
        self::assertStringContainsString('data-confirm=', $partial);
        self::assertStringContainsString('/delete', $partial);
        self::assertStringContainsString('custom_fields.delete_confirm', $partial);
        self::assertStringContainsString('custom_fields.usage', $partial, 'Le contenu actuel est annoncé sur la carte.');
        self::assertStringContainsString('name="options"', $partial, 'Les choix d’une liste sont modifiables.');
        self::assertStringContainsString('name="label"', $partial);
        self::assertStringContainsString('name="sort_order"', $partial);
        self::assertStringContainsString('button--danger', $partial);
        // Toute sortie dynamique est échappée.
        self::assertStringNotContainsString('<?= $field[', $partial);
        self::assertStringNotContainsString('<?= $impact[', $partial);

        $settings = self::file('/app/Views/pages/settings/custom-fields.php');
        self::assertSame(2, substr_count($settings, "partials/custom-field-definition"), 'Les deux groupes utilisent la même carte.');
        self::assertStringContainsString('$impacts', $settings);

        $controller = self::file('/app/Controllers/CustomFieldController.php');
        self::assertStringContainsString('$impacts[(int) $definition[\'id\']] = $lifecycle->impact($definition)', $controller);
    }

    /** Les deux langues couvrent la modification, la suppression et le journal. */
    public function testTranslationsCoverModificationAndDeletion(): void
    {
        $keys = [
            'custom_fields.delete',
            'custom_fields.delete_confirm',
            'custom_fields.deleted',
            'custom_fields.delete_failed',
            'custom_fields.usage',
            'custom_fields.type_change_hint',
            'custom_fields.type_change_locked',
            'custom_fields.type_change_blocked',
            'audit.event.custom_fields.deleted',
        ];
        foreach (['fr', 'ar'] as $locale) {
            $file = self::file('/lang/' . $locale . '.php');
            foreach ($keys as $key) {
                self::assertStringContainsString("'" . $key . "' =>", $file, $locale . ' : ' . $key);
            }
        }
    }
}
