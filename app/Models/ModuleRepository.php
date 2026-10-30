<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use PDO;

/** Accès PDO au registre des modules installés. */
final class ModuleRepository
{
    public function __construct(private ?PDO $pdo = null)
    {
        $this->pdo ??= Database::connection();
    }

    /** @return array<int,array<string,mixed>> */
    public function all(): array
    {
        $rows = $this->pdo->query(
            'SELECT id, slug, name, version, is_enabled, archive_sha256, manifest_json,
                    installed_by, install_hook_completed, installed_at, updated_at
             FROM modules ORDER BY name, slug'
        )->fetchAll();
        return array_map([self::class, 'hydrate'], $rows);
    }

    /** @return array<string,mixed>|null */
    public function find(string $slug): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, slug, name, version, is_enabled, archive_sha256, manifest_json,
                    installed_by, install_hook_completed, installed_at, updated_at
             FROM modules WHERE slug = ? LIMIT 1'
        );
        $stmt->execute([$slug]);
        $row = $stmt->fetch();
        return is_array($row) ? self::hydrate($row) : null;
    }

    /** @return string[] */
    public function enabled(): array
    {
        return $this->pdo->query('SELECT slug FROM modules WHERE is_enabled = 1 ORDER BY slug')
            ->fetchAll(PDO::FETCH_COLUMN);
    }

    /** Enregistre un module installé, inactif jusqu'à sa première activation. */
    public function create(array $manifest, string $archiveHash, ?int $installedBy): int
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO modules
                (slug, name, version, is_enabled, archive_sha256, manifest_json, installed_by, install_hook_completed)
             VALUES (:slug, :name, :version, 0, :archive_sha256, :manifest_json, :installed_by, 0)'
        );
        $statement->execute([
            'slug' => $manifest['slug'],
            'name' => $manifest['name'],
            'version' => $manifest['version'],
            'archive_sha256' => $archiveHash,
            'manifest_json' => json_encode($manifest, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'installed_by' => $installedBy,
        ]);
        return (int) $this->pdo->lastInsertId();
    }

    public function setEnabled(string $slug, bool $enabled): void
    {
        $statement = $this->pdo->prepare('UPDATE modules SET is_enabled = ?, updated_at = CURRENT_TIMESTAMP WHERE slug = ?');
        $statement->execute([$enabled ? 1 : 0, $slug]);
    }

    public function markInstallHookCompleted(string $slug): void
    {
        $statement = $this->pdo->prepare(
            'UPDATE modules SET install_hook_completed = 1, updated_at = CURRENT_TIMESTAMP WHERE slug = ?'
        );
        $statement->execute([$slug]);
    }

    private static function hydrate(array $row): array
    {
        $manifest = null;
        if (is_string($row['manifest_json'] ?? null) && $row['manifest_json'] !== '') {
            $decoded = json_decode($row['manifest_json'], true);
            $manifest = is_array($decoded) ? $decoded : null;
        }
        unset($row['manifest_json']);
        $row['is_enabled'] = (bool) $row['is_enabled'];
        $row['install_hook_completed'] = (bool) $row['install_hook_completed'];
        $row['manifest'] = $manifest;
        return $row;
    }
}
