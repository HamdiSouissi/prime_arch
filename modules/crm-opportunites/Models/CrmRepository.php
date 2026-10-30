<?php
declare(strict_types=1);

namespace Modules\CrmOpportunites\Models;

use App\Core\Database;
use PDO;

/**
 * Accès PDO aux prospects du pipeline commercial.
 *
 * Toutes les écritures portent sur les tables `crm_*`. Les clients, projets et utilisateurs du
 * noyau sont seulement lus (options de rattachement, propriétaire) : aucun enregistrement du
 * cœur n’est créé, modifié ou supprimé par ce module.
 */
final class CrmRepository
{
    /** Colonnes de l’opportunité, communes aux lectures de liste et de détail. */
    private const COLUMNS = "o.id, o.title, o.prospect_name, o.client_type, o.email, o.phone, o.city,
            o.origin, o.stage, o.estimated_amount, o.expected_close_on, o.next_action_on,
            o.owner_id, o.client_id, o.project_id, o.converted_at, o.lost_reason, o.notes,
            o.created_at, o.updated_at,
            u.name AS owner_name,
            c.reference AS client_reference, c.display_name AS client_name,
            p.reference AS project_reference, p.name AS project_name,
            (SELECT COUNT(*) FROM crm_activites a WHERE a.opportunite_id = o.id) AS activity_count,
            (SELECT MAX(a.occurred_on) FROM crm_activites a WHERE a.opportunite_id = o.id) AS last_activity_on";

    private const JOINS = " FROM crm_opportunites o
            LEFT JOIN users u ON u.id = o.owner_id
            LEFT JOIN clients c ON c.id = o.client_id
            LEFT JOIN projects p ON p.id = o.project_id";

