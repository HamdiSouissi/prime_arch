<?php
declare(strict_types=1);

namespace Modules\MetreQuantitatif\Services;

/**
 * Règles de saisie et calculs du métré / DQE.
 *
 * Aucun taux, aucun prix de référence et aucune marge par défaut ne sont imposés : le cabinet
 * saisit ses quantités et ses prix. Tous les calculs passent par Decimal (millimes entiers).
 */
final class MetreService
{
    /** Unités proposées (codes stockés en base, libellés traduits à l’affichage). */
    public const UNITS = ['m2', 'm3', 'ml', 'm', 'u', 'kg', 't', 'ens', 'h', 'j', 'ff'];

    /** Lots fréquents proposés en suggestion de saisie (jamais créés automatiquement). */
    public const SUGGESTED_LOTS = [
        'gros_oeuvre',
        'second_oeuvre',
        'lots_techniques',
        'vrd',
        'amenagement_exterieur',
    ];

    /** Bornes de saisie : elles gardent les produits dans la capacité des entiers 64 bits. */
    public const MAX_QUANTITY = 1_000_000_000; // 1 000 000,000 unités
    public const MAX_UNIT_PRICE = 1_000_000_000; // 1 000 000,000 DT

    /**
     * Valide un lot.
     *
     * @param array<string,mixed> $input
     * @return array{values:array<string,mixed>,errors:array<string,string>}
     */
    public function validateLot(array $input): array
    {
        $values = [
            'name' => trim(self::string($input['name'] ?? '')),
            'sort_order' => self::integer($input['sort_order'] ?? 0, 999, 0),
        ];
        $errors = [];
        $length = mb_strlen($values['name'], 'UTF-8');
        if ($length < 2 || $length > 150) {
            $errors['name'] = __('metre.invalid_lot_name');
        }

        return ['values' => $values, 'errors' => $errors];
    }

    /**
     * Valide un poste du DQE.
     *
     * Quand un article de la bibliothèque est choisi, sa désignation, son unité et son prix
     * remplacent les champs libres ; la quantité et la marge restent celles du formulaire.
     *
     * @param array<string,mixed>      $input
     * @param array<string,mixed>|null $libraryItem Article de bibliothèque sélectionné.
     * @return array{values:array<string,mixed>,errors:array<string,string>}
     */
    public function validatePoste(array $input, ?array $libraryItem = null): array
    {
        $values = [
            'code' => trim(self::string($input['code'] ?? '')),
            'designation' => trim(self::string($input['designation'] ?? '')),
            'unit' => self::string($input['unit'] ?? 'u'),
            'quantity' => trim(self::string($input['quantity'] ?? '')),
            'unit_price' => trim(self::string($input['unit_price'] ?? '')),
            'margin_percent' => trim(self::string($input['margin_percent'] ?? '')),
            'sort_order' => self::integer($input['sort_order'] ?? 0, 999, 0),
            'library_id' => self::positiveId($input['library_id'] ?? null),
        ];
        $errors = [];

        if ($libraryItem !== null) {
            $values['designation'] = (string) ($libraryItem['designation'] ?? $values['designation']);
            $values['unit'] = (string) ($libraryItem['unit'] ?? $values['unit']);
            $values['unit_price'] = (string) ($libraryItem['unit_price'] ?? $values['unit_price']);
            if ($values['code'] === '') {
                $values['code'] = (string) ($libraryItem['code'] ?? '');
            }
        } elseif ($values['library_id'] !== null) {
            $values['library_id'] = null;
        }

        if (!in_array($values['unit'], self::UNITS, true)) {
            $values['unit'] = 'u';
        }
        $designationLength = mb_strlen($values['designation'], 'UTF-8');
        if ($designationLength < 2 || $designationLength > 180) {
            $errors['designation'] = __('metre.invalid_designation');
        }
        if (mb_strlen($values['code'], 'UTF-8') > 40) {
            $values['code'] = mb_substr($values['code'], 0, 40, 'UTF-8');
        }

        $quantity = Decimal::parse($values['quantity']);
        if ($quantity === null || $quantity > self::MAX_QUANTITY) {
            $errors['quantity'] = __('metre.invalid_quantity');
            $quantity = 0;
        }
        $unitPrice = Decimal::parse($values['unit_price']);
        if ($unitPrice === null || $unitPrice > self::MAX_UNIT_PRICE) {
            $errors['unit_price'] = __('metre.invalid_unit_price');
            $unitPrice = 0;
        }
        $margin = $values['margin_percent'] === '' ? 0 : Decimal::parseRate($values['margin_percent']);
        if ($margin === null) {
            $errors['margin_percent'] = __('metre.invalid_margin');
            $margin = 0;
        }

        $values['quantity'] = Decimal::format($quantity);
        $values['unit_price'] = Decimal::format($unitPrice);
        $values['margin_percent'] = number_format($margin / 1000, 3, '.', '');

        return ['values' => $values, 'errors' => $errors];
    }

