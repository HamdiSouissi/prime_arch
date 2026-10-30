<?php
declare(strict_types=1);

namespace Modules\PermisBatir\Models;

use App\Core\Database;
use PDO;

/**
 * Accès PDO aux dossiers de permis de bâtir, à leurs pièces et à leur historique.
 *
 * Les tables sont préfixées `permis_batir_` ; les tables du cœur (`projects`, `clients`)
 * ne sont que lues, jamais modifiées.
 */
final class DossierRepository
{
    private PDO $pdo;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? Database::connection();
    }

    /**
     * @param array{q:string,status:string,year:string} $filters
     * @return array{items:array<int,array<string,mixed>>,total:int}
     */
    public function paginate(array $filters, int $limit = 20, int $offset = 0): array
    {
        [$where, $parameters] = $this->listConditions($filters);

        $count = $this->pdo->prepare(
            'SELECT COUNT(*) FROM permis_batir_dossiers d
             INNER JOIN projects p ON p.id = d.project_id
             INNER JOIN clients c ON c.id = p.client_id' . $where
        );
        $count->execute($parameters);
        $total = (int) $count->fetchColumn();

        $statement = $this->pdo->prepare(
            'SELECT d.id, d.project_id, d.reference, d.commune, d.status, d.deposit_number,
                    d.deposit_date, d.decision_number, d.decision_date, d.deadline_date, d.updated_at,
                    p.name AS project_name, p.reference AS project_reference,
                    c.id AS client_id, c.display_name AS client_name,
                    (SELECT COUNT(*) FROM permis_batir_pieces pc
                      WHERE pc.dossier_id = d.id AND pc.is_deposited = 1) AS pieces_deposited,
                    (SELECT COUNT(*) FROM permis_batir_pieces pc WHERE pc.dossier_id = d.id) AS pieces_total
             FROM permis_batir_dossiers d
             INNER JOIN projects p ON p.id = d.project_id
             INNER JOIN clients c ON c.id = p.client_id' . $where
            . ' ORDER BY d.updated_at DESC, d.id DESC LIMIT ' . max(1, $limit) . ' OFFSET ' . max(0, $offset)
        );
        $statement->execute($parameters);

        return ['items' => $statement->fetchAll(), 'total' => $total];
    }

    /**
     * Lignes complètes pour l’export CSV (même filtre que la liste).
     *
     * @param array{q:string,status:string,year:string} $filters
     * @return array<int,array<string,mixed>>
     */
    public function exportRows(array $filters): array
    {
        [$where, $parameters] = $this->listConditions($filters);
        $statement = $this->pdo->prepare(
            'SELECT d.reference, p.reference AS project_reference, p.name AS project_name,
                    c.display_name AS client_name, d.commune, d.status, d.deposit_number, d.deposit_date,
                    d.decision_number, d.decision_date, d.deadline_date,
                    (SELECT COUNT(*) FROM permis_batir_pieces pc
                      WHERE pc.dossier_id = d.id AND pc.is_deposited = 1) AS pieces_deposited,
                    (SELECT COUNT(*) FROM permis_batir_pieces pc WHERE pc.dossier_id = d.id) AS pieces_total
             FROM permis_batir_dossiers d
             INNER JOIN projects p ON p.id = d.project_id
             INNER JOIN clients c ON c.id = p.client_id' . $where
            . ' ORDER BY d.reference'
        );
        $statement->execute($parameters);

        return $statement->fetchAll();
    }

    /** @return array<string,mixed>|null */
    public function find(int $id): ?array
    {
        $statement = $this->pdo->prepare(
            'SELECT d.*, p.name AS project_name, p.reference AS project_reference, p.city AS project_city,
                    c.id AS client_id, c.display_name AS client_name
             FROM permis_batir_dossiers d
             INNER JOIN projects p ON p.id = d.project_id
             INNER JOIN clients c ON c.id = p.client_id
             WHERE d.id = ? LIMIT 1'
        );
        $statement->execute([$id]);
        $row = $statement->fetch();

        return is_array($row) ? $row : null;
    }

    /** Dossiers rattachés à un projet, pour l’affichage dans les écrans du module. */
    /** @return array<int,array<string,mixed>> */
    public function forProject(int $projectId): array
    {
        $statement = $this->pdo->prepare(
            'SELECT id, reference, commune, status, deposit_date, decision_date, deadline_date
             FROM permis_batir_dossiers WHERE project_id = ? ORDER BY created_at DESC, id DESC'
        );
        $statement->execute([$projectId]);

        return $statement->fetchAll();
    }

    /** @param array<string,mixed> $values */
    public function create(array $values, ?int $authorId): int
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO permis_batir_dossiers
                (project_id, reference, commune, status, deposit_number, deposit_date,
                 decision_number, decision_date, deadline_date, notes, created_by)
             VALUES
                (:project_id, :reference, :commune, :status, :deposit_number, :deposit_date,
                 :decision_number, :decision_date, :deadline_date, :notes, :created_by)'
        );
        $statement->execute([
            'project_id' => $values['project_id'],
            'reference' => $values['reference'],
            'commune' => $values['commune'],
            'status' => $values['status'],
            'deposit_number' => $values['deposit_number'],
            'deposit_date' => $values['deposit_date'],
            'decision_number' => $values['decision_number'],
            'decision_date' => $values['decision_date'],
            'deadline_date' => $values['deadline_date'],
            'notes' => $values['notes'],
            'created_by' => $authorId,
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    /** @param array<string,mixed> $values */
    public function update(int $id, array $values): void
    {
        $statement = $this->pdo->prepare(
            'UPDATE permis_batir_dossiers SET
                commune = :commune,
                status = :status,
                deposit_number = :deposit_number,
                deposit_date = :deposit_date,
                decision_number = :decision_number,
                decision_date = :decision_date,
                deadline_date = :deadline_date,
                notes = :notes
             WHERE id = :id'
        );
        $statement->execute([
            'commune' => $values['commune'],
            'status' => $values['status'],
            'deposit_number' => $values['deposit_number'],
            'deposit_date' => $values['deposit_date'],
            'decision_number' => $values['decision_number'],
            'decision_date' => $values['decision_date'],
            'deadline_date' => $values['deadline_date'],
            'notes' => $values['notes'],
            'id' => $id,
        ]);
    }

    /** Prochaine référence libre de l’année : PB-AAAA-NNN. */
    public function nextReference(int $year): string
    {
        $prefix = 'PB-' . $year . '-';
        $statement = $this->pdo->prepare(
            'SELECT reference FROM permis_batir_dossiers WHERE reference LIKE ? ORDER BY reference DESC LIMIT 1'
        );
        $statement->execute([$prefix . '%']);
        $last = $statement->fetchColumn();
        $sequence = 1;
        if (is_string($last) && preg_match('/^PB-\d{4}-(\d{3,})$/', $last, $matches) === 1) {
            $sequence = (int) $matches[1] + 1;
        }

        return $prefix . str_pad((string) $sequence, 3, '0', STR_PAD_LEFT);
    }

    public function referenceExists(string $reference): bool
    {
        $statement = $this->pdo->prepare('SELECT 1 FROM permis_batir_dossiers WHERE reference = ? LIMIT 1');
        $statement->execute([$reference]);

        return $statement->fetchColumn() !== false;
    }

    /** @return array<int,array<string,mixed>> */
    public function pieces(int $dossierId): array
    {
        $statement = $this->pdo->prepare(
            'SELECT id, code, label, is_required, is_deposited, deposited_on
             FROM permis_batir_pieces WHERE dossier_id = ? ORDER BY sort_order, id'
        );
        $statement->execute([$dossierId]);

        return $statement->fetchAll();
    }

    /**
     * Insère la liste type des pièces d’un dossier.
     *
     * @param array<int,array{code:string,label:string,required:bool}> $pieces
     */
    public function seedPieces(int $dossierId, array $pieces): void
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO permis_batir_pieces (dossier_id, code, label, is_required, sort_order)
             VALUES (?, ?, ?, ?, ?)'
        );
        $position = 0;
        foreach ($pieces as $piece) {
            $position += 10;
            $statement->execute([
                $dossierId,
                $piece['code'],
                $piece['label'],
                $piece['required'] ? 1 : 0,
                $position,
            ]);
        }
    }

    /** Bascule l’état d’une pièce, en vérifiant qu’elle appartient bien au dossier. */
    public function togglePiece(int $dossierId, int $pieceId, bool $deposited): bool
    {
        $statement = $this->pdo->prepare(
            'UPDATE permis_batir_pieces
             SET is_deposited = ?, deposited_on = ?
             WHERE id = ? AND dossier_id = ?'
        );
        $statement->execute([$deposited ? 1 : 0, $deposited ? date('Y-m-d') : null, $pieceId, $dossierId]);

        return $statement->rowCount() > 0;
    }

    /** @return array<int,array<string,mixed>> */
    public function events(int $dossierId): array
    {
        $statement = $this->pdo->prepare(
            'SELECT e.id, e.event_date, e.kind, e.summary, e.details, u.name AS author_name
             FROM permis_batir_evenements e
             LEFT JOIN users u ON u.id = e.created_by
             WHERE e.dossier_id = ?
             ORDER BY e.event_date DESC, e.id DESC'
        );
        $statement->execute([$dossierId]);

        return $statement->fetchAll();
    }

    public function addEvent(int $dossierId, string $date, string $kind, string $summary, string $details, ?int $authorId): void
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO permis_batir_evenements (dossier_id, event_date, kind, summary, details, created_by)
             VALUES (?, ?, ?, ?, ?, ?)'
        );
        $statement->execute([
            $dossierId,
            $date,
            $kind,
            $summary,
            $details !== '' ? $details : null,
            $authorId,
        ]);
    }

    /**
     * Compteurs du tableau de bord.
     *
     * @return array{total:int,instruction:int,granted:int,refused:int,overdue:int,preparation:int}
     */
    public function statistics(): array
    {
        $row = $this->pdo->query(
            "SELECT COUNT(*) AS total,
                    SUM(status IN ('deposited', 'instruction', 'complement')) AS instruction,
                    SUM(status = 'granted') AS granted,
                    SUM(status = 'refused') AS refused,
                    SUM(status = 'preparation') AS preparation,
                    SUM(deadline_date IS NOT NULL AND deadline_date < CURDATE()
                        AND status IN ('deposited', 'instruction', 'complement')) AS overdue
             FROM permis_batir_dossiers"
        )->fetch();

        $statistics = [];
        foreach (['total', 'instruction', 'granted', 'refused', 'preparation', 'overdue'] as $key) {
            $statistics[$key] = (int) ($row[$key] ?? 0);
        }

        return $statistics;
    }

    /** @return array<int,array<string,mixed>> Projets sélectionnables, avec leur client. */
    public function projectsForSelect(): array
    {
        return $this->pdo->query(
            "SELECT p.id, p.name, p.reference, c.display_name AS client_name
             FROM projects p
             INNER JOIN clients c ON c.id = p.client_id
             WHERE p.status <> 'cancelled'
             ORDER BY p.updated_at DESC, p.id DESC
             LIMIT 500"
        )->fetchAll();
    }

    public function projectExists(int $projectId): bool
    {
        $statement = $this->pdo->prepare("SELECT 1 FROM projects WHERE id = ? AND status <> 'cancelled' LIMIT 1");
        $statement->execute([$projectId]);

        return $statement->fetchColumn() !== false;
    }

    /** @return array<int,string> Années présentes dans les dossiers. */
    public function years(): array
    {
        return $this->pdo->query(
            'SELECT DISTINCT YEAR(COALESCE(deposit_date, created_at)) AS year
             FROM permis_batir_dossiers ORDER BY year DESC'
        )->fetchAll(PDO::FETCH_COLUMN);
    }

    /**
     * @param array{q:string,status:string,year:string} $filters
     * @return array{0:string,1:array<int,mixed>}
     */
    private function listConditions(array $filters): array
    {
        $conditions = [];
        $parameters = [];

        if ($filters['q'] !== '') {
            $conditions[] = '(d.reference LIKE :search OR d.commune LIKE :search
                              OR p.name LIKE :search OR c.display_name LIKE :search
                              OR d.deposit_number LIKE :search)';
            $parameters['search'] = '%' . $filters['q'] . '%';
        }
        if ($filters['status'] !== '') {
            $conditions[] = 'd.status = :status';
            $parameters['status'] = $filters['status'];
        }
        if ($filters['year'] !== '') {
            $conditions[] = 'YEAR(COALESCE(d.deposit_date, d.created_at)) = :year';
            $parameters['year'] = (int) $filters['year'];
        }

        return [$conditions === [] ? '' : ' WHERE ' . implode(' AND ', $conditions), $parameters];
    }
}
