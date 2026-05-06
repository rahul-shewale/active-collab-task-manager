<?php
namespace App\Core;

use App\Services\TrelloService;
use App\Services\MantisService;
use App\Services\HubstaffService;
use App\Services\ActiveCollabService;

/**
 * Central orchestrator for all integrations.
 *
 * Used by both `bin/cron.php` (scheduled) and `POST /api/sync/cron`
 * (manual "Sync Data" button).
 *
 * Each step is wrapped in try/catch so a failed source never aborts the
 * others. Per-source results are written to `integration_logs` and the
 * overall run status is mirrored into the single-row `ac_sync_state`
 * (re-purposed as a global "last cron run" marker).
 */
class SyncRunner
{
    /**
     * Run every integration in sequence and return a summary.
     *
     * @return array{
     *     trello:   array,
     *     mantis:   array,
     *     hubstaff: array,
     *     ac:       array,
     *     started_at: string,
     *     finished_at: string,
     *     duration_ms: int,
     *     status: string,
     * }
     */
    public function runAll(): array
    {
        $startedAt = microtime(true);
        $startTs   = date('Y-m-d H:i:s');

        $trello   = $this->runSource('trello',   fn() => (new TrelloService())->syncAll());
        $mantis   = $this->runSource('mantis',   fn() => (new MantisService())->syncAll());

        $hubstaff = $this->runSource('hubstaff', function () {
            $svc = new HubstaffService();
            $svc->syncMembers();
            return $svc->syncActivities();
        });

        $ac = $this->runSource('activecollab', fn() => (new ActiveCollabService())->syncAll());

        $duration = (int) ((microtime(true) - $startedAt) * 1000);

        $statuses = array_column([$trello, $mantis, $hubstaff, $ac], 'status');
        $overall  = in_array('error', $statuses, true)
            ? 'error'
            : (in_array('partial', $statuses, true) ? 'partial' : 'success');

        $errorBlob = trim(implode(' | ', array_filter([
            !empty($trello['errors'])   ? 'trello: '   . implode('; ', $trello['errors'])   : null,
            !empty($mantis['errors'])   ? 'mantis: '   . implode('; ', $mantis['errors'])   : null,
            !empty($hubstaff['errors']) ? 'hubstaff: ' . implode('; ', $hubstaff['errors']) : null,
            !empty($ac['errors'])       ? 'ac: '       . implode('; ', $ac['errors'])       : null,
        ])));

        $this->updateGlobalState($overall, $errorBlob ?: null, $duration);

        return [
            'trello'      => $trello,
            'mantis'      => $mantis,
            'hubstaff'    => $hubstaff,
            'ac'          => $ac,
            'started_at'  => $startTs,
            'finished_at' => date('Y-m-d H:i:s'),
            'duration_ms' => $duration,
            'status'      => $overall,
        ];
    }

    /**
     * Run a single source, log it, and normalise the return shape.
     * Never throws — exceptions are captured into the result/log.
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
            try {
                DB::insert('integration_logs', [
                    'source'       => $source,
                    'status'       => 'error',
                    'tasks_synced' => 0,
                    'message'      => $e->getMessage(),
                    'synced_at'    => date('Y-m-d H:i:s'),
                ]);
            } catch (\Throwable) {
                // last resort: don't let the logger itself crash the runner
            }

            return [
                'source' => $source,
                'synced' => 0,
                'errors' => [$e->getMessage()],
                'status' => 'error',
            ];
        }
    }

    /**
     * Update the single-row ac_sync_state marker — also covers
     * Trello/Mantis/Hubstaff: it's the global "last cron run" record
     * surfaced in the dashboard header.
     */
    private function updateGlobalState(string $status, ?string $error, int $durationMs): void
    {
        try {
            DB::query(
                'INSERT INTO ac_sync_state (id, last_run_at, last_status, last_error, duration_ms)
                 VALUES (1, NOW(), ?, ?, ?)
                 ON DUPLICATE KEY UPDATE
                    last_run_at = VALUES(last_run_at),
                    last_status = VALUES(last_status),
                    last_error  = VALUES(last_error),
                    duration_ms = VALUES(duration_ms)',
                [$status, $error, $durationMs]
            );
        } catch (\Throwable) {
            // ac_sync_state may not exist yet on a stale install — surface via integration_logs.
        }
    }
}
