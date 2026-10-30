<?php
declare(strict_types=1);

namespace Tests;

use App\Services\BackupService;
use PHPUnit\Framework\TestCase;

final class BackupArchivePolicyTest extends TestCase
{
    public function testSqlSchemaValidationRejectsMultipleStatementsAndTableNameMismatch(): void
    {
        $validator = new \ReflectionMethod(BackupService::class, 'isSafeCreateTable');
        self::assertTrue($validator->invoke(null, "CREATE TABLE `clients` (id INT NOT NULL) ENGINE=InnoDB", 'clients'));
        self::assertFalse($validator->invoke(null, "CREATE TABLE `clients` (id INT) ENGINE=InnoDB; DROP TABLE `users`", 'clients'));
        self::assertFalse($validator->invoke(null, "CREATE TABLE `users` (id INT) ENGINE=InnoDB", 'clients'));
        self::assertFalse($validator->invoke(null, "CREATE TABLE `clients` (id INT) ENGINE=MyISAM", 'clients'));
    }

    public function testOnlyPrivateApplicationStorageRootsCanBeIncluded(): void
    {
        self::assertTrue(BackupService::isAllowedStoragePath('storage/uploads/cabinet/logo-a1.png'));
        self::assertTrue(BackupService::isAllowedStoragePath('storage/pdf/document-12.pdf'));
        self::assertTrue(BackupService::isAllowedStoragePath('storage/modules/sample/Module.php'));
        self::assertFalse(BackupService::isAllowedStoragePath('storage/backups/previous.zip'));
        self::assertFalse(BackupService::isAllowedStoragePath('storage/logs/app.log'));
        self::assertFalse(BackupService::isAllowedStoragePath('storage/uploads/../secrets.txt'));
        self::assertFalse(BackupService::isAllowedStoragePath('/storage/uploads/file.png'));
        self::assertFalse(BackupService::isAllowedStoragePath('storage/uploads/file name.png'));
    }
}
