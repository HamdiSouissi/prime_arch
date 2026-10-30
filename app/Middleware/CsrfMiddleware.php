<?php
declare(strict_types=1);

namespace App\Middleware;

use App\Core\Csrf;
use App\Core\Request;
use App\Core\Response;

/**
 * Vérifie le jeton CSRF sur les requêtes POST.
 */
final class CsrfMiddleware
{
    public function handle(Request $request): ?Response
    {
        if ($request->isPost() && !Csrf::verify((string) $request->input('_token'))) {
            return new Response('Jeton CSRF invalide.', 419);
        }
        return null;
    }
}
