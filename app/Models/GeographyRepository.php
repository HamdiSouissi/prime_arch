<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use PDO;

/**
 * Référentiel des gouvernorats, délégations et codes postaux.
 */
final class GeographyRepository
{
    private PDO $pdo;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? Database::connection();
    }

    /** @return array<int,array{id:int,iso_code:string,name_fr:string,name_ar:string}> */
    public function governorates(): array
    {
        $rows = $this->pdo->query(
            'SELECT id, iso_code, name_fr, name_ar FROM governorates ORDER BY name_fr'
        )->fetchAll();
        return array_map(static fn (array $row): array => [
            'id' => (int) $row['id'],
            'iso_code' => (string) $row['iso_code'],
            'name_fr' => (string) $row['name_fr'],
            'name_ar' => (string) $row['name_ar'],
        ], $rows);
    }

    public function governorateIdByIsoCode(string $isoCode): ?int
    {
        $statement = $this->pdo->prepare(
            'SELECT id FROM governorates WHERE iso_code = :iso_code LIMIT 1'
        );
        $statement->execute(['iso_code' => strtoupper(trim($isoCode))]);
        $id = $statement->fetchColumn();
        return $id === false ? null : (int) $id;
    }

    public function delegationIdByCode(string $code, int $governorateId): ?int
    {
        $statement = $this->pdo->prepare(
            'SELECT id FROM delegations
             WHERE code = :code AND governorate_id = :governorate_id LIMIT 1'
        );
        $statement->execute(['code' => trim($code), 'governorate_id' => $governorateId]);
        $id = $statement->fetchColumn();
        return $id === false ? null : (int) $id;
    }

    /** @return array<int,array{id:int,governorate_id:int,code:string,name_latin:string,name_ar:string}> */
    public function delegations(int $governorateId): array
    {
        $statement = $this->pdo->prepare(
            'SELECT id, governorate_id, code, name_latin, name_ar
             FROM delegations WHERE governorate_id = :governorate_id ORDER BY name_latin'
        );
        $statement->execute(['governorate_id' => $governorateId]);
        return array_map(static fn (array $row): array => [
            'id' => (int) $row['id'],
            'governorate_id' => (int) $row['governorate_id'],
            'code' => (string) $row['code'],
            'name_latin' => (string) $row['name_latin'],
            'name_ar' => (string) $row['name_ar'],
        ], $statement->fetchAll());
    }

    /** @return array<int,array{id:int,code:string,locality_latin:string,locality_ar:string,source_delegation:string}> */
    public function postalCodes(int $delegationId): array
    {
        $statement = $this->pdo->prepare(
            'SELECT id, code, locality_latin, locality_ar, source_delegation
             FROM postal_codes WHERE delegation_id = :delegation_id ORDER BY code, locality_latin'
        );
        $statement->execute(['delegation_id' => $delegationId]);
        return array_map(self::normalizePostalCode(...), $statement->fetchAll());
    }

    /** @return array<int,array{id:int,code:string,locality_latin:string,locality_ar:string,source_delegation:string}> */
    public function postalCodesForGovernorate(int $governorateId): array
    {
        $statement = $this->pdo->prepare(
            'SELECT id, code, locality_latin, locality_ar, source_delegation
             FROM postal_codes WHERE governorate_id = :governorate_id ORDER BY source_delegation, code, locality_latin'
        );
        $statement->execute(['governorate_id' => $governorateId]);
        return array_map(self::normalizePostalCode(...), $statement->fetchAll());
    }

    /** @return array{id:int,code:string,locality_latin:string,locality_ar:string,source_delegation:string} */
    private static function normalizePostalCode(array $row): array
    {
        return [
            'id' => (int) $row['id'],
            'code' => (string) $row['code'],
            'locality_latin' => (string) $row['locality_latin'],
            'locality_ar' => (string) $row['locality_ar'],
            'source_delegation' => (string) $row['source_delegation'],
        ];
    }

    public function delegationBelongsTo(int $delegationId, int $governorateId): bool
    {
        $statement = $this->pdo->prepare(
            'SELECT 1 FROM delegations WHERE id = :id AND governorate_id = :governorate_id LIMIT 1'
        );
        $statement->execute(['id' => $delegationId, 'governorate_id' => $governorateId]);
        return $statement->fetchColumn() !== false;
    }

    public function counts(): array
    {
        return [
            'governorates' => (int) $this->pdo->query('SELECT COUNT(*) FROM governorates')->fetchColumn(),
            'delegations' => (int) $this->pdo->query('SELECT COUNT(*) FROM delegations')->fetchColumn(),
            'postal_codes' => (int) $this->pdo->query('SELECT COUNT(*) FROM postal_codes')->fetchColumn(),
        ];
    }

    public function governorateExists(int $id): bool
    {
        $statement = $this->pdo->prepare('SELECT 1 FROM governorates WHERE id = :id LIMIT 1');
        $statement->execute(['id' => $id]);
        return $statement->fetchColumn() !== false;
    }
}
