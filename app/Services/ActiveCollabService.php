<?php
namespace App\Services;

use App\Core\Bootstrap;

class ActiveCollabService
{
    private string $baseUrl;
    private string $token;
    private const TIMEOUT = 15;

    public function __construct()
    {
        $this->baseUrl = rtrim(Bootstrap::config('activecollab.base_url', ''), '/');
        $this->token   = Bootstrap::config('activecollab.token', '');
    }

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
        return array_values(array_filter($projects, fn($p) => empty($p['is_completed']) && empty($p['is_trashed'])));
    }

    public function getTasksForProjects(array $projectIds): array
    {
        if (empty($projectIds)) return [];

        $allTasks = [];
        // Sequential HTTP requests (no pool in plain PHP — use curl_multi for parallelism)
        $chunks = array_chunk($projectIds, 30);

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
