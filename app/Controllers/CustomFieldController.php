<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\View;
use App\Models\CustomFieldRepository;
use App\Services\AuditLogger;
use App\Services\CustomFieldLifecycleService;
use App\Services\CustomFieldService;
use App\Services\ViewContext;
use PDOException;
use Throwable;

/**
 * Configuration des champs personnalisés des fiches clients et projets.
 */
final class CustomFieldController
{
    public function index(Request $request): Response
    {
        if (!self::canManageAny()) {
            return Response::html(__('common.forbidden'), 403);
        }
        $clientFields = Auth::can('clients.manage') ? (new CustomFieldRepository())->all('client') : [];
        $projectFields = Auth::can('projects.configure') ? (new CustomFieldRepository())->all('project') : [];
        $context = (new ViewContext())->authenticated(__('custom_fields.title'), 'settings');
        $values = Session::pullFlash('custom_fields.values', []);
        // Contenu de chaque champ : la confirmation de suppression annonce ce qui sera perdu.
        $lifecycle = new CustomFieldLifecycleService();
        $impacts = [];
        foreach (array_merge($clientFields, $projectFields) as $definition) {
            $impacts[(int) $definition['id']] = $lifecycle->impact($definition);
        }
        return Response::html(View::render('pages/settings/custom-fields', $context + [
            'clientFields' => $clientFields,
            'projectFields' => $projectFields,
            'canManageClients' => Auth::can('clients.manage'),
            'canManageProjects' => Auth::can('projects.configure'),
            'errors' => Session::pullFlash('custom_fields.errors', []),
            'notice' => Session::pullFlash('custom_fields.notice'),
            'values' => is_array($values) ? $values : [],
            'impacts' => $impacts,
        ]));
    }

    public function create(Request $request): Response
    {
        $input = self::definitionInput($request);
        $validated = (new CustomFieldService())->validateDefinition($input);
        if (!$this->authorizeEntity($validated['data']['entity_type'])) {
            return Response::html(__('common.forbidden'), 403);
        }
        if ($validated['errors'] !== []) {
            Session::flash('custom_fields.errors', $validated['errors']);
            Session::flash('custom_fields.values', $input);
            return Response::redirect(url('/settings/custom-fields'));
        }
        $repository = new CustomFieldRepository();
        // Une base antérieure à la migration 0015 n'accepte ni « image » ni « file » : MySQL
        // enregistrerait un type vide et le champ s'afficherait comme une zone de texte. On
        // refuse la création tant que le schéma n'est pas à jour, avec la marche à suivre.
        $acceptedTypes = $repository->acceptedTypes();
        if ($acceptedTypes !== null
            && !in_array((string) $validated['data']['field_type'], $acceptedTypes, true)
        ) {
            Session::flash('custom_fields.errors', ['field_type' => __('custom_fields.type_requires_migration')]);
            Session::flash('custom_fields.values', $input);
            return Response::redirect(url('/settings/custom-fields'));
        }
        try {
            $fieldId = $repository->create($validated['data']);
        } catch (PDOException $error) {
            if ($error->getCode() === '23000') {
                Session::flash('custom_fields.errors', ['field_key' => __('custom_fields.key_exists')]);
                Session::flash('custom_fields.values', $input);
                return Response::redirect(url('/settings/custom-fields'));
            }
            error_log((string) $error);
            Session::flash('custom_fields.errors', ['general' => __('custom_fields.save_failed')]);
            return Response::redirect(url('/settings/custom-fields'));
        } catch (Throwable $error) {
            error_log((string) $error);
            Session::flash('custom_fields.errors', ['general' => __('custom_fields.save_failed')]);
            return Response::redirect(url('/settings/custom-fields'));
        }
        (new AuditLogger())->record('custom_fields.created', 'custom_field', $fieldId, [
            'entity_type' => (string) $validated['data']['entity_type'],
            'field_type' => (string) $validated['data']['field_type'],
            'is_required' => (bool) $validated['data']['is_required'],
        ], Auth::id());
        Session::flash('custom_fields.notice', __('custom_fields.created'));
        return Response::redirect(url('/settings/custom-fields'));
    }

