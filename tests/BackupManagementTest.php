<?php
declare(strict_types=1);

namespace Tests;

use App\Core\Response;
use App\Services\BackupService;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * Onglet « Sauvegardes » des réglages.
 *
 * Ce que ces tests protègent :
 *
 * 1. **Format unique** : toute sauvegarde est une archive `.zip` ; l'import refuse tout
 *    autre fichier, y compris un `.zip` renommé qui ne porte pas la signature ZIP.
 * 2. **Chemin maîtrisé** : une archive ne sort jamais de `storage/backups`, n'est jamais
 *    servie depuis `public/` et le téléchargement est transmis par flux.
 * 3. **Restauration explicite** : le mot `RESTAURER` est exigé côté serveur et une
 *    sauvegarde de sécurité de l'état courant précède toute destruction.
 * 4. **Droits** : l'onglet et ses routes restent réservés aux administrateurs.
 * 5. **Traductions** : chaque clé affichée existe en français et en arabe.
 *
 * Tests statiques et unitaires, exécutables sans base de données ni archive réelle.
 */
final class BackupManagementTest extends TestCase
{
    private static function file(string $relativePath): string
    {
        $contents = file_get_contents(BASE_PATH . $relativePath);
        self::assertIsString($contents, 'Fichier introuvable : ' . $relativePath);

        return $contents;
    }

    public function testServiceExposesTheWholeArchiveLifecycle(): void
    {
        foreach (['create', 'verifyArchive', 'restore', 'restoreInPlace', 'list', 'import', 'delete', 'bundle'] as $method) {
            self::assertTrue(
                method_exists(BackupService::class, $method),
                'Méthode attendue sur BackupService : ' . $method
            );
        }
        self::assertTrue((new \ReflectionMethod(BackupService::class, 'restoreInPlace'))->isPublic());
        self::assertTrue((new \ReflectionMethod(BackupService::class, 'list'))->isPublic());
    }

    public function testListingStaysInsidePrivateStorageAndKeepsOnlyZipArchives(): void
    {
        $service = self::file('/app/Services/BackupService.php');
        self::assertStringContainsString("BASE_PATH . '/storage/backups'", $service);
        self::assertStringContainsString('strtolower(pathinfo($name, PATHINFO_EXTENSION)) !== \'zip\'', $service);
        self::assertStringContainsString('is_link($path)', $service);
        self::assertStringContainsString("'path' => 'storage/backups/' . \$candidate['name']", $service);
        self::assertStringContainsString('describeArchive', $service, 'La liste relit le manifeste sans décompresser.');
        self::assertSame([], (new BackupService())->list(), 'Sans dossier de sauvegardes, la liste est vide.');
    }

    public function testImportOnlyAcceptsRealZipArchives(): void
    {
        $service = self::file('/app/Services/BackupService.php');
        self::assertStringContainsString('is_uploaded_file($temporaryPath)', $service);
        self::assertStringContainsString('move_uploaded_file($temporaryPath, $destination)', $service);
        self::assertStringContainsString('UPLOAD_ERR_OK', $service);
        self::assertStringContainsString(
            "strtolower(pathinfo(\$originalName, PATHINFO_EXTENSION)) !== 'zip'",
            $service,
            'L’extension .zip est exigée sur le nom d’origine.'
        );
        self::assertStringContainsString(
            '"PK\\x03\\x04"',
            $service,
            'La signature ZIP est vérifiée, pas seulement l’extension.'
        );
        self::assertStringContainsString('MAX_IMPORT_BYTES', $service);
        self::assertStringContainsString('chmod($destination, 0600)', $service);
        // Une archive non conforme est refusée puis supprimée : elle n'est jamais listée.
        self::assertStringContainsString('$this->verifyArchive($destination)', $service);
        self::assertStringContainsString('@unlink($destination)', $service);
    }

