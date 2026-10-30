<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\View;
use App\Models\ProjectRepository;
use App\Services\ViewContext;
use Throwable;

/**
 * Configuration des phases de mission proposées aux nouveaux projets.
 */
final class ProjectPhaseController
{
    public function index(Request $request): Response
    {
        if (!Auth::can('projects.configure')) {
            return Response::html(__('common.forbidden'), 403);
        }
        $context = (new ViewContext())->authenticated(__('project_phases.title'), 'projects');
        return Response::html(View::render('pages/settings/project-phases', $context + [
            'phases' => (new ProjectRepository())->phases(),
            'errors' => Session::pullFlash('project_phases.errors', []),
            'notice' => Session::pullFlash('project_phases.notice'),
            'values' => Session::pullFlash('project_phases.values', []),
        ]));
    }

    public function create(Request $request): Response
    {
        if (!Auth::can('projects.configure')) {
            return Response::html(__('common.forbidden'), 403);
        }
        $name = self::inputString($request, 'name');
        $sortInput = self::inputString($request, 'sort_order');
        $phases = new ProjectRepository();
        $sortOrder = $sortInput === '' ? (count($phases->phases()) + 1) * 10 : (ctype_digit($sortInput) ? (int) $sortInput : -1);
        $errors = self::validate($name, $sortOrder);
        if ($errors !== []) {
            Session::flash('project_phases.errors', $errors);
            Session::flash('project_phases.values', ['name' => $name, 'sort_order' => $sortInput]);
            return Response::redirect(url('/settings/project-phases'));
        }
        try {
            $phases->createPhase($name, $sortOrder);
        } catch (Throwable $error) {
            error_log((string) $error);
            Session::flash('project_phases.errors', ['general' => __('project_phases.save_failed')]);
            return Response::redirect(url('/settings/project-phases'));
        }
        Session::flash('project_phases.notice', __('project_phases.created'));
        return Response::redirect(url('/settings/project-phases'));
    }

    public function update(Request $request, string $id): Response
    {
        if (!Auth::can('projects.configure')) {
            return Response::html(__('common.forbidden'), 403);
        }
        $phaseId = filter_var($id, FILTER_VALIDATE_INT);
        $name = self::inputString($request, 'name');
        $sortInput = self::inputString($request, 'sort_order');
        $activeInput = self::inputString($request, 'is_active');
        $sortOrder = ctype_digit($sortInput) ? (int) $sortInput : -1;
        $active = $activeInput === '1';
        $repository = new ProjectRepository();
        $phases = $repository->phases();
        $phase = null;
        foreach ($phases as $candidate) {
            if ($phaseId !== false && (int) $candidate['id'] === (int) $phaseId) {
                $phase = $candidate;
                break;
            }
        }
        if ($phase === null || !in_array($activeInput, ['0', '1'], true)) {
            Session::flash('project_phases.errors', ['general' => __('project_phases.invalid')]);
            return Response::redirect(url('/settings/project-phases'));
        }
        $errors = self::validate($name, $sortOrder);
        $activeCount = count(array_filter($phases, static fn (array $item): bool => $item['is_active']));
        if (!$active && $phase['is_active'] && $activeCount <= 1) {
            $errors['is_active'] = __('project_phases.keep_one_active');
        }
        if ($errors !== []) {
            Session::flash('project_phases.errors', $errors);
            return Response::redirect(url('/settings/project-phases'));
        }
        try {
            $repository->updatePhase((int) $phaseId, $name, $sortOrder, $active);
        } catch (Throwable $error) {
            error_log((string) $error);
            Session::flash('project_phases.errors', ['general' => __('project_phases.save_failed')]);
            return Response::redirect(url('/settings/project-phases'));
        }
        Session::flash('project_phases.notice', __('project_phases.updated'));
        return Response::redirect(url('/settings/project-phases'));
    }

    /** @return array<string,string> */
    private static function validate(string $name, int $sortOrder): array
    {
        $errors = [];
        if (mb_strlen($name, 'UTF-8') < 2 || mb_strlen($name, 'UTF-8') > 150) {
            $errors['name'] = __('project_phases.invalid');
        }
        if ($sortOrder < 0 || $sortOrder > 9999) {
            $errors['sort_order'] = __('project_phases.invalid');
        }
        return $errors;
    }

    private static function inputString(Request $request, string $key): string
    {
        $value = $request->input($key, '');
        return is_string($value) || is_int($value) ? trim((string) $value) : '';
    }
}
