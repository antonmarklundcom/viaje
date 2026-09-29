<?php
declare(strict_types=1);

/**
 * tools/indexnow.php — tell Bing (and every IndexNow engine) which URLs changed.
 *
 *   php tools/indexnow.php --since=2d            URLs whose content file changed in the last 2 days
 *   php tools/indexnow.php --all                 every published URL (first launch, big redesign)
 *   php tools/indexnow.php /blog/ /nosotros/     just these paths (or full URLs on this site)
 *   php tools/indexnow.php --since=1d --dry-run  print the payload, send nothing
 *
 * Options: --domain=viaje.com.py  --site-dir=DIR  --endpoint=URL  --skip-key-check
 * --since accepts Nd, Nh or YYYY-MM-DD and looks at each content file's modification time,
 * so it also catches pages published from /admin/ on the server.
 *
 * The key lives in config.php (`indexnow.key`) and the engine serves it at /<key>.txt;
 * the script checks that file is live before it submits anything (a 403 from the API
 * almost always means the key file is not reachable yet).
 */

$repo   = dirname(__DIR__);
$args   = array_slice($argv, 1);
$opts   = ['dry' => false, 'all' => false, 'since' => null, 'domain' => 'viaje.com.py', 'dir' => null,
           'endpoint' => 'https://api.indexnow.org/indexnow', 'keycheck' => true];
$explicit = [];
foreach ($args as $a) {
    if ($a === '--dry-run') {
        $opts['dry'] = true;
    } elseif ($a === '--all') {
        $opts['all'] = true;
    } elseif ($a === '--skip-key-check') {
        $opts['keycheck'] = false;
    } elseif (str_starts_with($a, '--since=')) {
        $opts['since'] = substr($a, 8);
    } elseif (str_starts_with($a, '--domain=')) {
        $opts['domain'] = substr($a, 9);
    } elseif (str_starts_with($a, '--site-dir=')) {
        $opts['dir'] = rtrim(substr($a, 11), '/');
    } elseif (str_starts_with($a, '--endpoint=')) {
        $opts['endpoint'] = substr($a, 11);
    } elseif (str_starts_with($a, '--')) {
        fwrite(STDERR, "Unknown option $a\n");
        exit(2);
    } else {
        $explicit[] = $a;
    }
}
if (!$opts['all'] && $opts['since'] === null && $explicit === []) {
    fwrite(STDERR, "Nothing to submit: pass --since=2d, --all, or one or more paths/URLs.\n");
    exit(2);
}

$siteDir = $opts['dir'] ?? $repo . '/sites/' . $opts['domain'];
if (!is_dir($siteDir)) {
    $siteDir = $repo . '/site';   // built dist/ layout
}
if (!is_dir($siteDir)) {
    fwrite(STDERR, "No site directory found (tried sites/{$opts['domain']} and site/).\n");
    exit(2);
}
define('VJ_NO_DISPATCH', true);
define('VJ_SITE', $siteDir);
require $repo . '/engine/bootstrap.php';

$base = (string)Config::v('base_url');
$host = (string)parse_url($base, PHP_URL_HOST);
$key  = (string)Config::v('indexnow.key', '');
if ($key === '' || !preg_match('/^[A-Za-z0-9-]{16,128}$/', $key)) {
    fwrite(STDERR, "config.php has no valid indexnow.key (8–128 chars of a-z, A-Z, 0-9, -; at least 16 here).\n");
    exit(1);
}
$keyLocation = $base . '/' . $key . '.txt';

