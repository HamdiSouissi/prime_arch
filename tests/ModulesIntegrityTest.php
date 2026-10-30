<?php
declare(strict_types=1);

namespace Tests;

use App\Services\ModuleManifestValidator;
use PHPUnit\Framework\TestCase;

/**
 * Garde-fou des modules installables (vague 4 et suivantes).
 *
 * Chaque dossier de `modules/` doit rester autonome et honnête : manifeste valide, traductions
 * FR/AR complètes, vues réellement présentes et **aucune écriture dans les tables du cœur**.
 * Le test lit le disque, sans base de données ni module activé.
 */
final class ModulesIntegrityTest extends TestCase
{
    /** Tables du noyau qu’aucun module ne doit modifier (lecture seule autorisée). */
    private const CORE_TABLES = [
        'clients',
        'projects',
        'documents',
        'document_payments',
        'document_items',
        'document_templates',
        'document_sequences',
        'time_entries',
        'project_phases',
        'project_phase_assignments',
        'users',
        'settings',
        'audit_logs',
        'modules',
        'roles',
        'permissions',
    ];

    /** @return array<int,string> Slugs des modules réels (les gabarits `_` sont ignorés). */
    private static function slugs(): array
    {
        $slugs = [];
        foreach (glob(BASE_PATH . '/modules/*/module.json') ?: [] as $manifest) {
            $slug = basename(dirname($manifest));
            if (!str_starts_with($slug, '_')) {
                $slugs[] = $slug;
            }
        }
        sort($slugs);
        self::assertNotSame([], $slugs, 'Aucun module détecté.');

        return $slugs;
    }

    /** @return array<int,string> */
    private static function files(string $slug, string $extension): array
    {
        $files = [];
        $directory = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator(BASE_PATH . '/modules/' . $slug, \FilesystemIterator::SKIP_DOTS)
        );
        foreach ($directory as $file) {
            if ($file instanceof \SplFileInfo && $file->isFile() && $file->getExtension() === $extension) {
                $files[] = $file->getPathname();
            }
        }
        sort($files);

