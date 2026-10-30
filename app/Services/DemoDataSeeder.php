<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Models\ClientRepository;
use App\Models\DocumentRepository;
use App\Models\GeographyRepository;
use App\Models\HourlyRateRepository;
use App\Models\ProjectRepository;
use App\Models\TimeEntryRepository;
use App\Models\UserRepository;
use PDO;
use RuntimeException;

/**
 * Écriture du jeu de démonstration dans une base migrée et préchargée.
 *
 * Principes :
 *  - réexécutable : chaque élément est recherché par sa clé naturelle (adresse électronique,
 *    nom, références de document) avant toute insertion ;
 *  - aucun contournement des règles métier : clients, projets et documents passent par les
 *    services de validation du noyau, les factures par la vraie émission (numérotation,
 *    instantané, PDF) ;
 *  - aucune donnée fiscale inventée : TVA, timbre et retenue proviennent des modèles et des
 *    réglages du cabinet ;
 *  - pas de transaction englobante : l'émission d'un document ouvre la sienne, et chaque
 *    étape reste donc idempotente prise séparément.
 */
final class DemoDataSeeder
{
    /** Tables interrogées pour reconnaître un élément déjà présent. */
    private const LOOKUP_TABLES = ['users', 'projects', 'time_entries', 'documents'];

    private PDO $pdo;
    private UserRepository $users;
    private ClientRepository $clients;
    private ProjectRepository $projects;
    private TimeEntryRepository $time;
    private HourlyRateRepository $rates;
    private GeographyRepository $geography;
    private DocumentRepository $documentRepository;
    private DocumentService $documents;
    private ClientService $clientRules;
    private ProjectService $projectRules;

    /** @var array<string,int> */
    private array $userIds = [];

    /** @var array<string,int> */
    private array $clientIds = [];

    /** @var array<string,int> */
    private array $projectIds = [];

    /** @var array<string,array<string,int>> */
    private array $assignmentIds = [];

    /** @var array<string,int> */
    private array $documentIds = [];

    /** @var array<string,array<string,int>> */
    private array $counts = [];