    /**
     * Valide un article de la bibliothèque de prix.
     *
     * @param array<string,mixed> $input
     * @return array{values:array<string,mixed>,errors:array<string,string>}
     */
    public function validateLibrary(array $input): array
    {
        $values = [
            'code' => trim(self::string($input['code'] ?? '')),
            'designation' => trim(self::string($input['designation'] ?? '')),
            'unit' => self::string($input['unit'] ?? 'u'),
            'unit_price' => trim(self::string($input['unit_price'] ?? '')),
            'category' => trim(self::string($input['category'] ?? '')),
            'is_active' => self::string($input['is_active'] ?? '') === '1' ? 1 : 0,
        ];
        $errors = [];

        if (!in_array($values['unit'], self::UNITS, true)) {
            $values['unit'] = 'u';
        }
        $length = mb_strlen($values['designation'], 'UTF-8');
        if ($length < 2 || $length > 180) {
            $errors['designation'] = __('metre.invalid_designation');
        }
        if (mb_strlen($values['code'], 'UTF-8') > 40) {
            $errors['code'] = __('metre.invalid_code');
        }
        if (mb_strlen($values['category'], 'UTF-8') > 80) {
            $values['category'] = mb_substr($values['category'], 0, 80, 'UTF-8');
        }
        $unitPrice = Decimal::parse($values['unit_price']);
        if ($unitPrice === null || $unitPrice > self::MAX_UNIT_PRICE) {
            $errors['unit_price'] = __('metre.invalid_unit_price');
            $unitPrice = 0;
        }
        $values['unit_price'] = Decimal::format($unitPrice);

        return ['values' => $values, 'errors' => $errors];
    }

    /**
     * Décompose un poste (valeurs DECIMAL de la base) en montants entiers.
     *
     * @param array<string,mixed> $poste
     * @return array<string,mixed> Poste enrichi de `total_millimes` et `margin_millimes`.
     */
    public function enrichPoste(array $poste): array
    {
        $quantity = Decimal::parse($poste['quantity'] ?? '0') ?? 0;
        $unitPrice = Decimal::parse($poste['unit_price'] ?? '0') ?? 0;
        $marginRate = Decimal::parseRate($poste['margin_percent'] ?? '0') ?? 0;
        $total = Decimal::multiply($quantity, $unitPrice);

        $poste['quantity_millimes'] = $quantity;
        $poste['unit_price_millimes'] = $unitPrice;
        $poste['total_millimes'] = $total;
        $poste['margin_millimes'] = Decimal::percentOf($total, $marginRate);
        // Valeurs canoniques : les vues affichent toujours trois décimales, même si la base
        // ou un test fournit « 12.5 ».
        $poste['quantity'] = Decimal::format($quantity);
        $poste['unit_price'] = Decimal::format($unitPrice);
        $poste['total'] = Decimal::format($total);
        $poste['margin_amount'] = Decimal::format($poste['margin_millimes']);

        return $poste;
    }

    /**
     * Totaux par lot et totaux généraux.
     *
     * @param array<int,array<string,mixed>> $lots
     * @param array<int,array<int,array<string,mixed>>> $postesByLot
     * @return array<string,mixed>
     */
    public function buildSummary(array $lots, array $postesByLot): array
    {
        $summaryLots = [];
        $total = 0;
        $margin = 0;
        $count = 0;
        $hasMargin = false;

        foreach ($lots as $lot) {
            $lotId = (int) $lot['id'];
            $lines = [];
            $lotTotal = 0;
            $lotMargin = 0;
            foreach ($postesByLot[$lotId] ?? [] as $poste) {
                $enriched = $this->enrichPoste($poste);
                $lotTotal += (int) $enriched['total_millimes'];
                $lotMargin += (int) $enriched['margin_millimes'];
                if ((int) $enriched['margin_millimes'] > 0) {
                    $hasMargin = true;
                }
                $lines[] = $enriched;
                $count++;
            }
            $summaryLots[] = [
                'lot' => $lot,
                'postes' => $lines,
                'total_millimes' => $lotTotal,
                'total' => Decimal::format($lotTotal),
                'margin_millimes' => $lotMargin,
                'margin_amount' => Decimal::format($lotMargin),
            ];
            $total += $lotTotal;
            $margin += $lotMargin;
        }

        return [
            'lots' => $summaryLots,
            'postes_count' => $count,
            'lots_count' => count($lots),
            'total_millimes' => $total,
            'total' => Decimal::format($total),
            'margin_millimes' => $margin,
            'margin_amount' => Decimal::format($margin),
            'cost_millimes' => max(0, $total - $margin),
            'cost' => Decimal::format(max(0, $total - $margin)),
            'has_margin' => $hasMargin,
        ];
    }

    /** Libellé traduit d’une unité, avec repli sur le code stocké. */
    public static function unitLabel(string $unit): string
    {
        return in_array($unit, self::UNITS, true) ? __('metre.unit.' . $unit) : $unit;
    }

    private static function integer(mixed $value, int $max, int $default): int
    {
        $text = self::string($value);
        if (!ctype_digit($text) || (int) $text > $max) {
            return $default;
        }

        return (int) $text;
    }

    private static function positiveId(mixed $value): ?int
    {
        $text = self::string($value);

        return ctype_digit($text) && (int) $text > 0 ? (int) $text : null;
    }

    private static function string(mixed $value): string
    {
        return is_string($value) || is_int($value) ? trim((string) $value) : '';
    }
}
