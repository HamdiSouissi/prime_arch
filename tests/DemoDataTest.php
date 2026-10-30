<?php
declare(strict_types=1);

namespace Tests;

use App\Services\DemoDataset;
use App\Services\TunisianPhoneNumber;
use PHPUnit\Framework\TestCase;

/**
 * Cohérence du jeu de démonstration, sans base de données.
 *
 * Le jeu est relu comme le ferait le seeder : clés naturelles uniques, géographie issue des
 * référentiels préchargés, montants au millime, chaîne devis → contrat → facture → avoir
 * sans rupture, temps saisi uniquement sur des phases ouvertes ou terminées.
 */
final class DemoDataTest extends TestCase
{
    /** @var array<int,array<string,mixed>>|null */
    private static ?array $governorates = null;

    /** @var array<int,array<string,mixed>>|null */
    private static ?array $delegations = null;

    /** @var array<int,array<string,mixed>>|null */
    private static ?array $phases = null;

    private static function dataset(): DemoDataset
    {
        // Date de référence fixe : les tests ne dépendent pas du jour d'exécution.
        return new DemoDataset('2026-03-15');
    }

    /** @return array<int,array<string,mixed>> */
    private static function governorates(): array
    {
        self::$governorates ??= require BASE_PATH . '/database/seeds/governorates.php';

        return self::$governorates;
    }

    /** @return array<int,array<string,mixed>> */
    private static function delegations(): array
    {
        self::$delegations ??= require BASE_PATH . '/database/seeds/delegations.php';

        return self::$delegations;
    }

    /** @return array<int,string> */
    private static function phaseKeys(): array
    {
        return array_keys((array) config('tunisia.architecture.phases', []));
    }

    /** @param array<int,array<string,mixed>> $rows
     *  @return array<string,mixed>|null
     */
    private static function findBy(array $rows, string $column, string $value): ?array
    {
        foreach ($rows as $row) {
            if ((string) $row[$column] === $value) {
                return $row;
            }
        }

        return null;
    }

    public function testUsersCoverEveryRoleWithUniqueAddresses(): void
    {
        $users = self::dataset()->users();
        $keys = [];
        $emails = [];
        $roles = [];
        foreach ($users as $user) {
            $keys[] = $user['key'];
            $emails[] = $user['email'];
            $roles[] = $user['role'];
            self::assertTrue(str_ends_with($user['email'], '@' . DemoDataset::EMAIL_DOMAIN), 'Adresse hors domaine de démonstration.');
            self::assertTrue(in_array($user['locale'], ['fr', 'ar'], true), 'Langue du compte inattendue.');
            self::assertTrue(in_array($user['role'], DemoDataset::ROLES, true), 'Rôle inconnu du noyau.');
        }
        self::assertSame(count($keys), count(array_unique($keys)), 'Clés d’utilisateur en double.');
        self::assertSame(count($emails), count(array_unique($emails)), 'Adresses en double.');
        self::assertSame(1, count(array_keys($roles, 'administrator', true)), 'Un seul administrateur est attendu.');
        self::assertSame(count(DemoDataset::ROLES), count(array_unique($roles)), 'Chaque rôle doit être représenté une fois.');
    }

