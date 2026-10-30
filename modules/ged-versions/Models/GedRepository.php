<?php
declare(strict_types=1);

namespace Modules\GedVersions\Models;

use App\Core\Database;
use Modules\GedVersions\Services\GedService;
use PDO;

/**
 * Accès PDO au dépôt documentaire.
 *
 * Le module n’écrit que dans ses tables `ged_*`. Les clients, projets et utilisateurs du noyau
 * sont seulement lus, pour proposer un classement et un propriétaire.
 */
final class GedRepository
{
    /** Nombre maximal de documents renvoyés par la liste. */
    private const LIMIT = 300;

    private PDO $pdo;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? Database::connection();
    }

    /**
     * Documents visibles selon les filtres (recherche libre, catégorie, projet, client, archive).
     *
     * @param array<string,string> $filters
     * @return array<int,array<string,mixed>>
     */
    public function documents(array $filters): array
    {
        $conditions = ['1 = 1'];
        $parameters = [];
        $category = (string) ($filters['category'] ?? '');
        if ($category !== '') {
            $conditions[] = 'd.category = :category_value';
            $parameters['category_value'] = $category;
        }
        $project = (string) ($filters['project_id'] ?? '');
        if ($project !== '') {
            $conditions[] = 'd.project_id = :project_value';
            $parameters['project_value'] = (int) $project;
        }
        $client = (string) ($filters['client_id'] ?? '');
        if ($client !== '') {
            $conditions[] = 'd.client_id = :client_value';
            $parameters['client_value'] = (int) $client;
        }
        $archive = (string) ($filters['archive'] ?? '');
        if ($archive === 'archived') {
            $conditions[] = 'd.archived_at IS NOT NULL';
        } elseif ($archive !== 'all') {
            $conditions[] = 'd.archived_at IS NULL';
        }

        $search = trim((string) ($filters['search'] ?? ''));
        if ($search !== '') {
            $like = '%' . addcslashes($search, '%_\\') . '%';
            $conditions[] = '(d.title LIKE :q_title OR d.tags LIKE :q_tags'
                . ' OR d.description LIKE :q_description OR d.reference LIKE :q_reference'
                . ' OR p.name LIKE :q_project OR p.reference LIKE :q_project_reference'
                . ' OR c.display_name LIKE :q_client OR c.reference LIKE :q_client_reference)';
            $parameters += [
                'q_title' => $like,
                'q_tags' => $like,
                'q_description' => $like,
                'q_reference' => $like,
                'q_project' => $like,
                'q_project_reference' => $like,
                'q_client' => $like,
                'q_client_reference' => $like,
            ];
        }

        $sql = 'SELECT d.id, d.reference, d.title, d.category, d.project_id, d.client_id, d.tags,'
            . ' d.description, d.retention_until, d.archived_at, d.created_by, d.created_at, d.updated_at,'
            . ' p.reference AS project_reference, p.name AS project_name, p.status AS project_status,'
            . ' c.reference AS client_reference, c.display_name AS client_name,'
            . ' u.name AS author_name,'
            . ' (SELECT COUNT(*) FROM ged_versions v WHERE v.document_id = d.id) AS version_count,'
            . ' (SELECT COALESCE(SUM(v.file_size), 0) FROM ged_versions v WHERE v.document_id = d.id) AS total_bytes,'
            . ' (SELECT MAX(v.version_number) FROM ged_versions v WHERE v.document_id = d.id) AS latest_version_number,'
            . ' (SELECT MAX(v.created_at) FROM ged_versions v WHERE v.document_id = d.id) AS last_uploaded_at'
            . ' FROM ged_documents d'
            . ' LEFT JOIN projects p ON p.id = d.project_id'
            . ' LEFT JOIN clients c ON c.id = d.client_id'
            . ' LEFT JOIN users u ON u.id = d.created_by'
            . ' WHERE ' . implode(' AND ', $conditions)
            . ' ORDER BY d.updated_at DESC, d.id DESC'
            . ' LIMIT ' . self::LIMIT;

        $statement = $this->pdo->prepare($sql);
        $statement->execute($parameters);

        return $statement->fetchAll();
    }

    /** @return array<string,mixed>|null */
    /** Nombre de pièces actives rattachées à un projet ou à un client (passerelle des fiches). */
    public function contextCount(string $column, int $id): int
    {
        if (!in_array($column, ['project_id', 'client_id'], true)) {
            return 0;
        }
        $statement = $this->pdo->prepare(
            'SELECT COUNT(*) FROM ged_documents WHERE ' . $column . ' = ? AND archived_at IS NULL'
        );
        $statement->execute([$id]);

        return (int) $statement->fetchColumn();
    }

    public function find(int $documentId): ?array
    {
        if ($documentId < 1) {
            return null;
        }
        $statement = $this->pdo->prepare(
            'SELECT d.id, d.reference, d.title, d.category, d.project_id, d.client_id, d.tags,'
            . ' d.description, d.retention_until, d.archived_at, d.created_by, d.created_at, d.updated_at,'
            . ' p.reference AS project_reference, p.name AS project_name, p.status AS project_status,'
            . ' c.reference AS client_reference, c.display_name AS client_name,'
            . ' u.name AS author_name,'
            . ' (SELECT COUNT(*) FROM ged_versions v WHERE v.document_id = d.id) AS version_count,'
            . ' (SELECT COALESCE(SUM(v.file_size), 0) FROM ged_versions v WHERE v.document_id = d.id) AS total_bytes,'
            . ' (SELECT MAX(v.version_number) FROM ged_versions v WHERE v.document_id = d.id) AS latest_version_number,'
            . ' (SELECT MAX(v.created_at) FROM ged_versions v WHERE v.document_id = d.id) AS last_uploaded_at'
            . ' FROM ged_documents d'
            . ' LEFT JOIN projects p ON p.id = d.project_id'
            . ' LEFT JOIN clients c ON c.id = d.client_id'
            . ' LEFT JOIN users u ON u.id = d.created_by'
            . ' WHERE d.id = :document_id'
            . ' LIMIT 1'
        );
        $statement->execute(['document_id' => $documentId]);
        $row = $statement->fetch();

        return is_array($row) ? $row : null;
    }

    /** Versions d’un document, de la plus récente à la plus ancienne. */
    public function versions(int $documentId): array
    {
        if ($documentId < 1) {
            return [];
        }
        $statement = $this->pdo->prepare(
            'SELECT v.id, v.document_id, v.version_number, v.stored_name, v.original_name, v.mime_type,'
            . ' v.extension, v.file_size, v.checksum, v.note, v.uploaded_by, v.created_at, u.name AS author_name'
            . ' FROM ged_versions v'
            . ' LEFT JOIN users u ON u.id = v.uploaded_by'
            . ' WHERE v.document_id = :document_id'
            . ' ORDER BY v.version_number DESC, v.id DESC'
        );
        $statement->execute(['document_id' => $documentId]);

        return $statement->fetchAll();
    }

    /** @return array<string,mixed>|null */
    public function findVersion(int $documentId, int $versionId): ?array
    {
        if ($documentId < 1 || $versionId < 1) {
            return null;
        }
        $statement = $this->pdo->prepare(
            'SELECT v.id, v.document_id, v.version_number, v.stored_name, v.original_name, v.mime_type,'
            . ' v.extension, v.file_size, v.checksum, v.note, v.uploaded_by, v.created_at, d.title AS document_title,'
            . ' d.reference AS document_reference'
            . ' FROM ged_versions v'
            . ' INNER JOIN ged_documents d ON d.id = v.document_id'
            . ' WHERE v.document_id = :document_id AND v.id = :version_value'
            . ' LIMIT 1'
        );
        $statement->execute(['document_id' => $documentId, 'version_value' => $versionId]);
        $row = $statement->fetch();

        return is_array($row) ? $row : null;
    }

    /** Crée la fiche documentaire, puis renseigne sa référence de classement. */
    public function create(array $values, ?int $authorId): int
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO ged_documents'
            . ' (title, category, project_id, client_id, tags, description, retention_until, created_by)'
            . ' VALUES (:title_value, :category_value, :project_value, :client_value, :tags_value,'
            . ' :description_value, :retention_value, :author_value)'
        );
        $statement->execute([
            'title_value' => $values['title'],
            'category_value' => $values['category'],
            'project_value' => $values['project_id'],
            'client_value' => $values['client_id'],
            'tags_value' => $values['tags'] !== '' ? $values['tags'] : null,
            'description_value' => $values['description'] !== '' ? $values['description'] : null,
            'retention_value' => $values['retention_until'] !== '' ? $values['retention_until'] : null,
            'author_value' => $authorId,
        ]);
        $documentId = (int) $this->pdo->lastInsertId();
        $reference = GedService::reference($documentId, date('Y'));
        $update = $this->pdo->prepare('UPDATE ged_documents SET reference = :reference_value WHERE id = :document_id');
        $update->execute(['reference_value' => $reference, 'document_id' => $documentId]);

        return $documentId;
    }

    public function update(int $documentId, array $values): bool
    {
        if ($documentId < 1) {
            return false;
        }
        $statement = $this->pdo->prepare(
            'UPDATE ged_documents SET title = :title_value, category = :category_value,'
            . ' project_id = :project_value, client_id = :client_value, tags = :tags_value,'
            . ' description = :description_value, retention_until = :retention_value'
            . ' WHERE id = :document_id'
        );
        $statement->execute([
            'title_value' => $values['title'],
            'category_value' => $values['category'],
            'project_value' => $values['project_id'],
            'client_value' => $values['client_id'],
            'tags_value' => $values['tags'] !== '' ? $values['tags'] : null,
            'description_value' => $values['description'] !== '' ? $values['description'] : null,
            'retention_value' => $values['retention_until'] !== '' ? $values['retention_until'] : null,
            'document_id' => $documentId,
        ]);

        return $statement->rowCount() >= 0;
    }

    /** Archive ou désarchive une fiche (aucun fichier n’est supprimé). */
    public function setArchived(int $documentId, bool $archived): bool
    {
        if ($documentId < 1) {
            return false;
        }
        $statement = $this->pdo->prepare(
            'UPDATE ged_documents SET archived_at = ' . ($archived ? 'CURRENT_TIMESTAMP' : 'NULL')
            . ' WHERE id = :document_id'
        );
        $statement->execute(['document_id' => $documentId]);

        return $statement->rowCount() > 0;
    }

    public function delete(int $documentId): bool
    {
        if ($documentId < 1) {
            return false;
        }
        $statement = $this->pdo->prepare('DELETE FROM ged_documents WHERE id = :document_id');
        $statement->execute(['document_id' => $documentId]);

        return $statement->rowCount() > 0;
    }

    /** Ajoute une version ; le numéro est calculé par le service à partir des versions lues. */
    public function addVersion(int $documentId, array $version, int $number, ?int $authorId): int
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO ged_versions'
            . ' (document_id, version_number, stored_name, original_name, mime_type, extension,'
            . ' file_size, checksum, note, uploaded_by)'
            . ' VALUES (:document_value, :number_value, :stored_value, :original_value, :mime_value,'
            . ' :extension_value, :size_value, :checksum_value, :note_value, :author_value)'
        );
        $statement->execute([
            'document_value' => $documentId,
            'number_value' => $number,
            'stored_value' => $version['stored_name'],
            'original_value' => $version['original_name'],
            'mime_value' => $version['mime_type'],
            'extension_value' => $version['extension'],
            'size_value' => $version['file_size'],
            'checksum_value' => $version['checksum'],
            'note_value' => $version['note'] !== '' ? $version['note'] : null,
            'author_value' => $authorId,
        ]);
        $this->pdo->prepare('UPDATE ged_documents SET updated_at = CURRENT_TIMESTAMP WHERE id = :document_value')
            ->execute(['document_value' => $documentId]);

        return (int) $this->pdo->lastInsertId();
    }

    public function deleteVersion(int $versionId): bool
    {
        if ($versionId < 1) {
            return false;
        }
        $statement = $this->pdo->prepare('DELETE FROM ged_versions WHERE id = :version_value');
        $statement->execute(['version_value' => $versionId]);

        return $statement->rowCount() > 0;
    }

    public function countVersions(int $documentId): int
    {
        if ($documentId < 1) {
            return 0;
        }
        $statement = $this->pdo->prepare('SELECT COUNT(*) FROM ged_versions WHERE document_id = :document_id');
        $statement->execute(['document_id' => $documentId]);

        return (int) $statement->fetchColumn();
    }

    /**
     * @return array<int,array{id:int,label:string}>
     */
    public function clientOptions(): array
    {
        $rows = $this->pdo->query(
            'SELECT id, reference, display_name FROM clients ORDER BY display_name ASC, id ASC LIMIT 500'
        )->fetchAll();

        return array_map(static fn (array $row): array => [
            'id' => (int) $row['id'],
            'label' => trim((string) $row['reference'] . ' — ' . (string) $row['display_name'], ' —'),
        ], $rows);
    }

    /**
     * @return array<int,array{id:int,label:string,client_id:int}>
     */
    public function projectOptions(): array
    {
        $rows = $this->pdo->query(
            'SELECT p.id, p.reference, p.name, p.client_id, c.display_name AS client_name'
            . ' FROM projects p'
            . ' LEFT JOIN clients c ON c.id = p.client_id'
            . ' ORDER BY p.name ASC, p.id ASC LIMIT 500'
        )->fetchAll();

        return array_map(static fn (array $row): array => [
            'id' => (int) $row['id'],
            'label' => trim(
                (string) $row['reference'] . ' — ' . (string) $row['name']
                . ((string) ($row['client_name'] ?? '') !== '' ? ' · ' . (string) $row['client_name'] : ''),
                ' —·'
            ),
            'client_id' => (int) ($row['client_id'] ?? 0),
        ], $rows);
    }

    /**
     * @param array<int,array{id:int,label:string,client_id:int}> $projects
     * @return array<int,int> identifiant de projet ⇒ identifiant de client
     */
    public static function projectClients(array $projects): array
    {
        $map = [];
        foreach ($projects as $project) {
            $map[(int) $project['id']] = (int) $project['client_id'];
        }

        return $map;
    }

    /**
     * @return array<int,array{id:int,name:string}>
     */
    public function ownerOptions(): array
    {
        $rows = $this->pdo->query(
            'SELECT id, name FROM users WHERE is_active = 1 ORDER BY name ASC, id ASC LIMIT 200'
        )->fetchAll();

        return array_map(static fn (array $row): array => [
            'id' => (int) $row['id'],
            'name' => (string) $row['name'],
        ], $rows);
    }

    /** @return array<int,int> */
    public function clientIds(): array
    {
        return array_map(
            static fn (array $row): int => (int) $row['id'],
            $this->pdo->query('SELECT id FROM clients LIMIT 500')->fetchAll()
        );
    }
}
