<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use PDO;
use PDOException;

/**
 * Accès préparé aux fichiers CAD privés rattachés aux champs personnalisés des projets.
 *
 * Chaque ligne est liée à une fiche projet et à la clé technique de son champ : les fichiers
 * d'un champ ne sont jamais mélangés avec ceux d'un autre. Le contenu binaire vit dans
 * `storage/uploads/projects` ; la table ne conserve que les métadonnées et l'empreinte.
 */
final class ProjectFileRepository
{
    private PDO $pdo;
    /** Évite de répéter l'avertissement de schéma non migré à chaque requête. */
    private static bool $schemaWarningLogged = false;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? Database::connection();
    }

    /**
     * Fichiers rattachés aux champs personnalisés, regroupés par clé de champ.
     *
     * Une base dont les migrations ne sont pas encore appliquées (table `project_files`
     * absente) renvoie une liste vide plutôt qu'une erreur : la fiche projet reste
     * consultable pendant une mise à jour, et `php bin/preflight.php` signale l'écart.
     *
     * @return array<string,array<int,array<string,mixed>>>
     */
    public function groupedByCustomField(int $projectId): array
    {
        try {
            $statement = $this->pdo->prepare(
                'SELECT f.id, f.project_id, f.custom_field_key, f.original_name, f.stored_name,
                        f.extension, f.mime_type, f.file_size, f.checksum, f.created_at,
                        uploader.name AS uploaded_by_name
                 FROM project_files f
                 LEFT JOIN users uploader ON uploader.id = f.uploaded_by
                 WHERE f.project_id = :project_id
                 ORDER BY f.created_at DESC, f.id DESC'
            );
            $statement->execute(['project_id' => $projectId]);
        } catch (PDOException $error) {
            self::warnSchemaGap($error);

            return [];
        }

        $grouped = [];
        foreach ($statement->fetchAll() as $row) {
            $file = self::normalize($row);
            $grouped[$file['custom_field_key']][] = $file;
        }

        return $grouped;
    }

    /** Nombre de fichiers déjà rattachés à un champ pour ce projet. */
    public function countForCustomField(int $projectId, string $fieldKey): int
    {
        $statement = $this->pdo->prepare(
            'SELECT COUNT(*) FROM project_files
             WHERE project_id = :project_id AND custom_field_key = :field_key'
        );
        $statement->execute(['project_id' => $projectId, 'field_key' => $fieldKey]);

        return (int) $statement->fetchColumn();
    }

    /** @param array<string,mixed> $file */
    public function create(int $projectId, array $file, ?int $uploadedBy, string $customFieldKey): int
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO project_files (
                project_id, custom_field_key, original_name, stored_name, extension,
                mime_type, file_size, checksum, uploaded_by
             ) VALUES (
                :project_id, :custom_field_key, :original_name, :stored_name, :extension,
                :mime_type, :file_size, :checksum, :uploaded_by
             )'
        );
        $statement->execute([
            'project_id' => $projectId,
            'custom_field_key' => $customFieldKey,
            'original_name' => $file['original_name'],
            'stored_name' => $file['stored_name'],
            'extension' => $file['extension'],
            'mime_type' => $file['mime_type'],
            'file_size' => $file['file_size'],
            'checksum' => $file['checksum'],
            'uploaded_by' => $uploadedBy,
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    /** @return array<string,mixed>|null */
    public function findForCustomField(int $projectId, string $fieldKey, int $fileId): ?array
    {
        $statement = $this->pdo->prepare(
            'SELECT id, project_id, custom_field_key, original_name, stored_name, extension,
                    mime_type, file_size, checksum, created_at
             FROM project_files
             WHERE id = :id AND project_id = :project_id AND custom_field_key = :field_key
             LIMIT 1'
        );
        $statement->execute(['id' => $fileId, 'project_id' => $projectId, 'field_key' => $fieldKey]);
        $row = $statement->fetch();

        return is_array($row) ? self::normalize($row) : null;
    }

    /** Fichier d'un projet, quel que soit son champ (route de téléchargement). */
    public function findForProject(int $projectId, int $fileId): ?array
    {
        $statement = $this->pdo->prepare(
            'SELECT id, project_id, custom_field_key, original_name, stored_name, extension,
                    mime_type, file_size, checksum, created_at
             FROM project_files WHERE id = :id AND project_id = :project_id LIMIT 1'
        );
        $statement->execute(['id' => $fileId, 'project_id' => $projectId]);
        $row = $statement->fetch();

        return is_array($row) ? self::normalize($row) : null;
    }

    public function delete(int $projectId, int $fileId): bool
    {
        $statement = $this->pdo->prepare(
            'DELETE FROM project_files WHERE id = :id AND project_id = :project_id'
        );
        $statement->execute(['id' => $fileId, 'project_id' => $projectId]);

        return $statement->rowCount() > 0;
    }

    /** @param array<string,mixed> $row
     *  @return array<string,mixed>
     */
    private static function normalize(array $row): array
    {
        $row['id'] = (int) $row['id'];
        $row['project_id'] = (int) $row['project_id'];
        $row['file_size'] = (int) $row['file_size'];
        foreach (['custom_field_key', 'original_name', 'stored_name', 'extension', 'mime_type', 'checksum'] as $key) {
            $row[$key] = (string) ($row[$key] ?? '');
        }

        return $row;
    }

    private static function warnSchemaGap(PDOException $error): void
    {
        if (self::$schemaWarningLogged) {
            return;
        }
        self::$schemaWarningLogged = true;
        // Diagnostic lisible dans storage/logs/php-error.log : la correction est une migration.
        error_log('project_files indisponible (migration 0015 non appliquée ?) : ' . $error->getMessage());
    }
}
