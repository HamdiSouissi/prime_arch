<?php
declare(strict_types=1);

namespace App\Core;

use RuntimeException;

/**
 * Moteur de vues PHP (templates dans app/Views, avec layout optionnel).
 */
final class View
{
    /**
     * Rend une vue.
     *
     * @param string      $view   chemin relatif à app/Views, sans extension (ex. "pages/home")
     * @param array       $data   variables disponibles dans la vue
     * @param string|null $layout layout à utiliser (ex. "layouts/app"), ou null
     */
    public static function render(string $view, array $data = [], ?string $layout = 'layouts/app'): string
    {
        $content = self::capture($view, $data);
        if ($layout === null) {
            return $content;
        }
        return self::capture($layout, $data + ['content' => $content]);
    }

    /**
     * Inclut un partiel (ex. sidebar) depuis un layout ou une vue.
     */
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
        extract($data, EXTR_SKIP);
        ob_start();
        require $file;
        return (string) ob_get_clean();
    }
}
