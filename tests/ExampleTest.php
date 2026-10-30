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
    }

    public function testGovernoratesSeedHas24Entries(): void
    {
        $this->assertCount(24, require BASE_PATH . '/database/seeds/governorates.php');
    }
}
