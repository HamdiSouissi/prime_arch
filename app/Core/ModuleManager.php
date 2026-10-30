<?php
declare(strict_types=1);

namespace App\Core;

use App\Models\ModuleRepository;
use App\Services\ModuleException;
use App\Services\ModuleManifestValidator;
use Throwable;

/** Découverte et cycle d'exécution des modules activés en base. */
final class ModuleManager
{
    /** @var array<string,array<string,mixed>> manifestes prêts à charger */
    private static array $modules = [];

    /** @var array<string,string> namespaces PSR-4 déjà enregistrés */
    private static array $registeredNamespaces = [];

    /**
     * Charge les modules installés depuis le registre, puis valide leur manifeste sur disque.
     * Les archives téléversées résident dans storage/modules/<slug>/.
     *
     * @return array<string,array<string,mixed>>
     */
    public static function discover(): array
    {
        self::$modules = [];
        $validator = new ModuleManifestValidator();
        try {
            $rows = (new ModuleRepository())->all();
        } catch (Throwable $error) {
            error_log('Impossible de lire le registre des modules : ' . $error->getMessage());
            return [];
        }

        foreach ($rows as $row) {
            $slug = (string) ($row['slug'] ?? '');
            $path = self::modulePath($slug);
            if ($path === null) {
                continue;
            }
            $manifestFile = $path . DIRECTORY_SEPARATOR . 'module.json';
            if (is_link($manifestFile) || !is_file($manifestFile) || !is_readable($manifestFile)) {
                error_log("Le manifeste du module {$slug} est introuvable ou invalide.");
                continue;
            }
            $manifestSize = filesize($manifestFile);
            if ($manifestSize === false || $manifestSize > 65536) {
                error_log("Le manifeste du module {$slug} dépasse 64 Ko.");
                continue;
            }
            try {
                $manifest = $validator->decode((string) file_get_contents($manifestFile), $slug);
                if (is_array($row['manifest'] ?? null) && !$validator->matchesSnapshot($row['manifest'], $manifest)) {
                    throw new ModuleException('Le manifeste installé a changé depuis la validation du ZIP.');
                }
                $manifest['path'] = $path;
                self::$modules[$slug] = $manifest;
            } catch (Throwable $error) {
                error_log("Chargement du manifeste du module {$slug} refusé : " . $error->getMessage());
            }
        }
        return self::$modules;
    }

    /** @return string[] Slugs activés en base de données. */
    public static function enabled(): array
    {
        try {
            return (new ModuleRepository())->enabled();
        } catch (Throwable $error) {
            error_log('Impossible de lire les modules activés : ' . $error->getMessage());
            return [];
        }
    }

    /**
     * Démarre les dépendances en premier, puis enregistre hooks, routes et traductions.
     */
    public static function boot(Router $router): void
    {
        $modules = self::discover();
        $enabled = array_fill_keys(self::enabled(), true);
        $validator = new ModuleManifestValidator();
        $state = [];
        $order = [];
        $visit = static function (string $slug, int $depth = 0) use (&$visit, &$state, &$order, $modules, $enabled, $validator): void {
            if ($depth > 100) {
                throw new ModuleException('La chaîne de dépendances dépasse la profondeur autorisée.');
            }
            if (($state[$slug] ?? 0) === 2) {
                return;
            }
            if (($state[$slug] ?? 0) === 1) {
                throw new ModuleException("Une dépendance circulaire implique le module {$slug}.");
            }
            if (!isset($modules[$slug]) || !isset($enabled[$slug])) {
                throw new ModuleException("Le module requis {$slug} n’est pas installé et activé.");
            }
            $validator->assertCompatible($modules[$slug]);
            $state[$slug] = 1;
            foreach ($modules[$slug]['requires']['modules'] as $dependency) {
                if (!isset($enabled[$dependency])) {
                    throw new ModuleException("La dépendance {$dependency} du module {$slug} n’est pas activée.");
                }
                $visit($dependency, $depth + 1);
            }
            $state[$slug] = 2;
            $order[] = $slug;
        };

        foreach (array_keys($enabled) as $slug) {
            try {
                $visit((string) $slug);
            } catch (Throwable $error) {
                error_log("Démarrage du module {$slug} refusé : " . $error->getMessage());
            }
        }

        foreach (array_values(array_unique($order)) as $slug) {
            $manifest = $modules[$slug];
            try {
                HookManager::doAction('module.booting', $slug, $manifest);
                $module = self::instantiate($manifest);
                if (method_exists($module, 'boot')) {
                    $module->boot();
                }
                if (method_exists($module, 'routes')) {
                    $module->routes($router);
                }
                $langPath = self::safeChildDirectory($manifest['path'], (string) $manifest['lang']);
                if ($langPath !== null) {
                    Translator::addPath($langPath);
                }
                HookManager::doAction('module.booted', $slug, $manifest);
            } catch (Throwable $error) {
                error_log("Erreur au démarrage du module {$slug} : " . $error->getMessage());
            }
        }
    }