    private PDO $pdo;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? Database::connection();
    }

    /**
     * Opportunités de la sélection, relances les plus urgentes d’abord puis les fiches ouvertes
     * récemment modifiées ; les opportunités gagnées ou perdues ferment la liste.
     *
     * @param array<string,mixed> $filters stage, origin, owner_id, follow, search
     * @return array<int,array<string,mixed>>
     */
    public function opportunities(array $filters, string $today, string $weekEnd): array
    {
        $conditions = [];
        $parameters = [];

        $stage = (string) ($filters['stage'] ?? '');
        if ($stage !== '') {
            $conditions[] = 'o.stage = :stage_value';
            $parameters['stage_value'] = $stage;
        }
        $origin = (string) ($filters['origin'] ?? '');
        if ($origin !== '') {
            $conditions[] = 'o.origin = :origin_value';
            $parameters['origin_value'] = $origin;
        }
        $owner = (string) ($filters['owner_id'] ?? '');
        if ($owner === 'none') {
            $conditions[] = 'o.owner_id IS NULL';
        } elseif (ctype_digit($owner)) {
            $conditions[] = 'o.owner_id = :owner_value';
            $parameters['owner_value'] = (int) $owner;
        }
        $clientId = (string) ($filters['client_id'] ?? '');
        if (ctype_digit($clientId)) {
            $conditions[] = 'o.client_id = :client_value';
            $parameters['client_value'] = (int) $clientId;
        }
        $follow = (string) ($filters['follow'] ?? '');
        if ($follow === 'late') {
            $conditions[] = "o.stage NOT IN ('won', 'lost') AND o.next_action_on IS NOT NULL AND o.next_action_on < :today_late";
            $parameters['today_late'] = $today;
        } elseif ($follow === 'today') {
            $conditions[] = "o.stage NOT IN ('won', 'lost') AND o.next_action_on = :today_value";
            $parameters['today_value'] = $today;
        } elseif ($follow === 'week') {
            $conditions[] = "o.stage NOT IN ('won', 'lost') AND o.next_action_on BETWEEN :week_start AND :week_end";
            $parameters['week_start'] = $today;
            $parameters['week_end'] = $weekEnd;
        } elseif ($follow === 'none') {
            $conditions[] = "o.stage NOT IN ('won', 'lost') AND o.next_action_on IS NULL";
        }
        $search = trim((string) ($filters['search'] ?? ''));
        if ($search !== '') {
            $pattern = '%' . str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $search) . '%';
            $conditions[] = "(o.title LIKE :search_title ESCAPE '!'
                OR o.prospect_name LIKE :search_prospect ESCAPE '!'
                OR o.email LIKE :search_email ESCAPE '!'
                OR o.phone LIKE :search_phone ESCAPE '!'
                OR o.city LIKE :search_city ESCAPE '!')";
            $parameters += [
                'search_title' => $pattern,
                'search_prospect' => $pattern,
                'search_email' => $pattern,
                'search_phone' => $pattern,
                'search_city' => $pattern,
            ];
        }

        $statement = $this->pdo->prepare(
            'SELECT ' . self::COLUMNS . self::JOINS
            . ($conditions === [] ? '' : ' WHERE ' . implode(' AND ', $conditions))
            . " ORDER BY (o.stage IN ('won', 'lost')) ASC, (o.next_action_on IS NULL) ASC,
                o.next_action_on ASC, o.updated_at DESC, o.id DESC"
        );
        $statement->execute($parameters);

        return $statement->fetchAll();
    }

    /** @return array<string,mixed>|null */
    public function find(int $id): ?array
    {
        $statement = $this->pdo->prepare('SELECT ' . self::COLUMNS . self::JOINS . ' WHERE o.id = :id LIMIT 1');
        $statement->execute(['id' => $id]);
        $row = $statement->fetch();

        return is_array($row) ? $row : null;
    }

    /**
     * Journal des activités d’une opportunité, de la plus récente à la plus ancienne.
     *
     * @return array<int,array<string,mixed>>
     */
    /** Nombre d'opportunités encore ouvertes pour un client (passerelle de la fiche client). */
    public function openCountForClient(int $clientId): int
    {
        $statement = $this->pdo->prepare(
            "SELECT COUNT(*) FROM crm_opportunites WHERE client_id = ? AND stage NOT IN ('won', 'lost')"
        );
        $statement->execute([$clientId]);

        return (int) $statement->fetchColumn();
    }

    public function activities(int $opportuniteId): array
    {
        $statement = $this->pdo->prepare(
            "SELECT a.id, a.opportunite_id, a.kind, a.occurred_on, a.summary, a.detail, a.created_at,
                    u.name AS author_name
             FROM crm_activites a
             LEFT JOIN users u ON u.id = a.created_by
             WHERE a.opportunite_id = :opportunite_id
             ORDER BY a.occurred_on DESC, a.id DESC"
        );
        $statement->execute(['opportunite_id' => $opportuniteId]);

        return $statement->fetchAll();
    }

    /** @param array<string,mixed> $values */
    public function create(array $values, ?int $authorId): int
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO crm_opportunites (
                title, prospect_name, client_type, email, phone, city, origin, stage,
                estimated_amount, expected_close_on, next_action_on, owner_id, lost_reason, notes, created_by
             ) VALUES (
                :title, :prospect_name, :client_type, :email, :phone, :city, :origin, :stage,
                :estimated_amount, :expected_close_on, :next_action_on, :owner_id, :lost_reason, :notes, :created_by
             )'
        );
        $statement->execute([
            'title' => $values['title'],
            'prospect_name' => $values['prospect_name'],
            'client_type' => $values['client_type'],
            'email' => $values['email'] !== '' ? $values['email'] : null,
            'phone' => $values['phone'] !== '' ? $values['phone'] : null,
            'city' => $values['city'] !== '' ? $values['city'] : null,
            'origin' => $values['origin'],
            'stage' => $values['stage'],
            'estimated_amount' => $values['estimated_amount'],
            'expected_close_on' => $values['expected_close_on'],
            'next_action_on' => $values['next_action_on'],
            'owner_id' => $values['owner_id'],
            'lost_reason' => $values['lost_reason'] !== '' ? $values['lost_reason'] : null,
            'notes' => $values['notes'] !== '' ? $values['notes'] : null,
            'created_by' => $authorId,
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    /** @param array<string,mixed> $values */
    public function update(int $id, array $values): bool
    {
        $statement = $this->pdo->prepare(
            'UPDATE crm_opportunites SET
                title = :title, prospect_name = :prospect_name, client_type = :client_type,
                email = :email, phone = :phone, city = :city, origin = :origin, stage = :stage,
                estimated_amount = :estimated_amount, expected_close_on = :expected_close_on,
                next_action_on = :next_action_on, owner_id = :owner_id,
                lost_reason = :lost_reason, notes = :notes
             WHERE id = :id'
        );
        $statement->execute([
            'title' => $values['title'],
            'prospect_name' => $values['prospect_name'],
            'client_type' => $values['client_type'],
            'email' => $values['email'] !== '' ? $values['email'] : null,
            'phone' => $values['phone'] !== '' ? $values['phone'] : null,
            'city' => $values['city'] !== '' ? $values['city'] : null,
            'origin' => $values['origin'],
            'stage' => $values['stage'],
            'estimated_amount' => $values['estimated_amount'],
            'expected_close_on' => $values['expected_close_on'],
            'next_action_on' => $values['next_action_on'],
            'owner_id' => $values['owner_id'],
            'lost_reason' => $values['lost_reason'] !== '' ? $values['lost_reason'] : null,
            'notes' => $values['notes'] !== '' ? $values['notes'] : null,
            'id' => $id,
        ]);

        return $statement->rowCount() > 0;
    }

    /** Change uniquement l’étape (et le motif de perte le cas échéant). */
    public function updateStage(int $id, string $stage, string $lostReason): bool
    {
        $statement = $this->pdo->prepare(
            'UPDATE crm_opportunites SET stage = :stage, lost_reason = :lost_reason WHERE id = :id'
        );
        $statement->execute([
            'stage' => $stage,
            'lost_reason' => $lostReason !== '' ? $lostReason : null,
            'id' => $id,
        ]);

        return $statement->rowCount() > 0;
    }

    /**
     * Rattache l’opportunité à un client (et éventuellement à un projet) existants du noyau.
     * Aucune fiche du cœur n’est créée : seuls les liens et l’étape de l’opportunité changent.
     */
    public function link(int $id, int $clientId, ?int $projectId, string $stage, string $lostReason): bool
    {
        $statement = $this->pdo->prepare(
            'UPDATE crm_opportunites SET
                client_id = :client_id, project_id = :project_id, converted_at = CURRENT_TIMESTAMP,
                stage = :stage, lost_reason = :lost_reason
             WHERE id = :id'
        );
        $statement->execute([
            'client_id' => $clientId,
            'project_id' => $projectId,
            'stage' => $stage,
            'lost_reason' => $lostReason !== '' ? $lostReason : null,
            'id' => $id,
        ]);

        return $statement->rowCount() > 0;
    }

    public function delete(int $id): bool
    {
        $statement = $this->pdo->prepare('DELETE FROM crm_opportunites WHERE id = :id');
        $statement->execute(['id' => $id]);

        return $statement->rowCount() > 0;
    }

    /** @param array<string,mixed> $values */
    public function addActivity(int $opportuniteId, array $values, ?int $authorId): int
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO crm_activites (opportunite_id, kind, occurred_on, summary, detail, created_by)
             VALUES (:opportunite_id, :kind, :occurred_on, :summary, :detail, :created_by)'
        );
        $statement->execute([
            'opportunite_id' => $opportuniteId,
            'kind' => $values['kind'],
            'occurred_on' => $values['occurred_on'],
            'summary' => $values['summary'],
            'detail' => $values['detail'] !== '' ? $values['detail'] : null,
            'created_by' => $authorId,
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    /** Supprime une activité, uniquement si elle appartient à l’opportunité indiquée. */
    public function deleteActivity(int $opportuniteId, int $activityId): bool
    {
        $statement = $this->pdo->prepare(
            'DELETE FROM crm_activites WHERE id = :id AND opportunite_id = :opportunite_id'
        );
        $statement->execute(['id' => $activityId, 'opportunite_id' => $opportuniteId]);

        return $statement->rowCount() > 0;
    }

    /**
     * Clients rattachables (hors archives), lus dans le noyau.
     *
     * @return array<int,array<string,mixed>>
     */
    public function clientOptions(): array
    {
        $rows = $this->pdo->query(
            "SELECT id, reference, display_name, city FROM clients WHERE status <> 'archived' ORDER BY display_name, id"
        )->fetchAll();

        return array_map(static fn (array $row): array => [
            'id' => (int) $row['id'],
            'reference' => (string) ($row['reference'] ?? ''),
            'display_name' => (string) $row['display_name'],
            'city' => (string) ($row['city'] ?? ''),
        ], $rows);
    }

    /**
     * Projets du noyau, avec leur client, pour le rattachement facultatif.
     *
     * @return array<int,array<string,mixed>>
     */
    public function projectOptions(): array
    {
        $rows = $this->pdo->query(
            "SELECT p.id, p.reference, p.name, p.client_id, c.display_name AS client_name
             FROM projects p
             INNER JOIN clients c ON c.id = p.client_id
             ORDER BY c.display_name, p.name, p.id"
        )->fetchAll();

        return array_map(static fn (array $row): array => [
            'id' => (int) $row['id'],
            'reference' => (string) ($row['reference'] ?? ''),
            'name' => (string) $row['name'],
            'client_id' => (int) $row['client_id'],
            'client_name' => (string) $row['client_name'],
        ], $rows);
    }

    /**
     * Utilisateurs actifs proposés comme responsable du suivi.
     *
     * @return array<int,array<string,mixed>>
     */
    public function ownerOptions(): array
    {
        $rows = $this->pdo->query(
            'SELECT id, name FROM users WHERE is_active = 1 ORDER BY name, id'
        )->fetchAll();

        return array_map(static fn (array $row): array => [
            'id' => (int) $row['id'],
            'name' => (string) $row['name'],
        ], $rows);
    }
}
