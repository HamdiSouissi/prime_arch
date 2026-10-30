<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use PDO;
use Throwable;

/**
 * Accès préparé aux projets, à leurs phases et à leur configuration.
 */
final class ProjectRepository
{
    private PDO $pdo;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? Database::connection();
    }

    /** @param array<string,string> $filters
     *  @return array{items:array<int,array<string,mixed>>,total:int}
     */
    public function paginate(array $filters, int $limit = 20, int $offset = 0): array
    {
        [$where, $parameters] = $this->listConditions($filters);
        $count = $this->pdo->prepare(
            'SELECT COUNT(*) FROM projects p INNER JOIN clients c ON c.id = p.client_id' . $where
        );
        $count->execute($parameters);
        $total = (int) $count->fetchColumn();

        $statement = $this->pdo->prepare(
            "SELECT p.id, p.reference, p.client_id, p.name, p.project_type, p.status, p.city,
                    p.postal_code, p.start_date, p.target_end_date, p.updated_at,
                    c.display_name AS client_name,
                    (SELECT phase_name FROM project_phase_assignments a
                     WHERE a.project_id = p.id AND a.status = 'in_progress'
                     ORDER BY a.sort_order, a.id LIMIT 1) AS current_phase
             FROM projects p INNER JOIN clients c ON c.id = p.client_id"
            . $where . ' ORDER BY p.updated_at DESC, p.id DESC LIMIT ' . max(1, $limit)
            . ' OFFSET ' . max(0, $offset)
        );
        $statement->execute($parameters);
        return [
            'items' => array_map(self::normalize(...), $statement->fetchAll()),
            'total' => $total,
        ];
    }

    /** @return array<string,mixed>|null */
    public function findById(int $id): ?array
    {
        $statement = $this->pdo->prepare(
            'SELECT p.*, c.display_name AS client_name, c.reference AS client_reference,
                    g.name_fr AS governorate_name, g.name_ar AS governorate_name_ar,
                    d.name_latin AS delegation_name, d.name_ar AS delegation_name_ar,
                    creator.name AS created_by_name
             FROM projects p
             INNER JOIN clients c ON c.id = p.client_id
             LEFT JOIN governorates g ON g.id = p.governorate_id
             LEFT JOIN delegations d ON d.id = p.delegation_id
             LEFT JOIN users creator ON creator.id = p.created_by
             WHERE p.id = :id LIMIT 1'
        );
        $statement->execute(['id' => $id]);
        $row = $statement->fetch();
        return is_array($row) ? self::normalize($row) : null;
    }

    /** @return array<int,array<string,mixed>> */
    public function phaseAssignments(int $projectId): array
    {
        $statement = $this->pdo->prepare(
            'SELECT id, project_id, project_phase_id, phase_name, sort_order, status, started_at, completed_at
             FROM project_phase_assignments WHERE project_id = :project_id ORDER BY sort_order, id'
        );
        $statement->execute(['project_id' => $projectId]);
        return array_map(static fn (array $row): array => [
            'id' => (int) $row['id'],
            'project_id' => (int) $row['project_id'],
            'project_phase_id' => (int) $row['project_phase_id'],
            'phase_name' => (string) $row['phase_name'],
            'sort_order' => (int) $row['sort_order'],
            'status' => (string) $row['status'],
            'started_at' => (string) ($row['started_at'] ?? ''),
            'completed_at' => (string) ($row['completed_at'] ?? ''),
        ], $statement->fetchAll());
    }

    /** @return array<int,array<string,mixed>> */
    public function phases(bool $activeOnly = false): array
    {
        $sql = 'SELECT id, phase_key, name, sort_order, is_active, is_system, created_at, updated_at FROM project_phases';
        if ($activeOnly) {
            $sql .= ' WHERE is_active = 1';
        }
        $sql .= ' ORDER BY sort_order, id';
        $rows = $this->pdo->query($sql)->fetchAll();
        return array_map(static fn (array $row): array => [
            'id' => (int) $row['id'],
            'phase_key' => (string) $row['phase_key'],
            'name' => (string) $row['name'],
            'sort_order' => (int) $row['sort_order'],
            'is_active' => (bool) $row['is_active'],
            'is_system' => (bool) $row['is_system'],
            'created_at' => (string) $row['created_at'],
            'updated_at' => (string) $row['updated_at'],
        ], $rows);
    }

    /** @param array<string,mixed> $values */
    public function create(array $values, ?int $createdBy): int
    {
        $ownsTransaction = !$this->pdo->inTransaction();
        if ($ownsTransaction) {
            $this->pdo->beginTransaction();
        }
        try {
            $statement = $this->pdo->prepare(
                'INSERT INTO projects (
                    reference, client_id, name, project_type, description, status,
                    address_line, city, governorate_id, delegation_id, postal_code,
                    start_date, target_end_date, custom_fields, created_by
                 ) VALUES (
                    NULL, :client_id, :name, :project_type, :description, :status,
                    :address_line, :city, :governorate_id, :delegation_id, :postal_code,
                    :start_date, :target_end_date, :custom_fields, :created_by
                 )'
            );
            $statement->execute(self::parameters($values, $createdBy));
            $id = (int) $this->pdo->lastInsertId();
            $reference = sprintf('PA-%s-%06d', date('Y'), $id);
            $setReference = $this->pdo->prepare('UPDATE projects SET reference = :reference WHERE id = :id');
            $setReference->execute(['reference' => $reference, 'id' => $id]);

            $phaseRows = $this->pdo->query(
                'SELECT id, name, sort_order FROM project_phases WHERE is_active = 1 ORDER BY sort_order, id'
            )->fetchAll();
            if ($phaseRows !== []) {
                $assign = $this->pdo->prepare(
                    "INSERT INTO project_phase_assignments (project_id, project_phase_id, phase_name, sort_order, status)
                     VALUES (:project_id, :project_phase_id, :phase_name, :sort_order, 'pending')"
                );
                foreach ($phaseRows as $phase) {
                    $assign->execute([
                        'project_id' => $id,
                        'project_phase_id' => (int) $phase['id'],
                        'phase_name' => (string) $phase['name'],
                        'sort_order' => (int) $phase['sort_order'],
                    ]);
                }
            }
            if ($ownsTransaction) {
                $this->pdo->commit();
            }
            return $id;
        } catch (Throwable $error) {
            if ($ownsTransaction && $this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $error;
        }
    }

    /** @param array<string,mixed> $values */
    public function update(int $id, array $values): bool
    {
        $statement = $this->pdo->prepare(
            'UPDATE projects SET client_id = :client_id, name = :name, project_type = :project_type,
                description = :description, status = :status, address_line = :address_line, city = :city,
                governorate_id = :governorate_id, delegation_id = :delegation_id, postal_code = :postal_code,
                start_date = :start_date, target_end_date = :target_end_date, custom_fields = :custom_fields
             WHERE id = :id'
        );
        $parameters = self::parameters($values, null);
        unset($parameters['created_by']);
        $parameters['id'] = $id;
        $statement->execute($parameters);
        return $statement->rowCount() > 0 || $this->findById($id) !== null;
    }

    /** @return string updated|missing|previous_incomplete|active_exists|invalid */
    public function updatePhaseStatus(int $projectId, int $assignmentId, string $action): string
    {
        $ownsTransaction = !$this->pdo->inTransaction();
        if ($ownsTransaction) {
            $this->pdo->beginTransaction();
        }
        try {
            $project = $this->pdo->prepare('SELECT status FROM projects WHERE id = :id FOR UPDATE');
            $project->execute(['id' => $projectId]);
            $projectStatus = $project->fetchColumn();
            if ($projectStatus === false) {
                if ($ownsTransaction) {
                    $this->pdo->rollBack();
                }
                return 'missing';
            }
            $find = $this->pdo->prepare(
                'SELECT id, status, sort_order FROM project_phase_assignments
                 WHERE id = :id AND project_id = :project_id FOR UPDATE'
            );
            $find->execute(['id' => $assignmentId, 'project_id' => $projectId]);
            $assignment = $find->fetch();
            if (!is_array($assignment)) {
                if ($ownsTransaction) {
                    $this->pdo->rollBack();
                }
                return 'missing';
            }
            if (in_array($projectStatus, ['completed', 'cancelled'], true)) {
                if ($ownsTransaction) {
                    $this->pdo->rollBack();
                }
                return 'invalid';
            }

            if ($action === 'start' && $assignment['status'] === 'pending') {
                $active = $this->pdo->prepare(
                    "SELECT COUNT(*) FROM project_phase_assignments
                     WHERE project_id = :project_id AND status = 'in_progress'"
                );
                $active->execute(['project_id' => $projectId]);
                if ((int) $active->fetchColumn() > 0) {
                    if ($ownsTransaction) {
                        $this->pdo->rollBack();
                    }
                    return 'active_exists';
                }
                $previous = $this->pdo->prepare(
                    "SELECT COUNT(*) FROM project_phase_assignments
                     WHERE project_id = :project_id
                       AND (sort_order < :previous_sort_order
                            OR (sort_order = :same_sort_order AND id < :assignment_id))
                       AND status NOT IN ('completed', 'skipped')"
                );
                $previous->execute([
                    'project_id' => $projectId,
                    'previous_sort_order' => (int) $assignment['sort_order'],
                    'same_sort_order' => (int) $assignment['sort_order'],
                    'assignment_id' => $assignmentId,
                ]);
                if ((int) $previous->fetchColumn() > 0) {
                    if ($ownsTransaction) {
                        $this->pdo->rollBack();
                    }
                    return 'previous_incomplete';
                }
                $update = $this->pdo->prepare(
                    "UPDATE project_phase_assignments
                     SET status = 'in_progress', started_at = COALESCE(started_at, CURRENT_TIMESTAMP), completed_at = NULL
                     WHERE id = :id"
                );
                $update->execute(['id' => $assignmentId]);
            } elseif ($action === 'complete' && $assignment['status'] === 'in_progress') {
                $update = $this->pdo->prepare(
                    "UPDATE project_phase_assignments SET status = 'completed', completed_at = CURRENT_TIMESTAMP
                     WHERE id = :id"
                );
                $update->execute(['id' => $assignmentId]);
            } else {
                if ($ownsTransaction) {
                    $this->pdo->rollBack();
                }
                return 'invalid';
            }
            if ($ownsTransaction) {
                $this->pdo->commit();
            }
            return 'updated';
        } catch (Throwable $error) {
            if ($ownsTransaction && $this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $error;
        }
    }

    public function createPhase(string $name, int $sortOrder): int
    {
        $phaseKey = 'custom_' . bin2hex(random_bytes(8));
        $statement = $this->pdo->prepare(
            'INSERT INTO project_phases (phase_key, name, sort_order, is_active, is_system)
             VALUES (:phase_key, :name, :sort_order, 1, 0)'
        );
        $statement->execute([
            'phase_key' => $phaseKey,
            'name' => $name,
            'sort_order' => $sortOrder,
        ]);
        return (int) $this->pdo->lastInsertId();
    }

    public function updatePhase(int $id, string $name, int $sortOrder, bool $active): bool
    {
        $statement = $this->pdo->prepare(
            'UPDATE project_phases SET name = :name, sort_order = :sort_order, is_active = :is_active WHERE id = :id'
        );
        $statement->execute([
            'name' => $name,
            'sort_order' => $sortOrder,
            'is_active' => $active ? 1 : 0,
            'id' => $id,
        ]);
        return $statement->rowCount() > 0 || $this->phaseExists($id);
    }

    public function phaseExists(int $id): bool
    {
        $statement = $this->pdo->prepare('SELECT 1 FROM project_phases WHERE id = :id LIMIT 1');
        $statement->execute(['id' => $id]);
        return $statement->fetchColumn() !== false;
    }

    /** @param array<string,string> $filters
     *  @return array{string,array<string,mixed>}
     */
    private function listConditions(array $filters): array
    {
        $conditions = [];
        $parameters = [];
        $search = trim((string) ($filters['search'] ?? ''));
        if ($search !== '') {
            $pattern = '%' . str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $search) . '%';
            $conditions[] = "(p.reference LIKE :search_reference ESCAPE '!'
                OR p.name LIKE :search_name ESCAPE '!'
                OR p.project_type LIKE :search_type ESCAPE '!'
                OR c.display_name LIKE :search_client ESCAPE '!'
                OR p.city LIKE :search_city ESCAPE '!')";
            $parameters += [
                'search_reference' => $pattern,
                'search_name' => $pattern,
                'search_type' => $pattern,
                'search_client' => $pattern,
                'search_city' => $pattern,
            ];
        }
        if (($filters['status'] ?? '') !== '') {
            $conditions[] = 'p.status = :status';
            $parameters['status'] = $filters['status'];
        }
        if (($filters['client_id'] ?? '') !== '') {
            $conditions[] = 'p.client_id = :client_id';
            $parameters['client_id'] = (int) $filters['client_id'];
        }
        return [$conditions === [] ? '' : ' WHERE ' . implode(' AND ', $conditions), $parameters];
    }

    /** @param array<string,mixed> $values
     *  @return array<string,mixed>
     */
    private static function parameters(array $values, ?int $createdBy): array
    {
        return [
            'client_id' => (int) $values['client_id'],
            'name' => $values['name'],
            'project_type' => $values['project_type'] !== '' ? $values['project_type'] : null,
            'description' => $values['description'] !== '' ? $values['description'] : null,
            'status' => $values['status'],
            'address_line' => $values['address_line'] !== '' ? $values['address_line'] : null,
            'city' => $values['city'] !== '' ? $values['city'] : null,
            'governorate_id' => $values['governorate_id'] !== '' ? (int) $values['governorate_id'] : null,
            'delegation_id' => $values['delegation_id'] !== '' ? (int) $values['delegation_id'] : null,
            'postal_code' => $values['postal_code'] !== '' ? $values['postal_code'] : null,
            'start_date' => $values['start_date'] !== '' ? $values['start_date'] : null,
            'target_end_date' => $values['target_end_date'] !== '' ? $values['target_end_date'] : null,
            'custom_fields' => json_encode((object) ($values['custom_fields'] ?? []), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
            'created_by' => $createdBy,
        ];
    }

    private static function normalize(array $row): array
    {
        foreach (['id', 'client_id', 'governorate_id', 'delegation_id', 'created_by'] as $integerKey) {
            if (isset($row[$integerKey])) {
                $row[$integerKey] = (int) $row[$integerKey];
            }
        }
        $customFields = $row['custom_fields'] ?? [];
        if (is_string($customFields)) {
            $decoded = json_decode($customFields, true);
            $customFields = is_array($decoded) ? $decoded : [];
        }
        $row['custom_fields'] = is_array($customFields) ? $customFields : [];
        return $row;
    }
}
