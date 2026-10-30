<?php
declare(strict_types=1);

namespace Modules\GedVersions\Controllers;

use App\Core\Auth;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\View;
use App\Services\AuditLogger;
use App\Services\ViewContext;
use InvalidArgumentException;
use Modules\GedVersions\Models\GedRepository;
use Modules\GedVersions\Services\GedService;
use Modules\GedVersions\Services\GedStorage;
use Throwable;

/**
 * Dépôt documentaire versionné : classement par projet et par client, versions successives,
 * recherche, rétention et suppression maîtrisée.
 *
 * Le module écrit uniquement dans ses tables `ged_*`. Les clients, projets et utilisateurs du
 * noyau sont lus pour proposer un classement ; aucune fiche du cœur n’est créée ni modifiée.
 * Droits réutilisés : `documents.view` ou `projects.view` en lecture, `documents.manage` ou
 * `projects.manage` en écriture.
 */
final class GedController
{
    private GedRepository $ged;
    private GedStorage $storage;

    public function __construct(?GedRepository $ged = null, ?GedStorage $storage = null)
    {
        $this->ged = $ged ?? new GedRepository();
        $this->storage = $storage ?? new GedStorage();
    }

    /** Liste des documents : indicateurs, filtres et résultats. */
    public function index(Request $request): Response
    {
        if (($denied = $this->requireView()) !== null) {
            return $denied;
        }
        $today = date('Y-m-d');
        $filters = $this->filters($request);
        $documents = $this->decorateAll($this->ged->documents($filters), $today);

        return Response::html(View::renderModule('ged-versions', 'index', $this->context('ged.index_title') + [
            'today' => $today,
            'documents' => $documents,
            'statistics' => GedService::statistics($this->ged->documents([]), $today),
            'categories' => GedService::CATEGORIES,
            'clients' => $this->ged->clientOptions(),
            'projects' => $this->ged->projectOptions(),
            'filters' => $filters,
            'canManage' => $this->canManage(),
            'maxSize' => GedService::humanSize(GedService::MAX_BYTES),
            'notice' => Session::pullFlash('ged.notice'),
            'error' => Session::pullFlash('ged.error'),
            'documentErrors' => Session::pullFlash('ged.document_errors', []),
            'documentValues' => Session::pullFlash('ged.document_values', []),
        ]));
    }

    /** Fiche d’un document : métadonnées, versions, empreintes et actions. */
    public function show(Request $request, string $id): Response
    {
        if (($denied = $this->requireView()) !== null) {
            return $denied;
        }
        $document = $this->ged->find($this->identifier($id));
        if ($document === null) {
            Session::flash('ged.error', __('ged.not_found'));

            return Response::redirect(url('/ged'));
        }
        $today = date('Y-m-d');
        $document = $this->decorate($document, $today);

        return Response::html(View::renderModule('ged-versions', 'show', $this->context('ged.show_title') + [
            'today' => $today,
            'document' => $document,
            'versions' => $this->decorateVersions($this->ged->versions((int) $document['id'])),
            'categories' => GedService::CATEGORIES,
            'clients' => $this->ged->clientOptions(),
            'projects' => $this->ged->projectOptions(),
            'canManage' => $this->canManage(),
            'maxSize' => GedService::humanSize(GedService::MAX_BYTES),
            'maxVersions' => GedService::MAX_VERSIONS,
            'notice' => Session::pullFlash('ged.notice'),
            'error' => Session::pullFlash('ged.error'),
            'documentErrors' => Session::pullFlash('ged.document_errors', []),
            'documentValues' => Session::pullFlash('ged.document_values', []),
            'versionErrors' => Session::pullFlash('ged.version_errors', []),
            'versionValues' => Session::pullFlash('ged.version_values', []),
        ]));
    }

