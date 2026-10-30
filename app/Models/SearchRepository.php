<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use PDO;

/**
 * Recherche globale du cœur : clients, projets et documents.
 *
 * Toutes les requêtes sont préparées, la casse et les accents sont ignorés par la collation
 * `utf8mb4_unicode_ci`, et les caractères spéciaux de `LIKE` (`%`, `_`, `\`) sont échappés :
 * une saisie comme « 50 % » ne devient donc pas un joker. Les modules complètent la recherche
 * par le filtre `search.results` : le noyau n'a aucune connaissance de leurs tables.
 */
final class SearchRepository
{
    /** Nombre maximal de fiches renvoyées par catégorie. */
    public const LIMIT = 8;

    private PDO $pdo;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? Database::connection();
    }

    /**
     * Clients correspondant au terme : nom, raison sociale, référence, courriel, téléphone, ville.
     *
     * @return array<int,array<string,mixed>>
     */
    public function clients(string $query, int $limit = self::LIMIT): array
    {
        $statement = $this->pdo->prepare(
            'SELECT c.id, c.reference, c.display_name, c.city, c.email, c.phone
             FROM clients c
             WHERE c.display_name LIKE :name ESCAPE \'\\\\\'
                OR c.legal_name LIKE :legal ESCAPE \'\\\\\'
                OR c.reference LIKE :reference ESCAPE \'\\\\\'
                OR c.email LIKE :email ESCAPE \'\\\\\'
                OR c.phone LIKE :phone ESCAPE \'\\\\\'
                OR c.city LIKE :city ESCAPE \'\\\\\'
             ORDER BY CASE WHEN c.display_name LIKE :prefix ESCAPE \'\\\\\' THEN 0 ELSE 1 END,
                      c.updated_at DESC, c.id DESC
             LIMIT ' . $this->limit($limit)
        );
        $statement->execute($this->bindings($query, ['name', 'legal', 'reference', 'email', 'phone', 'city', 'prefix']));

        return $statement->fetchAll();
    }

    /**
     * Projets correspondant au terme : nom, référence, type, ville ou nom du client.
     *
     * @return array<int,array<string,mixed>>
     */
    public function projects(string $query, int $limit = self::LIMIT): array
    {
        $statement = $this->pdo->prepare(
            'SELECT p.id, p.reference, p.name, p.city, p.project_type, p.status,
                    c.display_name AS client_name
             FROM projects p
             INNER JOIN clients c ON c.id = p.client_id
             WHERE p.name LIKE :name ESCAPE \'\\\\\'
                OR p.reference LIKE :reference ESCAPE \'\\\\\'
                OR p.project_type LIKE :type ESCAPE \'\\\\\'
                OR p.city LIKE :city ESCAPE \'\\\\\'
                OR c.display_name LIKE :client ESCAPE \'\\\\\'
             ORDER BY CASE WHEN p.name LIKE :prefix ESCAPE \'\\\\\' THEN 0 ELSE 1 END,
                      p.updated_at DESC, p.id DESC
             LIMIT ' . $this->limit($limit)
        );
        $statement->execute($this->bindings($query, ['name', 'reference', 'type', 'city', 'client', 'prefix']));

        return $statement->fetchAll();
    }

    /**
     * Documents correspondant au terme : numéro, intitulé ou nom du client.
     *
     * @return array<int,array<string,mixed>>
     */
    public function documents(string $query, int $limit = self::LIMIT): array
    {
        $statement = $this->pdo->prepare(
            'SELECT d.id, d.document_number, d.document_type, d.status, d.title,
                    c.display_name AS client_name
             FROM documents d
             INNER JOIN clients c ON c.id = d.client_id
             WHERE d.document_number LIKE :number ESCAPE \'\\\\\'
                OR d.title LIKE :title ESCAPE \'\\\\\'
                OR c.display_name LIKE :client ESCAPE \'\\\\\'
             ORDER BY CASE WHEN d.document_number LIKE :prefix ESCAPE \'\\\\\' THEN 0 ELSE 1 END,
                      COALESCE(d.issue_date, DATE(d.created_at)) DESC, d.id DESC
             LIMIT ' . $this->limit($limit)
        );
        $statement->execute($this->bindings($query, ['number', 'title', 'client', 'prefix']));

        return $statement->fetchAll();
    }

    /**
     * Terme encadré de jokers, caractères spéciaux neutralisés.
     *
     * Public et statique : les modules réutilisent exactement la même règle d'échappement.
     */
    public static function likeTerm(string $query, bool $prefixOnly = false): string
    {
        $escaped = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], trim($query));

        return $prefixOnly ? $escaped . '%' : '%' . $escaped . '%';
    }

    /**
     * @param array<int,string> $names
     * @return array<string,string>
     */
    private function bindings(string $query, array $names): array
    {
        $bindings = [];
        foreach ($names as $name) {
            $bindings[$name] = self::likeTerm($query, $name === 'prefix');
        }

        return $bindings;
    }

    private function limit(int $limit): int
    {
        return max(1, min($limit, self::LIMIT));
    }
}
