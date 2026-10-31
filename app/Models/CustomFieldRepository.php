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

    /** Corrige le type d'une définition créée avant la migration 0015 (type vide). */
    public function updateType(int $id, string $type): bool
    {
        $statement = $this->pdo->prepare(
            'UPDATE custom_field_definitions SET field_type = :field_type WHERE id = :id'
        );
        $statement->execute(['field_type' => $type, 'id' => $id]);

        return $statement->rowCount() > 0;
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
