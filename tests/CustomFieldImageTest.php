<?php
declare(strict_types=1);

namespace Tests;

use App\Services\CustomFieldService;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * Champ personnalisé de type « image » sur les fiches projets.
 *
 * Un tel champ permet d'ajouter une ou plusieurs images à un projet. Les fichiers ne sont pas
 * stockés dans la colonne JSON des champs personnalisés : ils vivent dans `project_images`,
 * rattachés au champ par sa clé technique, si bien qu'il n'existe qu'une seule source de
 * vérité (et que la suppression d'un projet emporte ses images par la clé étrangère).
 *
 * Les tests de validation sont unitaires ; les autres sont statiques (lecture du code livré),
 * donc exécutables sans base de données ni envoi de fichier.
 */
final class CustomFieldImageTest extends TestCase
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
        $definition = array_merge([
            'entity_type' => 'project',
            'field_key' => 'photos_chantier',
            'label' => 'Photos de chantier',
            'field_type' => 'image',
            'options' => '',
            'is_required' => '0',
            'sort_order' => '10',
        ], $overrides);

        return self::service()->validateDefinition($definition);
    }

    /** Le type « image » est accepté pour un projet et refusé partout ailleurs. */
    public function testImageTypeIsAcceptedForProjectsOnly(): void
    {
        $project = self::definition();
        self::assertSame([], $project['errors']);
        self::assertSame('image', $project['data']['field_type']);

        $client = self::definition(['entity_type' => 'client']);
        self::assertArrayHasKey('field_type', $client['errors'], 'Les fiches clients n’ont pas d’espace privé pour ces images.');

        // Le type reste dans la liste proposée par les réglages.
        self::assertContains('image', CustomFieldService::TYPES);
        foreach (['text', 'number', 'date', 'select', 'checkbox'] as $type) {
            self::assertContains($type, CustomFieldService::TYPES, 'Les types existants restent disponibles.');
        }
    }

    /** Un champ image n'est jamais « obligatoire » : les images arrivent après la création. */
    public function testImageFieldCanNeverBeRequired(): void
    {
        $required = self::definition(['is_required' => '1']);
        self::assertSame([], $required['errors']);
        self::assertFalse($required['data']['is_required']);

        // Les autres types conservent le comportement demandé.
        $text = self::definition(['field_type' => 'text', 'is_required' => '1']);
        self::assertTrue($text['data']['is_required']);
    }

    /** Aucune valeur JSON n'est conservée pour un champ image (une seule source de vérité). */
    public function testImageFieldsStoreNoJsonValue(): void
    {
        $service = self::file('/app/Services/CustomFieldService.php');

        // Le bloc à téléversement écarte la clé de la colonne JSON au lieu d'attendre une valeur
        // texte : les images et les plans AutoCAD n'y ont jamais de valeur.
        self::assertStringContainsString("in_array(\$field['field_type'], self::UPLOAD_TYPES, true)", $service);
        self::assertStringContainsString("public const UPLOAD_TYPES = ['image', 'file'];", $service);
        self::assertStringContainsString('unset($values[$key]);', $service);
        self::assertStringContainsString('$values = $existing;', $service);
        // La borne haute et le type sont exposés par le service, pas dupliqués dans les vues.
        self::assertStringContainsString('IMAGE_MAX_PER_UPLOAD', $service);
        self::assertStringContainsString('IMAGE_MAX_PER_FIELD', $service);
    }

    /** Les images sont rattachées au champ par une colonne dédiée, ajoutée de façon idempotente. */
    public function testImagesCarryTheirFieldKey(): void
    {
        $repository = self::file('/app/Models/ProjectImageRepository.php');
        self::assertStringContainsString('custom_field_key', $repository);
        self::assertStringContainsString('function groupedByCustomField', $repository);
        self::assertStringContainsString('function countForCustomField', $repository);
        self::assertStringContainsString('function findForCustomField', $repository);
        self::assertStringContainsString('custom_field_key IS NULL', $repository, 'La carte « Images du projet » ne montre que les images générales.');

        $migration = self::file('/database/migrations/0014_attach_project_images_to_custom_fields.sql');
        self::assertStringContainsString('ADD COLUMN custom_field_key VARCHAR(80) NULL', $migration);
        self::assertStringContainsString('information_schema.COLUMNS', $migration);
        self::assertStringContainsString('information_schema.STATISTICS', $migration);
        self::assertStringContainsString('IF(', $migration, 'La migration doit être rejouable sans erreur.');
        self::assertStringNotContainsString('DROP ', $migration, 'Aucune donnée existante n’est supprimée.');
    }

    /** Envoi et suppression passent par des routes protégées, liées à un champ de type image. */
    public function testUploadAndDeleteRoutesAreProtectedAndFieldScoped(): void
    {
        $routes = self::file('/config/routes.php');
        self::assertStringContainsString(
            "\$router->post('/projects/{id}/custom-fields/{field}/images'",
            $routes
        );
        self::assertStringContainsString(
            "\$router->post('/projects/{id}/custom-fields/{field}/images/{imageId}/delete'",
            $routes
        );
        self::assertSame(
            2,
            substr_count($routes, 'ProjectImageController::class, \'uploadForField\'')
            + substr_count($routes, 'ProjectImageController::class, \'deleteForField\''),
            'Les deux routes d’images personnalisées sont déclarées.'
        );

        $controller = self::file('/app/Controllers/ProjectImageController.php');
        // Droits du noyau, aucune nouvelle permission.
        self::assertStringContainsString("Auth::can('projects.manage')", $controller);
        self::assertStringContainsString("Auth::can('projects.view')", $controller);
        // Le champ doit être une définition active de type image.
        self::assertStringContainsString("['field_type'] === 'image'", $controller);
        self::assertStringContainsString('preg_match(\'/^[a-z][a-z0-9_]{1,79}$/\'', $controller);
        // Le fichier est retiré si l'enregistrement échoue, et l'action est journalisée.
        self::assertStringContainsString('->remove($projectId', $controller);
        self::assertStringContainsString("'projects.image.deleted'", $controller);
        self::assertStringContainsString("'custom_field_key' => \$key", $controller);
    }

    /** La fiche projet affiche la galerie du champ et un envoi multiple. */
    public function testProjectPageRendersGalleryAndMultiFileUpload(): void
    {
        $show = self::file('/app/Views/pages/projects/show.php');
        self::assertStringContainsString("partials/project-custom-images", $show);
        self::assertStringContainsString('$imageFields', $show);
        self::assertStringContainsString('$valueFields', $show, 'Les champs image ne sont pas rendus comme des valeurs texte.');

        $partial = self::file('/app/Views/partials/project-custom-images.php');
        self::assertStringContainsString('enctype="multipart/form-data"', $partial);
        self::assertStringContainsString('name="images[]"', $partial);
        self::assertStringContainsString('multiple', $partial);
        self::assertStringContainsString('accept="image/jpeg,image/png,image/webp"', $partial);
        self::assertStringContainsString('id="<?= e($anchor) ?>"', $partial, 'Chaque bloc porte une ancre pour le retour après envoi.');
        self::assertStringContainsString('/delete', $partial);
        self::assertStringContainsString('data-confirm=', $partial);
        self::assertStringContainsString('csrf_field()', $partial);
        // Toute sortie dynamique est échappée.
        self::assertStringNotContainsString('<?= $image[', $partial);
        self::assertStringNotContainsString('<?= $field[', $partial);

        // Le formulaire de la fiche explique où ajouter les images, sans input fichier.
        $inputs = self::file('/app/Views/partials/custom-field-inputs.php');
        self::assertStringContainsString("['image', 'file']", $inputs);
        self::assertStringContainsString("custom_fields.' . (\$isFileField ? 'files_after_save' : 'images_after_save')", $inputs);
        self::assertStringNotContainsString('type="file"', $inputs);
    }

    /** Les réglages proposent le type et rappellent sa portée. */
    public function testSettingsOfferTheImageType(): void
    {
        $settings = self::file('/app/Views/pages/settings/custom-fields.php');
        self::assertStringContainsString('CustomFieldService::TYPES', $settings);
        self::assertStringContainsString("custom_fields.type.", $settings);
        // Le rappel est construit à partir du type : images ou plans AutoCAD.
        self::assertStringContainsString("=== 'file' ? 'files' : 'images') . '_after_save'", $settings);
        self::assertStringContainsString("=== 'file' ? 'file' : 'image') . '_not_required'", $settings);
    }

    /** Un CSV ne transporte pas de binaire : les champs image en sont écartés. */
    public function testImageFieldsAreExcludedFromCsvTransfer(): void
    {
        $csv = self::file('/app/Services/CsvDataTransferService.php');
        self::assertStringContainsString('array_filter(', $csv);
        self::assertStringContainsString("['image', 'file']", $csv, 'Les champs à téléversement ne partent pas en CSV.');
        self::assertStringContainsString('ne peut pas être importé', $csv);
    }

    /** Les deux langues couvrent le nouveau type et ses messages. */
    public function testTranslationsCoverImagesInBothLanguages(): void
    {
        $keys = [
            'custom_fields.type.image',
            'custom_fields.image_help',
            'custom_fields.image_projects_only',
            'custom_fields.images_after_save',
            'custom_fields.images_open_project',
            'custom_fields.image_not_required',
            'custom_fields.image_deactivated_note',
            'projects.custom_image_help',
            'projects.custom_image_empty',
            'projects.custom_image_select',
            'projects.custom_image_upload',
            'projects.custom_image_uploaded',
            'projects.custom_image_deleted',
            'projects.custom_image_missing',
            'projects.custom_image_delete',
            'projects.custom_image_delete_confirm',
            'projects.custom_image_batch_limit',
            'projects.custom_image_field_limit',
            'audit.event.projects.image.deleted',
        ];
        foreach (['fr', 'ar'] as $locale) {
            $file = self::file('/lang/' . $locale . '.php');
            foreach ($keys as $key) {
                self::assertStringContainsString("'" . $key . "' =>", $file, $locale . ' : ' . $key);
            }
        }
    }
}
