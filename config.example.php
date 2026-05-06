<?php
// ─── config.php ──────────────────────────────────────────────────────────────
// Copy this file to config.php and fill in your credentials.

return [

    // ─── Application ─────────────────────────────────────────────────────────
    'app' => [
        'name'     => 'Task Manager Dashboard',
        'url'      => 'http://localhost',
        'debug'    => true,
        'timezone' => 'Asia/Kolkata',
    ],

    // ─── Database ─────────────────────────────────────────────────────────────
    'db' => [
        'host'     => '127.0.0.1',
        'port'     => 3306,
        'database' => 'task_dashboard_php',
        'username' => 'root',
        'password' => 'NewPass123!',
        'charset'  => 'utf8mb4',
    ],

    // ─── Trello ───────────────────────────────────────────────────────────────
    'trello' => [
        'key'   => '',   // https://trello.com/app-key
        'token' => '',
    ],

    // ─── MantisBT ─────────────────────────────────────────────────────────────
    'mantis' => [
        'base_url' => 'https://mantis.thetemplatedemo.com',
        'token'    => '',
    ],

    // ─── Hubstaff ─────────────────────────────────────────────────────────────
    'hubstaff' => [
        'org_id'        => '117664',
        'refresh_token' => '',   // stored in storage/hubstaff_refresh_token.txt if present
    ],

    // ─── ActiveCollab ─────────────────────────────────────────────────────────
    'activecollab' => [
        'base_url' => 'https://designer.edeveloperz.com',   // e.g. https://designer.edeveloperz.com
        'token'    => '',
    ],

];
