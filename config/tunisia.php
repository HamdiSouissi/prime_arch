<?php
declare(strict_types=1);

/**
 * Paramètres propres à la Tunisie.
 *
 * Ces valeurs sont des RÉGLAGES MODIFIABLES : elles ne doivent jamais être codées en dur
 * dans le code métier (toujours passer par config('tunisia.…')). À terme, elles pourront
 * être surchargées depuis la table `settings`.
 *
 * ⚠ Les taux, formats et règles réglementaires ci-dessous sont indicatifs :
 *    à faire valider par un expert-comptable / l'Ordre des Architectes de Tunisie (OAT).
 */
return [
    'country'  => 'TN',
    'timezone' => 'Africa/Tunis',
    'locale'   => 'fr_TN',

    // Monnaie : le dinar tunisien se divise en 1000 millimes (3 décimales).
    'currency' => [
        'code'                => 'TND',
        'symbol'              => 'DT',
        'decimals'            => 3,
        'decimal_separator'   => ',',
        'thousands_separator' => ' ',
        'symbol_after'        => true,
    ],

    // TVA (taux en %). Le taux par défaut s'applique aux honoraires d'architecte.
    'vat' => [
        'default' => 19,
        'rates'   => [19, 13, 7, 0],
    ],

    // Autres taxes / droits (TODO : valider les valeurs et leur applicabilité).
    'taxes' => [
        'stamp_duty'      => 1.000,  // timbre fiscal (DT) — TODO
        'withholding_tax' => 0,      // retenue à la source (%) — TODO
        'fodec'           => 0,      // TODO
    ],

    // Matricule fiscal : 7 chiffres + clé (lettre) [+ code TVA / catégorie / n° d'établissement].
    // Expression indicative, à affiner.
    'tax_id' => [
        'label'   => 'Matricule fiscal',
        'pattern' => '/^\d{7}[A-Z](\/?[ABDNP]\/?[MPCNE]\/?\d{3})?$/i',
        'example' => '1234567/A/M/000',
    ],

    // Téléphone : indicatif +216, 8 chiffres.
    'phone' => [
        'country_code' => '+216',
        'pattern'      => '/^(?:\+216|00216)?\s?[2-579]\d{1}\s?\d{3}\s?\d{3}$/',
        'example'      => '+216 20 123 456',
    ],

    'postal_code' => [
        'pattern' => '/^\d{4}$/',
    ],

    'date_format'     => 'd/m/Y',
    'datetime_format' => 'd/m/Y H:i',
    'week_start'      => 'monday',

    // Les 24 gouvernorats : voir database/seeds/governorates.php
    // Les délégations : voir database/seeds/delegations.php (TODO)

    // Contexte architecture (TODO : à compléter avec les règles réglementaires en vigueur).
    'architecture' => [
        'professional_body' => 'Ordre des Architectes de Tunisie (OAT)',
        // Phases types d'un projet (modifiables depuis les réglages)
        'phases' => [
            'esquisse'        => 'Esquisse',
            'aps'             => 'Avant-projet sommaire (APS)',
            'apd'             => 'Avant-projet détaillé (APD)',
            'permis'          => 'Dossier de permis de bâtir',
            'dce'             => 'Dossier de consultation des entreprises (DCE)',
            'suivi_chantier'  => 'Suivi de chantier',
            'reception'       => 'Réception des travaux',
        ],
        // Documents administratifs à gérer (TODO : modèles PDF)
        'documents' => ['devis', 'facture', 'contrat', 'avenant', 'pv_reception'],
    ],
];
