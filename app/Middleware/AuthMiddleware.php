<?php
declare(strict_types=1);

namespace App\Middleware;

use App\Core\Auth;
use App\Core\Request;
use App\Core\Response;

/**
 * Exige une session authentifiée avant l'accès à une route privée.
 */
final class AuthMiddleware
{
    public function handle(Request $request): ?Response
    {
        if (Auth::check()) {
            return null;
        }

        $loginUrl = url('/login');
        $returnPath = $request->path();
        if ($returnPath !== '' && str_starts_with($returnPath, '/') && !str_starts_with($returnPath, '//')) {
            $loginUrl .= '?return=' . rawurlencode($returnPath);
        }
        return Response::redirect($loginUrl);
    }
}
