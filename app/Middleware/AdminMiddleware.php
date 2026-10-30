<?php
declare(strict_types=1);

namespace App\Middleware;

use App\Core\Auth;
use App\Core\Request;
use App\Core\Response;

/**
 * Réserve une route aux utilisateurs disposant du droit d'administration des comptes.
 */
final class AdminMiddleware
{
    public function handle(Request $request): ?Response
    {
        if (!Auth::can('users.manage')) {
            return Response::html(__('common.forbidden'), 403);
        }
        return null;
    }
}
