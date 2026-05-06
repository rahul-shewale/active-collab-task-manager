<?php
namespace App\Controllers;

use App\Core\Auth;
use App\Core\DB;
use App\Core\Request;
use App\Core\Response;
use App\Services\TrelloService;
use App\Services\MantisService;
use App\Services\HubstaffService;

class SyncController
{
    public function trello(Request $request): void
    {
        Auth::guard($request);
        $result = $this->runSync('trello', fn() => (new TrelloService())->syncAll());
        Response::json($result, empty($result['errors']) ? 200 : 207);
    }

    public function mantis(Request $request): void
    {
        Auth::guard($request);
        $result = $this->runSync('mantis', fn() => (new MantisService())->syncAll());
        Response::json($result, empty($result['errors']) ? 200 : 207);
    }

    public function hubstaff(Request $request): void
    {
        Auth::guard($request);
        $svc = new HubstaffService();
        $result = $this->runSync('hubstaff', function () use ($svc) {
            $svc->syncMembers();
            return $svc->syncActivities();
        });
        Response::json($result, empty($result['errors']) ? 200 : 207);
    }

    public function all(Request $request): void
    {
        Auth::guard($request);
        $trello  = $this->runSync('trello',  fn() => (new TrelloService())->syncAll());
        $mantis  = $this->runSync('mantis',  fn() => (new MantisService())->syncAll());
        $svc     = new HubstaffService();
        $hubstaff = $this->runSync('hubstaff', function () use ($svc) {
            $svc->syncMembers();
            return $svc->syncActivities();
        });
        Response::json(compact('trello', 'mantis', 'hubstaff'));
    }

    public function logs(Request $request): void
    {
        Auth::guard($request);
        $logs = DB::fetchAll('SELECT * FROM integration_logs ORDER BY synced_at DESC LIMIT 40');
        Response::json($logs);
    }

    private function runSync(string $source, callable $fn): array
    {
        try {
            $result = $fn();

            DB::insert('integration_logs', [
                'source'       => $source,
                'status'       => empty($result['errors']) ? 'success' : 'partial',
                'tasks_synced' => $result['synced'] ?? 0,
                'message'      => empty($result['errors']) ? null : implode('; ', $result['errors']),
                'synced_at'    => date('Y-m-d H:i:s'),
            ]);

            return [
                'source' => $source,
                'synced' => $result['synced'] ?? 0,
                'errors' => $result['errors'] ?? [],
                'status' => empty($result['errors']) ? 'success' : 'partial',
            ];
        } catch (\Throwable $e) {
            DB::insert('integration_logs', [
                'source'       => $source,
                'status'       => 'error',
                'tasks_synced' => 0,
                'message'      => $e->getMessage(),
                'synced_at'    => date('Y-m-d H:i:s'),
            ]);

            return [
                'source' => $source,
                'synced' => 0,
                'errors' => [$e->getMessage()],
                'status' => 'error',
            ];
        }
    }
}
