<?php
declare(strict_types=1);

namespace App\Middleware;

use App\Core\Csrf;
use App\Core\Request;
use App\Core\Response;

/**
 * Vérifie les jetons sur toutes les méthodes HTTP modifiant l'état.
 */
final class CsrfMiddleware
{
    private const STATE_CHANGING_METHODS = ['POST', 'PUT', 'PATCH', 'DELETE'];

    public function handle(Request $request): ?Response
    {
        if (!in_array($request->method(), self::STATE_CHANGING_METHODS, true)) {
            return null;
        }

        $token = $request->input('_token');
        if (!Csrf::verify(is_string($token) ? $token : null)) {
            return Response::html('Jeton CSRF invalide.', 419);
        }
        return null;
    }
}
