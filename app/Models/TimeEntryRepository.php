<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use PDO;
use RuntimeException;
use Throwable;

/**
 * Saisies de temps, statistiques et coûts calculés en décimal par MySQL.
 */
final class TimeEntryRepository
{
    private PDO $pdo;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? Database::connection();
    }

    /**
     * @param array<string,string> $filters
     * @return array{items:array<int,array<string,mixed>>,total:int}
     */
    public function paginate(
        array $filters,
        int $limit = 20,
        int $offset = 0,
        ?int $ownerUserId = null,
        bool $includeCosts = false
    ): array {
        [$where, $parameters] = $this->conditions($filters, $ownerUserId);
        $count = $this->pdo->prepare(
            'SELECT COUNT(*) FROM time_entries e INNER JOIN projects p ON p.id = e.project_id' . $where
        );
        $count->execute($parameters);
        $total = (int) $count->fetchColumn();

        $costColumn = $includeCosts ? ', e.hourly_rate_snapshot, e.cost_amount' : '';
        $statement = $this->pdo->prepare(
            'SELECT e.id, e.user_id, e.project_id, e.project_phase_assignment_id, e.work_date,
                    e.duration_hours, e.description, e.is_billable, e.created_at, e.updated_at' . $costColumn . ',
                    u.name AS user_name, p.reference AS project_reference, p.name AS project_name,
                    c.display_name AS client_name, a.phase_name
             FROM time_entries e
             INNER JOIN users u ON u.id = e.user_id
             INNER JOIN projects p ON p.id = e.project_id
             INNER JOIN clients c ON c.id = p.client_id
             INNER JOIN project_phase_assignments a ON a.id = e.project_phase_assignment_id'
            . $where . ' ORDER BY e.work_date DESC, e.id DESC LIMIT ' . max(1, $limit)
            . ' OFFSET ' . max(0, $offset)
        );
        $statement->execute($parameters);
        return [
            'items' => array_map(self::normalize(...), $statement->fetchAll()),
            'total' => $total,
        ];
    }

    /** @return array<int,array{id:int,name:string,is_active:bool}> */
    public function usersForFilter(): array
    {
        $rows = $this->pdo->query('SELECT id, name, is_active FROM users ORDER BY name, id')->fetchAll();
        return array_map(static fn (array $row): array => [
            'id' => (int) $row['id'],
            'name' => (string) $row['name'],
            'is_active' => (bool) $row['is_active'],
        ], $rows);
    }

    /** @return array<string,mixed>|null */
    public function findById(int $id, bool $includeCosts = false): ?array
    {
        $costColumns = $includeCosts ? ', e.hourly_rate_snapshot, e.cost_amount' : '';
        $statement = $this->pdo->prepare(
            'SELECT e.id, e.user_id, e.project_id, e.project_phase_assignment_id, e.work_date,
                    e.duration_hours, e.description, e.is_billable, e.created_at, e.updated_at' . $costColumns . ',
                    p.reference AS project_reference, p.name AS project_name,
                    c.display_name AS client_name, a.phase_name
             FROM time_entries e
             INNER JOIN projects p ON p.id = e.project_id
             INNER JOIN clients c ON c.id = p.client_id
             INNER JOIN project_phase_assignments a ON a.id = e.project_phase_assignment_id
             WHERE e.id = :id LIMIT 1'
        );
        $statement->execute(['id' => $id]);
        $row = $statement->fetch();
        return is_array($row) ? self::normalize($row) : null;
    }

    /** @param array<string,string> $filters
     *  @return array{hours:string,billable_hours:string,entry_count:int,cost:string|null}
     */
    /** Nombre de saisies de temps d'un projet : elles partent avec lui. */
    public function countForProject(int $projectId): int
    {
        $statement = $this->pdo->prepare('SELECT COUNT(*) FROM time_entries WHERE project_id = :project_id');
        $statement->execute(['project_id' => $projectId]);

        return (int) $statement->fetchColumn();
    }

    /** Heures déjà saisies sur un projet, pour l'annoncer avant suppression. */
    public function hoursForProject(int $projectId): string
    {
        $statement = $this->pdo->prepare(
            'SELECT COALESCE(SUM(duration_hours), 0) FROM time_entries WHERE project_id = :project_id'
        );
        $statement->execute(['project_id' => $projectId]);

        return (string) $statement->fetchColumn();
    }

    public function deleteForProject(int $projectId): int
    {
        $statement = $this->pdo->prepare('DELETE FROM time_entries WHERE project_id = :project_id');
        $statement->execute(['project_id' => $projectId]);

        return $statement->rowCount();
    }

    public function summary(array $filters, ?int $ownerUserId = null, bool $includeCosts = false): array
    {
        [$where, $parameters] = $this->conditions($filters, $ownerUserId);
        $costColumn = $includeCosts ? ', COALESCE(SUM(e.cost_amount), 0) AS total_cost' : '';
        $statement = $this->pdo->prepare(
            'SELECT COALESCE(SUM(e.duration_hours), 0) AS total_hours,
                    COALESCE(SUM(CASE WHEN e.is_billable = 1 THEN e.duration_hours ELSE 0 END), 0) AS billable_hours,
                    COUNT(*) AS entry_count' . $costColumn . '
             FROM time_entries e INNER JOIN projects p ON p.id = e.project_id' . $where
        );
        $statement->execute($parameters);
        $row = $statement->fetch() ?: [];
        return [
            'hours' => (string) ($row['total_hours'] ?? '0.00'),
            'billable_hours' => (string) ($row['billable_hours'] ?? '0.00'),
            'entry_count' => (int) ($row['entry_count'] ?? 0),
            'cost' => $includeCosts ? (string) ($row['total_cost'] ?? '0.000') : null,
        ];
    }

    /** @return array<string,mixed> */
    public function projectSummary(int $projectId, bool $includeCosts = false): array
    {
        $financialColumns = $includeCosts
            ? ', COALESCE(SUM(e.cost_amount), 0) AS total_cost, p.fee_budget_amount,
               CASE WHEN p.fee_budget_amount IS NULL THEN NULL
                    ELSE CAST(p.fee_budget_amount - COALESCE(SUM(e.cost_amount), 0) AS DECIMAL(18,3)) END AS gross_margin,
               CASE WHEN p.fee_budget_amount IS NULL OR p.fee_budget_amount = 0 THEN NULL
                    ELSE ROUND((p.fee_budget_amount - COALESCE(SUM(e.cost_amount), 0)) / p.fee_budget_amount * 100, 2) END AS margin_rate'
            : '';
        $statement = $this->pdo->prepare(
            'SELECT COALESCE(SUM(e.duration_hours), 0) AS total_hours,
                    COALESCE(SUM(CASE WHEN e.is_billable = 1 THEN e.duration_hours ELSE 0 END), 0) AS billable_hours,
                    COUNT(e.id) AS entry_count' . $financialColumns . '
             FROM projects p LEFT JOIN time_entries e ON e.project_id = p.id
             WHERE p.id = :project_id
             GROUP BY p.id' . ($includeCosts ? ', p.fee_budget_amount' : '')
        );
        $statement->execute(['project_id' => $projectId]);
        $row = $statement->fetch() ?: [];
        return [
            'hours' => (string) ($row['total_hours'] ?? '0.00'),
            'billable_hours' => (string) ($row['billable_hours'] ?? '0.00'),
            'entry_count' => (int) ($row['entry_count'] ?? 0),
            'cost' => $includeCosts ? (string) ($row['total_cost'] ?? '0.000') : null,
            'fee_budget' => $includeCosts && $row['fee_budget_amount'] !== null ? (string) $row['fee_budget_amount'] : null,
            'gross_margin' => $includeCosts && $row['gross_margin'] !== null ? (string) $row['gross_margin'] : null,
            'margin_rate' => $includeCosts && $row['margin_rate'] !== null ? (string) $row['margin_rate'] : null,
        ];
    }

    /** @return array<int,array<string,mixed>> */
    public function projectPhaseSummaries(int $projectId, bool $includeCosts = false): array
    {
        if ($includeCosts) {
            $sql = <<<'SQL'
WITH phase_totals AS (
    SELECT a.id, a.project_id, a.phase_name, a.status, a.sort_order, a.weight_percent,
           a.progress_percent, a.budget_hours, p.fee_budget_amount,
           COALESCE(SUM(e.duration_hours), 0) AS actual_hours,
           COALESCE(SUM(CASE WHEN e.is_billable = 1 THEN e.duration_hours ELSE 0 END), 0) AS billable_hours,
           COALESCE(SUM(e.cost_amount), 0) AS actual_cost,
           FLOOR(p.fee_budget_amount * a.weight_percent * 10) AS base_fee_mills,
           ROUND(p.fee_budget_amount * 1000, 0)
               - SUM(FLOOR(p.fee_budget_amount * a.weight_percent * 10))
                   OVER (PARTITION BY a.project_id) AS remaining_fee_mills,
           ROW_NUMBER() OVER (
               PARTITION BY a.project_id
               ORDER BY (p.fee_budget_amount * a.weight_percent * 10
                   - FLOOR(p.fee_budget_amount * a.weight_percent * 10)) DESC,
                   a.sort_order, a.id
           ) AS remainder_rank
    FROM project_phase_assignments a
    INNER JOIN projects p ON p.id = a.project_id
    LEFT JOIN time_entries e ON e.project_phase_assignment_id = a.id
    WHERE a.project_id = :project_id
    GROUP BY a.id, a.project_id, a.phase_name, a.status, a.sort_order, a.weight_percent,
             a.progress_percent, a.budget_hours, p.fee_budget_amount
), phase_allocations AS (
    SELECT phase_totals.*,
           CASE WHEN fee_budget_amount IS NULL THEN NULL
                ELSE CAST((base_fee_mills
                    + CASE WHEN remainder_rank <= remaining_fee_mills THEN 1 ELSE 0 END) / 1000
                    AS DECIMAL(18,3)) END AS allocated_fee
    FROM phase_totals
)
SELECT id, phase_name, status, sort_order, weight_percent, progress_percent, budget_hours,
       actual_hours, billable_hours, actual_cost, allocated_fee,
       CASE WHEN allocated_fee IS NULL THEN NULL
            ELSE CAST(allocated_fee - actual_cost AS DECIMAL(18,3)) END AS phase_margin,
       CASE WHEN allocated_fee IS NULL OR allocated_fee = 0 THEN NULL
            ELSE ROUND((allocated_fee - actual_cost) / allocated_fee * 100, 2) END AS phase_margin_rate
FROM phase_allocations
ORDER BY sort_order, id
SQL;
        } else {
            $sql = <<<'SQL'
SELECT a.id, a.phase_name, a.status, a.sort_order, a.weight_percent, a.progress_percent, a.budget_hours,
       COALESCE(SUM(e.duration_hours), 0) AS actual_hours,
       COALESCE(SUM(CASE WHEN e.is_billable = 1 THEN e.duration_hours ELSE 0 END), 0) AS billable_hours
FROM project_phase_assignments a
LEFT JOIN time_entries e ON e.project_phase_assignment_id = a.id
WHERE a.project_id = :project_id
GROUP BY a.id, a.phase_name, a.status, a.sort_order, a.weight_percent, a.progress_percent, a.budget_hours
ORDER BY a.sort_order, a.id
SQL;
        }
        $statement = $this->pdo->prepare($sql);
        $statement->execute(['project_id' => $projectId]);
        return array_map(static function (array $row): array {
            $row['id'] = (int) $row['id'];
            $row['weight_percent'] = (string) $row['weight_percent'];
            $row['progress_percent'] = (string) $row['progress_percent'];
            $row['budget_hours'] = $row['budget_hours'] === null ? null : (string) $row['budget_hours'];
            $row['actual_hours'] = (string) $row['actual_hours'];
            $row['billable_hours'] = (string) $row['billable_hours'];
            if (array_key_exists('actual_cost', $row)) {
                $row['actual_cost'] = (string) $row['actual_cost'];
                $row['allocated_fee'] = $row['allocated_fee'] === null ? null : (string) $row['allocated_fee'];
                $row['phase_margin'] = $row['phase_margin'] === null ? null : (string) $row['phase_margin'];
                $row['phase_margin_rate'] = $row['phase_margin_rate'] === null ? null : (string) $row['phase_margin_rate'];
            }
            return $row;
        }, $statement->fetchAll());
    }

    /** @param array{user_id:int,project_id:int,phase_assignment_id:int,work_date:string,duration_hours:string,description:string,is_billable:bool} $values */
    public function create(array $values): int
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO time_entries (
                user_id, project_id, project_phase_assignment_id, work_date, duration_hours,
                description, is_billable, hourly_rate_snapshot, cost_amount
             )
             SELECT :user_id, :project_id, :phase_assignment_id, :work_date,
                    CAST(:duration_hours AS DECIMAL(6,2)), :description, :is_billable,
                    r.cost_rate_per_hour,
                    CAST(ROUND(CAST(:cost_hours AS DECIMAL(6,2)) * r.cost_rate_per_hour, 3) AS DECIMAL(18,3))
             FROM user_hourly_rates r
             WHERE r.user_id = :rate_user_id AND r.effective_from <= :rate_date
             ORDER BY r.effective_from DESC, r.id DESC LIMIT 1'
        );
        $statement->execute([
            'user_id' => $values['user_id'],
            'project_id' => $values['project_id'],
            'phase_assignment_id' => $values['phase_assignment_id'],
            'work_date' => $values['work_date'],
            'duration_hours' => $values['duration_hours'],
            'description' => $values['description'],
            'is_billable' => $values['is_billable'] ? 1 : 0,
            'cost_hours' => $values['duration_hours'],
            'rate_user_id' => $values['user_id'],
            'rate_date' => $values['work_date'],
        ]);
        if ($statement->rowCount() !== 1) {
            throw new RuntimeException('Aucun taux applicable à cette date.');
        }
        return (int) $this->pdo->lastInsertId();
    }

    /** @param array{project_id:int,phase_assignment_id:int,work_date:string,duration_hours:string,description:string,is_billable:bool} $values */
    public function update(int $id, int $actorId, bool $manageAll, array $values): bool
    {
        $ownsTransaction = !$this->pdo->inTransaction();
        if ($ownsTransaction) {
            $this->pdo->beginTransaction();
        }
        try {
            $entrySql = 'SELECT user_id, work_date, hourly_rate_snapshot FROM time_entries WHERE id = :id';
            $entryParameters = ['id' => $id];
            if (!$manageAll) {
                $entrySql .= ' AND user_id = :owner_id';
                $entryParameters['owner_id'] = $actorId;
            }
            $entrySql .= ' FOR UPDATE';
            $entryStatement = $this->pdo->prepare($entrySql);
            $entryStatement->execute($entryParameters);
            $existing = $entryStatement->fetch();
            if (!is_array($existing)) {
                if ($ownsTransaction) {
                    $this->pdo->rollBack();
                }
                return false;
            }
            $ownerId = (int) $existing['user_id'];
            $rate = $values['work_date'] === (string) $existing['work_date']
                ? ['cost_rate_per_hour' => (string) $existing['hourly_rate_snapshot']]
                : (new HourlyRateRepository($this->pdo))->rateForDate($ownerId, $values['work_date']);
            if ($rate === null) {
                throw new RuntimeException('Aucun taux applicable à cette date.');
            }
            $update = $this->pdo->prepare(
                'UPDATE time_entries SET project_id = :project_id,
                    project_phase_assignment_id = :phase_assignment_id, work_date = :work_date,
                    duration_hours = CAST(:duration_hours AS DECIMAL(6,2)), description = :description,
                    is_billable = :is_billable, hourly_rate_snapshot = CAST(:rate AS DECIMAL(12,3)),
                    cost_amount = CAST(ROUND(CAST(:cost_hours AS DECIMAL(6,2)) * CAST(:cost_rate AS DECIMAL(12,3)), 3) AS DECIMAL(18,3))
                 WHERE id = :id' . ($manageAll ? '' : ' AND user_id = :owner_id')
            );
            $parameters = [
                'project_id' => $values['project_id'],
                'phase_assignment_id' => $values['phase_assignment_id'],
                'work_date' => $values['work_date'],
                'duration_hours' => $values['duration_hours'],
                'description' => $values['description'],
                'is_billable' => $values['is_billable'] ? 1 : 0,
                'rate' => $rate['cost_rate_per_hour'],
                'cost_hours' => $values['duration_hours'],
                'cost_rate' => $rate['cost_rate_per_hour'],
                'id' => $id,
            ];
            if (!$manageAll) {
                $parameters['owner_id'] = $actorId;
            }
            $update->execute($parameters);
            if ($ownsTransaction) {
                $this->pdo->commit();
            }
            return true;
        } catch (Throwable $error) {
            if ($ownsTransaction && $this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $error;
        }
    }

    /** @param array<string,string> $filters
     *  @return array{string,array<string,mixed>}
     */
    private function conditions(array $filters, ?int $ownerUserId): array
    {
        $conditions = [];
        $parameters = [];
        if ($ownerUserId !== null) {
            $conditions[] = 'e.user_id = :owner_user_id';
            $parameters['owner_user_id'] = $ownerUserId;
        } elseif (($filters['user_id'] ?? '') !== '') {
            $conditions[] = 'e.user_id = :filter_user_id';
            $parameters['filter_user_id'] = (int) $filters['user_id'];
        }
        foreach (['date_from' => '>=', 'date_to' => '<='] as $key => $operator) {
            if (($filters[$key] ?? '') !== '') {
                $parameter = $key;
                $conditions[] = 'e.work_date ' . $operator . ' :' . $parameter;
                $parameters[$parameter] = $filters[$key];
            }
        }
        if (($filters['project_id'] ?? '') !== '') {
            $conditions[] = 'e.project_id = :filter_project_id';
            $parameters['filter_project_id'] = (int) $filters['project_id'];
        }
        if (($filters['phase_id'] ?? '') !== '') {
            $conditions[] = 'e.project_phase_assignment_id = :filter_phase_id';
            $parameters['filter_phase_id'] = (int) $filters['phase_id'];
        }
        return [$conditions === [] ? '' : ' WHERE ' . implode(' AND ', $conditions), $parameters];
    }

    /** @param array<string,mixed> $row
     *  @return array<string,mixed>
     */
    private static function normalize(array $row): array
    {
        foreach (['id', 'user_id', 'project_id', 'project_phase_assignment_id'] as $key) {
            if (isset($row[$key])) {
                $row[$key] = (int) $row[$key];
            }
        }
        $row['is_billable'] = (bool) ($row['is_billable'] ?? false);
        if (isset($row['duration_hours'])) {
            $row['duration_hours'] = (string) $row['duration_hours'];
        }
        if (isset($row['hourly_rate_snapshot'])) {
            $row['hourly_rate_snapshot'] = (string) $row['hourly_rate_snapshot'];
        }
        if (isset($row['cost_amount'])) {
            $row['cost_amount'] = (string) $row['cost_amount'];
        }
        return $row;
    }
}
