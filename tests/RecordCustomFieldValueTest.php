<?php
declare(strict_types=1);

namespace Tests;

use App\Core\Translator;
use App\Core\View;
use App\Services\CustomFieldService;
use PHPUnit\Framework\TestCase;

/**
 * Valeurs des champs personnalisés sur la fiche client ou projet.
 *
 * Deux garanties :
 *
 * 1. **Un champ créé reste visible** : tous les champs actifs apparaissent sur la fiche,
 *    même sans valeur (« Non renseigné »). Auparavant, un champ fraîchement créé depuis la
 *    fiche était bien enregistré, mais invisible tant qu'aucune valeur n'était saisie.
 * 2. **Il se renseigne sur place** : chaque ligne propose un formulaire réduit qui valide
 *    cette seule valeur, sans exiger celles des autres champs obligatoires.
 *
 * Tests unitaires (normalisation) et rendu réel du partiel, sans base de données.
 */
final class RecordCustomFieldValueTest extends TestCase
{
    private static function file(string $relativePath): string
    {
        $contents = file_get_contents(BASE_PATH . $relativePath);
        self::assertIsString($contents, 'Fichier introuvable : ' . $relativePath);

        return $contents;
    }

    /** @param array<string,mixed> $field */
    private static function normalize(array $field, mixed $raw): array
    {
        $method = new \ReflectionMethod(CustomFieldService::class, 'normalizeValue');

        return $method->invoke(null, $field + ['field_key' => 'champ', 'options' => [], 'is_required' => false], $raw);
    }

    public function testTextValuesAreTrimmedAndEmptyOptionalValuesAreCleared(): void
    {
        $text = ['field_type' => 'text'];
        self::assertSame(['action' => 'set', 'value' => 'Villa', 'error' => null], self::normalize($text, '  Villa  '));
        self::assertSame('unset', self::normalize($text, '   ')['action'], 'Un champ facultatif vide est effacé.');
        self::assertSame('error', self::normalize($text + ['is_required' => true], '')['action'], 'Un champ obligatoire vide est refusé.');
        self::assertSame('error', self::normalize($text, str_repeat('a', 2001))['action']);
    }

    public function testTypedValuesAreValidatedLikeTheFullForm(): void
    {
        self::assertSame(['action' => 'set', 'value' => 12.5, 'error' => null], self::normalize(['field_type' => 'number'], '12.5'));
        self::assertSame('error', self::normalize(['field_type' => 'number'], '12,5')['action'], 'La virgule décimale n’est pas acceptée dans ce champ.');
        self::assertSame('error', self::normalize(['field_type' => 'number'], 'douze')['action']);
        self::assertSame('set', self::normalize(['field_type' => 'date'], '2026-02-28')['action']);
        self::assertSame('error', self::normalize(['field_type' => 'date'], '2026-02-30')['action']);
        self::assertSame('set', self::normalize(['field_type' => 'select', 'options' => ['Villa', 'Bureaux']], 'Villa')['action']);
        self::assertSame('error', self::normalize(['field_type' => 'select', 'options' => ['Villa']], 'Chalet')['action']);
        self::assertSame(['action' => 'set', 'value' => true, 'error' => null], self::normalize(['field_type' => 'checkbox'], '1'));
        self::assertSame(['action' => 'set', 'value' => false, 'error' => null], self::normalize(['field_type' => 'checkbox'], '0'));
        self::assertSame('error', self::normalize(['field_type' => 'checkbox', 'is_required' => true], '0')['action']);
    }

    public function testUploadFieldsNeverCarryAJsonValue(): void
    {
        self::assertSame('unset', self::normalize(['field_type' => 'file'], 'plan.dwg')['action']);
        self::assertSame('unset', self::normalize(['field_type' => 'image'], 'photo.jpg')['action']);
        self::assertSame('error', self::normalize(['field_type' => 'type_inconnu'], 'x')['action'], 'Un type inconnu n’est jamais deviné.');
    }

