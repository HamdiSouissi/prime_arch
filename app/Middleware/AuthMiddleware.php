<?php
declare(strict_types=1);

namespace App\Middleware;

use App\Core\Request;
use App\Core\Response;

/**
 * Exige un utilisateur connecté.
 * Contrat : handle() retourne null pour laisser passer, ou une Response pour interrompre.
 */
final class AuthMiddleware
{
    public function handle(Request $request): ?Response
    {
        // TODO : si pas de session utilisateur → Response::redirect(url('login'))
        return null;
    }
}
