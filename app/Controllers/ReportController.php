<?php
namespace App\Controllers;

use App\Core\DB;
use App\Core\Request;
use App\Core\Response;

class ReportController
{
    public function projectStats(Request $request): void
    {
        $boards = DB::fetchAll(
            "SELECT DISTINCT board_name FROM tasks
             WHERE status IN ('open','in_progress') AND board_name IS NOT NULL
             ORDER BY board_name"
        );
        $boardNames = array_column($boards, 'board_name');

        $users = DB::fetchAll("SELECT id, name FROM users ORDER BY name");

        $matrix = [];
        foreach ($boardNames as $board) {
            $userCounts = [];
            foreach ($users as $user) {
                $count = (int) DB::fetchOne(
                    "SELECT COUNT(*) AS c FROM tasks t
                     JOIN task_user tu ON tu.task_id = t.id
                     WHERE t.board_name = ?
                     AND t.status IN ('open','in_progress')
                     AND tu.user_id = ?",
                    [$board, $user['id']]
                )['c'];
                $userCounts[$user['id']] = $count;
            }
            $matrix[] = ['board' => $board, 'user_counts' => $userCounts];
        }

        Response::json([
            'boards' => $boardNames,
            'users'  => $users,
            'matrix' => $matrix,
        ]);
    }

    public function dueDateStats(Request $request): void
    {
        $today = date('Y-m-d');

        $tasks = DB::fetchAll(
            "SELECT t.*, GROUP_CONCAT(u.name ORDER BY u.name SEPARATOR ',') AS programmers
             FROM tasks t
             LEFT JOIN task_user tu ON tu.task_id = t.id
             LEFT JOIN users u ON u.id = tu.user_id
             WHERE t.status != 'done'
             GROUP BY t.id
             ORDER BY t.due_date ASC"
        );

        $formatter = function (array $tasks): array {
            return array_map(fn($t) => [
                'id'         => $t['id'],
                'title'      => $t['title'],
                'due_date'   => $t['due_date'],
                'status'     => $t['status'],
                'board_name' => $t['board_name'],
                'list_name'  => $t['list_name'],
                'url'        => $t['url'],
                'programmers'=> $t['programmers'] ? explode(',', $t['programmers']) : [],
            ], $tasks);
        };

        $pending  = array_filter($tasks, fn($t) => $t['due_date'] && $t['due_date'] < $today);
        $todayArr = array_filter($tasks, fn($t) => $t['due_date'] === $today);
        $upcoming = array_filter($tasks, fn($t) => $t['due_date'] && $t['due_date'] > $today);
        $open     = array_slice(array_filter($tasks, fn($t) => $t['due_date'] === null), 0, 100);

        Response::json([
            'pending'  => $formatter(array_values($pending)),
            'today'    => $formatter(array_values($todayArr)),
            'upcoming' => $formatter(array_values($upcoming)),
            'open'     => $formatter(array_values($open)),
        ]);
    }

