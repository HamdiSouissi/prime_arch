<?php
declare(strict_types=1);

namespace Modules\MetreQuantitatif\Models;

use App\Core\Database;
use PDO;

/**
 * Accès PDO au métré, au DQE et à la bibliothèque de prix.
 *
 * Les tables `projects` et `clients` du cœur sont uniquement lues. Les montants restent
 * des chaînes DECIMAL à trois décimales : aucune conversion en flottant n’est faite ici.
 */
final class MetreRepository
{
    private PDO $pdo;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? Database::connection();
    }

    /**
     * Projets non annulés, avec le nombre de lots, de postes et le montant du DQE.
     *
     * @param array{q:string} $filters
     * @return array<int,array<string,mixed>>
     */
    public function projects(array $filters): array
    {
        $conditions = ["p.status <> 'cancelled'"];
        $parameters = [];
        if ($filters['q'] !== '') {
            // Un marqueur par colonne : la connexion PDO n’émule pas les préparations.
            $conditions[] = '(p.name LIKE :search_project OR p.reference LIKE :search_reference
                              OR c.display_name LIKE :search_client)';
            $term = '%' . $filters['q'] . '%';
            $parameters['search_project'] = $term;
            $parameters['search_reference'] = $term;
            $parameters['search_client'] = $term;
        }

        $statement = $this->pdo->prepare(
            "SELECT p.id, p.name, p.reference, p.status AS project_status, p.city,
                    c.display_name AS client_name,
                    COUNT(DISTINCT l.id) AS lots_count,
                    COUNT(po.id) AS postes_count,
                    COALESCE(SUM(ROUND(po.quantity * po.unit_price, 3)), 0) AS total_amount,
                    COALESCE(SUM(ROUND(po.quantity * po.unit_price * po.margin_percent / 100, 3)), 0) AS margin_amount
             FROM projects p
             INNER JOIN clients c ON c.id = p.client_id
             LEFT JOIN metre_lots l ON l.project_id = p.id
             LEFT JOIN metre_postes po ON po.lot_id = l.id
             WHERE " . implode(' AND ', $conditions) . "
             GROUP BY p.id, p.name, p.reference, p.status, p.city, c.display_name
             ORDER BY p.updated_at DESC, p.id DESC
             LIMIT 300"
        );
        $statement->execute($parameters);

        return $statement->fetchAll();
    }

    /** @return array{projects:int,postes:int,total_amount:string} */
    public function statistics(): array
    {
        $row = $this->pdo->query(
            "SELECT COUNT(DISTINCT l.project_id) AS projects,
                    COUNT(po.id) AS postes,
                    COALESCE(SUM(ROUND(po.quantity * po.unit_price, 3)), 0) AS total_amount
             FROM metre_lots l
             LEFT JOIN metre_postes po ON po.lot_id = l.id"
        )->fetch();

        return [
            'projects' => (int) ($row['projects'] ?? 0),
            'postes' => (int) ($row['postes'] ?? 0),
            'total_amount' => (string) ($row['total_amount'] ?? '0.000'),
        ];
    }

    /** @return array<string,mixed>|null Projet avec son client, sans donnée financière du cœur. */
    public function project(int $projectId): ?array
    {
        $statement = $this->pdo->prepare(
            'SELECT p.id, p.name, p.reference, p.status, p.city, p.description,
                    c.display_name AS client_name
             FROM projects p
             INNER JOIN clients c ON c.id = p.client_id
             WHERE p.id = ? LIMIT 1'
        );
        $statement->execute([$projectId]);
        $row = $statement->fetch();

        return is_array($row) ? $row : null;
    }

    /** @return array<int,array<string,mixed>> */
    public function lots(int $projectId): array
    {
        $statement = $this->pdo->prepare(
            'SELECT id, project_id, name, sort_order
             FROM metre_lots
             WHERE project_id = ?
             ORDER BY sort_order, id'
        );
        $statement->execute([$projectId]);

        return $statement->fetchAll();
    }

    /** @return array<int,array<string,mixed>> Postes du projet, tous lots confondus. */
    public function postes(int $projectId): array
    {
        $statement = $this->pdo->prepare(
            'SELECT po.id, po.lot_id, po.library_id, po.code, po.designation, po.unit,
                    po.quantity, po.unit_price, po.margin_percent, po.sort_order
             FROM metre_postes po
             INNER JOIN metre_lots l ON l.id = po.lot_id
             WHERE l.project_id = ?
             ORDER BY l.sort_order, l.id, po.sort_order, po.id'
        );
        $statement->execute([$projectId]);

        return $statement->fetchAll();
    }

    /** @return array<string,mixed>|null */
    public function findLot(int $lotId): ?array
    {
        $statement = $this->pdo->prepare('SELECT id, project_id, name, sort_order FROM metre_lots WHERE id = ? LIMIT 1');
        $statement->execute([$lotId]);
        $row = $statement->fetch();

        return is_array($row) ? $row : null;
    }

    /** @return array<string,mixed>|null */
    public function findPoste(int $posteId): ?array
    {
        $statement = $this->pdo->prepare(
            'SELECT po.id, po.lot_id, po.library_id, po.code, po.designation, po.unit,
                    po.quantity, po.unit_price, po.margin_percent, po.sort_order, l.project_id
             FROM metre_postes po
             INNER JOIN metre_lots l ON l.id = po.lot_id
             WHERE po.id = ? LIMIT 1'
        );
        $statement->execute([$posteId]);
        $row = $statement->fetch();

        return is_array($row) ? $row : null;
    }

    public function nextLotOrder(int $projectId): int
    {
        $statement = $this->pdo->prepare('SELECT COALESCE(MAX(sort_order), 0) + 1 FROM metre_lots WHERE project_id = ?');
        $statement->execute([$projectId]);

        return (int) $statement->fetchColumn();
    }

    public function nextPosteOrder(int $lotId): int
    {
        $statement = $this->pdo->prepare('SELECT COALESCE(MAX(sort_order), 0) + 1 FROM metre_postes WHERE lot_id = ?');
        $statement->execute([$lotId]);

        return (int) $statement->fetchColumn();
    }

    /** @param array<string,mixed> $values */
    public function createLot(int $projectId, array $values, ?int $authorId): int
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO metre_lots (project_id, name, sort_order, created_by) VALUES (?, ?, ?, ?)'
        );
        $statement->execute([$projectId, $values['name'], $values['sort_order'], $authorId]);

        return (int) $this->pdo->lastInsertId();
    }

    /** Supprime un lot ; ses postes suivent par cascade. */
    public function deleteLot(int $projectId, int $lotId): bool
    {
        $statement = $this->pdo->prepare('DELETE FROM metre_lots WHERE id = ? AND project_id = ?');
        $statement->execute([$lotId, $projectId]);

        return $statement->rowCount() > 0;
    }

    /** @param array<string,mixed> $values */
    public function createPoste(int $lotId, array $values): int
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO metre_postes
                (lot_id, library_id, code, designation, unit, quantity, unit_price, margin_percent, sort_order)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $statement->execute([
            $lotId,
            $values['library_id'],
            $values['code'] !== '' ? $values['code'] : null,
            $values['designation'],
            $values['unit'],
            $values['quantity'],
            $values['unit_price'],
            $values['margin_percent'],
            $values['sort_order'],
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    /** @param array<string,mixed> $values */
    public function updatePoste(int $posteId, array $values): bool
    {
        $statement = $this->pdo->prepare(
            'UPDATE metre_postes
             SET code = ?, designation = ?, unit = ?, quantity = ?, unit_price = ?, margin_percent = ?, sort_order = ?
             WHERE id = ?'
        );
        $statement->execute([
            $values['code'] !== '' ? $values['code'] : null,
            $values['designation'],
            $values['unit'],
            $values['quantity'],
            $values['unit_price'],
            $values['margin_percent'],
            $values['sort_order'],
            $posteId,
        ]);

        return $statement->rowCount() > 0;
    }

    /** Supprime un poste en vérifiant qu’il appartient bien au projet demandé. */
    public function deletePoste(int $projectId, int $posteId): bool
    {
        $statement = $this->pdo->prepare(
            'DELETE po FROM metre_postes po
             INNER JOIN metre_lots l ON l.id = po.lot_id
             WHERE po.id = ? AND l.project_id = ?'
        );
        $statement->execute([$posteId, $projectId]);

        return $statement->rowCount() > 0;
    }

    /**
     * Bibliothèque de prix.
     *
     * @param array{q:string,active_only:bool} $filters
     * @return array<int,array<string,mixed>>
     */
    public function library(array $filters): array
    {
        $conditions = [];
        $parameters = [];
        if ($filters['q'] !== '') {
            $conditions[] = '(designation LIKE :search_designation OR code LIKE :search_code
                              OR category LIKE :search_category)';
            $term = '%' . $filters['q'] . '%';
            $parameters['search_designation'] = $term;
            $parameters['search_code'] = $term;
            $parameters['search_category'] = $term;
        }
        if ($filters['active_only']) {
            $conditions[] = 'is_active = 1';
        }
        $where = $conditions === [] ? '' : 'WHERE ' . implode(' AND ', $conditions);

        $statement = $this->pdo->prepare(
            "SELECT id, code, designation, unit, unit_price, category, is_active
             FROM metre_bibliotheque_prix
             {$where}
             ORDER BY category IS NULL, category, designation
             LIMIT 500"
        );
        $statement->execute($parameters);

        return $statement->fetchAll();
    }

    /** @return array<string,mixed>|null */
    public function findLibrary(int $libraryId): ?array
    {
        $statement = $this->pdo->prepare(
            'SELECT id, code, designation, unit, unit_price, category, is_active
             FROM metre_bibliotheque_prix WHERE id = ? LIMIT 1'
        );
        $statement->execute([$libraryId]);
        $row = $statement->fetch();

        return is_array($row) ? $row : null;
    }

    /** @param array<string,mixed> $values */
    public function createLibrary(array $values, ?int $authorId): int
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO metre_bibliotheque_prix (code, designation, unit, unit_price, category, is_active, created_by)
             VALUES (?, ?, ?, ?, ?, ?, ?)'
        );
        $statement->execute([
            $values['code'] !== '' ? $values['code'] : null,
            $values['designation'],
            $values['unit'],
            $values['unit_price'],
            $values['category'] !== '' ? $values['category'] : null,
            $values['is_active'],
            $authorId,
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    /** @param array<string,mixed> $values */
    public function updateLibrary(int $libraryId, array $values): bool
    {
        $statement = $this->pdo->prepare(
            'UPDATE metre_bibliotheque_prix
             SET code = ?, designation = ?, unit = ?, unit_price = ?, category = ?, is_active = ?
             WHERE id = ?'
        );
        $statement->execute([
            $values['code'] !== '' ? $values['code'] : null,
            $values['designation'],
            $values['unit'],
            $values['unit_price'],
            $values['category'] !== '' ? $values['category'] : null,
            $values['is_active'],
            $libraryId,
        ]);

        return $statement->rowCount() > 0;
    }

    public function deleteLibrary(int $libraryId): bool
    {
        $statement = $this->pdo->prepare('DELETE FROM metre_bibliotheque_prix WHERE id = ?');
        $statement->execute([$libraryId]);

        return $statement->rowCount() > 0;
    }
}
