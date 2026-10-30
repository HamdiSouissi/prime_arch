<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Csrf;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\Translator;

/**
 * Changement de langue de l'interface (français / arabe).
 *
 * La langue est conservée en session afin de s'appliquer à toutes les pages,
 * y compris la connexion et les documents PDF générés par la suite.
 */
final class LocaleController
{
    public function update(Request $request): Response
    {
        $locale = $request->input('locale');
        $returnTo = self::safeReturn($request->input('return_to'));

        if (!is_string($locale) || !Translator::isSupported($locale)) {
            return Response::redirect(url($returnTo));
        }

        Session::set('locale', $locale);
        // Nouveau jeton : le formulaire de langue précède souvent une connexion.
        Csrf::regenerate();

        return Response::redirect(url($returnTo));
    }

    /**
     * N'accepte qu'un chemin interne commençant par « / » (pas de redirection ouverte).
     */
    private static function safeReturn(mixed $candidate): string
    {
        if (!is_string($candidate) || $candidate === '' || !str_starts_with($candidate, '/')
            || str_starts_with($candidate, '//') || str_contains($candidate, '\\')
            || preg_match('/[\r\n]/', $candidate)
        ) {
            return '/';
        }

        $path = parse_url($candidate, PHP_URL_PATH);
        if (!is_string($path) || $path === '' || !str_starts_with($path, '/')) {
            return '/';
        }
        // Le changement de langue ne doit pas renvoyer vers sa propre action.
        return $path === '/locale' ? '/dashboard' : $path;
    }
}
