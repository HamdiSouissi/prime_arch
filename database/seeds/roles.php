<?php
declare(strict_types=1);

/**
 * Rôles initiaux et permissions du noyau. Le rôle administrateur garde l'accès complet.
 */
return [
    ['slug' => 'administrator', 'label' => 'Administrateur', 'permissions' => ['*']],
    ['slug' => 'architect', 'label' => 'Architecte', 'permissions' => ['dashboard.view', 'clients.*', 'projects.*', 'time.*', 'documents.*', 'settings.view']],
    ['slug' => 'collaborator', 'label' => 'Collaborateur', 'permissions' => ['dashboard.view', 'clients.view', 'projects.view', 'projects.manage', 'time.view', 'time.manage']],
    ['slug' => 'accountant', 'label' => 'Comptable', 'permissions' => ['dashboard.view', 'clients.view', 'documents.*', 'settings.view']],
    ['slug' => 'read_only', 'label' => 'Lecture seule', 'permissions' => ['dashboard.view', 'clients.view', 'projects.view', 'documents.view', 'time.view', 'settings.view']],
];
