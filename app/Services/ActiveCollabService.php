<?php
namespace App\Services;

use App\Core\Bootstrap;
use App\Core\DB;

class ActiveCollabService
{
    private string $baseUrl;
    private string $token;
    private const TIMEOUT = 15;

    /** Hard-coded manager directory used by the "AC Managers" view. */
    private const MANAGERS = [
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

    public function __construct()
    {
        $this->baseUrl = rtrim(Bootstrap::config('activecollab.base_url', ''), '/');
        $this->token   = Bootstrap::config('activecollab.token', '');
    }

    /* ─────────────────────────────────────────────────────────────────
     * Public sync entry (called by SyncRunner / cron / Sync Data button)
     * ───────────────────────────────────────────────────────────────── */

    /**
     * Pull users / companies / projects / tasks from ActiveCollab,
     * upsert into the ac_* tables, then precompute the four view
     * blobs and store them in `cache_store` for the dashboard.
     */
    public function syncAll(): array
    {
        $errors = [];
        $synced = 0;
        $runStart = date('Y-m-d H:i:s');

        if (!$this->token || !$this->baseUrl) {
            return ['synced' => 0, 'errors' => ['ActiveCollab base_url or token not configured']];
        }

        try {
            $users     = $this->getUsers();
            $companies = $this->getCompanies();
            $projects  = $this->getOngoingProjects();
            $tasks     = $this->getTasksForProjects(array_column($projects, 'id'));
        } catch (\Throwable $e) {
            return ['synced' => 0, 'errors' => ['Fetch failed: ' . $e->getMessage()]];
        }

        $synced += $this->upsertUsers($users, $runStart);
        $synced += $this->upsertCompanies($companies, $runStart);
        $synced += $this->upsertProjects($projects, $runStart);
        $synced += $this->upsertTasks($tasks, $runStart);

        try {
            $this->writeViewBlob('ac_teams_view',    $this->buildTeamsView($users, $projects, $tasks));
            $this->writeViewBlob('ac_projects_view', $this->buildProjectsView($projects, $tasks));
            $this->writeViewBlob('ac_managers_view', $this->buildManagersView($projects));
            $this->writeViewBlob('ac_clients_view',  $this->buildClientsView($projects, $companies));
        } catch (\Throwable $e) {
            $errors[] = 'View build failed: ' . $e->getMessage();
        }

        return ['synced' => $synced, 'errors' => $errors];
    }

    /* ─────────────────────────────────────────────────────────────────
     * Raw API helpers (unchanged behaviour)
     * ───────────────────────────────────────────────────────────────── */

    public function getProjects(): array
    {
        return $this->request('/api/v1/projects');
    }

    public function getUsers(): array
    {
        return $this->request('/api/v1/users');
    }

    public function getCompanies(): array
    {
        return $this->request('/api/v1/companies');
    }

    public function getOngoingProjects(): array
    {
        $projects = $this->getProjects();
        return array_values(array_filter(
            $projects,
            fn($p) => empty($p['is_completed']) && empty($p['is_trashed'])
        ));
    }

    public function getTasksForProjects(array $projectIds): array
    {
        if (empty($projectIds)) return [];

        $allTasks = [];
        $chunks   = array_chunk($projectIds, 30);

        foreach ($chunks as $chunk) {
            $multiHandle = curl_multi_init();
            $handles     = [];

            foreach ($chunk as $id) {
                $ch = $this->buildCurl("/api/v1/projects/{$id}/tasks");
                curl_multi_add_handle($multiHandle, $ch);
                $handles[$id] = $ch;
            }

            $running = null;
            do {
                curl_multi_exec($multiHandle, $running);
                curl_multi_select($multiHandle);
            } while ($running > 0);

            foreach ($chunk as $id) {
                $body = curl_multi_getcontent($handles[$id]);
                $code = curl_getinfo($handles[$id], CURLINFO_HTTP_CODE);
                curl_multi_remove_handle($multiHandle, $handles[$id]);
                curl_close($handles[$id]);

                if ($code >= 200 && $code < 300 && $body) {
                    $data  = json_decode($body, true) ?? [];
                    $tasks = $data['tasks'] ?? (is_array($data) ? $data : []);
                    foreach ($tasks as &$task) {
                        $task['project_id'] = $id;
                    }
                    $allTasks = array_merge($allTasks, $tasks);
                }
            }

            curl_multi_close($multiHandle);
        }

        return $allTasks;
    }

    /* ─────────────────────────────────────────────────────────────────
     * Upserts into the normalised ac_* tables
     * ───────────────────────────────────────────────────────────────── */

    private function upsertUsers(array $users, string $runStart): int
    {
        $n = 0;
        foreach ($users as $u) {
            if (empty($u['id'])) continue;
            DB::query(
                "INSERT INTO ac_users (id, display_name, email, avatar_url, is_archived, is_trashed, synced_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?)
                 ON DUPLICATE KEY UPDATE
                    display_name = VALUES(display_name),
                    email        = VALUES(email),
                    avatar_url   = VALUES(avatar_url),
                    is_archived  = VALUES(is_archived),
                    is_trashed   = VALUES(is_trashed),
                    synced_at    = VALUES(synced_at)",
                [
                    $u['id'],
                    $u['display_name'] ?? ($u['first_name'] ?? '') . ' ' . ($u['last_name'] ?? ''),
                    $u['email']        ?? null,
                    $u['avatar_url']   ?? null,
                    !empty($u['is_archived']) ? 1 : 0,
                    !empty($u['is_trashed'])  ? 1 : 0,
                    $runStart,
                ]
            );
            $n++;
        }
        return $n;
    }

    private function upsertCompanies(array $companies, string $runStart): int
    {
        $n = 0;
        foreach ($companies as $c) {
            if (empty($c['id'])) continue;
            DB::query(
                "INSERT INTO ac_companies (id, name, synced_at)
                 VALUES (?, ?, ?)
                 ON DUPLICATE KEY UPDATE
                    name      = VALUES(name),
                    synced_at = VALUES(synced_at)",
                [$c['id'], $c['name'] ?? "Company #{$c['id']}", $runStart]
            );
            $n++;
        }
        return $n;
    }

    private function upsertProjects(array $projects, string $runStart): int
    {
        $n = 0;
        foreach ($projects as $p) {
            if (empty($p['id'])) continue;
            DB::query(
                "INSERT INTO ac_projects
                    (id, name, url_path, leader_id, company_id, count_tasks,
                     is_billable, is_completed, is_trashed, synced_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                 ON DUPLICATE KEY UPDATE
                    name         = VALUES(name),
                    url_path     = VALUES(url_path),
                    leader_id    = VALUES(leader_id),
                    company_id   = VALUES(company_id),
                    count_tasks  = VALUES(count_tasks),
                    is_billable  = VALUES(is_billable),
                    is_completed = VALUES(is_completed),
                    is_trashed   = VALUES(is_trashed),
                    synced_at    = VALUES(synced_at)",
                [
                    $p['id'],
                    $p['name']        ?? "Project #{$p['id']}",
                    $p['url_path']    ?? null,
                    $p['leader_id']   ?? null,
                    $p['company_id']  ?? null,
                    (int)($p['count_tasks'] ?? 0),
                    !empty($p['is_billable'])  ? 1 : 0,
                    !empty($p['is_completed']) ? 1 : 0,
                    !empty($p['is_trashed'])   ? 1 : 0,
                    $runStart,
                ]
            );
            $n++;
        }
        return $n;
    }

    private function upsertTasks(array $tasks, string $runStart): int
    {
        $n = 0;
        $touched = [];
        foreach ($tasks as $t) {
            if (empty($t['id'])) continue;
            $touched[$t['project_id'] ?? 0][] = $t['id'];

            $dueOn = isset($t['due_on']) && $t['due_on'] ? date('Y-m-d', (int)$t['due_on']) : null;

            DB::query(
                "INSERT INTO ac_tasks
                    (id, project_id, name, assignee_id, task_list_name, priority,
                     is_completed, due_on, url, body_formatted, synced_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                 ON DUPLICATE KEY UPDATE
                    project_id     = VALUES(project_id),
                    name           = VALUES(name),
                    assignee_id    = VALUES(assignee_id),
                    task_list_name = VALUES(task_list_name),
                    priority       = VALUES(priority),
                    is_completed   = VALUES(is_completed),
                    due_on         = VALUES(due_on),
                    url            = VALUES(url),
                    body_formatted = VALUES(body_formatted),
                    synced_at      = VALUES(synced_at)",
                [
                    $t['id'],
                    $t['project_id']     ?? 0,
                    $t['name']           ?? '',
                    $t['assignee_id']    ?? null,
                    $t['task_list_name'] ?? null,
                    (int)($t['priority'] ?? 0),
                    !empty($t['is_completed']) ? 1 : 0,
                    $dueOn,
                    $t['url']            ?? null,
                    $t['body_formatted'] ?? null,
                    $runStart,
                ]
            );
            $n++;
        }

        // Drop tasks that were not seen in this run for the projects we touched
        // (ActiveCollab returns the full task list per project, so anything missing was deleted upstream).
        foreach ($touched as $projectId => $ids) {
            if (!$projectId) continue;
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            DB::query(
                "DELETE FROM ac_tasks WHERE project_id = ? AND id NOT IN ($placeholders)",
                array_merge([$projectId], $ids)
            );
        }

        return $n;
    }

    /* ─────────────────────────────────────────────────────────────────
     * View builders (moved from ActiveCollabController)
     * ───────────────────────────────────────────────────────────────── */

    private function buildTeamsView(array $acUsers, array $projects, array $allTasks): array
    {
        $projectsById = [];
        foreach ($projects as $p) $projectsById[$p['id']] = $p;

        $userTasks = [];
        foreach ($acUsers as $u) {
            if (!empty($u['is_archived']) || !empty($u['is_trashed'])) continue;
            $userTasks[$u['id']] = [
                'user' => [
                    'id'     => $u['id'],
                    'name'   => $u['display_name'] ?? 'Unknown',
                    'email'  => $u['email']        ?? null,
                    'avatar' => $u['avatar_url']   ?? null,
                ],
                'tasks' => [],
            ];
        }

        foreach ($allTasks as $task) {
            $participants = [];
            if (!empty($task['assignee_id'])) $participants[] = $task['assignee_id'];

            $body = $task['body_formatted'] ?? '';
            if ($body && preg_match_all('/<span class="mention mention-user">([^<]+)<\/span>/i', $body, $m)) {
                foreach (array_unique($m[1]) as $name) {
                    foreach ($acUsers as $u) {
                        if (strtolower($u['display_name'] ?? '') === strtolower($name)) {
                            $participants[] = $u['id'];
                        }
                    }
                }
            }

            $project   = $projectsById[$task['project_id']] ?? null;
            $formatted = [
                'id'           => $task['id'],
                'title'        => $task['name'] ?? '',
                'status'       => !empty($task['is_completed']) ? 'done' : 'open',
                'priority'     => $this->mapPriority((int)($task['priority'] ?? 0)),
                'due_date'     => isset($task['due_on']) && $task['due_on'] ? date('Y-m-d', (int)$task['due_on']) : null,
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
    }

    private function buildProjectsView(array $projects, array $allTasks): array
    {
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
                    'name' => $project['name'] ?? "Project #{$project['id']}",
                    'url'  => $project['url_path'] ?? null,
                ],
                'tasks' => array_map(fn($t) => [
                    'id'       => $t['id'],
                    'title'    => $t['name'] ?? '',
                    'status'   => !empty($t['is_completed']) ? 'done' : 'open',
                    'priority' => $this->mapPriority((int)($t['priority'] ?? 0)),
                    'due_date' => isset($t['due_on']) && $t['due_on'] ? date('Y-m-d', (int)$t['due_on']) : null,
                    'url'      => $t['url'] ?? null,
                ], $tasks),
            ];
        }

        return $result;
    }

    private function buildManagersView(array $projects): array
    {
        $result = [];
        foreach (self::MANAGERS as $managerId => $managerName) {
            $managerProjects = array_values(array_filter(
                $projects,
                fn($p) => ($p['leader_id'] ?? null) == $managerId
            ));

            $result[] = [
                'manager'  => ['id' => $managerId, 'name' => $managerName],
                'projects' => array_map(fn($p) => [
                    'id'          => $p['id'],
                    'name'        => $p['name']        ?? "Project #{$p['id']}",
                    'url'         => $p['url_path']    ?? null,
                    'task_count'  => $p['count_tasks'] ?? 0,
                    'leader_id'   => $p['leader_id']   ?? null,
                    'company_id'  => $p['company_id']  ?? null,
                    'is_billable' => !empty($p['is_billable']),
                ], $managerProjects),
            ];
        }
        return $result;
    }

    private function buildClientsView(array $projects, array $companies): array
    {
        $companyNames = [];
        foreach ($companies as $c) {
            if (!empty($c['id'])) {
                $companyNames[$c['id']] = $c['name'] ?? "Company #{$c['id']}";
            }
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
                'name'       => $p['name']        ?? "Project #{$p['id']}",
                'url'        => $p['url_path']    ?? null,
                'task_count' => $p['count_tasks'] ?? 0,
                'leader_id'  => $p['leader_id']   ?? null,
            ];
        }

        $result = array_values($grouped);
        usort($result, fn($a, $b) => strcmp($a['client']['name'], $b['client']['name']));
        return $result;
    }

    /* ─────────────────────────────────────────────────────────────────
     * Internals
     * ───────────────────────────────────────────────────────────────── */

    private function writeViewBlob(string $key, array $value): void
    {
        // 24h TTL means a missed cron still serves stale data instead of nothing.
        DB::query(
            'REPLACE INTO cache_store (cache_key, value, expiration) VALUES (?, ?, ?)',
            [$key, serialize($value), time() + 86400]
        );
    }

    private function mapPriority(int $priority): string
    {
        if ($priority >= 2) return 'urgent';
        if ($priority == 1) return 'high';
        if ($priority == 0) return 'normal';
        return 'low';
    }

    private function buildCurl(string $endpoint): \CurlHandle
    {
        $ch = curl_init($this->baseUrl . $endpoint);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => self::TIMEOUT,
            CURLOPT_HTTPHEADER     => ['X-Angie-AuthApiToken: ' . $this->token],
        ]);
        return $ch;
    }

    private function request(string $endpoint): array
    {
        if (!$this->token) return [];

        $ch = $this->buildCurl($endpoint);
        $body = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($code >= 200 && $code < 300 && $body) {
            $data = json_decode($body, true);
            return is_array($data) ? $data : [];
        }
        return [];
    }
}
