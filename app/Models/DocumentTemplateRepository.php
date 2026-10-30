<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use PDO;
use Throwable;

/** Modèles éditables de contenu et de présentation des documents. */
final class DocumentTemplateRepository
{
    private PDO $pdo;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? Database::connection();
    }

    /** @return array<int,array<string,mixed>> */
    public function all(bool $activeOnly = false): array
    {
        $sql = 'SELECT id, template_key, document_type, locale, name, title, terms_text, legal_notice,
                       validity_days, payment_terms_days, is_default, is_active, created_at, updated_at
                FROM document_templates';
        if ($activeOnly) {
            $sql .= ' WHERE is_active = 1';
        }
        $sql .= ' ORDER BY document_type, locale, is_default DESC, name, id';
        return array_map(self::normalize(...), $this->pdo->query($sql)->fetchAll());
    }

    /** @return array<int,array<string,mixed>> */
    public function forDocument(string $type, string $locale): array
    {
        $statement = $this->pdo->prepare(
            'SELECT id, template_key, document_type, locale, name, title, terms_text, legal_notice,
                    validity_days, payment_terms_days, is_default, is_active
             FROM document_templates
             WHERE document_type = :document_type AND locale = :locale AND is_active = 1
             ORDER BY is_default DESC, name, id'
        );
        $statement->execute(['document_type' => $type, 'locale' => $locale]);
        return array_map(self::normalize(...), $statement->fetchAll());
    }

    /** @return array<string,mixed>|null */
    public function findById(int $id): ?array
    {
        $statement = $this->pdo->prepare(
            'SELECT id, template_key, document_type, locale, name, title, terms_text, legal_notice,
                    validity_days, payment_terms_days, is_default, is_active
             FROM document_templates WHERE id = :id LIMIT 1'
        );
        $statement->execute(['id' => $id]);
        $row = $statement->fetch();
        return is_array($row) ? self::normalize($row) : null;
    }

    /** @return array<string,mixed>|null */
    public function findDefault(string $type, string $locale): ?array
    {
        $statement = $this->pdo->prepare(
            'SELECT id, template_key, document_type, locale, name, title, terms_text, legal_notice,
                    validity_days, payment_terms_days, is_default, is_active
             FROM document_templates
             WHERE document_type = :document_type AND locale = :locale AND is_active = 1
             ORDER BY is_default DESC, id LIMIT 1'
        );
        $statement->execute(['document_type' => $type, 'locale' => $locale]);
        $row = $statement->fetch();
        return is_array($row) ? self::normalize($row) : null;
    }

    /** @param array<string,mixed> $values */
    public function create(array $values, ?int $createdBy): int
    {
        $ownsTransaction = !$this->pdo->inTransaction();
        if ($ownsTransaction) {
            $this->pdo->beginTransaction();
        }
        try {
            $statement = $this->pdo->prepare(
                'INSERT INTO document_templates (
                    template_key, document_type, locale, name, title, terms_text, legal_notice,
                    validity_days, payment_terms_days, is_default, is_active, created_by
                 ) VALUES (
                    :template_key, :document_type, :locale, :name, :title, :terms_text, :legal_notice,
                    :validity_days, :payment_terms_days, 0, :is_active, :created_by
                 )'
            );
            $statement->execute(self::parameters($values, $createdBy));
            $id = (int) $this->pdo->lastInsertId();
            if (!empty($values['is_default'])) {
                $this->setDefault($id, (string) $values['document_type'], (string) $values['locale']);
            }
            if ($ownsTransaction) {
                $this->pdo->commit();
            }
            return $id;
        } catch (Throwable $error) {
            if ($ownsTransaction && $this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $error;
        }
    }

    /** @param array<string,mixed> $values */
    public function update(int $id, array $values): bool
    {
        $ownsTransaction = !$this->pdo->inTransaction();
        if ($ownsTransaction) {
            $this->pdo->beginTransaction();
        }
        try {
            $statement = $this->pdo->prepare(
                'UPDATE document_templates SET document_type = :document_type, locale = :locale,
                    name = :name, title = :title, terms_text = :terms_text, legal_notice = :legal_notice,
                    validity_days = :validity_days, payment_terms_days = :payment_terms_days,
                    is_active = :is_active, is_default = 0
                 WHERE id = :id'
            );
            $parameters = self::parameters($values, null);
            unset($parameters['template_key'], $parameters['created_by']);
            $parameters['id'] = $id;
            $statement->execute($parameters);
            if ($statement->rowCount() === 0 && $this->findById($id) === null) {
                if ($ownsTransaction) {
                    $this->pdo->rollBack();
                }
                return false;
            }
            if (!empty($values['is_default'])) {
                $this->setDefault($id, (string) $values['document_type'], (string) $values['locale']);
            }
            if ($ownsTransaction) {
                $this->pdo->commit();
            }
            return true;
        } catch (Throwable $error) {
            if ($ownsTransaction && $this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $error;
        }
    }

    private function setDefault(int $id, string $type, string $locale): void
    {
        $clear = $this->pdo->prepare(
            'UPDATE document_templates SET is_default = 0
             WHERE document_type = :document_type AND locale = :locale AND id <> :id'
        );
        $clear->execute(['document_type' => $type, 'locale' => $locale, 'id' => $id]);
        $set = $this->pdo->prepare(
            'UPDATE document_templates SET is_default = 1, is_active = 1 WHERE id = :id'
        );
        $set->execute(['id' => $id]);
    }

    /** @param array<string,mixed> $values
     *  @return array<string,mixed>
     */
    private static function parameters(array $values, ?int $createdBy): array
    {
        return [
            'template_key' => $values['template_key'] ?? 'template_' . bin2hex(random_bytes(12)),
            'document_type' => $values['document_type'],
            'locale' => $values['locale'],
            'name' => $values['name'],
            'title' => $values['title'],
            'terms_text' => $values['terms_text'] !== '' ? $values['terms_text'] : null,
            'legal_notice' => $values['legal_notice'] !== '' ? $values['legal_notice'] : null,
            'validity_days' => $values['validity_days'],
            'payment_terms_days' => $values['payment_terms_days'],
            'is_active' => !empty($values['is_active']) ? 1 : 0,
            'created_by' => $createdBy,
        ];
    }

    /** @param array<string,mixed> $row
     *  @return array<string,mixed>
     */
    private static function normalize(array $row): array
    {
        $row['id'] = (int) $row['id'];
        $row['validity_days'] = (int) $row['validity_days'];
        $row['payment_terms_days'] = (int) $row['payment_terms_days'];
        $row['is_default'] = (bool) $row['is_default'];
        $row['is_active'] = (bool) $row['is_active'];
        return $row;
    }
}
