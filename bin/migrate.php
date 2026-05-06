<?php
/**
 * bin/migrate.php
 *
 * Idempotent schema migration script.
 *
 * Adds the ActiveCollab tables (`ac_users`, `ac_companies`, `ac_projects`,
 * `ac_tasks`, `ac_sync_state`) used by the cron-driven sync flow.
 *
 * Run:
 *     php bin/migrate.php
 *
 * Safe to run repeatedly — every statement uses CREATE TABLE IF NOT EXISTS
 * or INSERT IGNORE so existing data is preserved.
 */

require_once __DIR__ . '/../app/Core/Bootstrap.php';

use App\Core\Bootstrap;
use App\Core\DB;

Bootstrap::init();

$statements = [

    "CREATE TABLE IF NOT EXISTS `ac_users` (
        `id` BIGINT UNSIGNED NOT NULL,
        `display_name` VARCHAR(255) NOT NULL,
        `email` VARCHAR(255) DEFAULT NULL,
        `avatar_url` VARCHAR(512) DEFAULT NULL,
        `is_archived` TINYINT(1) NOT NULL DEFAULT 0,
        `is_trashed` TINYINT(1) NOT NULL DEFAULT 0,
        `synced_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        KEY `ac_users_email_idx` (`email`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

    "CREATE TABLE IF NOT EXISTS `ac_companies` (
        `id` BIGINT UNSIGNED NOT NULL,
        `name` VARCHAR(255) NOT NULL,
        `synced_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

    "CREATE TABLE IF NOT EXISTS `ac_projects` (
        `id` BIGINT UNSIGNED NOT NULL,
        `name` VARCHAR(255) NOT NULL,
        `url_path` VARCHAR(512) DEFAULT NULL,
        `leader_id` BIGINT UNSIGNED DEFAULT NULL,
        `company_id` BIGINT UNSIGNED DEFAULT NULL,
        `count_tasks` INT NOT NULL DEFAULT 0,
        `is_billable` TINYINT(1) NOT NULL DEFAULT 0,
        `is_completed` TINYINT(1) NOT NULL DEFAULT 0,
        `is_trashed` TINYINT(1) NOT NULL DEFAULT 0,
        `synced_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        KEY `ac_projects_leader_idx` (`leader_id`),
        KEY `ac_projects_company_idx` (`company_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

    "CREATE TABLE IF NOT EXISTS `ac_tasks` (
        `id` BIGINT UNSIGNED NOT NULL,
        `project_id` BIGINT UNSIGNED NOT NULL,
        `name` VARCHAR(512) NOT NULL,
        `assignee_id` BIGINT UNSIGNED DEFAULT NULL,
        `task_list_name` VARCHAR(255) DEFAULT NULL,
        `priority` INT NOT NULL DEFAULT 0,
        `is_completed` TINYINT(1) NOT NULL DEFAULT 0,
        `due_on` DATE DEFAULT NULL,
        `url` VARCHAR(512) DEFAULT NULL,
        `body_formatted` MEDIUMTEXT,
        `synced_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        KEY `ac_tasks_project_idx` (`project_id`),
        KEY `ac_tasks_assignee_idx` (`assignee_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

    "CREATE TABLE IF NOT EXISTS `ac_sync_state` (
        `id` TINYINT UNSIGNED NOT NULL,
        `last_run_at` TIMESTAMP NULL DEFAULT NULL,
        `last_status` VARCHAR(32) DEFAULT NULL,
        `last_error` TEXT,
        `duration_ms` INT NOT NULL DEFAULT 0,
        PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

    "INSERT IGNORE INTO `ac_sync_state` (`id`, `last_run_at`, `last_status`, `last_error`, `duration_ms`)
     VALUES (1, NULL, NULL, NULL, 0)",
];

$ok = 0;
foreach ($statements as $sql) {
    try {
        DB::query($sql);
        $ok++;
        echo "  [ok] " . substr(preg_replace('/\s+/', ' ', $sql), 0, 80) . "...\n";
    } catch (\Throwable $e) {
        fwrite(STDERR, "  [fail] " . substr(preg_replace('/\s+/', ' ', $sql), 0, 80) . "...\n");
        fwrite(STDERR, "         " . $e->getMessage() . "\n");
    }
}

echo "\nMigration complete: $ok / " . count($statements) . " statements applied.\n";
