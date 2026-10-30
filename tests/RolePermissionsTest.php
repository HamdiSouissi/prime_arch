<?php
declare(strict_types=1);

namespace Tests;

use PHPUnit\Framework\TestCase;

final class RolePermissionsTest extends TestCase
{
    public function testOnlyAdministratorReceivesTheGlobalPermissionWildcard(): void
    {
        $roles = require BASE_PATH . '/database/seeds/roles.php';
        $wildcardRoles = [];
        foreach ($roles as $role) {
            if (in_array('*', $role['permissions'], true)) {
                $wildcardRoles[] = $role['slug'];
            }
        }
        self::assertSame(['administrator'], $wildcardRoles);
    }

    public function testSensitiveAdministrativeAndCabinetSettingsAreNotGrantedToStandardRoles(): void
    {
        $roles = require BASE_PATH . '/database/seeds/roles.php';
        foreach ($roles as $role) {
            if ($role['slug'] === 'administrator') {
                continue;
            }
            self::assertNotContains('users.manage', $role['permissions']);
            self::assertNotContains('audit.view', $role['permissions']);
            self::assertNotContains('settings.view', $role['permissions']);
        }
    }

    public function testTimeManagementDoesNotImplyFinancialPermissions(): void
    {
        $roles = require BASE_PATH . '/database/seeds/roles.php';
        $architect = array_values(array_filter($roles, static fn (array $role): bool => $role['slug'] === 'architect'))[0];
        self::assertContains('time.manage_all', $architect['permissions']);
        self::assertNotContains('finance.costs.view', $architect['permissions']);
        self::assertNotContains('finance.rates.view', $architect['permissions']);
    }
}
