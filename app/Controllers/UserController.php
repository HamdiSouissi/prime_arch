<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\View;
use App\Models\UserRepository;
use App\Services\AuditLogger;
use App\Services\AuthService;
use App\Services\ViewContext;
use PDOException;
use Throwable;

/**
 * Gestion des utilisateurs et de leurs rôles, réservée aux administrateurs.
 */
final class UserController
{
    public function index(Request $request): Response
    {
        $users = new UserRepository();
        $context = (new ViewContext())->authenticated(__('users.title'), 'settings');
        $values = Session::pullFlash('users.values', []);
        return Response::html(View::render('pages/app/users', $context + [
            'users' => $users->all(),
            'roles' => $users->roles(),
            'errors' => Session::pullFlash('users.errors', []),
            'notice' => Session::pullFlash('users.notice'),
            'values' => is_array($values) ? $values : [],
        ]));
    }

    public function create(Request $request): Response
    {
        $name = self::inputString($request, 'name');
        $email = mb_strtolower(self::inputString($request, 'email'), 'UTF-8');
        $password = $request->input('password');
        $role = self::inputString($request, 'role');
        $repository = new UserRepository();

        if (mb_strlen($name, 'UTF-8') < 2 || mb_strlen($name, 'UTF-8') > 150
            || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 190
            || !is_string($password) || mb_strlen($password, 'UTF-8') < 12 || strlen($password) > 1024
            || $repository->findRole($role) === null
        ) {
            Session::flash('users.errors', ['general' => __('users.invalid')]);
            Session::flash('users.values', ['name' => $name, 'email' => $email, 'role' => $role]);
            return Response::redirect(url('/admin/users'));
        }

        try {
            $createdId = $repository->create($name, $email, AuthService::hashPassword($password), $role);
        } catch (PDOException $error) {
            if ($error->getCode() === '23000') {
                Session::flash('users.errors', ['email' => __('users.email_exists')]);
                Session::flash('users.values', ['name' => $name, 'email' => $email, 'role' => $role]);
                return Response::redirect(url('/admin/users'));
            }
            error_log((string) $error);
            Session::flash('users.errors', ['general' => __('users.invalid')]);
            return Response::redirect(url('/admin/users'));
        } catch (Throwable $error) {
            error_log((string) $error);
            Session::flash('users.errors', ['general' => __('users.invalid')]);
            return Response::redirect(url('/admin/users'));
        }

        (new AuditLogger())->record('users.created', 'user', $createdId, ['role' => $role], Auth::id());
        Session::flash('users.notice', __('users.created'));
        return Response::redirect(url('/admin/users'));
    }

    public function update(Request $request, string $id): Response
    {
        $userId = filter_var($id, FILTER_VALIDATE_INT);
        $role = self::inputString($request, 'role');
        $activeInput = self::inputString($request, 'is_active');
        if ($userId === false || $userId < 1 || !in_array($activeInput, ['0', '1'], true)) {
            Session::flash('users.errors', ['general' => __('users.invalid')]);
            return Response::redirect(url('/admin/users'));
        }

        $repository = new UserRepository();
        $user = $repository->findById((int) $userId);
        if ($user === null || $repository->findRole($role) === null) {
            Session::flash('users.errors', ['general' => __('users.invalid')]);
            return Response::redirect(url('/admin/users'));
        }
        if ((int) Auth::id() === (int) $userId && ($activeInput !== '1' || $role !== $user['role_slug'])) {
            Session::flash('users.errors', ['general' => __('users.self_deactivate')]);
            return Response::redirect(url('/admin/users'));
        }

        try {
            $result = $repository->updateAccess((int) $userId, $role, $activeInput === '1');
        } catch (Throwable $error) {
            error_log((string) $error);
            Session::flash('users.errors', ['general' => __('users.invalid')]);
            return Response::redirect(url('/admin/users'));
        }
        if ($result === 'last_admin') {
            Session::flash('users.errors', ['general' => __('users.last_admin')]);
            return Response::redirect(url('/admin/users'));
        }
        if ($result === 'missing') {
            Session::flash('users.errors', ['general' => __('users.invalid')]);
            return Response::redirect(url('/admin/users'));
        }

        (new AuditLogger())->record('users.access.updated', 'user', (int) $userId, [
            'role' => $role,
            'is_active' => $activeInput === '1',
        ], Auth::id());
        Session::flash('users.notice', __('users.updated'));
        return Response::redirect(url('/admin/users'));
    }

    private static function inputString(Request $request, string $key): string
    {
        $value = $request->input($key, '');
        return is_string($value) ? trim($value) : '';
    }
}
