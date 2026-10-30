<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Core\HookManager;
use App\Core\Migrator;
use App\Core\ModuleManager;
use App\Models\ModuleRepository;
use PDO;
use PDOException;
use Throwable;

/** Orchestration métier de l'installation et de l'état des modules. */
final class ModuleService
{
    private PDO $pdo;
    private ModuleRepository $repository;
    private ModuleManifestValidator $validator;
    private ModuleArchiveInstaller $archiveInstaller;

    public function __construct(
        ?PDO $pdo = null,
        ?ModuleRepository $repository = null,
        ?ModuleManifestValidator $validator = null,
        ?ModuleArchiveInstaller $archiveInstaller = null
    ) {
        $this->pdo = $pdo ?? Database::connection();
        $this->repository = $repository ?? new ModuleRepository($this->pdo);
        $this->validator = $validator ?? new ModuleManifestValidator();
        $this->archiveInstaller = $archiveInstaller ?? new ModuleArchiveInstaller($this->validator);
    }

    /** @return array<int,array<string,mixed>> */
    public function all(): array
    {
        $rows = $this->repository->all();
        $enabled = [];
        foreach ($rows as $row) {
            if ($row['is_enabled']) {
                $enabled[$row['slug']] = true;
            }
        }

        foreach ($rows as &$row) {
            $manifest = null;
            $availabilityError = null;
            try {
                $manifest = $this->readManifest($row);
            } catch (ModuleException $error) {
                $availabilityError = $error->getMessage();
                $manifest = is_array($row['manifest'] ?? null) ? $row['manifest'] : null;
            }
            $compatibilityError = null;
            if (is_array($manifest)) {
                try {
                    $this->validator->assertCompatible($manifest);
                } catch (ModuleException $error) {
                    $compatibilityError = $error->getMessage();
                }
            }
            $dependencies = is_array($manifest['requires']['modules'] ?? null)
                ? $manifest['requires']['modules']
                : [];
            $missingDependencies = [];
            foreach ($dependencies as $dependency) {
                if (!isset($enabled[$dependency])) {
                    $missingDependencies[] = $dependency;
                }
            }
            $row['module_manifest'] = $manifest;
            $row['availability_error'] = $availabilityError;
            $row['compatibility_error'] = $compatibilityError;
            $row['missing_dependencies'] = $missingDependencies;
            $row['display_name'] = (string) ($manifest['name'] ?? $row['name']);
            $row['display_version'] = (string) ($manifest['version'] ?? $row['version']);
            $row['description'] = (string) ($manifest['description'] ?? '');
            $row['author'] = (string) ($manifest['author'] ?? '');
            $row['dependencies'] = $dependencies;
        }
        unset($row);
        return $rows;
    }

    /**
     * Valide, extrait et inscrit un ZIP sans remplacer de module existant.
     * Une activation par défaut est tentée après l'installation; un échec laisse le module inactif.
     *
     * @return array{manifest:array<string,mixed>,enabled:bool,warning:?string}
     */
    public function install(array $upload, ?int $installedBy): array
    {
        $package = $this->archiveInstaller->installUpload($upload);
        $manifest = $package['manifest'];
        $slug = $manifest['slug'];
        try {
            $this->validator->assertCompatible($manifest);
            if ($this->repository->find($slug) !== null) {
                throw new ModuleException('Ce module est déjà enregistré. Aucun fichier n’a été remplacé.');
            }
            $this->assertUniqueNamespace($manifest);
            $this->repository->create($manifest, $package['sha256'], $installedBy);
        } catch (ModuleException $error) {
            $this->archiveInstaller->removeModuleDirectory($slug);
            throw $error;
        } catch (PDOException $error) {
            $this->archiveInstaller->removeModuleDirectory($slug);
            if ($error->getCode() === '23000') {
                throw new ModuleException('Un module portant ce slug est déjà installé.');
            }
            error_log((string) $error);
            throw new ModuleException('Le module a été extrait, mais son inscription en base a échoué.');
        } catch (Throwable $error) {
            $this->archiveInstaller->removeModuleDirectory($slug);
            error_log((string) $error);
            throw new ModuleException('Le module n’a pas pu être enregistré.');
        }

        $this->dispatchEvent('module.installed', $slug, $manifest);
        $warning = null;
        if ($manifest['enabled_by_default']) {
            try {
                $this->activate($slug);
            } catch (ModuleException $error) {
                $warning = 'Module installé mais laissé inactif : ' . $error->getMessage();
            } catch (Throwable $error) {
                error_log((string) $error);
                $warning = 'Module installé mais laissé inactif après une erreur. Consultez le journal du serveur.';
            }
        }

        $row = $this->repository->find($slug);
        return [
            'manifest' => $manifest,
            'enabled' => (bool) ($row['is_enabled'] ?? false),
            'warning' => $warning,
        ];
    }

