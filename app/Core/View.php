<?php
declare(strict_types=1);

namespace App\Core;

use App\Services\ModuleManifestValidator;
use RuntimeException;

/** Moteur de vues PHP pour le noyau et les templates privés des modules. */
final class View
{
    /**
     * Rend une vue.
     *
     * @param string      $view   chemin relatif à app/Views, sans extension
     * @param array       $data   variables disponibles dans la vue
     * @param string|null $layout layout optionnel (ex. "layouts/app")
     */
    public static function render(string $view, array $data = [], ?string $layout = 'layouts/app'): string
    {
        $content = self::capture($view, $data);
        if ($layout === null) {
            return $content;
        }
        return self::capture($layout, $data + ['content' => $content]);
    }

    /** Rend une vue privée appartenant à un module, relativement à son dossier views/. */
    public static function renderModule(string $slug, string $view, array $data = [], ?string $layout = 'layouts/app'): string
    {
        if (!ModuleManifestValidator::isSafeRelativePath($view)) {
            throw new RuntimeException('Chemin de vue de module invalide.');
        }
        $modulePath = ModuleManager::modulePath($slug);
        if ($modulePath === null) {
            throw new RuntimeException('Module introuvable.');
        }
        $viewsPath = $modulePath . DIRECTORY_SEPARATOR . 'views';
        $realViews = realpath($viewsPath);
        $file = $viewsPath . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $view) . '.php';
        $realFile = realpath($file);
        if (is_link($viewsPath) || $realViews === false || $realFile === false
            || !str_starts_with($realFile, $realViews . DIRECTORY_SEPARATOR) || !is_file($realFile)
        ) {
            throw new RuntimeException('Vue de module introuvable.');
        }
        $content = self::captureFile($realFile, $data);
        if ($layout === null) {
            return $content;
        }
        return self::capture($layout, $data + ['content' => $content]);
    }

    /** Inclut un partiel depuis un layout ou une vue. */
    public static function partial(string $view, array $data = []): void
    {
        echo self::capture($view, $data);
    }

    private static function capture(string $view, array $data): string
    {
        $file = BASE_PATH . '/app/Views/' . $view . '.php';
        if (!is_file($file)) {
            throw new RuntimeException("Vue introuvable : {$view}");
        }
        return self::captureFile($file, $data);
    }

    private static function captureFile(string $file, array $data): string
    {
        extract($data, EXTR_SKIP);
        ob_start();
        require $file;
        return (string) ob_get_clean();
    }
}
