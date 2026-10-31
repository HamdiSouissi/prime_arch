<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\View;
use App\Models\CustomFieldRepository;
use App\Models\ProjectImageRepository;
use App\Models\ProjectRepository;
use App\Services\AuditLogger;
use App\Services\CustomFieldService;
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

    /**
     * Envoi d'une ou plusieurs images dans un champ personnalisé de type « image ».
     *
     * Les fichiers sont validés un par un : une image refusée n'annule pas les autres, et le
     * message d'erreur est rattaché au champ concerné sur la fiche projet.
     */
    public function uploadForField(Request $request, string $id, string $field): Response
    {
        if (!Auth::can('projects.manage')) {
            return Response::html(__('common.forbidden'), 403);
        }
        $projectId = self::existingProjectId($id);
        $definition = $projectId !== null ? self::imageField($field) : null;
        if ($projectId === null || $definition === null) {
            return Response::html(View::render('pages/404', [], 'layouts/app'), 404);
        }

        $key = (string) $definition['field_key'];
        $repository = new ProjectImageRepository();
        $storage = new ProjectImageStorage();
        $files = self::uploadedFiles($request->file('images'));
        if ($files === []) {
            return self::backToField($projectId, $key, null, __('projects.image_required'));
        }
        if (count($files) > CustomFieldService::IMAGE_MAX_PER_UPLOAD) {
            return self::backToField($projectId, $key, null, __('projects.custom_image_batch_limit', [
                'count' => (string) CustomFieldService::IMAGE_MAX_PER_UPLOAD,
            ]));
        }

        $stored = 0;
        $firstError = '';
        foreach ($files as $file) {
            if ($repository->countForCustomField($projectId, $key) >= CustomFieldService::IMAGE_MAX_PER_FIELD) {
                $firstError = $firstError !== '' ? $firstError : __('projects.custom_image_field_limit', [
                    'count' => (string) CustomFieldService::IMAGE_MAX_PER_FIELD,
                ]);
                break;
            }
            $storedImage = null;
            try {
                $storedImage = $storage->store($projectId, $file);
                $imageId = $repository->create($projectId, $storedImage, Auth::id(), $key);
                (new AuditLogger())->record('projects.image.uploaded', 'project_image', $imageId, [
                    'project_id' => $projectId,
                    'custom_field_key' => $key,
                    'mime_type' => $storedImage['mime_type'],
                    'width' => $storedImage['width'],
                    'height' => $storedImage['height'],
                ], Auth::id());
                $stored++;
            } catch (InvalidArgumentException $error) {
                if (is_array($storedImage)) {
                    $storage->remove($projectId, (string) $storedImage['stored_name']);
                }
                $firstError = $firstError !== '' ? $firstError : $error->getMessage();
            } catch (Throwable $error) {
                if (is_array($storedImage)) {
                    $storage->remove($projectId, (string) $storedImage['stored_name']);
                }
                error_log((string) $error);
                $firstError = $firstError !== '' ? $firstError : __('projects.image_upload_failed');
            }
        }

        $notice = $stored > 0
            ? __('projects.custom_image_uploaded', ['count' => (string) $stored])
            : null;

        return self::backToField($projectId, $key, $notice, $firstError);
    }

    /** Retire une image d'un champ personnalisé : fichier privé puis métadonnées. */
    public function deleteForField(Request $request, string $id, string $field, string $imageId): Response
    {
        if (!Auth::can('projects.manage')) {
            return Response::html(__('common.forbidden'), 403);
        }
        $projectId = self::existingProjectId($id);
        $definition = $projectId !== null ? self::imageField($field) : null;
        $validatedImageId = filter_var($imageId, FILTER_VALIDATE_INT);
        if ($projectId === null || $definition === null
            || $validatedImageId === false || $validatedImageId < 1
        ) {
            return Response::html(View::render('pages/404', [], 'layouts/app'), 404);
        }

        $key = (string) $definition['field_key'];
        $repository = new ProjectImageRepository();
        $image = $repository->findForCustomField($projectId, $key, (int) $validatedImageId);
        if ($image === null) {
            return self::backToField($projectId, $key, null, __('projects.custom_image_missing'));
        }

        (new ProjectImageStorage())->remove($projectId, (string) $image['stored_name']);
        $repository->delete($projectId, (int) $image['id']);
        (new AuditLogger())->record('projects.image.deleted', 'project_image', (int) $image['id'], [
            'project_id' => $projectId,
            'custom_field_key' => $key,
        ], Auth::id());

        return self::backToField($projectId, $key, __('projects.custom_image_deleted'), null);
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

    /** Identifiant du projet existant, ou null (404 côté appelant). */
    private static function existingProjectId(string $id): ?int
    {
        $projectId = filter_var($id, FILTER_VALIDATE_INT);
        if ($projectId === false || $projectId < 1
            || (new ProjectRepository())->findById((int) $projectId) === null
        ) {
            return null;
        }

        return (int) $projectId;
    }

    /** Définition du champ personnalisé « image » actif portant cette clé, sinon null. */
    private static function imageField(string $fieldKey): ?array
    {
        if (!preg_match('/^[a-z][a-z0-9_]{1,79}$/', $fieldKey)) {
            return null;
        }
        foreach ((new CustomFieldRepository())->all('project', true) as $field) {
            if ($field['field_key'] === $fieldKey && $field['field_type'] === 'image') {
                return $field;
            }
        }

        return null;
    }

    /**
     * Formulaire d'envoi multiple : `images[]` produit des tableaux parallèles dans $_FILES,
     * un envoi unique produit des valeurs simples. Les fichiers vides sont écartés.
     *
     * @param array<string,mixed>|null $files
     * @return array<int,array<string,mixed>>
     */
    private static function uploadedFiles(?array $files): array
    {
        if (!is_array($files) || !array_key_exists('name', $files)) {
            return [];
        }
        $entries = [];
        if (is_array($files['name'])) {
            foreach (array_keys($files['name']) as $index) {
                $entries[] = [
                    'name' => $files['name'][$index] ?? '',
                    'type' => is_array($files['type'] ?? null) ? ($files['type'][$index] ?? '') : '',
                    'tmp_name' => is_array($files['tmp_name'] ?? null) ? ($files['tmp_name'][$index] ?? '') : '',
                    'error' => is_array($files['error'] ?? null) ? ($files['error'][$index] ?? UPLOAD_ERR_NO_FILE) : UPLOAD_ERR_NO_FILE,
                    'size' => is_array($files['size'] ?? null) ? ($files['size'][$index] ?? 0) : 0,
                ];
            }
        } else {
            $entries[] = $files;
        }

        return array_values(array_filter(
            $entries,
            static fn (array $entry): bool => (int) ($entry['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE
        ));
    }

    /** Retour à la fiche projet, sur le bloc du champ concerné. */
    private static function backToField(int $projectId, string $fieldKey, ?string $notice, ?string $error): Response
    {
        if ($notice !== null) {
            Session::flash('projects.custom_image_notices', [$fieldKey => $notice]);
        }
        if ($error !== null) {
            Session::flash('projects.custom_image_errors', [$fieldKey => $error]);
        }

        return Response::redirect(url('/projects/' . $projectId) . '#custom-field-' . $fieldKey);
    }
}
