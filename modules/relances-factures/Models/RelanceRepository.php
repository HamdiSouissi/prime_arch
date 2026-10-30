<?php
declare(strict_types=1);

namespace Modules\RelancesFactures\Models;

use App\Core\Database;
use PDO;

/**
 * Accès PDO aux factures impayées (lecture seule du cœur) et aux tables du module.
 *
 * Aucune écriture n’est faite dans `documents`, `document_payments` ou `clients` :
 * le module se contente de constater l’encours et de consigner les relances.
 */
final class RelanceRepository
{
    private PDO $pdo;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? Database::connection();
    }

    /**
     * Factures émises dont l’encours n’est pas soldé.
     *
     * Le calcul de l’encours et de l’ancienneté est fait en PHP pour rester lisible et
     * pour éviter toute dépendance à une fonction de date propre à un SGBD.
     *
     * @return array<int,array<string,mixed>>
     */
    public function unpaidInvoices(int $limit = 500): array
    {
        $statement = $this->pdo->prepare(
            "SELECT d.id, d.document_number, d.title, d.issue_date, d.due_date, d.total_amount,
                    d.client_id, c.display_name AS client_name,
                    (SELECT p.name FROM projects p WHERE p.id = d.project_id) AS project_name,
                    (SELECT COALESCE(SUM(pay.amount), 0) FROM document_payments pay WHERE pay.document_id = d.id) AS paid_amount,
                    (SELECT MAX(ev.sent_on) FROM relances_evenements ev WHERE ev.document_id = d.id) AS last_relance_on,
                    (SELECT ev.tone FROM relances_evenements ev WHERE ev.document_id = d.id
                        ORDER BY ev.sent_on DESC, ev.id DESC LIMIT 1) AS last_tone,
                    (SELECT COUNT(*) FROM relances_evenements ev WHERE ev.document_id = d.id) AS relance_count,
                    EXISTS (SELECT 1 FROM relances_exclusions x WHERE x.client_id = d.client_id) AS is_excluded
             FROM documents d
             INNER JOIN clients c ON c.id = d.client_id
             WHERE d.document_type = 'invoice' AND d.status = 'issued'
             ORDER BY d.due_date IS NULL, d.due_date, d.id
             LIMIT " . max(1, min($limit, 2000))
        );
        $statement->execute();

        return $statement->fetchAll();
    }

    /** @return array<string,mixed>|null Facture émise avec son client, son projet et ses règlements. */
    public function findInvoice(int $documentId): ?array
    {
        $statement = $this->pdo->prepare(
            "SELECT d.id, d.document_number, d.title, d.issue_date, d.due_date, d.total_amount,
                    d.client_id, d.locale, c.display_name AS client_name,
                    (SELECT p.name FROM projects p WHERE p.id = d.project_id) AS project_name,
                    (SELECT COALESCE(SUM(pay.amount), 0) FROM document_payments pay WHERE pay.document_id = d.id) AS paid_amount,
                    EXISTS (SELECT 1 FROM relances_exclusions x WHERE x.client_id = d.client_id) AS is_excluded,
                    (SELECT x.reason FROM relances_exclusions x WHERE x.client_id = d.client_id) AS exclusion_reason
             FROM documents d
             INNER JOIN clients c ON c.id = d.client_id
             WHERE d.id = ? AND d.document_type = 'invoice' AND d.status = 'issued'
             LIMIT 1"
        );
        $statement->execute([$documentId]);
        $row = $statement->fetch();

        return is_array($row) ? $row : null;
    }

    /**
     * Historique des relances, éventuellement limité à une facture ou à un client.
     *
     * @return array<int,array<string,mixed>>
     */
    public function events(?int $documentId = null, ?int $clientId = null, int $limit = 200): array
    {
        $conditions = [];
        $parameters = [];
        if ($documentId !== null) {
            $conditions[] = 'ev.document_id = ?';
            $parameters[] = $documentId;
        }
        if ($clientId !== null) {
            $conditions[] = 'ev.client_id = ?';
            $parameters[] = $clientId;
        }
        $where = $conditions === [] ? '' : 'WHERE ' . implode(' AND ', $conditions);

        $statement = $this->pdo->prepare(
            "SELECT ev.id, ev.document_id, ev.client_id, ev.regle_id, ev.tone, ev.channel, ev.sent_on,
                    ev.promise_on, ev.promise_amount, ev.outcome, ev.note, ev.created_at,
                    d.document_number, d.total_amount,
                    c.display_name AS client_name,
                    u.name AS author_name
             FROM relances_evenements ev
             INNER JOIN documents d ON d.id = ev.document_id
             INNER JOIN clients c ON c.id = ev.client_id
             LEFT JOIN users u ON u.id = ev.created_by
             {$where}
             ORDER BY ev.sent_on DESC, ev.id DESC
             LIMIT " . max(1, min($limit, 1000))
        );
        $statement->execute($parameters);

        return $statement->fetchAll();
    }

    /** @param array<string,mixed> $values */
    public function addEvent(int $documentId, int $clientId, array $values, ?int $authorId): int
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO relances_evenements
                (document_id, client_id, regle_id, tone, channel, sent_on, promise_on, promise_amount, outcome, note, created_by)
             VALUES (:document_id, :client_id, :regle_id, :tone, :channel, :sent_on, :promise_on, :promise_amount, :outcome, :note, :created_by)'
        );
        $statement->execute([
            'document_id' => $documentId,
            'client_id' => $clientId,
            'regle_id' => $values['regle_id'] ?? null,
            'tone' => $values['tone'],
            'channel' => $values['channel'],
            'sent_on' => $values['sent_on'],
            'promise_on' => $values['promise_on'] ?? null,
            'promise_amount' => $values['promise_amount'] ?? null,
            'outcome' => $values['outcome'],
            'note' => $values['note'] ?? null,
            'created_by' => $authorId,
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    /** @return array<int,array<string,mixed>> Règles triées par ancienneté croissante. */
    public function rules(): array
    {
        $statement = $this->pdo->query(
            'SELECT id, name, delay_days, min_amount, tone, is_active, sort_order
             FROM relances_regles
             ORDER BY delay_days, sort_order, id'
        );

        return $statement->fetchAll();
    }

    /** @return array<string,mixed>|null */
    public function findRule(int $ruleId): ?array
    {
        $statement = $this->pdo->prepare(
            'SELECT id, name, delay_days, min_amount, tone, is_active, sort_order
             FROM relances_regles WHERE id = ? LIMIT 1'
        );
        $statement->execute([$ruleId]);
        $row = $statement->fetch();

        return is_array($row) ? $row : null;
    }

    /** @param array<string,mixed> $values */
    public function createRule(array $values, ?int $authorId): int
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO relances_regles (name, delay_days, min_amount, tone, is_active, sort_order, created_by)
             VALUES (?, ?, ?, ?, ?, ?, ?)'
        );
        $statement->execute([
            $values['name'],
            $values['delay_days'],
            $values['min_amount'],
            $values['tone'],
            $values['is_active'],
            $values['sort_order'],
            $authorId,
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    /** @param array<string,mixed> $values */
    public function updateRule(int $ruleId, array $values): bool
    {
        $statement = $this->pdo->prepare(
            'UPDATE relances_regles
             SET name = ?, delay_days = ?, min_amount = ?, tone = ?, is_active = ?, sort_order = ?
             WHERE id = ?'
        );
        $statement->execute([
            $values['name'],
            $values['delay_days'],
            $values['min_amount'],
            $values['tone'],
            $values['is_active'],
            $values['sort_order'],
            $ruleId,
        ]);

        return $statement->rowCount() > 0;
    }

    /** Supprime une règle ; les relances déjà consignées sont conservées (référence mise à null). */
    public function deleteRule(int $ruleId): bool
    {
        $statement = $this->pdo->prepare('DELETE FROM relances_regles WHERE id = ?');
        $statement->execute([$ruleId]);

        return $statement->rowCount() > 0;
    }

    /** @return array<string,array<string,mixed>> Modèles personnalisés indexés par « ton|langue ». */
    public function templates(): array
    {
        $statement = $this->pdo->query('SELECT tone, locale, subject, body FROM relances_modeles');
        $templates = [];
        foreach ($statement->fetchAll() as $row) {
            $templates[(string) $row['tone'] . '|' . (string) $row['locale']] = $row;
        }

        return $templates;
    }

    public function saveTemplate(string $tone, string $locale, string $subject, string $body, ?int $authorId): void
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO relances_modeles (tone, locale, subject, body, updated_by)
             VALUES (?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE subject = VALUES(subject), body = VALUES(body), updated_by = VALUES(updated_by)'
        );
        $statement->execute([$tone, $locale, $subject, $body, $authorId]);
    }

    public function deleteTemplate(string $tone, string $locale): void
    {
        $statement = $this->pdo->prepare('DELETE FROM relances_modeles WHERE tone = ? AND locale = ?');
        $statement->execute([$tone, $locale]);
    }

    /** @return array<int,array<string,mixed>> Clients exclus des relances. */
    public function exclusions(): array
    {
        $statement = $this->pdo->query(
            'SELECT x.id, x.client_id, x.reason, x.created_at, c.display_name AS client_name, u.name AS author_name
             FROM relances_exclusions x
             INNER JOIN clients c ON c.id = x.client_id
             LEFT JOIN users u ON u.id = x.created_by
             ORDER BY c.display_name'
        );

        return $statement->fetchAll();
    }

    public function excludeClient(int $clientId, string $reason, ?int $authorId): void
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO relances_exclusions (client_id, reason, created_by)
             VALUES (?, ?, ?)
             ON DUPLICATE KEY UPDATE reason = VALUES(reason), created_by = VALUES(created_by)'
        );
        $statement->execute([$clientId, $reason !== '' ? $reason : null, $authorId]);
    }

    public function includeClient(int $clientId): bool
    {
        $statement = $this->pdo->prepare('DELETE FROM relances_exclusions WHERE client_id = ?');
        $statement->execute([$clientId]);

        return $statement->rowCount() > 0;
    }
}
