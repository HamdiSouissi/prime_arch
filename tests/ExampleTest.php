<?php
declare(strict_types=1);

namespace Tests;

use App\Core\Config;
use PHPUnit\Framework\TestCase;

final class ExampleTest extends TestCase
{
    public function testConfigIsLoaded(): void
    {
        $this->assertSame('TND', Config::get('tunisia.currency.code'));
    }

    public function testMoneyFormat(): void
    {
        // Le séparateur de milliers est une espace insécable : le montant reste un seul bloc
        // en arabe (voir tests/CurrencyFormatTest.php et le commentaire de money_format_tnd).
        $nbsp = "\u{00A0}";
        \App\Core\Translator::setLocale('fr');
        $this->assertSame('1' . $nbsp . '250,500' . $nbsp . 'DT', money_format_tnd(1250.5));
        $this->assertSame(
            '1' . $nbsp . '234' . $nbsp . '567' . $nbsp . '890' . $nbsp . '123,456' . $nbsp . 'DT',
            money_format_tnd('1234567890123.456')
        );
        $this->assertSame('-0,001' . $nbsp . 'DT', money_format_tnd('-0.001'));
    }

    public function testFinancialPermissionsAreSeparateFromTimeWildcard(): void
    {
        $roles = require BASE_PATH . '/database/seeds/roles.php';
        $bySlug = array_column($roles, null, 'slug');
        // Les droits de temps sont énumérés (moindre privilège) : aucun joker `time.*`.
        foreach (['time.view', 'time.view_all', 'time.manage', 'time.manage_all'] as $permission) {
            $this->assertContains($permission, $bySlug['architect']['permissions']);
        }
        $this->assertNotContains('finance.costs.view', $bySlug['architect']['permissions']);
        $this->assertContains('finance.costs.view', $bySlug['accountant']['permissions']);
    }

    public function testGovernoratesSeedHas24Entries(): void
    {
        $this->assertCount(24, require BASE_PATH . '/database/seeds/governorates.php');
    }
}
