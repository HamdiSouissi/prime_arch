<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Request;
use App\Core\Response;
use App\Core\View;
use App\Models\AuditRepository;
use App\Services\ViewContext;

/** Consultation du journal de sécurité, réservée au rôle administrateur. */
final class AuditController
{
    public function index(Request $request): Response
    {
        if (!Auth::can('audit.view')) {
            return Response::html(__('common.forbidden'), 403);
        }

        $requestedPage = filter_var($request->query('page', 1), FILTER_VALIDATE_INT);
        $page = $requestedPage === false ? 1 : max(1, min(40_000, (int) $requestedPage));
        $result = (new AuditRepository())->paginate(50, ($page - 1) * 50);
        $context = (new ViewContext())->authenticated(__('audit.title'), 'settings');

        return Response::html(View::render('pages/app/audit', $context + [
            'entries' => $result['items'],
            'total' => $result['total'],
            'page' => $page,
            'pageCount' => max(1, (int) ceil($result['total'] / 50)),
        ]));
    }
}
