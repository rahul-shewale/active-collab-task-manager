<?php
namespace App\Controllers;

use App\Core\DB;
use App\Core\Request;
use App\Core\Response;

/**
 * ActiveCollab read endpoints — DB-only.
 *
 * The four view blobs (`ac_teams_view`, `ac_projects_view`,
 * `ac_managers_view`, `ac_clients_view`) are populated by the cron job
 * (or the manual "Sync Data" button) via `ActiveCollabService::syncAll()`
 * and stored in the `cache_store` table. These endpoints never call the
 * ActiveCollab API directly.
 *
 * Response shape: always a plain array (matching the legacy contract
 * the AC JS files expect). When the cron has never run we return an
 * empty array — the dashboard's "Last synced" label, fed by
 * /api/sync/status, tells the user when the next refresh is due.
 */
class ActiveCollabController
{
    public function teamsView(Request $request): void
    {
        Response::json($this->readBlob('ac_teams_view'));
    }

    public function projectsView(Request $request): void
    {
        Response::json($this->readBlob('ac_projects_view'));
    }

    public function managersView(Request $request): void
    {
        Response::json($this->readBlob('ac_managers_view'));
    }

    public function clientsView(Request $request): void
    {
        Response::json($this->readBlob('ac_clients_view'));
    }

    /**
     * Read a precomputed view blob from `cache_store`.
     * Returns the stored array, or an empty array if missing /
     * unreadable. Stale (past-expiration) blobs are still served so a
     * temporarily broken cron doesn't leave the dashboard blank.
     */
    private function readBlob(string $key): array
    {
        $row = DB::fetchOne(
            'SELECT value FROM cache_store WHERE cache_key = ?',
            [$key]
        );

        if (!$row) return [];

        $value = @unserialize($row['value']);
        return is_array($value) ? $value : [];
    }
}
