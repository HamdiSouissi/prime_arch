<?php
declare(strict_types=1);

use App\Core\Env;

/**
 * Connexion MySQL 8 (InnoDB, utf8mb4).
 */
return [
    'host'      => Env::get('DB_HOST', '127.0.0.1'),
    'port'      => (int) Env::get('DB_PORT', 3306),
    'name'      => Env::get('DB_NAME', 'prime_arch'),
    'user'      => Env::get('DB_USER', 'root'),
    'pass'      => Env::get('DB_PASS', ''),
    'charset'   => Env::get('DB_CHARSET', 'utf8mb4'),
    'collation' => Env::get('DB_COLLATION', 'utf8mb4_unicode_ci'),
];
