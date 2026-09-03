<?php
declare(strict_types=1);

/**
 * tools/verify.php <domain> [--base=http://127.0.0.1:8080] [--keep]
 *
 * Checks the URL contract in sites/<domain>/urls.txt plus the on-page SEO
 * invariants of spec §12. Exit 0 = everything passed.
 */

$repo   = dirname(__DIR__);
$domain = $argv[1] ?? '';
$base   = null;
$keep   = false;
foreach (array_slice($argv, 2) as $arg) {
    if (str_starts_with($arg, '--base=')) {
        $base = rtrim(substr($arg, 7), '/');
    } elseif ($arg === '--keep') {
        $keep = true;
    }
}
if ($domain === '' || str_starts_with($domain, '--')) {
    fwrite(STDERR, "Usage: php tools/verify.php <domain> [--base=URL]\n");
    exit(2);
}
$siteDir = $repo . '/sites/' . $domain;
if (!is_dir($siteDir)) {
    fwrite(STDERR, "No such site: sites/$domain\n");
    exit(2);
}

$failures = [];
$checks   = 0;
$server   = null;
$distDir  = $repo . '/dist/' . $domain;

/* ------------------------------------------------------------ local server */
if ($base === null) {
    passthru(PHP_BINARY . ' ' . escapeshellarg($repo . '/tools/build.php') . ' ' . escapeshellarg($domain) . ' --fresh', $rc);
    if ($rc !== 0) {
        fwrite(STDERR, "build failed\n");
        exit(1);
    }
    $port = freePort();
    $base = 'http://127.0.0.1:' . $port;
    $cmd  = sprintf(
        '%s -S 127.0.0.1:%d -t %s %s',
        escapeshellarg(PHP_BINARY),
        $port,
        escapeshellarg($distDir),
        escapeshellarg($distDir . '/engine/dev-router.php')
    );
    $server = proc_open($cmd, [1 => ['file', '/dev/null', 'a'], 2 => ['file', '/dev/null', 'a']], $pipes);
    register_shutdown_function(static function () use (&$server, $keep): void {
        if (is_resource($server) && !$keep) {
            $status = proc_get_status($server);
            if ($status['running'] ?? false) {
                @exec('kill ' . (int)$status['pid'] . ' 2>/dev/null');
            }
            proc_close($server);
        }
    });
    if (!waitFor($base, 60)) {
        fwrite(STDERR, "The built-in server did not come up at $base\n");
        exit(1);
    }
}

$cfg     = require $siteDir . '/config.php';
$baseUrl = rtrim((string)$cfg['base_url'], '/');

/* ------------------------------------------------------ 1. the URL contract */
$rows = parseUrls($siteDir . '/urls.txt');
if ($rows === []) {
    fail($failures, 'urls.txt', 'no rows found');
}
$expect200 = [];

foreach ($rows as $row) {
    [$path, $status, $target] = $row;
    $res = request($base . $path);
    $checks++;
    if ($res['status'] !== $status) {
        fail($failures, $path, "expected HTTP $status, got {$res['status']}");
        continue;
    }
    if ($target !== null) {
        $loc  = $res['headers']['location'] ?? '';
        $locP = (string)(parse_url($loc, PHP_URL_PATH) ?: $loc);
        if ($locP !== $target && $loc !== $target && $loc !== $baseUrl . $target) {
            fail($failures, $path, "expected Location $target, got " . ($loc === '' ? '(none)' : $loc));
        }
        continue;
    }
    if ($status === 200 && str_contains((string)($res['headers']['content-type'] ?? ''), 'text/html')) {
        $expect200[] = $path;
        checkHtml($failures, $path, $res['body'], $baseUrl, $checks);
    }
}

/* --------------------------------------------------------------- 2. sitemap */
$sitemapUrl = $base . '/sitemap.xml';
$sm = request($sitemapUrl);
$checks++;
$locs = [];
if ($sm['status'] !== 200) {
    fail($failures, '/sitemap.xml', "expected 200, got {$sm['status']}");
} else {
    $xml = @simplexml_load_string($sm['body']);
    if ($xml === false) {
        fail($failures, '/sitemap.xml', 'is not valid XML');
    } else {
        foreach ($xml->url as $u) {
            $locs[] = (string)$u->loc;
        }
        if ($locs === []) {
            fail($failures, '/sitemap.xml', 'contains no <loc> entries');
        }
        foreach (array_slice($locs, 0, 200) as $loc) {
            $local = str_replace($baseUrl, $base, $loc);
            $r     = request($local);
            $checks++;
            if ($r['status'] !== 200) {
                fail($failures, $loc, "listed in the sitemap but returns {$r['status']}");
            }
        }
    }
}
foreach ($expect200 as $path) {
    if ($path === '/feed/' || str_ends_with($path, '.xml') || $path === '/robots.txt') {
        continue;
    }
    if (isNoindex($siteDir, $path)) {
        continue;
    }
    if (!in_array($baseUrl . $path, $locs, true)) {
        fail($failures, $path, 'returns 200 but is missing from sitemap.xml');
    }
}

