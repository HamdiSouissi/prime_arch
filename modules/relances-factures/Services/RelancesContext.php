<?php
declare(strict_types=1);

namespace Modules\RelancesFactures\Services;

use App\Core\Auth;
use App\Core\HookManager;
use Modules\RelancesFactures\Models\RelanceRepository;

/**
 * Passerelle « fiche facture → relances ».
 *
 * Le lien n'apparaît que pour une facture émise dont le solde est encore positif : une facture
 * réglée ne propose pas de relance, même si le module est actif.
 */
final class RelancesContext
{
    public static function register(): void
    {
        HookManager::addFilter('document.detail.actions', static function (array $sections, array $document): array {
            if (!Auth::can('documents.view') && !Auth::can('documents.manage')) {
                return $sections;
            }
            if (($document['document_type'] ?? '') !== 'invoice' || ($document['status'] ?? '') !== 'issued') {
                return $sections;
            }
            $invoice = (new RelanceRepository())->findInvoice((int) ($document['id'] ?? 0));
            if ($invoice === null) {
                return $sections;
            }
            $balance = self::remainder(
                (string) ($invoice['total_amount'] ?? '0'),
                (string) ($invoice['paid_amount'] ?? '0')
            );
            if ($balance <= 0.0) {
                return $sections;
            }
            $sections[] = [
                'key' => 'relances-factures',
                'label' => __('relance.title'),
                'meta' => __('relance.context_meta', ['amount' => money_format_tnd($balance)]),
                'url' => url('/relances/factures/' . (int) $document['id']),
                'icon' => 'wallet',
                'tone' => 'warning',
            ];

            return $sections;
        });
    }

    /**
     * Solde restant, calculé en millièmes de dinar pour ne pas dériver en flottant.
     *
     * Les montants du noyau sont des DECIMAL à trois décimales : on les convertit en entiers,
     * on soustrait, puis on reformate en chaîne décimale acceptée par `money_format_tnd()`.
     */
    private static function remainder(string $total, string $paid): float
    {
        $milli = static function (string $amount): ?int {
            $amount = trim($amount);
            if (preg_match('/^(\d+)(?:\.(\d{1,3}))?$/', $amount, $parts) !== 1) {
                return null;
            }
            $fraction = str_pad((string) ($parts[2] ?? ''), 3, '0');

            return (int) $parts[1] * 1000 + (int) $fraction;
        };
        $totalMilli = $milli($total);
        $paidMilli = $milli($paid);
        if ($totalMilli === null || $paidMilli === null) {
            return (float) $total - (float) $paid;
        }

        return ($totalMilli - $paidMilli) / 1000;
    }
}
