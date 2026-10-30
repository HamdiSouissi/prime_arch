<?php
declare(strict_types=1);

namespace Modules\RegistreCourrier\Models;

use App\Core\Database;
use App\Models\SearchRepository;
use PDO;
use Throwable;

/**
 * Accès PDO au registre du courrier.
 *
 * Le module ne possède que ses tables : `courrier_registre`, `courrier_reglages` et
 * `courrier_compteurs`. Le cœur (`projects`, `clients`, `users`) est uniquement lu.
 */
final class CourrierRepository
{
    /** Réglages de numérotation par défaut : le cabinet peut tout changer. */
    public const DEFAULT_SETTINGS = [
        'prefix_in' => 'CE',
        'prefix_out' => 'CS',
        'separator' => '-',
        'digits' => 3,
    ];

    private PDO $pdo;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? Database::connection();
    }

    // ------------------------------------------------------------------
    // Numérotation
    // ------------------------------------------------------------------

    /**
     * Réglages de numérotation du cabinet (préfixes, séparateur, nombre de chiffres).
     *
     * @return array{prefix_in:string,prefix_out:string,separator:string,digits:int}
     */
    public function settings(): array
    {
        $row = $this->pdo->query('SELECT prefix_in, prefix_out, separator, digits FROM courrier_reglages WHERE id = 1')->fetch();
        if (!is_array($row)) {
            return self::DEFAULT_SETTINGS;
        }

        return [
            'prefix_in' => (string) $row['prefix_in'],
            'prefix_out' => (string) $row['prefix_out'],
            'separator' => (string) $row['separator'],
            'digits' => (int) $row['digits'],
        ];
    }

    /**
     * Enregistre les réglages de numérotation (une seule ligne, créée au besoin).
     *
     * @param array{prefix_in:string,prefix_out:string,separator:string,digits:int} $values
     */
    public function saveSettings(array $values): void
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO courrier_reglages (id, prefix_in, prefix_out, separator, digits)
             VALUES (1, :prefix_in, :prefix_out, :separator, :digits)
             ON DUPLICATE KEY UPDATE prefix_in = VALUES(prefix_in), prefix_out = VALUES(prefix_out),
                 separator = VALUES(separator), digits = VALUES(digits)'
        );
        $statement->execute([
            'prefix_in' => $values['prefix_in'],
            'prefix_out' => $values['prefix_out'],
            'separator' => $values['separator'],
            'digits' => $values['digits'],
        ]);
    }

    /**
     * Compose un numéro à partir des réglages : `PRÉFIXE{séparateur}ANNÉE{séparateur}SÉQUENCE`.
     *
     * @param array{prefix_in:string,prefix_out:string,separator:string,digits:int} $settings
     */
    public static function buildNumber(array $settings, string $sens, int $year, int $sequence): string
    {
        $prefix = $sens === 'in' ? (string) $settings['prefix_in'] : (string) $settings['prefix_out'];
        $separator = (string) $settings['separator'];
        $digits = max(1, min((int) $settings['digits'], 8));

        return $prefix . $separator . $year . $separator . str_pad((string) $sequence, $digits, '0', STR_PAD_LEFT);
    }

    /**
     * Compteurs connus pour une année, un par sens.
     *
     * @return array<string,int>
     */
    public function counters(int $year): array
    {
        $statement = $this->pdo->prepare('SELECT sens, dernier FROM courrier_compteurs WHERE annee = ?');
        $statement->execute([$year]);
        $counters = ['in' => 0, 'out' => 0];
        foreach ($statement->fetchAll() as $row) {
            $counters[(string) $row['sens']] = (int) $row['dernier'];
        }

        return $counters;
    }

    /**
     * Fixe le dernier numéro utilisé pour un sens et une année.
     *
     * Utilisé pour reprendre une numérotation existante ; l’opération ne supprime
     * jamais de courrier et ne modifie aucun numéro déjà attribué.
     */
    public function saveCounter(string $sens, int $year, int $last): void
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO courrier_compteurs (sens, annee, dernier) VALUES (?, ?, ?)
             ON DUPLICATE KEY UPDATE dernier = VALUES(dernier)'
        );
        $statement->execute([$sens, $year, max(0, $last)]);
    }

    /**
     * Réserve le prochain numéro de séquence pour un sens et une année.
     *
     * Le compteur est la source de vérité ; s’il n’existe pas encore, il est initialisé
     * à partir du registre afin de ne jamais réattribuer un numéro déjà utilisé.
     */
    public function reserveSequence(string $sens, int $year): int
    {
        $select = $this->pdo->prepare('SELECT dernier FROM courrier_compteurs WHERE sens = ? AND annee = ? FOR UPDATE');
        $select->execute([$sens, $year]);
        $last = $select->fetchColumn();

        if ($last === false) {
            $max = $this->pdo->prepare('SELECT COALESCE(MAX(sequence), 0) FROM courrier_registre WHERE sens = ? AND annee = ?');
            $max->execute([$sens, $year]);
            $last = (int) $max->fetchColumn();
            $insert = $this->pdo->prepare('INSERT INTO courrier_compteurs (sens, annee, dernier) VALUES (?, ?, ?)');
            $insert->execute([$sens, $year, $last]);
        }

        $next = (int) $last + 1;
        $update = $this->pdo->prepare('UPDATE courrier_compteurs SET dernier = ? WHERE sens = ? AND annee = ?');
        $update->execute([$next, $sens, $year]);

        return $next;
    }

    // ------------------------------------------------------------------
    // Registre
    // ------------------------------------------------------------------

    /**
     * @param array{q:string,sens:string,statut:string,annee:string,projet:string} $filters
     * @return array{items:array<int,array<string,mixed>>,total:int}
     */
    public function paginate(array $filters, int $limit = 20, int $offset = 0): array
    {
        [$where, $parameters] = $this->listConditions($filters);

        $count = $this->pdo->prepare('SELECT COUNT(*) FROM courrier_registre c' . $where);
        $count->execute($parameters);
        $total = (int) $count->fetchColumn();

        $statement = $this->pdo->prepare(
            'SELECT c.id, c.sens, c.annee, c.sequence, c.numero, c.date_courrier, c.correspondant,
                    c.objet, c.reference, c.mode, c.statut, c.echeance, c.project_id, c.client_id,
                    p.name AS project_name, p.reference AS project_reference,
                    cl.display_name AS client_name
             FROM courrier_registre c
             LEFT JOIN projects p ON p.id = c.project_id
             LEFT JOIN clients cl ON cl.id = c.client_id' . $where
            . ' ORDER BY c.date_courrier DESC, c.sequence DESC, c.id DESC LIMIT ' . max(1, $limit)
            . ' OFFSET ' . max(0, $offset)
        );
        $statement->execute($parameters);

        return ['items' => $statement->fetchAll(), 'total' => $total];
    }

    /**
     * Lignes complètes pour l’export CSV (même filtre que la liste).
     *
     * @param array{q:string,sens:string,statut:string,annee:string,projet:string} $filters
     * @return array<int,array<string,mixed>>
     */
    public function exportRows(array $filters): array
    {
        [$where, $parameters] = $this->listConditions($filters);
        $statement = $this->pdo->prepare(
            'SELECT c.numero, c.sens, c.date_courrier, c.correspondant, c.objet, c.reference,
                    c.mode, c.statut, c.echeance, p.name AS project_name, cl.display_name AS client_name
             FROM courrier_registre c
             LEFT JOIN projects p ON p.id = c.project_id
             LEFT JOIN clients cl ON cl.id = c.client_id' . $where
            . ' ORDER BY c.annee DESC, c.sens, c.sequence'
        );
        $statement->execute($parameters);

        return $statement->fetchAll();
    }

    /**
     * Registre d’une année, éventuellement limité à un sens, dans l’ordre chronologique.
     *
     * @return array<int,array<string,mixed>>
     */
    public function registerRows(int $year, string $sens = ''): array
    {
        $sql = 'SELECT c.id, c.sens, c.sequence, c.numero, c.date_courrier, c.correspondant,
                       c.objet, c.reference, c.mode, c.statut, p.reference AS project_reference,
                       cl.display_name AS client_name
                FROM courrier_registre c
                LEFT JOIN projects p ON p.id = c.project_id
                LEFT JOIN clients cl ON cl.id = c.client_id
                WHERE c.annee = :annee';
        $parameters = ['annee' => $year];
        if ($sens === 'in' || $sens === 'out') {
            $sql .= ' AND c.sens = :sens';
            $parameters['sens'] = $sens;
        }
        $statement = $this->pdo->prepare($sql . ' ORDER BY c.sens, c.sequence');

        $statement->execute($parameters);

        return $statement->fetchAll();
    }

    /**
     * Indicateurs du registre, toutes années confondues.
     *
     * @return array{todo:int,in_progress:int,done:int,archived:int,overdue:int,total:int}
     */
    public function statistics(): array
    {
        $row = $this->pdo->query(
            "SELECT
                SUM(CASE WHEN statut = 'todo' THEN 1 ELSE 0 END) AS todo,
                SUM(CASE WHEN statut = 'in_progress' THEN 1 ELSE 0 END) AS in_progress,
                SUM(CASE WHEN statut = 'done' THEN 1 ELSE 0 END) AS done,
                SUM(CASE WHEN statut = 'archived' THEN 1 ELSE 0 END) AS archived,
                SUM(CASE WHEN statut IN ('todo', 'in_progress') AND echeance IS NOT NULL AND echeance < CURDATE()
                         THEN 1 ELSE 0 END) AS overdue,
                COUNT(*) AS total
             FROM courrier_registre"
        )->fetch();

        $stats = ['todo' => 0, 'in_progress' => 0, 'done' => 0, 'archived' => 0, 'overdue' => 0, 'total' => 0];
        if (is_array($row)) {
            foreach (array_keys($stats) as $key) {
                $stats[$key] = (int) ($row[$key] ?? 0);
            }
        }

        return $stats;
    }

    /** @return array<int,int> */
    public function years(): array
    {
        $rows = $this->pdo->query('SELECT DISTINCT annee FROM courrier_registre ORDER BY annee DESC')->fetchAll();

        return array_map(static fn (array $row): int => (int) $row['annee'], $rows);
    }

    /** @return array<string,mixed>|null */
    public function find(int $id): ?array
    {
        $statement = $this->pdo->prepare(
            'SELECT c.*, p.name AS project_name, p.reference AS project_reference, p.city AS project_city,
                    cl.id AS client_id, cl.display_name AS client_name, u.name AS author_name
             FROM courrier_registre c
             LEFT JOIN projects p ON p.id = c.project_id
             LEFT JOIN clients cl ON cl.id = c.client_id
             LEFT JOIN users u ON u.id = c.created_by
             WHERE c.id = ? LIMIT 1'
        );
        $statement->execute([$id]);
        $row = $statement->fetch();

        return is_array($row) ? $row : null;
    }

    /**
     * Crée une ligne et réserve son numéro dans la même transaction.
     *
     * @param array<string,mixed> $values
     */
    public function create(array $values, ?int $authorId): int
    {
        $started = !$this->pdo->inTransaction();
        if ($started) {
            $this->pdo->beginTransaction();
        }

        try {
            $year = (int) substr((string) $values['date_courrier'], 0, 4);
            $sequence = $this->reserveSequence((string) $values['sens'], $year);
            $numero = self::buildNumber($this->settings(), (string) $values['sens'], $year, $sequence);

            $statement = $this->pdo->prepare(
                'INSERT INTO courrier_registre
                    (sens, annee, sequence, numero, date_courrier, correspondant, objet, reference,
                     mode, statut, project_id, client_id, echeance, notes, created_by)
                 VALUES
                    (:sens, :annee, :sequence, :numero, :date_courrier, :correspondant, :objet, :reference,
                     :mode, :statut, :project_id, :client_id, :echeance, :notes, :created_by)'
            );
            $statement->execute([
                'sens' => $values['sens'],
                'annee' => $year,
                'sequence' => $sequence,
                'numero' => $numero,
                'date_courrier' => $values['date_courrier'],
                'correspondant' => $values['correspondant'],
                'objet' => $values['objet'],
                'reference' => $values['reference'],
                'mode' => $values['mode'],
                'statut' => $values['statut'],
                'project_id' => $values['project_id'],
                'client_id' => $values['client_id'],
                'echeance' => $values['echeance'],
                'notes' => $values['notes'],
                'created_by' => $authorId,
            ]);
            $id = (int) $this->pdo->lastInsertId();

            if ($started) {
                $this->pdo->commit();
            }
        } catch (Throwable $error) {
            if ($started && $this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $error;
        }

        return $id;
    }

    /**
     * Met à jour une fiche. Ni le numéro, ni l’année, ni la séquence ne changent :
     * un numéro attribué reste acquis même si la date est corrigée.
     *
     * @param array<string,mixed> $values
     */
    public function update(int $id, array $values): void
    {
        $statement = $this->pdo->prepare(
            'UPDATE courrier_registre SET
                sens = :sens,
                date_courrier = :date_courrier,
                correspondant = :correspondant,
                objet = :objet,
                reference = :reference,
                mode = :mode,
                statut = :statut,
                project_id = :project_id,
                client_id = :client_id,
                echeance = :echeance,
                notes = :notes
             WHERE id = :id'
        );
        $statement->execute([
            'sens' => $values['sens'],
            'date_courrier' => $values['date_courrier'],
            'correspondant' => $values['correspondant'],
            'objet' => $values['objet'],
            'reference' => $values['reference'],
            'mode' => $values['mode'],
            'statut' => $values['statut'],
            'project_id' => $values['project_id'],
            'client_id' => $values['client_id'],
            'echeance' => $values['echeance'],
            'notes' => $values['notes'],
            'id' => $id,
        ]);
    }

    public function updateStatus(int $id, string $status): void
    {
        $statement = $this->pdo->prepare('UPDATE courrier_registre SET statut = ? WHERE id = ?');
        $statement->execute([$status, $id]);
    }

    /**
     * Courriers correspondant à la recherche globale du noyau.
     *
     * @return array<int,array<string,mixed>>
     */
    public function search(string $query, int $limit = SearchRepository::LIMIT): array
    {
        $statement = $this->pdo->prepare(
            'SELECT c.id, c.numero, c.sens, c.objet, c.correspondant, c.date_courrier,
                    p.name AS project_name
             FROM courrier_registre c
             LEFT JOIN projects p ON p.id = c.project_id
             WHERE ' . self::like('c.numero', 'numero')
            . ' OR ' . self::like('c.correspondant', 'correspondant')
            . ' OR ' . self::like('c.objet', 'objet')
            . ' OR ' . self::like('c.reference', 'reference')
            . ' ORDER BY c.date_courrier DESC, c.sequence DESC
             LIMIT ' . max(1, min($limit, SearchRepository::LIMIT))
        );
        $statement->execute([
            'numero' => SearchRepository::likeTerm($query),
            'correspondant' => SearchRepository::likeTerm($query),
            'objet' => SearchRepository::likeTerm($query),
            'reference' => SearchRepository::likeTerm($query),
        ]);

        return $statement->fetchAll();
    }

    // ------------------------------------------------------------------
    // Sélections et passerelles
    // ------------------------------------------------------------------

    /** @return array<int,array<string,mixed>> */
    public function projectsForSelect(): array
    {
        return $this->pdo->query(
            'SELECT p.id, p.name, p.reference, p.client_id, c.display_name AS client_name
             FROM projects p
             LEFT JOIN clients c ON c.id = p.client_id
             ORDER BY p.name'
        )->fetchAll();
    }

    /** @return array<int,array<string,mixed>> */
    public function clientsForSelect(): array
    {
        return $this->pdo->query('SELECT id, display_name FROM clients ORDER BY display_name')->fetchAll();
    }

    public function projectExists(int $id): bool
    {
        $statement = $this->pdo->prepare('SELECT 1 FROM projects WHERE id = ? LIMIT 1');
        $statement->execute([$id]);

        return $statement->fetchColumn() !== false;
    }

    public function clientExists(int $id): bool
    {
        $statement = $this->pdo->prepare('SELECT 1 FROM clients WHERE id = ? LIMIT 1');
        $statement->execute([$id]);

        return $statement->fetchColumn() !== false;
    }

    /** @return array<string,mixed>|null */
    public function projectRow(int $id): ?array
    {
        $statement = $this->pdo->prepare('SELECT id, name, reference, client_id FROM projects WHERE id = ? LIMIT 1');
        $statement->execute([$id]);
        $row = $statement->fetch();

        return is_array($row) ? $row : null;
    }

    /**
     * Résumé du courrier d’un projet (passerelle de la fiche projet).
     *
     * @return array{total:int,open:int}|null
     */
    public function projectSummary(int $projectId): ?array
    {
        return $this->summaryFor('project_id', $projectId);
    }

    /**
     * Résumé du courrier d’un client (passerelle de la fiche client).
     *
     * @return array{total:int,open:int}|null
     */
    public function clientSummary(int $clientId): ?array
    {
        return $this->summaryFor('client_id', $clientId);
    }

    /** @return array{total:int,open:int}|null */
    private function summaryFor(string $column, int $id): ?array
    {
        $sql = 'SELECT COUNT(*) AS total,
                       SUM(CASE WHEN statut IN (\'todo\', \'in_progress\') THEN 1 ELSE 0 END) AS open_count
                FROM courrier_registre WHERE ' . $column . ' = ?';
        $statement = $this->pdo->prepare($sql);
        $statement->execute([$id]);
        $row = $statement->fetch();
        if (!is_array($row) || (int) $row['total'] === 0) {
            return null;
        }

        return ['total' => (int) $row['total'], 'open' => (int) ($row['open_count'] ?? 0)];
    }

    /**
     * Clause `LIKE` avec échappement explicite.
     *
     * Le caractère d’échappement est l’antislash littéral, comme dans le noyau
     * (`SearchRepository::likeTerm()` échappe lui aussi avec des antislashs).
     */
    private static function like(string $column, string $parameter): string
    {
        return $column . ' LIKE :' . $parameter . " ESCAPE '\\\\'";
    }

    /**
     * @param array{q:string,sens:string,statut:string,annee:string,projet:string} $filters
     * @return array{0:string,1:array<string,mixed>}
     */
    private function listConditions(array $filters): array
    {
        $clauses = [];
        $parameters = [];

        if ($filters['sens'] !== '') {
            $clauses[] = 'c.sens = :sens';
            $parameters['sens'] = $filters['sens'];
        }
        if ($filters['statut'] !== '') {
            $clauses[] = 'c.statut = :statut';
            $parameters['statut'] = $filters['statut'];
        }
        if ($filters['annee'] !== '') {
            $clauses[] = 'c.annee = :annee';
            $parameters['annee'] = (int) $filters['annee'];
        }
        if ($filters['projet'] !== '') {
            $clauses[] = 'c.project_id = :projet';
            $parameters['projet'] = (int) $filters['projet'];
        }
        if ($filters['q'] !== '') {
            $clauses[] = '(' . self::like('c.numero', 'q_numero')
                . ' OR ' . self::like('c.correspondant', 'q_correspondant')
                . ' OR ' . self::like('c.objet', 'q_objet')
                . ' OR ' . self::like('c.reference', 'q_reference') . ')';
            $term = SearchRepository::likeTerm($filters['q']);
            $parameters['q_numero'] = $term;
            $parameters['q_correspondant'] = $term;
            $parameters['q_objet'] = $term;
            $parameters['q_reference'] = $term;
        }

        return [$clauses === [] ? '' : ' WHERE ' . implode(' AND ', $clauses), $parameters];
    }
}
