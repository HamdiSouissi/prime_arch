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

    /**
     * Images générales du projet (carte « Images du projet »).
     *
     * Les images rattachées à un champ personnalisé de type « image » en sont exclues : elles
     * s'affichent dans le bloc de leur champ, avec leur propre formulaire d'envoi.
     *
     * @return array<int,array<string,mixed>>
     */
    public function forProject(int $projectId): array
    {
        $statement = $this->pdo->prepare(
            'SELECT i.id, i.project_id, i.original_name, i.stored_name, i.mime_type,
                    i.file_size, i.width, i.height, i.created_at, i.custom_field_key,
                    uploader.name AS uploaded_by_name
             FROM project_images i
             LEFT JOIN users uploader ON uploader.id = i.uploaded_by
             WHERE i.project_id = :project_id AND i.custom_field_key IS NULL
             ORDER BY i.created_at DESC, i.id DESC'
        );
        $statement->execute(['project_id' => $projectId]);
        return array_map(self::normalize(...), $statement->fetchAll());
    }

    /**
     * Images rattachées aux champs personnalisés, regroupées par clé de champ.
     *
     * @return array<string,array<int,array<string,mixed>>>
     */
    public function groupedByCustomField(int $projectId): array
    {
        $statement = $this->pdo->prepare(
            'SELECT i.id, i.project_id, i.original_name, i.stored_name, i.mime_type,
                    i.file_size, i.width, i.height, i.created_at, i.custom_field_key,
                    uploader.name AS uploaded_by_name
             FROM project_images i
             LEFT JOIN users uploader ON uploader.id = i.uploaded_by
             WHERE i.project_id = :project_id AND i.custom_field_key IS NOT NULL
             ORDER BY i.created_at DESC, i.id DESC'
        );
        $statement->execute(['project_id' => $projectId]);
        $grouped = [];
        foreach ($statement->fetchAll() as $row) {
            $image = self::normalize($row);
            $grouped[$image['custom_field_key']][] = $image;
        }

        return $grouped;
    }

    /** Nombre d'images déjà rattachées à un champ pour ce projet. */
    public function countForCustomField(int $projectId, string $fieldKey): int
    {
        $statement = $this->pdo->prepare(
            'SELECT COUNT(*) FROM project_images
             WHERE project_id = :project_id AND custom_field_key = :field_key'
        );
        $statement->execute(['project_id' => $projectId, 'field_key' => $fieldKey]);

        return (int) $statement->fetchColumn();
    }

    /** @param array<string,mixed> $image */
    public function create(int $projectId, array $image, ?int $uploadedBy, ?string $customFieldKey = null): int
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO project_images (
                project_id, original_name, stored_name, mime_type, file_size, width, height,
                uploaded_by, custom_field_key
             ) VALUES (
                :project_id, :original_name, :stored_name, :mime_type, :file_size, :width, :height,
                :uploaded_by, :custom_field_key
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
            'custom_field_key' => $customFieldKey,
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

    /** @return array<string,mixed>|null */
    public function findForCustomField(int $projectId, string $fieldKey, int $imageId): ?array
    {
        $statement = $this->pdo->prepare(
            'SELECT id, project_id, original_name, stored_name, mime_type,
                    file_size, width, height, created_at, custom_field_key
             FROM project_images
             WHERE id = :id AND project_id = :project_id AND custom_field_key = :field_key
             LIMIT 1'
        );
        $statement->execute(['id' => $imageId, 'project_id' => $projectId, 'field_key' => $fieldKey]);
        $row = $statement->fetch();
        return is_array($row) ? self::normalize($row) : null;
    }

    /** Supprime la ligne d'une image du projet ; renvoie true si une ligne a été retirée. */
    public function delete(int $projectId, int $imageId): bool
    {
        $statement = $this->pdo->prepare(
            'DELETE FROM project_images WHERE id = :id AND project_id = :project_id'
        );
        $statement->execute(['id' => $imageId, 'project_id' => $projectId]);

        return $statement->rowCount() > 0;
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
        $row['custom_field_key'] = isset($row['custom_field_key']) && $row['custom_field_key'] !== null
            ? (string) $row['custom_field_key']
            : null;
        return $row;
    }
}
