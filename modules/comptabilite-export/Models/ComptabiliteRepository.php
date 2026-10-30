<?php
declare(strict_types=1);

namespace Modules\ComptabiliteExport\Models;

use App\Core\Database;
use PDO;

/**
 * Lectures comptables du noyau.
 *
 * Le module ne dispose d’aucune table et n’exécute aucune écriture : il lit uniquement les
 * documents émis et leurs règlements, pour construire des journaux exportables.
 */
final class ComptabiliteRepository
{
    private PDO $pdo;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? Database::connection();
    }

    /**
     * Factures et avoirs émis sur la période, par date d’émission.
     *
     * @return array<int,array<string,mixed>>
     */
    public function documents(string $from, string $to): array
    {
        $statement = $this->pdo->prepare(
            "SELECT d.id, d.document_number, d.document_type, d.issue_date, d.tax_rate,
                    d.stamp_amount, d.withholding_amount, d.subtotal, d.tax_amount, d.total_amount,
                    c.display_name AS client_name, c.tax_id AS client_tax_id,
                    p.name AS project_name, p.reference AS project_reference
             FROM documents d
             INNER JOIN clients c ON c.id = d.client_id
             LEFT JOIN projects p ON p.id = d.project_id
             WHERE d.document_type IN ('invoice', 'credit_note')
               AND d.status = 'issued'
               AND d.issue_date BETWEEN ? AND ?
             ORDER BY d.issue_date, d.document_number, d.id"
        );
        $statement->execute([$from, $to]);

        return $statement->fetchAll();
    }

    /**
     * Règlements enregistrés sur la période, par date de paiement.
     *
     * @return array<int,array<string,mixed>>
     */
    public function payments(string $from, string $to): array
    {
        $statement = $this->pdo->prepare(
            "SELECT pay.id, pay.document_id, pay.paid_on, pay.amount, pay.payment_method, pay.reference,
                    d.document_number, d.document_type,
                    c.display_name AS client_name
             FROM document_payments pay
             INNER JOIN documents d ON d.id = pay.document_id
             INNER JOIN clients c ON c.id = d.client_id
             WHERE d.status = 'issued'
               AND d.document_type IN ('invoice', 'credit_note')
               AND pay.paid_on BETWEEN ? AND ?
             ORDER BY pay.paid_on, pay.id"
        );
        $statement->execute([$from, $to]);

        return $statement->fetchAll();
    }

    /** @return array<int,int> Années présentes dans les documents émis et les règlements. */
    public function years(): array
    {
        $rows = $this->pdo->query(
            "SELECT YEAR(d.issue_date) AS year_value
             FROM documents d
             WHERE d.document_type IN ('invoice', 'credit_note')
               AND d.status = 'issued'
               AND d.issue_date IS NOT NULL
             UNION
             SELECT YEAR(pay.paid_on) AS year_value
             FROM document_payments pay
             WHERE pay.paid_on IS NOT NULL
             ORDER BY year_value DESC"
        )->fetchAll();

        $years = [];
        foreach ($rows as $row) {
            $year = (int) $row['year_value'];
            if ($year > 0) {
                $years[] = $year;
            }
        }

        return $years;
    }
}
