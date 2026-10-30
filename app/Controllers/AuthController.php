<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\View;
use App\Services\AuditLogger;
use App\Services\AuthService;
use Throwable;

/**
 * Connexion, déconnexion et page d'entrée de l'application.
 */
final class AuthController
{
    public function home(Request $request): Response
    {
        if (!Database::isAvailable()) {
            return $this->installationStatus(false);
        }

        try {
            return Response::redirect(url(Auth::check() ? '/dashboard' : '/login'));
        } catch (Throwable) {
            return $this->installationStatus(true);
        }
    }

    public function showLogin(Request $request): Response
    {
        $returnTo = self::safeReturn($request->query('return'));
        try {
            if (Auth::check()) {
                return Response::redirect(url('/dashboard'));
            }
        } catch (Throwable $error) {
            error_log((string) $error);
            return $this->loginPage($returnTo, __('auth.unavailable'), 503);
        }

        return $this->loginPage($returnTo, Session::pullFlash('auth.error'));
    }

    public function login(Request $request): Response
    {
        $email = $request->input('email');
        $password = $request->input('password');
        $returnTo = self::safeReturn($request->input('return_to'));

        if (!is_string($email) || !is_string($password)
            || strlen(trim($email)) > 190
            || !filter_var(trim($email), FILTER_VALIDATE_EMAIL)
            || strlen($password) > 1024
        ) {
            return $this->failedLogin($returnTo, __('auth.invalid_credentials'));
        }

        try {
            $result = (new AuthService())->authenticate(trim($email), $password, $request->ip());
        } catch (Throwable $error) {
            error_log((string) $error);
            return $this->loginPage($returnTo, __('auth.unavailable'), 503);
        }
        if ($result === 'success') {
            return Response::redirect(url($returnTo));
        }

        $message = $result === 'throttled'
            ? __('auth.rate_limited')
            : __('auth.invalid_credentials');
        return $this->failedLogin($returnTo, $message);
    }

    public function logout(Request $request): Response
    {
        $userId = Auth::id();
        (new AuditLogger())->record('auth.logout', 'user', $userId, [], $userId);
        Auth::logout();
        return Response::redirect(url('/login'));
    }

    private function loginPage(string $returnTo, mixed $errorMessage, int $status = 200): Response
    {
        return Response::html(View::render('pages/auth/login', [
            'title' => __('auth.login_title'),
            'returnTo' => $returnTo,
            'errorMessage' => $errorMessage,
            'demoMode' => false,
            'devOnly' => false,
        ], 'layouts/auth'), $status);
    }

    private function failedLogin(string $returnTo, string $message): Response
    {
        Session::flash('auth.error', $message);
        return Response::redirect(url('/login') . '?return=' . rawurlencode($returnTo));
    }

    private static function safeReturn(mixed $candidate): string
    {
        if (!is_string($candidate) || $candidate === '' || !str_starts_with($candidate, '/')
            || str_starts_with($candidate, '//') || str_contains($candidate, '\\')
            || preg_match('/[\r\n]/', $candidate)
        ) {
            return '/dashboard';
        }
        $path = parse_url($candidate, PHP_URL_PATH);
        return is_string($path) && $path !== '' && str_starts_with($path, '/') ? $path : '/dashboard';
    }

    private function installationStatus(bool $databaseAvailable): Response
    {
        return Response::html(View::render('pages/home', [
            'title' => __('app.install_ok'),
            'phpVersion' => PHP_VERSION,
            'phpOk' => version_compare(PHP_VERSION, '8.2.0', '>='),
            'dbOk' => $databaseAvailable,
            'appVersion' => config('app.version'),
        ]));
    }
}
