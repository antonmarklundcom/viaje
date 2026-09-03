<?php
declare(strict_types=1);

/**
 * Router script for PHP's built-in server: emulates the .htaccess rules.
 *   php -S 127.0.0.1:8080 -t dist/<domain> dist/<domain>/engine/dev-router.php
 */

$root = $_SERVER['DOCUMENT_ROOT'] ?? __DIR__ . '/..';
$path = (string)(parse_url((string)($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH) ?: '/');
$path = rawurldecode($path);

// Never expose the engine's source or the site's internals (spec §13).
if (preg_match('#^/(site|engine)/#', $path) && !str_starts_with($path, '/engine/assets/')) {
    http_response_code(403);
    header('Content-Type: text/plain; charset=utf-8');
    echo "403\n";
    return true;
}

$file = $root . $path;
if ($path !== '/' && !str_ends_with($path, '/') && is_file($file)) {
    return false; // let the built-in server serve the static file
}

require __DIR__ . '/bootstrap.php';
return true;
