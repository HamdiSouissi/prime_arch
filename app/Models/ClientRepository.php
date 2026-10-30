<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use PDO;
use Throwable;

/**
 * Accès préparé aux clients, adresses, contacts et projets associés.
 */
final class ClientRepository
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
        [$where, $parameters] = $this->listConditions($filters);
        $count = $this->pdo->prepare('SELECT COUNT(*) FROM clients c' . $where);
        $count->execute($parameters);
        $total = (int) $count->fetchColumn();

        $statement = $this->pdo->prepare(
            'SELECT c.id, c.reference, c.client_type, c.display_name, c.legal_name, c.email, c.phone,
                    c.city, c.postal_code, c.status, c.updated_at,
                    g.name_fr AS governorate_name,
                    (SELECT COUNT(*) FROM projects p WHERE p.client_id = c.id) AS project_count
             FROM clients c
             LEFT JOIN governorates g ON g.id = c.governorate_id'
            . $where . ' ORDER BY c.updated_at DESC, c.id DESC LIMIT ' . max(1, $limit)
            . ' OFFSET ' . max(0, $offset)
        );
        $statement->execute($parameters);
        $items = array_map(self::normalize(...), $statement->fetchAll());
        return ['items' => $items, 'total' => $total];
    }

    /** @param array<string,string> $filters
     *  @return array<int,array<string,mixed>>
     */
    public function exportRows(array $filters): array
    {
        [$where, $parameters] = $this->listConditions($filters);
        $statement = $this->pdo->prepare(
            'SELECT c.reference, c.client_type, c.display_name, c.legal_name, c.tax_id,
                    c.email, c.phone, c.address_line, c.city, g.iso_code AS governorate_iso_code,
                    d.code AS delegation_code, c.postal_code, c.status, c.notes, c.custom_fields,
                    c.created_at, c.updated_at
             FROM clients c
             LEFT JOIN governorates g ON g.id = c.governorate_id
             LEFT JOIN delegations d ON d.id = c.delegation_id'
            . $where . ' ORDER BY c.updated_at DESC, c.id DESC'
        );
        $statement->execute($parameters);
        return $statement->fetchAll();
    }

    public function findIdByReference(string $reference): ?int
    {
        $statement = $this->pdo->prepare(
            'SELECT id FROM clients WHERE reference = :reference LIMIT 1'
        );
        $statement->execute(['reference' => trim($reference)]);
        $id = $statement->fetchColumn();
        return $id === false ? null : (int) $id;
    }

    /** @return array<int,array<string,mixed>> */
    public function forSelect(): array
    {
        $rows = $this->pdo->query(
            "SELECT id, reference, display_name FROM clients WHERE status <> 'archived' ORDER BY display_name, id"
        )->fetchAll();
        return array_map(static fn (array $row): array => [
            'id' => (int) $row['id'],
            'reference' => (string) ($row['reference'] ?? ''),
            'display_name' => (string) $row['display_name'],
        ], $rows);
    }

    public function findById(int $id): ?array
    {
        $statement = $this->pdo->prepare(
            'SELECT c.*, g.name_fr AS governorate_name, g.name_ar AS governorate_name_ar,
                    d.name_latin AS delegation_name, d.name_ar AS delegation_name_ar,
                    creator.name AS created_by_name
             FROM clients c
             LEFT JOIN governorates g ON g.id = c.governorate_id
             LEFT JOIN delegations d ON d.id = c.delegation_id
             LEFT JOIN users creator ON creator.id = c.created_by
             WHERE c.id = :id LIMIT 1'
        );
        $statement->execute(['id' => $id]);
        $row = $statement->fetch();
        return is_array($row) ? self::normalize($row) : null;
    }

    /** @return array<int,array<string,mixed>> */
    public function contacts(int $clientId): array
    {
        $statement = $this->pdo->prepare(
            'SELECT id, client_id, full_name, job_title, email, phone, is_primary, created_at, updated_at
             FROM client_contacts WHERE client_id = :client_id
             ORDER BY is_primary DESC, full_name, id'
        );
        $statement->execute(['client_id' => $clientId]);
        return array_map(static fn (array $row): array => [
            'id' => (int) $row['id'],
            'client_id' => (int) $row['client_id'],
            'full_name' => (string) $row['full_name'],
            'job_title' => (string) ($row['job_title'] ?? ''),
            'email' => (string) ($row['email'] ?? ''),
            'phone' => (string) ($row['phone'] ?? ''),
            'is_primary' => (bool) $row['is_primary'],
            'created_at' => (string) $row['created_at'],
            'updated_at' => (string) $row['updated_at'],
        ], $statement->fetchAll());
    }

    /** @return array<int,array<string,mixed>> */
    public function projects(int $clientId): array
    {
        $statement = $this->pdo->prepare(
            "SELECT p.id, p.reference, p.name, p.status, p.city, p.updated_at,
                    (SELECT phase_name FROM project_phase_assignments a
                     WHERE a.project_id = p.id AND a.status = 'in_progress'
                     ORDER BY a.sort_order, a.id LIMIT 1) AS current_phase
             FROM projects p WHERE p.client_id = :client_id
             ORDER BY p.updated_at DESC, p.id DESC"
        );
        $statement->execute(['client_id' => $clientId]);
        return array_map(static fn (array $row): array => [
            'id' => (int) $row['id'],
            'reference' => (string) ($row['reference'] ?? ''),
            'name' => (string) $row['name'],
            'status' => (string) $row['status'],
            'city' => (string) ($row['city'] ?? ''),
            'current_phase' => (string) ($row['current_phase'] ?? ''),
            'updated_at' => (string) $row['updated_at'],
        ], $statement->fetchAll());
    }

    /** @param array<string,mixed> $values */
    public function create(array $values, ?int $createdBy): int
    {
        $ownsTransaction = !$this->pdo->inTransaction();
        if ($ownsTransaction) {
            $this->pdo->beginTransaction();
        }
        try {
            $statement = $this->pdo->prepare(
                'INSERT INTO clients (
                    reference, client_type, display_name, legal_name, tax_id, email, phone,
                    address_line, city, governorate_id, delegation_id, postal_code, status, notes, custom_fields, created_by
                 ) VALUES (
                    NULL, :client_type, :display_name, :legal_name, :tax_id, :email, :phone,
                    :address_line, :city, :governorate_id, :delegation_id, :postal_code, :status, :notes, :custom_fields, :created_by
                 )'
            );
            $statement->execute($this->parameters($values, $createdBy));
            $id = (int) $this->pdo->lastInsertId();
            $reference = sprintf('CL-%s-%06d', date('Y'), $id);
            $update = $this->pdo->prepare('UPDATE clients SET reference = :reference WHERE id = :id');
            $update->execute(['reference' => $reference, 'id' => $id]);
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

    /** @param array<string,mixed> $values */
    public function update(int $id, array $values): bool
    {
        $statement = $this->pdo->prepare(
            'UPDATE clients SET client_type = :client_type, display_name = :display_name, legal_name = :legal_name,
                tax_id = :tax_id, email = :email, phone = :phone, address_line = :address_line,
                city = :city, governorate_id = :governorate_id, delegation_id = :delegation_id,
                postal_code = :postal_code, status = :status, notes = :notes, custom_fields = :custom_fields
             WHERE id = :id'
        );
        $parameters = $this->parameters($values, null);
        unset($parameters['created_by']);
        $parameters['id'] = $id;
        $statement->execute($parameters);
        return $statement->rowCount() > 0 || $this->findById($id) !== null;
    }

    /** @param array{full_name:string,job_title:?string,email:?string,phone:?string,is_primary:bool} $contact */
    public function createContact(int $clientId, array $contact): int
    {
        $ownsTransaction = !$this->pdo->inTransaction();
        if ($ownsTransaction) {
            $this->pdo->beginTransaction();
        }
        try {
            if ($contact['is_primary']) {
                $clear = $this->pdo->prepare('UPDATE client_contacts SET is_primary = 0 WHERE client_id = :client_id');
                $clear->execute(['client_id' => $clientId]);
            }
            $statement = $this->pdo->prepare(
                'INSERT INTO client_contacts (client_id, full_name, job_title, email, phone, is_primary)
                 VALUES (:client_id, :full_name, :job_title, :email, :phone, :is_primary)'
            );
            $statement->execute([
                'client_id' => $clientId,
                'full_name' => $contact['full_name'],
                'job_title' => $contact['job_title'],
                'email' => $contact['email'],
                'phone' => $contact['phone'],
                'is_primary' => $contact['is_primary'] ? 1 : 0,
            ]);
            $id = (int) $this->pdo->lastInsertId();
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

    /** @param array{full_name:string,job_title:?string,email:?string,phone:?string,is_primary:bool} $contact */
    public function updateContact(int $clientId, int $contactId, array $contact): bool
    {
        $ownsTransaction = !$this->pdo->inTransaction();
        if ($ownsTransaction) {
            $this->pdo->beginTransaction();
        }
        try {
            $exists = $this->pdo->prepare(
                'SELECT 1 FROM client_contacts WHERE id = :id AND client_id = :client_id FOR UPDATE'
            );
            $exists->execute(['id' => $contactId, 'client_id' => $clientId]);
            if ($exists->fetchColumn() === false) {
                if ($ownsTransaction) {
                    $this->pdo->rollBack();
                }
                return false;
            }
            if ($contact['is_primary']) {
                $clear = $this->pdo->prepare(
                    'UPDATE client_contacts SET is_primary = 0 WHERE client_id = :client_id AND id <> :id'
                );
                $clear->execute(['client_id' => $clientId, 'id' => $contactId]);
            }
            $statement = $this->pdo->prepare(
                'UPDATE client_contacts SET full_name = :full_name, job_title = :job_title,
                    email = :email, phone = :phone, is_primary = :is_primary
                 WHERE id = :id AND client_id = :client_id'
            );
            $statement->execute([
                'id' => $contactId,
                'client_id' => $clientId,
                'full_name' => $contact['full_name'],
                'job_title' => $contact['job_title'],
                'email' => $contact['email'],
                'phone' => $contact['phone'],
                'is_primary' => $contact['is_primary'] ? 1 : 0,
            ]);
            $updated = $statement->rowCount() > 0 || $this->contactExists($clientId, $contactId);
            if ($ownsTransaction) {
                $this->pdo->commit();
            }
            return $updated;
        } catch (Throwable $error) {
            if ($ownsTransaction && $this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $error;
        }
    }

    public function deleteContact(int $clientId, int $contactId): bool
    {
        $statement = $this->pdo->prepare('DELETE FROM client_contacts WHERE id = :id AND client_id = :client_id');
        $statement->execute(['id' => $contactId, 'client_id' => $clientId]);
        return $statement->rowCount() > 0;
    }

    private function contactExists(int $clientId, int $contactId): bool
    {
        $statement = $this->pdo->prepare(
            'SELECT 1 FROM client_contacts WHERE id = :id AND client_id = :client_id LIMIT 1'
        );
        $statement->execute(['id' => $contactId, 'client_id' => $clientId]);
        return $statement->fetchColumn() !== false;
    }

    /** @return array{string,array<string,mixed>} */
    private function listConditions(array $filters): array
    {
        $conditions = [];
        $parameters = [];
        $search = trim((string) ($filters['search'] ?? ''));
        if ($search !== '') {
            $pattern = '%' . str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $search) . '%';
            $conditions[] = "(c.reference LIKE :search_reference ESCAPE '!'
                OR c.display_name LIKE :search_name ESCAPE '!'
                OR c.legal_name LIKE :search_legal ESCAPE '!'
                OR c.email LIKE :search_email ESCAPE '!'
                OR c.phone LIKE :search_phone ESCAPE '!'
                OR c.tax_id LIKE :search_tax_id ESCAPE '!'
                OR EXISTS (
                    SELECT 1 FROM client_contacts ct
                    WHERE ct.client_id = c.id
                      AND (ct.full_name LIKE :search_contact_name ESCAPE '!'
                           OR ct.email LIKE :search_contact_email ESCAPE '!'
                           OR ct.phone LIKE :search_contact_phone ESCAPE '!')
                ))";
            $parameters += [
                'search_reference' => $pattern,
                'search_name' => $pattern,
                'search_legal' => $pattern,
                'search_email' => $pattern,
                'search_phone' => $pattern,
                'search_tax_id' => $pattern,
                'search_contact_name' => $pattern,
                'search_contact_email' => $pattern,
                'search_contact_phone' => $pattern,
            ];
        }
        if (($filters['status'] ?? '') !== '') {
            $conditions[] = 'c.status = :status';
            $parameters['status'] = $filters['status'];
        }
        if (($filters['type'] ?? '') !== '') {
            $conditions[] = 'c.client_type = :client_type';
            $parameters['client_type'] = $filters['type'];
        }
        return [$conditions === [] ? '' : ' WHERE ' . implode(' AND ', $conditions), $parameters];
    }

    /** @param array<string,mixed> $values
     *  @return array<string,mixed>
     */
    private function parameters(array $values, ?int $createdBy): array
    {
        return [
            'client_type' => $values['client_type'],
            'display_name' => $values['display_name'],
            'legal_name' => $values['legal_name'] !== '' ? $values['legal_name'] : null,
            'tax_id' => $values['tax_id'] !== '' ? $values['tax_id'] : null,
            'email' => $values['email'] !== '' ? $values['email'] : null,
            'phone' => $values['phone'] !== '' ? $values['phone'] : null,
            'address_line' => $values['address_line'] !== '' ? $values['address_line'] : null,
            'city' => $values['city'] !== '' ? $values['city'] : null,
            'governorate_id' => $values['governorate_id'] !== '' ? (int) $values['governorate_id'] : null,
            'delegation_id' => $values['delegation_id'] !== '' ? (int) $values['delegation_id'] : null,
            'postal_code' => $values['postal_code'] !== '' ? $values['postal_code'] : null,
            'status' => $values['status'],
            'notes' => $values['notes'] !== '' ? $values['notes'] : null,
            'custom_fields' => json_encode((object) ($values['custom_fields'] ?? []), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
            'created_by' => $createdBy,
        ];
    }

    private static function normalize(array $row): array
    {
        foreach (['id', 'governorate_id', 'delegation_id', 'project_count', 'created_by'] as $integerKey) {
            if (isset($row[$integerKey])) {
                $row[$integerKey] = (int) $row[$integerKey];
            }
        }
        $customFields = $row['custom_fields'] ?? [];
        if (is_string($customFields)) {
            $decoded = json_decode($customFields, true);
            $customFields = is_array($decoded) ? $decoded : [];
        }
        $row['custom_fields'] = is_array($customFields) ? $customFields : [];
        return $row;
    }
}
