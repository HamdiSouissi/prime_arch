<?php
declare(strict_types=1);

namespace Modules\HonorairesBareme\Models;

use App\Core\Database;
use PDO;

/**
 * Accès PDO aux barèmes d’honoraires, à leurs tranches et aux décomptes.
 *
 * Les tables du cœur (`projects`, `project_phases`, `clients`, `documents`) sont uniquement
 * lues, sauf le devis que le cabinet demande explicitement depuis un décompte.
 */
final class HonoraireRepository
{
    private PDO $pdo;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? Database::connection();
    }

    /**
     * Barèmes du cabinet, avec le nombre de lignes et de décomptes qui les utilisent.
     *
     * @param array{q:string,active_only:bool} $filters
     * @return array<int,array<string,mixed>>
     */
    public function missions(array $filters): array
    {
        $conditions = [];
        $parameters = [];
        if ($filters['q'] !== '') {
            // Un marqueur par colonne : la connexion PDO n’émule pas les préparations.
            $conditions[] = '(m.name LIKE :search_name OR m.description LIKE :search_description)';
            $term = '%' . $filters['q'] . '%';
            $parameters['search_name'] = $term;
            $parameters['search_description'] = $term;
        }
        if ($filters['active_only']) {
            $conditions[] = 'm.is_active = 1';
        }
        $where = $conditions === [] ? '' : 'WHERE ' . implode(' AND ', $conditions);

        $statement = $this->pdo->prepare(
            "SELECT m.id, m.name, m.description, m.is_active, m.sort_order,
                    COUNT(DISTINCT t.id) AS tranches_count,
                    COUNT(DISTINCT d.id) AS decomptes_count
             FROM bareme_missions m
             LEFT JOIN bareme_tranches t ON t.mission_id = m.id
             LEFT JOIN bareme_decomptes d ON d.mission_id = m.id
             {$where}
             GROUP BY m.id, m.name, m.description, m.is_active, m.sort_order
             ORDER BY m.is_active DESC, m.sort_order, m.name
             LIMIT 200"
        );
        $statement->execute($parameters);

        return $statement->fetchAll();
    }

    /** @return array<string,mixed>|null */
    public function findMission(int $missionId): ?array
    {
        $statement = $this->pdo->prepare(
            'SELECT id, name, description, is_active, sort_order FROM bareme_missions WHERE id = ? LIMIT 1'
        );
        $statement->execute([$missionId]);
        $row = $statement->fetch();

        return is_array($row) ? $row : null;
    }

    /** @param array<string,mixed> $values */
    public function createMission(array $values, ?int $authorId): int
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO bareme_missions (name, description, is_active, sort_order, created_by)
             VALUES (?, ?, ?, ?, ?)'
        );
        $statement->execute([
            $values['name'],
            $values['description'] !== '' ? $values['description'] : null,
            $values['is_active'],
            $values['sort_order'],
            $authorId,
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    /** @param array<string,mixed> $values */
    public function updateMission(int $missionId, array $values): bool
    {
        $statement = $this->pdo->prepare(
            'UPDATE bareme_missions SET name = ?, description = ?, is_active = ?, sort_order = ? WHERE id = ?'
        );
        $statement->execute([
            $values['name'],
            $values['description'] !== '' ? $values['description'] : null,
            $values['is_active'],
            $values['sort_order'],
            $missionId,
        ]);

        return true;
    }

    /** Supprime un barème ; les décomptes déjà établis sont conservés (mission_id passe à NULL). */
    public function deleteMission(int $missionId): bool
    {
        $statement = $this->pdo->prepare('DELETE FROM bareme_missions WHERE id = ?');
        $statement->execute([$missionId]);

        return $statement->rowCount() > 0;
    }

    /** @return array<int,array<string,mixed>> */
    public function tranches(int $missionId): array
    {
        $statement = $this->pdo->prepare(
            'SELECT id, mission_id, phase_key, min_amount, max_amount, rate_percent, sort_order
             FROM bareme_tranches
             WHERE mission_id = ?
             ORDER BY sort_order, min_amount, id'
        );
        $statement->execute([$missionId]);

        return $statement->fetchAll();
    }

    /** @param array<string,mixed> $values */
    public function createTranche(int $missionId, array $values): int
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO bareme_tranches (mission_id, phase_key, min_amount, max_amount, rate_percent, sort_order)
             VALUES (?, ?, ?, ?, ?, ?)'
        );
        $statement->execute([
            $missionId,
            $values['phase_key'],
            $values['min_amount'],
            $values['max_amount'],
            $values['rate_percent'],
            $values['sort_order'],
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    public function deleteTranche(int $missionId, int $trancheId): bool
    {
        $statement = $this->pdo->prepare('DELETE FROM bareme_tranches WHERE id = ? AND mission_id = ?');
        $statement->execute([$trancheId, $missionId]);

        return $statement->rowCount() > 0;
    }

    /**
     * Phases du catalogue du cabinet (clé => libellé).
     *
     * @return array<string,string>
     */
    public function phases(): array
    {
        $statement = $this->pdo->query(
            'SELECT phase_key, name FROM project_phases ORDER BY sort_order, id'
        );
        $phases = [];
        foreach ($statement->fetchAll() as $row) {
            $phases[(string) $row['phase_key']] = (string) $row['name'];
        }

        return $phases;
    }

    /**
     * Projets suivis en honoraires.
     *
     * @param array{q:string} $filters
     * @return array<int,array<string,mixed>>
     */
    public function projects(array $filters): array
    {
        $conditions = ["p.status <> 'cancelled'"];
        $parameters = [];
        if ($filters['q'] !== '') {
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
                    COUNT(d.id) AS decomptes_count,
                    COALESCE(SUM(d.fee_amount), 0) AS fee_total,
                    MAX(d.created_at) AS last_decompte
             FROM projects p
             INNER JOIN clients c ON c.id = p.client_id
             LEFT JOIN bareme_decomptes d ON d.project_id = p.id
             WHERE " . implode(' AND ', $conditions) . "
             GROUP BY p.id, p.name, p.reference, p.status, p.city, c.display_name
             ORDER BY p.updated_at DESC, p.id DESC
             LIMIT 300"
        );
        $statement->execute($parameters);

        return $statement->fetchAll();
    }

    /** @return array<string,mixed>|null */
    public function project(int $projectId): ?array
    {
        $statement = $this->pdo->prepare(
            'SELECT p.id, p.name, p.reference, p.status, p.city, p.description,
                    c.display_name AS client_name, c.id AS client_id
             FROM projects p
             INNER JOIN clients c ON c.id = p.client_id
             WHERE p.id = ? LIMIT 1'
        );
        $statement->execute([$projectId]);
        $row = $statement->fetch();

        return is_array($row) ? $row : null;
    }

    /**
     * Décomptes d’un projet, avec le devis éventuellement généré.
     *
     * @return array<int,array<string,mixed>>
     */
    /** Nombre de décomptes du projet, pour la passerelle de la fiche projet. */
    public function contextCounts(int $projectId): int
    {
        $statement = $this->pdo->prepare('SELECT COUNT(*) FROM bareme_decomptes WHERE project_id = ?');
        $statement->execute([$projectId]);

        return (int) $statement->fetchColumn();
    }

    public function decomptes(int $projectId): array
    {
        $statement = $this->pdo->prepare(
            'SELECT d.id, d.project_id, d.mission_id, d.mission_name, d.basis_amount, d.fee_amount,
                    d.breakdown, d.status, d.document_id, d.notes, d.created_at,
                    doc.reference AS document_reference, doc.document_number AS document_number,
                    doc.status AS document_status
             FROM bareme_decomptes d
             LEFT JOIN documents doc ON doc.id = d.document_id
             WHERE d.project_id = ?
             ORDER BY d.created_at DESC, d.id DESC'
        );
        $statement->execute([$projectId]);

        return $statement->fetchAll();
    }

    /** @return array<string,mixed>|null */
    public function findDecompte(int $decompteId): ?array
    {
        $statement = $this->pdo->prepare(
            'SELECT d.id, d.project_id, d.mission_id, d.mission_name, d.basis_amount, d.fee_amount,
                    d.breakdown, d.status, d.document_id, d.notes, d.created_at,
                    doc.reference AS document_reference, doc.document_number AS document_number,
                    doc.status AS document_status
             FROM bareme_decomptes d
             LEFT JOIN documents doc ON doc.id = d.document_id
             WHERE d.id = ? LIMIT 1'
        );
        $statement->execute([$decompteId]);
        $row = $statement->fetch();

        return is_array($row) ? $row : null;
    }

    /**
     * @param array{basis_amount:string,fee_amount:string,notes:string} $values
     * @param array<int,array<string,mixed>> $breakdown
     */
    public function createDecompte(
        int $projectId,
        array $values,
        array $breakdown,
        ?int $missionId,
        string $missionName,
        ?int $authorId
    ): int {
        $statement = $this->pdo->prepare(
            'INSERT INTO bareme_decomptes
                (project_id, mission_id, mission_name, basis_amount, fee_amount, breakdown, status, notes, created_by)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $statement->execute([
            $projectId,
            $missionId,
            $missionName,
            $values['basis_amount'],
            $values['fee_amount'],
            json_encode($breakdown, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
            'draft',
            $values['notes'] !== '' ? $values['notes'] : null,
            $authorId,
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    public function updateDecompteStatus(int $decompteId, string $status): bool
    {
        $statement = $this->pdo->prepare('UPDATE bareme_decomptes SET status = ? WHERE id = ?');
        $statement->execute([$status, $decompteId]);

        return true;
    }

    public function attachDocument(int $decompteId, int $documentId): bool
    {
        $statement = $this->pdo->prepare(
            "UPDATE bareme_decomptes SET document_id = ?, status = 'quoted' WHERE id = ?"
        );
        $statement->execute([$documentId, $decompteId]);

        return $statement->rowCount() > 0;
    }

    public function deleteDecompte(int $decompteId): bool
    {
        $statement = $this->pdo->prepare('DELETE FROM bareme_decomptes WHERE id = ?');
        $statement->execute([$decompteId]);

        return $statement->rowCount() > 0;
    }

    /** @return array{missions:int,tranches:int,decomptes:int,fee_total:string,documents:int} */
    public function statistics(): array
    {
        $missions = $this->pdo->query('SELECT COUNT(*) FROM bareme_missions WHERE is_active = 1')->fetchColumn();
        $tranches = $this->pdo->query('SELECT COUNT(*) FROM bareme_tranches')->fetchColumn();
        $decomptes = $this->pdo->query('SELECT COUNT(*) FROM bareme_decomptes')->fetchColumn();
        $fee = $this->pdo->query('SELECT COALESCE(SUM(fee_amount), 0) FROM bareme_decomptes')->fetchColumn();
        $documents = $this->pdo->query('SELECT COUNT(*) FROM bareme_decomptes WHERE document_id IS NOT NULL')
            ->fetchColumn();

        return [
            'missions' => (int) $missions,
            'tranches' => (int) $tranches,
            'decomptes' => (int) $decomptes,
            'fee_total' => (string) $fee,
            'documents' => (int) $documents,
        ];
    }
}
