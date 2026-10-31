<?php
declare(strict_types=1);

namespace Tests;

use App\Services\CustomFieldService;
use App\Services\ProjectFileStorage;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * Champ personnalisé « fichiers AutoCAD » des fiches projets.
 *
 * Un tel champ se crée dans Réglages › Champs personnalisés, puis s'alimente depuis la fiche
 * projet : envoi, téléchargement et retrait d'un fichier DWG, DXF, DWT, DWS, BAK, SV$, DWF,
 * DWFX ou PDF, sans jamais plus de trois actions. Les fichiers ne sont pas stockés dans la colonne JSON des champs personnalisés ni
 * sous `public/` : ils vivent dans `storage/uploads/projects`, référencés par `project_files`
 * et servis uniquement par une route authentifiée.
 *
 * Les tests de validation et de reconnaissance de format sont unitaires ; les autres sont
 * statiques (lecture du code livré), donc exécutables sans base de données ni envoi de fichier.
 */
final class CustomFieldFileTest extends TestCase
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
            'field_key' => 'plans_autocad',
            'label' => 'Plans AutoCAD',
            'field_type' => 'file',
            'options' => '',
            'is_required' => '0',
            'sort_order' => '20',
        ], $overrides));
    }

    /** Écrit un fichier temporaire et renvoie son chemin. */
    private static function fixture(string $contents): string
    {
        $path = tempnam(sys_get_temp_dir(), 'primearch-cad-');
        self::assertIsString($path);
        file_put_contents($path, $contents);

        return $path;
    }

    /** Le type « fichier » est proposé, accepté pour un projet et refusé ailleurs. */
    public function testFileTypeIsAcceptedForProjectsOnly(): void
    {
        $project = self::definition();
        self::assertSame([], $project['errors']);
        self::assertSame('file', $project['data']['field_type']);
        self::assertSame([], $project['data']['options'], 'Un champ fichier n’a pas d’options.');

        self::assertContains('file', CustomFieldService::TYPES, 'Le type doit être proposé dans les réglages.');
        foreach (['text', 'number', 'date', 'select', 'checkbox', 'image'] as $type) {
            self::assertContains($type, CustomFieldService::TYPES, 'Les types existants restent disponibles.');
        }

        $client = self::definition(['entity_type' => 'client']);
        self::assertArrayHasKey('field_type', $client['errors'], 'Les fiches clients n’ont pas d’espace privé pour ces plans.');
    }

    /** Un champ fichier n'est jamais « obligatoire » : les plans arrivent après la création. */
    public function testFileFieldCanNeverBeRequired(): void
    {
        $required = self::definition(['is_required' => '1']);
        self::assertSame([], $required['errors']);
        self::assertFalse($required['data']['is_required']);

        $text = self::definition(['field_type' => 'text', 'is_required' => '1']);
        self::assertTrue($text['data']['is_required'], 'Les autres types conservent le comportement demandé.');
    }

    /** Les deux types à téléversement sont écartés de la colonne JSON et des exports CSV. */
    public function testUploadTypesStayOutOfJsonAndCsv(): void
    {
        $service = self::file('/app/Services/CustomFieldService.php');
        self::assertStringContainsString("public const UPLOAD_TYPES = ['image', 'file'];", $service);
        self::assertStringContainsString("in_array(\$field['field_type'], self::UPLOAD_TYPES, true)", $service);
        self::assertStringContainsString('unset($values[$key]);', $service);
        self::assertStringContainsString('FILE_MAX_PER_UPLOAD', $service);
        self::assertStringContainsString('FILE_MAX_PER_FIELD', $service);

        $csv = self::file('/app/Services/CsvDataTransferService.php');
        self::assertStringContainsString("['image', 'file']", $csv, 'Un CSV ne transporte pas de fichier binaire.');
    }

    /** L'en-tête du fichier décide de la famille de contenu : une extension seule ne suffit pas. */
    public function testFormatIsDetectedFromFileSignature(): void
    {
        // DWG et ses variantes : tous les fichiers AutoCAD binaires portent « AC10xx ».
        foreach (['dwg' => 'plan.dwg', 'dwt' => 'gabarit.dwt', 'dws' => 'normes.dws',
            'bak' => 'plan.bak', 'sv$' => 'plan_1_2_3456.sv$'] as $extension => $name) {
            $file = self::fixture("AC1027\x00\x00\x00\x00\x0d\x0a" . str_repeat("\x00", 120));
            self::assertSame('acad', ProjectFileStorage::detectFormat($file), 'En-tête AutoCAD reconnu : ' . $name);
            self::assertSame('acad', ProjectFileStorage::familyOf($extension), 'Famille attendue pour .' . $extension);
            @unlink($file);
        }

        // DXF texte : deux premières lignes utiles « 0 » puis « SECTION ».
        $dxf = self::fixture("0\r\nSECTION\r\n2\r\nHEADER\r\n0\r\nENDSEC\r\n0\r\nEOF\r\n");
        self::assertSame('dxf', ProjectFileStorage::detectFormat($dxf), 'DXF texte reconnu.');
        @unlink($dxf);

        // DXF binaire : signature explicite d'AutoCAD.
        $binaryDxf = self::fixture("AutoCAD Binary DXF\r\n\x1a\x00" . str_repeat("\x00", 32));
        self::assertSame('dxf', ProjectFileStorage::detectFormat($binaryDxf), 'DXF binaire reconnu.');
        @unlink($binaryDxf);

        // DWF 6+ et DWFX sont des conteneurs ZIP ; DWF 5 et antérieurs sont binaires.
        $dwf = self::fixture("PK\x03\x04" . str_repeat("\x00", 64));
        self::assertSame('dwf', ProjectFileStorage::detectFormat($dwf), 'DWF/DWFX (ZIP) reconnu.');
        self::assertSame('dwf', ProjectFileStorage::familyOf('dwfx'));
        @unlink($dwf);

        $legacyDwf = self::fixture('(DWF V06.00)P' . str_repeat('\x00', 32));
        self::assertSame('dwf', ProjectFileStorage::detectFormat($legacyDwf), 'DWF binaire historique reconnu.');
        @unlink($legacyDwf);

        // PDF : signature « %PDF- ».
        $pdf = self::fixture("%PDF-1.7\n1 0 obj\n<< /Type /Catalog >>\nendobj\n%%EOF\n");
        self::assertSame('pdf', ProjectFileStorage::detectFormat($pdf), 'PDF reconnu.');
        self::assertSame('pdf', ProjectFileStorage::familyOf('pdf'));
        @unlink($pdf);

        // Un exécutable, un fichier vide ou un texte quelconque restent refusés.
        $executable = self::fixture("MZ\x90\x00" . str_repeat("\x00", 64));
        self::assertNull(ProjectFileStorage::detectFormat($executable), 'Un exécutable renommé est refusé.');
        @unlink($executable);

        $text = self::fixture("Bonjour, ceci n'est pas un plan.\n" . str_repeat('ligne\n', 20));
        self::assertNull(ProjectFileStorage::detectFormat($text), 'Un texte quelconque est refusé.');
        @unlink($text);

        $empty = self::fixture('');
        self::assertNull(ProjectFileStorage::detectFormat($empty), 'Un fichier vide est refusé.');
        @unlink($empty);

        // Une extension acceptée sans famille connue ne peut jamais correspondre à un contenu.
        self::assertNull(ProjectFileStorage::familyOf('exe'));
    }

    /** Extensions autorisées et bornes de taille sont explicites, côté service. */
    public function testStorageLimitsAreExplicit(): void
    {
        self::assertSame(
            ['dwg', 'dxf', 'dwt', 'dws', 'bak', 'dwf', 'dwfx', 'pdf', 'sv$'],
            ProjectFileStorage::EXTENSIONS,
            'Plans AutoCAD, gabarits, normes, sauvegardes, DWF/DWFX et PDF.'
        );
        self::assertSame(33_554_432, ProjectFileStorage::MAX_BYTES, '32 Mio par fichier.');
        self::assertSame('application/octet-stream', ProjectFileStorage::DOWNLOAD_MIME);
        self::assertSame('dwg', ProjectFileStorage::extensionOf('Plan RDC.DWG'));
        self::assertSame('dxf', ProjectFileStorage::extensionOf('chemin/facade.dxf'));
        self::assertSame('sv$', ProjectFileStorage::extensionOf('plan_1_2_3456.SV$'), 'Le dollar fait partie de l’extension AutoCAD.');
        self::assertSame('dwt', ProjectFileStorage::extensionOf('gabarit.dwt'));
        self::assertSame('', ProjectFileStorage::extensionOf('plan'));
        // Seule la liste blanche décide : une extension inconnue ne passe jamais la validation.
        self::assertSame('exe', ProjectFileStorage::extensionOf('plan.dwg.exe'));
        self::assertNotContains('exe', ProjectFileStorage::EXTENSIONS);

        $storage = self::file('/app/Services/ProjectFileStorage.php');
        self::assertStringContainsString('is_uploaded_file', $storage, 'Seul un envoi HTTP est accepté.');
        self::assertStringContainsString('filesize', $storage, 'La taille réelle est vérifiée, pas celle annoncée.');
        self::assertStringContainsString('random_bytes(16)', $storage, 'Le nom stocké est aléatoire.');
        self::assertStringContainsString('self::familyOf($extension)', $storage, 'Le contenu doit appartenir à la famille de l’extension.');
        self::assertStringContainsString('private const FAMILIES', $storage);
        self::assertStringContainsString('chmod($destination, 0640)', $storage, 'Lecture réservée au serveur.');
        self::assertStringContainsString("BASE_PATH . '/storage/uploads/projects'", $storage);
        self::assertStringNotContainsString('/public/', $storage, 'Aucun fichier n’est écrit dans la racine publique.');
        self::assertStringContainsString('hash_equals', $storage, 'Le contenu servi est vérifié par empreinte.');
        self::assertStringContainsString('is_link', $storage, 'Les liens symboliques du stockage sont refusés.');
    }

    /** Le fichier est référencé par une table dédiée, liée au projet et au champ. */
    public function testMigrationCreatesProjectFilesTable(): void
    {
        $migration = self::file('/database/migrations/0015_custom_field_file_uploads.sql');
        self::assertStringContainsString('CREATE TABLE IF NOT EXISTS project_files', $migration);
        self::assertStringContainsString('custom_field_key VARCHAR(80) NOT NULL', $migration);
        self::assertStringContainsString('stored_name VARCHAR(64) NOT NULL UNIQUE', $migration);
        self::assertStringContainsString('checksum CHAR(64) NOT NULL', $migration);
        self::assertStringContainsString('ON DELETE CASCADE', $migration, 'La suppression du projet emporte ses plans.');
        self::assertStringContainsString('information_schema.COLUMNS', $migration);
        self::assertStringContainsString('PREPARE', $migration);
        self::assertStringNotContainsString('DROP ', $migration, 'Aucune donnée existante n’est supprimée.');
        // L'ENUM doit accepter les deux types à téléversement, sinon la création du champ échoue.
        self::assertStringContainsString("''image''", $migration);
        self::assertStringContainsString("''file''", $migration);
        self::assertStringNotContainsString('DELIMITER', $migration, 'Le migrateur découpe les instructions lui-même.');
    }

    /** Dépôt : requêtes préparées, rattachées au projet et à sa clé de champ. */
    public function testRepositoryScopesFilesToProjectAndField(): void
    {
        $repository = self::file('/app/Models/ProjectFileRepository.php');
        self::assertStringContainsString('function groupedByCustomField', $repository);
        self::assertStringContainsString('function countForCustomField', $repository);
        self::assertStringContainsString('function findForCustomField', $repository);
        self::assertStringContainsString('function findForProject', $repository);
        self::assertStringContainsString('WHERE id = :id AND project_id = :project_id', $repository);
        self::assertStringContainsString('AND custom_field_key = :field_key', $repository);
        self::assertStringContainsString('custom_field_key', $repository);
        self::assertStringContainsString('PDOException', $repository, 'Une base non migrée ne casse pas la fiche projet.');
        self::assertStringNotContainsString('query("', $repository, 'Aucune requête concaténée.');
    }

    /** Envoi, retrait et téléchargement passent par des routes privées et protégées. */
    public function testRoutesAreAuthenticatedAndFieldScoped(): void
    {
        $routes = self::file('/config/routes.php');
        self::assertStringContainsString(
            "\$router->post('/projects/{id}/custom-fields/{field}/files', [ProjectFileController::class, 'upload'], [AuthMiddleware::class, CsrfMiddleware::class])",
            $routes
        );
        self::assertStringContainsString(
            "\$router->post('/projects/{id}/custom-fields/{field}/files/{fileId}/delete', [ProjectFileController::class, 'delete'], [AuthMiddleware::class, CsrfMiddleware::class])",
            $routes
        );
        self::assertStringContainsString(
            "\$router->get('/projects/{id}/files/{fileId}', [ProjectFileController::class, 'download'], [AuthMiddleware::class])",
            $routes,
            'Le téléchargement exige une session : aucun accès public au stockage.'
        );
        self::assertStringNotContainsString("'/storage/", $routes, 'Aucune route ne sert le dossier de stockage.');

        $controller = self::file('/app/Controllers/ProjectFileController.php');
        self::assertStringContainsString("Auth::can('projects.manage')", $controller);
        self::assertStringContainsString("Auth::can('projects.view')", $controller);
        self::assertStringContainsString("['field_type'] === 'file'", $controller, 'Seul un champ « fichier » actif accepte un plan.');
        self::assertStringContainsString('preg_match(\'/^[a-z][a-z0-9_]{1,79}$/\'', $controller);
        self::assertStringContainsString('->remove($projectId', $controller, 'Le fichier est retiré si l’enregistrement échoue.');
        self::assertStringContainsString("'projects.file.uploaded'", $controller);
        self::assertStringContainsString("'projects.file.deleted'", $controller);
        self::assertStringContainsString("'attachment'", $controller, 'Un plan n’est jamais affiché dans le navigateur.');
    }

    /** La fiche projet affiche la liste des plans, un envoi et un retour sur l'ancre du champ. */
    public function testProjectPageListsFilesAndUploadsThem(): void
    {
        $show = self::file('/app/Views/pages/projects/show.php');
        self::assertStringContainsString('partials/project-custom-files', $show);
        self::assertStringContainsString('$fileFields', $show);
        self::assertStringContainsString('CustomFieldService::VALUE_TYPES', $show, 'Un champ fichier n’est pas rendu comme une valeur texte.');

        $partial = self::file('/app/Views/partials/project-custom-files.php');
        self::assertStringContainsString('enctype="multipart/form-data"', $partial);
        self::assertStringContainsString('name="files[]"', $partial);
        self::assertStringContainsString('multiple', $partial);
        self::assertStringContainsString('ProjectFileStorage::EXTENSIONS', $partial, 'Les extensions acceptées viennent du service.');
        self::assertStringContainsString('id="<?= e($anchor) ?>"', $partial, 'Chaque bloc porte une ancre pour le retour après envoi.');
        self::assertStringContainsString('/delete', $partial);
        self::assertStringContainsString('data-confirm=', $partial);
        self::assertStringContainsString('csrf_field()', $partial);
        self::assertStringContainsString('file_download', $partial);
        // Toute sortie dynamique est échappée, et aucun lien ne pointe vers le stockage privé.
        self::assertStringNotContainsString('<?= $file[', $partial);
        self::assertStringNotContainsString('<?= $field[', $partial);
        self::assertStringNotContainsString('storage/', $partial);

        // Le formulaire de la fiche renvoie vers la fiche enregistrée, sans input fichier.
        $inputs = self::file('/app/Views/partials/custom-field-inputs.php');
        self::assertStringContainsString("['image', 'file']", $inputs);
        self::assertStringNotContainsString('type="file"', $inputs);
    }

    /** Les réglages proposent le type et rappellent sa portée. */
    public function testSettingsOfferTheFileType(): void
    {
        $settings = self::file('/app/Views/pages/settings/custom-fields.php');
        self::assertStringContainsString('CustomFieldService::TYPES', $settings);
        self::assertStringContainsString("custom_fields.type.", $settings);
        self::assertStringContainsString('custom_fields.file_help', $settings);
        // Les rappels et le sélecteur de type vivent désormais dans la carte partagée.
        $card = self::file('/app/Views/partials/custom-field-definition.php');
        self::assertStringContainsString("=== 'file' ? 'file' : 'image') . '_not_required'", $card);
        self::assertStringContainsString("=== 'file' ? 'files' : 'images') . '_after_save'", $card);
    }

    /** Le diagnostic d'installation nomme les objets manquants de la fiche projet. */
    public function testPreflightReportsMissingSchema(): void
    {
        $preflight = self::file('/bin/preflight.php');
        self::assertStringContainsString('Schéma de la fiche projet', $preflight);
        self::assertStringContainsString("'project_files'", $preflight);
        self::assertStringContainsString('project_images', $preflight);
        self::assertStringContainsString('project_phase_assignments', $preflight);
        self::assertStringContainsString('migration 0015', $preflight);
    }

    /** Les deux langues couvrent le type, ses messages et le journal d'audit. */
    public function testTranslationsCoverFilesInBothLanguages(): void
    {
        $keys = [
            'custom_fields.type.file',
            'custom_fields.file_help',
            'custom_fields.file_projects_only',
            'custom_fields.files_after_save',
            'custom_fields.files_open_project',
            'custom_fields.file_not_required',
            'custom_fields.file_deactivated_note',
            'projects.custom_file_help',
            'projects.custom_file_empty',
            'projects.custom_file_select',
            'projects.custom_file_upload',
            'projects.custom_file_uploaded',
            'projects.custom_file_deleted',
            'projects.custom_file_missing',
            'projects.custom_file_delete',
            'projects.custom_file_delete_confirm',
            'projects.custom_file_batch_limit',
            'projects.custom_file_field_limit',
            'projects.file_download',
            'projects.file_required',
            'projects.file_invalid',
            'projects.file_format_invalid',
            'projects.file_extension_mismatch',
            'projects.file_upload_failed',
            'projects.file_storage_failed',
            'projects.file_upload_help',
            'projects.file_size_kb',
            'projects.file_size_mb',
            'audit.event.projects.file.uploaded',
            'audit.event.projects.file.deleted',
            'custom_fields.type_requires_migration',
            'custom_fields.type_invalid',
            'custom_fields.type_invalid_hint',
            'custom_fields.type_invalid_field',
        ];
        foreach (['fr', 'ar'] as $locale) {
            $file = self::file('/lang/' . $locale . '.php');
            foreach ($keys as $key) {
                self::assertStringContainsString("'" . $key . "' =>", $file, $locale . ' : ' . $key);
            }
        }
    }

    /** Une base non migrée ne peut pas fabriquer un champ au type vide (zone de texte). */
    public function testTypeIsCheckedAgainstTheSchemaBeforeCreation(): void
    {
        $repository = self::file('/app/Models/CustomFieldRepository.php');
        self::assertStringContainsString('function acceptedTypes', $repository);
        self::assertStringContainsString("information_schema.COLUMNS", $repository);
        self::assertStringContainsString('function updateDefinition', $repository, 'Un type vide peut être corrigé depuis la modification.');
        self::assertStringContainsString('field_type = :field_type', $repository);

        $controller = self::file('/app/Controllers/CustomFieldController.php');
        self::assertStringContainsString('->acceptedTypes()', $controller);
        self::assertStringContainsString("custom_fields.type_requires_migration", $controller);
        // Le contrôle a lieu avant l'écriture : aucun champ inutilisable n'est créé.
        $guard = strpos($controller, 'acceptedTypes()');
        $insert = strpos($controller, '->create($validated');
        self::assertTrue($guard !== false && $insert !== false && $guard < $insert, 'Le contrôle précède l’insertion.');

        $service = self::file('/app/Services/CustomFieldService.php');
        self::assertStringContainsString('public const VALUE_TYPES', $service);
        self::assertStringContainsString('function isKnownType', $service);
        self::assertStringContainsString('function isUploadType', $service);
    }

    /** Un type inconnu n'est jamais rendu comme une zone de texte. */
    public function testUnknownTypeNeverRendersAsTextInput(): void
    {
        $inputs = self::file('/app/Views/partials/custom-field-inputs.php');
        self::assertStringContainsString('CustomFieldService::VALUE_TYPES', $inputs, 'Seuls les types saisis produisent un champ.');
        self::assertStringContainsString('custom_fields.type_invalid_hint', $inputs);
        // L'input typé n'est atteint que par la branche des types saisis : la garde précède.
        $guard = strpos($inputs, 'CustomFieldService::VALUE_TYPES');
        $typedInput = strpos($inputs, "type=\"<?= e(\$field['field_type']) ?>\"");
        self::assertTrue($guard !== false && $typedInput !== false && $guard < $typedInput, 'La garde des types précède l’input.');

        $show = self::file('/app/Views/pages/projects/show.php');
        self::assertStringContainsString('CustomFieldService::VALUE_TYPES', $show);
        self::assertStringContainsString('$invalidFieldTypes', $show, 'Les définitions cassées sont signalées.');

        $card = self::file('/app/Views/partials/custom-field-definition.php');
        self::assertStringContainsString('CustomFieldService::isKnownType', $card);
        self::assertStringContainsString('custom_fields.type_invalid', $card);
        self::assertStringContainsString('name="field_type"', $card, 'Le type peut être corrigé depuis les réglages.');

        $preflight = self::file('/bin/preflight.php');
        self::assertStringContainsString("'Champs personnalisés'", $preflight, 'Le diagnostic liste les champs cassés.');
        self::assertStringContainsString("field_type NOT IN ('text', 'number', 'date', 'select', 'checkbox', 'image', 'file')", $preflight);
    }
}
