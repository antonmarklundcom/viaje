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
          'content', 'seo', 'render', 'leads', 'history', 'admin', 'router'] as $lib) {
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

// First request on a fresh install: the server owns content/, git only ships content-seed/.
// Copy the seed once (marker file in data/ means "already done, never again", so pages the
// owner later deletes in /admin/ do not come back). Same result as tools/seed-content.php.
vj_auto_seed(VJ_SITE);

function vj_auto_seed(string $site): void
{
    $seed = $site . '/content-seed';
    $dest = $site . '/content';
    $mark = $site . '/data/.content-seeded';
    if (!is_dir($seed) || is_file($mark)) {
        return;
    }
    $has = static function (string $dir): bool {
        if (!is_dir($dir)) {
            return false;
        }
        $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS));
        foreach ($it as $f) {
            if ($f->isFile() && !str_starts_with($f->getFilename(), '.')) {
                return true;
            }
        }
        return false;
    };
    if ($has($dest)) {
        @mkdir($site . '/data', 0775, true);
        @file_put_contents($mark, date('c') . " existing content\n");
        return;
    }
    @mkdir($site . '/data', 0775, true);
    $lock = $site . '/data/.seed.lock';
    if (!@mkdir($lock, 0775)) {
        // Another request is seeding; ignore a lock older than a minute (crashed request).
        if (@filemtime($lock) < time() - 60) {
            @rmdir($lock);
        }
        return;
    }
    $n = 0;
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($seed, FilesystemIterator::SKIP_DOTS));
    foreach ($it as $f) {
        if (!$f->isFile() || str_starts_with($f->getFilename(), '.')) {
            continue;
        }
        $to = $dest . '/' . substr($f->getPathname(), strlen($seed) + 1);
        if (!is_dir(dirname($to))) {
            @mkdir(dirname($to), 0775, true);
        }
        if (@copy($f->getPathname(), $to)) {
            $n++;
        }
    }
    if ($n > 0) {
        foreach ([$site . '/cache/pages', $site . '/cache/index.php'] as $stale) {
            if (is_dir($stale)) {
                $r = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($stale, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
                foreach ($r as $x) {
                    $x->isDir() ? @rmdir($x->getPathname()) : @unlink($x->getPathname());
                }
                @rmdir($stale);
            } else {
                @unlink($stale);
            }
        }
        @file_put_contents($mark, date('c') . " $n files (auto)\n");
    }
    @rmdir($lock);
}

$method = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));
$uri    = (string)($_SERVER['REQUEST_URI'] ?? '/');
$path   = (string)(parse_url($uri, PHP_URL_PATH) ?: '/');
$query  = $_GET;

// Sessions exist only where they are needed (spec §1 item 2).
if (str_starts_with($path, '/admin') || str_starts_with($path, '/preview/') || in_array(rtrim($path, '/'), ['/enviar', '/enviar/sello', '/suscribir', '/suscribir/confirmar'], true)) {
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
