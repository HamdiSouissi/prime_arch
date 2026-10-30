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
        $delegationGovernorateIsos = array_column($delegations, 'governorate_iso', 'code');
        $postalCodes = require BASE_PATH . '/database/seeds/postal_codes.php';

        $this->pdo->beginTransaction();
        try {
            $governorateIds = $this->seedGovernorates($governorates);
            $delegationIds = $this->seedDelegations($delegations, $governorateIds);
            $postalCodeCount = $this->seedPostalCodes(
                $postalCodes,
                $governorateIds,
                $delegationIds,
                $delegationGovernorateIsos
            );
            $projectPhaseCount = $this->seedProjectPhases((array) config('tunisia.architecture.phases', []));
            $projectPhaseWeightCount = $this->initializeProjectPhaseWeights();
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
            'project_phases' => $projectPhaseCount,
            'project_phase_weights' => $projectPhaseWeightCount,
        ];
    }

    private function seedRoles(): int
    {
        $roles = require BASE_PATH . '/database/seeds/roles.php';
        $find = $this->pdo->prepare('SELECT id FROM roles WHERE name = :name LIMIT 1');
        $insert = $this->pdo->prepare('INSERT INTO roles (name, label, permissions) VALUES (:name, :label, :permissions)');
        $update = $this->pdo->prepare('UPDATE roles SET label = :label, permissions = :permissions WHERE id = :id');
        $count = 0;
        foreach ($roles as $role) {
            $name = (string) $role['slug'];
            $find->execute(['name' => $name]);
            $id = $find->fetchColumn();
            $parameters = [
                'name' => $name,
                'label' => (string) $role['label'],
                'permissions' => json_encode($role['permissions'], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
            ];
            if ($id === false) {
                $insert->execute($parameters);
                $count++;
                continue;
            }
            // Les rôles intégrés suivent les permissions de la définition versionnée du noyau.
            $update->execute([
                'id' => (int) $id,
                'label' => $parameters['label'],
                'permissions' => $parameters['permissions'],
            ]);
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

    /** @param array<string,string> $items */
    private function seedProjectPhases(array $items): int
    {
        $find = $this->pdo->prepare('SELECT id FROM project_phases WHERE phase_key = :phase_key LIMIT 1');
        $insert = $this->pdo->prepare(
            'INSERT INTO project_phases (phase_key, name, sort_order, is_active, is_system)
             VALUES (:phase_key, :name, :sort_order, 1, 1)'
        );
        $count = 0;
        $position = 10;
        foreach ($items as $key => $name) {
            if (!preg_match('/^[a-z][a-z0-9_]{0,79}$/', (string) $key)
                || !is_string($name) || trim($name) === '' || mb_strlen($name, 'UTF-8') > 150
            ) {
                throw new RuntimeException('Phase de projet invalide dans la configuration.');
            }
            $find->execute(['phase_key' => $key]);
            if ($find->fetchColumn() === false) {
                $insert->execute([
                    'phase_key' => $key,
                    'name' => trim($name),
                    'sort_order' => $position,
                ]);
                $count++;
            }
            $position += 10;
        }
        return $count;
    }

    private function initializeProjectPhaseWeights(): int
    {
        $projectIds = $this->pdo->query(
            'SELECT project_id FROM project_phase_assignments
             GROUP BY project_id HAVING MIN(weight_percent) = 0 AND MAX(weight_percent) = 0'
        )->fetchAll(PDO::FETCH_COLUMN);
        $phases = $this->pdo->prepare(
            'SELECT id FROM project_phase_assignments WHERE project_id = :project_id ORDER BY sort_order, id'
        );
        $update = $this->pdo->prepare(
            'UPDATE project_phase_assignments SET weight_percent = :weight_percent
             WHERE id = :id AND weight_percent = 0'
        );
        $count = 0;
        foreach ($projectIds as $projectId) {
            $phases->execute(['project_id' => (int) $projectId]);
            $phaseIds = array_map('intval', $phases->fetchAll(PDO::FETCH_COLUMN));
            if ($phaseIds === []) {
                continue;
            }
            $baseWeight = intdiv(10000, count($phaseIds));
            $remainder = 10000 % count($phaseIds);
            foreach ($phaseIds as $index => $phaseId) {
                $weightUnits = $baseWeight + ($index < $remainder ? 1 : 0);
                $update->execute([
                    'id' => $phaseId,
                    'weight_percent' => sprintf('%d.%02d', intdiv($weightUnits, 100), $weightUnits % 100),
                ]);
                $count += $update->rowCount();
            }
        }
        return $count;
    }

    /** @param array<int,array{governorate_iso:string,delegation_code:?string,source_delegation:string,code:string,locality_latin:string,locality_ar:string}> $items
     *  @param array<string,int> $governorateIds
     *  @param array<string,int> $delegationIds
     *  @param array<string,string> $delegationGovernorateIsos
     */
    private function seedPostalCodes(
        array $items,
        array $governorateIds,
        array $delegationIds,
        array $delegationGovernorateIsos
    ): int {
        $find = $this->pdo->prepare(
            'SELECT id FROM postal_codes
             WHERE governorate_id = :governorate_id AND source_delegation = :source_delegation
               AND code = :code AND locality_latin = :locality_latin LIMIT 1'
        );
        $insert = $this->pdo->prepare(
            'INSERT INTO postal_codes (delegation_id, governorate_id, source_delegation, code, locality_latin, locality_ar)
             VALUES (:delegation_id, :governorate_id, :source_delegation, :code, :locality_latin, :locality_ar)'
        );
        $update = $this->pdo->prepare(
            'UPDATE postal_codes SET delegation_id = :delegation_id, locality_ar = :locality_ar
             WHERE governorate_id = :governorate_id AND source_delegation = :source_delegation
               AND code = :code AND locality_latin = :locality_latin'
        );
        $count = 0;

        foreach ($items as $item) {
            $governorateIso = (string) ($item['governorate_iso'] ?? '');
            $delegationCode = $item['delegation_code'] ?? null;
            $code = (string) ($item['code'] ?? '');
            $sourceDelegation = trim((string) ($item['source_delegation'] ?? ''));
            $localityLatin = trim((string) ($item['locality_latin'] ?? ''));
            $localityArabic = trim((string) ($item['locality_ar'] ?? ''));
            if (!isset($governorateIds[$governorateIso]) || !preg_match('/^[0-9]{4}$/', $code)
                || $sourceDelegation === '' || mb_strlen($sourceDelegation, 'UTF-8') > 150
                || mb_strlen($localityLatin, 'UTF-8') > 150 || mb_strlen($localityArabic, 'UTF-8') > 150
            ) {
                throw new RuntimeException('Code postal ou référence géographique invalide dans le seed.');
            }

            $delegationId = null;
            if ($delegationCode !== null) {
                if (!is_string($delegationCode) || $delegationCode === ''
                    || !isset($delegationIds[$delegationCode])
                    || ($delegationGovernorateIsos[$delegationCode] ?? null) !== $governorateIso
                ) {
                    throw new RuntimeException('Délégation inconnue ou rattachée à un autre gouvernorat dans le seed postal.');
                }
                $delegationId = $delegationIds[$delegationCode];
            }
            $parameters = [
                'delegation_id' => $delegationId,
                'governorate_id' => $governorateIds[$governorateIso],
                'source_delegation' => $sourceDelegation,
                'code' => $code,
                'locality_latin' => $localityLatin,
                'locality_ar' => $localityArabic,
            ];
            $find->execute([
                'governorate_id' => $parameters['governorate_id'],
                'source_delegation' => $parameters['source_delegation'],
                'code' => $parameters['code'],
                'locality_latin' => $parameters['locality_latin'],
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
