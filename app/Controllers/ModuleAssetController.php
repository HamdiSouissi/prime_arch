<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\ModuleManager;
use App\Core\Request;
use App\Core\Response;
use App\Models\ModuleRepository;
use App\Services\ModuleManifestValidator;

/** Sert des ressources statiques de modules sans rendre storage/ public. */
final class ModuleAssetController
{
    private const CONTENT_TYPES = [
        'css' => 'text/css; charset=utf-8',
        'js' => 'text/javascript; charset=utf-8',
        'png' => 'image/png',
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'webp' => 'image/webp',
        'gif' => 'image/gif',
        'svg' => 'image/svg+xml',
        'woff' => 'font/woff',
        'woff2' => 'font/woff2',
        'ico' => 'image/x-icon',
    ];

    public function show(Request $request, string $slug): Response
    {
        $relative = $request->query('file');
        if (!is_string($relative) || !ModuleManifestValidator::isSafeRelativePath($relative)) {
            return Response::html('Ressource de module introuvable.', 404);
        }
        $extension = strtolower(pathinfo($relative, PATHINFO_EXTENSION));
        if (!isset(self::CONTENT_TYPES[$extension])) {
            return Response::html('Type de ressource de module interdit.', 404);
        }
        $record = (new ModuleRepository())->find($slug);
        if ($record === null || !$record['is_enabled']) {
            return Response::html('Ressource de module introuvable.', 404);
        }
        $modulePath = ModuleManager::modulePath($slug);
        if ($modulePath === null) {
            return Response::html('Ressource de module introuvable.', 404);
        }
        $assetsPath = $modulePath . DIRECTORY_SEPARATOR . 'assets';
        $realAssets = realpath($assetsPath);
        $file = $assetsPath . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative);
        $realFile = realpath($file);
        if (is_link($assetsPath) || is_link($file) || $realAssets === false || $realFile === false
            || !str_starts_with($realFile, $realAssets . DIRECTORY_SEPARATOR) || !is_file($realFile)
        ) {
            return Response::html('Ressource de module introuvable.', 404);
        }
        $size = filesize($realFile);
        if ($size === false || $size > 5_000_000) {
            return Response::html('Ressource de module trop volumineuse.', 413);
        }
        $contents = file_get_contents($realFile);
        if (!is_string($contents)) {
            return Response::html('Ressource de module introuvable.', 404);
        }
        $response = Response::download($contents, basename($realFile), self::CONTENT_TYPES[$extension], 'inline');
        if ($extension === 'svg') {
            $response->header('Content-Security-Policy', "sandbox; default-src 'none'; style-src 'unsafe-inline'");
        }
        return $response;
    }
}