/* ------------------------------------------------------------------ 3. feed */
$feed = request($base . '/feed/');
$checks++;
if ($feed['status'] !== 200) {
    fail($failures, '/feed/', "expected 200, got {$feed['status']}");
} elseif (@simplexml_load_string($feed['body']) === false) {
    fail($failures, '/feed/', 'is not valid XML');
}

/* -------------------------------------------------------- 4. content scan */
foreach (contentIssues($siteDir, $cfg) as $issue) {
    $checks++;
    fail($failures, $issue[0], $issue[1]);
}

/* --------------------------------------------------------------- 5. report */
printf("\n%s — %d checks, %d failure(s)\n", $domain, $checks, count($failures));
foreach ($failures as $f) {
    printf("  FAIL  %-46s %s\n", $f[0], $f[1]);
}
if ($failures === []) {
    echo "  OK    every row of urls.txt, the sitemap, the feed and the content scan passed\n";
}
exit($failures === [] ? 0 : 1);

/* ================================================================= helpers */

function fail(array &$failures, string $where, string $why): void
{
    $failures[] = [$where, $why];
}

/** @return list<array{0:string,1:int,2:?string}> */
function parseUrls(string $file): array
{
    if (!is_file($file)) {
        return [];
    }
    $rows = [];
    foreach (file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#')) {
            continue;
        }
        $parts = preg_split('/\s+/', $line) ?: [];
        if (count($parts) < 2) {
            continue;
        }
        $rows[] = [$parts[0], (int)$parts[1], $parts[2] ?? null];
    }
    return $rows;
}

/** @return array{status:int,headers:array<string,string>,body:string} */
function request(string $url): array
{
    $ctx = stream_context_create(['http' => [
        'method'          => 'GET',
        'follow_location' => 0,
        'ignore_errors'   => true,
        'timeout'         => 20,
        'header'          => "User-Agent: viaje-verify/1.0\r\nAccept: text/html,application/xhtml+xml,*/*\r\n",
    ]]);
    $body    = @file_get_contents($url, false, $ctx);
    $status  = 0;
    $headers = [];
    foreach ($http_response_header ?? [] as $h) {
        if (preg_match('#^HTTP/\S+\s+(\d{3})#', $h, $m)) {
            $status = (int)$m[1];
            continue;
        }
        $p = explode(':', $h, 2);
        if (count($p) === 2) {
            $headers[strtolower(trim($p[0]))] = trim($p[1]);
        }
    }
    return ['status' => $status, 'headers' => $headers, 'body' => (string)$body];
}

function freePort(): int
{
    $sock = @stream_socket_server('tcp://127.0.0.1:0', $errno, $errstr);
    if ($sock === false) {
        return 8080;
    }
    $name = stream_socket_get_name($sock, false);
    fclose($sock);
    return (int)substr((string)$name, (int)strrpos((string)$name, ':') + 1);
}

function waitFor(string $base, int $tries): bool
{
    for ($i = 0; $i < $tries; $i++) {
        $fp = @fsockopen(
            (string)parse_url($base, PHP_URL_HOST),
            (int)parse_url($base, PHP_URL_PORT),
            $errno,
            $errstr,
            0.5
        );
        if ($fp) {
            fclose($fp);
            return true;
        }
        usleep(200000);
    }
    return false;
}

/** The on-page invariants every 200 HTML response must satisfy. */
function checkHtml(array &$failures, string $path, string $html, string $baseUrl, int &$checks): void
{
    $checks += 6;

    $n = preg_match_all('#<title>(.*?)</title>#si', $html, $m);
    if ($n !== 1) {
        fail($failures, $path, "expected exactly one <title>, found $n");
    } elseif (trim(strip_tags($m[1][0])) === '') {
        fail($failures, $path, '<title> is empty');
    }

    $n = preg_match_all('#<meta[^>]+name="description"[^>]*>#i', $html, $m);
    if ($n !== 1) {
        fail($failures, $path, "expected exactly one meta description, found $n");
    } elseif (!preg_match('#content="([^"]*)"#i', $m[0][0], $c) || trim($c[1]) === '') {
        fail($failures, $path, 'meta description is empty');
    }

    $n = preg_match_all('#<link[^>]+rel="canonical"[^>]*>#i', $html, $m);
    if ($n !== 1) {
        fail($failures, $path, "expected exactly one canonical, found $n");
    } elseif (preg_match('#href="([^"]*)"#i', $m[0][0], $c)) {
        $want = $baseUrl . $path;
        if (html_entity_decode($c[1], ENT_QUOTES, 'UTF-8') !== $want) {
            fail($failures, $path, "canonical is {$c[1]}, expected $want");
        }
    }

    $n = preg_match_all('#<h1\b#i', $html);
    if ($n !== 1) {
        fail($failures, $path, "expected exactly one <h1>, found $n");
    }

    if (preg_match_all('#<img\b[^>]*>#i', $html, $imgs)) {
        foreach ($imgs[0] as $img) {
            if (!preg_match('#\balt=#i', $img)) {
                fail($failures, $path, 'an <img> has no alt attribute: ' . substr($img, 0, 90));
                break;
            }
        }
    }

    if (preg_match_all('#<script[^>]+application/ld\+json[^>]*>(.*?)</script>#si', $html, $ld)) {
        $foundOrg = false;
        foreach ($ld[1] as $block) {
            $data = json_decode(str_replace('<\/', '</', $block), true);
            if (!is_array($data)) {
                fail($failures, $path, 'a JSON-LD block does not parse');
                continue;
            }
            foreach ((array)($data['@graph'] ?? [$data]) as $node) {
                $id = (string)($node['@id'] ?? '');
                if (str_ends_with($id, '#org')) {
                    $foundOrg = true;
                }
            }
        }
        if (!$foundOrg) {
            fail($failures, $path, 'JSON-LD has no Organization node');
        }
    } else {
        fail($failures, $path, 'no JSON-LD block');
    }

    foreach (['TourDen', 'Harbert', 'Lorem', 'No content is added yet', '0+'] as $banned) {
        if (str_contains($html, $banned)) {
            fail($failures, $path, "contains the banned string \"$banned\"");
        }
    }
}

