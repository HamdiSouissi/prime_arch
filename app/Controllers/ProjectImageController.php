<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\View;
use App\Models\ProjectImageRepository;
use App\Models\ProjectRepository;
use App\Services\AuditLogger;
use App\Services\ProjectImageStorage;
use InvalidArgumentException;
use Throwable;

/** Téléversement et consultation authentifiée des images privées des projets. */
final class ProjectImageController
{
    public function upload(Request $request, string $id): Response
    {
        if (!Auth::can('projects.manage')) {
            return Response::html(__('common.forbidden'), 403);
        }
        $projectId = filter_var($id, FILTER_VALIDATE_INT);
        if ($projectId === false || $projectId < 1
            || (new ProjectRepository())->findById((int) $projectId) === null
        ) {
            return Response::html(View::render('pages/404', [], 'layouts/app'), 404);
        }

        $storage = new ProjectImageStorage();
        $storedImage = null;
        try {
            $storedImage = $storage->store((int) $projectId, $request->file('image') ?? []);
            $imageId = (new ProjectImageRepository())->create((int) $projectId, $storedImage, Auth::id());
            (new AuditLogger())->record('projects.image.uploaded', 'project_image', $imageId, [
                'project_id' => (int) $projectId,
                'mime_type' => $storedImage['mime_type'],
                'width' => $storedImage['width'],
                'height' => $storedImage['height'],
            ], Auth::id());
            Session::flash('projects.image_notice', __('projects.image_uploaded'));
        } catch (InvalidArgumentException $error) {
            if (is_array($storedImage)) {
                $storage->remove((int) $projectId, (string) $storedImage['stored_name']);
            }
            Session::flash('projects.image_errors', ['image' => $error->getMessage()]);
        } catch (Throwable $error) {
            if (is_array($storedImage)) {
                $storage->remove((int) $projectId, (string) $storedImage['stored_name']);
            }
            error_log((string) $error);
            Session::flash('projects.image_errors', ['image' => __('projects.image_upload_failed')]);
        }

        return Response::redirect(url('/projects/' . (int) $projectId));
    }

    public function show(Request $request, string $id, string $imageId): Response
    {
        if (!Auth::can('projects.view')) {
            return Response::html(__('common.forbidden'), 403);
        }
        $projectId = filter_var($id, FILTER_VALIDATE_INT);
        $validatedImageId = filter_var($imageId, FILTER_VALIDATE_INT);
        if ($projectId === false || $projectId < 1 || $validatedImageId === false || $validatedImageId < 1) {
            return Response::html(View::render('pages/404', [], 'layouts/app'), 404);
        }

        $image = (new ProjectImageRepository())->findForProject((int) $projectId, (int) $validatedImageId);
        if ($image === null) {
            return Response::html(View::render('pages/404', [], 'layouts/app'), 404);
        }
        $storedImage = (new ProjectImageStorage())->read((int) $projectId, $image['stored_name']);
        if ($storedImage === null || $storedImage['mime_type'] !== $image['mime_type']
            || strlen($storedImage['contents']) !== $image['file_size']
        ) {
            return Response::html(View::render('pages/404', [], 'layouts/app'), 404);
        }

        return Response::download(
            $storedImage['contents'],
            $image['original_name'],
            $storedImage['mime_type'],
            'inline'
        );
    }
}
