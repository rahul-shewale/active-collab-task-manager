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
        $assignmentStats = [
            'cards_processed' => 0,
            'assigned' => 0,
            'unassigned' => 0,
            'via_member' => 0,
            'via_mention' => 0,
            'via_email' => 0,
        ];

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
            $handles["actions_{$bid}"]    = $this->buildCurl("/boards/{$bid}/actions", ['filter' => 'commentCard', 'limit' => 500]);
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

        // Build user maps once to avoid per-card queries.
        $users = DB::fetchAll('SELECT id, trello_member_id, email, name FROM users');
        $memberMap = [];
        $emailMap = [];
        $usernameMap = [];
        $namePrefixMap = [];
        foreach ($users as $u) {
            if (!empty($u['trello_member_id'])) {
                $memberMap[$u['trello_member_id']] = (int) $u['id'];
            }
            $email = strtolower(trim((string) ($u['email'] ?? '')));
            if ($email !== '') {
                $emailMap[$email] = (int) $u['id'];
                $localPart = explode('@', $email)[0] ?? '';
                if ($localPart !== '' && !isset($usernameMap[$localPart])) {
                    $usernameMap[$localPart] = (int) $u['id'];
                }
            }

            $name = strtolower(trim((string) ($u['name'] ?? '')));
            if ($name !== '' && !isset($namePrefixMap[$name])) {
                $namePrefixMap[$name] = (int) $u['id'];
            }
        }

        foreach ($filteredBoards as $board) {
            $bid   = $board['id'];
            $cards = $responses["cards_{$bid}"] ?? [];
            $lists = $responses["lists_{$bid}"] ?? [];
            $checklists = $responses["checklists_{$bid}"] ?? [];
            $actions = $responses["actions_{$bid}"] ?? [];
            $boardStats = [
                'board' => $board['name'] ?? $bid,
                'cards_processed' => 0,
                'assigned' => 0,
                'unassigned' => 0,
                'via_member' => 0,
                'via_mention' => 0,
                'via_email' => 0,
            ];

            $listMap = [];
            foreach ($lists as $l) $listMap[$l['id']] = $l['name'];
            $actionsByCard = [];
            foreach ($actions as $action) {
                $cardId = $action['data']['card']['id'] ?? null;
                if (!$cardId) {
                    continue;
                }
                if (!isset($actionsByCard[$cardId])) {
                    $actionsByCard[$cardId] = [];
                }
                $actionsByCard[$cardId][] = $action;
            }

            $checklistsByCard = [];
            foreach ($checklists as $checklist) {
                $cardId = $checklist['idCard'] ?? null;
                if (!$cardId) {
                    continue;
                }
                if (!isset($checklistsByCard[$cardId])) {
                    $checklistsByCard[$cardId] = [];
                }
                $checklistsByCard[$cardId][] = $checklist;
            }

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
                $assignmentStats['cards_processed']++;
                $boardStats['cards_processed']++;

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
                    // Use explicit positional values — relying on
                    // $taskData ordering caused 'external_id' and
                    // 'created_at' to be swapped, which crashed the
                    // INSERT with "Incorrect datetime value".
                    $now = date('Y-m-d H:i:s');
                    DB::query(
                        "INSERT INTO tasks (title,status,priority,due_date,source,board_name,list_name,url,external_id,created_at,updated_at)
                         VALUES (?,?,?,?,?,?,?,?,?,?,?)
                         ON DUPLICATE KEY UPDATE title=VALUES(title), status=VALUES(status), priority=VALUES(priority),
                         due_date=VALUES(due_date), board_name=VALUES(board_name), list_name=VALUES(list_name),
                         url=VALUES(url), updated_at=VALUES(updated_at)",
                        [
                            $taskData['title'],
                            $taskData['status'],
                            $taskData['priority'],
                            $taskData['due_date'],
                            $taskData['source'],
                            $taskData['board_name'],
                            $taskData['list_name'],
                            $taskData['url'],
                            $card['id'],
                            $now,
                            $now,
                        ]
                    );
                    $taskId = (int) DB::lastInsertId();
                }

                $targetUserIds = [];
                $hasMemberMatch = false;
                $hasMentionMatch = false;
                $hasEmailMatch = false;
                foreach ($card['idMembers'] ?? [] as $memberId) {
                    if (isset($memberMap[$memberId])) {
                        $targetUserIds[] = $memberMap[$memberId];
                        $hasMemberMatch = true;
                    }
                }

                $textContent = trim(($card['name'] ?? '') . ' ' . ($card['desc'] ?? ''));
                foreach ($actionsByCard[$card['id']] ?? [] as $action) {
                    $textContent .= ' ' . ($action['data']['text'] ?? '');
                }
                foreach ($checklistsByCard[$card['id']] ?? [] as $checklist) {
                    foreach ($checklist['checkItems'] ?? [] as $item) {
                        $textContent .= ' ' . ($item['name'] ?? '');
                    }
                }
                $textContent = strtolower($textContent);

                if (preg_match_all('/@([a-z0-9_\.]+)/i', $textContent, $mentionMatches)) {
                    foreach (array_unique($mentionMatches[1]) as $username) {
                        if (isset($usernameMap[$username])) {
                            $targetUserIds[] = $usernameMap[$username];
                            $hasMentionMatch = true;
                            continue;
                        }
                        foreach ($namePrefixMap as $name => $userId) {
                            if (str_starts_with($name, $username)) {
                                $targetUserIds[] = $userId;
                                $hasMentionMatch = true;
                                break;
                            }
                        }
                    }
                }

                if (preg_match_all('/[a-z0-9\._%+\-]+@[a-z0-9\.-]+\.[a-z]{2,}/i', $textContent, $emailMatches)) {
                    foreach (array_unique($emailMatches[0]) as $email) {
                        $email = strtolower($email);
                        if (isset($emailMap[$email])) {
                            $targetUserIds[] = $emailMap[$email];
                            $hasEmailMatch = true;
                        }
                    }
                }

                // Fallback: infer assignee from list/title keywords like "rahul", "manish nadar", etc.
                // This helps boards where Trello members are not mapped but lists are person-specific.
                if (empty($targetUserIds)) {
                    $keywordText = strtolower(trim(($listName ?? '') . ' ' . ($card['name'] ?? '')));
                    foreach ($namePrefixMap as $name => $userId) {
                        if ($name !== '' && str_contains($keywordText, $name)) {
                            $targetUserIds[] = $userId;
                            $hasMentionMatch = true;
                            break;
                        }
                    }
                }

                $targetUserIds = array_values(array_unique($targetUserIds));
                if (!empty($targetUserIds)) {
                    $assignmentStats['assigned']++;
                    $boardStats['assigned']++;
                } else {
                    $assignmentStats['unassigned']++;
                    $boardStats['unassigned']++;
                }
                if ($hasMemberMatch) {
                    $assignmentStats['via_member']++;
                    $boardStats['via_member']++;
                }
                if ($hasMentionMatch) {
                    $assignmentStats['via_mention']++;
                    $boardStats['via_mention']++;
                }
                if ($hasEmailMatch) {
                    $assignmentStats['via_email']++;
                    $boardStats['via_email']++;
                }

                // Sync task-user pivot using trello members + mention/email extraction
                DB::delete('task_user', 'task_id = ?', [$taskId]);
                foreach ($targetUserIds as $userId) {
                    try {
                        DB::query(
                            'INSERT IGNORE INTO task_user (task_id, user_id, created_at) VALUES (?, ?, NOW())',
                            [$taskId, $userId]
                        );
                    } catch (\Throwable) {}
                }
                DB::update('tasks', ['assigned_to' => $targetUserIds[0] ?? null], 'id = ?', [$taskId]);

                $synced++;
            }

            error_log('TrelloService board assignment stats: ' . json_encode($boardStats));
        }

        error_log('TrelloService sync assignment summary: ' . json_encode($assignmentStats));

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
