<?php
declare(strict_types=1);

use App\Core\Env;

/**
 * Configuration générale de l'application.
 * Les valeurs proviennent du fichier .env (voir .env.example).
 */
return [
    'name'    => Env::get('APP_NAME', 'Prime Arch'),
    'env'     => Env::get('APP_ENV', 'production'),
    'debug'   => (bool) Env::get('APP_DEBUG', false),
    'url'     => Env::get('APP_URL', 'http://localhost:8000'),
    'key'     => Env::get('APP_KEY', ''),
    'locale'  => Env::get('APP_LOCALE', 'fr'),
    'locales' => ['fr', 'ar'],
    'version' => '0.1.0',
    'session' => [
        'name'     => Env::get('SESSION_NAME', 'primearch_session'),
        'lifetime' => (int) Env::get('SESSION_LIFETIME', 7200),
    ],
];
