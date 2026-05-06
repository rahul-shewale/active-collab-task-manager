<?php
namespace App\Services;

use App\Core\Bootstrap;
use App\Core\DB;

class TrelloService
{
    private string $apiKey;
    private string $apiToken;
    private string $baseUrl = 'https://api.trello.com/1';

    private array $boardFilters = [
        'leaddetector'  => ['Pending list', 'rahul', 'manish', 'shifa', 'sandesh', 'vedant'],
        'Minute Pages'  => ['Pending emails', 'manish nadar', 'rahul', 'sandesh', 'shifa', 'vedant'],
        'RocketSkip'    => ['Pending list', 'manish nadar', 'rahul', 'sandesh', 'shifa', 'vedant'],
        'S@geWorkspace' => ['Changes from client'],
    ];

    public function __construct()
    {
        $this->apiKey   = Bootstrap::config('trello.key', '');
        $this->apiToken = Bootstrap::config('trello.token', '');
    }

    public function syncAll(): array
    {
        $synced = 0;
        $errors = [];

        $boards = $this->getBoards();
        $filteredBoards = array_filter($boards, function ($board) {
            foreach ($this->boardFilters as $key => $_) {
                if (stripos($board['name'], $key) !== false) return true;
            }
            return false;
        });

        if (empty($filteredBoards)) {
            return ['synced' => 0, 'errors' => []];
        }

        // Parallel fetch using curl_multi
        $multiHandle = curl_multi_init();
        $handles     = [];

        foreach ($filteredBoards as $board) {
            $bid = $board['id'];
            $handles["cards_{$bid}"]      = $this->buildCurl("/boards/{$bid}/cards", ['filter' => 'open', 'fields' => 'id,name,desc,due,idMembers,idList,shortUrl,labels,dateLastActivity,closed']);
            $handles["lists_{$bid}"]      = $this->buildCurl("/boards/{$bid}/lists");
            $handles["checklists_{$bid}"] = $this->buildCurl("/boards/{$bid}/checklists");
        }

        foreach ($handles as $ch) curl_multi_add_handle($multiHandle, $ch);

        $running = null;
        do {
            curl_multi_exec($multiHandle, $running);
            curl_multi_select($multiHandle);
        } while ($running > 0);

        $responses = [];
        foreach ($handles as $key => $ch) {
            $responses[$key] = json_decode(curl_multi_getcontent($ch), true) ?? [];
            curl_multi_remove_handle($multiHandle, $ch);
            curl_close($ch);
        }
        curl_multi_close($multiHandle);

        // Build member map: Trello member_id -> local user
        $users = DB::fetchAll('SELECT id, trello_member_id FROM users WHERE trello_member_id IS NOT NULL');
        $memberMap = [];
        foreach ($users as $u) $memberMap[$u['trello_member_id']] = $u['id'];

        foreach ($filteredBoards as $board) {
            $bid   = $board['id'];
            $cards = $responses["cards_{$bid}"] ?? [];
            $lists = $responses["lists_{$bid}"] ?? [];

            $listMap = [];
            foreach ($lists as $l) $listMap[$l['id']] = $l['name'];

            // Determine allowed list names for this board
            $allowedLists = null;
            foreach ($this->boardFilters as $key => $listNames) {
                if (stripos($board['name'], $key) !== false) {
                    $allowedLists = array_map('strtolower', $listNames);
                    break;
                }
            }

            foreach ($cards as $card) {
                $listName = $listMap[$card['idList']] ?? '';

                if ($allowedLists !== null) {
                    $listLower = strtolower($listName);
                    $match = false;
                    foreach ($allowedLists as $allowed) {
                        if (str_contains($listLower, $allowed)) { $match = true; break; }
                    }
                    if (!$match) continue;
                }

                $priority = 'normal';
                foreach ($card['labels'] ?? [] as $label) {
                    $name = strtolower($label['name'] ?? '');
                    if (str_contains($name, 'urgent')) { $priority = 'urgent'; break; }
                    if (str_contains($name, 'high'))   { $priority = 'high'; break; }
                    if (str_contains($name, 'low'))    { $priority = 'low'; break; }
                }

                $status = 'open';
                $ln = strtolower($listName);
                if (str_contains($ln, 'done') || str_contains($ln, 'complete')) $status = 'done';
                if (str_contains($ln, 'progress') || str_contains($ln, 'doing')) $status = 'in_progress';

                $dueDate = $card['due'] ? date('Y-m-d', strtotime($card['due'])) : null;

                // Upsert task
                $existing = DB::fetchOne(
                    "SELECT id FROM tasks WHERE source = 'trello' AND external_id = ?",
                    [$card['id']]
                );

                $taskData = [
                    'title'      => $card['name'],
                    'status'     => $status,
                    'priority'   => $priority,
                    'due_date'   => $dueDate,
                    'source'     => 'trello',
                    'board_name' => $board['name'],
                    'list_name'  => $listName,
                    'url'        => $card['shortUrl'] ?? null,
                    'updated_at' => date('Y-m-d H:i:s'),
                ];

                if ($existing) {
                    DB::update('tasks', $taskData, 'id = ?', [$existing['id']]);
                    $taskId = $existing['id'];
                } else {
                    $taskData['external_id'] = $card['id'];
                    $taskData['created_at']  = date('Y-m-d H:i:s');
                    DB::query(
                        "INSERT INTO tasks (title,status,priority,due_date,source,board_name,list_name,url,external_id,created_at,updated_at)
                         VALUES (?,?,?,?,?,?,?,?,?,?,?)
                         ON DUPLICATE KEY UPDATE title=VALUES(title), status=VALUES(status), priority=VALUES(priority),
                         due_date=VALUES(due_date), board_name=VALUES(board_name), list_name=VALUES(list_name),
                         url=VALUES(url), updated_at=VALUES(updated_at)",
                        array_values($taskData)
                    );
                    $taskId = (int) DB::lastInsertId();
                }

                // Sync task-user pivot
                DB::delete('task_user', 'task_id = ?', [$taskId]);
                foreach ($card['idMembers'] ?? [] as $memberId) {
                    if (isset($memberMap[$memberId])) {
                        try {
                            DB::query(
                                'INSERT IGNORE INTO task_user (task_id, user_id, created_at) VALUES (?, ?, NOW())',
                                [$taskId, $memberMap[$memberId]]
                            );
                        } catch (\Throwable) {}
                    }
                }

                $synced++;
            }
        }

        return ['synced' => $synced, 'errors' => $errors];
    }

    private function getBoards(): array
    {
        $url = "{$this->baseUrl}/members/me/boards?" . http_build_query([
            'key'    => $this->apiKey,
            'token'  => $this->apiToken,
            'filter' => 'open',
            'fields' => 'id,name',
        ]);

        $ch = curl_init($url);
        curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 15]);
        $body = curl_exec($ch);
        curl_close($ch);

        return json_decode($body, true) ?? [];
    }

    private function buildCurl(string $endpoint, array $extra = []): \CurlHandle
    {
        $params = array_merge(['key' => $this->apiKey, 'token' => $this->apiToken], $extra);
        $url    = $this->baseUrl . $endpoint . '?' . http_build_query($params);
        $ch     = curl_init($url);
        curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 15]);
        return $ch;
    }
}