    /**
     * L'interface ne transmet que le nom de l'archive : il doit être résolu dans
     * `storage/backups`, sinon téléchargement et suppression répondaient
     * « L'archive doit se trouver dans storage/backups ».
     */
    public function testBareArchiveNameIsResolvedInsidePrivateStorage(): void
    {
        $root = BASE_PATH . '/storage/backups';
        if (!is_dir($root) && !mkdir($root, 0700, true) && !is_dir($root)) {
            self::markTestSkipped('Dossier storage/backups non créable dans cet environnement.');
        }
        $name = 'prime-arch-20260101-120000-0a1b2c3d.zip';
        $file = $root . '/' . $name;
        file_put_contents($file, "PK\x05\x06" . str_repeat("\0", 18));

        try {
            $service = new BackupService();
            $normalized = static fn (string $path): string => str_replace('\\', '/', $path);
            self::assertStringEndsWith('/storage/backups/' . $name, $normalized($service->resolveArchivePath($name)));
            self::assertStringEndsWith(
                '/storage/backups/' . $name,
                $normalized($service->resolveArchivePath('storage/backups/' . $name)),
                'Un chemin relatif complet reste accepté.'
            );

            foreach (['../' . $name, 'storage/backups/../backups/' . $name . '/x', '/etc/passwd', $name . "/..", '.htaccess'] as $forbidden) {
                $refused = false;
                try {
                    $service->resolveArchivePath($forbidden);
                } catch (RuntimeException) {
                    $refused = true;
                }
                self::assertTrue($refused, 'Chemin refusé attendu : ' . $forbidden);
            }
        } finally {
            @unlink($file);
        }
    }

    public function testImportedArchiveGetsAGeneratedName(): void
    {
        $service = self::file('/app/Services/BackupService.php');
        self::assertStringContainsString("'prime-arch-import-' . gmdate('Ymd-His')", $service);
        self::assertStringContainsString('bin2hex(random_bytes(8))', $service);
        // Le nom fourni par le navigateur n'est jamais réutilisé comme chemin d'écriture.
        self::assertStringDoesNotContainDestinationFromClientName($service);
    }

    private static function assertStringDoesNotContainDestinationFromClientName(string $service): void
    {
        self::assertDoesNotMatchRegularExpression(
            '/destination\s*=\s*\$directory\s*\.\s*\/\'?\s*\.\s*\$upload/',
            $service,
            'Le nom d’origine ne doit pas construire le chemin de destination.'
        );
    }

    public function testInPlaceRestoreRequiresConfirmationAndCreatesASafetyArchiveFirst(): void
    {
        $service = self::file('/app/Services/BackupService.php');
        self::assertStringContainsString('if (!$confirm)', $service);
        $safety = strpos($service, '$safety = $this->create();');
        $drop = strpos($service, 'self::dropExistingTables($pdo);');
        self::assertTrue(is_int($safety) && is_int($drop), 'La restauration en place doit créer puis remplacer.');
        self::assertTrue($safety < $drop, 'La sauvegarde de sécurité précède la destruction de la base.');
        // La validation complète de l'archive intervient avant la moindre écriture.
        self::assertTrue(
            strpos($service, '$inspected = self::inspectArchive($archivePath);') < $safety,
            'L’archive est vérifiée avant toute modification.'
        );
    }

    public function testInPlaceRestoreReplacesDatabaseAndPrivateStorage(): void
    {
        $service = self::file('/app/Services/BackupService.php');
        foreach (['dropExistingTables', 'purgeStorageTargets', 'installStoragePayload', 'extractStoragePayload'] as $step) {
            self::assertStringContainsString($step, $service);
        }
        self::assertStringContainsString("SET FOREIGN_KEY_CHECKS = 0", $service);
        self::assertStringContainsString("SET FOREIGN_KEY_CHECKS = 1", $service);
        self::assertStringContainsString("'safety' => basename(\$safety)", $service, 'L’audit nomme la sauvegarde de sécurité.');
    }

