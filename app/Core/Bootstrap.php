<?php
// ─── app/Core/Bootstrap.php ───────────────────────────────────────────────────
// Loaded by every entry-point (public/index.php, public/api.php).

namespace App\Core;

class Bootstrap
{
    private static array $config = [];

    public static function init(): void
    {
        // ── Load config ───────────────────────────────────────────────────────
        $cfgFile = dirname(__DIR__, 2) . '/config.php';
        if (!file_exists($cfgFile)) {
            die('config.php not found. Copy config.php.example to config.php and fill in your credentials.');
        }
        self::$config = require $cfgFile;

        // ── Timezone ──────────────────────────────────────────────────────────
        date_default_timezone_set(self::$config['app']['timezone'] ?? 'UTC');

        // ── Error reporting ───────────────────────────────────────────────────
        $debug = self::$config['app']['debug'] ?? false;
        error_reporting($debug ? E_ALL : 0);
        ini_set('display_errors', $debug ? '1' : '0');

        // ── Simple PSR-4-style autoloader ─────────────────────────────────────
        spl_autoload_register(function (string $class): void {
            // Namespace prefix: App\  → app/
            $prefix = 'App\\';
            $baseDir = dirname(__DIR__) . '/';

            if (strncmp($prefix, $class, strlen($prefix)) !== 0) {
                return;
            }
            $relative = substr($class, strlen($prefix));
            $file = $baseDir . str_replace('\\', '/', $relative) . '.php';
            if (file_exists($file)) {
                require $file;
            }
        });

        // ── Session ───────────────────────────────────────────────────────────
        // Use a project-local session directory to avoid permission issues on
        // /var/lib/php/sessions when running via built-in PHP server or custom users.
        $sessionPath = dirname(__DIR__, 2) . '/storage/sessions';
        if (!is_dir($sessionPath)) {
            @mkdir($sessionPath, 0777, true);
        }
        if (is_dir($sessionPath) && is_writable($sessionPath)) {
            ini_set('session.save_path', $sessionPath);
        }
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }

    public static function config(string $key, mixed $default = null): mixed
    {
        $parts = explode('.', $key);
        $value = self::$config;
        foreach ($parts as $part) {
            if (!is_array($value) || !array_key_exists($part, $value)) {
                return $default;
            }
            $value = $value[$part];
        }
        return $value;
    }
}
