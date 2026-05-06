<?php
namespace App\Controllers;

use App\Core\Auth;
use App\Core\DB;
use App\Core\Request;
use App\Core\Response;
use App\Core\SyncRunner;
use App\Services\TrelloService;
use App\Services\MantisService;
use App\Services\HubstaffService;
use App\Services\ActiveCollabService;

class SyncController
{
    public function trello(Request $request): void
    {
        Auth::guard($request);
        $result = $this->runSource('trello', fn() => (new TrelloService())->syncAll());
        Response::json($result, empty($result['errors']) ? 200 : 207);
    }

    public function mantis(Request $request): void
    {
        Auth::guard($request);
        $result = $this->runSource('mantis', fn() => (new MantisService())->syncAll());
        Response::json($result, empty($result['errors']) ? 200 : 207);
    }

    public function hubstaff(Request $request): void
    {
        Auth::guard($request);
        $svc = new HubstaffService();
        $result = $this->runSource('hubstaff', function () use ($svc) {
            $svc->syncMembers();
            return $svc->syncActivities();
        });
        Response::json($result, empty($result['errors']) ? 200 : 207);
    }

    /**
     * Legacy "all" endpoint kept for backward compatibility — runs
     * Trello + Mantis + Hubstaff but NOT ActiveCollab.
     * New callers should use POST /api/sync/cron instead.
     */
    public function all(Request $request): void
    {
        Auth::guard($request);
        $trello   = $this->runSource('trello',  fn() => (new TrelloService())->syncAll());
        $mantis   = $this->runSource('mantis',  fn() => (new MantisService())->syncAll());
        $svc      = new HubstaffService();
        $hubstaff = $this->runSource('hubstaff', function () use ($svc) {
            $svc->syncMembers();
            return $svc->syncActivities();
        });
        Response::json(compact('trello', 'mantis', 'hubstaff'));
    }

    /**
     * Manual cron trigger — backs the dashboard "Sync Data" button.
     * Runs the same orchestration as bin/cron.php.
     */
    public function cron(Request $request): void
    {
        Auth::guard($request);

        // External APIs can be slow; allow long execution and don't
        // stop if the browser disconnects.
        @set_time_limit(0);
        ignore_user_abort(true);

        $summary = (new SyncRunner())->runAll();
        $code    = $summary['status'] === 'success' ? 200 : 207;
        Response::json($summary, $code);
    }

    public function logs(Request $request): void
    {
        Auth::guard($request);
        $logs = DB::fetchAll('SELECT * FROM integration_logs ORDER BY synced_at DESC LIMIT 40');
        Response::json($logs);
    }

    /**
     * Lightweight status payload for the navbar "Last synced …" label.
     */
    public function status(Request $request): void
    {
        Auth::guard($request);

        $state = DB::fetchOne('SELECT * FROM ac_sync_state WHERE id = 1');

        $sources = DB::fetchAll(
            "SELECT source, status, tasks_synced, message, synced_at
             FROM integration_logs
             WHERE id IN (SELECT MAX(id) FROM integration_logs GROUP BY source)
             ORDER BY synced_at DESC"
        );

        Response::json([
            'last_run_at' => $state['last_run_at'] ?? null,
            'last_status' => $state['last_status'] ?? null,
            'last_error'  => $state['last_error']  ?? null,
            'duration_ms' => isset($state['duration_ms']) ? (int) $state['duration_ms'] : 0,
            'sources'     => $sources,
        ]);
    }

    /**
     * Run a single source, log it, and normalise the return shape.
     * (Kept for the per-source endpoints; the bulk path uses SyncRunner.)
     */
    private function runSource(string $source, callable $fn): array
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
