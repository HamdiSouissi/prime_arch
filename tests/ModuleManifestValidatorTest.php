<?php
declare(strict_types=1);

namespace Tests;

use App\Services\ModuleException;
use App\Services\ModuleManifestValidator;
use PHPUnit\Framework\TestCase;

final class ModuleManifestValidatorTest extends TestCase
{
    public function testAcceptsAndNormalizesTheDocumentedManifest(): void
    {
        $manifest = (new ModuleManifestValidator())->validate($this->manifest());

        self::assertSame('example-module', $manifest['slug']);
        self::assertSame('Module.php', $manifest['entry']);
        self::assertSame([], $manifest['requires']['modules']);
        self::assertFalse($manifest['enabled_by_default']);
    }

    public function testRejectsUnsafeEntryPathsAndMismatchedArchiveRoot(): void
    {
        $validator = new ModuleManifestValidator();
        $manifest = $this->manifest();
        $manifest['entry'] = '../Module.php';
        try {
            $validator->validate($manifest);
            self::fail('Un chemin d’entrée traversant doit être refusé.');
        } catch (ModuleException) {
            self::assertTrue(true);
        }

        $manifest = $this->manifest();
        $this->expectException(ModuleException::class);
        $validator->validate($manifest, 'other-module');
    }

    public function testRejectsMalformedVersionsAndSelfDependencies(): void
    {
        $validator = new ModuleManifestValidator();
        $manifest = $this->manifest();
        $manifest['version'] = '1.2';
        try {
            $validator->validate($manifest);
            self::fail('Une version sans patch doit être refusée.');
        } catch (ModuleException) {
            self::assertTrue(true);
        }

        $manifest = $this->manifest();
        $manifest['requires']['modules'] = ['example-module'];
        $this->expectException(ModuleException::class);
        $validator->validate($manifest);
    }

    public function testChecksRuntimeCompatibilityConstraints(): void
    {
        $validator = new ModuleManifestValidator();
        $validator->assertCompatible($validator->validate($this->manifest()));

        $manifest = $this->manifest();
        $manifest['requires']['php'] = '>=99.0';
        $this->expectException(ModuleException::class);
        $validator->assertCompatible($validator->validate($manifest));
    }

    public function testManifestSnapshotComparisonIgnoresObjectKeyOrder(): void
    {
        $validator = new ModuleManifestValidator();
        $normalized = $validator->validate($this->manifest());
        $reordered = [
            'enabled_by_default' => false,
            'lang' => 'lang',
            'migrations' => 'migrations',
            'entry' => 'Module.php',
            'requires' => [
                'modules' => [],
                'app' => '>=0.1.0',
                'php' => '>=8.2',
            ],
            'namespace' => 'Modules\\ExampleModule',
            'author' => 'Test',
            'description' => 'Description',
            'version' => '0.1.0',
            'name' => 'Example module',
            'slug' => 'example-module',
        ];

        self::assertTrue($validator->matchesSnapshot($reordered, $normalized));
    }

    private function manifest(): array
    {
        return [
            'slug' => 'example-module',
            'name' => 'Example module',
            'version' => '0.1.0',
            'description' => 'Description',
            'author' => 'Test',
            'namespace' => 'Modules\\ExampleModule',
            'requires' => [
                'php' => '>=8.2',
                'app' => '>=0.1.0',
                'modules' => [],
            ],
            'enabled_by_default' => false,
        ];
    }
}