    public function testSingleFieldValidationIgnoresOtherRequiredFields(): void
    {
        $service = self::file('/app/Services/CustomFieldService.php');
        self::assertStringContainsString('public function validateFieldValue(', $service);
        self::assertStringContainsString(
            "if ((string) \$candidate['field_key'] === \$fieldKey)",
            $service,
            'Seule la définition demandée est contrôlée.'
        );
        self::assertStringContainsString(
            'foreach ($this->fields->all($entityType, true) as $candidate)',
            $service
        );
        self::assertStringContainsString("return ['values' => \$values, 'errors' => ['field' => __('custom_fields.unknown_field')]];", $service);
        self::assertStringContainsString("__('custom_fields.upload_field_not_editable')", $service);
    }

    public function testRecordPageRendersEveryActiveFieldIncludingEmptyOnes(): void
    {
        // Les libellés affichés sont ceux des fichiers de langue, pas les clés techniques.
        Translator::setLocale('fr');
        $fields = [
            ['field_key' => 'typologie', 'label' => 'Typologie', 'field_type' => 'select', 'options' => ['Villa', 'Bureaux'], 'is_required' => false],
            ['field_key' => 'surface', 'label' => 'Surface (m²)', 'field_type' => 'number', 'options' => [], 'is_required' => false],
            ['field_key' => 'permis_obtenu', 'label' => 'Permis obtenu', 'field_type' => 'checkbox', 'options' => [], 'is_required' => false],
        ];

        ob_start();
        View::partial('partials/record-custom-fields', [
            'entityType' => 'project',
            'recordId' => 12,
            'fields' => $fields,
            'values' => ['typologie' => 'Villa'],
            'canEdit' => true,
        ]);
        $html = (string) ob_get_clean();

        // Les trois champs sont présents, y compris ceux sans valeur.
        self::assertStringContainsString('id="custom-field-typologie"', $html);
        self::assertStringContainsString('id="custom-field-surface"', $html);
        self::assertStringContainsString('id="custom-field-permis_obtenu"', $html);
        self::assertStringContainsString('Villa', $html);
        self::assertStringContainsString('Non renseigné', $html, 'Une valeur absente est annoncée, pas omise.');
        self::assertStringContainsString('Renseigner', $html);
        self::assertStringContainsString('Modifier', $html);
        self::assertStringContainsString('/projects/12/custom-fields/surface/value', $html);
        self::assertStringContainsString('name="value"', $html);
        self::assertStringContainsString('button--primary', $html);
    }

    public function testEmptyStateAndReadOnlyMode(): void
    {
        Translator::setLocale('fr');
        ob_start();
        View::partial('partials/record-custom-fields', [
            'entityType' => 'client',
            'recordId' => 3,
            'fields' => [['field_key' => 'segment', 'label' => 'Segment', 'field_type' => 'text', 'options' => [], 'is_required' => false]],
            'values' => [],
            'canEdit' => false,
        ]);
        $html = (string) ob_get_clean();
        self::assertStringContainsString('Non renseigné', $html);
        self::assertStringNotContainsString('custom-value__form', $html, 'Sans droit d’écriture, aucun formulaire.');

        ob_start();
        View::partial('partials/record-custom-fields', [
            'entityType' => 'client',
            'recordId' => 3,
            'fields' => [],
            'values' => [],
            'canEdit' => true,
        ]);
        $empty = (string) ob_get_clean();
        self::assertStringContainsString('Aucun champ actif', $empty);
    }

    public function testShowPagesUseTheSharedPartial(): void
    {
        $project = self::file('/app/Views/pages/projects/show.php');
        self::assertStringContainsString("partials/record-custom-fields", $project);
        self::assertStringContainsString("'fields' => \$valueFields", $project);
        self::assertStringContainsString("'canEdit' => !empty(\$canManage)", $project);
        self::assertStringNotContainsString(
            "if (\$value !== null && \$value !== ''): ?><div class=\"detail-list__item\"",
            $project,
            'Les champs vides ne sont plus filtrés à l’affichage.'
        );

        $client = self::file('/app/Views/pages/clients/show.php');
        self::assertStringContainsString("partials/record-custom-fields", $client);
        self::assertStringContainsString("'entityType' => 'client'", $client);
    }

