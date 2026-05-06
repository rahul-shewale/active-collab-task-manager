<?php
namespace App\Controllers;

use App\Core\DB;
use App\Core\Request;
use App\Core\Response;

class PendingController
{
    /**
     * GET /api/pending/trello
     *
     * Returns Trello tasks (open + in_progress) grouped by local user using the
     * `task_user` pivot.
     *
     * Shape matches ActiveCollab teams view:
     * [
     *   { user: {id,name,email,avatar}, tasks: [{id,title,status,priority,due_date,board_name,project_name,url}] }
     * ]
     */
    public function trello(Request $request): void
    {
        // We want a useful UI even when Trello member->local user mapping is missing.
        // So we:
        // 1) group tasks by task_user pivot when present
        // 2) also include an "Unassigned" column for tasks with no pivot rows

        $rows = DB::fetchAll(
            "SELECT
                u.id   AS user_id,
                u.name AS user_name,
                u.email AS user_email,
                u.avatar AS user_avatar,
                t.id   AS task_id,
                t.title,
                t.status,
                t.priority,
                t.due_date,
                t.board_name,
                t.list_name,
                t.url
             FROM tasks t
             LEFT JOIN task_user tu ON tu.task_id = t.id
             LEFT JOIN users u ON u.id = tu.user_id
             WHERE t.source = 'trello'
               AND t.status IN ('open','in_progress')
             ORDER BY
               (u.name IS NULL) ASC,
               u.name ASC,
               (t.due_date IS NULL) ASC,
               t.due_date ASC,
               t.updated_at DESC"
        );

        $grouped = [];
        $unassignedKey = '__unassigned__';
        foreach ($rows as $r) {
            $uid = $r['user_id'] ? (int) $r['user_id'] : $unassignedKey;
            if (!isset($grouped[$uid])) {
                $grouped[$uid] = [
                    'user' => $uid === $unassignedKey
                        ? ['id' => 0, 'name' => 'Unassigned', 'email' => null, 'avatar' => null]
                        : [
                            'id'     => (int) $r['user_id'],
                            'name'   => $r['user_name'] ?? 'Unknown',
                            'email'  => $r['user_email'] ?? null,
                            'avatar' => $r['user_avatar'] ?? null,
                        ],
                    'tasks' => [],
                ];
            }

            $grouped[$uid]['tasks'][] = [
                'id'           => (int) $r['task_id'],
                'title'        => $r['title'] ?? '',
                'status'       => $r['status'] ?? 'open',
                'priority'     => $r['priority'] ?? 'normal',
                'due_date'     => $r['due_date'] ?: null,
                'board_name'   => $r['board_name'] ?? null,
                'project_name' => $r['list_name'] ?? null,
                'url'          => $r['url'] ?? null,
            ];
        }

        $out = array_values(array_filter($grouped, fn($g) => !empty($g['tasks'])));
        Response::json($out);
    }

    /**
     * GET /api/pending/active-collab
     *
     * Uses the precomputed `ac_teams_view` blob (built by cron) and filters
     * out done tasks.
     */
    public function activeCollab(Request $request): void
    {
        $row = DB::fetchOne('SELECT value FROM cache_store WHERE cache_key = ?', ['ac_teams_view']);
        $teams = $row ? (@unserialize($row['value']) ?: []) : [];
        if (!is_array($teams)) $teams = [];

        $filtered = [];
        foreach ($teams as $member) {
            $tasks = $member['tasks'] ?? [];
            if (!is_array($tasks)) $tasks = [];
            $tasks = array_values(array_filter($tasks, fn($t) => ($t['status'] ?? '') !== 'done'));

            if (!empty($tasks)) {
                $member['tasks'] = $tasks;
                $filtered[] = $member;
            }
        }

        Response::json($filtered);
    }
}

