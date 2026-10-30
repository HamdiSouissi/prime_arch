<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use PDO;

/**
 * Lectures agrégées du tableau de bord (lot 4).
 *
 * Toutes les bornes numériques (limites) sont converties en entiers avant d'entrer dans la
 * requête ; les dates sont toujours liées en paramètres. Aucune donnée de démonstration :
 * chaque chiffre affiché provient des tables du noyau.
 */
final class DashboardRepository
{
    /** Nombre maximal de fiches renvoyées par liste du tableau de bord. */
    public const LIMIT = 12;

    private PDO $pdo;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? Database::connection();
    }

    /**
     * Compteurs de la semaine : clients, projets ouverts, retards, heures saisies.
     *
     * Les heures sont restreintes au compte connecté (`$scopeUserId`) ou à tout le cabinet
     * (`null`, réservé aux rôles qui voient le temps de tous).
     *
     * @return array<string,int|string>
     */
    public function metrics(string $today, string $weekStart, string $weekEnd, ?int $scopeUserId): array
    {
        // PDO est en mode natif : un même nom de paramètre ne peut pas servir deux fois.
        $timeScope = $scopeUserId === null ? '' : ' AND e.user_id = :scope_user';
        $billableScope = $scopeUserId === null ? '' : ' AND e.user_id = :scope_billable_user';
        $statement = $this->pdo->prepare(
            "SELECT
                (SELECT COUNT(*) FROM clients WHERE status = 'active') AS active_clients,
                (SELECT COUNT(*) FROM clients WHERE status = 'prospect') AS prospect_clients,
                (SELECT COUNT(*) FROM clients) AS client_total,
                (SELECT COUNT(*) FROM projects WHERE status IN ('planning', 'active', 'on_hold')) AS open_projects,
                (SELECT COUNT(*) FROM projects
                 WHERE status IN ('planning', 'active', 'on_hold')
                   AND target_end_date IS NOT NULL
                   AND target_end_date < :late_today) AS late_projects,
                (SELECT COUNT(*) FROM documents
                 WHERE document_type = 'invoice' AND status = 'issued') AS issued_invoices,
                (SELECT COALESCE(SUM(e.duration_hours), 0) FROM time_entries e
                 WHERE e.work_date BETWEEN :week_start AND :week_end" . $timeScope . ') AS week_hours,
                (SELECT COALESCE(SUM(CASE WHEN e.is_billable = 1 THEN e.duration_hours ELSE 0 END), 0)
                 FROM time_entries e
                 WHERE e.work_date BETWEEN :billable_start AND :billable_end' . $billableScope . ') AS week_billable_hours'
        );
        $parameters = [
            'late_today' => $today,
            'week_start' => $weekStart,
            'week_end' => $weekEnd,
            'billable_start' => $weekStart,
            'billable_end' => $weekEnd,
        ];
        if ($scopeUserId !== null) {
            $parameters['scope_user'] = $scopeUserId;
            $parameters['scope_billable_user'] = $scopeUserId;
        }
        $statement->execute($parameters);
        $row = $statement->fetch() ?: [];

        return [
            'active_clients' => (int) ($row['active_clients'] ?? 0),
            'prospect_clients' => (int) ($row['prospect_clients'] ?? 0),
            'client_total' => (int) ($row['client_total'] ?? 0),
            'open_projects' => (int) ($row['open_projects'] ?? 0),
            'late_projects' => (int) ($row['late_projects'] ?? 0),
            'issued_invoices' => (int) ($row['issued_invoices'] ?? 0),
            'week_hours' => (string) ($row['week_hours'] ?? '0.00'),
            'week_billable_hours' => (string) ($row['week_billable_hours'] ?? '0.00'),
        ];
    }

    /**
     * Projets ouverts les plus proches de leur échéance, avec leur avancement pondéré.
     *
     * @return array<int,array<string,mixed>>
     */
    public function openProjects(int $limit = 4): array
    {
        $statement = $this->pdo->prepare(
            "SELECT p.id, p.reference, p.name, p.status, p.target_end_date,
                    c.display_name AS client_name,
                    (SELECT CASE WHEN COALESCE(SUM(a.weight_percent), 0) = 0 THEN 0
                                 ELSE ROUND(SUM(a.weight_percent * a.progress_percent) / SUM(a.weight_percent), 2)
                            END
                     FROM project_phase_assignments a WHERE a.project_id = p.id) AS weighted_progress,
                    (SELECT COUNT(*) FROM project_phase_assignments a
                     WHERE a.project_id = p.id AND a.status <> 'completed') AS open_phases
             FROM projects p
             INNER JOIN clients c ON c.id = p.client_id
             WHERE p.status IN ('planning', 'active', 'on_hold')
             ORDER BY (p.target_end_date IS NULL), p.target_end_date ASC, p.updated_at DESC
             LIMIT " . $this->limit($limit)
        );
        $statement->execute();

        return array_map(static function (array $row): array {
            $row['id'] = (int) $row['id'];
            $row['open_phases'] = (int) $row['open_phases'];
            $row['weighted_progress'] = (string) ($row['weighted_progress'] ?? '0.00');
            $row['client_name'] = (string) ($row['client_name'] ?? '');
            $row['target_end_date'] = $row['target_end_date'] === null ? null : (string) $row['target_end_date'];

            return $row;
        }, $statement->fetchAll());
    }

    /**
     * Échéances des projets et des documents dans une fenêtre donnée.
     *
     * Seules les branches autorisées sont incluses : un utilisateur sans accès aux documents
     * ne reçoit aucune ligne de facture, et la requête reste une union de sélections bornées.
     *
     * @return array<int,array<string,mixed>>
     */
    public function upcoming(
        string $from,
        string $to,
        bool $includeProjects,
        bool $includeDocuments,
        int $limit = 5
    ): array {
        $parts = [];
        $parameters = [];
        if ($includeProjects) {
            $parts[] = "SELECT 'project' AS kind, p.id AS subject_id, p.reference AS reference,
                               p.name AS title, c.display_name AS client_name, p.target_end_date AS due_date
                        FROM projects p
                        INNER JOIN clients c ON c.id = p.client_id
                        WHERE p.status IN ('planning', 'active', 'on_hold')
                          AND p.target_end_date IS NOT NULL
                          AND p.target_end_date BETWEEN :project_from AND :project_to";
            $parameters['project_from'] = $from;
            $parameters['project_to'] = $to;
        }
        if ($includeDocuments) {
            $parts[] = "SELECT 'invoice' AS kind, d.id AS subject_id, d.document_number AS reference,
                               d.title AS title, c.display_name AS client_name, d.due_date AS due_date
                        FROM documents d
                        INNER JOIN clients c ON c.id = d.client_id
                        WHERE d.document_type = 'invoice' AND d.status = 'issued'
                          AND d.due_date IS NOT NULL
                          AND d.due_date BETWEEN :invoice_from AND :invoice_to
                          AND (d.total_amount
                               - COALESCE((SELECT SUM(pay.amount) FROM document_payments pay
                                           WHERE pay.document_id = d.id), 0)) > 0";
            $parameters['invoice_from'] = $from;
            $parameters['invoice_to'] = $to;
            $parts[] = "SELECT 'quote' AS kind, d.id AS subject_id, d.document_number AS reference,
                               d.title AS title, c.display_name AS client_name, d.valid_until AS due_date
                        FROM documents d
                        INNER JOIN clients c ON c.id = d.client_id
                        WHERE d.document_type = 'quote' AND d.status = 'issued'
                          AND d.valid_until IS NOT NULL
                          AND d.valid_until BETWEEN :quote_from AND :quote_to";
            $parameters['quote_from'] = $from;
            $parameters['quote_to'] = $to;
        }
        if ($parts === []) {
            return [];
        }

        $statement = $this->pdo->prepare(
            implode("\nUNION ALL\n", $parts)
            . ' ORDER BY due_date ASC, title ASC LIMIT ' . $this->limit($limit)
        );
        $statement->execute($parameters);

        return $statement->fetchAll();
    }

    /**
     * Factures émises dont l'échéance est passée et qui gardent un solde.
     *
     * @return array<int,array<string,mixed>>
     */
    public function overdueInvoices(string $today, int $limit = 3): array
    {
        $statement = $this->pdo->prepare(
            "SELECT 'invoice' AS kind, d.id AS subject_id, d.document_number AS reference,
                    d.title AS title, c.display_name AS client_name, d.due_date AS due_date
             FROM documents d
             INNER JOIN clients c ON c.id = d.client_id
             WHERE d.document_type = 'invoice' AND d.status = 'issued'
               AND d.due_date IS NOT NULL
               AND d.due_date < :overdue_today
               AND (d.total_amount
                    - COALESCE((SELECT SUM(pay.amount) FROM document_payments pay
                                WHERE pay.document_id = d.id), 0)) > 0
             ORDER BY d.due_date ASC, d.id ASC
             LIMIT " . $this->limit($limit)
        );
        $statement->execute(['overdue_today' => $today]);

        return $statement->fetchAll();
    }

    /**
     * Heures saisies jour par jour sur une semaine.
     *
     * @return array<int,array{day:string,hours:string,billable:string}>
     */
    public function weekHours(string $weekStart, string $weekEnd, ?int $scopeUserId): array
    {
        $timeScope = $scopeUserId === null ? '' : ' AND e.user_id = :scope_user';
        $statement = $this->pdo->prepare(
            'SELECT e.work_date AS day,
                    COALESCE(SUM(e.duration_hours), 0) AS hours,
                    COALESCE(SUM(CASE WHEN e.is_billable = 1 THEN e.duration_hours ELSE 0 END), 0) AS billable
             FROM time_entries e
             WHERE e.work_date BETWEEN :start AND :end' . $timeScope . '
             GROUP BY e.work_date
             ORDER BY e.work_date ASC'
        );
        $parameters = ['start' => $weekStart, 'end' => $weekEnd];
        if ($scopeUserId !== null) {
            $parameters['scope_user'] = $scopeUserId;
        }
        $statement->execute($parameters);

        return array_map(static fn (array $row): array => [
            'day' => (string) $row['day'],
            'hours' => (string) $row['hours'],
            'billable' => (string) $row['billable'],
        ], $statement->fetchAll());
    }

    /**
     * Derniers événements du journal limités aux objets que l'utilisateur peut consulter.
     *
     * @param array<int,string> $subjectTypes
     * @return array<int,array<string,mixed>>
     */
    public function activity(array $subjectTypes, int $limit = 6): array
    {
        $subjectTypes = array_values(array_filter($subjectTypes, static fn ($type): bool => is_string($type) && $type !== ''));
        if ($subjectTypes === []) {
            return [];
        }
        $placeholders = [];
        $parameters = [];
        foreach ($subjectTypes as $index => $type) {
            $placeholders[] = ':subject_' . $index;
            $parameters['subject_' . $index] = $type;
        }
        $statement = $this->pdo->prepare(
            'SELECT l.id, l.event_key, l.subject_type, l.subject_id, l.created_at, u.name AS actor_name
             FROM audit_logs l
             LEFT JOIN users u ON u.id = l.actor_id
             WHERE l.subject_type IN (' . implode(', ', $placeholders) . ')
             ORDER BY l.created_at DESC, l.id DESC
             LIMIT ' . $this->limit($limit)
        );
        $statement->execute($parameters);

        return $statement->fetchAll();
    }

    private function limit(int $limit): int
    {
        return max(1, min($limit, self::LIMIT));
    }
}
