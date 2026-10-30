<?php
declare(strict_types=1);

namespace Tests;

use App\Services\AuthService;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class FoundationsTest extends TestCase
{
    public function testTwentyFourGovernoratesAndTwoHundredSixtyFourDelegationsAreSeeded(): void
    {
        $governorates = require BASE_PATH . '/database/seeds/governorates.php';
        $delegations = require BASE_PATH . '/database/seeds/delegations.php';
        $governorateCodes = array_column($governorates, 'iso');
        $delegationCodes = array_column($delegations, 'code');

        self::assertCount(24, $governorates);
        self::assertCount(24, array_unique($governorateCodes));
        self::assertCount(264, $delegations);
        self::assertCount(264, array_unique($delegationCodes));
        foreach ($delegations as $delegation) {
            self::assertContains($delegation['governorate_iso'], $governorateCodes);
            self::assertNotSame('', $delegation['name_latin']);
            self::assertNotSame('', $delegation['name_ar']);
        }
    }

    public function testPostalCodeSeedContainsOnlyFourDigitCodes(): void
    {
        $delegations = require BASE_PATH . '/database/seeds/delegations.php';
        $postalCodes = require BASE_PATH . '/database/seeds/postal_codes.php';
        $delegationCodes = array_column($delegations, 'code');

        foreach ($postalCodes as $postalCode) {
            self::assertMatchesRegularExpression('/^\d{4}$/', $postalCode['code']);
            self::assertContains($postalCode['delegation_code'], $delegationCodes);
        }
    }

    public function testDefaultRolesAreUniqueAndAdministratorHasFullPermission(): void
    {
        $roles = require BASE_PATH . '/database/seeds/roles.php';
        self::assertCount(5, $roles);
        self::assertCount(5, array_unique(array_column($roles, 'slug')));
        $administrator = array_values(array_filter($roles, static fn (array $role): bool => $role['slug'] === 'administrator'))[0];
        self::assertSame(['*'], $administrator['permissions']);
    }

    public function testPasswordHashUsesArgon2idWhenAvailable(): void
    {
        if (!in_array('argon2id', password_algos(), true)) {
            self::markTestSkipped('Argon2id n’est pas disponible sur cet environnement.');
        }
        $hash = AuthService::hashPassword('mot-de-passe-fort-2026');
        self::assertStringStartsWith('$argon2id$', $hash);
        self::assertTrue(password_verify('mot-de-passe-fort-2026', $hash));
    }

    public function testPasswordHashRejectsShortPasswords(): void
    {
        $this->expectException(InvalidArgumentException::class);
        AuthService::hashPassword('trop-court');
    }
}
