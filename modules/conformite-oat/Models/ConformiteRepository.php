<?php
declare(strict_types=1);

namespace Modules\ConformiteOat\Models;

use App\Core\Database;
use PDO;

/**
 * Accès PDO au dossier professionnel.
 *
 * Toutes les écritures sont limitées aux tables `conformite_*` : le module lit `users`,
 * `projects` et `clients` du cœur sans jamais les modifier.
 */
final class ConformiteRepository
{
    private PDO $pdo;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? Database::connection();
    }

    /** @return array<int,array<string,mixed>> Pièces administratives, échéance la plus proche d’abord. */
    public function inscriptions(): array
    {
        return $this->pdo->query(
            'SELECT id, kind, label, reference, issued_on, expires_on, note, created_at
             FROM conformite_inscriptions
             ORDER BY (expires_on IS NULL) ASC, expires_on ASC, id ASC'
        )->fetchAll();
    }

    /** @param array<string,mixed> $values */
    public function createInscription(array $values, ?int $authorId): int
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO conformite_inscriptions (kind, label, reference, issued_on, expires_on, note, created_by)
             VALUES (?, ?, ?, ?, ?, ?, ?)'
        );
        $statement->execute([
            $values['kind'],
            $values['label'],
            $values['reference'] !== '' ? $values['reference'] : null,
            $values['issued_on'],
            $values['expires_on'],
            $values['note'] !== '' ? $values['note'] : null,
            $authorId,
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    /** @return array<string,mixed>|null */
    public function findInscription(int $inscriptionId): ?array
    {
        $statement = $this->pdo->prepare(
            'SELECT id, kind, label, reference, issued_on, expires_on FROM conformite_inscriptions WHERE id = ? LIMIT 1'
        );
        $statement->execute([$inscriptionId]);
        $row = $statement->fetch();

        return is_array($row) ? $row : null;
    }

    public function deleteInscription(int $inscriptionId): bool
    {
        $statement = $this->pdo->prepare('DELETE FROM conformite_inscriptions WHERE id = ?');
        $statement->execute([$inscriptionId]);

        return $statement->rowCount() > 0;
    }

    /** @return array<int,array<string,mixed>> Polices d’assurance, la plus récente d’abord. */
    public function assurances(): array
    {
        return $this->pdo->query(
            'SELECT id, insurer, policy_number, coverage_amount, starts_on, ends_on, note, created_at
             FROM conformite_assurances
             ORDER BY ends_on DESC, id DESC'
        )->fetchAll();
    }

    /**
     * Police à retenir pour une attestation : celle qui couvre la date du jour, sinon la plus
     * récente. Aucune police n’est inventée si la table est vide.
     *
     * @return array<string,mixed>|null
     */
    public function currentAssurance(string $today): ?array
    {
        $statement = $this->pdo->prepare(
            'SELECT id, insurer, policy_number, coverage_amount, starts_on, ends_on, note
             FROM conformite_assurances
             ORDER BY (ends_on >= ?) DESC, ends_on DESC, id DESC
             LIMIT 1'
        );
        $statement->execute([$today]);
        $row = $statement->fetch();

        return is_array($row) ? $row : null;
    }

    /** @param array<string,mixed> $values */
    public function createAssurance(array $values, ?int $authorId): int
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO conformite_assurances
                (insurer, policy_number, coverage_amount, starts_on, ends_on, note, created_by)
             VALUES (?, ?, ?, ?, ?, ?, ?)'
        );
        $statement->execute([
            $values['insurer'],
            $values['policy_number'] !== '' ? $values['policy_number'] : null,
            $values['coverage_amount'],
            $values['starts_on'],
            $values['ends_on'],
            $values['note'] !== '' ? $values['note'] : null,
            $authorId,
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    /** @return array<string,mixed>|null */
    public function findAssurance(int $assuranceId): ?array
    {
        $statement = $this->pdo->prepare(
            'SELECT id, insurer, policy_number, ends_on FROM conformite_assurances WHERE id = ? LIMIT 1'
        );
        $statement->execute([$assuranceId]);
        $row = $statement->fetch();

        return is_array($row) ? $row : null;
    }

    public function deleteAssurance(int $assuranceId): bool
    {
        $statement = $this->pdo->prepare('DELETE FROM conformite_assurances WHERE id = ?');
        $statement->execute([$assuranceId]);

        return $statement->rowCount() > 0;
    }

    /** @return array<int,array<string,mixed>> Formations, la plus récente d’abord. */
    public function formations(): array
    {
        return $this->pdo->query(
            'SELECT id, title, provider, hours, completed_on, expires_on, certificate_reference, note, created_at
             FROM conformite_formations
             ORDER BY completed_on DESC, id DESC'
        )->fetchAll();
    }

    /** @param array<string,mixed> $values */
    public function createFormation(array $values, ?int $authorId): int
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO conformite_formations
                (title, provider, hours, completed_on, expires_on, certificate_reference, note, created_by)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $statement->execute([
            $values['title'],
            $values['provider'] !== '' ? $values['provider'] : null,
            $values['hours'],
            $values['completed_on'],
            $values['expires_on'],
            $values['certificate_reference'] !== '' ? $values['certificate_reference'] : null,
            $values['note'] !== '' ? $values['note'] : null,
            $authorId,
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    /** @return array<string,mixed>|null */
    public function findFormation(int $formationId): ?array
    {
        $statement = $this->pdo->prepare(
            'SELECT id, title, completed_on FROM conformite_formations WHERE id = ? LIMIT 1'
        );
        $statement->execute([$formationId]);
        $row = $statement->fetch();

        return is_array($row) ? $row : null;
    }

    public function deleteFormation(int $formationId): bool
    {
        $statement = $this->pdo->prepare('DELETE FROM conformite_formations WHERE id = ?');
        $statement->execute([$formationId]);

        return $statement->rowCount() > 0;
    }

    /** @return array<int,array<string,mixed>> Bureaux d’études partenaires, actifs d’abord. */
    public function partenaires(): array
    {
        return $this->pdo->query(
            'SELECT id, name, discipline, contact_name, phone, email, agreement_reference, is_active, note, created_at
             FROM conformite_partenaires
             ORDER BY is_active DESC, name ASC, id ASC'
        )->fetchAll();
    }

    /** @param array<string,mixed> $values */
    public function createPartenaire(array $values, ?int $authorId): int
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO conformite_partenaires
                (name, discipline, contact_name, phone, email, agreement_reference, is_active, note, created_by)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $statement->execute([
            $values['name'],
            $values['discipline'] !== '' ? $values['discipline'] : null,
            $values['contact_name'] !== '' ? $values['contact_name'] : null,
            $values['phone'] !== '' ? $values['phone'] : null,
            $values['email'] !== '' ? $values['email'] : null,
            $values['agreement_reference'] !== '' ? $values['agreement_reference'] : null,
            $values['is_active'],
            $values['note'] !== '' ? $values['note'] : null,
            $authorId,
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    /** @return array<string,mixed>|null */
    public function findPartenaire(int $partenaireId): ?array
    {
        $statement = $this->pdo->prepare(
            'SELECT id, name, is_active FROM conformite_partenaires WHERE id = ? LIMIT 1'
        );
        $statement->execute([$partenaireId]);
        $row = $statement->fetch();

        return is_array($row) ? $row : null;
    }

    public function togglePartenaire(int $partenaireId, bool $active): bool
    {
        $statement = $this->pdo->prepare('UPDATE conformite_partenaires SET is_active = ? WHERE id = ?');
        $statement->execute([$active ? 1 : 0, $partenaireId]);

        return $statement->rowCount() > 0;
    }

    public function deletePartenaire(int $partenaireId): bool
    {
        $statement = $this->pdo->prepare('DELETE FROM conformite_partenaires WHERE id = ?');
        $statement->execute([$partenaireId]);

        return $statement->rowCount() > 0;
    }

    /** @return array<int,array<string,mixed>> Projets non annulés, pour les attestations. */
    public function projects(): array
    {
        return $this->pdo->query(
            "SELECT p.id, p.name, p.reference, p.status AS project_status, c.display_name AS client_name
             FROM projects p
             INNER JOIN clients c ON c.id = p.client_id
             WHERE p.status <> 'cancelled'
             ORDER BY p.updated_at DESC, p.id DESC
             LIMIT 300"
        )->fetchAll();
    }

    /** @return array<string,mixed>|null Projet et client, ou null s’il n’existe plus. */
    public function findProject(int $projectId): ?array
    {
        $statement = $this->pdo->prepare(
            "SELECT p.id, p.name, p.reference, p.status AS project_status, p.city, p.address_line,
                    c.display_name AS client_name, c.phone AS client_phone, c.email AS client_email
             FROM projects p
             INNER JOIN clients c ON c.id = p.client_id
             WHERE p.id = ? AND p.status <> 'cancelled'
             LIMIT 1"
        );
        $statement->execute([$projectId]);
        $row = $statement->fetch();

        return is_array($row) ? $row : null;
    }
}