    /** @var array<int,string> */
    private array $warnings = [];

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? Database::connection();
        $this->users = new UserRepository($this->pdo);
        $this->clients = new ClientRepository($this->pdo);
        $this->projects = new ProjectRepository($this->pdo);
        $this->time = new TimeEntryRepository($this->pdo);
        $this->rates = new HourlyRateRepository($this->pdo);
        $this->geography = new GeographyRepository($this->pdo);
        $this->documentRepository = new DocumentRepository($this->pdo);
        $this->documents = new DocumentService($this->pdo);
        $this->clientRules = new ClientService($this->geography);
        $this->projectRules = new ProjectService($this->clients, $this->geography);
    }

    /**
     * Exécute toutes les étapes et renvoie un compte rendu chiffré.
     *
     * Sauf `$force`, le seeder refuse une base qui contient déjà des comptes ou des clients
     * réels : la démonstration ne doit jamais s'ajouter au dossier d'un cabinet.
     *
     * @return array{version:int,today:string,steps:array<string,array{created:int,existing:int}>,warnings:array<int,string>}
     */
    public function run(string $password, ?string $today = null, bool $force = false): array
    {
        if (!$force) {
            $this->assertEmptyOfRealData();
        }
        $dataset = new DemoDataset($today);
        $this->counts = [];
        $this->warnings = [];
        $this->userIds = [];
        $this->clientIds = [];
        $this->projectIds = [];
        $this->assignmentIds = [];
        $this->documentIds = [];

        $actorId = $this->seedUsers($dataset, $password);
        $this->seedRates($dataset, $actorId);
        $this->seedClients($dataset, $actorId);
        $this->seedProjects($dataset, $actorId);
        $this->seedTimeEntries($dataset);
        $this->seedDocuments($dataset, $actorId);
        $this->recordAudit($actorId);

        return [
            'version' => DemoDataset::VERSION,
            'today' => $dataset->today(),
            'steps' => $this->counts,
            'warnings' => $this->warnings,
        ];
    }

    /** Refuse de mélanger la démonstration aux données réelles d'un cabinet. */
    private function assertEmptyOfRealData(): void
    {
        $found = [];
        foreach (['users' => 'compte(s)', 'clients' => 'client(s)'] as $table => $label) {
            $statement = $this->pdo->prepare(
                sprintf(
                    "SELECT COUNT(*) FROM %s WHERE email IS NOT NULL AND email <> '' AND email NOT LIKE :domain",
                    $table
                )
            );
            $statement->execute(['domain' => '%@' . DemoDataset::EMAIL_DOMAIN]);
            $count = (int) $statement->fetchColumn();
            if ($count > 0) {
                $found[] = $count . ' ' . $label;
            }
        }
        if ($found !== []) {
            throw new RuntimeException(
                'La base contient déjà des données réelles (' . implode(', ', $found) . ' hors domaine de '
                . 'démonstration). Utilisez une base de démonstration dédiée, ou --force si vous acceptez '
                . 'd’ajouter le jeu à ces données.'
            );
        }
    }

    private function seedUsers(DemoDataset $dataset, string $password): int
    {
        $hash = null;
        $actorId = 0;
        foreach ($dataset->users() as $user) {
            $id = $this->findId('users', ['email' => $user['email']]);
            if ($id === null) {
                if ($this->users->findRole($user['role']) === null) {
                    throw new RuntimeException('Rôle introuvable : ' . $user['role'] . '. Lancez php bin/seed.php.');
                }
                // Le coût Argon2id n'est payé que si un compte doit réellement être créé.
                $hash ??= AuthService::hashPassword($password);
                $id = $this->users->create($user['name'], $user['email'], $hash, $user['role'], $user['locale']);
                $this->bump('users', 'created');
            } else {
                $this->bump('users', 'existing');
            }
            $this->userIds[$user['key']] = $id;
            if ($actorId === 0 && $user['role'] === 'administrator') {
                $actorId = $id;
            }
        }

        return $actorId > 0 ? $actorId : (int) array_values($this->userIds)[0];
    }

    private function seedRates(DemoDataset $dataset, int $actorId): void
    {
        foreach ($dataset->rates() as $rate) {
            $userId = $this->userId($rate['user']);
            $date = $dataset->date($rate['effective_offset']);
            if ($this->rates->existsForDate($userId, $date)) {
                $this->bump('user_hourly_rates', 'existing');
                continue;
            }
            $this->rates->create([
                'user_id' => $userId,
                'effective_from' => $date,
                'cost_rate_per_hour' => $rate['rate'],
                'note' => $rate['note'],
            ], $actorId);
            $this->bump('user_hourly_rates', 'created');
        }
    }

    private function seedClients(DemoDataset $dataset, int $actorId): void
    {
        foreach ($dataset->clients() as $client) {
            $id = $this->findClientId($client);
            if ($id === null) {
                $input = [
                    'client_type' => $client['client_type'],
                    'display_name' => $client['display_name'],
                    'legal_name' => $client['legal_name'],
                    'tax_id' => $client['tax_id'],
                    'email' => $client['email'],
                    'phone' => $client['phone'],
                    'address_line' => $client['address_line'],
                    'status' => $client['status'],
                    'notes' => $client['notes'],
                ] + $this->place($client['governorate'], $client['delegation']);
                $validated = $this->clientRules->validate($input, []);
                if ($validated['errors'] !== []) {
                    throw new RuntimeException(sprintf(
                        'Client de démonstration invalide (%s) : %s',
                        $client['key'],
                        implode(', ', array_keys($validated['errors']))
                    ));
                }
                $id = $this->clients->create($validated['values'], $actorId);
                $this->bump('clients', 'created');
            } else {
                $this->bump('clients', 'existing');
            }
            $this->clientIds[$client['key']] = $id;

            foreach ($client['contacts'] as $contact) {
                if ($this->findContactId($id, $contact['full_name']) !== null) {
                    $this->bump('client_contacts', 'existing');
                    continue;
                }
                $this->clients->createContact($id, $contact);
                $this->bump('client_contacts', 'created');
            }
        }
    }

    private function seedProjects(DemoDataset $dataset, int $actorId): void
    {
        $phaseKeyById = [];
        foreach ($this->projects->phases() as $phase) {
            $phaseKeyById[$phase['id']] = $phase['phase_key'];
        }

        foreach ($dataset->projects() as $project) {
            $clientId = $this->clientId($project['client']);
            $deferredStatus = $project['status'] === 'completed' ? 'completed' : null;
            $id = $this->findId('projects', ['name' => $project['name'], 'client_id' => $clientId]);
            $input = [
                'client_id' => (string) $clientId,
                'name' => $project['name'],
                'project_type' => $project['project_type'],
                'description' => $project['description'],
                'status' => $deferredStatus === null ? $project['status'] : 'active',
                'address_line' => $project['address_line'],
                'start_date' => $dataset->date($project['start_offset']),
                'target_end_date' => $dataset->date($project['end_offset']),
                'budget_hours' => $project['budget_hours'],
                'fee_budget_amount' => $project['fee_budget_amount'],
            ] + $this->place($project['governorate'], $project['delegation']);

            $created = $id === null;
            if ($created) {
                $validated = $this->projectRules->validate($input, []);
                if ($validated['errors'] !== []) {
                    throw new RuntimeException(sprintf(
                        'Projet de démonstration invalide (%s) : %s',
                        $project['key'],
                        implode(', ', array_keys($validated['errors']))
                    ));
                }
                $id = $this->projects->create($validated['values'], $this->userId($project['created_by']));
                $this->bump('projects', 'created');
            } else {
                $this->bump('projects', 'existing');
            }
            $this->projectIds[$project['key']] = $id;

            $this->applyPhases($project, $id, $phaseKeyById, $created);

            if ($deferredStatus !== null) {
                // Un projet livré ne peut plus faire évoluer ses phases : le statut est posé en dernier.
                $current = $this->projects->findById($id);
                if ($current !== null && $current['status'] !== $deferredStatus) {
                    $input['status'] = $deferredStatus;
                    $input['custom_fields'] = [];
                    $this->projects->update($id, $input, true);
                }
            }
        }
    }

    /**
     * Rejoue l'avancement des phases dans l'ordre du noyau : chaque phase terminée doit être
     * démarrée puis clôturée, et une seule phase peut être en cours par projet.
     *
     * @param array<string,mixed> $project
     * @param array<int,string> $phaseKeyById
     */
    private function applyPhases(array $project, int $projectId, array $phaseKeyById, bool $projectIsNew): void
    {
        $completed = array_flip($project['completed_phases']);
        $current = $project['current_phase'];
        $assignments = $this->projects->phaseAssignments($projectId);
        $this->assignmentIds[$project['key']] = [];

        foreach ($assignments as $assignment) {
            $phaseKey = $phaseKeyById[$assignment['project_phase_id']] ?? null;
            if ($phaseKey === null) {
                continue;
            }
            $this->assignmentIds[$project['key']][$phaseKey] = $assignment['id'];
            $this->bump('project_phase_assignments', $projectIsNew ? 'created' : 'existing');
            $status = $assignment['status'];
            $shouldComplete = isset($completed[$phaseKey]);
            $shouldStart = $shouldComplete || $phaseKey === $current;

            if (!$shouldComplete && !$shouldStart && $status === 'pending') {
                continue;
            }
            if ($status === 'pending' && $shouldStart) {
                $result = $this->projects->updatePhaseStatus($projectId, $assignment['id'], 'start');
                if ($result !== 'updated') {
                    throw new RuntimeException(sprintf(
                        'Phase %s du projet %s non démarrée (%s).',
                        $phaseKey,
                        $project['key'],
                        $result
                    ));
                }
                $status = 'in_progress';
            }
            if ($shouldComplete && $status === 'in_progress') {
                $result = $this->projects->updatePhaseStatus($projectId, $assignment['id'], 'complete');
                if ($result !== 'updated') {
                    throw new RuntimeException(sprintf(
                        'Phase %s du projet %s non clôturée (%s).',
                        $phaseKey,
                        $project['key'],
                        $result
                    ));
                }
                continue;
            }
            if ($shouldComplete && $status !== 'completed') {
                throw new RuntimeException(sprintf(
                    'La phase %s du projet %s ne peut pas être clôturée depuis l’état %s.',
                    $phaseKey,
                    $project['key'],
                    $status
                ));
            }
            if ($phaseKey === $current && !$shouldComplete
                && (string) $project['current_progress'] !== '0.00'
                && $assignment['progress_percent'] !== $project['current_progress']
            ) {
                $result = $this->projects->updatePhaseProgress($projectId, $assignment['id'], $project['current_progress']);
                if ($result !== 'updated') {
                    throw new RuntimeException(sprintf(
                        'Avancement de la phase %s du projet %s non enregistré (%s).',
                        $phaseKey,
                        $project['key'],
                        $result
                    ));
                }
            }
        }
    }

    private function seedTimeEntries(DemoDataset $dataset): void
    {
        foreach ($dataset->timeEntries() as $entry) {
            $userId = $this->userId($entry['user']);
            $projectId = $this->projectId($entry['project']);
            $assignmentId = $this->assignmentIds[$entry['project']][$entry['phase']] ?? null;
            if ($assignmentId === null) {
                $this->warnings[] = sprintf('Phase %s absente du projet %s : temps ignoré.', $entry['phase'], $entry['project']);
                continue;
            }
            $date = $dataset->date($entry['offset']);
            $known = $this->findId('time_entries', [
                'description' => $entry['description'],
                'user_id' => $userId,
                'work_date' => $date,
            ]);
            if ($known !== null) {
                $this->bump('time_entries', 'existing');
                continue;
            }
            if ($this->rates->rateForDate($userId, $date) === null) {
                $this->warnings[] = sprintf('Aucun taux horaire pour %s au %s : temps ignoré.', $entry['user'], $date);
                continue;
            }
            $this->time->create([
                'user_id' => $userId,
                'project_id' => $projectId,
                'phase_assignment_id' => $assignmentId,
                'work_date' => $date,
                'duration_hours' => $entry['hours'],
                'description' => $entry['description'],
                'is_billable' => $entry['billable'],
            ]);
            $this->bump('time_entries', 'created');
        }
    }

    private function seedDocuments(DemoDataset $dataset, int $actorId): void
    {
        foreach ($dataset->documents() as $document) {
            $clientId = $this->clientId($document['client']);
            $projectId = $this->projectId($document['project']);
            $referenceId = $document['reference'] === null ? null : $this->documentId($document['reference']);
            $existing = $this->findDocument($clientId, $document['document_type'], $document['title']);
            $issued = $document['issue_offset'] !== null;

            if ($existing === null) {
                $defaults = $this->documents->draftDefaults(
                    $document['document_type'],
                    $document['locale'],
                    $referenceId
                );
                $values = array_merge($defaults, [
                    'title' => $document['title'],
                    'client_id' => (string) $clientId,
                    'project_id' => (string) $projectId,
                    'reference_document_id' => $referenceId === null ? '' : (string) $referenceId,
                    'billing_kind' => $document['billing_kind'],
                    'notes' => $document['notes'],
                    'lines' => $document['lines'],
                ]);
                if ($document['phase'] !== null) {
                    $assignmentId = $this->assignmentIds[$document['project']][$document['phase']] ?? null;
                    if ($assignmentId === null) {
                        throw new RuntimeException(sprintf(
                            'Phase %s introuvable pour le document %s.',
                            $document['phase'],
                            $document['key']
                        ));
                    }
                    $values['project_phase_assignment_id'] = (string) $assignmentId;
                }
                $validated = $this->documents->validateDraft($values);
                if ($validated['errors'] !== []) {
                    throw new RuntimeException(sprintf(
                        'Document de démonstration invalide (%s) : %s',
                        $document['key'],
                        implode(', ', array_keys($validated['errors']))
                    ));
                }
                $id = $this->documents->createDraft($validated['values'], $actorId);
                $this->bump('documents', 'created');
                $status = 'draft';
            } else {
                $id = $existing['id'];
                $status = $existing['status'];
                $this->bump('documents', 'existing');
            }
            $this->documentIds[$document['key']] = $id;

            if ($issued && $status === 'draft') {
                $this->documents->issue($id, $actorId, $dataset->date((int) $document['issue_offset']));
                $status = 'issued';
            }
            if ($document['payments'] === [] || $status !== 'issued' || $document['document_type'] !== 'invoice') {
                continue;
            }
            $this->seedPayments($dataset, $document, $id, $actorId);
        }
    }

    /**
     * @param array<string,mixed> $document
     */
    private function seedPayments(DemoDataset $dataset, array $document, int $documentId, int $actorId): void
    {
        $row = $this->documentRepository->findById($documentId);
        if ($row === null) {
            return;
        }
        foreach ($document['payments'] as $payment) {
            $paidOn = $dataset->date($payment['offset']);
            $known = $this->pdo->prepare(
                'SELECT id FROM document_payments WHERE document_id = :document_id AND reference = :reference LIMIT 1'
            );
            $known->execute(['document_id' => $documentId, 'reference' => $payment['reference']]);
            if ($known->fetchColumn() !== false) {
                $this->bump('document_payments', 'existing');
                continue;
            }
            $position = $this->documentRepository->financialPosition($documentId);
            $amount = $payment['percent'] === '100'
                ? (string) $position['balance_due']
                : self::percentOf((string) $row['total_amount'], $payment['percent']);
            if (self::compare($amount, (string) $position['balance_due']) > 0) {
                $amount = (string) $position['balance_due'];
            }
            if (self::compare($amount, '0.000') <= 0) {
                $this->bump('document_payments', 'existing');
                continue;
            }
            $errors = $this->documents->addPayment($documentId, [
                'paid_on' => $paidOn,
                'amount' => $amount,
                'payment_method' => $payment['method'],
                'reference' => $payment['reference'],
                'note' => $payment['note'],
            ], $actorId);
            if ($errors !== []) {
                throw new RuntimeException(sprintf(
                    'Règlement de démonstration refusé (%s) : %s',
                    $payment['reference'],
                    implode(', ', array_keys($errors))
                ));
            }
            $this->bump('document_payments', 'created');
        }
    }

    /** Trace honnête de l'opération : un seul événement, sans donnée métier sensible. */
    private function recordAudit(int $actorId): void
    {
        $created = 0;
        foreach ($this->counts as $step) {
            $created += $step['created'];
        }
        if ($created === 0) {
            return;
        }
        (new AuditLogger($this->pdo))->record(
            'demo.data.seeded',
            'demo_dataset',
            (string) DemoDataset::VERSION,
            ['created_total' => $created],
            $actorId > 0 ? $actorId : null
        );
    }

    /**
     * Gouvernorat, délégation, ville et code postal lus dans les référentiels préchargés.
     *
     * @return array{governorate_id:string,delegation_id:string,city:string,postal_code:string}
     */
    private function place(string $governorateIso, string $delegationCode): array
    {
        $governorateId = $this->geography->governorateIdByIsoCode($governorateIso);
        if ($governorateId === null) {
            throw new RuntimeException('Gouvernorat introuvable : ' . $governorateIso . '. Lancez php bin/seed.php.');
        }
        $delegationId = $this->geography->delegationIdByCode($delegationCode, $governorateId);
        if ($delegationId === null) {
            throw new RuntimeException('Délégation introuvable : ' . $delegationCode . '. Lancez php bin/seed.php.');
        }
        $city = '';
        foreach ($this->geography->delegations($governorateId) as $delegation) {
            if ($delegation['id'] === $delegationId) {
                $city = $delegation['name_latin'];
                break;
            }
        }
        $postalCodes = $this->geography->postalCodes($delegationId);
        $postalCode = $postalCodes === [] ? '' : (string) $postalCodes[0]['code'];

        return [
            'governorate_id' => (string) $governorateId,
            'delegation_id' => (string) $delegationId,
            'city' => $city,
            'postal_code' => $postalCode,
        ];
    }

    /** @param array<string,mixed> $client */
    private function findClientId(array $client): ?int
    {
        $statement = $this->pdo->prepare(
            'SELECT id FROM clients WHERE display_name = :display_name AND email = :email ORDER BY id LIMIT 1'
        );
        $statement->execute(['display_name' => $client['display_name'], 'email' => $client['email']]);
        $id = $statement->fetchColumn();

        return $id === false ? null : (int) $id;
    }

    private function findContactId(int $clientId, string $fullName): ?int
    {
        $statement = $this->pdo->prepare(
            'SELECT id FROM client_contacts WHERE client_id = :client_id AND full_name = :full_name LIMIT 1'
        );
        $statement->execute(['client_id' => $clientId, 'full_name' => $fullName]);
        $id = $statement->fetchColumn();

        return $id === false ? null : (int) $id;
    }

    /** @return array{id:int,status:string}|null */
    private function findDocument(int $clientId, string $type, string $title): ?array
    {
        $statement = $this->pdo->prepare(
            'SELECT id, status FROM documents
             WHERE client_id = :client_id AND document_type = :document_type AND title = :title
             ORDER BY id LIMIT 1'
        );
        $statement->execute(['client_id' => $clientId, 'document_type' => $type, 'title' => $title]);
        $row = $statement->fetch();

        return is_array($row) ? ['id' => (int) $row['id'], 'status' => (string) $row['status']] : null;
    }

    /**
     * Recherche une valeur entière par égalités exactes sur une table connue.
     *
     * @param array<string,string|int> $conditions
     */
    private function findId(string $table, array $conditions): ?int
    {
        if (!in_array($table, self::LOOKUP_TABLES, true) || $conditions === []) {
            throw new RuntimeException('Recherche de démonstration non autorisée : ' . $table);
        }
        $clauses = [];
        $parameters = [];
        foreach ($conditions as $column => $value) {
            if (preg_match('/^[a-z_]{2,40}$/', $column) !== 1) {
                throw new RuntimeException('Colonne de recherche invalide : ' . $column);
            }
            $clauses[] = $column . ' = :' . $column;
            $parameters[$column] = $value;
        }
        $statement = $this->pdo->prepare(sprintf(
            'SELECT id FROM %s WHERE %s ORDER BY id LIMIT 1',
            $table,
            implode(' AND ', $clauses)
        ));
        $statement->execute($parameters);
        $id = $statement->fetchColumn();

        return $id === false ? null : (int) $id;
    }

    private function userId(string $key): int
    {
        if (!isset($this->userIds[$key])) {
            throw new RuntimeException('Utilisateur de démonstration inconnu : ' . $key);
        }

        return $this->userIds[$key];
    }

    private function clientId(string $key): int
    {
        if (!isset($this->clientIds[$key])) {
            throw new RuntimeException('Client de démonstration inconnu : ' . $key);
        }

        return $this->clientIds[$key];
    }

    private function projectId(string $key): int
    {
        if (!isset($this->projectIds[$key])) {
            throw new RuntimeException('Projet de démonstration inconnu : ' . $key);
        }

        return $this->projectIds[$key];
    }

    private function documentId(string $key): int
    {
        if (!isset($this->documentIds[$key])) {
            throw new RuntimeException('Document de démonstration inconnu : ' . $key);
        }

        return $this->documentIds[$key];
    }

    private function bump(string $table, string $column): void
    {
        $this->counts[$table] ??= ['created' => 0, 'existing' => 0];
        $this->counts[$table][$column] = ($this->counts[$table][$column] ?? 0) + 1;
    }

    /** Pourcentage d'un montant décimal, arrondi au millime. */
    private static function percentOf(string $amount, string $percent): string
    {
        return number_format(round((float) $amount * (float) $percent / 100, 3), 3, '.', '');
    }

    private static function compare(string $left, string $right): int
    {
        return (int) round(((float) $left - (float) $right) * 1000);
    }
}
