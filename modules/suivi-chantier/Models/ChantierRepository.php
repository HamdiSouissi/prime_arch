<?php
declare(strict_types=1);

namespace Modules\SuiviChantier\Models;

use App\Core\Database;
use PDO;

/**
 * Accès PDO au journal de chantier, aux réserves et aux réceptions.
 *
 * Les tables `projects` et `clients` du cœur sont uniquement lues.
 */
final class ChantierRepository
{
    private PDO $pdo;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? Database::connection();
    }

    /**
     * Projets suivis, avec leurs compteurs.
     *
     * @param array{q:string,only_open:bool} $filters
     * @return array<int,array<string,mixed>>
     */
    public function projects(array $filters): array
    {
        $conditions = ["p.status <> 'cancelled'"];
        $parameters = [];
        if ($filters['q'] !== '') {
            // Marqueurs distincts : PDO tourne sans émulation, un marqueur ne peut pas
            // être réutilisé plusieurs fois dans la même requête préparée.
            $conditions[] = '(p.name LIKE :search_project OR p.reference LIKE :search_reference
                              OR c.display_name LIKE :search_client)';
            $term = '%' . $filters['q'] . '%';
            $parameters['search_project'] = $term;
            $parameters['search_reference'] = $term;
            $parameters['search_client'] = $term;
        }
        if ($filters['only_open']) {
            $conditions[] = "EXISTS (SELECT 1 FROM chantier_reserves r WHERE r.project_id = p.id AND r.status = 'open')";
        }

        $statement = $this->pdo->prepare(
            "SELECT p.id, p.name, p.reference, p.status AS project_status, p.city,
                    c.display_name AS client_name,
                    (SELECT COUNT(*) FROM chantier_visites v WHERE v.project_id = p.id) AS visits_count,
                    (SELECT MAX(v.visit_date) FROM chantier_visites v WHERE v.project_id = p.id) AS last_visit,
                    (SELECT COUNT(*) FROM chantier_reserves r WHERE r.project_id = p.id AND r.status = 'open') AS open_reserves,
                    (SELECT COUNT(*) FROM chantier_reserves r WHERE r.project_id = p.id) AS reserves_count,
                    (SELECT COUNT(*) FROM chantier_receptions rc WHERE rc.project_id = p.id) AS receptions_count
             FROM projects p
             INNER JOIN clients c ON c.id = p.client_id
             WHERE " . implode(' AND ', $conditions) . "
             ORDER BY COALESCE((SELECT MAX(v.visit_date) FROM chantier_visites v WHERE v.project_id = p.id), p.updated_at) DESC,
                      p.id DESC
             LIMIT 200"
        );
        $statement->execute($parameters);

        return $statement->fetchAll();
    }

    /** @return array{projects:int,visits:int,open_reserves:int,receptions:int} */
    public function statistics(): array
    {
        $row = $this->pdo->query(
            "SELECT (SELECT COUNT(DISTINCT project_id) FROM chantier_visites) AS projects,
                    (SELECT COUNT(*) FROM chantier_visites) AS visits,
                    (SELECT COUNT(*) FROM chantier_reserves WHERE status = 'open') AS open_reserves,
                    (SELECT COUNT(*) FROM chantier_receptions) AS receptions"
        )->fetch();

        return [
            'projects' => (int) ($row['projects'] ?? 0),
            'visits' => (int) ($row['visits'] ?? 0),
            'open_reserves' => (int) ($row['open_reserves'] ?? 0),
            'receptions' => (int) ($row['receptions'] ?? 0),
        ];
    }

    /** @return array<string,mixed>|null Projet avec son client, sans donnée financière. */
    public function findProject(int $projectId): ?array
    {
        $statement = $this->pdo->prepare(
            'SELECT p.id, p.name, p.reference, p.status, p.city, p.start_date, p.target_end_date,
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
    public function visits(int $projectId): array
    {
        $statement = $this->pdo->prepare(
            'SELECT v.id, v.visit_date, v.weather, v.workforce, v.observations, v.created_at,
                    u.name AS author_name,
                    (SELECT COUNT(*) FROM chantier_reserves r WHERE r.visit_id = v.id) AS reserves_count
             FROM chantier_visites v
             LEFT JOIN users u ON u.id = v.created_by
             WHERE v.project_id = ?
             ORDER BY v.visit_date DESC, v.id DESC'
        );
        $statement->execute([$projectId]);

        return $statement->fetchAll();
    }

    public function addVisit(int $projectId, string $date, string $weather, ?int $workforce, string $observations, ?int $authorId): int
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO chantier_visites (project_id, visit_date, weather, workforce, observations, created_by)
             VALUES (?, ?, ?, ?, ?, ?)'
        );
        $statement->execute([$projectId, $date, $weather, $workforce, $observations, $authorId]);

        return (int) $this->pdo->lastInsertId();
    }

    /** Supprime une visite du projet ; les réserves rattachées sont conservées (visite_id null). */
    public function deleteVisit(int $projectId, int $visitId): bool
    {
        $statement = $this->pdo->prepare('DELETE FROM chantier_visites WHERE id = ? AND project_id = ?');
        $statement->execute([$visitId, $projectId]);

        return $statement->rowCount() > 0;
    }

    /** @return array<int,array<string,mixed>> */
    public function reserves(int $projectId): array
    {
        $statement = $this->pdo->prepare(
            "SELECT r.id, r.reference, r.visit_id, r.description, r.severity, r.status,
                    r.opened_on, r.lifted_on, r.lift_note, u.name AS author_name
             FROM chantier_reserves r
             LEFT JOIN users u ON u.id = r.created_by
             WHERE r.project_id = ?
             ORDER BY FIELD(r.status, 'open', 'contested', 'lifted'), r.opened_on DESC, r.id DESC"
        );
        $statement->execute([$projectId]);

        return $statement->fetchAll();
    }

    public function addReserve(int $projectId, array $values, ?int $authorId): int
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO chantier_reserves
                (project_id, visit_id, reference, description, severity, status, opened_on, created_by)
             VALUES (:project_id, :visit_id, :reference, :description, :severity, \'open\', :opened_on, :created_by)'
        );
        $statement->execute([
            'project_id' => $projectId,
            'visit_id' => $values['visit_id'],
            'reference' => $values['reference'],
            'description' => $values['description'],
            'severity' => $values['severity'],
            'opened_on' => $values['opened_on'],
            'created_by' => $authorId,
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    /** Change l’état d’une réserve (levée ou contestée) en conservant la date et la note. */
    public function updateReserveStatus(int $projectId, int $reserveId, string $status, ?string $note): bool
    {
        $statement = $this->pdo->prepare(
            'UPDATE chantier_reserves
             SET status = ?, lifted_on = ?, lift_note = ?
             WHERE id = ? AND project_id = ?'
        );
        $statement->execute([
            $status,
            $status === 'lifted' ? date('Y-m-d') : null,
            $note !== null && $note !== '' ? mb_substr($note, 0, 255, 'UTF-8') : null,
            $reserveId,
            $projectId,
        ]);

        return $statement->rowCount() > 0;
    }

    /** Référence de réserve suivante pour un projet : R-001, R-002… */
    public function nextReserveReference(int $projectId): string
    {
        $statement = $this->pdo->prepare(
            'SELECT reference FROM chantier_reserves WHERE project_id = ? ORDER BY id DESC LIMIT 1'
        );
        $statement->execute([$projectId]);
        $last = $statement->fetchColumn();
        $sequence = 1;
        if (is_string($last) && preg_match('/^R-(\d{3,})$/', $last, $matches) === 1) {
            $sequence = (int) $matches[1] + 1;
        }

        return 'R-' . str_pad((string) $sequence, 3, '0', STR_PAD_LEFT);
    }

    /** @return array<int,array<string,mixed>> */
    public function receptions(int $projectId): array
    {
        $statement = $this->pdo->prepare(
            'SELECT id, kind, reception_date, notes FROM chantier_receptions
             WHERE project_id = ? ORDER BY reception_date DESC, id DESC'
        );
        $statement->execute([$projectId]);

        return $statement->fetchAll();
    }

    /** @return array<string,mixed>|null */
    public function findReception(int $projectId, int $receptionId): ?array
    {
        $statement = $this->pdo->prepare(
            'SELECT id, project_id, kind, reception_date, notes FROM chantier_receptions
             WHERE id = ? AND project_id = ? LIMIT 1'
        );
        $statement->execute([$receptionId, $projectId]);
        $row = $statement->fetch();

        return is_array($row) ? $row : null;
    }

    public function addReception(int $projectId, string $kind, string $date, string $notes, ?int $authorId): int
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO chantier_receptions (project_id, kind, reception_date, notes, created_by)
             VALUES (?, ?, ?, ?, ?)'
        );
        $statement->execute([$projectId, $kind, $date, $notes !== '' ? $notes : null, $authorId]);

        return (int) $this->pdo->lastInsertId();
    }

    /** Réserves encore ouvertes, pour le procès-verbal de réception. */
    /** @return array<int,array<string,mixed>> */
    public function openReserves(int $projectId): array
    {
        $statement = $this->pdo->prepare(
            "SELECT reference, description, severity, opened_on
             FROM chantier_reserves
             WHERE project_id = ? AND status <> 'lifted'
             ORDER BY FIELD(severity, 'critical', 'major', 'minor'), opened_on, id"
        );
        $statement->execute([$projectId]);

        return $statement->fetchAll();
    }
}