    public function testValueRoutesAreCsrfProtectedAndPermissionChecked(): void
    {
        $routes = self::file('/config/routes.php');
        self::assertStringContainsString(
            "post('/clients/{id}/custom-fields/{field}/value', [RecordCustomFieldController::class, 'saveValueClient'], [AuthMiddleware::class, CsrfMiddleware::class])",
            $routes
        );
        self::assertStringContainsString(
            "post('/projects/{id}/custom-fields/{field}/value', [RecordCustomFieldController::class, 'saveValueProject'], [AuthMiddleware::class, CsrfMiddleware::class])",
            $routes
        );
        self::assertStringNotContainsString("get('/projects/{id}/custom-fields/{field}/value'", $routes, 'Une écriture n’est jamais un GET.');

        $controller = self::file('/app/Controllers/RecordCustomFieldController.php');
        self::assertStringContainsString('public function saveValueProject(', $controller);
        self::assertStringContainsString('public function saveValueClient(', $controller);
        self::assertStringContainsString("Auth::can('projects.manage')", $controller);
        self::assertStringContainsString("Auth::can('clients.manage')", $controller);
        self::assertStringContainsString('validateFieldValue(', $controller);
        self::assertStringContainsString('updateCustomFields(', $controller);
        self::assertStringContainsString("record('custom_fields.value_updated'", $controller);
        self::assertStringContainsString("preg_match('/^[a-z][a-z0-9_]{1,79}\$/', \$key)", $controller, 'La clé reçue est validée avant usage.');
    }

    public function testOnlyCustomFieldValuesAreUpdated(): void
    {
        $tables = ['ProjectRepository' => 'projects', 'ClientRepository' => 'clients'];
        foreach ($tables as $repository => $table) {
            $source = self::file('/app/Models/' . $repository . '.php');
            self::assertStringContainsString('public function updateCustomFields(int $id, array $values): bool', $source);
            self::assertStringContainsString(
                'UPDATE ' . $table . ' SET custom_fields = :custom_fields WHERE id = :id',
                $source,
                'Seule la colonne JSON est modifiée, pas les autres champs de la fiche.'
            );
        }
    }

    public function testQuickAddScrollsToTheNewField(): void
    {
        $controller = self::file('/app/Controllers/RecordCustomFieldController.php');
        self::assertStringContainsString(
            "'custom-field-' . (string) \$validated['data']['field_key']",
            $controller,
            'Après une création rapide, la fiche s’ouvre sur le nouveau champ.'
        );
        self::assertStringContainsString(
            "?string \$anchor = null",
            $controller
        );
    }

    public function testNewLabelsExistInFrenchAndArabic(): void
    {
        $fr = require BASE_PATH . '/lang/fr.php';
        $ar = require BASE_PATH . '/lang/ar.php';
        $keys = [
            'custom_fields.not_filled',
            'custom_fields.none_active',
            'custom_fields.set_value',
            'custom_fields.edit_value',
            'custom_fields.save_value',
            'custom_fields.value_label',
            'custom_fields.checkbox_checked',
            'custom_fields.value_saved',
            'custom_fields.unknown_field',
            'custom_fields.upload_field_not_editable',
            'audit.event.custom_fields.value_updated',
            'audit.detail.field_key',
            'audit.detail.record_id',
        ];
        foreach ($keys as $key) {
            self::assertArrayHasKey($key, $fr, 'Clé française manquante : ' . $key);
            self::assertArrayHasKey($key, $ar, 'Clé arabe manquante : ' . $key);
            self::assertNotSame('', $fr[$key]);
            self::assertNotSame('', $ar[$key]);
        }
    }
}
