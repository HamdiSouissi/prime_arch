<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use PDO;
use Throwable;

/** Journalise les changements utiles à la sécurité sans conserver les valeurs métier. */
final class AuditLogger
{
    private const SENSITIVE_DETAIL_KEY_PATTERN = '/password|token|secret|credential|email|phone|address|tax|rib|iban|bank|account|'
        . 'note|description|content|ip|reference|amount|rate|cost|price|budget|fee|total|currency|'
        . 'financial|salary|wage|vat|withholding|stamp|matricule|fiscal|document_number|name|label|title/i';

    public function __construct(private ?PDO $pdo = null)
    {
    }

    /** @param array<string,mixed> $details */
    public function record(
        string $event,
        string $subjectType,
        int|string|null $subjectId = null,
        array $details = [],
        ?int $actorId = null
    ): void {
        if (!preg_match('/^[a-z][a-z0-9_.-]{1,79}$/', $event)
            || !preg_match('/^[a-z][a-z0-9_.-]{1,79}$/', $subjectType)
        ) {
            return;
        }

        try {
            $statement = ($this->pdo ?? Database::connection())->prepare(
                'INSERT INTO audit_logs (actor_id, event_key, subject_type, subject_id, details)
                 VALUES (:actor_id, :event_key, :subject_type, :subject_id, :details)'
            );
            $statement->execute([
                'actor_id' => $actorId,
                'event_key' => $event,
                'subject_type' => $subjectType,
                'subject_id' => $subjectId === null ? null : substr((string) $subjectId, 0, 100),
                'details' => json_encode(
                    self::sanitizeDetails($details),
                    JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
                ),
            ]);
        } catch (Throwable $error) {
            // Une panne du journal ne doit pas interrompre une opération métier déjà autorisée.
            error_log('Écriture du journal d’audit impossible : ' . get_class($error));
        }
    }

    /** @param array<string,mixed> $details
     *  @return array<string,scalar|null>
     */
    public static function sanitizeDetails(array $details): array
    {
        $safe = [];
        foreach ($details as $key => $value) {
            if (!is_string($key)
                || !preg_match('/^[a-z][a-z0-9_]{0,39}$/i', $key)
                || preg_match(self::SENSITIVE_DETAIL_KEY_PATTERN, $key)
            ) {
                continue;
            }
            if (is_bool($value) || is_int($value) || $value === null) {
                $safe[$key] = $value;
            } elseif (is_float($value) && is_finite($value)) {
                $safe[$key] = (string) $value;
            } elseif (is_string($value)) {
                $safe[$key] = mb_substr($value, 0, 120, 'UTF-8');
            }
        }
        return $safe;
    }
}
