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
        $this->assertSame('1 250,500 DT', money_format_tnd(1250.5));
        $this->assertSame('1 234 567 890 123,456 DT', money_format_tnd('1234567890123.456'));
        $this->assertSame('-0,001 DT', money_format_tnd('-0.001'));
    }

    public function testFinancialPermissionsAreSeparateFromTimeWildcard(): void
    {
        $roles = require BASE_PATH . '/database/seeds/roles.php';
        $bySlug = array_column($roles, null, 'slug');
        $this->assertContains('time.*', $bySlug['architect']['permissions']);
        $this->assertNotContains('finance.costs.view', $bySlug['architect']['permissions']);
        $this->assertContains('finance.costs.view', $bySlug['accountant']['permissions']);
    }

    public function testGovernoratesSeedHas24Entries(): void
    {
        $this->assertCount(24, require BASE_PATH . '/database/seeds/governorates.php');
    }
}
