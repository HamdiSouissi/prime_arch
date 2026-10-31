<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use PDO;
use PDOException;

/**
 * Définitions des champs personnalisés des fiches clients et projets.
 */
final class CustomFieldRepository
{
    private PDO $pdo;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? Database::connection();
    }

    /** @return array<int,array<string,mixed>> */
    public function all(?string $entityType = null, bool $activeOnly = false): array
    {
        $conditions = [];
        $parameters = [];
        if ($entityType !== null) {
            $conditions[] = 'entity_type = :entity_type';
            $parameters['entity_type'] = $entityType;
        }
        if ($activeOnly) {
            $conditions[] = 'is_active = 1';
        }
        $sql = 'SELECT id, entity_type, field_key, label, field_type, options_json, is_required, sort_order, is_active
                FROM custom_field_definitions';
        if ($conditions !== []) {
            $sql .= ' WHERE ' . implode(' AND ', $conditions);
        }
        $sql .= ' ORDER BY entity_type, sort_order, id';
        $statement = $this->pdo->prepare($sql);
        $statement->execute($parameters);
        return array_map(self::normalize(...), $statement->fetchAll());
    }

    public function findById(int $id): ?array
    {
        $statement = $this->pdo->prepare(
            'SELECT id, entity_type, field_key, label, field_type, options_json, is_required, sort_order, is_active
             FROM custom_field_definitions WHERE id = :id LIMIT 1'
        );
        $statement->execute(['id' => $id]);
        $row = $statement->fetch();
        return is_array($row) ? self::normalize($row) : null;
    }

    /** @param array<string,mixed> $field */
    public function create(array $field): int
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO custom_field_definitions
                (entity_type, field_key, label, field_type, options_json, is_required, sort_order, is_active)
             VALUES (:entity_type, :field_key, :label, :field_type, :options_json, :is_required, :sort_order, 1)'
        );
        $statement->execute([
            'entity_type' => $field['entity_type'],
            'field_key' => $field['field_key'],
            'label' => $field['label'],
            'field_type' => $field['field_type'],
            'options_json' => $field['options'] === []
                ? null
                : json_encode($field['options'], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
            'is_required' => $field['is_required'] ? 1 : 0,
            'sort_order' => $field['sort_order'],
        ]);
        return (int) $this->pdo->lastInsertId();
    }

    /**
     * Valeurs acceptées par l'ENUM `field_type`, ou null si la colonne est absente.
     *
     * Une base antérieure à la migration 0015 n'accepte ni « image » ni « file » : MySQL
     * tronque alors la valeur en chaîne vide (mode non strict), et le champ apparaît comme une
     * simple zone de texte sur les formulaires. Le contrôleur s'appuie sur cette liste pour
     * refuser la création avant d'écrire une définition inutilisable.
     *
     * @return array<int,string>|null
     */
    public function acceptedTypes(): ?array
    {
        try {
            $statement = $this->pdo->prepare(
                "SELECT COLUMN_TYPE FROM information_schema.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'custom_field_definitions'
                   AND COLUMN_NAME = 'field_type' LIMIT 1"
            );
            $statement->execute();
        } catch (PDOException) {
            return null;
        }
        $columnType = $statement->fetchColumn();
        if (!is_string($columnType) || preg_match_all("/'([a-z_]+)'/i", $columnType, $matches) < 1) {
            return null;
        }

        return array_values(array_unique(array_map('strtolower', $matches[1])));
    }

    /**
     * Met à jour une définition : libellé, type, options, caractère obligatoire et ordre.
     *
     * La clé technique et le type de fiche ne changent jamais : ils servent de clé de stockage
     * dans la colonne JSON des fiches (et de jointure pour les images et les fichiers).
     *
     * @param array{label:string,field_type:string,options:array<int,string>,is_required:bool,sort_order:int} $data
     */
    public function updateDefinition(int $id, array $data): bool
    {
        $statement = $this->pdo->prepare(
            'UPDATE custom_field_definitions
             SET label = :label, field_type = :field_type, options_json = :options_json,
                 is_required = :is_required, sort_order = :sort_order
             WHERE id = :id'
        );
        $statement->execute([
            'label' => $data['label'],
            'field_type' => $data['field_type'],
            'options_json' => $data['options'] === []
                ? null
                : json_encode($data['options'], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
            'is_required' => $data['is_required'] ? 1 : 0,
            'sort_order' => $data['sort_order'],
            'id' => $id,
        ]);

        return $statement->rowCount() > 0 || $this->findById($id) !== null;
    }

    public function delete(int $id): bool
    {
        $statement = $this->pdo->prepare('DELETE FROM custom_field_definitions WHERE id = :id');
        $statement->execute(['id' => $id]);

        return $statement->rowCount() > 0;
    }

    /**
     * Nombre de fiches portant une valeur pour cette clé.
     *
     * Les valeurs des types saisis vivent dans la colonne JSON `custom_fields` de chaque fiche :
     * supprimer une définition sans les retirer laisserait des données orphelines, visibles
     * nulle part et jamais purgées.
     */
    public function countStoredValues(string $entityType, string $fieldKey): int
    {
        $table = self::valueTable($entityType);
        if ($table === null || !self::isSafeKey($fieldKey)) {
            return 0;
        }
        $statement = $this->pdo->prepare(
            'SELECT COUNT(*) FROM ' . $table . " WHERE JSON_CONTAINS_PATH(custom_fields, 'one', :path)"
        );
        $statement->execute(['path' => self::jsonPath($fieldKey)]);

        return (int) $statement->fetchColumn();
    }

    /** Retire la valeur de cette clé de toutes les fiches ; renvoie le nombre de fiches modifiées. */
    public function removeStoredValues(string $entityType, string $fieldKey): int
    {
        $table = self::valueTable($entityType);
        if ($table === null || !self::isSafeKey($fieldKey)) {
            return 0;
        }
        $statement = $this->pdo->prepare(
            "UPDATE " . $table . " SET custom_fields = JSON_REMOVE(custom_fields, :path)
             WHERE JSON_CONTAINS_PATH(custom_fields, 'one', :presence)"
        );
        $statement->execute([
            'path' => self::jsonPath($fieldKey),
            'presence' => self::jsonPath($fieldKey),
        ]);

        return $statement->rowCount();
    }

    public function setActive(int $id, bool $active): bool
    {
        $statement = $this->pdo->prepare(
            'UPDATE custom_field_definitions SET is_active = :is_active WHERE id = :id'
        );
        $statement->execute(['is_active' => $active ? 1 : 0, 'id' => $id]);

        return $statement->rowCount() > 0 || $this->findById($id) !== null;
    }

    /** Table des fiches qui porte la colonne JSON, ou null pour un type inconnu. */
    private static function valueTable(string $entityType): ?string
    {
        return match ($entityType) {
            'client' => 'clients',
            'project' => 'projects',
            default => null,
        };
    }

    /** Les clés sont validées à la création ; cette garde protège le chemin JSON construit. */
    private static function isSafeKey(string $fieldKey): bool
    {
        return preg_match('/^[a-z][a-z0-9_]{1,79}$/', $fieldKey) === 1;
    }

    private static function jsonPath(string $fieldKey): string
    {
        return '$."' . $fieldKey . '"';
    }

    private static function normalize(array $row): array
    {
        $options = $row['options_json'] ?? [];
        if (is_string($options)) {
            $decoded = json_decode($options, true);
            $options = is_array($decoded) ? $decoded : [];
        }
        return [
            'id' => (int) $row['id'],
            'entity_type' => (string) $row['entity_type'],
            'field_key' => (string) $row['field_key'],
            'label' => (string) $row['label'],
            'field_type' => (string) $row['field_type'],
            'options' => array_values(array_filter($options, 'is_string')),
            'is_required' => (bool) $row['is_required'],
            'sort_order' => (int) $row['sort_order'],
            'is_active' => (bool) $row['is_active'],
        ];
    }
}
