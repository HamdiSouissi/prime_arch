<?php
declare(strict_types=1);

namespace App\Controllers;

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
            return Response::redirect(url($returnTo), 303);
        }

        Session::set('locale', $locale);

        // 303 : le navigateur recharge la page en GET après le changement de langue.
        return Response::redirect(url($returnTo), 303);
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

        $parts = parse_url($candidate);
        if (!is_array($parts)) {
            return '/';
        }
        // Une URL absolue (hôte ou schéma) est refusée : seuls les chemins internes sont acceptés.
        if (($parts['scheme'] ?? '') !== '' || ($parts['host'] ?? '') !== '') {
            return '/';
        }
        $path = (string) ($parts['path'] ?? '');
        if ($path === '' || !str_starts_with($path, '/') || str_starts_with($path, '//')) {
            return '/';
        }
        // Le changement de langue ne doit pas renvoyer vers sa propre action.
        if ($path === '/locale') {
            return '/dashboard';
        }
        $query = (string) ($parts['query'] ?? '');
        return $query === '' ? $path : $path . '?' . $query;
    }
}
