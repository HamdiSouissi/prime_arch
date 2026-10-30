<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\Translator;
use App\Core\View;
use App\Models\DocumentTemplateRepository;
use App\Services\DocumentTemplateService;
use App\Services\ViewContext;
use Throwable;

/** Édition des modèles réutilisés par les nouveaux brouillons. */
final class DocumentTemplateController
{
    public function index(Request $request): Response
    {
        if (!self::canManageTemplates()) {
            return Response::html(__('common.forbidden'), 403);
        }
        $values = Session::pullFlash('document_templates.values', []);
        if (!is_array($values) || $values === []) {
            $values = [
                'document_type' => 'quote',
                'locale' => Translator::locale(),
                'name' => '',
                'title' => '',
                'terms_text' => '',
                'legal_notice' => '',
                'validity_days' => '0',
                'payment_terms_days' => '0',
                'is_default' => false,
                'is_active' => true,
            ];
        }
        $context = (new ViewContext())->authenticated(__('documents.templates_title'), 'documents');
        return Response::html(View::render('pages/documents/templates', $context + [
            'templates' => (new DocumentTemplateRepository())->all(),
            'values' => $values,
            'errors' => Session::pullFlash('document_templates.errors', []),
            'rowValues' => Session::pullFlash('document_templates.row_values', []),
            'notice' => Session::pullFlash('document_templates.notice'),
        ]));
    }

    public function create(Request $request): Response
    {
        if (!self::canManageTemplates()) {
            return Response::html(__('common.forbidden'), 403);
        }
        $validated = (new DocumentTemplateService())->validate($request->all());
        if ($validated['errors'] !== []) {
            Session::flash('document_templates.errors', $validated['errors']);
            Session::flash('document_templates.values', $validated['values']);
            return Response::redirect(url('/settings/document-templates'));
        }
        try {
            (new DocumentTemplateRepository())->create($validated['values'], Auth::id());
        } catch (Throwable $error) {
            error_log((string) $error);
            Session::flash('document_templates.errors', ['general' => __('documents.templates_save_failed')]);
            Session::flash('document_templates.values', $validated['values']);
            return Response::redirect(url('/settings/document-templates'));
        }
        Session::flash('document_templates.notice', __('documents.template_created'));
        return Response::redirect(url('/settings/document-templates'));
    }

    public function update(Request $request, string $id): Response
    {
        if (!self::canManageTemplates()) {
            return Response::html(__('common.forbidden'), 403);
        }
        $templateId = filter_var($id, FILTER_VALIDATE_INT);
        if ($templateId === false || $templateId < 1) {
            return Response::redirect(url('/settings/document-templates'));
        }
        $values = $request->all();
        $values['document_type'] = $request->input('document_type', '');
        $values['locale'] = $request->input('locale', '');
        $validated = (new DocumentTemplateService())->validate($values);
        if ($validated['errors'] !== []) {
            Session::flash('document_templates.errors', $validated['errors'] + ['template_id' => (string) $templateId]);
            Session::flash('document_templates.row_values', [(string) $templateId => $validated['values']]);
            return Response::redirect(url('/settings/document-templates'));
        }
        try {
            if (!(new DocumentTemplateRepository())->update((int) $templateId, $validated['values'])) {
                Session::flash('document_templates.errors', ['general' => __('documents.template_not_found')]);
                return Response::redirect(url('/settings/document-templates'));
            }
        } catch (Throwable $error) {
            error_log((string) $error);
            Session::flash('document_templates.errors', ['general' => __('documents.templates_save_failed')]);
            Session::flash('document_templates.row_values', [(string) $templateId => $validated['values']]);
            return Response::redirect(url('/settings/document-templates'));
        }
        Session::flash('document_templates.notice', __('documents.template_updated'));
        return Response::redirect(url('/settings/document-templates'));
    }

    private static function canManageTemplates(): bool
    {
        return Auth::can('documents.templates.manage') || Auth::can('settings.manage');
    }
}