    /** Active un module après validation des dépendances et migrations. */
    public function activate(string $slug): void
    {
        $row = $this->findInstalled($slug);
        if ($row['is_enabled']) {
            return;
        }
        $manifest = $this->readManifest($row);
        $this->validator->assertCompatible($manifest);
        $this->assertUniqueNamespace($manifest, $slug);
        $this->assertNoDependencyCycle($slug, $manifest);
        $this->assertDependenciesEnabled($manifest, [$slug => true]);

        $path = ModuleManager::modulePath($slug);
        if ($path === null) {
            throw new ModuleException('Le code du module est absent du stockage privé.');
        }
        $this->runMigrations($manifest, $path);

        if (!$row['install_hook_completed']) {
            try {
                ModuleManager::runInstallHook($manifest, $path);
            } catch (Throwable $error) {
                error_log("Hook install du module {$slug} en erreur : " . $error->getMessage());
                throw new ModuleException('Le hook install() du module a échoué; le module reste inactif.');
            }
            $this->repository->markInstallHookCompleted($slug);
        }

        $this->repository->setEnabled($slug, true);
        $this->dispatchEvent('module.activated', $slug, $manifest);
    }

    /** Désactive sans désinstaller le code, supprimer les tables ni effacer les données. */
    public function deactivate(string $slug): void
    {
        $row = $this->findInstalled($slug);
        if (!$row['is_enabled']) {
            return;
        }
        foreach ($this->repository->all() as $dependent) {
            if (!$dependent['is_enabled'] || $dependent['slug'] === $slug) {
                continue;
            }
            $manifest = $this->manifestForDependencyCheck($dependent);
            $dependencies = $manifest['requires']['modules'] ?? [];
            if (is_array($dependencies) && in_array($slug, $dependencies, true)) {
                throw new ModuleException('Désactivez d’abord le module « ' . $dependent['name'] . ' », qui en dépend.');
            }
        }

        $this->repository->setEnabled($slug, false);
        $manifest = is_array($row['manifest'] ?? null) ? $row['manifest'] : [];
        $this->dispatchEvent('module.deactivated', $slug, $manifest);
    }

    private function runMigrations(array $manifest, string $modulePath): void
    {
        $directoryName = (string) $manifest['migrations'];
        if (!ModuleManifestValidator::isSafeRelativePath($directoryName) || str_contains($directoryName, '/')) {
            throw new ModuleException('Le dossier de migrations du module est invalide.');
        }
        $directory = $modulePath . DIRECTORY_SEPARATOR . $directoryName;
        if (is_link($directory)) {
            throw new ModuleException('Le dossier de migrations ne peut pas être un lien symbolique.');
        }
        if (!is_dir($directory)) {
            return;
        }
        $root = realpath($modulePath);
        $realDirectory = realpath($directory);
        if ($root === false || $realDirectory === false || !str_starts_with($realDirectory, $root . DIRECTORY_SEPARATOR)) {
            throw new ModuleException('Le dossier de migrations sort du répertoire du module.');
        }
        foreach (glob($realDirectory . DIRECTORY_SEPARATOR . '*.sql') ?: [] as $file) {
            $realFile = realpath($file);
            $relativeFile = str_replace('\\', '/', substr($file, strlen(BASE_PATH) + 1));
            if (is_link($file) || $realFile === false || !str_starts_with($realFile, $realDirectory . DIRECTORY_SEPARATOR)
                || strlen($relativeFile) > 255
            ) {
                throw new ModuleException('Un fichier de migration est lié ou son chemin dépasse la limite autorisée.');
            }
        }

        try {
            (new Migrator($this->pdo))->run([$realDirectory]);
        } catch (Throwable $error) {
            error_log("Migration du module {$manifest['slug']} en erreur : " . $error->getMessage());
            throw new ModuleException('Une migration du module a échoué; le module reste inactif.');
        }
    }

    private function findInstalled(string $slug): array
    {
        if (!preg_match('/^[a-z0-9](?:[a-z0-9-]{0,98}[a-z0-9])?$/D', $slug)) {
            throw new ModuleException('Le slug du module est invalide.');
        }
        $row = $this->repository->find($slug);
        if ($row === null) {
            throw new ModuleException('Ce module n’est pas installé.');
        }
        return $row;
    }

