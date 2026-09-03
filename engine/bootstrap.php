<?php
declare(strict_types=1);

/**
 * Front-controller bootstrap. The document root's index.php is one line:
 *   <?php require __DIR__ . '/engine/bootstrap.php';
 *
 * CLI tools may predefine VJ_ROOT / VJ_SITE and VJ_NO_DISPATCH before requiring this.
 */

defined('VJ_ENGINE') || define('VJ_ENGINE', __DIR__);
defined('VJ_ROOT')   || define('VJ_ROOT', dirname(__DIR__));
defined('VJ_SITE')   || define('VJ_SITE', VJ_ROOT . '/site');
mb_internal_encoding('UTF-8');
date_default_timezone_set('UTC');   // replaced by the site's timezone once config is loaded

// Deprecations come from the vendored Parsedown on PHP 8.4; they are not actionable here.
error_reporting(E_ALL & ~E_DEPRECATED);
ini_set('display_errors', '0');
ini_set('log_errors', '1');

foreach (['util', 'config', 'i18n', 'frontmatter', 'types', 'markdown', 'images',
          'content', 'seo', 'render', 'leads', 'admin', 'router'] as $lib) {
    require_once VJ_ENGINE . '/lib/' . $lib . '.php';
}

try {
    $cfg = Config::load(VJ_SITE);
} catch (Throwable $e) {
    http_response_code(500);
    header('Content-Type: text/plain; charset=utf-8');
    echo "Configuration error\n\n" . $e->getMessage() . "\n";
    exit(1);
}

defined('VJ_TZ') || define('VJ_TZ', (string)($cfg['timezone'] ?? 'America/Asuncion'));
date_default_timezone_set(VJ_TZ);

if ($cfg['debug']) {
    ini_set('display_errors', '1');
    error_reporting(E_ALL & ~E_DEPRECATED);
}
I18n::load((string)$cfg['lang'], (array)($cfg['labels'] ?? []));

if (defined('VJ_NO_DISPATCH') && VJ_NO_DISPATCH) {
    return;
}

$method = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));
$uri    = (string)($_SERVER['REQUEST_URI'] ?? '/');
$path   = (string)(parse_url($uri, PHP_URL_PATH) ?: '/');
$query  = $_GET;

// Sessions exist only where they are needed (spec §1 item 2).
if (str_starts_with($path, '/admin') || str_starts_with($path, '/preview/') || rtrim($path, '/') === '/enviar') {
    Render::disableCache();
}

try {
    $response = Router::dispatch($method, $path, $query, $_POST);
} catch (Throwable $e) {
    Util::log('Unhandled: ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
    if ($cfg['debug']) {
        http_response_code(500);
        header('Content-Type: text/plain; charset=utf-8');
        echo (string)$e;
        exit(1);
    }
    $response = new Response(500, "500\n", ['Content-Type' => 'text/plain; charset=utf-8']);
}

if ($method === 'HEAD') {
    $response->body = '';
}
$response->emit();