    /** Construit une instance sans activer le module ni exécuter ses routes. */
    public static function instantiate(array $manifest, ?string $path = null): object
    {
        $path ??= is_string($manifest['path'] ?? null) ? $manifest['path'] : null;
        if ($path === null || !is_dir($path) || is_link($path)) {
            throw new ModuleException('Le dossier du module est introuvable.');
        }
        $entry = (string) ($manifest['entry'] ?? 'Module.php');
        if (!ModuleManifestValidator::isSafeRelativePath($entry) || !str_ends_with(strtolower($entry), '.php')) {
            throw new ModuleException('Le point d’entrée du module est invalide.');
        }
        $entryFile = $path . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $entry);
        $realPath = realpath($path);
        $realEntry = realpath($entryFile);
        if ($realPath === false || $realEntry === false || is_link($entryFile)
            || !str_starts_with($realEntry, $realPath . DIRECTORY_SEPARATOR) || !is_file($realEntry)
        ) {
            throw new ModuleException('Le point d’entrée du module sort de son dossier ou est introuvable.');
        }

        self::registerAutoload($manifest, $realPath);
        require_once $realEntry;
        $class = (string) ($manifest['namespace'] ?? '') . '\\Module';
        if (!class_exists($class, true)) {
            throw new ModuleException('La classe namespace\\Module attendue est absente du point d’entrée.');
        }
        $module = new $class();
        return $module;
    }

    /** Exécute le hook install une seule fois après le succès des migrations du module. */
    public static function runInstallHook(array $manifest, string $path): void
    {
        $module = self::instantiate($manifest, $path);
        if (method_exists($module, 'install')) {
            $module->install();
        }
    }

    /** Retourne un dossier privé existant, sans suivre de lien symbolique. */
    public static function modulePath(string $slug): ?string
    {
        if (!preg_match('/^[a-z0-9](?:[a-z0-9-]{0,98}[a-z0-9])?$/D', $slug)) {
            return null;
        }
        $storage = BASE_PATH . '/storage';
        $storageReal = !is_link($storage) && is_dir($storage) ? realpath($storage) : false;
        $storageRoot = $storage . DIRECTORY_SEPARATOR . 'modules';
        if ($storageReal !== false && !is_link($storageRoot) && is_dir($storageRoot)) {
            $root = realpath($storageRoot);
            $candidate = $storageRoot . DIRECTORY_SEPARATOR . $slug;
            $real = realpath($candidate);
            if ($root !== false && str_starts_with($root, $storageReal . DIRECTORY_SEPARATOR)
                && !is_link($candidate) && $real !== false
                && str_starts_with($real, $root . DIRECTORY_SEPARATOR) && is_dir($real)
            ) {
                return $real;
            }
        }

        // Compatibilité avec les modules livrés avec le code de l'application.
        $bundledRoot = BASE_PATH . '/modules';
        $candidate = $bundledRoot . DIRECTORY_SEPARATOR . $slug;
        $root = !is_link($bundledRoot) ? realpath($bundledRoot) : false;
        $real = realpath($candidate);
        if ($root !== false && !is_link($candidate) && $real !== false
            && str_starts_with($real, $root . DIRECTORY_SEPARATOR) && is_dir($real)
        ) {
            return $real;
        }
        return null;
    }

    private static function registerAutoload(array $manifest, string $modulePath): void
    {
        $namespace = (string) ($manifest['namespace'] ?? '');
        if (!preg_match('/^Modules\\\\[A-Z][A-Za-z0-9]*(?:\\\\[A-Z][A-Za-z0-9]*)*$/D', $namespace)) {
            throw new ModuleException('Le namespace PSR-4 du module est invalide.');
        }
        $prefix = rtrim($namespace, '\\') . '\\';
        if (isset(self::$registeredNamespaces[$prefix])) {
            if (self::$registeredNamespaces[$prefix] !== $modulePath) {
                throw new ModuleException('Un autre module utilise déjà ce namespace.');
            }
            return;
        }
        self::$registeredNamespaces[$prefix] = $modulePath;
        spl_autoload_register(static function (string $class) use ($prefix, $modulePath): void {
            if (!str_starts_with($class, $prefix)) {
                return;
            }
            $relative = substr($class, strlen($prefix));
            if (!preg_match('/^[A-Za-z_][A-Za-z0-9_]*(?:\\\\[A-Za-z_][A-Za-z0-9_]*)*$/D', $relative)) {
                return;
            }
            $file = $modulePath . DIRECTORY_SEPARATOR . str_replace('\\', DIRECTORY_SEPARATOR, $relative) . '.php';
            if (is_file($file) && !is_link($file)) {
                $realFile = realpath($file);
                if ($realFile !== false && str_starts_with($realFile, $modulePath . DIRECTORY_SEPARATOR)) {
                    require_once $realFile;
                }
            }
        }, true, true);
    }

    private static function safeChildDirectory(string $modulePath, string $directory): ?string
    {
        if (!ModuleManifestValidator::isSafeRelativePath($directory) || str_contains($directory, '/')) {
            return null;
        }
        $candidate = $modulePath . DIRECTORY_SEPARATOR . $directory;
        if (is_link($candidate) || !is_dir($candidate)) {
            return null;
        }
        $root = realpath($modulePath);
        $real = realpath($candidate);
        return $root !== false && $real !== false && str_starts_with($real, $root . DIRECTORY_SEPARATOR)
            ? $real
            : null;
    }
}
