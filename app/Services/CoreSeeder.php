<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use PDO;
use RuntimeException;

/**
 * Précharge les rôles et le référentiel géographique de la Tunisie.
 */
final class CoreSeeder
{
    private PDO $pdo;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? Database::connection();
    }

    /** @return array<string,int> */
    public function run(): array
    {
        $roles = $this->seedRoles();
        $governorates = require BASE_PATH . '/database/seeds/governorates.php';
        $delegations = require BASE_PATH . '/database/seeds/delegations.php';
        $postalCodes = require BASE_PATH . '/database/seeds/postal_codes.php';

        $this->pdo->beginTransaction();
        try {
            $governorateIds = $this->seedGovernorates($governorates);
            $delegationIds = $this->seedDelegations($delegations, $governorateIds);
            $postalCodeCount = $this->seedPostalCodes($postalCodes, $delegationIds);
            $this->pdo->commit();
        } catch (\Throwable $error) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $error;
        }

        return [
            'roles' => $roles,
            'governorates' => count($governorateIds),
            'delegations' => count($delegationIds),
            'postal_codes' => $postalCodeCount,
        ];
    }

    private function seedRoles(): int
    {
        $roles = require BASE_PATH . '/database/seeds/roles.php';
        $find = $this->pdo->prepare('SELECT id FROM roles WHERE name = :name LIMIT 1');
        $insert = $this->pdo->prepare('INSERT INTO roles (name, label, permissions) VALUES (:name, :label, :permissions)');
        $count = 0;
        foreach ($roles as $role) {
            $name = (string) $role['slug'];
            $find->execute(['name' => $name]);
            if ($find->fetchColumn() !== false) {
                continue;
            }
            $insert->execute([
                'name' => $name,
                'label' => (string) $role['label'],
                'permissions' => json_encode($role['permissions'], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
            ]);
            $count++;
        }
        return $count;
    }

    /** @param array<int,array{iso:string,name_fr:string,name_ar:string}> $items
     *  @return array<string,int>
     */
    private function seedGovernorates(array $items): array
    {
        $find = $this->pdo->prepare('SELECT id FROM governorates WHERE iso_code = :iso LIMIT 1');
        $insert = $this->pdo->prepare(
            'INSERT INTO governorates (iso_code, name_fr, name_ar) VALUES (:iso, :name_fr, :name_ar)'
        );
        $update = $this->pdo->prepare(
            'UPDATE governorates SET name_fr = :name_fr, name_ar = :name_ar WHERE iso_code = :iso'
        );
        $ids = [];

        foreach ($items as $item) {
            $iso = (string) ($item['iso'] ?? '');
            if (!preg_match('/^TN-\d{2}$/', $iso)) {
                throw new RuntimeException('Code ISO de gouvernorat invalide dans le seed.');
            }
            $find->execute(['iso' => $iso]);
            $id = $find->fetchColumn();
            if ($id === false) {
                $insert->execute([
                    'iso' => $iso,
                    'name_fr' => (string) $item['name_fr'],
                    'name_ar' => (string) $item['name_ar'],
                ]);
                $id = $this->pdo->lastInsertId();
            } else {
                $update->execute([
                    'iso' => $iso,
                    'name_fr' => (string) $item['name_fr'],
                    'name_ar' => (string) $item['name_ar'],
                ]);
            }
            $ids[$iso] = (int) $id;
        }
        return $ids;
    }

    /** @param array<int,array{governorate_iso:string,code:string,name_latin:string,name_ar:string}> $items
     *  @param array<string,int> $governorateIds
     *  @return array<string,int>
     */
    private function seedDelegations(array $items, array $governorateIds): array
    {
        $find = $this->pdo->prepare('SELECT id FROM delegations WHERE code = :code LIMIT 1');
        $insert = $this->pdo->prepare(
            'INSERT INTO delegations (governorate_id, code, name_latin, name_ar)
             VALUES (:governorate_id, :code, :name_latin, :name_ar)'
        );
        $update = $this->pdo->prepare(
            'UPDATE delegations SET governorate_id = :governorate_id, name_latin = :name_latin, name_ar = :name_ar
             WHERE code = :code'
        );
        $ids = [];

        foreach ($items as $item) {
            $code = (string) ($item['code'] ?? '');
            $iso = (string) ($item['governorate_iso'] ?? '');
            if (!isset($governorateIds[$iso]) || !preg_match('/^TN\d{4}$/', $code)) {
                throw new RuntimeException('Référence ou code de délégation invalide dans le seed.');
            }
            $parameters = [
                'governorate_id' => $governorateIds[$iso],
                'code' => $code,
                'name_latin' => (string) $item['name_latin'],
                'name_ar' => (string) $item['name_ar'],
            ];
            $find->execute(['code' => $code]);
            $id = $find->fetchColumn();
            if ($id === false) {
                $insert->execute($parameters);
                $id = $this->pdo->lastInsertId();
            } else {
                $update->execute($parameters);
            }
            $ids[$code] = (int) $id;
        }
        return $ids;
    }

    /** @param array<int,array{delegation_code:string,code:string,locality_fr:string,locality_ar:string}> $items
     *  @param array<string,int> $delegationIds
     */
    private function seedPostalCodes(array $items, array $delegationIds): int
    {
        $find = $this->pdo->prepare(
            'SELECT id FROM postal_codes
             WHERE delegation_id = :delegation_id AND code = :code AND locality_fr = :locality_fr LIMIT 1'
        );
        $insert = $this->pdo->prepare(
            'INSERT INTO postal_codes (delegation_id, code, locality_fr, locality_ar)
             VALUES (:delegation_id, :code, :locality_fr, :locality_ar)'
        );
        $update = $this->pdo->prepare(
            'UPDATE postal_codes SET locality_ar = :locality_ar
             WHERE delegation_id = :delegation_id AND code = :code AND locality_fr = :locality_fr'
        );
        $count = 0;

        foreach ($items as $item) {
            $delegationCode = (string) ($item['delegation_code'] ?? '');
            $code = (string) ($item['code'] ?? '');
            if (!isset($delegationIds[$delegationCode]) || !preg_match('/^\d{4}$/', $code)) {
                throw new RuntimeException('Code postal ou délégation invalide dans le seed.');
            }
            $parameters = [
                'delegation_id' => $delegationIds[$delegationCode],
                'code' => $code,
                'locality_fr' => (string) ($item['locality_fr'] ?? ''),
                'locality_ar' => (string) ($item['locality_ar'] ?? ''),
            ];
            $find->execute([
                'delegation_id' => $parameters['delegation_id'],
                'code' => $parameters['code'],
                'locality_fr' => $parameters['locality_fr'],
            ]);
            if ($find->fetchColumn() === false) {
                $insert->execute($parameters);
                $count++;
            } else {
                $update->execute($parameters);
            }
        }
        return $count;
    }
}
