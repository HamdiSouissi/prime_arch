<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use PDO;
use PDOException;

/** Accès préparé aux images privées associées aux projets. */
final class ProjectImageRepository
{
    private PDO $pdo;
    /** Évite de répéter l'avertissement de schéma non migré à chaque requête. */
    private static bool $schemaWarningLogged = false;

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
        try {
            return $this->images($projectId, 'general');
        } catch (PDOException $error) {
            // Colonne `custom_field_key` (migration 0014) ou table `project_images` absente :
            // la fiche projet doit rester consultable pendant une mise à jour. On retombe sur
            // la requête historique, sans distinction de champ.
            self::warnSchemaGap($error);
            try {
                return $this->images($projectId, 'legacy');
            } catch (PDOException) {
                return [];
            }
        }
    }

    /**
     * Images d'un projet, filtrées par rattachement.
     *
     * @param string $scope « general » (aucun champ), « custom » (un champ) ou « legacy »
     *                      (base antérieure à la migration 0014)
     * @return array<int,array<string,mixed>>
     */
    private function images(int $projectId, string $scope): array
    {
        $hasField = $scope !== 'legacy';
        $sql = 'SELECT i.id, i.project_id, i.original_name, i.stored_name, i.mime_type,
                       i.file_size, i.width, i.height, i.created_at'
            . ($hasField ? ', i.custom_field_key' : '') . ',
                       uploader.name AS uploaded_by_name
                FROM project_images i
                LEFT JOIN users uploader ON uploader.id = i.uploaded_by
                WHERE i.project_id = :project_id';
        if ($scope === 'general') {
            $sql .= ' AND i.custom_field_key IS NULL';
        } elseif ($scope === 'custom') {
            $sql .= ' AND i.custom_field_key IS NOT NULL';
        }
        $sql .= ' ORDER BY i.created_at DESC, i.id DESC';
        $statement = $this->pdo->prepare($sql);
        $statement->execute(['project_id' => $projectId]);

        return array_map(self::normalize(...), $statement->fetchAll());
    }

    /**
     * Toutes les images du projet, champs personnalisés compris.
     *
     * La suppression d'un projet doit connaître chaque fichier du disque, y compris ceux des
     * galeries rattachées à un champ : `forProject()` les exclut volontairement.
     *
     * @return array<int,array<string,mixed>>
     */
    public function allForProject(int $projectId): array
    {
        try {
            return $this->images($projectId, 'all');
        } catch (PDOException $error) {
            self::warnSchemaGap($error);
            try {
                return $this->images($projectId, 'legacy');
            } catch (PDOException) {
                return [];
            }
        }
    }

    /** Retire les métadonnées de toutes les images d'un projet ; renvoie le nombre de lignes. */
    public function deleteForProject(int $projectId): int
    {
        $statement = $this->pdo->prepare('DELETE FROM project_images WHERE project_id = :project_id');
        $statement->execute(['project_id' => $projectId]);

        return $statement->rowCount();
    }

    /**
     * Images rattachées aux champs personnalisés, regroupées par clé de champ.
     *
     * @return array<string,array<int,array<string,mixed>>>
     */
    public function groupedByCustomField(int $projectId): array
    {
        try {
            $rows = $this->images($projectId, 'custom');
        } catch (PDOException $error) {
            self::warnSchemaGap($error);

            return [];
        }
        $grouped = [];
        foreach ($rows as $image) {
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
    /**
     * Toutes les images d'une clé de champ, tous projets confondus.
     *
     * Sert à la suppression d'une définition : les fichiers privés doivent être retirés du
     * disque avant leurs métadonnées, projet par projet.
     *
     * @return array<int,array<string,mixed>>
     */
    public function allForCustomField(string $fieldKey): array
    {
        $statement = $this->pdo->prepare(
            'SELECT id, project_id, stored_name, original_name, custom_field_key
             FROM project_images WHERE custom_field_key = :field_key ORDER BY project_id, id'
        );
        $statement->execute(['field_key' => $fieldKey]);

        return array_map(self::normalize(...), $statement->fetchAll());
    }

    /** Supprime les métadonnées des images d'une clé de champ ; renvoie le nombre de lignes. */
    public function deleteForCustomField(string $fieldKey): int
    {
        $statement = $this->pdo->prepare('DELETE FROM project_images WHERE custom_field_key = :field_key');
        $statement->execute(['field_key' => $fieldKey]);

        return $statement->rowCount();
    }

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

    /**
     * Signale une fois par requête que le schéma des images de projet est incomplet.
     *
     * Le message vise l'exploitant : la correction consiste à rejouer `php bin/migrate.php`
     * (migrations 0013 et 0014), ce que `php bin/preflight.php` rappelle également.
     */
    private static function warnSchemaGap(PDOException $error): void
    {
        if (self::$schemaWarningLogged) {
            return;
        }
        self::$schemaWarningLogged = true;
        error_log('project_images : schéma non migré (0013/0014) — fiche projet dégradée : ' . $error->getMessage());
    }
}
