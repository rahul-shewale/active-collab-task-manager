<?php
namespace App\Controllers;

use App\Core\Cache;
use App\Core\Request;
use App\Core\Response;
use App\Services\ActiveCollabService;

class ActiveCollabController
{
    private ActiveCollabService $ac;

    public function __construct()
    {
        $this->ac = new ActiveCollabService();
    }

    // GET /api/active-collab/teams-view
    public function teamsView(Request $request): void
    {
        $data = Cache::remember('ac_teams_view', 900, function () {
            $acUsers  = $this->ac->getUsers();
            $projects = $this->ac->getOngoingProjects();
            $allTasks = $this->ac->getTasksForProjects(array_column($projects, 'id'));

            $projectsById = [];
            foreach ($projects as $p) $projectsById[$p['id']] = $p;

            $acUsersById = [];
            foreach ($acUsers as $u) $acUsersById[$u['id']] = $u;

            $userTasks = [];
            foreach ($acUsers as $u) {
                if (!empty($u['is_archived']) || !empty($u['is_trashed'])) continue;
                $userTasks[$u['id']] = [
                    'user'  => [
                        'id'     => $u['id'],
                        'name'   => $u['display_name'] ?? 'Unknown',
                        'email'  => $u['email'] ?? null,
                        'avatar' => $u['avatar_url'] ?? null,
                    ],
                    'tasks' => [],
                ];
            }

            foreach ($allTasks as $task) {
                $participants = [];
                if (!empty($task['assignee_id'])) $participants[] = $task['assignee_id'];

                $body = $task['body_formatted'] ?? '';
                if (preg_match_all('/<span class="mention mention-user">([^<]+)<\/span>/i', $body, $m)) {
                    foreach (array_unique($m[1]) as $name) {
                        foreach ($acUsers as $u) {
                            if (strtolower($u['display_name'] ?? '') === strtolower($name)) {
                                $participants[] = $u['id'];
                            }
                        }
                    }
                }

                $project  = $projectsById[$task['project_id']] ?? null;
                $formatted = [
                    'id'           => $task['id'],
                    'title'        => $task['name'],
                    'status'       => !empty($task['is_completed']) ? 'done' : 'open',
                    'priority'     => $this->mapPriority($task['priority'] ?? 0),
                    'due_date'     => isset($task['due_on']) ? date('Y-m-d', $task['due_on']) : null,
                    'board_name'   => $project['name'] ?? 'Unknown Project',
                    'project_name' => $task['task_list_name'] ?? null,
                    'url'          => $task['url'] ?? null,
                ];

                foreach (array_unique($participants) as $uid) {
                    if (isset($userTasks[$uid])) {
                        $userTasks[$uid]['tasks'][] = $formatted;
                    }
                }
            }

            return array_values(array_filter($userTasks, fn($u) => count($u['tasks']) > 0));
        });

        Response::json($data);
    }

    // GET /api/active-collab/projects-view
    public function projectsView(Request $request): void
    {
        $data = Cache::remember('ac_projects_view', 900, function () {
            $projects = $this->ac->getOngoingProjects();
            $allTasks = $this->ac->getTasksForProjects(array_column($projects, 'id'));

            $tasksByProject = [];
            foreach ($allTasks as $task) {
                $tasksByProject[$task['project_id']][] = $task;
            }

            $result = [];
            foreach ($projects as $project) {
                $tasks = $tasksByProject[$project['id']] ?? [];
                if (empty($tasks)) continue;

                $result[] = [
                    'project' => [
                        'id'   => $project['id'],
                        'name' => $project['name'],
                        'url'  => $project['url_path'] ?? null,
                    ],
                    'tasks' => array_map(fn($t) => [
                        'id'       => $t['id'],
                        'title'    => $t['name'],
                        'status'   => !empty($t['is_completed']) ? 'done' : 'open',
                        'priority' => $this->mapPriority($t['priority'] ?? 0),
                        'due_date' => isset($t['due_on']) ? date('Y-m-d', $t['due_on']) : null,
                        'url'      => $t['url'] ?? null,
                    ], $tasks),
                ];
            }

            return $result;
        });

        Response::json($data);
    }

    // GET /api/active-collab/managers-view
    public function managersView(Request $request): void
    {
        $data = Cache::remember('ac_managers_view', 900, function () {
            $managers = [
                61  => 'Vijeyta Prabhu',
                34  => 'Shakir Suratwala',
                124 => 'Surekha Pal',
                316 => 'Monika S',
                44  => 'Sarang Suryawanshi',
                341 => 'Aarti Mohite',
                96  => 'Aarti (aarti@ebrandz.com)',
                109 => 'Arti Barapatre',
                1   => 'designadmin',
                118 => 'support.designer',
                222 => 'Sai',
            ];

            $projects = $this->ac->getOngoingProjects();
            $result   = [];

            foreach ($managers as $managerId => $managerName) {
                $managerProjects = array_values(array_filter(
                    $projects,
                    fn($p) => ($p['leader_id'] ?? null) == $managerId
                ));

                $result[] = [
                    'manager'  => ['id' => $managerId, 'name' => $managerName],
                    'projects' => array_map(fn($p) => [
                        'id'          => $p['id'],
                        'name'        => $p['name'],
                        'url'         => $p['url_path'] ?? null,
                        'task_count'  => $p['count_tasks'] ?? 0,
                        'leader_id'   => $p['leader_id'] ?? null,
                        'company_id'  => $p['company_id'] ?? null,
                        'is_billable' => $p['is_billable'] ?? false,
                    ], $managerProjects),
                ];
            }

            return $result;
        });

        Response::json($data);
    }

    // GET /api/active-collab/clients-view
    public function clientsView(Request $request): void
    {
        $data = Cache::remember('ac_clients_view', 900, function () {
            $projects  = $this->ac->getOngoingProjects();
            $companies = $this->ac->getCompanies();

            $companyNames = [];
            foreach ($companies as $c) {
                if (!empty($c['id'])) $companyNames[$c['id']] = $c['name'] ?? "Company #{$c['id']}";
            }

            $grouped = [];
            foreach ($projects as $p) {
                $cid = $p['company_id'] ?? 0;
                if (!isset($grouped[$cid])) {
                    $grouped[$cid] = [
                        'client'   => ['id' => $cid, 'name' => $companyNames[$cid] ?? "Client #{$cid}"],
                        'projects' => [],
                    ];
                }
                $grouped[$cid]['projects'][] = [
                    'id'         => $p['id'],
                    'name'       => $p['name'],
                    'url'        => $p['url_path'] ?? null,
                    'task_count' => $p['count_tasks'] ?? 0,
                    'leader_id'  => $p['leader_id'] ?? null,
                ];
            }

            $result = array_values($grouped);
            usort($result, fn($a, $b) => strcmp($a['client']['name'], $b['client']['name']));
            return $result;
        });

        Response::json($data);
    }

    private function mapPriority(int $priority): string
    {
        if ($priority >= 2) return 'urgent';
        if ($priority == 1) return 'high';
        if ($priority == 0) return 'normal';
        return 'low';
    }
}