    public function testClientsUsePrefetchedGeographyAndValidContacts(): void
    {
        $clients = self::dataset()->clients();
        $keys = [];
        foreach ($clients as $client) {
            $keys[] = $client['key'];
            self::assertTrue(
                in_array($client['client_type'], ['individual', 'company', 'organization'], true),
                'Type de client inattendu.'
            );
            self::assertTrue(in_array($client['status'], ['prospect', 'active', 'archived'], true), 'Statut inattendu.');

            $governorate = self::findBy(self::governorates(), 'iso', $client['governorate']);
            self::assertNotSame(null, $governorate, 'Gouvernorat absent du référentiel : ' . $client['governorate']);
            $delegation = self::findBy(self::delegations(), 'code', $client['delegation']);
            self::assertNotSame(null, $delegation, 'Délégation absente du référentiel : ' . $client['delegation']);
            self::assertSame($client['governorate'], $delegation['governorate_iso'], 'Délégation rattachée à un autre gouvernorat.');

            self::assertTrue(TunisianPhoneNumber::isValid($client['phone']), 'Téléphone invalide pour ' . $client['key']);
            if ($client['tax_id'] !== '') {
                self::assertSame(
                    1,
                    preg_match((string) config('tunisia.tax_id.pattern'), $client['tax_id']),
                    'Matricule fiscal invalide pour ' . $client['key']
                );
            }
            $primary = 0;
            foreach ($client['contacts'] as $contact) {
                self::assertTrue(TunisianPhoneNumber::isValid($contact['phone']), 'Téléphone de contact invalide.');
                self::assertTrue(str_ends_with($contact['email'], '@' . DemoDataset::EMAIL_DOMAIN), 'Contact hors domaine de démonstration.');
                $primary += $contact['is_primary'] ? 1 : 0;
            }
            self::assertTrue($primary <= 1, 'Un seul contact principal par client.');
        }
        self::assertSame(count($keys), count(array_unique($keys)), 'Clés de client en double.');
    }

    public function testArchiveIsShownWithoutBreakingProjectRules(): void
    {
        $dataset = self::dataset();
        $clientKeys = array_column($dataset->clients(), 'status', 'key');
        self::assertSame('archived', $clientKeys['atelier'] ?? null, 'Un client archivé est nécessaire à la démonstration.');
        foreach ($dataset->projects() as $project) {
            // Un projet ne peut pas être rattaché à un client archivé (règle du noyau).
            self::assertNotSame('archived', $clientKeys[$project['client']] ?? null, 'Projet rattaché à un client archivé.');
        }
    }

    public function testProjectsDeclareCoherentPhasesAndDates(): void
    {
        $dataset = self::dataset();
        $clientKeys = array_column($dataset->clients(), 'key');
        $knownPhases = self::phaseKeys();
        $keys = [];
        $statuses = [];
        foreach ($dataset->projects() as $project) {
            $keys[] = $project['key'];
            $statuses[] = $project['status'];
            self::assertTrue(in_array($project['client'], $clientKeys, true), 'Client inconnu pour ' . $project['key']);
            self::assertTrue(
                in_array($project['status'], ['planning', 'active', 'on_hold', 'completed', 'cancelled'], true),
                'Statut de projet inattendu.'
            );
            self::assertTrue($project['start_offset'] < $project['end_offset'], 'Dates de projet incohérentes.');
            self::assertTrue((float) $project['budget_hours'] > 0, 'Budget d’heures manquant.');
            self::assertSame(1, preg_match('/^\d+\.\d{3}$/', $project['fee_budget_amount']), 'Honoraires hors millimes.');
            foreach ($project['completed_phases'] as $phase) {
                self::assertTrue(in_array($phase, $knownPhases, true), 'Phase inconnue du noyau : ' . $phase);
            }
            if ($project['current_phase'] !== null) {
                self::assertTrue(in_array($project['current_phase'], $knownPhases, true), 'Phase courante inconnue.');
                self::assertFalse(
                    in_array($project['current_phase'], $project['completed_phases'], true),
                    'La phase courante ne peut pas être déjà terminée.'
                );
                self::assertTrue((float) $project['current_progress'] > 0, 'Avancement de la phase courante nul.');
            }
            if ($project['status'] === 'completed') {
                self::assertSame(count($knownPhases), count($project['completed_phases']), 'Un projet livré doit être entièrement terminé.');
                self::assertSame(null, $project['current_phase'], 'Un projet livré ne peut pas avoir de phase en cours.');
            }
            // L'ordre du noyau est respecté : les phases terminées précèdent la phase courante.
            if ($project['current_phase'] !== null) {
                $currentIndex = array_search($project['current_phase'], $knownPhases, true);
                foreach ($project['completed_phases'] as $phase) {
                    self::assertTrue(
                        array_search($phase, $knownPhases, true) < $currentIndex,
                        'Une phase terminée suit la phase courante.'
                    );
                }
            }
        }
        self::assertSame(count($keys), count(array_unique($keys)), 'Clés de projet en double.');
        self::assertTrue(in_array('completed', $statuses, true), 'Un projet livré est attendu.');
        self::assertTrue(in_array('on_hold', $statuses, true), 'Un projet suspendu est attendu.');
    }

