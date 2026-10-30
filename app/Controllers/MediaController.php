<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Services\CabinetLogoStorage;
use App\Services\CabinetSettings;
use finfo;

/**
 * Sert les médias privés du cabinet après authentification.
 */
final class MediaController
{
    public function cabinetLogo(Request $request): Response
    {
        $filename = (new CabinetSettings())->get()['logo'];
        $path = (new CabinetLogoStorage())->path($filename);
        if ($path === null) {
            return Response::html('Logo introuvable.', 404);
        }

        $mime = (new finfo(FILEINFO_MIME_TYPE))->file($path);
        if (!in_array($mime, ['image/jpeg', 'image/png', 'image/webp'], true)) {
            return Response::html('Type de média interdit.', 415);
        }
        $contents = file_get_contents($path);
        if (!is_string($contents)) {
            return Response::html('Logo introuvable.', 404);
        }

        return (new Response($contents))
            ->header('Content-Type', $mime)
            ->header('Content-Length', (string) strlen($contents))
            ->header('X-Content-Type-Options', 'nosniff')
            ->header('Cache-Control', 'private, max-age=3600');
    }
}
