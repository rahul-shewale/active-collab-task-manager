<?php
// bin/cron.php
//
// Scheduled entry point for the dashboard's background sync.
//
// Suggested crontab (every 15 minutes):
//
//     */15 * * * * php /var/www/html/task-manager/bin/cron.php >> /var/www/html/task-manager/storage/cron.log 2>&1
//
// Same orchestration is also reachable via `POST /api/sync/cron` from
// the dashboard's "Sync Data" button — both paths call SyncRunner::runAll().

require_once __DIR__ . '/../app/Core/Bootstrap.php';

use App\Core\Bootstrap;
use App\Core\SyncRunner;

Bootstrap::init();

@set_time_limit(0);
ignore_user_abort(true);

$summary = (new SyncRunner())->runAll();

echo "[" . date('Y-m-d H:i:s') . "] sync run finished: "
   . "status={$summary['status']} "
   . "duration={$summary['duration_ms']}ms "
   . "trello=" . ($summary['trello']['synced']   ?? 0) . " "
   . "mantis=" . ($summary['mantis']['synced']   ?? 0) . " "
   . "hubstaff=" . ($summary['hubstaff']['synced'] ?? 0) . " "
   . "ac=" . ($summary['ac']['synced']         ?? 0)
   . PHP_EOL;

foreach (['trello', 'mantis', 'hubstaff', 'ac'] as $src) {
    if (!empty($summary[$src]['errors'])) {
        echo "  [$src] errors: " . implode(' | ', $summary[$src]['errors']) . PHP_EOL;
    }
}

// Non-zero exit on hard failure so cron / monitoring can pick it up.
exit($summary['status'] === 'error' ? 1 : 0);
