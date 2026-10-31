<?php
declare(strict_types=1);

namespace Tests;

use PHPUnit\Framework\TestCase;

/**
 * Garde-fous de mise en ligne : le modèle d'environnement, l'archive de livraison et le
 * diagnostic doivent rester sûrs sans qu'on ait à les relire à chaque déploiement.
 *
 * Ces tests sont statiques : ils n'exigent ni base de données, ni Composer, ni accès réseau.
 */
final class DeploymentGuideTest extends TestCase
{
    private static function file(string $relativePath): string
    {
        $contents = file_get_contents(BASE_PATH . $relativePath);
        self::assertIsString($contents, 'Fichier introuvable : ' . $relativePath);

        return $contents;
    }

    /** Le modèle d'environnement décrit une production sûre et le bon nom de produit. */
    public function testEnvironmentTemplateIsProductionSafe(): void
    {
        $env = self::file('/.env.example');

        self::assertStringContainsString('APP_NAME="Prime Architecture"', $env);
        self::assertStringContainsString('APP_ENV=production', $env);
        self::assertStringContainsString('APP_DEBUG=false', $env);
        self::assertStringContainsString('APP_URL=', $env);
        self::assertStringContainsString('APP_KEY=', $env, 'La clé reste à générer : aucune valeur par défaut.');
        self::assertStringNotContainsString('APP_KEY="', $env, 'Aucune clé d’application ne doit être versionnée.');
        foreach (['DB_HOST', 'DB_NAME', 'DB_USER', 'DB_PASS', 'DB_CHARSET=utf8mb4', 'SESSION_NAME'] as $key) {
            self::assertStringContainsString($key, $env, $key);
        }
    }

    /** L'archive de livraison ne contient jamais de secret ni de fichier de développement. */
    public function testReleaseArchiveExcludesSecretsAndDevelopmentFiles(): void
    {
        $release = self::file('/bin/release.php');

        self::assertMatchesRegularExpression(
            '/const ALWAYS_EXCLUDED = \[(.*)\];/s',
            $release,
            'La liste des exclusions doit rester une constante lisible.'
        );
        preg_match('/const ALWAYS_EXCLUDED = \[(.*)\];/s', $release, $matches);
        $excluded = (string) ($matches[1] ?? '');
        foreach (["'.env'", "'tests'", "'phpunit.xml'", "'.git'", "'prime-arch-design.zip'"] as $entry) {
            self::assertStringContainsString($entry, $excluded, $entry . ' doit être exclu de l’archive.');
        }
        // Les contenus générés ne partent que sous forme de protections.
        self::assertStringContainsString('RUNTIME_DIRECTORIES', $release);
        foreach (['storage/logs', 'storage/uploads', 'storage/pdf', 'storage/backups'] as $runtime) {
            self::assertStringContainsString("'" . $runtime . "'", $release, $runtime);
        }
        self::assertStringContainsString("'.htaccess'", $release);
        self::assertStringContainsString('--dry-run', $release);
    }

    /** Le diagnostic de mise en ligne ne modifie rien : ni base, ni fichier. */
    public function testPreflightOnlyReads(): void
    {
        $preflight = self::file('/bin/preflight.php');

        foreach (['INSERT INTO', 'UPDATE ', 'DELETE FROM', 'file_put_contents', 'unlink(', 'mkdir(', "fopen('w"] as $write) {
            self::assertStringNotContainsString($write, $preflight, 'bin/preflight.php doit rester en lecture seule : ' . $write);
        }
        self::assertStringContainsString("PHP_SAPI !== 'cli'", $preflight, 'Réservé à la ligne de commande.');
        foreach (['password_algos', 'APP_KEY', 'storage/', 'database/migrations', 'administrator'] as $needle) {
            self::assertStringContainsString($needle, $preflight, $needle);
        }
        self::assertStringContainsString('exit($errors === 0 ? 0 : 1);', $preflight);
    }

    /** Le guide couvre toutes les étapes, dans l'ordre où elles doivent être exécutées. */
    public function testDeploymentGuideCoversEveryStep(): void
    {
        $guide = self::file('/docs/DEPLOIEMENT-IONOS.md');

        foreach ([
            'suite.primevisionsolutions.fr',
            'composer install --no-dev',
            'bin/release.php',
            'bin/preflight.php',
            'bin/migrate.php',
            'bin/seed.php',
            'bin/create-admin.php',
            'bin/backup.php',
            'APP_KEY',
            'APP_DEBUG=false',
            'primearch_session',
            'php8.2',
            'HTTPS',
            'prime-arch/public',
            '.htaccess',
        ] as $needle) {
            self::assertStringContainsString($needle, $guide, 'Étape manquante dans le guide : ' . $needle);
        }

        // Les trois règles de sécurité non négociables sont rappelées.
        self::assertStringContainsString('Le fichier `.env` n\'est **jamais** accessible', $guide);
        self::assertStringContainsString('`APP_DEBUG` reste à `false`', $guide);
        self::assertStringContainsString('403 ou', $guide, 'La vérification d’accès au .env doit être décrite.');

        // Le guide est référencé depuis le README.
        self::assertStringContainsString('docs/DEPLOIEMENT-IONOS.md', self::file('/README.md'));
    }
}
