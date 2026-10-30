<?php
declare(strict_types=1);

namespace App\Services;

use DateTimeImmutable;
use InvalidArgumentException;

/**
 * Jeu de données de démonstration, décrit une seule fois, sans base de données.
 *
 * Le jeu est volontairement séparé de son écriture (`DemoDataSeeder`) : il peut ainsi être
 * relu, testé et comparé sans PDO ni MySQL. Les dates sont exprimées en décalage de jours
 * par rapport à une date de référence, afin que la démonstration reste d'actualité quelle
 * que soit la date d'exécution.
 *
 * Règles observées : aucune règle fiscale ni légale n'est inventée (TVA, timbre et retenue
 * proviennent de la configuration du cabinet) ; les identifiants (matricules, téléphones,
 * adresses électroniques) sont fictifs ; la géographie provient du référentiel préchargé.
 */
final class DemoDataset
{
    /** Version du jeu : à incrémenter à chaque évolution notable des données. */
    public const VERSION = 1;

    /** Domaine des adresses fictives, utilisé aussi pour reconnaître les comptes de démo. */
    public const EMAIL_DOMAIN = 'demo.prime-architecture.tn';

    /** Rôles du noyau acceptés pour les comptes de démonstration. */
    public const ROLES = ['administrator', 'architect', 'collaborator', 'accountant', 'read_only'];

    private DateTimeImmutable $today;

