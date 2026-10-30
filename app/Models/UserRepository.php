<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use InvalidArgumentException;
use PDO;

/**
 * Accès préparé aux comptes et aux rôles du cabinet.
 */
final class UserRepository
{
    private PDO $pdo;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? Database::connection();
    }

    /** Compte et rôle, utilisés uniquement pendant l'authentification. */
    public function findForAuthentication(string $email): ?array
    {
        $statement = $this->pdo->prepare(
            'SELECT u.id, u.name, u.email, u.password, u.locale, u.is_active,
                    r.name AS role_slug, r.label AS role_label, r.permissions
             FROM users u
             INNER JOIN roles r ON r.id = u.role_id
             WHERE u.email = :email
             LIMIT 1'
        );
        $statement->execute(['email' => $email]);
        $user = $statement->fetch();
        if (!is_array($user)) {
            return null;
        }

        $user['password_hash'] = (string) $user['password'];
        unset($user['password']);
        return $this->normalize($user);
    }

    /** Profil actif sans empreinte du mot de passe. */
    public function findActiveById(int $id): ?array
    {
        $statement = $this->pdo->prepare(
            'SELECT u.id, u.name, u.email, u.locale, u.is_active,
                    r.name AS role_slug, r.label AS role_label, r.permissions
             FROM users u
             INNER JOIN roles r ON r.id = u.role_id
             WHERE u.id = :id AND u.is_active = 1
             LIMIT 1'
        );
        $statement->execute(['id' => $id]);
        $user = $statement->fetch();
        return is_array($user) ? $this->normalize($user) : null;
    }

    /** Profil d'un compte actif ou inactif, réservé à l'administration. */
    public function findById(int $id): ?array
    {
        $statement = $this->pdo->prepare(
            'SELECT u.id, u.name, u.email, u.locale, u.is_active,
                    r.name AS role_slug, r.label AS role_label, r.permissions
             FROM users u
             INNER JOIN roles r ON r.id = u.role_id
             WHERE u.id = :id
             LIMIT 1'
        );
        $statement->execute(['id' => $id]);
        $user = $statement->fetch();
        return is_array($user) ? $this->normalize($user) : null;
    }

    /** @return array<int,array{id:int,slug:string,label:string,permissions:array<int,string>}> */
    public function roles(): array
    {
        $rows = $this->pdo->query('SELECT id, name, label, permissions FROM roles ORDER BY id')->fetchAll();
        return array_map(function (array $row): array {
            $row = $this->normalize($row);
            return [
                'id' => (int) $row['id'],
                'slug' => (string) $row['role_slug'],
                'label' => (string) $row['role_label'],
                'permissions' => $row['permissions'],
            ];
        }, $rows);
    }

    public function findRole(string $slug): ?array
    {
        $statement = $this->pdo->prepare('SELECT id, name, label, permissions FROM roles WHERE name = :name LIMIT 1');
        $statement->execute(['name' => $slug]);
        $role = $statement->fetch();
        if (!is_array($role)) {
            return null;
        }
        $role = $this->normalize($role);
        return [
            'id' => (int) $role['id'],
            'slug' => (string) $role['role_slug'],
            'label' => (string) $role['role_label'],
            'permissions' => $role['permissions'],
        ];
    }

    /** @return array<int,array<string,mixed>> */
    public function all(): array
    {
        $rows = $this->pdo->query(
            'SELECT u.id, u.name, u.email, u.locale, u.is_active, u.last_login_at, u.created_at,
                    r.name AS role_slug, r.label AS role_label, r.permissions
             FROM users u
             INNER JOIN roles r ON r.id = u.role_id
             ORDER BY u.name, u.id'
        )->fetchAll();
        return array_map(fn (array $row): array => $this->normalize($row), $rows);
    }

    public function create(string $name, string $email, string $passwordHash, string $roleSlug, string $locale = 'fr'): int
    {
        $role = $this->findRole($roleSlug);
        if ($role === null) {
            throw new InvalidArgumentException('Le rôle demandé n’existe pas.');
        }

        $statement = $this->pdo->prepare(
            'INSERT INTO users (role_id, name, email, password, locale, is_active)
             VALUES (:role_id, :name, :email, :password, :locale, 1)'
        );
        $statement->execute([
            'role_id' => $role['id'],
            'name' => $name,
            'email' => mb_strtolower($email, 'UTF-8'),
            'password' => $passwordHash,
            'locale' => in_array($locale, ['fr', 'ar'], true) ? $locale : 'fr',
        ]);
        return (int) $this->pdo->lastInsertId();
    }

    public function updateAccess(int $id, string $roleSlug, bool $active): string
    {
        $role = $this->findRole($roleSlug);
        if ($role === null) {
            throw new InvalidArgumentException('Le rôle demandé n’existe pas.');
        }

        $ownsTransaction = !$this->pdo->inTransaction();
        if ($ownsTransaction) {
            $this->pdo->beginTransaction();
        }
        try {
            $activeAdmins = $this->pdo->query(
                "SELECT u.id FROM users u INNER JOIN roles r ON r.id = u.role_id
                 WHERE u.is_active = 1 AND r.name = 'administrator' FOR UPDATE"
            )->fetchAll(PDO::FETCH_COLUMN);
            $find = $this->pdo->prepare(
                'SELECT u.id, u.is_active, r.name AS role_slug
                 FROM users u INNER JOIN roles r ON r.id = u.role_id
                 WHERE u.id = :id FOR UPDATE'
            );
            $find->execute(['id' => $id]);
            $current = $find->fetch();
            if (!is_array($current)) {
                if ($ownsTransaction) {
                    $this->pdo->rollBack();
                }
                return 'missing';
            }

            $wasActiveAdministrator = (bool) $current['is_active'] && $current['role_slug'] === 'administrator';
            $willBeActiveAdministrator = $active && $roleSlug === 'administrator';
            if ($wasActiveAdministrator && !$willBeActiveAdministrator && count($activeAdmins) <= 1) {
                if ($ownsTransaction) {
                    $this->pdo->rollBack();
                }
                return 'last_admin';
            }

            $statement = $this->pdo->prepare(
                'UPDATE users SET role_id = :role_id, is_active = :is_active WHERE id = :id'
            );
            $statement->execute([
                'role_id' => $role['id'],
                'is_active' => $active ? 1 : 0,
                'id' => $id,
            ]);
            if ($ownsTransaction) {
                $this->pdo->commit();
            }
            return 'updated';
        } catch (\Throwable $error) {
            if ($ownsTransaction && $this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $error;
        }
    }

    public function countActiveAdministrators(): int
    {
        return (int) $this->pdo->query(
            "SELECT COUNT(*) FROM users u INNER JOIN roles r ON r.id = u.role_id
             WHERE u.is_active = 1 AND r.name = 'administrator'"
        )->fetchColumn();
    }

    public function updateLastLogin(int $id): void
    {
        $statement = $this->pdo->prepare('UPDATE users SET last_login_at = CURRENT_TIMESTAMP WHERE id = :id');
        $statement->execute(['id' => $id]);
    }

    public function updatePasswordHash(int $id, string $passwordHash): void
    {
        $statement = $this->pdo->prepare('UPDATE users SET password = :password WHERE id = :id');
        $statement->execute(['password' => $passwordHash, 'id' => $id]);
    }

    private function normalize(array $row): array
    {
        $permissions = $row['permissions'] ?? [];
        if (is_string($permissions)) {
            $decoded = json_decode($permissions, true);
            $permissions = is_array($decoded) ? $decoded : [];
        }
        $row['permissions'] = array_values(array_filter($permissions, 'is_string'));
        if (array_key_exists('is_active', $row)) {
            $row['is_active'] = (bool) $row['is_active'];
        }
        if (array_key_exists('id', $row)) {
            $row['id'] = (int) $row['id'];
        }
        if (!array_key_exists('role_slug', $row) && array_key_exists('name', $row) && array_key_exists('label', $row)) {
            $row['role_slug'] = $row['name'];
            $row['role_label'] = $row['label'];
            unset($row['name'], $row['label']);
        }
        if (array_key_exists('role_slug', $row)) {
            $row['role'] = $row['role_slug'];
            $translationKey = 'role.' . $row['role_slug'];
            $translatedLabel = __($translationKey);
            if ($translatedLabel !== $translationKey) {
                $row['role_label'] = $translatedLabel;
            }
        }
        return $row;
    }
}
