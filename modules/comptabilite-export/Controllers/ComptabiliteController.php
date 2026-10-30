<?php
declare(strict_types=1);

namespace Modules\ComptabiliteExport\Controllers;

use App\Core\Auth;
use App\Core\Request;
use App\Core\Response;
use App\Core\View;
use App\Services\ViewContext;
use Modules\ComptabiliteExport\Models\ComptabiliteRepository;
use Modules\ComptabiliteExport\Services\ComptabiliteService;

/**
 * Journaux comptables : consultation et export CSV pour l’expert-comptable.
 *
 * Le module est strictement en lecture : il ne crée, ne modifie et ne supprime aucune donnée,
 * et n’applique aucun taux. Droits réutilisés : `documents.view` ou `finance.view`.
 */
final class ComptabiliteController
{
    private ComptabiliteRepository $comptabilite;
    private ComptabiliteService $service;

    public function __construct(?ComptabiliteRepository $comptabilite = null, ?ComptabiliteService $service = null)
    {
        $this->comptabilite = $comptabilite ?? new ComptabiliteRepository();
        $this->service = $service ?? new ComptabiliteService();
    }

    /** Journaux du mois sélectionné : ventes, encaissements et ventilation de la TVA. */
    public function index(Request $request): Response
    {
        if (($denied = $this->requireView()) !== null) {
            return $denied;
        }
        $year = ComptabiliteService::year($request->query('year', ''));
        $month = ComptabiliteService::month($request->query('month', ''));
        $period = ComptabiliteService::period($year, $month);
        $documents = $this->comptabilite->documents($period['start'], $period['end']);
        $payments = $this->comptabilite->payments($period['start'], $period['end']);
        $salesTotals = $this->service->salesTotals($documents);
        $paymentsTotal = $this->service->paymentsTotal($payments);

        return Response::html(View::renderModule('comptabilite-export', 'index', $this->context('comptabilite.index_title') + [
            'year' => $year,
            'month' => $month,
            'period' => $period,
            'years' => $this->years($year),
            'documents' => $documents,
            'payments' => $payments,
            'salesTotals' => $salesTotals,
            'paymentsTotal' => $paymentsTotal,
            'vatBreakdown' => $this->service->vatBreakdown($documents),
            'statistics' => [
                'documents' => $salesTotals['documents'],
                'invoices' => $this->countType($documents, 'invoice'),
                'credit_notes' => $this->countType($documents, 'credit_note'),
                'payments' => count($payments),
                'outstanding' => $salesTotals['total'] - $paymentsTotal,
            ],
        ]));
    }

    /** Export CSV d’un journal pour la période affichée. */
    public function exportCsv(Request $request): Response
    {
        if (($denied = $this->requireView()) !== null) {
            return $denied;
        }
        $journal = (string) $request->query('journal', 'ventes');
        if (!in_array($journal, ComptabiliteService::JOURNALS, true)) {
            return Response::html(__('comptabilite.unknown_journal'), 404);
        }
        $year = ComptabiliteService::year($request->query('year', ''));
        $month = ComptabiliteService::month($request->query('month', ''));
        $period = ComptabiliteService::period($year, $month);

        $rows = $journal === 'ventes'
            ? $this->salesRows($period['start'], $period['end'])
            : $this->paymentRows($period['start'], $period['end']);

        return Response::download(
            $this->service->csv($rows),
            'prime-arch-journal-' . $journal . '-' . $period['key'] . '.csv',
            'text/csv; charset=utf-8'
        );
    }

