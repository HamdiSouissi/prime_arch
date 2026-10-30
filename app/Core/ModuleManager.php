<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Gestionnaire de modules (extensions type WordPress).
 * Spécification complète : docs/MODULES.md
 *
 * Squelette : découverte + démarrage des modules activés.
 * TODO : installation par téléversement ZIP, activation/désactivation en base
 *        (table `modules`), vérification des dépendances, exécution des migrations du module.
 */
final class ModuleManager
{
    /** @var array<string,array> manifestes (module.json) indexés par slug */
    private static array $modules = [];

    /**
     * Découvre les modules présents dans modules/ (dossiers contenant module.json).
     * Les dossiers préfixés par "_" (ex. _example-module) sont ignorés par le démarrage.
     */
    public static function discover(): array
    {
        self::$modules = [];
        foreach (glob(BASE_PATH . '/modules/*/module.json') ?: [] as $manifest) {
            $data = json_decode((string) file_get_contents($manifest), true);
            if (!is_array($data) || empty($data['slug'])) {
                continue; // TODO : journaliser les manifestes invalides
            }
            $data['path'] = dirname($manifest);
            self::$modules[$data['slug']] = $data;
        }
        return self::$modules;
    }

    /**
     * Liste des slugs de modules activés.
     * Source actuelle : config/modules.php. TODO : lire la table `modules`.
     *
     * @return string[]
     */
    public static function enabled(): array
    {
        return (array) Config::get('modules.enabled', []);
    }

    /**
     * Charge et démarre les modules activés.
     */
    public static function boot(Router $router): void
    {
        self::discover();
        foreach (self::enabled() as $slug) {
            if (!isset(self::$modules[$slug])) {
                continue;
            }
            $manifest = self::$modules[$slug];
            self::registerAutoload($manifest);

            $class = $manifest['namespace'] . '\\Module';
            if (!class_exists($class)) {
                require_once $manifest['path'] . '/Module.php';
            }
            if (class_exists($class)) {
                $module = new $class();
                $module->boot();
                $module->routes($router);
                Translator::addPath($manifest['path'] . '/lang');
            }
        }
    }

    /**
     * Enregistre l'autoload PSR-4 d'un module selon son "namespace" et son dossier
     * (le dossier d'un module est en kebab-case, son namespace en StudlyCase).
     */
    private static function registerAutoload(array $manifest): void
    {
        $prefix = rtrim($manifest['namespace'], '\\') . '\\';
        $dir    = $manifest['path'] . '/';
        spl_autoload_register(static function (string $class) use ($prefix, $dir): void {
            if (str_starts_with($class, $prefix)) {
                $file = $dir . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
                if (is_file($file)) {
                    require_once $file;
                }
            }
        });
    }
}