    public function testTimeEntriesStayOnOpenOrCompletedPhases(): void
    {
        $dataset = self::dataset();
        $userKeys = array_column($dataset->users(), 'key');
        $projects = [];
        foreach ($dataset->projects() as $project) {
            $projects[$project['key']] = $project;
        }
        $seen = [];
        foreach ($dataset->timeEntries() as $entry) {
            self::assertTrue(in_array($entry['user'], $userKeys, true), 'Utilisateur inconnu dans le temps saisi.');
            self::assertTrue(isset($projects[$entry['project']]), 'Projet inconnu dans le temps saisi.');
            $project = $projects[$entry['project']];
            $openPhases = $project['completed_phases'];
            $openPhases[] = $project['current_phase'];
            self::assertTrue(
                in_array($entry['phase'], $openPhases, true),
                'Temps saisi sur une phase non ouverte : ' . $entry['phase'] . ' (' . $entry['project'] . ')'
            );
            self::assertTrue($entry['offset'] <= 0, 'Le temps saisi ne peut pas être futur.');
            $hours = (float) $entry['hours'];
            self::assertTrue($hours > 0 && $hours <= 12, 'Durée quotidienne hors bornes.');
            self::assertSame(0.0, fmod($hours * 4, 1.0), 'La durée doit être un multiple de quinze minutes.');
            self::assertTrue(mb_strlen($entry['description'], 'UTF-8') >= 3, 'Description trop courte.');
            $signature = $entry['user'] . '|' . $entry['offset'] . '|' . $entry['description'];
            self::assertFalse(isset($seen[$signature]), 'Saisie de temps en double : ' . $signature);
            $seen[$signature] = true;
        }
        self::assertTrue(count($dataset->timeEntries()) >= 30, 'Le jeu doit couvrir plusieurs semaines de saisie.');
    }

    public function testDocumentsFormAnUnbrokenReferenceChain(): void
    {
        $dataset = self::dataset();
        $clientKeys = array_column($dataset->clients(), 'key');
        $projects = [];
        foreach ($dataset->projects() as $project) {
            $projects[$project['key']] = $project;
        }
        $seen = [];
        $types = [];
        foreach ($dataset->documents() as $document) {
            self::assertFalse(isset($seen[$document['key']]), 'Clé de document en double.');
            self::assertTrue(in_array($document['client'], $clientKeys, true), 'Client inconnu pour ' . $document['key']);
            self::assertTrue(isset($projects[$document['project']]), 'Projet inconnu pour ' . $document['key']);
            self::assertSame($document['client'], $projects[$document['project']]['client'], 'Client et projet dissociés.');
            self::assertTrue(in_array($document['locale'], ['fr', 'ar'], true), 'Langue de document inattendue.');
            $types[] = $document['document_type'];

            if ($document['reference'] !== null) {
                self::assertTrue(isset($seen[$document['reference']]), 'Référence absente ou postérieure.');
                $reference = $seen[$document['reference']];
                $allowed = [
                    'contract' => ['quote'],
                    'invoice' => ['quote', 'contract'],
                    'credit_note' => ['invoice'],
                ][$document['document_type']] ?? [];
                self::assertTrue(
                    in_array($reference, $allowed, true),
                    'Référence de type incohérent pour ' . $document['key']
                );
            }

            if ($document['document_type'] === 'invoice') {
                self::assertTrue(in_array($document['billing_kind'], ['standard', 'deposit', 'phase'], true), 'Mode de facturation inconnu.');
                if ($document['billing_kind'] === 'phase') {
                    self::assertTrue($document['phase'] !== null, 'Une facture de phase doit désigner une phase.');
                    $project = $projects[$document['project']];
                    $phases = $project['completed_phases'];
                    $phases[] = $project['current_phase'];
                    self::assertTrue(in_array($document['phase'], $phases, true), 'Phase facturée non ouverte.');
                } else {
                    self::assertSame(null, $document['phase'], 'Une facture hors phase ne doit pas désigner de phase.');
                }
            } else {
                self::assertSame('', $document['billing_kind'], 'Seule une facture porte un mode de facturation.');
                self::assertSame(null, $document['phase'], 'Seule une facture de phase désigne une phase.');
            }

            if ($document['issue_offset'] !== null) {
                self::assertTrue($document['issue_offset'] <= 0, 'Un document ne peut pas être émis dans le futur.');
            }
            self::assertTrue($document['lines'] !== [], 'Document sans ligne : ' . $document['key']);
            foreach ($document['lines'] as $line) {
                self::assertTrue((float) $line['quantity'] > 0, 'Quantité nulle ou négative.');
                self::assertTrue(preg_match('/^\d+\.\d{3}$/', $line['unit_price']) === 1, 'Prix hors millimes.');
                self::assertTrue(mb_strlen($line['description'], 'UTF-8') > 2, 'Description de ligne trop courte.');
            }

            foreach ($document['payments'] as $payment) {
                self::assertSame('invoice', $document['document_type'], 'Seule une facture reçoit un règlement.');
                self::assertNotSame(null, $document['issue_offset'], 'Règlement sur un brouillon impossible.');
                self::assertTrue($payment['offset'] >= $document['issue_offset'], 'Règlement antérieur à l’émission.');
                self::assertTrue($payment['offset'] <= 0, 'Règlement futur.');
                self::assertTrue((float) $payment['percent'] > 0 && (float) $payment['percent'] <= 100, 'Pourcentage de règlement hors bornes.');
                self::assertTrue(
                    in_array($payment['method'], ['cash', 'bank_transfer', 'cheque', 'card', 'other'], true),
                    'Mode de règlement inconnu.'
                );
            }

            $seen[$document['key']] = $document['document_type'];
        }
        foreach (['quote', 'contract', 'invoice', 'credit_note'] as $type) {
            self::assertTrue(in_array($type, $types, true), 'Type de document absent du jeu : ' . $type);
        }
        self::assertTrue(in_array('ar', array_column($dataset->documents(), 'locale'), true), 'Un document arabe est attendu.');
    }