    /**
     * Modifie une définition existante : libellé, ordre, caractère obligatoire, type et options.
     *
     * La clé technique et le type de fiche restent figés : ils servent de clé de stockage des
     * valeurs. Le type ne peut changer que si le champ ne contient encore rien — sinon les
     * valeurs déjà saisies, ou les fichiers déjà déposés, deviendraient illisibles.
     */
    public function update(Request $request, string $id): Response
    {
        $fieldId = filter_var($id, FILTER_VALIDATE_INT);
        $repository = new CustomFieldRepository();
        $field = $fieldId !== false && $fieldId > 0 ? $repository->findById((int) $fieldId) : null;
        if ($field === null) {
            return Response::html(View::render('pages/404', [], 'layouts/app'), 404);
        }
        if (!$this->authorizeEntity($field['entity_type'])) {
            return Response::html(__('common.forbidden'), 403);
        }

        $currentType = (string) $field['field_type'];
        $typeInput = self::inputString($request, 'field_type');
        $type = $request->input('field_type') !== null && $typeInput !== '' ? $typeInput : $currentType;
        $label = self::inputString($request, 'label');
        $required = in_array($request->input('is_required'), ['1', 1, true, 'on'], true);
        $optionsText = $request->input('options');
        $input = [
            'entity_type' => (string) $field['entity_type'],
            'field_key' => (string) $field['field_key'],
            'label' => $label,
            'field_type' => $type,
            'options' => is_string($optionsText) ? $optionsText : '',
            'is_required' => $required ? '1' : '0',
            'sort_order' => self::inputString($request, 'sort_order'),
        ];
        $validated = (new CustomFieldService())->validateDefinition($input);
        if ($validated['errors'] !== []) {
            Session::flash('custom_fields.errors', $validated['errors']);
            return Response::redirect(url('/settings/custom-fields'));
        }

        $lifecycle = new CustomFieldLifecycleService();
        $typeChanged = $type !== $currentType;
        if ($typeChanged) {
            $impact = $lifecycle->impact($field);
            // Un type vide (base non migrée) n'a jamais rien stocké : sa correction reste possible.
            if ($impact['total'] > 0 && CustomFieldService::isKnownType($currentType)) {
                Session::flash('custom_fields.errors', ['field_type' => __('custom_fields.type_change_blocked', [
                    'count' => (string) $impact['total'],
                ])]);
                return Response::redirect(url('/settings/custom-fields'));
            }
            $acceptedTypes = $repository->acceptedTypes();
            if ($acceptedTypes !== null && !in_array($type, $acceptedTypes, true)) {
                Session::flash('custom_fields.errors', ['field_type' => __('custom_fields.type_requires_migration')]);
                return Response::redirect(url('/settings/custom-fields'));
            }
        }

        try {
            $repository->updateDefinition((int) $fieldId, $validated['data']);
        } catch (Throwable $error) {
            error_log((string) $error);
            Session::flash('custom_fields.errors', ['general' => __('custom_fields.save_failed')]);
            return Response::redirect(url('/settings/custom-fields'));
        }
        (new AuditLogger())->record('custom_fields.updated', 'custom_field', (int) $fieldId, [
            'entity_type' => (string) $field['entity_type'],
            'field_type' => (string) $validated['data']['field_type'],
            'is_required' => (bool) $validated['data']['is_required'],
        ], Auth::id());
        Session::flash('custom_fields.notice', __('custom_fields.updated'));
        return Response::redirect(url('/settings/custom-fields'));
    }

