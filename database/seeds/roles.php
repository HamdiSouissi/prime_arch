<?php
declare(strict_types=1);

/**
 * Rôles initiaux et permissions du noyau ; seul l’administrateur garde le joker global.
 * Les autres rôles énumèrent chaque capacité afin qu’une nouvelle permission ne leur soit
 * jamais accordée implicitement lors d’une évolution du noyau.
 */
return [
    ['slug' => 'administrator', 'label' => 'Administrateur', 'permissions' => ['*']],
    ['slug' => 'architect', 'label' => 'Architecte', 'permissions' => [
        'dashboard.view',
        'clients.view', 'clients.manage',
        'projects.view', 'projects.manage', 'projects.configure',
        'time.view', 'time.view_all', 'time.manage', 'time.manage_all',
        'documents.view', 'documents.create', 'documents.manage', 'documents.issue',
        'documents.payments.manage', 'documents.templates.manage',
    ]],
    ['slug' => 'collaborator', 'label' => 'Collaborateur', 'permissions' => [
        'dashboard.view', 'clients.view', 'projects.view', 'projects.manage', 'time.view', 'time.manage',
    ]],
    ['slug' => 'accountant', 'label' => 'Comptable', 'permissions' => [
        'dashboard.view', 'clients.view', 'projects.view',
        'documents.view', 'documents.create', 'documents.manage', 'documents.issue',
        'documents.payments.manage', 'documents.templates.manage',
        'finance.costs.view', 'finance.rates.view',
    ]],
    ['slug' => 'read_only', 'label' => 'Lecture seule', 'permissions' => [
        'dashboard.view', 'clients.view', 'projects.view', 'documents.view', 'time.view',
    ]],
];
