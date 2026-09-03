<?php
declare(strict_types=1);

/**
 * Router script for PHP's built-in server: emulates the .htaccess rules
 * (serve real files, deny the private trees, everything else to index.php).
 *
 *   php -S 127.0.0.1:8080 -t dist/<domain> dist/<domain>/engine/dev-router.php
 */

$root = $_SERVER['DOCUMENT_ROOT'] ?? __DIR__ . '/..';
$path = parse_url((string)($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH) ?: '/';
$path = rawurldecode($path);

// Never serve the private trees, even though they live under the document root.
$denied = ['#^/site/content/#', '#^/site/data/#', '#^/site/cache/#', '#^/site/config#',
           '#^/engine/(?!assets/)#', '#\.md$#i', '#^/site/.*\.json$#i'];
foreach ($denied as $re) {
    if (preg_match($re, $path)) {
        http_response_code(403);
        header('Content-Type: text/plain; charset=utf-8');
        echo "403 Forbidden\n";
        return true;
    }
}

$file = $root . $path;
if ($path !== '/' && !str_ends_with($path, '/') && is_file($file)) {
    return false; // let the built-in server serve it with its own MIME handling
}

require $root . '/index.php';
return true;
