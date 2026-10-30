<?php
declare(strict_types=1);

namespace Modules\FiscalTn\Models;

use App\Core\Database;
use PDO;

/**
 * Accès PDO à l’échéancier fiscal.
 *
 * Le module n’écrit que dans ses tables `fiscal_*` et ne touche à aucune donnée du noyau.
 */
final class FiscalRepository
{
    private PDO $pdo;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? Database::connection();
    }

    /**
     * Obligations de l’exercice, avec leur déclaration éventuelle.
     *
     * @return array<int,array<string,mixed>>
     */
    public function echeances(int $year, string $kind = '', string $status = '', ?string $today = null): array
    {
        $conditions = ['YEAR(e.due_on) = :year_value'];
        $parameters = ['year_value' => $year];
        if ($kind !== '') {
            $conditions[] = 'e.kind = :kind';
            $parameters['kind'] = $kind;
        }
        if ($status === 'late') {
            $conditions[] = "d.status <> 'paid' AND e.due_on < :today_value";
            $parameters['today_value'] = $today ?? date('Y-m-d');
        } elseif ($status !== '') {
            $conditions[] = 'COALESCE(d.status, \'to_prepare\') = :status_value';
            $parameters['status_value'] = $status;
        }

        $statement = $this->pdo->prepare(
            'SELECT e.id, e.kind, e.label, e.period_start, e.period_end, e.due_on, e.note,
                    d.id AS declaration_id, COALESCE(d.status, \'to_prepare\') AS status,
                    d.amount, d.filed_on, d.paid_on, d.reference, d.note AS declaration_note
             FROM fiscal_echeances e
             LEFT JOIN fiscal_declarations d ON d.echeance_id = e.id
             WHERE ' . implode(' AND ', $conditions) . '
             ORDER BY e.due_on ASC, e.kind ASC, e.id ASC'
        );
        $statement->execute($parameters);

        return $statement->fetchAll();
    }

    /** @return array<string,mixed>|null */
    public function findEcheance(int $echeanceId): ?array
    {
        $statement = $this->pdo->prepare(
            'SELECT id, kind, label, period_start, period_end, due_on, note FROM fiscal_echeances WHERE id = ? LIMIT 1'
        );
        $statement->execute([$echeanceId]);
        $row = $statement->fetch();

        return is_array($row) ? $row : null;
    }

    /** @param array<string,mixed> $values */
    public function createEcheance(array $values, ?int $authorId): int
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO fiscal_echeances (kind, label, period_start, period_end, due_on, note, created_by)
             VALUES (?, ?, ?, ?, ?, ?, ?)'
        );
        $statement->execute([
            $values['kind'],
            $values['label'],
            $values['period_start'],
            $values['period_end'],
            $values['due_on'],
            $values['note'] !== '' ? $values['note'] : null,
            $authorId,
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    public function deleteEcheance(int $echeanceId): bool
    {
        $statement = $this->pdo->prepare('DELETE FROM fiscal_echeances WHERE id = ?');
        $statement->execute([$echeanceId]);

        return $statement->rowCount() > 0;
    }

    /**
     * Enregistre ou met à jour la déclaration d’une obligation (une seule par obligation).
     *
     * @param array<string,mixed> $values
     */
    public function saveDeclaration(array $values, ?int $authorId): int
    {
        $statement = $this->pdo->prepare('SELECT id FROM fiscal_declarations WHERE echeance_id = ? LIMIT 1');
        $statement->execute([$values['echeance_id']]);
        $existing = $statement->fetch();
        $parameters = [
            $values['status'],
            $values['amount'],
            $values['filed_on'],
            $values['paid_on'],
            $values['reference'] !== '' ? $values['reference'] : null,
            $values['note'] !== '' ? $values['note'] : null,
        ];

        if (is_array($existing)) {
            $update = $this->pdo->prepare(
                'UPDATE fiscal_declarations
                 SET status = ?, amount = ?, filed_on = ?, paid_on = ?, reference = ?, note = ?
                 WHERE echeance_id = ?'
            );
            $update->execute(array_merge($parameters, [$values['echeance_id']]));

            return (int) $existing['id'];
        }

        $insert = $this->pdo->prepare(
            'INSERT INTO fiscal_declarations (status, amount, filed_on, paid_on, reference, note, echeance_id, created_by)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $insert->execute(array_merge($parameters, [$values['echeance_id'], $authorId]));

        return (int) $this->pdo->lastInsertId();
    }

    /** @return array<int,int> Identifiants de toutes les obligations, pour valider une saisie. */
    public function ids(): array
    {
        $ids = [];
        foreach ($this->pdo->query('SELECT id FROM fiscal_echeances')->fetchAll() as $row) {
            $ids[] = (int) $row['id'];
        }

        return $ids;
    }

    /** @return array<int,int> Années présentes dans l’échéancier. */
    public function years(): array
    {
        $rows = $this->pdo->query(
            'SELECT DISTINCT YEAR(due_on) AS year_value FROM fiscal_echeances WHERE due_on IS NOT NULL ORDER BY year_value DESC'
        )->fetchAll();

        $years = [];
        foreach ($rows as $row) {
            $year = (int) $row['year_value'];
            if ($year > 0) {
                $years[] = $year;
            }
        }

        return $years;
    }
}
