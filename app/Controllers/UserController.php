<?php
namespace App\Controllers;

use App\Core\Auth;
use App\Core\DB;
use App\Core\Request;
use App\Core\Response;

class UserController
{
    public function index(Request $request): void
    {
        Auth::guard($request);

        $users = DB::fetchAll(
            "SELECT u.*,
                    COUNT(DISTINCT CASE WHEN t.status IN ('open','in_progress') THEN tu.task_id END) AS pending_tasks_count
             FROM users u
             LEFT JOIN task_user tu ON tu.user_id = u.id
             LEFT JOIN tasks t ON t.id = tu.task_id
             GROUP BY u.id
             ORDER BY u.name"
        );

        foreach ($users as &$u) {
            unset($u['password'], $u['remember_token']);
        }

        Response::json($users);
    }

    public function store(Request $request): void
    {
        Auth::guard($request);

        $data = $request->validate([
            'name'  => 'required|max:255',
            'email' => 'email|max:255',
        ]);

        if (empty($data['email'])) {
            $slug = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $data['name']));
            $data['email'] = $slug . '_' . uniqid() . '@local.dev';
        }

        $data['password'] = password_hash(bin2hex(random_bytes(8)), PASSWORD_BCRYPT);
        $data['role']     = 'user';

        $id   = DB::insert('users', $data);
        $user = DB::fetchOne('SELECT * FROM users WHERE id = ?', [$id]);
        unset($user['password'], $user['remember_token']);

        Response::json($user, 201);
    }

    public function show(Request $request, array $params): void
    {
        Auth::guard($request);

        $user = DB::fetchOne('SELECT * FROM users WHERE id = ?', [$params['user']]);
        if (!$user) Response::error('User not found', 404);

        unset($user['password'], $user['remember_token']);

        $user['pending_tasks_count'] = (int) DB::fetchOne(
            "SELECT COUNT(*) AS c FROM task_user tu
             JOIN tasks t ON t.id = tu.task_id
             WHERE tu.user_id = ? AND t.status IN ('open','in_progress')",
            [$user['id']]
        )['c'];

        $user['tasks'] = DB::fetchAll(
            "SELECT t.* FROM tasks t
             JOIN task_user tu ON tu.task_id = t.id
             WHERE tu.user_id = ?",
            [$user['id']]
        );

        Response::json($user);
    }

    public function destroy(Request $request, array $params): void
    {
        Auth::guard($request);

        $user = DB::fetchOne('SELECT id FROM users WHERE id = ?', [$params['user']]);
        if (!$user) Response::error('User not found', 404);

        // Delete task_user rows, then tasks owned, then user
        DB::query('DELETE FROM task_user WHERE user_id = ?', [$params['user']]);
        DB::query('DELETE FROM tasks WHERE assigned_to = ?', [$params['user']]);
        DB::delete('users', 'id = ?', [$params['user']]);

        Response::json(['message' => 'User deleted successfully']);
    }
}
