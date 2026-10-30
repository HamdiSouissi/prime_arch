<?php
declare(strict_types=1);

namespace Modules\PortailClient\Models;

use App\Core\Database;
use PDO;

/**
 * Accès PDO aux liens du portail client.
 *
 * Le module n’écrit que dans ses tables `portail_*`. Les clients, projets, phases, documents,
 * images et utilisateurs du noyau sont uniquement lus, pour composer l’espace client et les
 * options des écrans du cabinet.
 */
final class PortailRepository
{
    /** Colonnes d’un lien, enrichies du client et du projet. */
    private const LINK_COLUMNS = "j.id, j.label, j.client_id, j.scope, j.project_id,
            j.can_view_documents, j.can_view_amounts, j.can_view_photos,
            j.expires_on, j.revoked_at, j.last_seen_at, j.access_count, j.created_at,
            u.name AS author_name,
            c.reference AS client_reference, c.display_name AS client_name,
            p.reference AS project_reference, p.name AS project_name";

    private const LINK_JOINS = " FROM portail_jetons j
            INNER JOIN clients c ON c.id = j.client_id
            LEFT JOIN projects p ON p.id = j.project_id
            LEFT JOIN users u ON u.id = j.created_by";

    private PDO $pdo;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? Database::connection();
    }

    /**
     * Liens du cabinet, les plus utiles d’abord : actifs, puis expirants, puis les autres.
     *
     * @param array<string,string> $filters state (`active`, `expired`, `revoked`), client
     * @return array<int,array<string,mixed>>
     */
    public function links(array $filters, string $today): array
    {
        $conditions = [];
        $parameters = ['today_order' => $today];

        $state = (string) ($filters['state'] ?? '');
        if ($state === 'active') {
            $conditions[] = 'j.revoked_at IS NULL AND j.expires_on >= :today_active';
            $parameters['today_active'] = $today;
        } elseif ($state === 'expired') {
            $conditions[] = 'j.revoked_at IS NULL AND j.expires_on < :today_expired';
            $parameters['today_expired'] = $today;
        } elseif ($state === 'revoked') {
            $conditions[] = 'j.revoked_at IS NOT NULL';
        }
        $client = (string) ($filters['client'] ?? '');
        if (ctype_digit($client)) {
            $conditions[] = 'j.client_id = :client_value';
            $parameters['client_value'] = (int) $client;
        }

        $statement = $this->pdo->prepare(
            'SELECT ' . self::LINK_COLUMNS . self::LINK_JOINS
            . ($conditions === [] ? '' : ' WHERE ' . implode(' AND ', $conditions))
            . ' ORDER BY (j.revoked_at IS NULL AND j.expires_on >= :today_order) DESC,
                j.expires_on ASC, j.id DESC'
        );
        $statement->execute($parameters);

        return $statement->fetchAll();
    }

    /** @return array<string,mixed>|null */
    public function find(int $id): ?array
    {
        $statement = $this->pdo->prepare('SELECT ' . self::LINK_COLUMNS . self::LINK_JOINS . ' WHERE j.id = :id LIMIT 1');
        $statement->execute(['id' => $id]);
        $row = $statement->fetch();

        return is_array($row) ? $row : null;
    }

    /**
     * Recherche par empreinte du jeton : c’est la seule clé utilisée par les routes publiques.
     *
     * @return array<string,mixed>|null
     */
    public function findByHash(string $tokenHash): ?array
    {
        $statement = $this->pdo->prepare(
            'SELECT id, token_hash, label, client_id, scope, project_id,
                    can_view_documents, can_view_amounts, can_view_photos,
                    expires_on, revoked_at, access_count
             FROM portail_jetons WHERE token_hash = :token_hash LIMIT 1'
        );
        $statement->execute(['token_hash' => $tokenHash]);
        $row = $statement->fetch();

        return is_array($row) ? $row : null;
    }

    /** @param array<string,mixed> $values */
    public function createLink(string $tokenHash, array $values, ?int $authorId): int
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO portail_jetons (
                token_hash, label, client_id, scope, project_id,
                can_view_documents, can_view_amounts, can_view_photos,
                expires_on, created_by
             ) VALUES (
                :token_hash, :label, :client_id, :scope, :project_id,
                :can_view_documents, :can_view_amounts, :can_view_photos,
                :expires_on, :created_by
             )'
        );
        $statement->execute([
            'token_hash' => $tokenHash,
            'label' => $values['label'],
            'client_id' => $values['client_id'],
            'scope' => $values['scope'],
            'project_id' => $values['project_id'],
            'can_view_documents' => $values['can_view_documents'] ? 1 : 0,
            'can_view_amounts' => $values['can_view_amounts'] ? 1 : 0,
            'can_view_photos' => $values['can_view_photos'] ? 1 : 0,
            'expires_on' => $values['expires_on'],
            'created_by' => $authorId,
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    /** Révocation immédiate : le lien cesse de fonctionner au prochain accès. */
    public function revoke(int $id): bool
    {
        $statement = $this->pdo->prepare(
            'UPDATE portail_jetons SET revoked_at = CURRENT_TIMESTAMP WHERE id = :id AND revoked_at IS NULL'
        );
        $statement->execute(['id' => $id]);

        return $statement->rowCount() > 0;
    }

    /** Prolonge la validité sans réactiver un lien révoqué. */
    public function extend(int $id, string $expiresOn): bool
    {
        $statement = $this->pdo->prepare(
            'UPDATE portail_jetons SET expires_on = :expires_on WHERE id = :id'
        );
        $statement->execute(['expires_on' => $expiresOn, 'id' => $id]);

        return $statement->rowCount() > 0;
    }

    public function deleteLink(int $id): bool
    {
        $statement = $this->pdo->prepare('DELETE FROM portail_jetons WHERE id = :id');
        $statement->execute(['id' => $id]);

        return $statement->rowCount() > 0;
    }

    /** Compteur agrégé et dernière consultation, mis à jour à chaque affichage réussi. */
    public function touch(int $id): void
    {
        $statement = $this->pdo->prepare(
            'UPDATE portail_jetons
             SET access_count = access_count + 1, last_seen_at = CURRENT_TIMESTAMP
             WHERE id = :id'
        );
        $statement->execute(['id' => $id]);
    }

    /** @param array<string,mixed> $context */
    public function recordAccess(int $jetonId, string $page, string $ipHash, string $userAgent): void
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO portail_acces (jeton_id, page, ip_hash, user_agent)
             VALUES (:jeton_id, :page, :ip_hash, :user_agent)'
        );
        $statement->execute([
            'jeton_id' => $jetonId,
            'page' => $page,
            'ip_hash' => $ipHash,
            'user_agent' => $userAgent !== '' ? $userAgent : null,
        ]);
    }

    /**
     * Journal des consultations d’un lien (sans adresse IP en clair).
     *
     * @return array<int,array<string,mixed>>
     */
    public function accesses(int $jetonId, int $limit = 50): array
    {
        $statement = $this->pdo->prepare(
            'SELECT id, accessed_at, page, user_agent, LEFT(ip_hash, 12) AS ip_fingerprint
             FROM portail_acces
             WHERE jeton_id = :jeton_id
             ORDER BY accessed_at DESC, id DESC
             LIMIT ' . max(1, min(200, $limit))
        );
        $statement->execute(['jeton_id' => $jetonId]);

        return $statement->fetchAll();
    }

    /** Nombre de consultations sur les 30 derniers jours, tous liens confondus. */
    public function recentAccessCount(int $days = 30): int
    {
        $statement = $this->pdo->query(
            'SELECT COUNT(*) FROM portail_acces
             WHERE accessed_at >= DATE_SUB(CURRENT_TIMESTAMP, INTERVAL ' . max(1, min(365, $days)) . ' DAY)'
        );

        return (int) $statement->fetchColumn();
    }

    /** Enregistre une tentative (acceptée ou refusée) pour la limitation de débit. */
    public function recordAttempt(string $ipHash, bool $granted): void
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO portail_tentatives (ip_hash, result) VALUES (:ip_hash, :result)'
        );
        $statement->execute(['ip_hash' => $ipHash, 'result' => $granted ? 'granted' : 'denied']);
    }

    /** Tentatives récentes d’une adresse, éventuellement filtrées sur les refus. */
    public function countAttempts(string $ipHash, int $minutes, bool $deniedOnly = false): int
    {
        $sql = 'SELECT COUNT(*) FROM portail_tentatives
                WHERE ip_hash = :ip_hash
                  AND attempted_at >= DATE_SUB(CURRENT_TIMESTAMP, INTERVAL ' . max(1, min(1440, $minutes)) . ' MINUTE)'
            . ($deniedOnly ? " AND result = 'denied'" : '');
        $statement = $this->pdo->prepare($sql);
        $statement->execute(['ip_hash' => $ipHash]);

        return (int) $statement->fetchColumn();
    }

    /** Purge opportuniste des tentatives anciennes (la table ne doit pas croître sans fin). */
    public function pruneAttempts(int $keepDays = 2): void
    {
        $statement = $this->pdo->query(
            'DELETE FROM portail_tentatives
             WHERE attempted_at < DATE_SUB(CURRENT_TIMESTAMP, INTERVAL ' . max(1, min(30, $keepDays)) . ' DAY)'
        );
    }

    /** @return array<string,mixed>|null */
    public function client(int $clientId): ?array
    {
        $statement = $this->pdo->prepare(
            'SELECT id, reference, display_name, city FROM clients WHERE id = :id LIMIT 1'
        );
        $statement->execute(['id' => $clientId]);
        $row = $statement->fetch();

        return is_array($row) ? $row : null;
    }

    /**
     * Projets visibles : tout le client ou un seul projet, y compris les projets annulés
     * (leur état est affiché tel quel, sans être masqué au client).
     *
     * @return array<int,array<string,mixed>>
     */
    public function projects(int $clientId, ?int $projectId = null): array
    {
        $sql = 'SELECT id, reference, name, project_type, status, city, start_date, target_end_date, updated_at
                FROM projects
                WHERE client_id = :client_id'
            . ($projectId !== null ? ' AND id = :project_id' : '')
            . ' ORDER BY (status = \'cancelled\') ASC, start_date DESC, id DESC';
        $statement = $this->pdo->prepare($sql);
        $parameters = ['client_id' => $clientId];
        if ($projectId !== null) {
            $parameters['project_id'] = $projectId;
        }
        $statement->execute($parameters);

        return $statement->fetchAll();
    }

    /**
     * Phases des projets demandés, dans leur ordre d’affichage.
     *
     * @param array<int,int> $projectIds
     * @return array<int,array<string,mixed>> lignes indexées par projet côté appelant
     */
    public function phases(array $projectIds): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $projectIds), static fn (int $id): bool => $id > 0)));
        if ($ids === []) {
            return [];
        }
        $placeholders = implode(', ', array_fill(0, count($ids), '?'));
        $statement = $this->pdo->prepare(
            'SELECT project_id, phase_name, status, progress_percent, weight_percent, sort_order
             FROM project_phase_assignments
             WHERE project_id IN (' . $placeholders . ')
             ORDER BY project_id ASC, sort_order ASC, id ASC'
        );
        $statement->execute($ids);

        return $statement->fetchAll();
    }

    /**
     * Documents émis visibles par le client : jamais les brouillons ni les pièces annulées.
     *
     * @return array<int,array<string,mixed>>
     */
    public function documents(int $clientId, ?int $projectId = null, bool $withAmounts = false): array
    {
        $columns = 'id, document_number, document_type, issue_date, due_date, project_id'
            . ($withAmounts ? ', total_amount' : '');
        $sql = 'SELECT ' . $columns . '
                FROM documents
                WHERE client_id = :client_id AND status = \'issued\''
            . ($projectId !== null ? ' AND project_id = :project_id' : '')
            . ' ORDER BY issue_date DESC, id DESC';
        $statement = $this->pdo->prepare($sql);
        $parameters = ['client_id' => $clientId];
        if ($projectId !== null) {
            $parameters['project_id'] = $projectId;
        }
        $statement->execute($parameters);

        return $statement->fetchAll();
    }

    /**
     * Dernières photos des projets visibles, sans le chemin de stockage.
     *
     * @param array<int,int> $projectIds
     * @return array<int,array<string,mixed>>
     */
    public function photos(array $projectIds, int $limitPerProject = 12): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $projectIds), static fn (int $id): bool => $id > 0)));
        if ($ids === []) {
            return [];
        }
        $placeholders = implode(', ', array_fill(0, count($ids), '?'));
        $statement = $this->pdo->prepare(
            'SELECT id, project_id, mime_type, width, height, created_at
             FROM project_images
             WHERE project_id IN (' . $placeholders . ')
             ORDER BY project_id ASC, created_at DESC, id DESC'
        );
        $statement->execute($ids);

        $rows = $statement->fetchAll();
        $counts = [];
        $kept = [];
        $limit = max(1, min(50, $limitPerProject));
        foreach ($rows as $row) {
            $projectId = (int) $row['project_id'];
            $counts[$projectId] = ($counts[$projectId] ?? 0) + 1;
            if ($counts[$projectId] <= $limit) {
                $kept[] = $row;
            }
        }

        return $kept;
    }

    /**
     * Photo précise : renvoie le projet et le nom stocké, jamais le contenu.
     *
     * @return array<string,mixed>|null
     */
    public function photo(int $imageId): ?array
    {
        $statement = $this->pdo->prepare(
            'SELECT id, project_id, stored_name, mime_type, file_size FROM project_images WHERE id = :id LIMIT 1'
        );
        $statement->execute(['id' => $imageId]);
        $row = $statement->fetch();

        return is_array($row) ? $row : null;
    }

    /** Vérifie qu’un projet appartient bien au client du lien (portée « client »). */
    public function projectBelongsToClient(int $projectId, int $clientId): bool
    {
        $statement = $this->pdo->prepare(
            'SELECT 1 FROM projects WHERE id = :id AND client_id = :client_id LIMIT 1'
        );
        $statement->execute(['id' => $projectId, 'client_id' => $clientId]);

        return $statement->fetchColumn() !== false;
    }

    /**
     * Clients proposés à la création d’un lien (hors archives).
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
     * Projets du noyau, avec leur client, pour le choix de la portée.
     *
     * @return array<int,array<string,mixed>>
     */
    public function projectOptions(): array
    {
        $rows = $this->pdo->query(
            "SELECT p.id, p.reference, p.name, p.client_id, c.display_name AS client_name
             FROM projects p
             INNER JOIN clients c ON c.id = p.client_id
             WHERE c.status <> 'archived'
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
}