/* ------------------------------------------------------------ which URLs */
$urls = [];
if ($explicit !== []) {
    foreach ($explicit as $u) {
        $u = str_starts_with($u, '/') ? $base . $u : $u;
        if ((string)parse_url($u, PHP_URL_HOST) !== $host) {
            fwrite(STDERR, "Skipping $u — not on $host\n");
            continue;
        }
        $urls[$u] = true;
    }
} else {
    if (!is_dir($siteDir . '/content')) {
        fwrite(STDERR, "No content/ in $siteDir — nothing published to submit (seed it: php tools/seed-content.php).\n");
        exit(1);
    }
    $cutoff = $opts['all'] ? 0 : sinceToTimestamp((string)$opts['since']);
    $types  = [];
    foreach (Content::published() as $meta) {
        $mtime = (int)@filemtime((string)$meta['file']);
        if ($mtime >= $cutoff) {
            $urls[$base . $meta['path']] = true;
            $types[$meta['type']] = true;
        }
    }
    // A hub changes when something in it does.
    foreach ((array)Config::v('hubs', []) as $path => $hub) {
        if (isset($types[(string)($hub['type'] ?? '')])) {
            $urls[$base . $path] = true;
        }
    }
}
$list = array_keys($urls);
if ($list === []) {
    echo "No changed URLs — nothing to submit.\n";
    exit(0);
}

$payloadBase = ['host' => $host, 'key' => $key, 'keyLocation' => $keyLocation];
printf("%d URL(s) for %s via %s\n", count($list), $host, $opts['endpoint']);
foreach ($list as $u) {
    echo "  $u\n";
}
if ($opts['dry']) {
    echo "\nDry run — payload:\n" . json_encode($payloadBase + ['urlList' => $list], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
    exit(0);
}

/* ------------------------------------------------------ key file, then send */
if ($opts['keycheck']) {
    $live = trim((string)@file_get_contents($keyLocation, false, stream_context_create(['http' => ['timeout' => 15]])));
    if ($live !== $key) {
        fwrite(STDERR, "The key file $keyLocation is not live (got " . ($live === '' ? 'nothing' : 'a different body') . ").\n"
            . "Deploy first, or use --skip-key-check if you know better.\n");
        exit(1);
    }
}
$failed = false;
foreach (array_chunk($list, 10000) as $chunk) {
    [$status, $body] = postJson($opts['endpoint'], $payloadBase + ['urlList' => $chunk]);
    // 200 = received, 202 = received and key validation pending; both are success.
    $ok = $status === 200 || $status === 202;
    printf("%s  HTTP %d  (%d URLs)%s\n", $ok ? 'OK  ' : 'FAIL', $status, count($chunk), $body !== '' && !$ok ? '  ' . substr($body, 0, 200) : '');
    $failed = $failed || !$ok;
}
exit($failed ? 1 : 0);

/* ================================================================= helpers */

function sinceToTimestamp(string $since): int
{
    if (preg_match('/^(\d+)([dh])$/', $since, $m)) {
        return time() - (int)$m[1] * ($m[2] === 'd' ? 86400 : 3600);
    }
    $t = strtotime($since);
    if ($t === false) {
        fwrite(STDERR, "Cannot read --since=$since (use 2d, 12h or 2026-09-29).\n");
        exit(2);
    }
    return $t;
}

/** @return array{0:int,1:string} */
function postJson(string $url, array $payload): array
{
    $json = (string)json_encode($payload, JSON_UNESCAPED_SLASHES);
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $json,
            CURLOPT_HTTPHEADER     => ['Content-Type: application/json; charset=utf-8'],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 30,
        ]);
        $body   = (string)curl_exec($ch);
        $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        return [$status, $body];
    }
    $ctx  = stream_context_create(['http' => [
        'method' => 'POST', 'header' => "Content-Type: application/json; charset=utf-8\r\n",
        'content' => $json, 'timeout' => 30, 'ignore_errors' => true,
    ]]);
    $body = (string)@file_get_contents($url, false, $ctx);
    $status = 0;
    foreach ($http_response_header ?? [] as $h) {
        if (preg_match('#^HTTP/\S+\s+(\d{3})#', $h, $m)) {
            $status = (int)$m[1];
        }
    }
    return [$status, $body];
}