        return $files;
    }

    public function testEveryModuleHasAValidManifest(): void
    {
        $validator = new ModuleManifestValidator();
        foreach (self::slugs() as $slug) {
            $json = (string) file_get_contents(BASE_PATH . '/modules/' . $slug . '/module.json');
            $manifest = $validator->decode($json, $slug);
            $raw = json_decode($json, true);
            $declared = is_array($raw) ? $raw : [];
            self::assertSame($slug, $manifest['slug'], "Le manifeste de {$slug} ne porte pas son slug.");
            self::assertTrue(
                is_file(BASE_PATH . '/modules/' . $slug . '/' . $manifest['entry']),
                "Le point d’entrée déclaré par {$slug} est absent."
            );
            // Un module en lecture seule (aucune table) n’a pas à déclarer de migrations :
            // on regarde ce que le manifeste déclare réellement, avant normalisation.
            if (array_key_exists('migrations', $declared)) {
                self::assertTrue(
                    is_dir(BASE_PATH . '/modules/' . $slug . '/' . $manifest['migrations']),
                    "Le dossier de migrations déclaré par {$slug} est absent."
                );
            }
        }
    }

    public function testModuleTranslationsAreCompleteInBothLanguages(): void
    {
        foreach (self::slugs() as $slug) {
            $french = self::translations($slug, 'fr');
            $arabic = self::translations($slug, 'ar');
            self::assertNotSame([], $french, "Le module {$slug} ne déclare aucune traduction.");

            $frenchKeys = array_keys($french);
            $arabicKeys = array_keys($arabic);
            sort($frenchKeys);
            sort($arabicKeys);
            self::assertSame($frenchKeys, $arabicKeys, "Le module {$slug} n’a pas les mêmes clés en FR et en AR.");

            foreach (['fr' => $french, 'ar' => $arabic] as $locale => $messages) {
                foreach ($messages as $key => $value) {
                    self::assertIsString($value, "La clé {$key} ({$locale}, {$slug}) doit être une chaîne.");
                    self::assertNotSame('', trim($value), "La clé {$key} ({$locale}, {$slug}) est vide.");
                }
            }
        }
    }

    public function testModuleViewsReferencedByCodeExist(): void
    {
        foreach (self::slugs() as $slug) {
            $views = 0;
            foreach (self::files($slug, 'php') as $file) {
                $source = (string) file_get_contents($file);
                preg_match_all(
                    "/renderModule\(\s*'([a-z0-9-]+)'\s*,\s*'([a-zA-Z0-9_\/-]+)'/",
                    $source,
                    $matches,
                    PREG_SET_ORDER
                );
                foreach ($matches as $match) {
                    $views++;
                    self::assertSame($slug, $match[1], 'Une vue est rendue depuis un autre module.');
                    self::assertTrue(
                        is_file(BASE_PATH . '/modules/' . $slug . '/views/' . $match[2] . '.php'),
                        "Le module {$slug} rend la vue {$match[2]} qui n’existe pas."
                    );
                }
            }
            self::assertGreaterThanOrEqual(1, $views, "Le module {$slug} ne rend aucune vue.");
        }
    }

    public function testNoModuleWritesToCoreTables(): void
    {
        $writes = '/\b(?:INSERT\s+INTO|UPDATE|DELETE\s+FROM|ALTER\s+TABLE|DROP\s+TABLE|TRUNCATE)\s+`?([a-z_]+)`?/i';
        foreach (self::slugs() as $slug) {
            foreach (array_merge(self::files($slug, 'sql'), self::files($slug, 'php')) as $file) {
                if (str_contains($file, '/lang/')) {
                    continue;
                }
                $source = (string) preg_replace('/--[^\n]*/', '', (string) file_get_contents($file));
                preg_match_all($writes, $source, $matches);
                foreach ($matches[1] as $table) {
                    self::assertFalse(
                        in_array(strtolower($table), self::CORE_TABLES, true),
                        basename($file) . " ({$slug}) écrit dans la table du cœur {$table}."
                    );
                }
            }
        }
    }

    public function testModuleTablesShareOnePrefix(): void
    {
        $prefixes = [];
        foreach (self::slugs() as $slug) {
            $own = [];
            foreach (self::files($slug, 'sql') as $file) {
                $source = (string) preg_replace('/--[^\n]*/', '', (string) file_get_contents($file));
                preg_match_all('/CREATE\s+TABLE\s+(?:IF\s+NOT\s+EXISTS\s+)?`?([a-z0-9_]+)`?/i', $source, $matches);
                foreach ($matches[1] as $table) {
                    $prefix = explode('_', strtolower($table))[0];
                    $own[$prefix] = true;
                    self::assertFalse(
                        in_array(strtolower($table), self::CORE_TABLES, true),
                        "Le module {$slug} crée la table du cœur {$table}."
                    );
                }
            }
            if ($own === []) {
                continue;
            }
            self::assertCount(1, array_keys($own), "Les tables du module {$slug} n’ont pas de préfixe commun.");
            $prefix = (string) array_keys($own)[0];
            self::assertArrayNotHasKey($prefix, $prefixes, "Deux modules partagent le préfixe de tables {$prefix}_.");
            $prefixes[$prefix] = $slug;
        }
        self::assertNotSame([], $prefixes, 'Aucun module ne crée de table : le test ne vérifie rien.');
    }

    /** @return array<string,string> */
    private static function translations(string $slug, string $locale): array
    {
        $file = BASE_PATH . '/modules/' . $slug . '/lang/' . $locale . '.php';
        if (!is_file($file)) {
            return [];
        }
        $messages = require $file;

        return is_array($messages) ? $messages : [];
    }
}
