<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use PDO;

/** Accès préparé aux images privées associées aux projets. */
final class ProjectImageRepository
{
    private PDO $pdo;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? Database::connection();
    }

    /** @return array<int,array<string,mixed>> */
    public function forProject(int $projectId): array
    {
        $statement = $this->pdo->prepare(
            'SELECT i.id, i.project_id, i.original_name, i.stored_name, i.mime_type,
                    i.file_size, i.width, i.height, i.created_at, uploader.name AS uploaded_by_name
             FROM project_images i
             LEFT JOIN users uploader ON uploader.id = i.uploaded_by
             WHERE i.project_id = :project_id
             ORDER BY i.created_at DESC, i.id DESC'
        );
        $statement->execute(['project_id' => $projectId]);
        return array_map(self::normalize(...), $statement->fetchAll());
    }

    /** @param array<string,mixed> $image */
    public function create(int $projectId, array $image, ?int $uploadedBy): int
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO project_images (
                project_id, original_name, stored_name, mime_type, file_size, width, height, uploaded_by
             ) VALUES (
                :project_id, :original_name, :stored_name, :mime_type, :file_size, :width, :height, :uploaded_by
             )'
        );
        $statement->execute([
            'project_id' => $projectId,
            'original_name' => $image['original_name'],
            'stored_name' => $image['stored_name'],
            'mime_type' => $image['mime_type'],
            'file_size' => $image['file_size'],
            'width' => $image['width'],
            'height' => $image['height'],
            'uploaded_by' => $uploadedBy,
        ]);
        return (int) $this->pdo->lastInsertId();
    }

    /** @return array<string,mixed>|null */
    public function findForProject(int $projectId, int $imageId): ?array
    {
        $statement = $this->pdo->prepare(
            'SELECT id, project_id, original_name, stored_name, mime_type,
                    file_size, width, height, created_at
             FROM project_images
             WHERE id = :id AND project_id = :project_id
             LIMIT 1'
        );
        $statement->execute(['id' => $imageId, 'project_id' => $projectId]);
        $row = $statement->fetch();
        return is_array($row) ? self::normalize($row) : null;
    }

    /** @param array<string,mixed> $row
     *  @return array<string,mixed>
     */
    private static function normalize(array $row): array
    {
        foreach (['id', 'project_id', 'file_size', 'width', 'height'] as $field) {
            $row[$field] = (int) $row[$field];
        }
        foreach (['original_name', 'stored_name', 'mime_type', 'created_at'] as $field) {
            $row[$field] = (string) $row[$field];
        }
        $row['uploaded_by_name'] = (string) ($row['uploaded_by_name'] ?? '');
        return $row;
    }
}
