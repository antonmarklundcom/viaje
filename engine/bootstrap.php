<?php
declare(strict_types=1);

/**
 * Front controller. The document root's index.php is nothing but:
 *     <?php require __DIR__ . '/engine/bootstrap.php';
 */

define('VJ_ENGINE', __DIR__);
define('VJ_ROOT', dirname(__DIR__));
define('VJ_SITE', VJ_ROOT . '/site');

require_once VJ_ENGINE . '/lib/util.php';
require_once VJ_ENGINE . '/lib/config.php';
require_once VJ_ENGINE . '/lib/i18n.php';
require_once VJ_ENGINE . '/lib/frontmatter.php';
require_once VJ_ENGINE . '/lib/types.php';
require_once VJ_ENGINE . '/lib/images.php';
require_once VJ_ENGINE . '/lib/markdown.php';
require_once VJ_ENGINE . '/lib/content.php';
require_once VJ_ENGINE . '/lib/seo.php';
require_once VJ_ENGINE . '/lib/render.php';
require_once VJ_ENGINE . '/lib/leads.php';
require_once VJ_ENGINE . '/lib/admin.php';
require_once VJ_ENGINE . '/lib/router.php';

date_default_timezone_set('America/Asuncion');
mb_internal_encoding('UTF-8');

try {
    $site = Config::load(VJ_SITE);
} catch (Throwable $ex) {
    http_response_code(500);
    header('Content-Type: text/plain; charset=utf-8');
    error_log('[engine] boot failure: ' . $ex->getMessage());
    echo "The site is not configured correctly.\n";
    exit;
}

// Errors go to the log in production; only debug mode puts them on screen.
if (!empty($site['debug'])) {
    error_reporting(E_ALL);
    ini_set('display_errors', '1');
} else {
    error_reporting(E_ALL & ~E_DEPRECATED);
    ini_set('display_errors', '0');
}
ini_set('log_errors', '1');

I18n::load((string)$site['lang'], (array)($site['labels'] ?? []));

$method  = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));
$uri     = (string)($_SERVER['REQUEST_URI'] ?? '/');
$rawPath = explode('?', $uri, 2)[0];
$path    = Util::normalisePath($rawPath);

// Sessions exist only where they are needed (spec §1.2).
$needsSession = str_starts_with($path, '/admin')
    || $path === '/enviar/'
    || str_starts_with($path, '/preview/');
if ($needsSession && session_status() === PHP_SESSION_NONE) {
    $https = (!empty($_SERVER['HTTPS']) && strtolower((string)$_SERVER['HTTPS']) !== 'off')
        || strtolower((string)($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https';
    session_name(Admin::SESSION_NAME);
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'httponly' => true,
        'secure'   => $https,
        'samesite' => 'Strict',
    ]);
    session_start();
}

Render::share([
    'site' => $site,
    't'    => static fn(string $k, array $v = []): string => I18n::t($k, $v),
]);

$cacheable = Render::cacheable($method, $path) && $_GET === [];

if ($cacheable) {
    $hit = Render::cacheGet($path);
    if ($hit !== null) {
        (Response::html($hit, 200, [
            'X-Cache'        => 'HIT',
            'Cache-Control'  => 'public, max-age=300',
        ]))->emit();
        exit;
    }
}

try {
    $response = Router::dispatch($method, $rawPath, $_GET, $_SERVER);
} catch (Throwable $ex) {
    error_log('[engine] ' . $ex::class . ': ' . $ex->getMessage() . ' @ ' . $ex->getFile() . ':' . $ex->getLine());
    if (!empty($site['debug'])) {
        throw $ex;
    }
    $response = new Response(500, "Something went wrong.\n", ['Content-Type' => 'text/plain; charset=utf-8']);
}

// Baseline security and caching headers.
$response->headers += [
    'X-Content-Type-Options' => 'nosniff',
    'Referrer-Policy'        => 'strict-origin-when-cross-origin',
];
if (!empty($site['staging'])) {
    $response->headers['X-Robots-Tag'] = 'noindex, nofollow';
}
if ($response->status === 200 && !isset($response->headers['Cache-Control'])) {
    $response->headers['Cache-Control'] = str_starts_with($path, '/admin')
        ? 'no-store'
        : 'public, max-age=300';
}

if ($cacheable && $response->status === 200 && ($response->headers['Content-Type'] ?? '') === 'text/html; charset=utf-8') {
    Render::cachePut($path, $response->body);
    $response->headers['X-Cache'] = 'MISS';
}

if ($method === 'HEAD') {
    $response->body = '';
}

$response->emit();