    private function readManifest(array $row): array
    {
        $slug = (string) $row['slug'];
        $path = ModuleManager::modulePath($slug);
        if ($path === null) {
            throw new ModuleException('Le code du module « ' . $row['name'] . ' » est indisponible.');
        }
        $manifestFile = $path . DIRECTORY_SEPARATOR . 'module.json';
        if (is_link($manifestFile) || !is_file($manifestFile) || !is_readable($manifestFile)) {
            throw new ModuleException('Le manifeste installé est absent ou invalide.');
        }
        $manifestSize = filesize($manifestFile);
        if ($manifestSize === false || $manifestSize > 65536) {
            throw new ModuleException('Le manifeste installé dépasse 64 Ko.');
        }
        $manifest = $this->validator->decode((string) file_get_contents($manifestFile), $slug);
        $realRoot = realpath($path);
        $entryFile = $path . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $manifest['entry']);
        $realEntry = realpath($entryFile);
        if ($realRoot === false || $realEntry === false || is_link($entryFile)
            || !str_starts_with($realEntry, $realRoot . DIRECTORY_SEPARATOR) || !is_file($realEntry)
        ) {
            throw new ModuleException('Le point d’entrée du module est absent ou sort de son dossier.');
        }
        if (is_array($row['manifest'] ?? null) && !$this->validator->matchesSnapshot($row['manifest'], $manifest)) {
            throw new ModuleException('Le manifeste du module a changé depuis sa validation initiale; la mise à jour ZIP n’est pas encore prise en charge.');
        }
        $manifest['path'] = $path;
        return $manifest;
    }

    private function assertUniqueNamespace(array $manifest, ?string $exceptSlug = null): void
    {
        $namespace = strtolower((string) $manifest['namespace']);
        foreach ($this->repository->all() as $row) {
            if ($row['slug'] === $exceptSlug) {
                continue;
            }
            $candidate = is_array($row['manifest'] ?? null) ? $row['manifest'] : null;
            if ($candidate === null) {
                try {
                    $candidate = $this->readManifest($row);
                } catch (ModuleException) {
                    $candidate = null;
                }
            }
            if (isset($candidate['namespace']) && strtolower((string) $candidate['namespace']) === $namespace) {
                throw new ModuleException('Le namespace « ' . $manifest['namespace'] . ' » est déjà utilisé par un autre module.');
            }
        }
    }

    private function assertNoDependencyCycle(string $slug, array $manifest): void
    {
        $graph = [];
        foreach ($this->repository->all() as $row) {
            $candidate = is_array($row['manifest'] ?? null) ? $row['manifest'] : [];
            if ($candidate === []) {
                try {
                    $candidate = $this->readManifest($row);
                } catch (ModuleException) {
                    $candidate = [];
                }
            }
            $graph[$row['slug']] = is_array($candidate['requires']['modules'] ?? null)
                ? $candidate['requires']['modules']
                : [];
        }
        $graph[$slug] = $manifest['requires']['modules'];
        $state = [];
        $visit = static function (string $current, int $depth = 0) use (&$visit, &$state, $graph): void {
            if ($depth > 100) {
                throw new ModuleException('La chaîne de dépendances dépasse la profondeur autorisée.');
            }
            if (($state[$current] ?? 0) === 1) {
                throw new ModuleException('Les dépendances déclarées créent une boucle.');
            }
            if (($state[$current] ?? 0) === 2) {
                return;
            }
            $state[$current] = 1;
            foreach ($graph[$current] ?? [] as $dependency) {
                if (isset($graph[$dependency])) {
                    $visit($dependency, $depth + 1);
                }
            }
            $state[$current] = 2;
        };
        $visit($slug);
    }

    /** @param array<string,bool> $visiting */
    private function assertDependenciesEnabled(array $manifest, array $visiting): void
    {
        if (count($visiting) > 100) {
            throw new ModuleException('La chaîne de dépendances dépasse la profondeur autorisée.');
        }
        foreach ($manifest['requires']['modules'] as $dependency) {
            if (isset($visiting[$dependency])) {
                throw new ModuleException('Les dépendances déclarées créent une boucle.');
            }
            $row = $this->repository->find($dependency);
            if ($row === null || !$row['is_enabled']) {
                throw new ModuleException('Activez d’abord la dépendance « ' . $dependency . ' ».');
            }
            $dependencyManifest = $this->readManifest($row);
            $this->validator->assertCompatible($dependencyManifest);
            $visiting[$dependency] = true;
            $this->assertDependenciesEnabled($dependencyManifest, $visiting);
        }
    }

    private function manifestForDependencyCheck(array $row): array
    {
        try {
            return $this->readManifest($row);
        } catch (ModuleException) {
            return is_array($row['manifest'] ?? null) ? $row['manifest'] : [];
        }
    }

    private function dispatchEvent(string $event, string $slug, array $manifest): void
    {
        try {
            HookManager::doAction($event, $slug, $manifest);
        } catch (Throwable $error) {
            // Un écouteur externe ne doit pas annuler une opération déjà inscrite en base.
            error_log("Écouteur du hook {$event} en erreur : " . $error->getMessage());
        }
    }
}
