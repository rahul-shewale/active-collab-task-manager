<?php
/**
 * Router for PHP built-in server in local development.
 *
 * Usage:
 * php -S 127.0.0.1:8003 -t public public/dev-router.php
 */

$uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$path = __DIR__ . $uri;

// Let the built-in server serve existing static files directly.
if ($uri !== '/' && is_file($path)) {
    return false;
}

// Mirror .htaccess rewrites for local dev.
if (str_starts_with($uri, '/api/')) {
    require __DIR__ . '/api.php';
    return true;
}

require __DIR__ . '/index.php';
return true;