    public function testDraftsAndIssuedDocumentsAreBothPresent(): void
    {
        $documents = self::dataset()->documents();
        $drafts = array_filter($documents, static fn (array $document): bool => $document['issue_offset'] === null);
        self::assertTrue(count($drafts) >= 1, 'Un brouillon est attendu.');
        self::assertTrue(count($documents) - count($drafts) >= 5, 'Plusieurs documents émis sont attendus.');
        $paid = array_filter($documents, static fn (array $document): bool => $document['payments'] !== []);
        self::assertTrue(count($paid) >= 2, 'Deux factures réglées au moins sont attendues.');
    }

    public function testCountsDescribeTheWholeDataset(): void
    {
        $dataset = self::dataset();
        $counts = $dataset->counts();
        self::assertSame(count($dataset->users()), $counts['users']);
        self::assertSame(count($dataset->clients()), $counts['clients']);
        self::assertSame(count($dataset->projects()), $counts['projects']);
        self::assertSame(count($dataset->timeEntries()), $counts['time_entries']);
        self::assertSame(count($dataset->documents()), $counts['documents']);
        self::assertSame(
            array_sum(array_map(static fn (array $document): int => count($document['payments']), $dataset->documents())),
            $counts['document_payments']
        );
        $contacts = array_sum(array_map(static fn (array $client): int => count($client['contacts']), $dataset->clients()));
        self::assertSame($contacts, $counts['client_contacts']);
    }

    public function testReferenceDateShiftsEveryRelativeDate(): void
    {
        $early = new DemoDataset('2026-01-05');
        $late = new DemoDataset('2026-06-20');
        self::assertSame('2026-01-05', $early->today());
        self::assertSame('2026-06-20', $late->today());
        self::assertSame('2025-12-01', $early->date(-35));
        self::assertSame('2026-06-13', $late->date(-7));
        self::assertSame('2026-01-04', $early->date(-1));
        self::assertSame('2026-06-19', $late->date(-1));
        self::assertSame(
            array_keys($early->documents()),
            array_keys($late->documents()),
            'Les décalages ne doivent pas changer l’ordre des documents.'
        );
    }
}
