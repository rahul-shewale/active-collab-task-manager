<?php
/**
 * Router for PHP built-in server (`php -S`).
 *
 * Apache/Nginx use rewrite rules from .htaccess / server config.
 * The built-in server does not, so we map routes manually here.
 */

$uri  = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$file = __DIR__ . $uri;

// Serve real files directly (css/js/images/etc.).
if ($uri !== '/' && file_exists($file) && !is_dir($file)) {
    return false;
}

// Route /api/* requests to api.php.
if (str_starts_with($uri, '/api/')) {
    require __DIR__ . '/api.php';
    return true;
}

// Everything else -> index.php.
require __DIR__ . '/index.php';
return true;

