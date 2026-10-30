<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\View;
use App\Models\HourlyRateRepository;
use App\Services\AuditLogger;
use App\Services\HourlyRateService;
use App\Services\ViewContext;
use PDOException;
use Throwable;

/** Consultation des taux et ajout de nouveaux jalons d'historique financier. */
final class HourlyRateController
{
    public function index(Request $request): Response
    {
        if (!Auth::can('finance.rates.view') && !Auth::can('finance.rates.manage')) {
            return Response::html(__('common.forbidden'), 403);
        }
        $values = Session::pullFlash('finance.rate_values', []);
        if (!is_array($values) || $values === []) {
            $values = [
                'user_id' => '',
                'effective_from' => date('Y-m-d'),
                'cost_rate_per_hour' => '',
                'note' => '',
            ];
        }
        $context = (new ViewContext())->authenticated(__('finance.rates_title'), 'settings');
        return Response::html(View::render('pages/settings/hourly-rates', $context + [
            'rates' => (new HourlyRateRepository())->history(),
            'users' => Auth::can('finance.rates.manage') ? (new HourlyRateRepository())->users() : [],
            'values' => $values,
            'errors' => Session::pullFlash('finance.rate_errors', []),
            'notice' => Session::pullFlash('finance.rate_notice'),
            'canManage' => Auth::can('finance.rates.manage'),
        ]));
    }

    public function create(Request $request): Response
    {
        if (!Auth::can('finance.rates.manage')) {
            return Response::html(__('common.forbidden'), 403);
        }
        $validated = (new HourlyRateService())->validate($request->all());
        if ($validated['errors'] !== []) {
            Session::flash('finance.rate_errors', $validated['errors']);
            Session::flash('finance.rate_values', $validated['values']);
            return Response::redirect(url('/settings/hourly-rates'));
        }
        try {
            $rateId = (new HourlyRateRepository())->create($validated['values'], Auth::id());
        } catch (PDOException $error) {
            error_log((string) $error);
            $duplicate = (int) ($error->errorInfo[1] ?? 0) === 1062;
            Session::flash('finance.rate_errors', [
                'effective_from' => $duplicate ? __('finance.rate_date_exists') : __('finance.rate_save_failed'),
            ]);
            Session::flash('finance.rate_values', $validated['values']);
            return Response::redirect(url('/settings/hourly-rates'));
        } catch (Throwable $error) {
            error_log((string) $error);
            Session::flash('finance.rate_errors', ['general' => __('finance.rate_save_failed')]);
            Session::flash('finance.rate_values', $validated['values']);
            return Response::redirect(url('/settings/hourly-rates'));
        }
        (new AuditLogger())->record('finance.rates.created', 'hourly_rate', $rateId, [
            'user_id' => (int) $validated['values']['user_id'],
            'effective_from' => (string) $validated['values']['effective_from'],
        ], Auth::id());
        Session::flash('finance.rate_notice', __('finance.rate_created'));
        return Response::redirect(url('/settings/hourly-rates'));
    }
}