/** True when the content file behind $path is marked noindex. */
function isNoindex(string $siteDir, string $path): bool
{
    static $map = null;
    if ($map === null) {
        $map = [];
        foreach (glob($siteDir . '/content/*/*.md') ?: [] as $file) {
            $raw = (string)file_get_contents($file);
            if (!preg_match('/^---\R(.*?)\R---/s', $raw, $m)) {
                continue;
            }
            $fm   = $m[1];
            $slug = basename($file, '.md');
            $p    = preg_match('/^path:\s*(\S+)/m', $fm, $pm) ? '/' . trim($pm[1], '"\'/') . '/' : null;
            if ($p === '//') {
                $p = '/';
            }
            $key = $p ?? $slug;
            $map[$key] = (bool)preg_match('/^noindex:\s*true/m', $fm) || (bool)preg_match('/^draft:\s*true/m', $fm);
        }
    }
    return $map[$path] ?? false;
}

/** @return list<array{0:string,1:string}> */
function contentIssues(string $siteDir, array $cfg): array
{
    $issues = [];
    $paths  = [];
    $known  = ['data'];
    $folders = ['page' => 'pages', 'service' => 'services', 'post' => 'posts', 'news' => 'news', 'trip' => 'trips', 'activity' => 'activities'];
    foreach ((array)($cfg['types'] ?? []) as $type) {
        if (isset($folders[$type])) {
            $known[] = $folders[$type];
        }
    }
    foreach (glob($siteDir . '/content/*', GLOB_ONLYDIR) ?: [] as $dir) {
        if (!in_array(basename($dir), $known, true)) {
            $issues[] = ['content/' . basename($dir), 'unknown content folder for the enabled types'];
        }
    }
    foreach ($known as $folder) {
        if ($folder === 'data') {
            continue;
        }
        $typePath = array_search($folder, $folders, true);
        foreach (glob($siteDir . '/content/' . $folder . '/*.md') ?: [] as $file) {
            $rel  = 'content/' . $folder . '/' . basename($file);
            $raw  = (string)file_get_contents($file);
            if (!preg_match('/^---\R(.*?)\R---/s', $raw, $m)) {
                $issues[] = [$rel, 'has no front matter'];
                continue;
            }
            $fm    = $m[1];
            $slug  = basename($file, '.md');
            $draft = (bool)preg_match('/^draft:\s*true/m', $fm);
            $path  = preg_match('/^path:\s*(\S+)/m', $fm, $pm)
                ? rtrim('/' . trim($pm[1], "\"'/"), '/') . '/'
                : rtrim((string)($cfg['type_paths'][$typePath] ?? '/'), '/') . '/' . $slug . '/';
            if ($typePath === 'page' && $slug === 'home' && !preg_match('/^path:/m', $fm)) {
                $path = '/';
            }
            if (isset($paths[$path])) {
                $issues[] = [$rel, 'duplicate path ' . $path . ' (also ' . $paths[$path] . ')'];
            }
            $paths[$path] = $rel;
            if ($draft) {
                continue;
            }
            if (!preg_match('/^description:\s*\S/m', $fm)) {
                $issues[] = [$rel, 'published without a description'];
            }
            if (preg_match('/^hero:\s*\S/m', $fm) && !preg_match('/^hero_alt:\s*\S/m', $fm)) {
                $issues[] = [$rel, 'has a hero without hero_alt'];
            }
            if (!preg_match('/^title:\s*\S/m', $fm)) {
                $issues[] = [$rel, 'published without a title'];
            }
        }
    }
    return $issues;
}
