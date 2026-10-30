#!/usr/bin/env php
<?php
declare(strict_types=1);

/**
 * Alimente l'application avec un jeu de données de démonstration : php bin/seed-demo.php
 *
 * Le script est réexécutable : les éléments déjà présents sont conservés. Il refuse de
 * s'exécuter sur un environnement `production` sans l'option `--force`, car les comptes
 * créés partagent un mot de passe de démonstration.
 */

if (PHP_SAPI !== 'cli') {
    exit('Ce script doit être lancé en ligne de commande.');
}

$root = dirname(__DIR__);
require $root . '/vendor/autoload.php';
define('BASE_PATH', $root);

use App\Core\Config;
use App\Core\Env;
use App\Services\CoreSeeder;
use App\Services\DemoDataSeeder;
use App\Services\DemoDataset;
use InvalidArgumentException;

Env::load($root . '/.env');
Config::load($root . '/config');

date_default_timezone_set((string) Config::get('tunisia.timezone', 'Africa/Tunis'));

$options = ['force' => false, 'today' => null, 'password' => null, 'help' => false];
foreach (array_slice($argv, 1) as $argument) {
    if ($argument === '--force') {
        $options['force'] = true;
    } elseif ($argument === '--help' || $argument === '-h') {
        $options['help'] = true;
    } elseif (str_starts_with($argument, '--today=')) {
        $options['today'] = substr($argument, 8);
    } elseif (str_starts_with($argument, '--password=')) {
        $options['password'] = substr($argument, 11);
    } else {
        fwrite(STDERR, 'Option inconnue : ' . $argument . "\n");
        exit(2);
    }
}

if ($options['help']) {
    echo <<<'TXT'
Usage : php bin/seed-demo.php [--force] [--today=AAAA-MM-JJ] [--password=MOT_DE_PASSE]

  --force              autorise l'exécution alors que APP_ENV vaut « production »
  --today=DATE         décale la date de référence du jeu (démonstration figée, tests)
  --password=SECRET    mot de passe commun aux comptes créés (12 caractères minimum) ;
                       à défaut, un mot de passe aléatoire est généré et affiché une seule fois

À lancer après php bin/migrate.php. Le script précharge d'abord les référentiels du noyau.
TXT;
    exit(0);
}

$environment = (string) Config::get('app.env', 'production');
if ($environment === 'production' && !$options['force']) {
    fwrite(STDERR, "Refus : APP_ENV vaut « production ».\n");
    fwrite(STDERR, "Les comptes de démonstration partagent un mot de passe : utilisez un environnement local\n");
    fwrite(STDERR, "ou relancez avec --force si cette base est bien une base de démonstration.\n");
    exit(1);
}

try {
    $dataset = new DemoDataset($options['today']);
    $password = $options['password'];
    $generated = false;
    if ($password !== null && mb_strlen($password, 'UTF-8') < 12) {
        throw new InvalidArgumentException('Le mot de passe fourni doit contenir au moins 12 caractères.');
    }
    if ($dataset->today() > date('Y-m-d')) {
        throw new InvalidArgumentException('La date de référence ne peut pas être future.');
    }
    if ($password === null) {
        $password = 'demo-' . bin2hex(random_bytes(6)) . '-archi';
        $generated = true;
    }

    echo "Préchargement des référentiels…\n";
    foreach ((new CoreSeeder())->run() as $table => $count) {
        printf("  %-24s %d\n", $table . ':', $count);
    }

    echo "Insertion du jeu de démonstration (version " . DemoDataset::VERSION . ", référence " . $dataset->today() . ")…\n";
    $report = (new DemoDataSeeder())->run($password, $options['today']);
    foreach ($report['steps'] as $table => $counts) {
        printf(
            "  %-24s %d créé(s), %d déjà présent(s)\n",
            $table . ':',
            $counts['created'],
            $counts['existing']
        );
    }
    foreach ($report['warnings'] as $warning) {
        echo '  ! ' . $warning . "\n";
    }

    echo "\nComptes de démonstration (mot de passe commun) :\n";
    foreach ($dataset->users() as $user) {
        printf("  %-24s %-34s %s\n", $user['role'], $user['email'], $user['locale']);
    }
    if ($generated) {
        echo "\nMot de passe généré : " . $password . "\n";
        echo "Il n'est pas conservé : notez-le maintenant ou relancez avec --password=… pour d'autres comptes.\n";
    }
    echo "\nLes tables des modules installables ne sont pas alimentées : la démonstration porte sur le noyau.\n";
    echo "Données de démonstration : toute donnée réelle doit être saisie dans une base distincte.\n";
} catch (Throwable $error) {
    fwrite(STDERR, 'Erreur de démonstration : ' . $error->getMessage() . "\n");
    exit(1);
}
