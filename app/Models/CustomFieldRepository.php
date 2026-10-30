<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use PDO;

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

    public function updateDetails(int $id, string $label, bool $required, int $sortOrder): bool
    {
        $statement = $this->pdo->prepare(
            'UPDATE custom_field_definitions
             SET label = :label, is_required = :is_required, sort_order = :sort_order
             WHERE id = :id'
        );
        $statement->execute([
            'label' => $label,
            'is_required' => $required ? 1 : 0,
            'sort_order' => $sortOrder,
            'id' => $id,
        ]);
        return $statement->rowCount() > 0 || $this->findById($id) !== null;
    }

    public function setActive(int $id, bool $active): bool
    {
        $statement = $this->pdo->prepare(
            'UPDATE custom_field_definitions SET is_active = :is_active WHERE id = :id'
        );
        $statement->execute(['is_active' => $active ? 1 : 0, 'id' => $id]);
        return $statement->rowCount() > 0 || $this->findById($id) !== null;
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
