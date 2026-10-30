<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Request;
use App\Core\Response;
use App\Core\View;
use App\Models\GeographyRepository;
use App\Services\CabinetSettings;
use App\Services\ViewContext;

/**
 * Tableau de bord du noyau Prime Arch.
 */
final class DashboardController
{
    public function index(Request $request): Response
    {
        if (!Auth::can('dashboard.view')) {
            return Response::html(__('common.forbidden'), 403);
        }

        $user = Auth::user() ?? [];
        $settings = (new CabinetSettings())->get();
        $context = (new ViewContext())->authenticated(__('nav.dashboard'), 'dashboard');
        return Response::html(View::render('pages/app/dashboard', $context + [
            'userName' => (string) ($user['name'] ?? ''),
            'roleLabel' => role_label((string) ($user['role_slug'] ?? ''), (string) ($user['role_label'] ?? '')),
            'cabinetName' => $settings['name'],
            'geographyCounts' => (new GeographyRepository())->counts(),
        ]));
    }
}
