#!/usr/bin/env php
<?php
declare(strict_types=1);

/**
 * Contrôle d'installation avant mise en ligne : php bin/preflight.php
 *
 * Lecture seule : aucune écriture en base, aucun fichier créé. Chaque ligne s'affiche
 * « OK », « ATTENTION » ou « ERREUR » ; le code de sortie vaut 1 dès qu'une erreur est
 * relevée, ce qui permet de l'enchaîner dans un script de déploiement.
 */

if (PHP_SAPI !== 'cli') {
    exit('Ce script doit être lancé en ligne de commande.');
}

$root = dirname(__DIR__);
require $root . '/vendor/autoload.php';

define('BASE_PATH', $root);

use App\Core\Config;
use App\Core\Database;
use App\Core\Env;
use PDO;

Env::load($root . '/.env');
Config::load($root . '/config');

$errors = 0;
$warnings = 0;

/** Affiche une ligne de résultat et tient les compteurs à jour. */
function check(string $level, string $label, string $detail = ''): void
{
    global $errors, $warnings;
    if ($level === 'ERREUR') {
        $errors++;
    } elseif ($level === 'ATTENTION') {
        $warnings++;
    }
    $suffix = $detail === '' ? '' : ' — ' . $detail;
    printf("%-10s %s%s\n", $level, $label, $suffix);
}

echo "Contrôle d'installation — Prime Architecture\n";
echo "Racine : {$root}\n\n";

// ---------------------------------------------------------------- PHP
$phpOk = version_compare(PHP_VERSION, '8.2.0', '>=');
check($phpOk ? 'OK' : 'ERREUR', 'Version de PHP', PHP_VERSION . ($phpOk ? '' : ' (8.2 minimum)'));

foreach (['pdo', 'pdo_mysql', 'mbstring', 'fileinfo', 'json', 'zip'] as $extension) {
    $loaded = extension_loaded($extension);
    check($loaded ? 'OK' : 'ERREUR', 'Extension ' . $extension, $loaded ? '' : 'à activer dans le panneau d’hébergement');
}

$gd = extension_loaded('gd');
check($gd ? 'OK' : 'ATTENTION', 'Extension gd', $gd ? '' : 'recommandée pour les images');

/** Convertit une taille « 24M », « 512K » ou un nombre d'octets en octets. */
function ini_bytes(string $value): int
{
    $value = trim($value);
    if ($value === '') {
        return 0;
    }
    $number = (int) $value;
    return match (strtolower(substr($value, -1))) {
        'g' => $number * 1024 * 1024 * 1024,
        'm' => $number * 1024 * 1024,
        'k' => $number * 1024,
        default => $number,
    };
}

// Images de projet et images des champs personnalisés : 20 Mio par fichier.
$uploadMax = ini_bytes((string) ini_get('upload_max_filesize'));
$postMax = ini_bytes((string) ini_get('post_max_size'));
$uploadOk = $uploadMax >= 20 * 1048576 && $postMax >= 24 * 1048576;
check(
    $uploadOk ? 'OK' : 'ATTENTION',
    'Limites de téléversement',
    sprintf('upload_max_filesize=%s · post_max_size=%s', ini_get('upload_max_filesize'), ini_get('post_max_size'))
    . ($uploadOk ? '' : ' — prévoir 20M et 28M dans php.ini pour les images')
);

$argon = in_array('argon2id', password_algos(), true);
check($argon ? 'OK' : 'ERREUR', 'Argon2id', $argon ? '' : 'hachage des mots de passe indisponible sur ce PHP');

// ---------------------------------------------------------------- Configuration
$envFile = $root . '/.env';
if (is_file($envFile)) {
    check('OK', 'Fichier .env', 'présent');
    $mode = (int) (fileperms($envFile) & 0777);
    check($mode & 0004 ? 'ATTENTION' : 'OK', 'Permissions du .env', sprintf('%04o', $mode) . ($mode & 0004 ? ' (lisible par tous)' : ''));
} else {
    check('ERREUR', 'Fichier .env', 'absent : copier .env.example en .env et le renseigner');
}

$environment = strtolower((string) Config::get('app.env', 'production'));
$debug = (bool) Config::get('app.debug', false);
check($environment === 'production' ? 'OK' : 'ATTENTION', 'APP_ENV', $environment . ($environment === 'production' ? '' : ' (mettre production en ligne)'));
check($debug ? 'ERREUR' : 'OK', 'APP_DEBUG', $debug ? 'doit valoir false en ligne : sinon les erreurs s’affichent' : 'false');

