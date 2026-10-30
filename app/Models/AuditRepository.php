<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use PDO;

/** Lecture paginée du journal d'audit réservé à l'administration. */
final class AuditRepository
{
    public function __construct(private ?PDO $pdo = null)
    {
        $this->pdo ??= Database::connection();
    }

    /** @return array{items:array<int,array<string,mixed>>,total:int} */
    public function paginate(int $limit, int $offset): array
    {
        $limit = max(1, min(100, $limit));
        $offset = max(0, min(2_000_000, $offset));
        $total = (int) $this->pdo->query('SELECT COUNT(*) FROM audit_logs')->fetchColumn();
        $statement = $this->pdo->prepare(
            'SELECT l.id, l.actor_id, l.event_key, l.subject_type, l.subject_id, l.details, l.created_at,
                    u.name AS actor_name
             FROM audit_logs l
             LEFT JOIN users u ON u.id = l.actor_id
             ORDER BY l.created_at DESC, l.id DESC
             LIMIT :limit OFFSET :offset'
        );
        $statement->bindValue(':limit', $limit, PDO::PARAM_INT);
        $statement->bindValue(':offset', $offset, PDO::PARAM_INT);
        $statement->execute();
        $items = $statement->fetchAll();

        foreach ($items as &$item) {
            $details = json_decode((string) ($item['details'] ?? '{}'), true);
            $item['details'] = is_array($details) ? $details : [];
        }
        unset($item);

        return ['items' => $items, 'total' => $total];
    }
}
