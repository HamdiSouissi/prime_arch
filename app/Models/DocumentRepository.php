<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use PDO;
use RuntimeException;
use Throwable;

/** Accès PDO aux brouillons, documents émis, lignes, numéros et règlements. */
final class DocumentRepository
{
    private PDO $pdo;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? Database::connection();
    }

    /** @param array<string,string> $filters
     *  @return array{items:array<int,array<string,mixed>>,total:int}
     */
    public function paginate(array $filters, int $limit = 20, int $offset = 0): array
    {
        [$where, $parameters] = $this->conditions($filters);
        $count = $this->pdo->prepare(
            'SELECT COUNT(*) FROM documents d INNER JOIN clients c ON c.id = d.client_id
             LEFT JOIN projects p ON p.id = d.project_id' . $where
        );
        $count->execute($parameters);
        $total = (int) $count->fetchColumn();

        $statement = $this->pdo->prepare(
            'SELECT d.id, d.document_number, d.document_type, d.status, d.locale, d.title,
                    d.issue_date, d.due_date, d.total_amount, d.created_at,
                    c.display_name AS client_name, p.name AS project_name,
                    COALESCE((SELECT SUM(pay.amount) FROM document_payments pay
                              WHERE pay.document_id = d.id), 0) AS paid_amount,
                    COALESCE((SELECT SUM(credit.total_amount) FROM documents credit
                              WHERE credit.reference_document_id = d.id
                                AND credit.document_type = \'credit_note\' AND credit.status = \'issued\'), 0) AS credited_amount,
                    CAST(d.total_amount
                         - COALESCE((SELECT SUM(pay.amount) FROM document_payments pay
                                     WHERE pay.document_id = d.id), 0)
                         - COALESCE((SELECT SUM(credit.total_amount) FROM documents credit
                                     WHERE credit.reference_document_id = d.id
                                       AND credit.document_type = \'credit_note\' AND credit.status = \'issued\'), 0)
                         AS DECIMAL(18,3)) AS balance_due
             FROM documents d
             INNER JOIN clients c ON c.id = d.client_id
             LEFT JOIN projects p ON p.id = d.project_id' . $where
            . ' ORDER BY COALESCE(d.issue_date, DATE(d.created_at)) DESC, d.id DESC LIMIT ' . max(1, $limit)
            . ' OFFSET ' . max(0, $offset)
        );
        $statement->execute($parameters);
        return [
            'items' => array_map(self::normalize(...), $statement->fetchAll()),
            'total' => $total,
        ];
    }

    /** @return array<string,int|string> */
    public function summary(): array
    {
        $statement = $this->pdo->query(
            'SELECT COUNT(*) AS document_count,
                    SUM(CASE WHEN d.status = \'draft\' THEN 1 ELSE 0 END) AS draft_count,
                    SUM(CASE WHEN d.document_type = \'quote\' AND d.status = \'issued\' THEN 1 ELSE 0 END) AS issued_quote_count,
                    COALESCE(SUM(CASE WHEN d.document_type = \'invoice\' AND d.status = \'issued\' THEN
                        GREATEST(d.total_amount
                            - COALESCE((SELECT SUM(pay.amount) FROM document_payments pay WHERE pay.document_id = d.id), 0)
                            - COALESCE((SELECT SUM(credit.total_amount) FROM documents credit
                                        WHERE credit.reference_document_id = d.id
                                          AND credit.document_type = \'credit_note\' AND credit.status = \'issued\'), 0), 0)
                        ELSE 0 END), 0) AS outstanding_amount
             FROM documents d'
        );
        $row = $statement->fetch() ?: [];
        return [
            'document_count' => (int) ($row['document_count'] ?? 0),
            'draft_count' => (int) ($row['draft_count'] ?? 0),
            'issued_quote_count' => (int) ($row['issued_quote_count'] ?? 0),
            'outstanding_amount' => (string) ($row['outstanding_amount'] ?? '0.000'),
        ];
    }

    /** @return array<string,mixed>|null */
    public function findById(int $id): ?array
    {
        $statement = $this->pdo->prepare(
            'SELECT d.*, c.display_name AS client_name, c.legal_name AS client_legal_name,
                    c.client_type, c.tax_id AS client_tax_id, c.email AS client_email,
                    c.phone AS client_phone, c.address_line AS client_address_line,
                    c.city AS client_city, c.postal_code AS client_postal_code,
                    client_g.name_fr AS client_governorate_fr, client_g.name_ar AS client_governorate_ar,
                    client_d.name_latin AS client_delegation_latin, client_d.name_ar AS client_delegation_ar,
                    p.reference AS project_reference, p.name AS project_name,
                    p.project_type AS project_type, p.address_line AS project_address_line,
                    p.city AS project_city, p.postal_code AS project_postal_code,
                    project_g.name_fr AS project_governorate_fr, project_g.name_ar AS project_governorate_ar,
                    project_d.name_latin AS project_delegation_latin, project_d.name_ar AS project_delegation_ar,
                    phase.phase_name, related.document_number AS reference_document_number,
                    template.name AS template_name
             FROM documents d
             INNER JOIN clients c ON c.id = d.client_id
             LEFT JOIN governorates client_g ON client_g.id = c.governorate_id
             LEFT JOIN delegations client_d ON client_d.id = c.delegation_id
             LEFT JOIN projects p ON p.id = d.project_id
             LEFT JOIN governorates project_g ON project_g.id = p.governorate_id
             LEFT JOIN delegations project_d ON project_d.id = p.delegation_id
             LEFT JOIN project_phase_assignments phase ON phase.id = d.project_phase_assignment_id
             LEFT JOIN documents related ON related.id = d.reference_document_id
             LEFT JOIN document_templates template ON template.id = d.template_id
             WHERE d.id = :id LIMIT 1'
        );
        $statement->execute(['id' => $id]);
        $row = $statement->fetch();
        return is_array($row) ? self::normalize($row) : null;
    }

    /** @return array<int,array<string,mixed>> */
    public function forClient(int $clientId): array
    {
        $statement = $this->pdo->prepare(
            'SELECT d.id, d.document_number, d.document_type, d.status, d.title,
                    d.issue_date, d.due_date, d.total_amount, d.created_at,
                    p.name AS project_name
             FROM documents d
             LEFT JOIN projects p ON p.id = d.project_id
             WHERE d.client_id = :client_id
             ORDER BY COALESCE(d.issue_date, DATE(d.created_at)) DESC, d.id DESC'
        );
        $statement->execute(['client_id' => $clientId]);
        return array_map(static fn (array $row): array => [
            'id' => (int) $row['id'],
            'document_number' => (string) ($row['document_number'] ?? ''),
            'document_type' => (string) $row['document_type'],
            'status' => (string) $row['status'],
            'title' => (string) $row['title'],
            'issue_date' => (string) ($row['issue_date'] ?? ''),
            'due_date' => (string) ($row['due_date'] ?? ''),
            'total_amount' => (string) $row['total_amount'],
            'created_at' => (string) $row['created_at'],
            'project_name' => (string) ($row['project_name'] ?? ''),
        ], $statement->fetchAll());
    }

    /** @return array<int,array<string,mixed>> */
    public function lines(int $documentId): array
    {
        $statement = $this->pdo->prepare(
            'SELECT id, document_id, description, quantity, unit, unit_price, line_total, sort_order
             FROM document_lines WHERE document_id = :document_id ORDER BY sort_order, id'
        );
        $statement->execute(['document_id' => $documentId]);
        return array_map(static function (array $row): array {
            $row['id'] = (int) $row['id'];
            $row['document_id'] = (int) $row['document_id'];
            $row['sort_order'] = (int) $row['sort_order'];
            foreach (['quantity', 'unit_price', 'line_total'] as $field) {
                $row[$field] = (string) $row[$field];
            }
            return $row;
        }, $statement->fetchAll());
    }

    /** @return array<int,array<string,mixed>> */
    public function payments(int $documentId): array
    {
        $statement = $this->pdo->prepare(
            'SELECT pay.id, pay.paid_on, pay.amount, pay.payment_method, pay.reference, pay.note,
                    pay.created_at, creator.name AS created_by_name
             FROM document_payments pay
             LEFT JOIN users creator ON creator.id = pay.created_by
             WHERE pay.document_id = :document_id ORDER BY pay.paid_on, pay.id'
        );
        $statement->execute(['document_id' => $documentId]);
        return array_map(static function (array $row): array {
            $row['id'] = (int) $row['id'];
            $row['amount'] = (string) $row['amount'];
            return $row;
        }, $statement->fetchAll());
    }

    /** @return array<string,string> */
    public function financialPosition(int $documentId): array
    {
        $statement = $this->pdo->prepare(
            'SELECT CAST(d.total_amount
                         - COALESCE((SELECT SUM(pay.amount) FROM document_payments pay
                                     WHERE pay.document_id = d.id), 0)
                         - COALESCE((SELECT SUM(credit.total_amount) FROM documents credit
                                     WHERE credit.reference_document_id = d.id
                                       AND credit.document_type = \'credit_note\' AND credit.status = \'issued\'), 0)
                         AS DECIMAL(18,3)) AS balance_due,
                    CAST(COALESCE((SELECT SUM(pay.amount) FROM document_payments pay
                                   WHERE pay.document_id = d.id), 0) AS DECIMAL(18,3)) AS paid_amount,
                    CAST(COALESCE((SELECT SUM(credit.total_amount) FROM documents credit
                                   WHERE credit.reference_document_id = d.id
                                     AND credit.document_type = \'credit_note\' AND credit.status = \'issued\'), 0)
                         AS DECIMAL(18,3)) AS credited_amount
             FROM documents d WHERE d.id = :document_id LIMIT 1'
        );
        $statement->execute(['document_id' => $documentId]);
        $row = $statement->fetch() ?: [];
        return [
            'balance_due' => (string) ($row['balance_due'] ?? '0.000'),
            'paid_amount' => (string) ($row['paid_amount'] ?? '0.000'),
            'credited_amount' => (string) ($row['credited_amount'] ?? '0.000'),
        ];
    }

    /** @return array<int,array{id:int,display_name:string,client_id:int}> */
    public function clientOptions(): array
    {
        $rows = $this->pdo->query(
            "SELECT id, display_name FROM clients WHERE status <> 'archived' ORDER BY display_name, id"
        )->fetchAll();
        return array_map(static fn (array $row): array => [
            'id' => (int) $row['id'],
            'display_name' => (string) $row['display_name'],
            'client_id' => (int) $row['id'],
        ], $rows);
    }

    /** @return array<int,array{id:int,client_id:int,name:string,client_name:string,reference:string,status:string}> */
    public function projectOptions(): array
    {
        $rows = $this->pdo->query(
            "SELECT p.id, p.client_id, p.name, p.reference, p.status, c.display_name AS client_name
             FROM projects p INNER JOIN clients c ON c.id = p.client_id
             WHERE p.status <> 'cancelled' ORDER BY c.display_name, p.name, p.id"
        )->fetchAll();
        return array_map(static fn (array $row): array => [
            'id' => (int) $row['id'],
            'client_id' => (int) $row['client_id'],
            'name' => (string) $row['name'],
            'reference' => (string) ($row['reference'] ?? ''),
            'status' => (string) $row['status'],
            'client_name' => (string) $row['client_name'],
        ], $rows);
    }

    /** @return array<int,array{id:int,document_number:string,document_type:string,title:string,client_id:int,project_id:int|null,tax_rate:string,stamp_amount:string,withholding_rate:string,client_name:string,project_name:string}> */
    public function referenceOptions(string $documentType): array
    {
        if ($documentType === 'credit_note') {
            $types = "'invoice'";
        } elseif ($documentType === 'invoice') {
            $types = "'quote', 'contract'";
        } elseif ($documentType === 'all') {
            $types = "'quote', 'contract', 'invoice'";
        } else {
            return [];
        }
        $rows = $this->pdo->query(
            'SELECT d.id, d.document_number, d.document_type, d.title, d.client_id, d.project_id,
                    d.tax_rate, d.stamp_amount, d.withholding_rate,
                    c.display_name AS client_name, p.name AS project_name
             FROM documents d INNER JOIN clients c ON c.id = d.client_id
             LEFT JOIN projects p ON p.id = d.project_id
             WHERE d.status = \'issued\' AND d.document_type IN (' . $types . ')
             ORDER BY d.issue_date DESC, d.id DESC'
        )->fetchAll();
        return array_map(static fn (array $row): array => [
            'id' => (int) $row['id'],
            'document_number' => (string) $row['document_number'],
            'document_type' => (string) $row['document_type'],
            'title' => (string) $row['title'],
            'client_id' => (int) $row['client_id'],
            'project_id' => $row['project_id'] === null ? null : (int) $row['project_id'],
            'tax_rate' => (string) $row['tax_rate'],
            'stamp_amount' => (string) $row['stamp_amount'],
            'withholding_rate' => (string) $row['withholding_rate'],
            'client_name' => (string) $row['client_name'],
            'project_name' => (string) ($row['project_name'] ?? ''),
        ], $rows);
    }

    /** @return array<int,array{id:int,project_id:int,phase_name:string,project_name:string}> */
    public function phaseOptions(): array
    {
        $rows = $this->pdo->query(
            'SELECT a.id, a.project_id, a.phase_name, p.name AS project_name
             FROM project_phase_assignments a INNER JOIN projects p ON p.id = a.project_id
             WHERE p.status <> \'cancelled\' ORDER BY p.name, a.sort_order, a.id'
        )->fetchAll();
        return array_map(static fn (array $row): array => [
            'id' => (int) $row['id'],
            'project_id' => (int) $row['project_id'],
            'phase_name' => (string) $row['phase_name'],
            'project_name' => (string) $row['project_name'],
        ], $rows);
    }

    public function clientExists(int $clientId): bool
    {
        $statement = $this->pdo->prepare('SELECT 1 FROM clients WHERE id = :id AND status <> \'archived\' LIMIT 1');
        $statement->execute(['id' => $clientId]);
        return $statement->fetchColumn() !== false;
    }

    public function projectBelongsToClient(int $projectId, int $clientId): bool
    {
        $statement = $this->pdo->prepare(
            "SELECT 1 FROM projects WHERE id = :project_id AND client_id = :client_id AND status <> 'cancelled' LIMIT 1"
        );
        $statement->execute(['project_id' => $projectId, 'client_id' => $clientId]);
        return $statement->fetchColumn() !== false;
    }

    public function phaseBelongsToProject(int $phaseId, int $projectId): bool
    {
        $statement = $this->pdo->prepare(
            'SELECT 1 FROM project_phase_assignments WHERE id = :phase_id AND project_id = :project_id LIMIT 1'
        );
        $statement->execute(['phase_id' => $phaseId, 'project_id' => $projectId]);
        return $statement->fetchColumn() !== false;
    }

    /** @param array<string,mixed> $values
     *  @param array<int,array<string,string>> $lines
     */
    public function createDraft(array $values, array $lines, ?int $createdBy): int
    {
        $ownsTransaction = !$this->pdo->inTransaction();
        if ($ownsTransaction) {
            $this->pdo->beginTransaction();
        }
        try {
            $statement = $this->pdo->prepare(
                'INSERT INTO documents (
                    document_type, status, locale, title, client_id, project_id,
                    project_phase_assignment_id, template_id, reference_document_id, billing_kind,
                    tax_rate, stamp_amount, withholding_rate, validity_days, payment_terms_days,
                    terms_text, legal_notice, notes, created_by
                 ) VALUES (
                    :document_type, \'draft\', :locale, :title, :client_id, :project_id,
                    :phase_id, :template_id, :reference_document_id, :billing_kind,
                    CAST(:tax_rate AS DECIMAL(5,2)), CAST(:stamp_amount AS DECIMAL(12,3)),
                    CAST(:withholding_rate AS DECIMAL(6,3)), :validity_days, :payment_terms_days,
                    :terms_text, :legal_notice, :notes, :created_by
                 )'
            );
            $statement->execute(self::draftParameters($values) + ['created_by' => $createdBy]);
            $id = (int) $this->pdo->lastInsertId();
            $this->insertLines($id, $lines);
            $this->recalculate($id);
            if ($ownsTransaction) {
                $this->pdo->commit();
            }
            return $id;
        } catch (Throwable $error) {
            if ($ownsTransaction && $this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $error;
        }
    }

    /** @param array<string,mixed> $values
     *  @param array<int,array<string,string>> $lines
     */
    public function updateDraft(int $id, array $values, array $lines): bool
    {
        $ownsTransaction = !$this->pdo->inTransaction();
        if ($ownsTransaction) {
            $this->pdo->beginTransaction();
        }
        try {
            $lock = $this->pdo->prepare('SELECT status FROM documents WHERE id = :id FOR UPDATE');
            $lock->execute(['id' => $id]);
            if ($lock->fetchColumn() !== 'draft') {
                if ($ownsTransaction) {
                    $this->pdo->rollBack();
                }
                return false;
            }
            $statement = $this->pdo->prepare(
                'UPDATE documents SET document_type = :document_type, locale = :locale, title = :title,
                    client_id = :client_id, project_id = :project_id, project_phase_assignment_id = :phase_id,
                    template_id = :template_id, reference_document_id = :reference_document_id,
                    billing_kind = :billing_kind, tax_rate = CAST(:tax_rate AS DECIMAL(5,2)),
                    stamp_amount = CAST(:stamp_amount AS DECIMAL(12,3)),
                    withholding_rate = CAST(:withholding_rate AS DECIMAL(6,3)),
                    validity_days = :validity_days, payment_terms_days = :payment_terms_days,
                    terms_text = :terms_text, legal_notice = :legal_notice, notes = :notes
                 WHERE id = :id AND status = \'draft\' AND issued_snapshot IS NULL'
            );
            $statement->execute(self::draftParameters($values) + ['id' => $id]);
            if ($statement->rowCount() === 0 && $this->findById($id) === null) {
                if ($ownsTransaction) {
                    $this->pdo->rollBack();
                }
                return false;
            }
            $delete = $this->pdo->prepare('DELETE FROM document_lines WHERE document_id = :document_id');
            $delete->execute(['document_id' => $id]);
            $this->insertLines($id, $lines);
            $this->recalculate($id);
            if ($ownsTransaction) {
                $this->pdo->commit();
            }
            return true;
        } catch (Throwable $error) {
            if ($ownsTransaction && $this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $error;
        }
    }

    public function deleteDraft(int $id): bool
    {
        $statement = $this->pdo->prepare(
            'DELETE FROM documents WHERE id = :id AND status = \'draft\' AND issued_snapshot IS NULL'
        );
        $statement->execute(['id' => $id]);
        return $statement->rowCount() > 0;
    }

    /** @return array<string,mixed>|null */
    public function lockForIssue(int $id): ?array
    {
        $statement = $this->pdo->prepare(
            'SELECT id, document_type, status, client_id, reference_document_id
             FROM documents WHERE id = :id FOR UPDATE'
        );
        $statement->execute(['id' => $id]);
        $row = $statement->fetch();
        return is_array($row) ? [
            'id' => (int) $row['id'],
            'document_type' => (string) $row['document_type'],
            'status' => (string) $row['status'],
            'client_id' => (int) $row['client_id'],
            'reference_document_id' => $row['reference_document_id'] === null
                ? null
                : (int) $row['reference_document_id'],
        ] : null;
    }

    public function reserveNumber(string $type, int $year): string
    {
        $prefixes = [
            'quote' => 'DEV',
            'contract' => 'CTR',
            'invoice' => 'FAC',
            'credit_note' => 'AVR',
        ];
        if (!isset($prefixes[$type]) || $year < 2000 || $year > 9999) {
            throw new RuntimeException('Type ou année de numérotation invalide.');
        }
        $insert = $this->pdo->prepare(
            'INSERT INTO document_sequences (document_type, calendar_year, last_number)
             VALUES (:document_type, :calendar_year, 0)
             ON DUPLICATE KEY UPDATE last_number = document_sequences.last_number'
        );
        $insert->execute(['document_type' => $type, 'calendar_year' => $year]);
        $lock = $this->pdo->prepare(
            'SELECT last_number FROM document_sequences
             WHERE document_type = :document_type AND calendar_year = :calendar_year FOR UPDATE'
        );
        $lock->execute(['document_type' => $type, 'calendar_year' => $year]);
        $next = (int) $lock->fetchColumn() + 1;
        if ($next > 999999) {
            throw new RuntimeException('La séquence de documents a atteint sa limite annuelle.');
        }
        $update = $this->pdo->prepare(
            'UPDATE document_sequences SET last_number = :last_number
             WHERE document_type = :document_type AND calendar_year = :calendar_year'
        );
        $update->execute([
            'last_number' => $next,
            'document_type' => $type,
            'calendar_year' => $year,
        ]);
        return sprintf('%s-%04d-%06d', $prefixes[$type], $year, $next);
    }

    public function markIssued(
        int $id,
        string $number,
        string $issueDate,
        ?string $dueDate,
        ?string $validUntil,
        int $issuedBy,
        string $snapshotJson,
        string $pdfPath
    ): bool {
        $statement = $this->pdo->prepare(
            'UPDATE documents SET status = \'issued\', document_number = :document_number,
                issue_date = :issue_date, due_date = :due_date, valid_until = :valid_until,
                issued_at = CURRENT_TIMESTAMP, issued_by = :issued_by,
                issued_snapshot = :issued_snapshot, pdf_path = :pdf_path
             WHERE id = :id AND status = \'draft\' AND issued_snapshot IS NULL AND document_number IS NULL'
        );
        $statement->execute([
            'document_number' => $number,
            'issue_date' => $issueDate,
            'due_date' => $dueDate,
            'valid_until' => $validUntil,
            'issued_by' => $issuedBy,
            'issued_snapshot' => $snapshotJson,
            'pdf_path' => $pdfPath,
            'id' => $id,
        ]);
        return $statement->rowCount() === 1;
    }

    public function creditLimitRemaining(int $invoiceId): ?string
    {
        $statement = $this->pdo->prepare(
            'SELECT CAST(d.total_amount - COALESCE((
                        SELECT SUM(credit.total_amount) FROM documents credit
                        WHERE credit.reference_document_id = d.id
                          AND credit.document_type = \'credit_note\' AND credit.status = \'issued\'
                    ), 0) AS DECIMAL(18,3)) AS credit_limit
             FROM documents d
             WHERE d.id = :invoice_id AND d.document_type = \'invoice\' AND d.status = \'issued\'
             LIMIT 1'
        );
        $statement->execute(['invoice_id' => $invoiceId]);
        $value = $statement->fetchColumn();
        return $value === false ? null : (string) $value;
    }

    /** @param array<string,mixed> $values */
    public function addPayment(int $documentId, array $values, ?int $createdBy): int
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO document_payments (
                document_id, paid_on, amount, payment_method, reference, note, created_by
             ) VALUES (
                :document_id, :paid_on, CAST(:amount AS DECIMAL(18,3)),
                :payment_method, :reference, :note, :created_by
             )'
        );
        $statement->execute([
            'document_id' => $documentId,
            'paid_on' => $values['paid_on'],
            'amount' => $values['amount'],
            'payment_method' => $values['payment_method'],
            'reference' => $values['reference'] !== '' ? $values['reference'] : null,
            'note' => $values['note'] !== '' ? $values['note'] : null,
            'created_by' => $createdBy,
        ]);
        return (int) $this->pdo->lastInsertId();
    }

    /** @param array<string,mixed> $filters
     *  @return array{string,array<string,mixed>}
     */
    private function conditions(array $filters): array
    {
        $conditions = [];
        $parameters = [];
        foreach (['document_type' => 'document_type', 'status' => 'status'] as $filter => $column) {
            if (($filters[$filter] ?? '') !== '') {
                $conditions[] = 'd.' . $column . ' = :' . $filter;
                $parameters[$filter] = $filters[$filter];
            }
        }
        foreach (['client_id' => 'client_id', 'project_id' => 'project_id'] as $filter => $column) {
            if (($filters[$filter] ?? '') !== '') {
                $conditions[] = 'd.' . $column . ' = :' . $filter;
                $parameters[$filter] = (int) $filters[$filter];
            }
        }
        $search = trim((string) ($filters['search'] ?? ''));
        if ($search !== '') {
            $pattern = '%' . str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $search) . '%';
            $conditions[] = "(d.document_number LIKE :search_number ESCAPE '!'
                OR d.title LIKE :search_title ESCAPE '!'
                OR c.display_name LIKE :search_client ESCAPE '!'
                OR p.name LIKE :search_project ESCAPE '!')";
            $parameters += [
                'search_number' => $pattern,
                'search_title' => $pattern,
                'search_client' => $pattern,
                'search_project' => $pattern,
            ];
        }
        return [$conditions === [] ? '' : ' WHERE ' . implode(' AND ', $conditions), $parameters];
    }

    /** @param array<string,mixed> $values
     *  @return array<string,mixed>
     */
    private static function draftParameters(array $values): array
    {
        return [
            'document_type' => $values['document_type'],
            'locale' => $values['locale'],
            'title' => $values['title'],
            'client_id' => $values['client_id'],
            'project_id' => $values['project_id'] > 0 ? $values['project_id'] : null,
            'phase_id' => $values['project_phase_assignment_id'] > 0 ? $values['project_phase_assignment_id'] : null,
            'template_id' => $values['template_id'] > 0 ? $values['template_id'] : null,
            'reference_document_id' => $values['reference_document_id'] > 0 ? $values['reference_document_id'] : null,
            'billing_kind' => $values['billing_kind'] !== '' ? $values['billing_kind'] : null,
            'tax_rate' => $values['tax_rate'],
            'stamp_amount' => $values['stamp_amount'],
            'withholding_rate' => $values['withholding_rate'],
            'validity_days' => $values['validity_days'],
            'payment_terms_days' => $values['payment_terms_days'],
            'terms_text' => $values['terms_text'] !== '' ? $values['terms_text'] : null,
            'legal_notice' => $values['legal_notice'] !== '' ? $values['legal_notice'] : null,
            'notes' => $values['notes'] !== '' ? $values['notes'] : null,
        ];
    }

    /** @param array<int,array<string,string>> $lines */
    private function insertLines(int $documentId, array $lines): void
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO document_lines (
                document_id, description, quantity, unit, unit_price, line_total, sort_order
             ) VALUES (
                :document_id, :description, CAST(:quantity AS DECIMAL(9,3)), :unit,
                CAST(:unit_price AS DECIMAL(12,3)),
                CAST(ROUND(CAST(:quantity_for_total AS DECIMAL(9,3))
                    * CAST(:price_for_total AS DECIMAL(12,3)), 3) AS DECIMAL(18,3)), :sort_order
             )'
        );
        foreach (array_values($lines) as $index => $line) {
            $statement->execute([
                'document_id' => $documentId,
                'description' => $line['description'],
                'quantity' => $line['quantity'],
                'unit' => $line['unit'],
                'unit_price' => $line['unit_price'],
                'quantity_for_total' => $line['quantity'],
                'price_for_total' => $line['unit_price'],
                'sort_order' => ($index + 1) * 10,
            ]);
        }
    }

    public function recalculateForIssue(int $documentId): void
    {
        $this->recalculate($documentId);
    }

    private function recalculate(int $documentId): void
    {
        $statement = $this->pdo->prepare(
            'UPDATE documents d
             LEFT JOIN (
                 SELECT document_id, SUM(line_total) AS line_subtotal
                 FROM document_lines WHERE document_id = :line_document_id GROUP BY document_id
             ) line_totals ON line_totals.document_id = d.id
             SET d.subtotal = COALESCE(line_totals.line_subtotal, 0),
                 d.tax_amount = CAST(ROUND(COALESCE(line_totals.line_subtotal, 0) * d.tax_rate / 100, 3) AS DECIMAL(18,3)),
                 d.withholding_amount = CAST(ROUND(COALESCE(line_totals.line_subtotal, 0) * d.withholding_rate / 100, 3) AS DECIMAL(18,3)),
                 d.total_amount = CAST(COALESCE(line_totals.line_subtotal, 0)
                     + ROUND(COALESCE(line_totals.line_subtotal, 0) * d.tax_rate / 100, 3)
                     + d.stamp_amount
                     - ROUND(COALESCE(line_totals.line_subtotal, 0) * d.withholding_rate / 100, 3)
                     AS DECIMAL(18,3))
             WHERE d.id = :document_id AND d.status = \'draft\''
        );
        $statement->execute(['line_document_id' => $documentId, 'document_id' => $documentId]);
    }

    /** @param array<string,mixed> $row
     *  @return array<string,mixed>
     */
    private static function normalize(array $row): array
    {
        foreach (['id', 'client_id', 'project_id', 'project_phase_assignment_id', 'template_id', 'reference_document_id', 'created_by', 'issued_by'] as $key) {
            if (array_key_exists($key, $row)) {
                $row[$key] = $row[$key] === null ? null : (int) $row[$key];
            }
        }
        foreach ([
            'tax_rate', 'stamp_amount', 'withholding_rate', 'subtotal', 'tax_amount',
            'withholding_amount', 'total_amount', 'paid_amount', 'credited_amount', 'balance_due',
        ] as $key) {
            if (array_key_exists($key, $row) && $row[$key] !== null) {
                $row[$key] = (string) $row[$key];
            }
        }
        return $row;
    }
}