$appUrl = (string) Config::get('app.url', '');
$urlProblem = '';
if ($appUrl === '') {
    $urlProblem = 'vide';
} elseif (!str_starts_with($appUrl, 'https://')) {
    $urlProblem = 'doit commencer par https:// en ligne';
} elseif (str_ends_with($appUrl, '/')) {
    $urlProblem = 'sans barre oblique finale';
} elseif (str_contains($appUrl, '/public')) {
    $urlProblem = 'sans /public (le sous-domaine pointe déjà sur public)';
}
check($urlProblem === '' ? 'OK' : 'ERREUR', 'APP_URL', $urlProblem === '' ? $appUrl : $appUrl . ' — ' . $urlProblem);

$key = (string) Config::get('app.key', '');
check(
    strlen($key) >= 32 ? 'OK' : 'ERREUR',
    'APP_KEY',
    strlen($key) >= 32 ? strlen($key) . ' octets' : 'absente ou trop courte (32 octets minimum)'
);

// ---------------------------------------------------------------- Fichiers
foreach (['/app', '/config', '/database', '/lang', '/modules', '/public/index.php', '/vendor/autoload.php'] as $path) {
    $exists = file_exists($root . $path);
    check($exists ? 'OK' : 'ERREUR', 'Présent : ' . $path, $exists ? '' : 'fichier ou dossier manquant à l’envoi');
}

foreach (['logs', 'cache', 'tmp', 'pdf', 'uploads', 'backups', 'modules'] as $directory) {
    $path = $root . '/storage/' . $directory;
    if (!is_dir($path)) {
        check('ERREUR', 'storage/' . $directory, 'dossier absent');
        continue;
    }
    $writable = is_writable($path);
    check($writable ? 'OK' : 'ERREUR', 'storage/' . $directory, $writable ? 'inscriptible' : 'non inscriptible (chmod 755, ou 775 si le serveur l’exige)');
}

