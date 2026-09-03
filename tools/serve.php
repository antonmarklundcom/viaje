<?php
declare(strict_types=1);

/**
 * tools/serve.php <domain> [port]
 * Builds dist/<domain> fresh and serves it with PHP's built-in server.
 */

$repo   = dirname(__DIR__);
$domain = $argv[1] ?? '';
$port   = (int)($argv[2] ?? 8080);
if ($domain === '') {
    fwrite(STDERR, "Usage: php tools/serve.php <domain> [port]\n");
    exit(2);
}
passthru(PHP_BINARY . ' ' . escapeshellarg($repo . '/tools/build.php') . ' ' . escapeshellarg($domain) . ' --fresh', $rc);
if ($rc !== 0) {
    exit($rc);
}
$dist = $repo . '/dist/' . $domain;
echo "http://127.0.0.1:$port  (admin at /admin/)\n";
passthru(sprintf(
    '%s -S 127.0.0.1:%d -t %s %s',
    escapeshellarg(PHP_BINARY),
    $port,
    escapeshellarg($dist),
    escapeshellarg($dist . '/engine/dev-router.php')
));