    public function testRestoreIsRefusedWithoutTheConfirmationFlag(): void
    {
        $refused = false;
        try {
            (new BackupService())->restoreInPlace('storage/backups/inconnue.zip', false);
        } catch (RuntimeException) {
            $refused = true;
        }
        self::assertTrue($refused, 'Sans confirmation explicite, la restauration est refusée.');
    }

    public function testBackupRoutesAreAdministratorOnlyAndCsrfProtected(): void
    {
        $routes = self::file('/config/routes.php');
        foreach ([
            "post('/settings/backups'",
            "post('/settings/backups/import'",
            "post('/settings/backups/download'",
            "post('/settings/backups/delete'",
            "post('/settings/backups/restore'",
        ] as $route) {
            self::assertStringContainsString($route, $routes);
        }
        preg_match_all('/\$router->post\(\'\/settings\/backups[^\n]*/', $routes, $mutations);
        self::assertCount(5, $mutations[0], 'Cinq actions POST pour les sauvegardes.');
        foreach ($mutations[0] as $line) {
            self::assertStringContainsString('AdminMiddleware::class', $line, 'Réservé aux administrateurs : ' . $line);
            self::assertStringContainsString('CsrfMiddleware::class', $line, 'Jeton CSRF exigé : ' . $line);
        }
        self::assertStringContainsString(
            "get('/settings/backups/download/{name}', [BackupController::class, 'download'], [AuthMiddleware::class, AdminMiddleware::class])",
            $routes,
            'Le téléchargement reste une lecture authentifiée.'
        );
        self::assertStringNotContainsString("get('/settings/backups/delete'", $routes, 'La suppression n’est jamais un GET.');
        self::assertStringNotContainsString("get('/settings/backups/restore'", $routes, 'La restauration n’est jamais un GET.');
    }

    public function testDownloadsAreStreamedAndNeverServedFromPublic(): void
    {
        $response = self::file('/app/Core/Response.php');
        self::assertStringContainsString('public static function fileDownload(', $response);
        self::assertStringContainsString('fpassthru($handle)', $response, 'Le fichier est transmis par flux.');
        self::assertStringContainsString('@unlink($this->streamPath)', $response, 'L’archive de transit est supprimée après envoi.');
        self::assertStringContainsString('is_link($path)', $response, 'Un lien symbolique n’est jamais transmis.');
        self::assertStringContainsString("'Cache-Control', 'private, no-store, max-age=0'", $response);

        $controller = self::file('/app/Controllers/BackupController.php');
        self::assertStringContainsString('Response::fileDownload(', $controller);
        self::assertStringContainsString('resolveArchivePath(', $controller);
        self::assertStringNotContainsString('readfile(', $controller);
        self::assertStringNotContainsString('public/', $controller, 'Aucune archive n’est servie depuis public/.');

        $refused = false;
        try {
            Response::fileDownload('/aucun/fichier.zip', 'x.zip');
        } catch (RuntimeException) {
            $refused = true;
        }
        self::assertTrue($refused, 'Un fichier absent n’est jamais transmis.');
    }

    public function testControllerRequiresExplicitRestoreConfirmation(): void
    {
        $controller = self::file('/app/Controllers/BackupController.php');
        self::assertStringContainsString("public const CONFIRMATION_WORD = 'RESTAURER';", $controller);
        self::assertStringContainsString("if (\$confirmation !== self::CONFIRMATION_WORD)", $controller);
        self::assertStringContainsString("Auth::can('settings.manage')", $controller);
        self::assertStringContainsString('restoreInPlace($name, true)', $controller);
        // Les fichiers importés passent par la clé `archive` du formulaire.
        self::assertStringContainsString("\$request->file('archive')", $controller);
    }

