<?php
declare(strict_types=1);

namespace Tests;

use App\Services\AuditLogger;
use PHPUnit\Framework\TestCase;

final class AuditLoggerTest extends TestCase
{
    public function testSensitiveKeysAreRemovedFromAuditDetails(): void
    {
        $safe = AuditLogger::sanitizeDetails([
            'role' => 'accountant',
            'is_active' => true,
            'email' => 'confidentiel@example.tn',
            'password' => 'secret',
            'tax_id' => 'identifiant-fiscal',
            'amount' => '1250.000',
            'hourly_rate' => '80.000',
            'document_type' => 'invoice',
            'extra' => ['nested' => 'not copied'],
        ]);

        self::assertSame([
            'role' => 'accountant',
            'is_active' => true,
            'document_type' => 'invoice',
        ], $safe);
    }

    public function testDetailsAreLengthLimitedAndInvalidKeysAreDiscarded(): void
    {
        $safe = AuditLogger::sanitizeDetails([
            'status' => str_repeat('x', 200),
            'Invalid-Key' => 'discarded',
            5 => 'discarded',
            'count' => 12,
        ]);

        self::assertSame(120, mb_strlen((string) $safe['status'], 'UTF-8'));
        self::assertSame(12, $safe['count']);
        self::assertCount(2, $safe);
    }
}
