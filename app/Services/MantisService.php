<?php
namespace App\Services;

use App\Core\Bootstrap;
use App\Core\DB;

class MantisService
{
    private string $baseUrl;
    private string $apiToken;

    private array $statusMap = [
        10 => 'open', 20 => 'open', 30 => 'open',
        40 => 'in_progress', 50 => 'in_progress',
        80 => 'done', 90 => 'done',
    ];

    private array $priorityMap = [
        10 => 'low', 20 => 'normal', 30 => 'high', 40 => 'urgent', 50 => 'urgent',
    ];

    public function __construct()
    {
        $this->baseUrl  = rtrim(Bootstrap::config('mantis.base_url', ''), '/');
        $this->apiToken = Bootstrap::config('mantis.token', '');
    }

    public function syncAll(): array
    {
        $synced = 0;
        $errors = [];

        try {
            $issues = $this->getAllIssues();

            foreach ($issues as $issue) {
                $targetUserIds = [];

                if (isset($issue['handler']['id'])) {
                    $user = DB::fetchOne('SELECT id FROM users WHERE mantis_user_id = ?', [$issue['handler']['id']]);
                    if ($user) $targetUserIds[] = $user['id'];
                }

                if (empty($targetUserIds)) continue;

                $statusCode   = $issue['status']['id'] ?? 10;
                $priorityCode = $issue['priority']['id'] ?? 20;
                $status   = $this->statusMap[$statusCode] ?? 'open';
                $priority = $this->priorityMap[$priorityCode] ?? 'normal';
                $dueDate  = isset($issue['due_date']) ? date('Y-m-d', strtotime($issue['due_date'])) : null;

                $taskData = [
                    'title'      => $issue['summary'],
                    'description'=> $issue['description'] ?? null,
                    'status'     => $status,
                    'priority'   => $priority,
                    'due_date'   => $dueDate,
                    'source'     => 'mantis',
                    'external_id'=> (string) $issue['id'],
                    'board_name' => $issue['project']['name'] ?? 'Mantis',
                    'list_name'  => $issue['category']['name'] ?? null,
                    'url'        => $this->baseUrl . '/view.php?id=' . $issue['id'],
                    'updated_at' => date('Y-m-d H:i:s'),
                ];

                $existing = DB::fetchOne(
                    "SELECT id FROM tasks WHERE source = 'mantis' AND external_id = ?",
                    [(string) $issue['id']]
                );

                if ($existing) {
                    DB::update('tasks', $taskData, 'id = ?', [$existing['id']]);
                    $taskId = $existing['id'];
                } else {
                    $taskData['created_at'] = date('Y-m-d H:i:s');
                    DB::query(
                        "INSERT INTO tasks (title,description,status,priority,due_date,source,external_id,board_name,list_name,url,created_at,updated_at)
                         VALUES (?,?,?,?,?,?,?,?,?,?,?,?)
                         ON DUPLICATE KEY UPDATE title=VALUES(title), status=VALUES(status), updated_at=VALUES(updated_at)",
                        array_values($taskData)
                    );
                    $taskId = (int) DB::lastInsertId();
                }

                DB::delete('task_user', 'task_id = ?', [$taskId]);
                foreach ($targetUserIds as $uid) {
                    DB::query(
                        'INSERT IGNORE INTO task_user (task_id, user_id, created_at) VALUES (?, ?, NOW())',
                        [$taskId, $uid]
                    );
                }

                $synced++;
            }
        } catch (\Throwable $e) {
            $errors[] = $e->getMessage();
        }

        return ['synced' => $synced, 'errors' => $errors];
    }

    private function getAllIssues(int $pageSize = 50): array
    {
        $all  = [];
        $page = 1;

        do {
            $ch = curl_init("{$this->baseUrl}/api/rest/issues?page_size={$pageSize}&page={$page}");
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT        => 30,
                CURLOPT_HTTPHEADER     => ["Authorization: {$this->apiToken}"],
            ]);
            $body   = curl_exec($ch);
            $code   = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($code < 200 || $code >= 300) break;

            $data   = json_decode($body, true) ?? [];
            $issues = $data['issues'] ?? [];

            if (empty($issues)) break;

            $all  = array_merge($all, $issues);
            $page++;
        } while (count($issues) === $pageSize);

        return $all;
    }
}
