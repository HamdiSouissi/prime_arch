<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Request;
use App\Core\Response;
use App\Core\View;
use App\Services\CabinetSettings;
use App\Services\DashboardService;
use App\Services\ViewContext;

/**
 * Tableau de bord du noyau : indicateurs réels, échéances et activité du cabinet (lot 4).
 *
 * Le contrôleur ne fait que trois choses : vérifier la permission, laisser le service
 * assembler les données (chaque bloc est déjà filtré par droit), et rendre la vue.
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
        $overview = (new DashboardService())->overview($user, Auth::id());

        return Response::html(View::render('pages/app/dashboard', $context + $overview + [
            'userName' => (string) ($user['name'] ?? ''),
            'cabinetName' => $settings['name'],
        ]));
    }
}
