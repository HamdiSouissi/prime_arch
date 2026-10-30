<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\View;
use App\Models\CustomFieldRepository;
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
        return Response::html(View::render('pages/settings/custom-fields', $context + [
            'clientFields' => $clientFields,
            'projectFields' => $projectFields,
            'canManageClients' => Auth::can('clients.manage'),
            'canManageProjects' => Auth::can('projects.configure'),
            'errors' => Session::pullFlash('custom_fields.errors', []),
            'notice' => Session::pullFlash('custom_fields.notice'),
            'values' => is_array($values) ? $values : [],
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
        try {
            (new CustomFieldRepository())->create($validated['data']);
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
        Session::flash('custom_fields.notice', __('custom_fields.created'));
        return Response::redirect(url('/settings/custom-fields'));
    }

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
        $label = self::inputString($request, 'label');
        $sortInput = self::inputString($request, 'sort_order');
        $required = in_array($request->input('is_required'), ['1', 1, true, 'on'], true);
        $errors = [];
        if (mb_strlen($label, 'UTF-8') < 2 || mb_strlen($label, 'UTF-8') > 120) {
            $errors['label'] = __('custom_fields.invalid');
        }
        if (!ctype_digit($sortInput) || (int) $sortInput > 9999) {
            $errors['sort_order'] = __('custom_fields.invalid');
        }
        if ($errors !== []) {
            Session::flash('custom_fields.errors', $errors);
            return Response::redirect(url('/settings/custom-fields'));
        }
        try {
            $repository->updateDetails((int) $fieldId, $label, $required, (int) $sortInput);
        } catch (Throwable $error) {
            error_log((string) $error);
            Session::flash('custom_fields.errors', ['general' => __('custom_fields.save_failed')]);
            return Response::redirect(url('/settings/custom-fields'));
        }
        Session::flash('custom_fields.notice', __('custom_fields.updated'));
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