    public function hubstaffTeam(Request $request): void
    {
        $timeframe = $request->get('timeframe', 'day');
        $today     = date('Y-m-d');

        $startDate = match ($timeframe) {
            'day'   => date('Y-m-d'),
            'month' => date('Y-m-01'),
            default => date('Y-m-d', strtotime('monday this week')),
        };

        $members = DB::fetchAll('SELECT * FROM hubstaff_members ORDER BY name');
        $colors  = ['#c084fc', '#6366f1', '#10b981', '#f59e0b', '#ec4899', '#3b82f6'];

        $response = [];
        foreach ($members as $i => $member) {
            $activities = DB::fetchAll(
                'SELECT * FROM hubstaff_activities
                 WHERE hubstaff_member_id = ? AND tracked_date >= ?
                 ORDER BY tracked_date ASC',
                [$member['id'], $startDate]
            );

            $todaySecs  = 0;
            $periodSecs = 0;
            $groupedTasks = [];

            $todos = json_decode($member['todos'] ?? '[]', true) ?? [];
            foreach ($todos as $todo) {
                $tid = 'task_' . ($todo['id'] ?? '0');
                $groupedTasks[$tid] = [
                    'is_todo' => true,
                    'name'    => 'To-do: ' . ($todo['name'] ?? 'Unknown'),
                    'details' => $todo['details'] ?? '',
                    'todaySecs' => 0, 'todayActs' => [], 'periodSecs' => 0, 'periodActs' => [],
                ];
            }

            foreach ($activities as $act) {
                $periodSecs += $act['tracked_seconds'];
                if ($act['tracked_date'] === $today) {
                    $todaySecs += $act['tracked_seconds'];
                }

                if (!empty($act['hubstaff_task_id'])) {
                    $key = 'task_' . $act['hubstaff_task_id'];
                    if (!isset($groupedTasks[$key])) {
                        $groupedTasks[$key] = [
                            'is_todo'  => true,
                            'name'     => 'To-do #' . $act['hubstaff_task_id'],
                            'details'  => '',
                            'todaySecs'=> 0, 'todayActs'=> [], 'periodSecs'=> 0, 'periodActs'=> [],
                        ];
                    }
                } else {
                    $key = 'project_' . ($act['hubstaff_project_id'] ?? 'Unknown');
                    if (!isset($groupedTasks[$key])) {
                        $groupedTasks[$key] = [
                            'is_todo'  => false,
                            'name'     => 'Project #' . ($act['hubstaff_project_id'] ?? 'Unknown'),
                            'details'  => '',
                            'todaySecs'=> 0, 'todayActs'=> [], 'periodSecs'=> 0, 'periodActs'=> [],
                        ];
                    }
                }

                $groupedTasks[$key]['periodSecs'] += $act['tracked_seconds'];
                $groupedTasks[$key]['periodActs'][] = $act['overall_activity'];
                if ($act['tracked_date'] === $today) {
                    $groupedTasks[$key]['todaySecs'] += $act['tracked_seconds'];
                    $groupedTasks[$key]['todayActs'][] = $act['overall_activity'];
                }
            }

            $tasks = [];
            foreach ($groupedTasks as $data) {
                $sumPeriodActSecs = array_sum($data['periodActs']);
                $sumTodayActSecs  = array_sum($data['todayActs']);
                $avgPeriod = $data['periodSecs'] > 0 ? ($sumPeriodActSecs / $data['periodSecs']) * 100 : 0;
                $avgToday  = $data['todaySecs'] > 0 ? ($sumTodayActSecs / $data['todaySecs']) * 100 : 0;

                $tasks[] = [
                    'name'       => $data['name'],
                    'details'    => $data['details'],
                    'todayTime'  => sprintf('%d:%02d', floor($data['todaySecs'] / 3600), floor(($data['todaySecs'] / 60) % 60)),
                    'todayAct'   => round($avgToday),
                    'periodTime' => sprintf('%d:%02d', floor($data['periodSecs'] / 3600), floor(($data['periodSecs'] / 60) % 60)),
                    'periodAct'  => round($avgPeriod),
                ];
            }

            $totOverallSecs = array_sum(array_column($activities, 'overall_activity'));
            $avgActOverall  = $periodSecs > 0 ? ($totOverallSecs / $periodSecs) * 100 : 0;

            $response[] = [
                'name'     => explode(' ', $member['name'])[0],
                'initials' => strtoupper(substr($member['name'], 0, 1)),
                'color'    => $colors[$i % count($colors)],
                'todayH'   => sprintf('%d:%02d', floor($todaySecs / 3600), floor(($todaySecs / 60) % 60)),
                'periodH'  => sprintf('%d:%02d', floor($periodSecs / 3600), floor(($periodSecs / 60) % 60)),
                'activity' => round($avgActOverall),
                'tasks'    => $tasks,
            ];
        }

        Response::json($response);
    }
}
