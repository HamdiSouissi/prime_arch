<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\View;
use App\Models\CustomFieldRepository;
use App\Models\ProjectFileRepository;
use App\Models\ProjectRepository;
use App\Services\AuditLogger;
use App\Services\CustomFieldService;
use App\Services\ProjectFileStorage;
use InvalidArgumentException;
use Throwable;

/**
 * Plans AutoCAD privés rattachés à un champ personnalisé de type « fichier ».
 *
 * Le champ est créé dans Réglages › Champs personnalisés, puis alimenté depuis la fiche
 * projet : un envoi, un téléchargement, une suppression. Aucun fichier n'est placé sous
 * `public/` : la seule voie d'accès est la route authentifiée ci-dessous, protégée par
 * `projects.view` (lecture) et `projects.manage` (écriture).
 */
final class ProjectFileController
{
    /** Envoi d'un ou plusieurs plans dans le champ identifié par sa clé technique. */
    public function upload(Request $request, string $id, string $field): Response
    {
        if (!Auth::can('projects.manage')) {
            return Response::html(__('common.forbidden'), 403);
        }
        $projectId = self::existingProjectId($id);
        $definition = $projectId !== null ? self::fileField($field) : null;
        if ($projectId === null || $definition === null) {
            return self::notFound();
        }

        $key = (string) $definition['field_key'];
        $repository = new ProjectFileRepository();
        $storage = new ProjectFileStorage();
        $files = self::uploadedFiles($request->file('files'));
        if ($files === []) {
            return self::backToField($projectId, $key, null, __('projects.file_required'));
        }
        if (count($files) > CustomFieldService::FILE_MAX_PER_UPLOAD) {
            return self::backToField($projectId, $key, null, __('projects.custom_file_batch_limit', [
                'count' => (string) CustomFieldService::FILE_MAX_PER_UPLOAD,
            ]));
        }

        $stored = 0;
        $firstError = '';
        foreach ($files as $file) {
            if ($repository->countForCustomField($projectId, $key) >= CustomFieldService::FILE_MAX_PER_FIELD) {
                $firstError = $firstError !== '' ? $firstError : __('projects.custom_file_field_limit', [
                    'count' => (string) CustomFieldService::FILE_MAX_PER_FIELD,
                ]);
                break;
            }
            $storedFile = null;
            try {
                $storedFile = $storage->store($projectId, $file);
                $fileId = $repository->create($projectId, $storedFile, Auth::id(), $key);
                (new AuditLogger())->record('projects.file.uploaded', 'project_file', $fileId, [
                    'project_id' => $projectId,
                    'custom_field_key' => $key,
                    'extension' => $storedFile['extension'],
                    'file_size' => $storedFile['file_size'],
                ], Auth::id());
                $stored++;
            } catch (InvalidArgumentException $error) {
                if (is_array($storedFile)) {
                    $storage->remove($projectId, (string) $storedFile['stored_name']);
                }
                $firstError = $firstError !== '' ? $firstError : $error->getMessage();
            } catch (Throwable $error) {
                if (is_array($storedFile)) {
                    $storage->remove($projectId, (string) $storedFile['stored_name']);
                }
                error_log((string) $error);
                $firstError = $firstError !== '' ? $firstError : __('projects.file_upload_failed');
            }
        }

        $notice = $stored > 0
            ? __('projects.custom_file_uploaded', ['count' => (string) $stored])
            : null;

        return self::backToField($projectId, $key, $notice, $firstError);
    }

    /** Retire un plan : fichier privé d'abord, métadonnées ensuite. */
    public function delete(Request $request, string $id, string $field, string $fileId): Response
    {
        if (!Auth::can('projects.manage')) {
            return Response::html(__('common.forbidden'), 403);
        }
        $projectId = self::existingProjectId($id);
        $definition = $projectId !== null ? self::fileField($field) : null;
        $validatedId = filter_var($fileId, FILTER_VALIDATE_INT);
        if ($projectId === null || $definition === null || $validatedId === false || $validatedId < 1) {
            return self::notFound();
        }

        $key = (string) $definition['field_key'];
        $repository = new ProjectFileRepository();
        $file = $repository->findForCustomField($projectId, $key, (int) $validatedId);
        if ($file === null) {
            return self::backToField($projectId, $key, null, __('projects.custom_file_missing'));
        }

        (new ProjectFileStorage())->remove($projectId, (string) $file['stored_name']);
        $repository->delete($projectId, (int) $file['id']);
        (new AuditLogger())->record('projects.file.deleted', 'project_file', (int) $file['id'], [
            'project_id' => $projectId,
            'custom_field_key' => $key,
            'extension' => $file['extension'],
        ], Auth::id());

        return self::backToField($projectId, $key, __('projects.custom_file_deleted'), null);
    }

    /**
     * Téléchargement du plan.
     *
     * Le fichier est toujours servi en pièce jointe (`application/octet-stream`) : un DWG ne
     * doit jamais être interprété par le navigateur, et le nom d'origine est repris tel quel.
     */
    public function download(Request $request, string $id, string $fileId): Response
    {
        if (!Auth::can('projects.view')) {
            return Response::html(__('common.forbidden'), 403);
        }
        $projectId = filter_var($id, FILTER_VALIDATE_INT);
        $validatedId = filter_var($fileId, FILTER_VALIDATE_INT);
        if ($projectId === false || $projectId < 1 || $validatedId === false || $validatedId < 1) {
            return self::notFound();
        }

        $file = (new ProjectFileRepository())->findForProject((int) $projectId, (int) $validatedId);
        if ($file === null) {
            return self::notFound();
        }
        $stored = (new ProjectFileStorage())->read(
            (int) $projectId,
            (string) $file['stored_name'],
            (string) $file['checksum']
        );
        if ($stored === null || strlen($stored['contents']) !== $file['file_size']) {
            // Fichier disparu ou altéré sur le disque : on ne sert rien plutôt qu'un contenu douteux.
            error_log('project_files : contenu absent ou altéré pour le fichier #' . (int) $file['id']);
            return self::notFound();
        }

        return Response::download($stored['contents'], (string) $file['original_name'], $stored['mime_type'], 'attachment');
    }

    private static function notFound(): Response
    {
        return Response::html(View::render('pages/404', [], 'layouts/app'), 404);
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

    /** Définition du champ personnalisé « fichier » actif portant cette clé, sinon null. */
    private static function fileField(string $fieldKey): ?array
    {
        if (!preg_match('/^[a-z][a-z0-9_]{1,79}$/', $fieldKey)) {
            return null;
        }
        foreach ((new CustomFieldRepository())->all('project', true) as $field) {
            if ($field['field_key'] === $fieldKey && $field['field_type'] === 'file') {
                return $field;
            }
        }

        return null;
    }

    /**
     * Formulaire d'envoi multiple : `files[]` produit des tableaux parallèles dans $_FILES,
     * un envoi unique produit des valeurs simples. Les entrées vides sont écartées.
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
            Session::flash('projects.custom_file_notices', [$fieldKey => $notice]);
        }
        if ($error !== null) {
            Session::flash('projects.custom_file_errors', [$fieldKey => $error]);
        }

        return Response::redirect(url('/projects/' . $projectId) . '#custom-field-' . $fieldKey);
    }
}
