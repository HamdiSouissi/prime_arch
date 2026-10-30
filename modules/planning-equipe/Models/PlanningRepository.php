<?php
declare(strict_types=1);

namespace Modules\PlanningEquipe\Models;

use App\Core\Database;
use PDO;

/**
 * Accès PDO aux affectations, congés et lectures de temps.
 *
 * Les tables du cœur (`users`, `roles`, `projects`, `clients`, `project_phase_assignments`,
 * `time_entries`) sont uniquement lues : le module n’écrit que dans `planning_*`.
 */
final class PlanningRepository
{
    private PDO $pdo;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? Database::connection();
    }

    /** @return array<int,array<string,mixed>> Collaborateurs actifs et leur rôle. */
    public function collaborators(): array
    {
        $statement = $this->pdo->query(
            'SELECT u.id, u.name, u.is_active, r.label AS role_label
             FROM users u
             LEFT JOIN roles r ON r.id = u.role_id
             WHERE u.is_active = 1
             ORDER BY u.name'
        );

        return $statement->fetchAll();
    }

    /** @return array<int,array<string,mixed>> Projets non annulés, pour les listes déroulantes. */
    public function projects(): array
    {
        $statement = $this->pdo->query(
            "SELECT p.id, p.name, p.reference, c.display_name AS client_name
             FROM projects p
             INNER JOIN clients c ON c.id = p.client_id
             WHERE p.status <> 'cancelled'
             ORDER BY p.updated_at DESC, p.id DESC
             LIMIT 300"
        );

        return $statement->fetchAll();
    }

    /** @return array<int,array<string,mixed>> Phases de projet, pour une affectation facultative. */
    public function phaseAssignments(): array
    {
        $statement = $this->pdo->query(
            'SELECT a.id, a.phase_name, a.project_id, p.name AS project_name
             FROM project_phase_assignments a
             INNER JOIN projects p ON p.id = a.project_id
             ORDER BY p.name, a.sort_order, a.id
             LIMIT 500'
        );

        return $statement->fetchAll();
    }

    /**
     * Affectations qui chevauchent la période.
     *
     * @return array<int,array<string,mixed>>
     */
    public function affectations(string $from, string $to, ?int $userId, ?int $projectId = null): array
    {
        $conditions = ['a.start_date <= :period_end', 'a.end_date >= :period_start'];
        $parameters = ['period_end' => $to, 'period_start' => $from];
        if ($userId !== null) {
            $conditions[] = 'a.user_id = :user_id';
            $parameters['user_id'] = $userId;
        }
        if ($projectId !== null) {
            $conditions[] = 'a.project_id = :project_id';
            $parameters['project_id'] = $projectId;
        }

        $statement = $this->pdo->prepare(
            'SELECT a.id, a.user_id, a.project_id, a.project_phase_assignment_id,
                    a.start_date, a.end_date, a.daily_hours, a.note,
                    u.name AS user_name, p.name AS project_name, ph.phase_name
             FROM planning_affectations a
             INNER JOIN users u ON u.id = a.user_id
             INNER JOIN projects p ON p.id = a.project_id
             LEFT JOIN project_phase_assignments ph ON ph.id = a.project_phase_assignment_id
             WHERE ' . implode(' AND ', $conditions) . '
             ORDER BY a.start_date, u.name, p.name, a.id'
        );
        $statement->execute($parameters);

        return $statement->fetchAll();
    }

    /**
     * Congés qui chevauchent la période.
     *
     * @return array<int,array<string,mixed>>
     */
    public function leaves(string $from, string $to, ?int $userId): array
    {
        $conditions = ['c.start_date <= :period_end', 'c.end_date >= :period_start'];
        $parameters = ['period_end' => $to, 'period_start' => $from];
        if ($userId !== null) {
            $conditions[] = 'c.user_id = :user_id';
            $parameters['user_id'] = $userId;
        }

        $statement = $this->pdo->prepare(
            'SELECT c.id, c.user_id, c.start_date, c.end_date, c.kind, c.note, u.name AS user_name
             FROM planning_conges c
             INNER JOIN users u ON u.id = c.user_id
             WHERE ' . implode(' AND ', $conditions) . '
             ORDER BY c.start_date, u.name, c.id'
        );
        $statement->execute($parameters);

        return $statement->fetchAll();
    }

    /**
     * Heures réalisées par collaborateur et par jour (table `time_entries`, lecture seule).
     *
     * @return array<int,array<string,string>> [user_id][date] = heures « 7.50 »
     */
    public function timeByDay(string $from, string $to, ?int $userId): array
    {
        $conditions = ['t.work_date BETWEEN :period_start AND :period_end'];
        $parameters = ['period_start' => $from, 'period_end' => $to];
        if ($userId !== null) {
            $conditions[] = 't.user_id = :user_id';
            $parameters['user_id'] = $userId;
        }

        $statement = $this->pdo->prepare(
            'SELECT t.user_id, t.work_date, SUM(t.duration_hours) AS hours
             FROM time_entries t
             WHERE ' . implode(' AND ', $conditions) . '
             GROUP BY t.user_id, t.work_date'
        );
        $statement->execute($parameters);

        $map = [];
        foreach ($statement->fetchAll() as $row) {
            $map[(int) $row['user_id']][(string) $row['work_date']] = (string) $row['hours'];
        }

        return $map;
    }

    /**
     * Charge des projets : budget d’heures, heures pointées et affectations prévisionnelles.
     *
     * @return array{projects:array<int,array<string,mixed>>,affectations:array<int,array<int,array<string,mixed>>>}
     */
    public function projectLoads(): array
    {
        $projects = $this->pdo->query(
            "SELECT p.id, p.name, p.reference, p.status AS project_status, p.budget_hours,
                    c.display_name AS client_name,
                    COALESCE(SUM(t.duration_hours), 0) AS realised_hours
             FROM projects p
             INNER JOIN clients c ON c.id = p.client_id
             LEFT JOIN time_entries t ON t.project_id = p.id
             WHERE p.status <> 'cancelled'
             GROUP BY p.id, p.name, p.reference, p.status, p.budget_hours, c.display_name
             ORDER BY p.updated_at DESC, p.id DESC
             LIMIT 200"
        )->fetchAll();

        $affectations = $this->pdo->query(
            'SELECT project_id, start_date, end_date, daily_hours FROM planning_affectations'
        )->fetchAll();

        $byProject = [];
        foreach ($affectations as $row) {
            $byProject[(int) $row['project_id']][] = $row;
        }

        return ['projects' => $projects, 'affectations' => $byProject];
    }

    /** @param array<string,mixed> $values */
    public function createAffectation(array $values, ?int $authorId): int
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO planning_affectations
                (user_id, project_id, project_phase_assignment_id, start_date, end_date, daily_hours, note, created_by)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $statement->execute([
            $values['user_id'],
            $values['project_id'],
            $values['project_phase_assignment_id'],
            $values['start_date'],
            $values['end_date'],
            $values['daily_hours'],
            $values['note'] !== '' ? $values['note'] : null,
            $authorId,
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    /** @return array<string,mixed>|null */
    public function findAffectation(int $affectationId): ?array
    {
        $statement = $this->pdo->prepare(
            'SELECT id, user_id, project_id, start_date, end_date, daily_hours FROM planning_affectations WHERE id = ? LIMIT 1'
        );
        $statement->execute([$affectationId]);
        $row = $statement->fetch();

        return is_array($row) ? $row : null;
    }

    public function deleteAffectation(int $affectationId): bool
    {
        $statement = $this->pdo->prepare('DELETE FROM planning_affectations WHERE id = ?');
        $statement->execute([$affectationId]);

        return $statement->rowCount() > 0;
    }

    /** @param array<string,mixed> $values */
    public function createLeave(array $values, ?int $authorId): int
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO planning_conges (user_id, start_date, end_date, kind, note, created_by)
             VALUES (?, ?, ?, ?, ?, ?)'
        );
        $statement->execute([
            $values['user_id'],
            $values['start_date'],
            $values['end_date'],
            $values['kind'],
            $values['note'] !== '' ? $values['note'] : null,
            $authorId,
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    /** @return array<string,mixed>|null */
    public function findLeave(int $leaveId): ?array
    {
        $statement = $this->pdo->prepare(
            'SELECT id, user_id, start_date, end_date, kind FROM planning_conges WHERE id = ? LIMIT 1'
        );
        $statement->execute([$leaveId]);
        $row = $statement->fetch();

        return is_array($row) ? $row : null;
    }

    public function deleteLeave(int $leaveId): bool
    {
        $statement = $this->pdo->prepare('DELETE FROM planning_conges WHERE id = ?');
        $statement->execute([$leaveId]);

        return $statement->rowCount() > 0;
    }
}