// ---------------------------------------------------------------- Base de données
$connected = false;
try {
    $pdo = Database::connection();
    $connected = true;
    $version = (string) $pdo->query('SELECT VERSION()')->fetchColumn();
    $database = (string) $pdo->query('SELECT DATABASE()')->fetchColumn();
    check('OK', 'Connexion MySQL', $database . ' · ' . $version);

    $collation = (string) $pdo->query(
        "SELECT DEFAULT_COLLATION_NAME FROM information_schema.SCHEMATA WHERE SCHEMA_NAME = DATABASE()"
    )->fetchColumn();
    check(
        str_starts_with($collation, 'utf8mb4') ? 'OK' : 'ATTENTION',
        'Jeu de caractères de la base',
        $collation === '' ? 'inconnu' : $collation
    );

    $tables = (int) $pdo->query(
        'SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_TYPE = ' . "'BASE TABLE'"
    )->fetchColumn();
    check($tables > 0 ? 'OK' : 'ATTENTION', 'Tables', $tables . ' table(s)' . ($tables === 0 ? ' : exécuter php bin/migrate.php puis php bin/seed.php' : ''));

    if ($tables > 0) {
        $applied = $pdo->query('SELECT migration FROM migrations')->fetchAll(PDO::FETCH_COLUMN);
        $applied = array_map('strval', is_array($applied) ? $applied : []);
        $pending = [];
        foreach (glob($root . '/database/migrations/*.sql') ?: [] as $file) {
            $name = 'database/migrations/' . basename($file);
            if (!in_array($name, $applied, true)) {
                $pending[] = basename($file);
            }
        }
        check(
            $pending === [] ? 'OK' : 'ATTENTION',
            'Migrations du cœur',
            $pending === [] ? count($applied) . ' appliquée(s)' : count($pending) . ' en attente : ' . implode(', ', $pending)
        );

        // Le registre `migrations` peut être en avance sur le schéma réel (import partiel,
        // restauration de sauvegarde, ALTER interrompu). La fiche projet interroge les
        // colonnes ci-dessous : un écart se traduirait par une page d'erreur.
        $schemaGaps = [];
        $hasColumn = static function (PDO $pdo, string $table, string $column): bool {
            $statement = $pdo->prepare(
                'SELECT COUNT(*) FROM information_schema.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table_name AND COLUMN_NAME = :column_name'
            );
            $statement->execute(['table_name' => $table, 'column_name' => $column]);

            return (int) $statement->fetchColumn() > 0;
        };
        $hasTable = static function (PDO $pdo, string $table): bool {
            $statement = $pdo->prepare(
                'SELECT COUNT(*) FROM information_schema.TABLES
                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table_name'
            );
            $statement->execute(['table_name' => $table]);

            return (int) $statement->fetchColumn() > 0;
        };
        if ($hasTable($pdo, 'projects')) {
            if (!$hasColumn($pdo, 'projects', 'budget_hours')) {
                $schemaGaps[] = 'projects.budget_hours (migration 0008)';
            }
            if ($hasTable($pdo, 'project_phase_assignments')) {
                foreach (['weight_percent', 'budget_hours', 'progress_percent'] as $column) {
                    if (!$hasColumn($pdo, 'project_phase_assignments', $column)) {
                        $schemaGaps[] = 'project_phase_assignments.' . $column . ' (migration 0008)';
                    }
                }
            }
        }
        if (!$hasTable($pdo, 'project_images')) {
            $schemaGaps[] = 'table project_images (migration 0013)';
        } elseif (!$hasColumn($pdo, 'project_images', 'custom_field_key')) {
            $schemaGaps[] = 'project_images.custom_field_key (migration 0014)';
        }
        if (!$hasTable($pdo, 'project_files')) {
            $schemaGaps[] = 'table project_files (migration 0015)';
        }
        if ($hasTable($pdo, 'custom_field_definitions')) {
            $fieldType = $pdo->query(
                "SELECT COLUMN_TYPE FROM information_schema.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'custom_field_definitions'
                   AND COLUMN_NAME = 'field_type'"
            )->fetchColumn();
            $fieldType = is_string($fieldType) ? $fieldType : '';
            foreach (['image' => '0015', 'file' => '0015'] as $type => $migration) {
                if (!str_contains($fieldType, "'" . $type . "'")) {
                    $schemaGaps[] = 'custom_field_definitions.field_type « ' . $type . ' » (migration ' . $migration . ')';
                }
            }
        }
        check(
            $schemaGaps === [] ? 'OK' : 'ERREUR',
            'Schéma de la fiche projet',
            $schemaGaps === []
                ? 'projects, project_images, project_files et champs personnalisés à jour'
                : count($schemaGaps) . ' élément(s) manquant(s) : ' . implode(' · ', $schemaGaps)
                    . ' — exécuter php bin/migrate.php'
        );

        $enabled = (int) $pdo->query("SELECT COUNT(*) FROM modules WHERE is_enabled = 1")->fetchColumn();
        check('OK', 'Modules activés', (string) $enabled);

        $roles = (int) $pdo->query('SELECT COUNT(*) FROM roles')->fetchColumn();
        check($roles > 0 ? 'OK' : 'ATTENTION', 'Rôles', $roles . ' rôle(s)' . ($roles === 0 ? ' : exécuter php bin/seed.php' : ''));

        if ($roles > 0) {
            $administrators = (int) $pdo->query(
                "SELECT COUNT(*) FROM users u INNER JOIN roles r ON r.id = u.role_id
                 WHERE u.is_active = 1 AND r.name = 'administrator'"
            )->fetchColumn();
            check(
                $administrators > 0 ? 'OK' : 'ATTENTION',
                'Administrateur actif',
                $administrators > 0 ? (string) $administrators : 'aucun : exécuter php bin/create-admin.php'
            );
        }
    }
} catch (Throwable $error) {
    check('ERREUR', 'Connexion MySQL', $error->getMessage());
}

// ---------------------------------------------------------------- Résumé
echo "\n";
if (!$connected) {
    echo "La base n'est pas joignable : vérifier DB_HOST (nom d'hôte fourni par l'hébergeur,\n";
    echo "pas « localhost »), DB_NAME, DB_USER et DB_PASS dans .env.\n\n";
}
printf("%d erreur(s), %d avertissement(s).\n", $errors, $warnings);
if ($errors === 0) {
    echo "L'installation est prête.\n";
}

exit($errors === 0 ? 0 : 1);
