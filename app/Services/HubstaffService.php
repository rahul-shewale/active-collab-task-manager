<?php
namespace App\Services;

use App\Core\Bootstrap;
use App\Core\DB;

class HubstaffService
{
    private string $orgId;
    private string $baseUrl = 'https://api.hubstaff.com/v2';
    private string $tokenFile;

    private array $whitelistedEmails = [
        'arbina@ebrandz.com', 'shifa@ebrandz.com', 'rahul.s@ebrandz.com',
        'asmita@ebrandz.com', 'manish@ebrandz.com',
    ];

    public function __construct()
    {
        $this->orgId     = Bootstrap::config('hubstaff.org_id', '');
        $this->tokenFile = dirname(__DIR__, 2) . '/storage/hubstaff_refresh_token.txt';
    }

    private function getAccessToken(): string
    {
        // Try cached token in DB (reuse for ~23h)
        $cached = DB::fetchOne(
            "SELECT value, expiration FROM cache_store WHERE cache_key = 'hubstaff_access_token'"
        );
        if ($cached && $cached['expiration'] > time()) {
            return unserialize($cached['value']);
        }

        $refreshToken = file_exists($this->tokenFile)
            ? trim(file_get_contents($this->tokenFile))
            : Bootstrap::config('hubstaff.refresh_token', '');

        if (!$refreshToken) {
            throw new \RuntimeException('Missing Hubstaff refresh token');
        }

        $ch = curl_init('https://account.hubstaff.com/access_tokens');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => http_build_query([
                'grant_type'    => 'refresh_token',
                'refresh_token' => $refreshToken,
            ]),
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/x-www-form-urlencoded',
                'Accept: application/json',
            ],
        ]);

        $body = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $data = json_decode($body, true) ?? [];

        if ($code >= 400) {
            throw new \RuntimeException("Hubstaff token exchange failed. Status: $code");
        }

        if (!empty($data['refresh_token'])) {
            file_put_contents($this->tokenFile, $data['refresh_token']);
        }

        $token = $data['access_token'];

        // Cache for 23.5 hours
        DB::query(
            'REPLACE INTO cache_store (cache_key, value, expiration) VALUES (?, ?, ?)',
            ['hubstaff_access_token', serialize($token), time() + 85000]
        );

        return $token;
    }

    public function syncMembers(): int
    {
        $accessToken = $this->getAccessToken();
        $synced = 0;

        $ch = curl_init("{$this->baseUrl}/organizations/{$this->orgId}/members?include_removed=false&page_start_id=0&page_limit=500");
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => ["Authorization: Bearer $accessToken", 'Accept: application/json'],
        ]);
        $body = curl_exec($ch);
        curl_close($ch);

        $data    = json_decode($body, true) ?? [];
        $members = $data['members'] ?? [];

        foreach ($members as $member) {
            $email = $member['email'] ?? '';
            if (!in_array($email, $this->whitelistedEmails)) continue;

            $existing = DB::fetchOne(
                'SELECT id FROM hubstaff_members WHERE hubstaff_user_id = ?',
                [$member['user_id']]
            );

            $row = [
                'name'            => $member['name'] ?? $email,
                'email'           => $email,
                'hubstaff_user_id'=> $member['user_id'],
                'updated_at'      => date('Y-m-d H:i:s'),
            ];

            if ($existing) {
                DB::update('hubstaff_members', $row, 'id = ?', [$existing['id']]);
            } else {
                $row['created_at'] = date('Y-m-d H:i:s');
                DB::insert('hubstaff_members', $row);
            }
            $synced++;
        }

        return $synced;
    }

    public function syncActivities(): array
    {
        $accessToken = $this->getAccessToken();
        $synced      = 0;
        $errors      = [];

        $startDate = date('Y-m-d', strtotime('-7 days'));
        $stopDate  = date('Y-m-d');

        $members = DB::fetchAll('SELECT * FROM hubstaff_members');

        foreach ($members as $member) {
            $url = "{$this->baseUrl}/organizations/{$this->orgId}/activities/daily?"
                . http_build_query([
                    'date[start]' => $startDate,
                    'date[stop]'  => $stopDate,
                    'user_ids'    => $member['hubstaff_user_id'],
                    'page_limit'  => 500,
                ]);

            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HTTPHEADER     => ["Authorization: Bearer $accessToken", 'Accept: application/json'],
            ]);
            $body = curl_exec($ch);
            $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($code >= 400) {
                $errors[] = "Failed to fetch activities for {$member['name']}";
                continue;
            }

            $data       = json_decode($body, true) ?? [];
            $activities = $data['daily_activities'] ?? [];

            foreach ($activities as $act) {
                DB::query(
                    "INSERT INTO hubstaff_activities
                        (hubstaff_member_id, hubstaff_project_id, hubstaff_task_id, tracked_date,
                         tracked_seconds, keyboard_activity, mouse_activity, overall_activity, synced_at)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())
                     ON DUPLICATE KEY UPDATE
                        tracked_seconds=VALUES(tracked_seconds),
                        keyboard_activity=VALUES(keyboard_activity),
                        mouse_activity=VALUES(mouse_activity),
                        overall_activity=VALUES(overall_activity),
                        synced_at=NOW()",
                    [
                        $member['id'],
                        $act['project_id']  ?? null,
                        $act['task_id']     ?? null,
                        $act['date'],
                        $act['tracked']     ?? 0,
                        $act['keyboard']    ?? 0,
                        $act['mouse']       ?? 0,
                        $act['overall']     ?? 0,
                    ]
                );
                $synced++;
            }
        }

        return ['synced' => $synced, 'errors' => $errors];
    }
}