    /**
     * Lignes du journal des ventes, avoirs en négatif.
     *
     * @return array<int,array<int,string>>
     */
    private function salesRows(string $from, string $to): array
    {
        $documents = $this->comptabilite->documents($from, $to);
        $totals = $this->service->salesTotals($documents);
        $rows = [[
            __('comptabilite.date'),
            __('comptabilite.document'),
            __('comptabilite.type'),
            __('comptabilite.client'),
            __('comptabilite.tax_id'),
            __('comptabilite.project'),
            __('comptabilite.base'),
            __('comptabilite.vat_rate'),
            __('comptabilite.vat'),
            __('comptabilite.stamp'),
            __('comptabilite.withholding'),
            __('comptabilite.total'),
        ]];
        foreach ($documents as $document) {
            $sign = ComptabiliteService::sign((string) $document['document_type']);
            $project = trim((string) ($document['project_reference'] ?? ''));
            $rows[] = [
                self::date((string) ($document['issue_date'] ?? '')),
                (string) ($document['document_number'] ?? ''),
                __('comptabilite.document_type.' . (string) $document['document_type']),
                (string) $document['client_name'],
                (string) ($document['client_tax_id'] ?? ''),
                $project !== '' ? $project : (string) ($document['project_name'] ?? ''),
                ComptabiliteService::format($sign * ComptabiliteService::millimes($document['subtotal'] ?? '0')),
                str_replace('.', ',', (string) ($document['tax_rate'] ?? '0.000')),
                ComptabiliteService::format($sign * ComptabiliteService::millimes($document['tax_amount'] ?? '0')),
                ComptabiliteService::format($sign * ComptabiliteService::millimes($document['stamp_amount'] ?? '0')),
                ComptabiliteService::format($sign * ComptabiliteService::millimes($document['withholding_amount'] ?? '0')),
                ComptabiliteService::format($sign * ComptabiliteService::millimes($document['total_amount'] ?? '0')),
            ];
        }
        $rows[] = [
            __('comptabilite.totals'),
            '',
            '',
            '',
            '',
            '',
            ComptabiliteService::format($totals['base']),
            '',
            ComptabiliteService::format($totals['tax']),
            ComptabiliteService::format($totals['stamp']),
            ComptabiliteService::format($totals['withholding']),
            ComptabiliteService::format($totals['total']),
        ];

        return $rows;
    }

    /**
     * Lignes du journal des encaissements.
     *
     * @return array<int,array<int,string>>
     */
    private function paymentRows(string $from, string $to): array
    {
        $payments = $this->comptabilite->payments($from, $to);
        $rows = [[
            __('comptabilite.date'),
            __('comptabilite.document'),
            __('comptabilite.client'),
            __('comptabilite.method'),
            __('comptabilite.reference'),
            __('comptabilite.amount'),
        ]];
        foreach ($payments as $payment) {
            $rows[] = [
                self::date((string) ($payment['paid_on'] ?? '')),
                (string) ($payment['document_number'] ?? ''),
                (string) $payment['client_name'],
                __('comptabilite.method.' . (string) $payment['payment_method']),
                (string) ($payment['reference'] ?? ''),
                ComptabiliteService::format(ComptabiliteService::millimes($payment['amount'] ?? '0')),
            ];
        }
        $rows[] = [
            __('comptabilite.totals'),
            '',
            '',
            '',
            '',
            ComptabiliteService::format($this->service->paymentsTotal($payments)),
        ];

        return $rows;
    }

    /**
     * Années proposées : celles présentes en base, plus l’année affichée et l’année courante.
     *
     * @return array<int,int>
     */
    private function years(int $selected): array
    {
        $years = $this->comptabilite->years();
        foreach ([$selected, (int) date('Y')] as $year) {
            if (!in_array($year, $years, true)) {
                $years[] = $year;
            }
        }
        rsort($years);

        return $years;
    }

    /** @param array<int,array<string,mixed>> $documents */
    private function countType(array $documents, string $type): int
    {
        $count = 0;
        foreach ($documents as $document) {
            if ((string) $document['document_type'] === $type) {
                $count++;
            }
        }

        return $count;
    }

    private static function date(string $value): string
    {
        $timestamp = strtotime($value);

        return $timestamp === false ? '' : date('d/m/Y', $timestamp);
    }

    /** @return array<string,mixed> */
    private function context(string $titleKey): array
    {
        return (new ViewContext())->authenticated(__($titleKey), 'comptabilite-export');
    }

    private function requireView(): ?Response
    {
        return Auth::can('documents.view') || Auth::can('finance.view')
            ? null
            : Response::html(__('common.forbidden'), 403);
    }
}