    /** Crée une fiche documentaire, avec un premier fichier facultatif. */
    public function store(Request $request): Response
    {
        if (($denied = $this->requireManage()) !== null) {
            return $denied;
        }
        $validated = $this->validate($request->all());
        if ($validated['errors'] !== []) {
            Session::flash('ged.document_errors', $validated['errors']);
            Session::flash('ged.document_values', $validated['values']);

            return Response::redirect(url('/ged'));
        }

        $documentId = $this->ged->create($validated['values'], Auth::id());
        $file = $request->file('file');
        if (is_array($file) && (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
            try {
                $version = $this->storage->store($documentId, $file);
                $version['note'] = '';
                $this->ged->addVersion($documentId, $version, 1, Auth::id());
            } catch (InvalidArgumentException $error) {
                $this->discard($documentId);
                Session::flash('ged.document_errors', ['file' => $error->getMessage()]);
                Session::flash('ged.document_values', $validated['values']);

                return Response::redirect(url('/ged'));
            } catch (Throwable $error) {
                $this->discard($documentId);
                error_log((string) $error);
                Session::flash('ged.document_errors', ['file' => __('ged.error.storage_failed')]);
                Session::flash('ged.document_values', $validated['values']);

                return Response::redirect(url('/ged'));
            }
        }

        (new AuditLogger())->record('ged.document_created', 'ged_document', $documentId, [
            'category' => $validated['values']['category'],
            'project_id' => $validated['values']['project_id'],
            'with_file' => is_array($file) && (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE,
        ], Auth::id());
        Session::flash('ged.notice', __('ged.document_created'));

        return Response::redirect(url('/ged/' . $documentId));
    }

    /** Met à jour les métadonnées d’une fiche. */
    public function update(Request $request, string $id): Response
    {
        if (($denied = $this->requireManage()) !== null) {
            return $denied;
        }
        $document = $this->ged->find($this->identifier($id));
        if ($document === null) {
            Session::flash('ged.error', __('ged.not_found'));

            return Response::redirect(url('/ged'));
        }
        $documentId = (int) $document['id'];
        $validated = $this->validate($request->all());
        if ($validated['errors'] !== []) {
            Session::flash('ged.document_errors', $validated['errors']);
            Session::flash('ged.document_values', $validated['values']);

            return Response::redirect(url('/ged/' . $documentId));
        }
        $this->ged->update($documentId, $validated['values']);
        (new AuditLogger())->record('ged.document_updated', 'ged_document', $documentId, [
            'category' => $validated['values']['category'],
        ], Auth::id());
        Session::flash('ged.notice', __('ged.document_updated'));

        return Response::redirect(url('/ged/' . $documentId));
    }

    /** Ajoute une nouvelle version (fichier + note). */
    public function upload(Request $request, string $id): Response
    {
        if (($denied = $this->requireManage()) !== null) {
            return $denied;
        }
        $document = $this->ged->find($this->identifier($id));
        if ($document === null) {
            Session::flash('ged.error', __('ged.not_found'));

            return Response::redirect(url('/ged'));
        }
        $documentId = (int) $document['id'];
        $validated = GedService::validateVersion($request->all());
        if ($validated['errors'] !== []) {
            Session::flash('ged.version_errors', $validated['errors']);
            Session::flash('ged.version_values', $validated['values']);

            return Response::redirect(url('/ged/' . $documentId));
        }
        $file = $request->file('file');
        if (!is_array($file) || (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            Session::flash('ged.version_errors', ['file' => __('ged.error.file_required')]);
            Session::flash('ged.version_values', $validated['values']);

            return Response::redirect(url('/ged/' . $documentId));
        }
        $versions = $this->ged->versions($documentId);
        if (count($versions) >= GedService::MAX_VERSIONS) {
            Session::flash('ged.version_errors', ['file' => __('ged.error.too_many_versions', [
                'max' => (string) GedService::MAX_VERSIONS,
            ])]);

            return Response::redirect(url('/ged/' . $documentId));
        }

        try {
            $stored = $this->storage->store($documentId, $file);
            $stored['note'] = $validated['values']['note'];
            $versionId = $this->ged->addVersion(
                $documentId,
                $stored,
                GedService::nextVersionNumber($versions),
                Auth::id()
            );
        } catch (InvalidArgumentException $error) {
            Session::flash('ged.version_errors', ['file' => $error->getMessage()]);
            Session::flash('ged.version_values', $validated['values']);

            return Response::redirect(url('/ged/' . $documentId));
        } catch (Throwable $error) {
            error_log((string) $error);
            Session::flash('ged.version_errors', ['file' => __('ged.error.storage_failed')]);
            Session::flash('ged.version_values', $validated['values']);

            return Response::redirect(url('/ged/' . $documentId));
        }

        (new AuditLogger())->record('ged.version_added', 'ged_version', $versionId, [
            'document_id' => $documentId,
            'mime_type' => $stored['mime_type'],
            'file_size' => $stored['file_size'],
        ], Auth::id());
        Session::flash('ged.notice', __('ged.version_added'));

        return Response::redirect(url('/ged/' . $documentId));
    }

    /** Télécharge ou affiche une version, après vérification de l’empreinte. */
    public function download(Request $request, string $id, string $versionId): Response
    {
        if (($denied = $this->requireView()) !== null) {
            return $denied;
        }
        $documentId = $this->identifier($id);
        $version = $this->ged->findVersion($documentId, $this->identifier($versionId));
        if ($version === null) {
            return Response::html(View::render('pages/404', [], 'layouts/app'), 404);
        }
        $stored = $this->storage->read($documentId, (string) $version['stored_name']);
        if ($stored === null || !hash_equals((string) $version['checksum'], $stored['checksum'])
            || strlen($stored['contents']) !== (int) $version['file_size']
        ) {
            error_log('ged-versions : fichier altéré ou illisible pour la version ' . (int) $version['id']);

            return Response::html(View::render('pages/404', [], 'layouts/app'), 404);
        }
        $inline = $stored['mime_type'] === 'application/pdf' || str_starts_with($stored['mime_type'], 'image/');

        return Response::download(
            $stored['contents'],
            (string) $version['original_name'],
            $stored['mime_type'],
            $inline ? 'inline' : 'attachment'
        );
    }

    /** Supprime une version, en conservant au moins un fichier sur le document. */
    public function deleteVersion(Request $request, string $id, string $versionId): Response
    {
        if (($denied = $this->requireManage()) !== null) {
            return $denied;
        }
        $document = $this->ged->find($this->identifier($id));
        if ($document === null) {
            Session::flash('ged.error', __('ged.not_found'));

            return Response::redirect(url('/ged'));
        }
        $documentId = (int) $document['id'];
        $version = $this->ged->findVersion($documentId, $this->identifier($versionId));
        if ($version === null) {
            Session::flash('ged.error', __('ged.version_not_found'));

            return Response::redirect(url('/ged/' . $documentId));
        }
        if ($this->ged->countVersions($documentId) <= 1) {
            Session::flash('ged.error', __('ged.error.last_version'));

            return Response::redirect(url('/ged/' . $documentId));
        }
        $this->ged->deleteVersion((int) $version['id']);
        $this->storage->remove($documentId, (string) $version['stored_name']);
        (new AuditLogger())->record('ged.version_deleted', 'ged_version', (int) $version['id'], [
            'document_id' => $documentId,
            'version_number' => (int) $version['version_number'],
        ], Auth::id());
        Session::flash('ged.notice', __('ged.version_deleted'));

        return Response::redirect(url('/ged/' . $documentId));
    }

    /** Archive ou désarchive une fiche : aucun fichier n’est supprimé. */
    public function archive(Request $request, string $id): Response
    {
        if (($denied = $this->requireManage()) !== null) {
            return $denied;
        }
        $document = $this->ged->find($this->identifier($id));
        if ($document === null) {
            Session::flash('ged.error', __('ged.not_found'));

            return Response::redirect(url('/ged'));
        }
        $documentId = (int) $document['id'];
        $archived = (string) $request->input('archived', '') === '1';
        $this->ged->setArchived($documentId, $archived);
        (new AuditLogger())->record(
            $archived ? 'ged.document_archived' : 'ged.document_unarchived',
            'ged_document',
            $documentId,
            [],
            Auth::id()
        );
        Session::flash('ged.notice', $archived ? __('ged.document_archived') : __('ged.document_unarchived'));

        return Response::redirect(url('/ged/' . $documentId));
    }

    /** Supprime définitivement une fiche et tous ses fichiers. */
    public function destroy(Request $request, string $id): Response
    {
        if (($denied = $this->requireManage()) !== null) {
            return $denied;
        }
        $document = $this->ged->find($this->identifier($id));
        if ($document === null) {
            Session::flash('ged.error', __('ged.not_found'));

            return Response::redirect(url('/ged'));
        }
        $documentId = (int) $document['id'];
        $versions = $this->ged->versions($documentId);
        $this->storage->removeAll($documentId);
        $this->ged->delete($documentId);
        (new AuditLogger())->record('ged.document_deleted', 'ged_document', $documentId, [
            'reference' => (string) ($document['reference'] ?? ''),
            'versions' => count($versions),
        ], Auth::id());
        Session::flash('ged.notice', __('ged.document_deleted'));

        return Response::redirect(url('/ged'));
    }

    /** Export CSV de l’index, dans les filtres affichés. */
    public function exportCsv(Request $request): Response
    {
        if (($denied = $this->requireView()) !== null) {
            return $denied;
        }
        $today = date('Y-m-d');
        $rows = $this->decorateAll($this->ged->documents($this->filters($request)), $today);
        $table = [[
            __('ged.reference'),
            __('ged.field.title'),
            __('ged.field.category'),
            __('ged.field.client'),
            __('ged.field.project'),
            __('ged.field.tags'),
            __('ged.versions'),
            __('ged.total_size'),
            __('ged.field.retention'),
            __('ged.field.archived'),
            __('ged.field.updated_at'),
            __('ged.field.description'),
        ]];
        foreach ($rows as $row) {
            $table[] = [
                (string) ($row['reference'] ?? ''),
                (string) $row['title'],
                __('ged.category.' . (string) $row['category']),
                (string) ($row['client_name'] ?? ''),
                (string) ($row['project_name'] ?? ''),
                (string) ($row['tags'] ?? ''),
                (string) $row['version_count'],
                (string) $row['total_bytes'],
                (string) ($row['retention_until'] ?? ''),
                $row['archived_at'] !== null && (string) $row['archived_at'] !== '' ? __('ged.yes') : __('ged.no'),
                (string) ($row['updated_at'] ?? ''),
                (string) ($row['description'] ?? ''),
            ];
        }

        return Response::download(
            GedService::csv($table),
            'prime-arch-ged-' . $today . '.csv',
            'text/csv; charset=utf-8'
        );
    }

    /** @param array<string,mixed> $input
     *  @return array{values:array<string,mixed>,errors:array<string,string>}
     */
    private function validate(array $input): array
    {
        $clientIds = array_map(static fn (array $client): int => (int) $client['id'], $this->ged->clientOptions());

        return GedService::validateDocument($input, $clientIds, GedRepository::projectClients($this->ged->projectOptions()));
    }

    /** @return array<string,string> */
    private function filters(Request $request): array
    {
        $category = (string) $request->query('category', '');
        if (!in_array($category, GedService::CATEGORIES, true)) {
            $category = '';
        }
        $archive = (string) $request->query('archive', 'active');
        if (!in_array($archive, ['active', 'archived', 'all'], true)) {
            $archive = 'active';
        }
        $project = filter_var($request->query('projet', ''), FILTER_VALIDATE_INT);
        $client = filter_var($request->query('client', ''), FILTER_VALIDATE_INT);
        $search = trim((string) $request->query('q', ''));
        if (mb_strlen($search, 'UTF-8') > GedService::MAX_SEARCH) {
            $search = mb_substr($search, 0, GedService::MAX_SEARCH, 'UTF-8');
        }

        return [
            'category' => $category,
            'archive' => $archive,
            'project_id' => $project !== false && $project > 0 ? (string) (int) $project : '',
            'client_id' => $client !== false && $client > 0 ? (string) (int) $client : '',
            'search' => $search,
        ];
    }

    /** @param array<int,array<string,mixed>> $rows
     *  @return array<int,array<string,mixed>>
     */
    private function decorateAll(array $rows, string $today): array
    {
        return array_map(fn (array $row): array => $this->decorate($row, $today), $rows);
    }

    /** @param array<string,mixed> $row
     *  @return array<string,mixed>
     */
    private function decorate(array $row, string $today): array
    {
        $retention = GedService::retention(
            isset($row['retention_until']) && $row['retention_until'] !== null ? (string) $row['retention_until'] : null,
            $today
        );
        $row['retention_state'] = $retention['state'];
        $row['retention_days'] = $retention['days'];
        $row['version_count'] = (int) ($row['version_count'] ?? 0);
        $row['total_bytes'] = (int) ($row['total_bytes'] ?? 0);
        $row['size_label'] = GedService::humanSize($row['total_bytes']);
        $row['latest_version'] = GedService::versionLabel((int) ($row['latest_version_number'] ?? 1));

        return $row;
    }

    /**
     * @param array<int,array<string,mixed>> $versions
     * @return array<int,array<string,mixed>>
     */
    private function decorateVersions(array $versions): array
    {
        return array_map(static function (array $version): array {
            $version['version_label'] = GedService::versionLabel((int) ($version['version_number'] ?? 1));
            $version['size_label'] = GedService::humanSize((int) ($version['file_size'] ?? 0));
            $version['checksum_short'] = substr((string) ($version['checksum'] ?? ''), 0, 12);

            return $version;
        }, $versions);
    }

    /** Supprime une fiche créée dans une transaction logique (fichier refusé). */
    private function discard(int $documentId): void
    {
        $this->storage->removeAll($documentId);
        $this->ged->delete($documentId);
    }

    /** Identifiant entier positif, `0` si la valeur est invalide. */
    private function identifier(string $value): int
    {
        $identifier = filter_var($value, FILTER_VALIDATE_INT);

        return $identifier !== false && $identifier > 0 ? (int) $identifier : 0;
    }

    private function canView(): bool
    {
        return Auth::can('documents.view') || Auth::can('projects.view');
    }

    private function canManage(): bool
    {
        return Auth::can('documents.manage') || Auth::can('projects.manage');
    }

    private function requireView(): ?Response
    {
        return $this->canView() ? null : Response::html(__('common.forbidden'), 403);
    }

    private function requireManage(): ?Response
    {
        return $this->canManage() ? null : Response::html(__('common.forbidden'), 403);
    }

    /** @return array<string,mixed> */
    private function context(string $titleKey): array
    {
        return (new ViewContext())->authenticated(__($titleKey), 'ged-versions');
    }
}
