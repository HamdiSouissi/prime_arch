<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\View;
use App\Services\AuditLogger;
use App\Services\ModuleException;
use App\Services\ModuleService;
use App\Services\ViewContext;
use Throwable;

/** Administration des extensions, réservée aux administrateurs du cabinet. */
final class ModuleController
{
    public function index(Request $request): Response
    {
        $context = (new ViewContext())->authenticated(__('modules.title'), 'settings');
        $context['modulesBackUrl'] = Auth::can('settings.view') ? url('/settings') : url('/admin/users');
        $context['modulesBackLabel'] = Auth::can('settings.view') ? __('settings.title') : __('users.title');
        return Response::html(View::render('pages/settings/modules-admin', $context + [
            'modules' => (new ModuleService())->all(),
            'notice' => Session::pullFlash('modules.notice'),
            'warning' => Session::pullFlash('modules.warning'),
            'errors' => Session::pullFlash('modules.errors', []),
        ]));
    }

    public function install(Request $request): Response
    {
        if ($request->input('trusted_code') !== '1') {
            Session::flash('modules.errors', [
                'general' => 'Confirmez que le code PHP du module est de confiance avant de l’installer.',
            ]);
            return Response::redirect(url('/settings/modules'));
        }

        try {
            $result = (new ModuleService())->install($request->file('module_zip') ?? [], Auth::id());
            $slug = (string) ($result['manifest']['slug'] ?? '');
            (new AuditLogger())->record('modules.installed', 'module', $slug, ['module' => $slug], Auth::id());
            $name = (string) ($result['manifest']['name'] ?? 'Module');
            Session::flash('modules.notice', $result['enabled']
                ? '« ' . $name . ' » a été installé et activé.'
                : '« ' . $name . ' » a été installé et reste désactivé.');
            if (is_string($result['warning']) && $result['warning'] !== '') {
                Session::flash('modules.warning', $result['warning']);
            }
        } catch (ModuleException $error) {
            Session::flash('modules.errors', ['general' => $error->getMessage()]);
        } catch (Throwable $error) {
            error_log((string) $error);
            Session::flash('modules.errors', ['general' => 'L’installation du module a échoué. Consultez le journal du serveur.']);
        }
        return Response::redirect(url('/settings/modules'));
    }

    public function activate(Request $request, string $slug): Response
    {
        try {
            (new ModuleService())->activate($slug);
            (new AuditLogger())->record('modules.activated', 'module', $slug, ['module' => $slug], Auth::id());
            Session::flash('modules.notice', 'Le module « ' . $slug . ' » est activé.');
        } catch (ModuleException $error) {
            Session::flash('modules.errors', ['general' => $error->getMessage()]);
        } catch (Throwable $error) {
            error_log((string) $error);
            Session::flash('modules.errors', ['general' => 'L’activation a échoué. Consultez le journal du serveur.']);
        }
        return Response::redirect(url('/settings/modules'));
    }

    public function deactivate(Request $request, string $slug): Response
    {
        try {
            (new ModuleService())->deactivate($slug);
            (new AuditLogger())->record('modules.deactivated', 'module', $slug, ['module' => $slug], Auth::id());
            Session::flash('modules.notice', 'Le module « ' . $slug . ' » est désactivé. Son code et ses données sont conservés.');
        } catch (ModuleException $error) {
            Session::flash('modules.errors', ['general' => $error->getMessage()]);
        } catch (Throwable $error) {
            error_log((string) $error);
            Session::flash('modules.errors', ['general' => 'La désactivation a échoué. Consultez le journal du serveur.']);
        }
        return Response::redirect(url('/settings/modules'));
    }
}