    public function testSettingsPageOffersEveryBackupAction(): void
    {
        $view = self::file('/app/Views/pages/app/settings.php');
        self::assertStringContainsString('data-tab-group', $view, 'Les réglages s’organisent en onglets.');
        self::assertStringContainsString('id="sauvegardes"', $view);
        self::assertStringContainsString('data-tab-target="sauvegardes"', $view);
        foreach ([
            "url('/settings/backups')",
            "url('/settings/backups/import')",
            "url('/settings/backups/download')",
            "url('/settings/backups/delete')",
            "url('/settings/backups/restore')",
        ] as $action) {
            self::assertStringContainsString($action, $view, 'Action manquante : ' . $action);
        }
        self::assertStringContainsString('accept=".zip,application/zip"', $view, 'Seules les archives .zip sont téléversées.');
        self::assertStringContainsString('name="confirmation"', $view, 'La restauration exige une confirmation écrite.');
        self::assertStringContainsString("data-selection-boxes='input[name=\"names[]\"]'", $view, 'Sélection multiple des archives.');
        self::assertStringContainsString('name="names[]"', $view);
        self::assertStringContainsString("__('backup.column_date')", $view);
        self::assertStringContainsString("__('backup.column_time')", $view);
        self::assertStringContainsString("__('backup.column_size')", $view);
        self::assertStringContainsString('RESTAURER', $view, 'Le mot de confirmation est affiché.');
        self::assertStringContainsString('enctype="multipart/form-data"', $view, 'L’import passe par un envoi multipart.');
    }

    public function testBackupTabIsReservedToAdministrators(): void
    {
        $controller = self::file('/app/Controllers/SettingsController.php');
        self::assertStringContainsString(
            "Auth::can('settings.manage') && Auth::can('users.manage')",
            $controller,
            'Les sauvegardes suivent les droits d’administration des comptes.'
        );
        self::assertStringContainsString("'backups' => \$canBackup ? (new BackupService())->list() : []", $controller);
        self::assertStringContainsString("'backupImportLimit' => self::formatBytes(self::backupUploadLimit())", $controller);
    }

    public function testUploadLimitFollowsPhpConfiguration(): void
    {
        $limit = \App\Controllers\SettingsController::backupUploadLimit();
        self::assertGreaterThan(0, $limit);
        self::assertLessThanOrEqual(268_435_456, $limit, 'Jamais au-delà de la limite applicative.');
        self::assertSame('256 Mio', \App\Controllers\SettingsController::formatBytes(268_435_456));
        self::assertSame('32 Mio', \App\Controllers\SettingsController::formatBytes(33_554_432));
    }

    public function testEveryBackupLabelIsTranslatedInFrenchAndArabic(): void
    {
        $sources = self::file('/app/Services/BackupService.php')
            . self::file('/app/Controllers/BackupController.php')
            . self::file('/app/Views/pages/app/settings.php');
        preg_match_all("/__\\('(backup\\.[a-z_]+)'/", $sources, $matches);
        $keys = array_values(array_unique($matches[1]));
        self::assertGreaterThan(30, count($keys), 'Les libellés de l’onglet sont couverts.');

        $fr = require BASE_PATH . '/lang/fr.php';
        $ar = require BASE_PATH . '/lang/ar.php';
        foreach ($keys as $key) {
            self::assertArrayHasKey($key, $fr, 'Clé française manquante : ' . $key);
            self::assertArrayHasKey($key, $ar, 'Clé arabe manquante : ' . $key);
        }
        self::assertArrayHasKey('settings.tab_backups', $fr);
        self::assertArrayHasKey('settings.tab_backups', $ar);
    }

    public function testAuditTrailCoversBackupManagement(): void
    {
        $fr = require BASE_PATH . '/lang/fr.php';
        foreach (['backup.created', 'backup.imported', 'backup.downloaded', 'backup.deleted', 'backup.restored'] as $event) {
            self::assertArrayHasKey('audit.event.' . $event, $fr, 'Événement d’audit traduit : ' . $event);
        }
        $controller = self::file('/app/Controllers/BackupController.php');
        self::assertStringContainsString("record('backup.downloaded'", $controller);
        $service = self::file('/app/Services/BackupService.php');
        self::assertStringContainsString("record('backup.imported'", $service);
        self::assertStringContainsString("record('backup.deleted'", $service);
    }
}
