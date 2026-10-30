<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use PDO;

/**
 * Persistance clé/valeur des réglages du cabinet.
 */
final class SettingRepository
{
    private PDO $pdo;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? Database::connection();
    }

    public function get(string $key, ?string $default = null): ?string
    {
        $statement = $this->pdo->prepare('SELECT `value` FROM settings WHERE `key` = :key LIMIT 1');
        $statement->execute(['key' => $key]);
        $value = $statement->fetchColumn();
        return $value === false || $value === null ? $default : (string) $value;
    }

    /** @return array<string,string> */
    public function all(string $group): array
    {
        $statement = $this->pdo->prepare('SELECT `key`, `value` FROM settings WHERE `group` = :group ORDER BY `key`');
        $statement->execute(['group' => $group]);
        $settings = [];
        foreach ($statement->fetchAll() as $row) {
            $settings[(string) $row['key']] = (string) ($row['value'] ?? '');
        }
        return $settings;
    }

    /** @param array<string,string> $settings */
    public function setMany(array $settings, string $group): void
    {
        $this->setGroups([$group => $settings]);
    }

    /** @param array<string,array<string,string>> $groups */
    public function setGroups(array $groups): void
    {
        $ownsTransaction = !$this->pdo->inTransaction();
        if ($ownsTransaction) {
            $this->pdo->beginTransaction();
        }

        try {
            $statement = $this->pdo->prepare(
                'INSERT INTO settings (`key`, `value`, `group`)
                 VALUES (:key, :value, :group)
                 ON DUPLICATE KEY UPDATE `value` = :updated_value, `group` = :updated_group'
            );
            foreach ($groups as $group => $settings) {
                foreach ($settings as $key => $value) {
                    $statement->execute([
                        'key' => $key,
                        'value' => $value,
                        'group' => $group,
                        'updated_value' => $value,
                        'updated_group' => $group,
                    ]);
                }
            }
            if ($ownsTransaction) {
                $this->pdo->commit();
            }
        } catch (\Throwable $error) {
            if ($ownsTransaction && $this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $error;
        }
    }
}