    /**
     * Supprime une définition et tout son contenu.
     *
     * La suppression est définitive : les fichiers privés des champs « image » et « fichier »
     * sont retirés du disque, les valeurs saisies sont retirées de la colonne JSON des fiches.
     * Le nombre d'éléments concernés est rappelé dans la confirmation côté formulaire.
     */
    public function destroy(Request $request, string $id): Response
    {
        $fieldId = filter_var($id, FILTER_VALIDATE_INT);
        $repository = new CustomFieldRepository();
        $field = $fieldId !== false && $fieldId > 0 ? $repository->findById((int) $fieldId) : null;
        if ($field === null) {
            return Response::html(View::render('pages/404', [], 'layouts/app'), 404);
        }
        if (!$this->authorizeEntity($field['entity_type'])) {
            return Response::html(__('common.forbidden'), 403);
        }

        try {
            $impact = (new CustomFieldLifecycleService())->delete($field);
        } catch (Throwable $error) {
            error_log((string) $error);
            Session::flash('custom_fields.errors', ['general' => __('custom_fields.delete_failed')]);
            return Response::redirect(url('/settings/custom-fields'));
        }
        (new AuditLogger())->record('custom_fields.deleted', 'custom_field', (int) $field['id'], [
            'entity_type' => (string) $field['entity_type'],
            'field_type' => (string) $field['field_type'],
            'values_deleted' => $impact['values'],
            'images_deleted' => $impact['images'],
            'files_deleted' => $impact['files'],
        ], Auth::id());
        Session::flash('custom_fields.notice', __('custom_fields.deleted', [
            'label' => (string) $field['label'],
            'count' => (string) $impact['total'],
        ]));

        return Response::redirect(url('/settings/custom-fields'));
    }

    public function toggle(Request $request, string $id): Response
    {
        $fieldId = filter_var($id, FILTER_VALIDATE_INT);
        $repository = new CustomFieldRepository();
        $field = $fieldId !== false && $fieldId > 0 ? $repository->findById((int) $fieldId) : null;
        if ($field === null) {
            return Response::html(View::render('pages/404', [], 'layouts/app'), 404);
        }
        if (!$this->authorizeEntity($field['entity_type'])) {
            return Response::html(__('common.forbidden'), 403);
        }
        $activeInput = self::inputString($request, 'is_active');
        if (!in_array($activeInput, ['0', '1'], true)) {
            Session::flash('custom_fields.errors', ['general' => __('custom_fields.invalid')]);
            return Response::redirect(url('/settings/custom-fields'));
        }
        try {
            $repository->setActive((int) $fieldId, $activeInput === '1');
        } catch (Throwable $error) {
            error_log((string) $error);
            Session::flash('custom_fields.errors', ['general' => __('custom_fields.save_failed')]);
            return Response::redirect(url('/settings/custom-fields'));
        }
        (new AuditLogger())->record('custom_fields.toggled', 'custom_field', (int) $fieldId, [
            'entity_type' => (string) $field['entity_type'],
            'is_active' => $activeInput === '1',
        ], Auth::id());
        Session::flash('custom_fields.notice', __('custom_fields.updated'));
        return Response::redirect(url('/settings/custom-fields'));
    }

    private function authorizeEntity(string $entityType): bool
    {
        return match ($entityType) {
            'client' => Auth::can('clients.manage'),
            'project' => Auth::can('projects.configure'),
            default => false,
        };
    }

    private static function canManageAny(): bool
    {
        return Auth::can('clients.manage') || Auth::can('projects.configure');
    }

    /** @return array<string,mixed> */
    private static function definitionInput(Request $request): array
    {
        return [
            'entity_type' => self::inputString($request, 'entity_type'),
            'field_key' => self::inputString($request, 'field_key'),
            'label' => self::inputString($request, 'label'),
            'field_type' => self::inputString($request, 'field_type'),
            'options' => self::inputString($request, 'options'),
            'is_required' => $request->input('is_required', '0'),
            'sort_order' => self::inputString($request, 'sort_order'),
        ];
    }

    private static function inputString(Request $request, string $key): string
    {
        $value = $request->input($key, '');
        return is_string($value) || is_int($value) ? trim((string) $value) : '';
    }
}
