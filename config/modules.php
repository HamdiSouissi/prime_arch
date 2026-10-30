<?php
declare(strict_types=1);

/**
 * Réglages historiques conservés pour compatibilité de configuration.
 * Depuis l'administration des modules, la table `modules` est la seule source d'état;
 * la liste `enabled` n'est plus utilisée par ModuleManager.
 */
return [
    'directory' => 'modules',
    'enabled' => [],
];
