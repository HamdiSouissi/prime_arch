<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Système de hooks à la WordPress : actions (événements) et filtres (transformation de valeur).
 *
 * Exemple :
 *   HookManager::addFilter('sidebar.items', fn(array $items) => [...$items, $monItem]);
 *   $items = HookManager::applyFilters('sidebar.items', $items);
 */
final class HookManager
{
    /** @var array<string,array<int,array<int,callable>>> */
    private static array $actions = [];
    /** @var array<string,array<int,array<int,callable>>> */
    private static array $filters = [];

    public static function addAction(string $hook, callable $callback, int $priority = 10): void
    {
        self::$actions[$hook][$priority][] = $callback;
    }

    public static function doAction(string $hook, mixed ...$args): void
    {
        foreach (self::sorted(self::$actions[$hook] ?? []) as $callback) {
            $callback(...$args);
        }
    }

    public static function addFilter(string $hook, callable $callback, int $priority = 10): void
    {
        self::$filters[$hook][$priority][] = $callback;
    }

    public static function applyFilters(string $hook, mixed $value, mixed ...$args): mixed
    {
        foreach (self::sorted(self::$filters[$hook] ?? []) as $callback) {
            $value = $callback($value, ...$args);
        }
        return $value;
    }

    /** @return callable[] */
    private static function sorted(array $byPriority): array
    {
        ksort($byPriority);
        return $byPriority ? array_merge(...$byPriority) : [];
    }
}
