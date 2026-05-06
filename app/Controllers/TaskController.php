<?php
namespace App\Controllers;

use App\Core\Auth;
use App\Core\DB;
use App\Core\Request;
use App\Core\Response;

class TaskController
{
    public function index(Request $request): void
    {
        Auth::guard($request);

        $where  = [];
        $params = [];

        if ($request->filled('user_id')) {
            $where[]  = 'EXISTS (SELECT 1 FROM task_user tu WHERE tu.task_id = t.id AND tu.user_id = ?)';
            $params[] = $request->input('user_id');
        }

        if ($request->filled('source')) {
            $where[]  = 't.source = ?';
            $params[] = $request->input('source');
        }

        if ($request->filled('status')) {
            $status = $request->input('status');
            if ($status === 'pending') {
                $where[]  = "t.status IN ('open','in_progress')";
            } else {
                $where[]  = 't.status = ?';
                $params[] = $status;
            }
        }

        if ($request->filled('priority')) {
            $where[]  = 't.priority = ?';
            $params[] = $request->input('priority');
        }

        if ($request->filled('search')) {
            $where[]  = 't.title LIKE ?';
            $params[] = '%' . $request->input('search') . '%';
        }

        $whereClause = $where ? 'WHERE ' . implode(' AND ', $where) : '';
        $perPage     = min((int) ($request->input('per_page') ?? 30), 100);
        $page        = max((int) ($request->input('page') ?? 1), 1);
        $offset      = ($page - 1) * $perPage;

        $total = (int) DB::fetchOne(
            "SELECT COUNT(*) AS c FROM tasks t $whereClause",
            $params
        )['c'];

        $tasks = DB::fetchAll(
            "SELECT t.*,
                    u.id AS assignee_id, u.name AS assignee_name, u.email AS assignee_email, u.avatar AS assignee_avatar
             FROM tasks t
             LEFT JOIN users u ON u.id = t.assigned_to
             $whereClause
             ORDER BY t.due_date ASC, t.updated_at DESC
             LIMIT $perPage OFFSET $offset",
            $params
        );

        foreach ($tasks as &$task) {
            if ($task['assignee_id']) {
                $task['assignee'] = [
                    'id'     => $task['assignee_id'],
                    'name'   => $task['assignee_name'],
                    'email'  => $task['assignee_email'],
                    'avatar' => $task['assignee_avatar'],
                ];
            } else {
                $task['assignee'] = null;
            }
            unset($task['assignee_id'], $task['assignee_name'], $task['assignee_email'], $task['assignee_avatar']);
        }

        Response::json([
            'data'         => $tasks,
            'total'        => $total,
            'per_page'     => $perPage,
            'current_page' => $page,
            'last_page'    => (int) ceil($total / $perPage),
        ]);
    }

    public function store(Request $request): void
    {
        Auth::guard($request);

        $data = $request->validate([
            'title'       => 'required|max:255',
            'description' => '',
            'priority'    => 'required',
            'assigned_to' => '',
        ]);

        $now = date('Y-m-d H:i:s');
        $id  = DB::insert('tasks', [
            'title'       => $data['title'],
            'description' => $data['description'] ?? null,
            'status'      => 'open',
            'priority'    => $data['priority'],
            'assigned_to' => $data['assigned_to'] ?? null,
            'source'      => 'local',
            'board_name'  => 'Local Tasks',
            'external_id' => 'local_' . uniqid(),
            'due_date'    => null,
            'list_name'   => 'Local',
        ]);

        $task = DB::fetchOne('SELECT * FROM tasks WHERE id = ?', [$id]);
        Response::json($task, 201);
    }

    public function show(Request $request, array $params): void
    {
        Auth::guard($request);

        $task = DB::fetchOne(
            "SELECT t.*, u.id AS assignee_id, u.name AS assignee_name, u.email AS assignee_email, u.avatar AS assignee_avatar
             FROM tasks t
             LEFT JOIN users u ON u.id = t.assigned_to
             WHERE t.id = ?",
            [$params['task']]
        );

        if (!$task) Response::error('Task not found', 404);

        if ($task['assignee_id']) {
            $task['assignee'] = ['id' => $task['assignee_id'], 'name' => $task['assignee_name']];
        }

        Response::json($task);
    }

    public function stats(Request $request): void
    {
        Auth::guard($request);

        $byStatus = [];
        foreach (DB::fetchAll("SELECT status, COUNT(*) AS c FROM tasks GROUP BY status") as $row) {
            $byStatus[$row['status']] = (int) $row['c'];
        }

        $byPriority = [];
        foreach (DB::fetchAll("SELECT priority, COUNT(*) AS c FROM tasks GROUP BY priority") as $row) {
            $byPriority[$row['priority']] = (int) $row['c'];
        }

        $bySource = [];
        foreach (DB::fetchAll("SELECT source, COUNT(*) AS c FROM tasks GROUP BY source") as $row) {
            $bySource[$row['source']] = (int) $row['c'];
        }

        $byUser = [];
        foreach (DB::fetchAll(
            "SELECT t.assigned_to, u.name, COUNT(*) AS c
             FROM tasks t LEFT JOIN users u ON u.id = t.assigned_to
             GROUP BY t.assigned_to"
        ) as $row) {
            $byUser[] = [
                'user'  => $row['name'] ?? 'Unassigned',
                'count' => (int) $row['c'],
            ];
        }

        Response::json([
            'by_status'   => $byStatus,
            'by_priority' => $byPriority,
            'by_source'   => $bySource,
            'by_user'     => $byUser,
        ]);
    }
}
