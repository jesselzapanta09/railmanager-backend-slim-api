<?php

/**
 * Router for PHP built-in development server.
 *
 * Usage from the project root:
 *   php -S localhost:5000 -t public router.php
 */

$uri = urldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));

// Serve real static files directly (uploads, favicons, etc.)
$filePath = __DIR__ . DIRECTORY_SEPARATOR . 'public' . str_replace('/', DIRECTORY_SEPARATOR, $uri);
if ($uri !== '/' && file_exists($filePath) && !is_dir($filePath)) {
    return false;
}

// Everything else → Slim front controller
require_once __DIR__ . DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR . 'index.php';
