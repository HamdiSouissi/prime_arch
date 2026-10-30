<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use PDO;

/**
 * Accès aux taux horaires historisés, réservé aux permissions financières.
 */
final class HourlyRateRepository
{
    private PDO $pdo;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? Database::connection();
    }

    /** @return array<int,array<string,mixed>> */
    public function history(?int $userId = null): array
    {
        $sql = 'SELECT r.id, r.user_id, r.effective_from, r.cost_rate_per_hour, r.note,
                       r.created_at, u.name AS user_name, creator.name AS created_by_name
                FROM user_hourly_rates r
                INNER JOIN users u ON u.id = r.user_id
                LEFT JOIN users creator ON creator.id = r.created_by';
        $parameters = [];
        if ($userId !== null) {
            $sql .= ' WHERE r.user_id = :user_id';
            $parameters['user_id'] = $userId;
        }
        $sql .= ' ORDER BY r.effective_from DESC, u.name, r.id DESC';
        $statement = $this->pdo->prepare($sql);
        $statement->execute($parameters);
        return array_map(static function (array $row): array {
            $row['id'] = (int) $row['id'];
            $row['user_id'] = (int) $row['user_id'];
            return $row;
        }, $statement->fetchAll());
    }

    /** @return array<int,array{id:int,name:string,email:string,is_active:bool}> */
    public function users(): array
    {
        $rows = $this->pdo->query(
            'SELECT id, name, email, is_active FROM users ORDER BY is_active DESC, name, id'
        )->fetchAll();
        return array_map(static fn (array $row): array => [
            'id' => (int) $row['id'],
            'name' => (string) $row['name'],
            'email' => (string) $row['email'],
            'is_active' => (bool) $row['is_active'],
        ], $rows);
    }

    /** @return array<string,mixed>|null */
    public function findById(int $id): ?array
    {
        $statement = $this->pdo->prepare(
            'SELECT id, user_id, effective_from, cost_rate_per_hour, note
             FROM user_hourly_rates WHERE id = :id LIMIT 1'
        );
        $statement->execute(['id' => $id]);
        $row = $statement->fetch();
        if (!is_array($row)) {
            return null;
        }
        $row['id'] = (int) $row['id'];
        $row['user_id'] = (int) $row['user_id'];
        return $row;
    }

    /** @return array<string,mixed>|null */
    public function rateForDate(int $userId, string $date): ?array
    {
        $statement = $this->pdo->prepare(
            'SELECT id, cost_rate_per_hour, effective_from
             FROM user_hourly_rates
             WHERE user_id = :user_id AND effective_from <= :effective_date
             ORDER BY effective_from DESC, id DESC LIMIT 1'
        );
        $statement->execute(['user_id' => $userId, 'effective_date' => $date]);
        $row = $statement->fetch();
        if (!is_array($row)) {
            return null;
        }
        $row['id'] = (int) $row['id'];
        return $row;
    }

    public function existsForDate(int $userId, string $date): bool
    {
        $statement = $this->pdo->prepare(
            'SELECT 1 FROM user_hourly_rates WHERE user_id = :user_id AND effective_from = :effective_from LIMIT 1'
        );
        $statement->execute(['user_id' => $userId, 'effective_from' => $date]);
        return $statement->fetchColumn() !== false;
    }

    /** @param array{user_id:int,effective_from:string,cost_rate_per_hour:string,note:?string} $values */
    public function create(array $values, ?int $createdBy): int
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO user_hourly_rates (user_id, effective_from, cost_rate_per_hour, note, created_by)
             VALUES (:user_id, :effective_from, :cost_rate_per_hour, :note, :created_by)'
        );
        $statement->execute([
            'user_id' => $values['user_id'],
            'effective_from' => $values['effective_from'],
            'cost_rate_per_hour' => $values['cost_rate_per_hour'],
            'note' => $values['note'],
            'created_by' => $createdBy,
        ]);
        return (int) $this->pdo->lastInsertId();
    }
}
