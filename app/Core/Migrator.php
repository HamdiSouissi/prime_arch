<?php
declare(strict_types=1);

namespace App\Core;

use PDO;

/**
 * Exécuteur de migrations SQL (database/migrations et dossier migrations de chaque module).
 * Les migrations appliquées sont tracées dans la table `migrations`.
 */
final class Migrator
{
    public function __construct(private ?PDO $pdo = null)
    {
        $this->pdo ??= Database::connection();
    }

    /**
     * Applique les migrations en attente et retourne la liste des fichiers exécutés.
     *
     * @param string[]|null $dirs dossiers à parcourir (par défaut : migrations du cœur)
     * @return string[]
     */
    public function run(?array $dirs = null): array
    {
        $this->ensureTable();
        $dirs ??= [BASE_PATH . '/database/migrations'];
        $done = $this->pdo->query('SELECT migration FROM migrations')->fetchAll(PDO::FETCH_COLUMN);
        $batch = (int) $this->pdo->query('SELECT COALESCE(MAX(batch), 0) + 1 FROM migrations')->fetchColumn();
        $executed = [];

        foreach ($dirs as $dir) {
            $files = glob(rtrim($dir, '/') . '/*.sql') ?: [];
            sort($files);
            foreach ($files as $file) {
                $name = str_replace(BASE_PATH . '/', '', $file);
                if (in_array($name, $done, true)) {
                    continue;
                }
                // NB : DDL MySQL = commit implicite ; pas de transaction ici.
                foreach ($this->splitStatements((string) file_get_contents($file)) as $sql) {
                    $this->pdo->exec($sql);
                }
                $stmt = $this->pdo->prepare('INSERT INTO migrations (migration, batch) VALUES (?, ?)');
                $stmt->execute([$name, $batch]);
                $executed[] = $name;
            }
        }
        return $executed;
    }

    private function ensureTable(): void
    {
        $this->pdo->exec(
            'CREATE TABLE IF NOT EXISTS migrations (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                migration VARCHAR(255) NOT NULL UNIQUE,
                batch INT UNSIGNED NOT NULL,
                executed_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );
    }

    /**
     * Découpe un script SQL simple en instructions (séparateur ";" en fin de ligne).
     * Limite connue : pas de support des procédures stockées / DELIMITER.
     *
     * @return string[]
     */
    private function splitStatements(string $sql): array
    {
        $sql = preg_replace('/^\s*--.*$/m', '', $sql) ?? $sql;
        $parts = preg_split('/;\s*(?:\r?\n|$)/', $sql) ?: [];
        return array_values(array_filter(array_map('trim', $parts), fn ($s) => $s !== ''));
    }

    // TODO : rollback(), status(), exécution des seeds (database/seeds/*.php).
}
