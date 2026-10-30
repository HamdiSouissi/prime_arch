<?php
declare(strict_types=1);

namespace App\Services;

use JsonException;

/** Validation stricte du manifeste d'un module. */
final class ModuleManifestValidator
{
    /** @throws ModuleException */
    public function decode(string $json, ?string $expectedSlug = null): array
    {
        if ($json === '' || strlen($json) > 65536) {
            throw new ModuleException('Le manifeste module.json est vide ou dépasse 64 Ko.');
        }

        try {
            $data = json_decode($json, true, 32, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw new ModuleException('Le fichier module.json ne contient pas un JSON valide.');
        }
        if (!is_array($data) || array_is_list($data)) {
            throw new ModuleException('Le manifeste doit être un objet JSON.');
        }

        return $this->validate($data, $expectedSlug);
    }

    /** @throws ModuleException */
    public function validate(array $data, ?string $expectedSlug = null): array
    {
        foreach (['slug', 'name', 'version', 'description', 'author', 'namespace', 'requires', 'enabled_by_default'] as $key) {
            if (!array_key_exists($key, $data)) {
                throw new ModuleException("Le manifeste ne contient pas le champ obligatoire « {$key} ».");
            }
        }

        $slug = $this->string($data['slug'], 'slug', 100);
        if (!preg_match('/^[a-z0-9](?:[a-z0-9-]{0,98}[a-z0-9])?$/D', $slug)) {
            throw new ModuleException('Le slug doit être en minuscules, sans espace, et ne peut pas commencer ou finir par un tiret.');
        }
        if ($expectedSlug !== null && !hash_equals($expectedSlug, $slug)) {
            throw new ModuleException('Le nom du dossier ZIP doit correspondre au slug déclaré dans module.json.');
        }

        $name = $this->string($data['name'], 'name', 150);
        $version = $this->string($data['version'], 'version', 20);
        if (!preg_match('/^(0|[1-9][0-9]*)\.(0|[1-9][0-9]*)\.(0|[1-9][0-9]*)(?:-[0-9A-Za-z.-]+)?(?:\+[0-9A-Za-z.-]+)?$/D', $version)) {
            throw new ModuleException('La version du module doit respecter SemVer (ex. 1.2.3).');
        }
        $description = $this->string($data['description'], 'description', 500);
        $author = $this->string($data['author'], 'author', 150);
        $namespace = $this->string($data['namespace'], 'namespace', 190);
        if (!preg_match('/^Modules\\\\[A-Z][A-Za-z0-9]*(?:\\\\[A-Z][A-Za-z0-9]*)*$/D', $namespace)) {
            throw new ModuleException('Le namespace doit commencer par Modules\\ et utiliser des segments StudlyCase.');
        }

        if (!is_array($data['requires']) || array_is_list($data['requires'])) {
            throw new ModuleException('Le champ « requires » doit être un objet.');
        }
        $requires = $data['requires'];
        $phpConstraint = $this->string($requires['php'] ?? null, 'requires.php', 80);
        $appConstraint = $this->string($requires['app'] ?? null, 'requires.app', 80);
        if (!$this->validConstraint($phpConstraint) || !$this->validConstraint($appConstraint)) {
            throw new ModuleException('Les contraintes requires.php et requires.app doivent être des versions simples (>=8.2, ^1.2 ou ~1.2.3).');
        }
        $moduleRequirements = $requires['modules'] ?? [];
        if (!is_array($moduleRequirements) || !array_is_list($moduleRequirements) || count($moduleRequirements) > 50) {
            throw new ModuleException('requires.modules doit être une liste de 50 modules au maximum.');
        }
        $dependencies = [];
        foreach ($moduleRequirements as $dependency) {
            if (!is_string($dependency)
                || !preg_match('/^[a-z0-9](?:[a-z0-9-]{0,98}[a-z0-9])?$/D', $dependency)
                || $dependency === $slug
            ) {
                throw new ModuleException('Une dépendance de requires.modules est invalide ou référence le module lui-même.');
            }
            if (in_array($dependency, $dependencies, true)) {
                throw new ModuleException('La liste requires.modules contient une dépendance en double.');
            }
            $dependencies[] = $dependency;
        }

        $entry = $data['entry'] ?? 'Module.php';
        if (!is_string($entry) || !self::isSafeRelativePath($entry) || !str_ends_with(strtolower($entry), '.php')) {
            throw new ModuleException('Le point d’entrée doit être un chemin PHP relatif sûr.');
        }
        $migrations = $this->directory($data['migrations'] ?? 'migrations', 'migrations');
        $lang = $this->directory($data['lang'] ?? 'lang', 'lang');
        if (!is_bool($data['enabled_by_default'])) {
            throw new ModuleException('enabled_by_default doit être un booléen.');
        }

        return [
            'slug' => $slug,
            'name' => $name,
            'version' => $version,
            'description' => $description,
            'author' => $author,
            'namespace' => $namespace,
            'requires' => [
                'php' => $phpConstraint,
                'app' => $appConstraint,
                'modules' => $dependencies,
            ],
            'entry' => $entry,
            'migrations' => $migrations,
            'lang' => $lang,
            'enabled_by_default' => $data['enabled_by_default'],
        ];
    }

    /** Compare les instantanés JSON sans dépendre de l'ordre des clés d'objet MySQL. */
    public function matchesSnapshot(array $stored, array $current): bool
    {
        return self::canonicalize($stored) === self::canonicalize($current);
    }

    /** @param array<mixed> $value
     *  @return array<mixed>
     */
    private static function canonicalize(array $value): array
    {
        if (!array_is_list($value)) {
            ksort($value, SORT_STRING);
        }
        foreach ($value as $key => $item) {
            if (is_array($item)) {
                $value[$key] = self::canonicalize($item);
            }
        }
        return $value;
    }

    /** @throws ModuleException */
    public function assertCompatible(array $manifest): void
    {
        $requires = $manifest['requires'];
        if (!$this->matchesConstraint((string) $requires['php'], PHP_VERSION)) {
            throw new ModuleException('Ce module requiert PHP ' . $requires['php'] . ', version actuelle : ' . PHP_VERSION . '.');
        }
        $appVersion = (string) config('app.version', '0.0.0');
        if (!$this->matchesConstraint((string) $requires['app'], $appVersion)) {
            throw new ModuleException('Ce module requiert Prime Arch ' . $requires['app'] . ', version actuelle : ' . $appVersion . '.');
        }
    }

    /** Les chemins d'archive sont volontairement limités à des segments ASCII simples. */
    public static function isSafeRelativePath(string $path): bool
    {
        if ($path === '' || strlen($path) > 240 || str_contains($path, "\0") || str_contains($path, '\\') || str_starts_with($path, '/')) {
            return false;
        }
        $segments = explode('/', $path);
        if (count($segments) > 8) {
            return false;
        }
        foreach ($segments as $segment) {
            if ($segment === '' || $segment === '.' || $segment === '..'
                || !preg_match('/^[A-Za-z0-9][A-Za-z0-9._-]{0,119}$/D', $segment)
            ) {
                return false;
            }
        }
        return true;
    }

    private function string(mixed $value, string $field, int $maxLength): string
    {
        if (!is_string($value)) {
            throw new ModuleException("Le champ « {$field} » doit être une chaîne de caractères.");
        }
        $value = trim($value);
        if ($value === '' || strlen($value) > $maxLength * 4 || mb_strlen($value, 'UTF-8') > $maxLength
            || preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', $value)
        ) {
            throw new ModuleException("Le champ « {$field} » est vide ou dépasse la longueur autorisée.");
        }
        return $value;
    }

    private function directory(mixed $value, string $field): string
    {
        if (!is_string($value) || !self::isSafeRelativePath($value) || str_contains($value, '/')) {
            throw new ModuleException("Le dossier « {$field} » doit être un nom de répertoire simple.");
        }
        return $value;
    }

    private function validConstraint(string $constraint): bool
    {
        $clauses = preg_split('/\s*,\s*/', trim($constraint));
        if (!$clauses || $clauses === ['']) {
            return false;
        }
        foreach ($clauses as $clause) {
            if (!preg_match('/^(?:>=|<=|>|<|=|\^|~)?\s*[0-9]+(?:\.[0-9]+){0,2}$/D', trim($clause))) {
                return false;
            }
        }
        return true;
    }

    private function matchesConstraint(string $constraint, string $current): bool
    {
        if (!$this->validConstraint($constraint)) {
            return false;
        }
        $current = $this->normalizeVersion($current);
        foreach (preg_split('/\s*,\s*/', trim($constraint)) ?: [] as $clause) {
            if (!preg_match('/^(>=|<=|>|<|=|\^|~)?\s*([0-9]+(?:\.[0-9]+){0,2})$/D', trim($clause), $matches)) {
                return false;
            }
            $operator = $matches[1] ?? '=';
            $parts = array_map('intval', explode('.', $matches[2]));
            $base = $this->normalizeVersion(implode('.', $parts));
            if ($operator === '^' || $operator === '~') {
                $upper = $this->upperBound($parts, $operator);
                if (version_compare($current, $base, '<') || version_compare($current, $upper, '>=')) {
                    return false;
                }
                continue;
            }
            $comparison = $operator === '' ? '=' : $operator;
            if (!version_compare($current, $base, $comparison)) {
                return false;
            }
        }
        return true;
    }

    /** @param int[] $parts */
    private function upperBound(array $parts, string $operator): string
    {
        $precision = count($parts);
        $parts = array_pad($parts, 3, 0);
        [$major, $minor, $patch] = $parts;
        if ($operator === '~') {
            return $precision >= 3
                ? $major . '.' . ($minor + 1) . '.0'
                : ($major + 1) . '.0.0';
        }
        if ($major > 0 || $precision === 1) {
            return ($major + 1) . '.0.0';
        }
        if ($minor > 0 || $precision === 2) {
            return '0.' . ($minor + 1) . '.0';
        }
        return '0.0.' . ($patch + 1);
    }

    private function normalizeVersion(string $version): string
    {
        $parts = array_slice(explode('.', preg_replace('/[^0-9.].*$/', '', $version) ?? $version), 0, 3);
        return implode('.', array_pad($parts, 3, '0'));
    }
}
