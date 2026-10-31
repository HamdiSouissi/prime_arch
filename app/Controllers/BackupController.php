<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Services\AuditLogger;
use App\Services\BackupService;
use RuntimeException;
use Throwable;

/**
 * Onglet « Sauvegardes » des réglages : créer, importer, télécharger, supprimer et
 * restaurer des archives `.zip`.
 *
 * Toutes les actions exigent `settings.manage` : une sauvegarde contient l'intégralité
 * des données du cabinet, et une restauration remplace la base et les fichiers privés.
 * Les archives ne sortent jamais de `storage/backups` : elles sont transmises par flux,
 * jamais servies depuis la racine publique.
 */
final class BackupController
{
    private const SETTINGS_URL = '/settings';
    private const TAB_ANCHOR = '#sauvegardes';

    /** Mot de confirmation à recopier avant toute restauration en place. */
    public const CONFIRMATION_WORD = 'RESTAURER';

    /** Crée une sauvegarde complète de la base et des fichiers privés. */
    public function create(Request $request): Response
    {
        if (!self::allowed()) {
            return Response::html(__('common.forbidden'), 403);
        }

        try {
            $path = (new BackupService())->create();
            Session::flash('settings.backup_notice', __('backup.created_notice', [
                'name' => basename($path),
            ]));
        } catch (Throwable $error) {
            self::fail($error);
        }

        return self::back();
    }

    /** Importe une archive `.zip` fournie par l'exploitant, puis la vérifie. */
    public function import(Request $request): Response
    {
        if (!self::allowed()) {
            return Response::html(__('common.forbidden'), 403);
        }

        $upload = $request->file('archive');
        if (!is_array($upload)) {
            Session::flash('settings.backup_errors', [__('backup.import_required')]);
            return self::back();
        }

        try {
            $result = (new BackupService())->import($upload);
            Session::flash('settings.backup_notice', __('backup.imported_notice', [
                'name' => $result['name'],
                'tables' => (string) $result['tables'],
                'files' => (string) $result['files'],
            ]));
        } catch (Throwable $error) {
            self::fail($error);
        }

        return self::back();
    }

    /** Télécharge une sauvegarde unique par son nom de fichier. */
    public function download(Request $request, string $name): Response
    {
        if (!self::allowed()) {
            return Response::html(__('common.forbidden'), 403);
        }

        try {
            $service = new BackupService();
            $path = $service->resolveArchivePath(rawurldecode($name));
            if (strtolower(pathinfo($path, PATHINFO_EXTENSION)) !== 'zip') {
                throw new RuntimeException(__('backup.archive_not_zip'));
            }
            (new AuditLogger())->record('backup.downloaded', 'backup', basename($path), [], Auth::id());

            return Response::fileDownload($path, basename($path), 'application/zip');
        } catch (Throwable $error) {
            Session::flash('settings.backup_errors', [self::message($error)]);
            return self::back();
        }
    }

    /** Télécharge plusieurs sauvegardes sélectionnées dans une archive `.zip` de transit. */
    public function downloadSelection(Request $request): Response
    {
        if (!self::allowed()) {
            return Response::html(__('common.forbidden'), 403);
        }

        $selection = $request->input('names', []);
        if (!is_array($selection)) {
            Session::flash('settings.backup_errors', [__('backup.selection_empty')]);
            return self::back();
        }

        try {
            $bundle = (new BackupService())->bundle(array_values(array_filter(
                $selection,
                static fn (mixed $name): bool => is_string($name) && $name !== ''
            )));
            (new AuditLogger())->record('backup.downloaded', 'backup', $bundle['filename'], [
                'archives' => (string) $bundle['count'],
            ], Auth::id());

            return Response::fileDownload($bundle['path'], $bundle['filename'], 'application/zip', true);
        } catch (Throwable $error) {
            Session::flash('settings.backup_errors', [self::message($error)]);
            return self::back();
        }
    }

    /** Supprime une ou plusieurs archives sélectionnées. */
    public function delete(Request $request): Response
    {
        if (!self::allowed()) {
            return Response::html(__('common.forbidden'), 403);
        }

        $selection = self::selection($request);
        if ($selection === []) {
            Session::flash('settings.backup_errors', [__('backup.selection_empty')]);
            return self::back();
        }

        try {
            $service = new BackupService();
            $deleted = 0;
            foreach ($selection as $name) {
                $service->delete($name);
                $deleted++;
            }
            Session::flash('settings.backup_notice', __('backup.deleted_notice', [
                'count' => (string) $deleted,
            ]));
        } catch (Throwable $error) {
            self::fail($error);
        }

        return self::back();
    }

    /**
     * Restaure une sauvegarde sur l'instance en service.
     *
     * L'exploitant doit recopier exactement le mot de confirmation : la restauration
     * remplace la base et les fichiers privés, seule la sauvegarde de sécurité créée
     * automatiquement permet de revenir à l'état précédent.
     */
    public function restore(Request $request): Response
    {
        if (!self::allowed()) {
            return Response::html(__('common.forbidden'), 403);
        }

        $name = self::inputString($request, 'name');
        $confirmation = self::inputString($request, 'confirmation');
        if ($name === '') {
            Session::flash('settings.backup_errors', [__('backup.selection_empty')]);
            return self::back();
        }
        if ($confirmation !== self::CONFIRMATION_WORD) {
            Session::flash('settings.backup_errors', [__('backup.restore_confirm_required')]);
            return self::back();
        }

        try {
            $result = (new BackupService())->restoreInPlace($name, true);
            Session::flash('settings.backup_notice', __('backup.restored_notice', [
                'tables' => (string) $result['tables'],
                'files' => (string) $result['files'],
                'safety' => basename($result['safety']),
            ]));
        } catch (Throwable $error) {
            self::fail($error);
        }

        return self::back();
    }

    private static function allowed(): bool
    {
        return Auth::can('settings.manage');
    }

    private static function back(): Response
    {
        return Response::redirect(url(self::SETTINGS_URL . self::TAB_ANCHOR));
    }

    private static function fail(Throwable $error): void
    {
        error_log('Sauvegarde : ' . (string) $error);
        Session::flash('settings.backup_errors', [self::message($error)]);
    }

    /** Message affichable : les erreurs métier sont déjà traduites, les autres restent génériques. */
    private static function message(Throwable $error): string
    {
        $message = trim($error->getMessage());

        return $message !== '' ? $message : __('backup.generic_error');
    }

    /** @return list<string> */
    private static function selection(Request $request): array
    {
        $selection = $request->input('names', []);
        if (is_string($selection)) {
            $selection = [$selection];
        }
        if (!is_array($selection)) {
            return [];
        }

        return array_values(array_filter($selection, static fn (mixed $name): bool => is_string($name) && $name !== ''));
    }

    private static function inputString(Request $request, string $key): string
    {
        $value = $request->input($key, '');

        return is_string($value) ? trim($value) : '';
    }
}
