#!/usr/bin/env php
<?php
declare(strict_types=1);

/**
 * Crée le premier administrateur sans identifiant ni mot de passe par défaut.
 * Usage : php bin/create-admin.php
 */
if (PHP_SAPI !== 'cli') {
    exit('Ce script doit être lancé en ligne de commande.');
}

$root = dirname(__DIR__);
require $root . '/vendor/autoload.php';
define('BASE_PATH', $root);

use App\Core\Config;
use App\Core\Env;
use App\Models\UserRepository;
use App\Services\AuthService;

Env::load($root . '/.env');
Config::load($root . '/config');

/** Lit une ligne de saisie et retire les fins de ligne. */
function prompt(string $label): string
{
    fwrite(STDOUT, $label);
    $value = fgets(STDIN);
    return trim(is_string($value) ? $value : '');
}

/** Lit un secret sans l'afficher lorsque la commande est utilisée dans un terminal Unix. */
function promptSecret(string $label): string
{
    fwrite(STDOUT, $label);
    $isTerminal = function_exists('stream_isatty') && stream_isatty(STDIN);
    if ($isTerminal && PHP_OS_FAMILY !== 'Windows') {
        system('stty -echo');
        try {
            $value = fgets(STDIN);
        } finally {
            system('stty echo');
            fwrite(STDOUT, PHP_EOL);
        }
    } else {
        $value = fgets(STDIN);
        fwrite(STDOUT, PHP_EOL);
    }
    return is_string($value) ? rtrim($value, "\r\n") : '';
}

try {
    $users = new UserRepository();
    if ($users->countActiveAdministrators() > 0) {
        throw new RuntimeException('Un administrateur actif existe déjà. Créez les comptes suivants depuis /admin/users.');
    }
    if ($users->findRole('administrator') === null) {
        throw new RuntimeException('Rôle absent. Exécutez d’abord php bin/migrate.php puis php bin/seed.php.');
    }

    $name = prompt('Nom complet : ');
    $email = strtolower(prompt('Adresse courriel : '));
    if (mb_strlen($name, 'UTF-8') < 2 || mb_strlen($name, 'UTF-8') > 150) {
        throw new InvalidArgumentException('Le nom doit contenir entre 2 et 150 caractères.');
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 190) {
        throw new InvalidArgumentException('Adresse courriel invalide.');
    }

    $password = promptSecret('Mot de passe (12 caractères minimum) : ');
    $confirmation = promptSecret('Confirmer le mot de passe : ');
    if (!hash_equals($password, $confirmation)) {
        throw new InvalidArgumentException('Les mots de passe ne correspondent pas.');
    }

    $hash = AuthService::hashPassword($password);
    $id = $users->create($name, $email, $hash, 'administrator');
    printf("Administrateur créé (ID %d, %s). Connectez-vous sur %s/login.\n", $id, $email, rtrim((string) Config::get('app.url'), '/'));
} catch (Throwable $error) {
    fwrite(STDERR, 'Erreur : ' . $error->getMessage() . "\n");
    exit(1);
}