    /** @param string|null $today Date de référence (AAAA-MM-JJ) ; par défaut, aujourd'hui. */
    public function __construct(?string $today = null)
    {
        if ($today === null || $today === '') {
            $this->today = new DateTimeImmutable('today');
            return;
        }
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $today);
        $errors = DateTimeImmutable::getLastErrors();
        if ($date === false
            || ($errors !== false && ($errors['warning_count'] > 0 || $errors['error_count'] > 0))
            || $date->format('Y-m-d') !== $today
        ) {
            throw new InvalidArgumentException('Date de référence invalide pour le jeu de démonstration.');
        }
        $this->today = $date;
    }

    /** Date décalée de `$offset` jours (négatif = passé). */
    public function date(int $offset): string
    {
        return $this->today->modify(sprintf('%+d days', $offset))->format('Y-m-d');
    }

    public function today(): string
    {
        return $this->today->format('Y-m-d');
    }

    /** Adresse électronique fictive et stable pour une clé donnée. */
    public static function email(string $key): string
    {
        return $key . '@' . self::EMAIL_DOMAIN;
    }

    /**
     * Équipe du cabinet : un compte par rôle, dont un administrateur.
     *
     * @return array<int,array{key:string,name:string,email:string,role:string,locale:string}>
     */
    public function users(): array
    {
        return [
            [
                'key' => 'amine',
                'name' => 'Amine Ben Salah',
                'email' => self::email('amine.bensalah'),
                'role' => 'administrator',
                'locale' => 'fr',
            ],
            [
                'key' => 'leila',
                'name' => 'Leïla Trabelsi',
                'email' => self::email('leila.trabelsi'),
                'role' => 'architect',
                'locale' => 'fr',
            ],
            [
                'key' => 'youssef',
                'name' => 'Youssef Gharbi',
                'email' => self::email('youssef.gharbi'),
                'role' => 'architect',
                'locale' => 'ar',
            ],
            [
                'key' => 'nour',
                'name' => 'Nour Chaabane',
                'email' => self::email('nour.chaabane'),
                'role' => 'collaborator',
                'locale' => 'fr',
            ],
            [
                'key' => 'hatem',
                'name' => 'Hatem Jaziri',
                'email' => self::email('hatem.jaziri'),
                'role' => 'accountant',
                'locale' => 'fr',
            ],
            [
                'key' => 'salma',
                'name' => 'Salma Bouzid',
                'email' => self::email('salma.bouzid'),
                'role' => 'read_only',
                'locale' => 'ar',
            ],
        ];
    }

    /**
     * Taux horaires internes historisés : une valeur initiale, puis une revalorisation.
     *
     * @return array<int,array{user:string,effective_offset:int,rate:string,note:string}>
     */
    public function rates(): array
    {
        $initial = [
            ['amine', '52.000', 'Taux associé — année précédente'],
            ['leila', '44.000', 'Taux architecte — année précédente'],
            ['youssef', '41.000', 'Taux architecte — année précédente'],
            ['nour', '28.000', 'Taux collaborateur — année précédente'],
            ['hatem', '33.000', 'Taux comptable — année précédente'],
        ];
        $rates = [];
        foreach ($initial as [$user, $rate, $note]) {
            $rates[] = [
                'user' => $user,
                'effective_offset' => -400,
                'rate' => $rate,
                'note' => $note,
            ];
        }
        $rates[] = [
            'user' => 'leila',
            'effective_offset' => -90,
            'rate' => '47.500',
            'note' => 'Revalorisation — nouvelle grille du cabinet',
        ];

        return $rates;
    }

    /**
     * Clients fictifs (particuliers, sociétés et association) rattachés au référentiel réel.
     *
     * `governorate` reprend le code ISO du gouvernorat et `delegation` le code de la
     * délégation : la ville et le code postal sont ensuite lus dans les tables de géographie.
     *
     * @return array<int,array<string,mixed>>
     */
    public function clients(): array
    {
        return [
            [
                'key' => 'jasmins',
                'client_type' => 'company',
                'display_name' => 'Résidence Les Jasmins',
                'legal_name' => 'Résidence Les Jasmins SARL',
                'tax_id' => '0000011B/B/M/000',
                'email' => self::email('jasmins'),
                'phone' => '+216 71 745 210',
                'address_line' => 'Immeuble Le Panorama, 3e étage, avenue Habib Bourguiba',
                'governorate' => 'TN-11',
                'delegation' => 'TN1153',
                'status' => 'active',
                'notes' => 'Données de démonstration — promoteur de 24 logements.',
                'contacts' => [
                    [
                        'full_name' => 'Mounir Ben Amor',
                        'job_title' => 'Promoteur',
                        'email' => self::email('mounir.benamor'),
                        'phone' => '+216 98 220 110',
                        'is_primary' => true,
                    ],
                    [
                        'full_name' => 'Sana Mrad',
                        'job_title' => 'Assistante de direction',
                        'email' => self::email('sana.mrad'),
                        'phone' => '+216 71 745 211',
                        'is_primary' => false,
                    ],
                ],
            ],
            [
                'key' => 'khelifi',
                'client_type' => 'individual',
                'display_name' => 'Sami Khelifi',
                'legal_name' => '',
                'tax_id' => '',
                'email' => self::email('sami.khelifi'),
                'phone' => '+216 22 145 908',
                'address_line' => 'Lotissement El Ouns, rue n° 14',
                'governorate' => 'TN-11',
                'delegation' => 'TN1171',
                'status' => 'active',
                'notes' => 'Données de démonstration — construction d’une villa individuelle.',
                'contacts' => [
                    [
                        'full_name' => 'Sami Khelifi',
                        'job_title' => 'Maître d’ouvrage',
                        'email' => self::email('sami.khelifi'),
                        'phone' => '+216 22 145 908',
                        'is_primary' => true,
                    ],
                ],
            ],
            [
                'key' => 'medina',
                'client_type' => 'company',
                'display_name' => 'Medina Invest',
                'legal_name' => 'Medina Invest SA',
                'tax_id' => '0000023C/D/M/000',
                'email' => self::email('medina.invest'),
                'phone' => '+216 73 226 480',
                'address_line' => 'Avenue de la République, immeuble Yasmine',
                'governorate' => 'TN-51',
                'delegation' => 'TN3151',
                'status' => 'active',
                'notes' => 'Données de démonstration — immeuble de bureaux.',
                'contacts' => [
                    [
                        'full_name' => 'Karim Zammouri',
                        'job_title' => 'Directeur immobilier',
                        'email' => self::email('karim.zammouri'),
                        'phone' => '+216 55 480 320',
                        'is_primary' => true,
                    ],
                ],
            ],
            [
                'key' => 'elbahr',
                'client_type' => 'company',
                'display_name' => 'El Bahr Développement',
                'legal_name' => 'El Bahr Développement SARL',
                'tax_id' => '0000037D/N/P/000',
                'email' => self::email('elbahr'),
                'phone' => '+216 72 431 560',
                'address_line' => 'Zone touristique, route de la plage',
                'governorate' => 'TN-23',
                'delegation' => 'TN1751',
                'status' => 'active',
                'notes' => 'Données de démonstration — extension d’un hôtel ; mission suspendue.',
                'contacts' => [
                    [
                        'full_name' => 'Rim Ayari',
                        'job_title' => 'Responsable projets',
                        'email' => self::email('rim.ayari'),
                        'phone' => '+216 96 310 774',
                        'is_primary' => true,
                    ],
                ],
            ],
            [
                'key' => 'ibnkhaldoun',
                'client_type' => 'organization',
                'display_name' => 'Association Ibn Khaldoun',
                'legal_name' => 'Association culturelle Ibn Khaldoun',
                'tax_id' => '',
                'email' => self::email('ibnkhaldoun'),
                'phone' => '+216 71 903 615',
                'address_line' => 'Rue de la Médina, local associatif',
                'governorate' => 'TN-11',
                'delegation' => 'TN1151',
                'status' => 'prospect',
                'notes' => 'Données de démonstration — maison de culture, phase de prospection.',
                'contacts' => [
                    [
                        'full_name' => 'Hédi Mahfoudh',
                        'job_title' => 'Président de l’association',
                        'email' => self::email('hedi.mahfoudh'),
                        'phone' => '+216 24 668 902',
                        'is_primary' => true,
                    ],
                ],
            ],
            [
                'key' => 'ferchichi',
                'client_type' => 'individual',
                'display_name' => 'Ines Ferchichi',
                'legal_name' => '',
                'tax_id' => '',
                'email' => self::email('ines.ferchichi'),
                'phone' => '+216 50 217 340',
                'address_line' => 'Résidence Les Cyprès, appartement 12',
                'governorate' => 'TN-21',
                'delegation' => 'TN1551',
                'status' => 'prospect',
                'notes' => 'Données de démonstration — rénovation d’appartement.',
                'contacts' => [
                    [
                        'full_name' => 'Ines Ferchichi',
                        'job_title' => 'Propriétaire',
                        'email' => self::email('ines.ferchichi'),
                        'phone' => '+216 50 217 340',
                        'is_primary' => true,
                    ],
                ],
            ],
            [
                'key' => 'atelier',
                'client_type' => 'company',
                'display_name' => 'Atelier du Fil',
                'legal_name' => 'Atelier du Fil SARL',
                'tax_id' => '0000044E/P/C/000',
                'email' => self::email('atelier.du.fil'),
                'phone' => '+216 74 229 880',
                'address_line' => 'Rue des Artisans, zone artisanale',
                'governorate' => 'TN-61',
                'delegation' => 'TN3452',
                'status' => 'archived',
                'notes' => 'Données de démonstration — dossier clôturé, client archivé.',
                'contacts' => [],
            ],
        ];
    }

    /**
     * Projets et avancement des phases.
     *
     * `completed_phases` et `current_phase` utilisent les clés de phases du noyau
     * (`esquisse`, `aps`, `apd`, `permis`, `dce`, `passation`, `execution`, `reception`).
     * Un projet `completed` voit toutes ses phases terminées après coup.
     *
     * @return array<int,array<string,mixed>>
     */
    public function projects(): array
    {
        return [
            [
                'key' => 'villa-khelifi',
                'client' => 'khelifi',
                'name' => 'Villa Khelifi — La Marsa',
                'project_type' => 'Villa individuelle',
                'description' => 'Construction d’une villa individuelle de 240 m² couverts sur un terrain de 600 m².',
                'status' => 'active',
                'address_line' => 'Lotissement El Ouns, rue n° 14',
                'governorate' => 'TN-11',
                'delegation' => 'TN1171',
                'start_offset' => -150,
                'end_offset' => 75,
                'budget_hours' => '420.00',
                'fee_budget_amount' => '21000.000',
                'completed_phases' => ['esquisse', 'aps', 'apd'],
                'current_phase' => 'permis',
                'current_progress' => '60.00',
                'created_by' => 'leila',
            ],
            [
                'key' => 'jasmins',
                'client' => 'jasmins',
                'name' => 'Résidence Les Jasmins — 24 logements',
                'project_type' => 'Logements collectifs',
                'description' => 'Résidence de 24 logements avec deux niveaux de sous-sol et un local commun.',
                'status' => 'active',
                'address_line' => 'Immeuble Le Panorama, 3e étage, avenue Habib Bourguiba',
                'governorate' => 'TN-11',
                'delegation' => 'TN1153',
                'start_offset' => -260,
                'end_offset' => 140,
                'budget_hours' => '1250.00',
                'fee_budget_amount' => '62000.000',
                'completed_phases' => ['esquisse', 'aps', 'apd', 'permis'],
                'current_phase' => 'dce',
                'current_progress' => '35.00',
                'created_by' => 'amine',
            ],
            [
                'key' => 'medina',
                'client' => 'medina',
                'name' => 'Immeuble de bureaux — Sousse',
                'project_type' => 'Bâtiment tertiaire',
                'description' => 'Immeuble de bureaux de cinq niveaux avec parking en sous-sol.',
                'status' => 'active',
                'address_line' => 'Avenue de la République, immeuble Yasmine',
                'governorate' => 'TN-51',
                'delegation' => 'TN3151',
                'start_offset' => -120,
                'end_offset' => 300,
                'budget_hours' => '980.00',
                'fee_budget_amount' => '54000.000',
                'completed_phases' => ['esquisse', 'aps'],
                'current_phase' => 'apd',
                'current_progress' => '70.00',
                'created_by' => 'youssef',
            ],
            [
                'key' => 'elbahr',
                'client' => 'elbahr',
                'name' => 'Extension hôtel El Bahr',
                'project_type' => 'Hôtellerie',
                'description' => 'Extension de 18 chambres et réaménagement du hall d’accueil.',
                'status' => 'on_hold',
                'address_line' => 'Zone touristique, route de la plage',
                'governorate' => 'TN-23',
                'delegation' => 'TN1751',
                'start_offset' => -200,
                'end_offset' => -10,
                'budget_hours' => '700.00',
                'fee_budget_amount' => '38500.000',
                'completed_phases' => ['esquisse'],
                'current_phase' => 'aps',
                'current_progress' => '25.00',
                'created_by' => 'youssef',
            ],
            [
                'key' => 'ibnkhaldoun',
                'client' => 'ibnkhaldoun',
                'name' => 'Maison de culture — Carthage',
                'project_type' => 'Équipement culturel',
                'description' => 'Étude de faisabilité et esquisse d’une maison de culture de 650 m².',
                'status' => 'planning',
                'address_line' => 'Rue de la Médina, local associatif',
                'governorate' => 'TN-11',
                'delegation' => 'TN1151',
                'start_offset' => -20,
                'end_offset' => 365,
                'budget_hours' => '260.00',
                'fee_budget_amount' => '14500.000',
                'completed_phases' => [],
                'current_phase' => null,
                'current_progress' => '0.00',
                'created_by' => 'amine',
            ],
            [
                'key' => 'ferchichi',
                'client' => 'ferchichi',
                'name' => 'Rénovation appartement — Nabeul',
                'project_type' => 'Rénovation',
                'description' => 'Rénovation complète d’un appartement de 95 m², cuisine et salle de bain incluses.',
                'status' => 'planning',
                'address_line' => 'Résidence Les Cyprès, appartement 12',
                'governorate' => 'TN-21',
                'delegation' => 'TN1551',
                'start_offset' => 15,
                'end_offset' => 210,
                'budget_hours' => '180.00',
                'fee_budget_amount' => '9800.000',
                'completed_phases' => [],
                'current_phase' => null,
                'current_progress' => '0.00',
                'created_by' => 'leila',
            ],
            [
                'key' => 'khelifi-livre',
                'client' => 'khelifi',
                'name' => 'Garage Khelifi — réception des travaux',
                'project_type' => 'Villa individuelle',
                'description' => 'Mission achevée : garage attenant à la villa, réception prononcée.',
                'status' => 'completed',
                'address_line' => 'Lotissement El Ouns, rue n° 14',
                'governorate' => 'TN-11',
                'delegation' => 'TN1171',
                'start_offset' => -520,
                'end_offset' => -140,
                'budget_hours' => '140.00',
                'fee_budget_amount' => '7600.000',
                'completed_phases' => ['esquisse', 'aps', 'apd', 'permis', 'dce', 'passation', 'execution', 'reception'],
                'current_phase' => null,
                'current_progress' => '0.00',
                'created_by' => 'leila',
            ],
        ];
    }

    /**
     * Saisies de temps : elles ne visent que des phases terminées ou en cours.
     *
     * @return array<int,array<string,mixed>>
     */
    public function timeEntries(): array
    {
        $entries = [
            // Villa Khelifi — dossier de permis en cours.
            ['leila', 'villa-khelifi', 'permis', -1, '3.50', 'Mise au net du dossier de permis de bâtir', true],
            ['leila', 'villa-khelifi', 'permis', -3, '4.00', 'Relevé complémentaire sur site et coupes', true],
            ['leila', 'villa-khelifi', 'apd', -16, '2.25', 'Corrections du dossier APD après observations', true],
            ['leila', 'villa-khelifi', 'permis', -22, '6.50', 'Préparation du dépôt et notice descriptive', true],
            ['nour', 'villa-khelifi', 'permis', -2, '6.50', 'Plans de niveau et repérage des menuiseries', true],
            ['nour', 'villa-khelifi', 'permis', -5, '3.00', 'Plan de situation et implantation', true],
            ['nour', 'villa-khelifi', 'apd', -9, '4.00', 'Mise à jour de la maquette numérique', true],
            ['nour', 'villa-khelifi', 'permis', -15, '2.25', 'Réponses aux demandes de l’administration', true],
            ['nour', 'villa-khelifi', 'esquisse', -36, '3.50', 'Variantes de façade pour présentation', true],
            ['youssef', 'villa-khelifi', 'permis', -6, '2.00', 'Relecture technique du dossier', true],
            ['youssef', 'villa-khelifi', 'permis', -12, '1.50', 'Échange avec le bureau d’études structures', false],
            ['youssef', 'villa-khelifi', 'apd', -29, '3.00', 'Vérification du dimensionnement des ouvertures', true],

            // Résidence Les Jasmins — dossier de consultation des entreprises.
            ['leila', 'jasmins', 'dce', -4, '4.00', 'Rédaction du cahier des charges des lots', true],
            ['leila', 'jasmins', 'dce', -8, '3.50', 'Coordination avec le bureau de contrôle', true],
            ['leila', 'jasmins', 'dce', -19, '6.50', 'Revue des pièces écrites du DCE', true],
            ['leila', 'jasmins', 'permis', -33, '2.25', 'Suivi de l’instruction du permis', true],
            ['nour', 'jasmins', 'dce', -5, '3.00', 'Métrés et bordereau quantitatif', true],
            ['nour', 'jasmins', 'dce', -12, '4.00', 'Plans de coffrage et de fondations', true],
            ['nour', 'jasmins', 'permis', -26, '2.25', 'Retouches graphiques sur les façades', true],
            ['nour', 'jasmins', 'dce', -41, '3.50', 'Nomenclature des équipements techniques', true],
            ['amine', 'jasmins', 'dce', -6, '1.50', 'Revue de direction avant consultation', false],
            ['amine', 'jasmins', 'dce', -16, '2.00', 'Réunion de cadrage avec le promoteur', false],

            // Immeuble de bureaux Medina Invest — avant-projet détaillé.
            ['youssef', 'medina', 'apd', -2, '6.50', 'Plans de niveaux courants et noyau central', true],
            ['youssef', 'medina', 'apd', -9, '4.00', 'Détails de façade et protections solaires', true],
            ['youssef', 'medina', 'apd', -19, '3.50', 'Coordination des lots techniques', true],
            ['youssef', 'medina', 'aps', -36, '2.25', 'Synthèse des surfaces utiles et circulations', true],
            ['nour', 'medina', 'apd', -3, '4.00', 'Coupes transversales et détails de balcons', true],
            ['nour', 'medina', 'apd', -11, '3.00', 'Mise à jour des plans de parking', true],
            ['nour', 'medina', 'apd', -22, '2.25', 'Repérage des gaines et faux plafonds', true],
            ['leila', 'medina', 'apd', -29, '1.50', 'Revue de conformité avec le programme', false],

            // Extension hôtel El Bahr — mission suspendue.
            ['youssef', 'elbahr', 'aps', -13, '3.00', 'Étude de faisabilité de l’extension', true],
            ['amine', 'elbahr', 'aps', -15, '1.50', 'Point de blocage budgétaire avec le client', false],
        ];

        return array_map(static fn (array $row): array => [
            'user' => $row[0],
            'project' => $row[1],
            'phase' => $row[2],
            'offset' => (int) $row[3],
            'hours' => $row[4],
            'description' => $row[5],
            'billable' => (bool) $row[6],
        ], $entries);
    }

    /**
     * Devis, contrats, factures et avoir, dans l'ordre de dépendance des références.
     *
     * `issue_offset` à `null` signifie « brouillon ». `reference` désigne la clé d'un
     * document précédent : un contrat reprend son devis, une facture son contrat ou son
     * devis, un avoir sa facture. Les montants sont en millimes (trois décimales) et la TVA,
     * le timbre et la retenue restent ceux de la configuration du cabinet.
     *
     * @return array<int,array<string,mixed>>
     */
    public function documents(): array
    {
        $villaLines = [
            ['description' => 'Esquisse et avant-projet sommaire', 'quantity' => '1.000', 'unit' => 'forfait', 'unit_price' => '4200.000'],
            ['description' => 'Avant-projet détaillé', 'quantity' => '1.000', 'unit' => 'forfait', 'unit_price' => '3600.000'],
            ['description' => 'Dossier de permis de bâtir', 'quantity' => '1.000', 'unit' => 'forfait', 'unit_price' => '2800.000'],
            ['description' => 'Dossier de consultation des entreprises', 'quantity' => '1.000', 'unit' => 'forfait', 'unit_price' => '2400.000'],
            ['description' => 'Direction de l’exécution des travaux', 'quantity' => '1.000', 'unit' => 'forfait', 'unit_price' => '5000.000'],
        ];
        $jasminsLines = [
            ['description' => 'Études préliminaires et esquisse', 'quantity' => '1.000', 'unit' => 'forfait', 'unit_price' => '9000.000'],
            ['description' => 'Avant-projet sommaire et détaillé', 'quantity' => '1.000', 'unit' => 'forfait', 'unit_price' => '16000.000'],
            ['description' => 'Dossier de permis de bâtir', 'quantity' => '1.000', 'unit' => 'forfait', 'unit_price' => '11000.000'],
            ['description' => 'Suivi de chantier (mission partielle)', 'quantity' => '1.000', 'unit' => 'forfait', 'unit_price' => '26000.000'],
        ];

        return [
            [
                'key' => 'devis-villa',
                'document_type' => 'quote',
                'locale' => 'fr',
                'client' => 'khelifi',
                'project' => 'villa-khelifi',
                'title' => 'Mission complète — Villa Khelifi',
                'issue_offset' => -150,
                'reference' => null,
                'billing_kind' => '',
                'phase' => null,
                'notes' => 'Données de démonstration — devis accepté par le maître d’ouvrage.',
                'lines' => $villaLines,
                'payments' => [],
            ],
            [
                'key' => 'contrat-villa',
                'document_type' => 'contract',
                'locale' => 'fr',
                'client' => 'khelifi',
                'project' => 'villa-khelifi',
                'title' => 'Contrat d’architecte — Villa Khelifi',
                'issue_offset' => -145,
                'reference' => 'devis-villa',
                'billing_kind' => '',
                'phase' => null,
                'notes' => 'Données de démonstration — contrat signé, mission complète.',
                'lines' => $villaLines,
                'payments' => [],
            ],
            [
                'key' => 'facture-villa-aps',
                'document_type' => 'invoice',
                'locale' => 'fr',
                'client' => 'khelifi',
                'project' => 'villa-khelifi',
                'title' => 'Facture — avant-projet sommaire (villa Khelifi)',
                'issue_offset' => -95,
                'reference' => 'contrat-villa',
                'billing_kind' => 'phase',
                'phase' => 'aps',
                'notes' => 'Données de démonstration — facture de phase réglée par virement.',
                'lines' => [
                    ['description' => 'Honoraires — avant-projet sommaire (APS)', 'quantity' => '1.000', 'unit' => 'forfait', 'unit_price' => '4200.000'],
                ],
                'payments' => [
                    ['percent' => '100', 'offset' => -80, 'method' => 'bank_transfer', 'reference' => 'VIR-DEMO-0142', 'note' => 'Règlement de la facture de phase'],
                ],
            ],
            [
                'key' => 'facture-medina-retard',
                'document_type' => 'invoice',
                'locale' => 'fr',
                'client' => 'medina',
                'project' => 'medina',
                'title' => 'Facture — avant-projet détaillé (Medina Invest)',
                'issue_offset' => -75,
                'reference' => null,
                'billing_kind' => 'phase',
                'phase' => 'apd',
                'notes' => 'Données de démonstration — facture échue, relance à effectuer.',
                'lines' => [
                    ['description' => 'Honoraires — avant-projet détaillé (APD)', 'quantity' => '1.000', 'unit' => 'forfait', 'unit_price' => '18000.000'],
                ],
                'payments' => [],
            ],
            [
                'key' => 'devis-jasmins',
                'document_type' => 'quote',
                'locale' => 'fr',
                'client' => 'jasmins',
                'project' => 'jasmins',
                'title' => 'Mission complète — Résidence Les Jasmins',
                'issue_offset' => -45,
                'reference' => null,
                'billing_kind' => '',
                'phase' => null,
                'notes' => 'Données de démonstration — devis en cours de validité.',
                'lines' => $jasminsLines,
                'payments' => [],
            ],
            [
                'key' => 'contrat-jasmins',
                'document_type' => 'contract',
                'locale' => 'fr',
                'client' => 'jasmins',
                'project' => 'jasmins',
                'title' => 'Contrat d’architecte — Résidence Les Jasmins',
                'issue_offset' => -42,
                'reference' => 'devis-jasmins',
                'billing_kind' => '',
                'phase' => null,
                'notes' => 'Données de démonstration — contrat signé après acceptation du devis.',
                'lines' => $jasminsLines,
                'payments' => [],
            ],
            [
                'key' => 'facture-jasmins-acompte',
                'document_type' => 'invoice',
                'locale' => 'fr',
                'client' => 'jasmins',
                'project' => 'jasmins',
                'title' => 'Facture d’acompte — Résidence Les Jasmins',
                'issue_offset' => -35,
                'reference' => 'contrat-jasmins',
                'billing_kind' => 'deposit',
                'phase' => null,
                'notes' => 'Données de démonstration — acompte partiellement réglé par chèque.',
                'lines' => [
                    ['description' => 'Acompte de 20 % sur les honoraires de mission', 'quantity' => '1.000', 'unit' => 'forfait', 'unit_price' => '12400.000'],
                ],
                'payments' => [
                    ['percent' => '50', 'offset' => -18, 'method' => 'cheque', 'reference' => 'CHQ-DEMO-204511', 'note' => 'Premier encaissement de l’acompte'],
                ],
            ],
            [
                'key' => 'devis-ferchichi-ar',
                'document_type' => 'quote',
                'locale' => 'ar',
                'client' => 'ferchichi',
                'project' => 'ferchichi',
                'title' => 'عرض أسعار — تجديد شقة ببنابل',
                'issue_offset' => -20,
                'reference' => null,
                'billing_kind' => '',
                'phase' => null,
                'notes' => 'Données de démonstration — devis bilingue, modèle arabe.',
                'lines' => [
                    ['description' => 'الدراسة الأولية والتصميم', 'quantity' => '1.000', 'unit' => 'المجموع', 'unit_price' => '3200.000'],
                    ['description' => 'ملف رخصة البناء', 'quantity' => '1.000', 'unit' => 'المجموع', 'unit_price' => '1500.000'],
                ],
                'payments' => [],
            ],
            [
                'key' => 'facture-jasmins-apd',
                'document_type' => 'invoice',
                'locale' => 'fr',
                'client' => 'jasmins',
                'project' => 'jasmins',
                'title' => 'Facture — avant-projet détaillé (Les Jasmins)',
                'issue_offset' => -14,
                'reference' => 'contrat-jasmins',
                'billing_kind' => 'phase',
                'phase' => 'apd',
                'notes' => 'Données de démonstration — facture de phase en attente de règlement.',
                'lines' => [
                    ['description' => 'Honoraires — avant-projet détaillé (APD)', 'quantity' => '1.000', 'unit' => 'forfait', 'unit_price' => '16000.000'],
                ],
                'payments' => [],
            ],
            [
                'key' => 'avoir-jasmins',
                'document_type' => 'credit_note',
                'locale' => 'fr',
                'client' => 'jasmins',
                'project' => 'jasmins',
                'title' => 'Avoir — remise commerciale sur l’acompte',
                'issue_offset' => -8,
                'reference' => 'facture-jasmins-acompte',
                'billing_kind' => '',
                'phase' => null,
                'notes' => 'Données de démonstration — remise accordée après réunion de chantier.',
                'lines' => [
                    ['description' => 'Remise commerciale sur les honoraires de mission', 'quantity' => '1.000', 'unit' => 'forfait', 'unit_price' => '1500.000'],
                ],
                'payments' => [],
            ],
            [
                'key' => 'devis-elbahr-brouillon',
                'document_type' => 'quote',
                'locale' => 'fr',
                'client' => 'elbahr',
                'project' => 'elbahr',
                'title' => 'Étude de faisabilité — extension hôtel El Bahr',
                'issue_offset' => null,
                'reference' => null,
                'billing_kind' => '',
                'phase' => null,
                'notes' => 'Données de démonstration — brouillon non émis, mission suspendue.',
                'lines' => [
                    ['description' => 'Étude de faisabilité et pré-diagnostic', 'quantity' => '1.000', 'unit' => 'forfait', 'unit_price' => '5600.000'],
                    ['description' => 'Relevé de l’existant', 'quantity' => '3.000', 'unit' => 'jour', 'unit_price' => '450.000'],
                ],
                'payments' => [],
            ],
        ];
    }

    /**
     * Volumétrie attendue, utilisée par le script et par les tests.
     *
     * @return array<string,int>
     */
    public function counts(): array
    {
        $contacts = 0;
        foreach ($this->clients() as $client) {
            $contacts += count($client['contacts']);
        }

        return [
            'users' => count($this->users()),
            'hourly_rates' => count($this->rates()),
            'clients' => count($this->clients()),
            'client_contacts' => $contacts,
            'projects' => count($this->projects()),
            'time_entries' => count($this->timeEntries()),
            'documents' => count($this->documents()),
            'document_payments' => array_sum(array_map(
                static fn (array $document): int => count($document['payments']),
                $this->documents()
            )),
        ];
    }
}
