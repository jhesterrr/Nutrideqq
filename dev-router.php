<?php
// dev-router.php — used with PHP's built-in server (`php -S host:port dev-router.php`)
// to mirror the production .htaccess deny rules (the built-in server ignores .htaccess).
$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?? '/';

// Same rules as legacy-php/.htaccess
if (preg_match('#/(api/sessions|dev-tools)/#', $uri) || preg_match('#\.log$#', $uri)) {
    http_response_code(403);
    header('Content-Type: text/plain');
    exit('Forbidden');
}

// Serve the requested file if it exists; otherwise fall back to index.php
// (mirrors the .htaccess front-controller behaviour).
if ($uri !== '/' && file_exists(__DIR__ . $uri) && !is_dir(__DIR__ . $uri)) {
    return false; // let PHP's built-in server serve the static/php file
}

require __DIR__ . '/index.php';
