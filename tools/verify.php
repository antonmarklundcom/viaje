<?php
declare(strict_types=1);

/**
 * tools/verify.php <domain> [--base=http://127.0.0.1:8080] [--keep] [--strict]
 *
 * Checks the URL contract in sites/<domain>/urls.txt plus the on-page SEO
 * invariants of spec §12. Exit 0 = everything passed.
 *
 * Failures break the build. Warnings (title/description length, missing or absent
 * focus keyword, thin alt text, links only from the site chrome) are printed and do not,
 * unless --strict turns them into failures (CI does that for viaje.com.py).
 * Sites opt in to the guide-page checks (quick answer, FAQ, related links, lead form)
 * with `'verify' => ['guides' => true, 'keywords' => true]` in config.php.
 */

$repo   = dirname(__DIR__);
$domain = $argv[1] ?? '';
$base   = null;
$keep   = false;
$strict = false;
foreach (array_slice($argv, 2) as $arg) {
    if (str_starts_with($arg, '--base=')) {
        $base = rtrim(substr($arg, 7), '/');
    } elseif ($arg === '--keep') {
        $keep = true;
    } elseif ($arg === '--strict') {
        $strict = true;
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

require_once $repo . '/engine/lib/frontmatter.php';

$failures = [];
$warnings = [];
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
    // mail() goes to a file instead of a real MTA, so the form checks can read what was "sent".
    $cmd  = sprintf(
        '%s -d %s -S 127.0.0.1:%d -t %s %s',
        escapeshellarg(PHP_BINARY),
        escapeshellarg('sendmail_path=cat >> ' . escapeshellarg($distDir . '/site/data/outbox.log')),
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
$opt     = (array)($cfg['verify'] ?? []);          // per-site opt-ins, see the header comment
$pages   = pageIndex($siteDir, $cfg);              // path => front matter, type, flags
$htmlByPath = [];

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
        $htmlByPath[$path] = $res['body'];
        checkHtml($failures, $warnings, $path, $res['body'], $baseUrl, $checks, $pages[$path] ?? null, $cfg, $opt);
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
            $checks++;
            $lm = (string)$u->lastmod;
            $lp = (string)parse_url((string)$u->loc, PHP_URL_PATH);
            if (!preg_match('/^\d{4}-\d{2}-\d{2}/', $lm)) {
                fail($failures, $lp, 'sitemap <lastmod> is missing or not a W3C date');
            } elseif (($pages[$lp]['fm']['updated'] ?? '') !== '' && substr($lm, 0, 10) !== substr((string)$pages[$lp]['fm']['updated'], 0, 10)) {
                fail($failures, $lp, "sitemap lastmod $lm does not follow the page's `updated:` " . $pages[$lp]['fm']['updated']);
            }
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
            } elseif (!isset($htmlByPath[(string)parse_url($loc, PHP_URL_PATH)])
                && str_contains((string)($r['headers']['content-type'] ?? ''), 'text/html')) {
                // A page the sitemap promises but urls.txt never mentioned still gets the full on-page audit.
                $sp = (string)parse_url($loc, PHP_URL_PATH);
                $htmlByPath[$sp] = $r['body'];
                checkHtml($failures, $warnings, $sp, $r['body'], $baseUrl, $checks, $pages[$sp] ?? null, $cfg, $opt);
            }
        }
    }
}
foreach ($expect200 as $path) {
    if ($path === '/feed/' || str_ends_with($path, '.xml') || $path === '/robots.txt') {
        continue;
    }
    if (isNoindex($siteDir, $path, $cfg)) {
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

/* ------------------------------------ 5. site-wide: keywords, orphans, IndexNow */
$checks += siteChecks($failures, $warnings, $pages, $htmlByPath, $cfg, $opt, $base, $baseUrl, $repo, $domain, $locs);

/* -------------------------------- 6. forms: leads and newsletter, end to end */
if ($server !== null && ($opt['guides'] ?? false)) {
    $checks += formChecks($failures, $base, $htmlByPath, $distDir);
}

/* --------------------------------------------------------------- 7. report */
if ($strict) {
    foreach ($warnings as $w) {
        $failures[] = [$w[0], 'warning (strict): ' . $w[1]];
    }
    $warnings = [];
}
printf("\n%s — %d checks, %d failure(s), %d warning(s)\n", $domain, $checks, count($failures), count($warnings));
foreach ($failures as $f) {
    printf("  FAIL  %-46s %s\n", $f[0], $f[1]);
}
foreach ($warnings as $w) {
    printf("  WARN  %-46s %s\n", $w[0], $w[1]);
}
if ($failures === []) {
    echo "  OK    every row of urls.txt, the sitemap, the feed, the content scan and the on-page checks passed\n";
}
exit($failures === [] ? 0 : 1);

/* ================================================================= helpers */

function fail(array &$failures, string $where, string $why): void
{
    $failures[] = [$where, $why];
}

function warn(array &$warnings, string $where, string $why): void
{
    $warnings[] = [$where, $why];
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

/** ASCII-lowercase form for phrase matching: no accents, punctuation as single spaces. */
function norm(string $s): string
{
    $s = mb_strtolower(html_entity_decode(strip_tags($s), ENT_QUOTES, 'UTF-8'), 'UTF-8');
    $s = strtr($s, ['á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ü' => 'u', 'ñ' => 'n']);
    return trim((string)preg_replace('/[^a-z0-9]+/', ' ', $s));
}

/** True when the normalised phrase occurs as whole words inside the normalised text. */
function hasPhrase(string $text, string $phrase): bool
{
    $p = norm($phrase);
    return $p !== '' && str_contains(' ' . norm($text) . ' ', ' ' . $p . ' ');
}

/**
 * Every published-or-draft content file plus the configured hubs, keyed by URL path.
 * @return array<string,array{type:string,fm:array,file:string,draft:bool,noindex:bool,layout:string}>
 */
function pageIndex(string $siteDir, array $cfg): array
{
    $folders = ['page' => 'pages', 'service' => 'services', 'post' => 'posts',
                'news' => 'news', 'trip' => 'trips', 'activity' => 'activities'];
    $out = [];
    foreach ((array)($cfg['types'] ?? []) as $type) {
        if (!isset($folders[$type])) {
            continue;
        }
        foreach (glob(contentDir($siteDir) . '/' . $folders[$type] . '/*.md') ?: [] as $file) {
            [$fm, $body] = Frontmatter::parseFile((string)file_get_contents($file));
            $slug = basename($file, '.md');
            $raw  = (string)file_get_contents($file);
            $fmRaw = preg_match('/^---\R(.*?)\R---/s', $raw, $m) ? $m[1] : '';
            $path = contentPath($fmRaw, $type, $slug, $cfg);
            $out[$path] = [
                'type' => $type, 'fm' => $fm, 'body' => $body, 'file' => $folders[$type] . '/' . $slug . '.md',
                'draft' => (bool)($fm['draft'] ?? false), 'noindex' => (bool)($fm['noindex'] ?? false),
                'layout' => (string)($fm['layout'] ?? 'default'),
            ];
        }
    }
    foreach ((array)($cfg['hubs'] ?? []) as $hubPath => $hub) {
        $out[$hubPath] ??= [
            'type' => 'hub', 'fm' => ['title' => $hub['title'] ?? '', 'keyword' => $hub['keyword'] ?? ''],
            'body' => (string)($hub['intro'] ?? ''), 'file' => 'config.php (hubs)', 'draft' => false, 'noindex' => false, 'layout' => 'hub',
        ];
    }
    return $out;
}

/** Everything the response should say about itself. Failures break the build, warnings inform it. */
function checkHtml(array &$failures, array &$warnings, string $path, string $html, string $baseUrl, int &$checks, ?array $page, array $cfg, array $opt): void
{
    $checks += 6;
    $type    = (string)($page['type'] ?? '');
    $layout  = (string)($page['layout'] ?? '');
    $guide   = ($opt['guides'] ?? false) && in_array($type, ['post', 'activity', 'trip'], true);
    $title   = '';
    $main    = preg_match('#<main\b[^>]*>(.*)</main>#si', $html, $mm) ? $mm[1] : $html;

    $n = preg_match_all('#<title>(.*?)</title>#si', $html, $m);
    if ($n !== 1) {
        fail($failures, $path, "expected exactly one <title>, found $n");
    } elseif (trim(strip_tags($m[1][0])) === '') {
        fail($failures, $path, '<title> is empty');
    } else {
        $title = trim(html_entity_decode(strip_tags($m[1][0]), ENT_QUOTES, 'UTF-8'));
    }

    $desc = '';
    $n = preg_match_all('#<meta[^>]+name="description"[^>]*>#i', $html, $m);
    if ($n !== 1) {
        fail($failures, $path, "expected exactly one meta description, found $n");
    } elseif (!preg_match('#content="([^"]*)"#i', $m[0][0], $c) || trim($c[1]) === '') {
        fail($failures, $path, 'meta description is empty');
    } else {
        $desc = html_entity_decode($c[1], ENT_QUOTES, 'UTF-8');
    }

    $n = preg_match_all('#<link[^>]+rel="canonical"[^>]*>#i', $html, $m);
    $canonical = '';
    if ($n !== 1) {
        fail($failures, $path, "expected exactly one canonical, found $n");
    } elseif (preg_match('#href="([^"]*)"#i', $m[0][0], $c)) {
        $want = $baseUrl . $path;
        $canonical = html_entity_decode($c[1], ENT_QUOTES, 'UTF-8');
        if ($canonical !== $want) {
            fail($failures, $path, "canonical is {$c[1]}, expected $want");
        }
    }

    $h1 = '';
    $n = preg_match_all('#<h1\b[^>]*>(.*?)</h1>#si', $html, $m);
    if ($n !== 1) {
        fail($failures, $path, "expected exactly one <h1>, found $n");
    } else {
        $h1 = trim(html_entity_decode(strip_tags($m[1][0]), ENT_QUOTES, 'UTF-8'));
    }

    /* ---- titles and descriptions: length limits (warnings) */
    $tl = mb_strlen($title, 'UTF-8');
    $dl = mb_strlen($desc, 'UTF-8');
    if ($title !== '' && $tl > 60) {
        warn($warnings, $path, "title is $tl chars (max 60): $title");
    }
    if ($desc !== '' && ($dl < 140 || $dl > 160) && $layout !== 'error') {
        warn($warnings, $path, "meta description is $dl chars (want 140–160)");
    }

    /* ---- focus keyword: title, H1 and first paragraph */
    if ($opt['keywords'] ?? false) {
        $kw = trim((string)($page['fm']['keyword'] ?? ''));
        if ($page !== null && !$page['noindex'] && !$page['draft']) {
            if ($kw === '') {
                warn($warnings, $path, 'no `keyword:` (one primary phrase per URL, see docs/keyword-map.md)');
            } else {
                foreach (['title' => $title, 'H1' => $h1] as $where => $text) {
                    if (!hasPhrase($text, $kw)) {
                        warn($warnings, $path, "keyword \"$kw\" is missing from the $where");
                    }
                }
                $first = '';
                foreach (['#<p class="(?:lede|hero__text)"[^>]*>(.*?)</p>#si', '#<div class="prose">.*?<p[^>]*>(.*?)</p>#si'] as $re) {
                    if (preg_match($re, $main, $fp)) {
                        $first = $fp[1];
                        break;
                    }
                }
                if (!hasPhrase($first, $kw)) {
                    warn($warnings, $path, "keyword \"$kw\" is missing from the first paragraph");
                }
            }
        }
    }

    /* ---- images: alt, size, lazy-loading, hero priority */
    if (preg_match_all('#<img\b[^>]*>#i', $html, $imgs)) {
        $seenHero = false;
        foreach ($imgs[0] as $img) {
            $checks++;
            $short = substr($img, 0, 110);
            if (!preg_match('#\balt="([^"]*)"#i', $img, $am)) {
                fail($failures, $path, 'an <img> has no alt attribute: ' . $short);
                continue;
            }
            $alt = trim(html_entity_decode($am[1], ENT_QUOTES, 'UTF-8'));
            if ($alt === '') {
                fail($failures, $path, 'an <img> has an empty alt: ' . $short);
            } elseif (!str_contains($img, 'brand__logo') && (mb_strlen($alt, 'UTF-8') < 15 || preg_match('/\.(jpe?g|png|webp)$/i', $alt))) {
                warn($warnings, $path, "alt text is not descriptive (\"$alt\")");
            }
            preg_match('#\bsrc="([^"]*)"#i', $img, $sm);
            $src = html_entity_decode($sm[1] ?? '', ENT_QUOTES, 'UTF-8');
            $isLocal = $src !== '' && !preg_match('#^https?://#i', $src) || str_starts_with($src, $baseUrl);
            if ($isLocal && (!preg_match('#\bwidth="\d+"#', $img) || !preg_match('#\bheight="\d+"#', $img))) {
                fail($failures, $path, 'a local <img> has no width/height (layout shift): ' . $short);
            }
            $isHero = (bool)preg_match('#class="[^"]*\b(?:hero__img|post__hero-img|page__hero-img)\b#', $img);
            $isLogo = str_contains($img, 'brand__logo');
            if ($isHero && !$seenHero) {
                $seenHero = true;
                if (!str_contains($img, 'fetchpriority="high"') || str_contains($img, 'loading="lazy"')) {
                    fail($failures, $path, 'the hero image must be eager with fetchpriority="high"');
                }
            } elseif (!$isLogo && !str_contains($img, 'loading="lazy"')) {
                fail($failures, $path, 'a below-the-fold <img> is not loading="lazy": ' . $short);
            }
        }
    }

    /* ---- no Google (or any tracker/embed host) in the page */
    if (preg_match('#(googleapis\.com|gstatic\.com|googletagmanager\.com|google-analytics\.com|google\.com/maps|maps\.google|youtube\.com|youtu\.be|recaptcha)#i', $html, $gm)) {
        fail($failures, $path, "references a forbidden third party: {$gm[1]}");
    }

    /* ---- JSON-LD: present, parses, is coherent */
    if (preg_match_all('#<script[^>]+application/ld\+json[^>]*>(.*?)</script>#si', $html, $ld)) {
        $graph = [];
        foreach ($ld[1] as $block) {
            $data = json_decode(str_replace('<\/', '</', $block), true);
            if (!is_array($data)) {
                fail($failures, $path, 'a JSON-LD block does not parse');
                continue;
            }
            if (($data['@context'] ?? '') !== 'https://schema.org') {
                fail($failures, $path, 'JSON-LD @context is not https://schema.org');
            }
            foreach ((array)($data['@graph'] ?? [$data]) as $node) {
                if (is_array($node)) {
                    $graph[] = $node;
                }
            }
        }
        foreach (validateJsonLd($graph, $path, $canonical, $page, $cfg, $opt) as $problem) {
            fail($failures, $path, 'JSON-LD: ' . $problem);
        }
        $checks += 4;
    } else {
        fail($failures, $path, 'no JSON-LD block');
    }

    /* ---- conversion and trust, on every page */
    $checks += 2;
    if (!str_contains($html, 'class="wa-bar"')) {
        fail($failures, $path, 'no sticky mobile WhatsApp bar');
    }
    if (in_array($type, ['post', 'activity', 'trip', 'service'], true) && !str_contains($main, 'class="wa-cta"')) {
        fail($failures, $path, 'no WhatsApp button above the fold (wa-cta)');
    }
    if (in_array($path, ['/nosotros/', '/contacto/'], true)) {
        $c = (array)($cfg['contact'] ?? []);
        foreach (['phone_display', 'email'] as $k) {
            if (!empty($c[$k]) && !str_contains($html, (string)$c[$k])) {
                fail($failures, $path, "contact detail \"{$c[$k]}\" from config is not shown");
            }
        }
        if (!empty($c['address']['street']) && !str_contains($html, (string)$c['address']['street'])) {
            fail($failures, $path, 'the address from config is not shown');
        }
    }

    /* ---- guide pages: answer box, FAQ, related links, lead form, freshness */
    if ($guide) {
        $checks += 8;
        if (!str_contains($main, 'class="quick"')) {
            fail($failures, $path, 'no "Respuesta rápida" answer box');
        }
        $faqItems = preg_match_all('#<details class="faq__item">#', $main);
        if ($faqItems < 3 || $faqItems > 5) {
            fail($failures, $path, "expected 3–5 FAQ items, found $faqItems");
        }
        if (!str_contains($html, 'class="related"')) {
            fail($failures, $path, 'no "Te puede interesar" related-links block');
        }
        if (!str_contains($main, 'lead__form--short') || !str_contains($main, 'name="page_title"')) {
            fail($failures, $path, 'no end-of-guide lead form carrying the page context');
        }
        if (!preg_match('#Actualizado: \d{1,2} de \w+ de \d{4}#u', $main)) {
            fail($failures, $path, 'no "Actualizado: <fecha>" line');
        }
        if ($type === 'post') {
            if (!str_contains($main, 'class="author-box"')) {
                fail($failures, $path, 'no author box');
            }
            if (!str_contains($main, 'id="suscribirse"')) {
                fail($failures, $path, 'no newsletter signup at the end of the post');
            }
        }
    }

    foreach (['TourDen', 'Harbert', 'Lorem', 'No content is added yet', '0+'] as $banned) {
        if (str_contains($html, $banned)) {
            fail($failures, $path, "contains the banned string \"$banned\"");
        }
    }
}

/**
 * Structural checks over a page's JSON-LD graph. @return list<string> problems
 */
function validateJsonLd(array $graph, string $path, string $canonical, ?array $page, array $cfg, array $opt): array
{
    $bad   = [];
    $ids   = [];
    $types = [];
    foreach ($graph as $node) {
        $t = $node['@type'] ?? null;
        if ($t === null || $t === '') {
            $bad[] = 'a node has no @type';
            continue;
        }
        $types[] = is_array($t) ? (string)$t[0] : (string)$t;
        if (isset($node['@id'])) {
            if (isset($ids[$node['@id']])) {
                $bad[] = 'duplicate @id ' . $node['@id'];
            }
            $ids[$node['@id']] = true;
        }
    }
    if (!in_array('TravelAgency', $types, true) && !in_array('Organization', $types, true)) {
        $bad[] = 'no Organization node';
    }
    if (!in_array('WebSite', $types, true)) {
        $bad[] = 'no WebSite node';
    }

    $walk = static function (mixed $v) use (&$walk, &$bad, $ids): void {
        if (!is_array($v)) {
            return;
        }
        if (array_keys($v) === ['@id'] && !isset($ids[$v['@id']])) {
            $bad[] = 'reference to a missing node ' . $v['@id'];
        }
        foreach ($v as $child) {
            $walk($child);
        }
    };
    $walk($graph);

    $isIso = static fn(mixed $d): bool => is_string($d) && preg_match('/^\d{4}-\d{2}-\d{2}(T\d{2}:\d{2}(:\d{2})?([+-]\d{2}:\d{2}|Z)?)?$/', $d) === 1;
    $count = static fn(string $t): int => count(array_filter($types, static fn(string $x): bool => $x === $t));

    foreach ($graph as $node) {
        $t = is_array($node['@type'] ?? null) ? (string)$node['@type'][0] : (string)($node['@type'] ?? '');
        switch ($t) {
            case 'BlogPosting':
            case 'NewsArticle':
                foreach (array_merge(['headline', 'datePublished', 'dateModified', 'author', 'publisher', 'description'], ($opt['guides'] ?? false) ? ['image'] : []) as $k) {
                    if (empty($node[$k])) {
                        $bad[] = "$t has no $k";
                    }
                }
                foreach (['datePublished', 'dateModified'] as $k) {
                    if (!empty($node[$k]) && !$isIso($node[$k])) {
                        $bad[] = "$t $k is not ISO 8601: " . (string)$node[$k];
                    }
                }
                if ($isIso($node['datePublished'] ?? null) && $isIso($node['dateModified'] ?? null)
                    && strtotime((string)$node['dateModified']) < strtotime((string)$node['datePublished'])) {
                    $bad[] = "$t dateModified is before datePublished";
                }
                if (empty($node['author']['name'] ?? '')) {
                    $bad[] = "$t author has no name";
                }
                break;
            case 'FAQPage':
                $qs = (array)($node['mainEntity'] ?? []);
                if ($qs === []) {
                    $bad[] = 'FAQPage has no questions';
                }
                foreach ($qs as $q) {
                    if (($q['@type'] ?? '') !== 'Question' || trim((string)($q['name'] ?? '')) === ''
                        || trim((string)($q['acceptedAnswer']['text'] ?? '')) === '') {
                        $bad[] = 'a FAQ Question lacks name or acceptedAnswer.text';
                        break;
                    }
                }
                break;
            case 'Offer':
                if (!is_numeric($node['price'] ?? null) || !preg_match('/^[A-Z]{3}$/', (string)($node['priceCurrency'] ?? ''))) {
                    $bad[] = 'Offer needs a numeric price and a 3-letter priceCurrency';
                }
                break;
            case 'BreadcrumbList':
                $items = (array)($node['itemListElement'] ?? []);
                foreach ($items as $i => $it) {
                    if (($it['position'] ?? 0) !== $i + 1 || empty($it['name']) || !preg_match('#^https?://#', (string)($it['item'] ?? ''))) {
                        $bad[] = 'BreadcrumbList item ' . ($i + 1) . ' is malformed';
                        break;
                    }
                }
                if ($items !== [] && $canonical !== '' && rtrim((string)end($items)['item'], '/') !== rtrim($canonical, '/')) {
                    $bad[] = 'the last BreadcrumbList item is not this page';
                }
                break;
            case 'TouristTrip':
                if (empty($node['provider'])) {
                    $bad[] = 'TouristTrip has no provider';
                }
                if (isset($node['itinerary']) && (int)($node['itinerary']['numberOfItems'] ?? -1) !== count((array)($node['itinerary']['itemListElement'] ?? []))) {
                    $bad[] = 'TouristTrip itinerary numberOfItems does not match its items';
                }
                break;
            case 'TravelAgency':
            case 'Organization':
                foreach ((array)($node['sameAs'] ?? []) as $u) {
                    if (!filter_var($u, FILTER_VALIDATE_URL)) {
                        $bad[] = "Organization sameAs is not a URL: $u";
                    }
                }
                break;
        }
        if (isset($node['url']) && $canonical !== '' && ($node['@id'] ?? '') !== '' && str_contains((string)$node['@id'], '#')
            && !str_ends_with((string)$node['@id'], '#org') && !str_ends_with((string)$node['@id'], '#website')
            && rtrim((string)$node['url'], '/') !== rtrim($canonical, '/')) {
            $bad[] = "$t url does not match the canonical";
        }
    }

    // FAQPage: exactly where the page has a FAQ, nowhere else.
    $wantsFaq = $layoutFaq = ($page['layout'] ?? '') === 'faq';
    $ownFaq   = array_values(array_filter((array)($page['fm']['faq'] ?? []), 'is_array')) !== [];
    if ($ownFaq && in_array($page['type'] ?? '', ['post', 'news', 'trip', 'activity', 'service', 'page'], true)) {
        $wantsFaq = true;
    }
    if ($page !== null && $wantsFaq && $count('FAQPage') !== 1) {
        $bad[] = 'expected exactly one FAQPage node, found ' . $count('FAQPage');
    }
    if ($page !== null && !$wantsFaq && $count('FAQPage') > 0) {
        $bad[] = 'FAQPage on a page that has no FAQ of its own';
    }

    if ($opt['guides'] ?? false) {
        $want = ['post' => 'BlogPosting', 'trip' => 'TouristTrip', 'activity' => 'TouristAttraction', 'service' => 'Service'][$page['type'] ?? ''] ?? null;
        if ($want !== null && $count($want) !== 1) {
            $bad[] = "expected one $want node";
        }
        if (in_array($page['type'] ?? '', ['post', 'trip', 'activity', 'service'], true) && $count('BreadcrumbList') < 1) {
            $bad[] = 'no BreadcrumbList';
        }
    }
    return array_values(array_unique($bad));
}

/**
 * Whole-site checks: unique keywords and the keyword map, orphans, the IndexNow key file.
 * @param list<string> $locs sitemap URLs
 */
function siteChecks(array &$failures, array &$warnings, array $pages, array $htmlByPath, array $cfg, array $opt, string $base, string $baseUrl, string $repo, string $domain, array $locs): int
{
    $checks = 0;

    /* keyword uniqueness (+ the doc that lists them) */
    if ($opt['keywords'] ?? false) {
        $byKw = [];
        foreach ($pages as $path => $p) {
            $kw = norm((string)($p['fm']['keyword'] ?? ''));
            if ($kw !== '' && !$p['noindex'] && !$p['draft']) {
                $byKw[$kw][] = $path;
            }
        }
        foreach ($byKw as $kw => $paths) {
            $checks++;
            if (count($paths) > 1) {
                fail($failures, implode(' + ', $paths), "pages compete for the same keyword \"$kw\"");
            }
        }
        $mapFile = $repo . '/docs/keyword-map.md';
        if (is_file($mapFile)) {
            $mapped = [];
            foreach (file($mapFile, FILE_IGNORE_NEW_LINES) ?: [] as $line) {
                if (preg_match('#^\|\s*`?(/[^`|\s]*)`?\s*\|\s*([^|]+?)\s*\|#', $line, $m)) {
                    $mapped[$m[1]] = trim($m[2]);
                }
            }
            foreach ($pages as $path => $p) {
                if ($p['noindex'] || $p['draft'] || ($p['fm']['keyword'] ?? '') === '') {
                    continue;
                }
                $checks++;
                if (!isset($mapped[$path])) {
                    warn($warnings, $path, 'not listed in docs/keyword-map.md');
                } elseif (norm($mapped[$path]) !== norm((string)$p['fm']['keyword'])) {
                    warn($warnings, $path, 'docs/keyword-map.md says "' . $mapped[$path] . '", front matter says "' . $p['fm']['keyword'] . '"');
                }
            }
        }
    }

    /* orphans: every indexable page needs an inbound link from some other page */
    $inbound = [];
    $inMain  = [];
    foreach ($htmlByPath as $from => $html) {
        $main = preg_match('#<main\b[^>]*>(.*)</main>#si', $html, $mm) ? $mm[1] : '';
        foreach (['all' => $html, 'main' => $main] as $scope => $chunk) {
            if (!preg_match_all('#<a\b[^>]*\bhref="([^"]*)"#i', $chunk, $hs)) {
                continue;
            }
            foreach ($hs[1] as $href) {
                $href = html_entity_decode($href, ENT_QUOTES, 'UTF-8');
                if (str_starts_with($href, $baseUrl)) {
                    $href = substr($href, strlen($baseUrl));
                }
                if ($href === '' || $href[0] !== '/' || str_starts_with($href, '//')) {
                    continue;
                }
                $to = (string)parse_url($href, PHP_URL_PATH);
                if ($to !== '/' && !str_ends_with($to, '/') && !preg_match('#\.[a-z0-9]+$#i', $to)) {
                    $to .= '/';
                }
                if ($to !== $from) {
                    if ($scope === 'all') {
                        $inbound[$to][$from] = true;
                    } else {
                        $inMain[$to][$from] = true;
                    }
                }
            }
        }
    }
    foreach ($pages as $path => $p) {
        if ($p['noindex'] || $p['draft'] || $path === '/' || !isset($htmlByPath[$path])) {
            continue;
        }
        $checks++;
        if (empty($inbound[$path])) {
            fail($failures, $path, 'orphan page: no other page links to it');
        } elseif (empty($inMain[$path])) {
            warn($warnings, $path, 'only linked from the header/footer, never from page content');
        }
    }

    /* IndexNow: /<key>.txt answers with the key */
    $key = (string)($cfg['indexnow']['key'] ?? '');
    if ($key !== '') {
        $checks++;
        $r = request($base . '/' . $key . '.txt');
        if ($r['status'] !== 200 || trim($r['body']) !== $key) {
            fail($failures, "/$key.txt", 'the IndexNow key file does not answer with the key (status ' . $r['status'] . ')');
        }
    }
    return $checks;
}

/** A form POST without following redirects. @return array{status:int,headers:array<string,string>,body:string} */
function post(string $url, array $fields): array
{
    $ctx = stream_context_create(['http' => [
        'method' => 'POST', 'follow_location' => 0, 'ignore_errors' => true, 'timeout' => 20,
        'header' => "Content-Type: application/x-www-form-urlencoded\r\nUser-Agent: viaje-verify/1.0\r\n",
        'content' => http_build_query($fields),
    ]]);
    $body = @file_get_contents($url, false, $ctx);
    $status = 0;
    $headers = [];
    foreach ($http_response_header ?? [] as $h) {
        if (preg_match('#^HTTP/\S+\s+(\d{3})#', $h, $m)) {
            $status = (int)$m[1];
        } elseif (str_contains($h, ':')) {
            [$k, $v] = explode(':', $h, 2);
            $headers[strtolower(trim($k))] = trim($v);
        }
    }
    return ['status' => $status, 'headers' => $headers, 'body' => (string)$body];
}

/**
 * The forms as a visitor uses them: take the signed stamp out of a *served* guide page (the
 * page cache must not have let it expire), submit, and read the record from disk.
 */
function formChecks(array &$failures, string $base, array $htmlByPath, string $distDir): int
{
    $checks = 0;
    $guide  = '/actividades/saltos-del-monday/';
    $html   = $htmlByPath[$guide] ?? '';
    if ($html === '' && $htmlByPath !== []) {
        $guide = (string)array_key_first(array_filter($htmlByPath, static fn(string $h): bool => str_contains($h, 'lead__form--short')));
        $html  = $htmlByPath[$guide] ?? '';
    }
    if ($html === '' || !preg_match_all('#<input type="hidden" name="ts" value="([^"]+)">#', $html, $ts) || count($ts[1]) < 1) {
        fail($failures, $guide, 'cannot find the signed form stamp on the guide page');
        return 1;
    }
    sleep(4);   // Leads::MIN_AGE is 3 s
    $leads = $distDir . '/site/data/leads';
    $email = 'verify+' . substr(sha1((string)microtime(true)), 0, 8) . '@example.com';

    // 1. contact/lead form on a guide: back to the guide, with title and URL on the record
    $checks++;
    $r = post($base . '/enviar/', [
        'name' => 'Verifier', 'phone' => '0981 000 000', 'message' => 'Quiero más información sobre esta guía.',
        'topic' => 'Consulta desde: guía', 'ts' => $ts[1][0], 'page' => $guide, 'page_title' => 'Saltos del Monday', 'website' => '',
    ]);
    if ($r['status'] !== 303 || !str_starts_with((string)($r['headers']['location'] ?? ''), $guide . '?enviado=1')) {
        fail($failures, '/enviar/', 'a lead from a guide should 303 back to the guide, got ' . $r['status'] . ' ' . ($r['headers']['location'] ?? ''));
    } else {
        $rows = array_filter(array_map('trim', (array)glob($leads . '/*.jsonl')));
        $last = '';
        foreach ($rows as $f) {
            $lines = file($f, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
            $last  = (string)end($lines);
        }
        $rec = json_decode($last, true);
        if (!is_array($rec) || ($rec['page'] ?? '') !== $guide || ($rec['page_title'] ?? '') !== 'Saltos del Monday'
            || !str_ends_with((string)($rec['page_url'] ?? ''), $guide)) {
            fail($failures, '/enviar/', 'the lead record does not carry the page, its title and its URL: ' . $last);
        }
    }

    // 2. newsletter, double opt-in: signup → pending + email, bad token → error, link → confirmed, replay → harmless
    $pendingFile = $leads . '/newsletter-pending.jsonl';
    $nlFile      = $leads . '/newsletter.jsonl';
    $outbox      = $distDir . '/site/data/outbox.log';
    $rowsFor = static function (string $file) use ($email): array {
        $lines = is_file($file) ? (file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: []) : [];
        return array_values(array_filter(array_map(static fn(string $l) => json_decode($l, true), $lines),
            static fn($r): bool => is_array($r) && strtolower((string)($r['email'] ?? '')) === strtolower($email)));
    };
    $checks += 8;
    $r = post($base . '/suscribir/', ['email' => $email, 'ts' => $ts[1][0], 'page' => $guide, 'website' => '']);
    if ($r['status'] !== 303 || !str_contains((string)($r['headers']['location'] ?? ''), 'suscrito=1')) {
        fail($failures, '/suscribir/', 'signup should 303 with ?suscrito=1, got ' . $r['status'] . ' ' . ($r['headers']['location'] ?? ''));
    }
    post($base . '/suscribir/', ['email' => strtoupper($email), 'ts' => $ts[1][0], 'page' => $guide, 'website' => '']);
    $pending = $rowsFor($pendingFile);
    if (count($pending) !== 1 || ($pending[0]['status'] ?? '') !== 'pending' || ($pending[0]['mail'] ?? '') !== 'sent') {
        fail($failures, '/suscribir/', 'a signup should leave exactly one pending row (mail sent) in newsletter-pending.jsonl, found ' . json_encode($pending));
    }
    if ($rowsFor($nlFile) !== []) {
        fail($failures, '/suscribir/', 'an unconfirmed address must not be in newsletter.jsonl');
    }
    $link = null;
    $mail = is_file($outbox) ? (string)file_get_contents($outbox) : '';
    if (preg_match_all('#https?://\S+?(/suscribir/confirmar/\?e=([^&\s]+)&t=([a-f0-9]{64}))#', $mail, $lm, PREG_SET_ORDER)) {
        foreach ($lm as $m) {
            if (urldecode($m[2]) === $email) {
                $link = $m[1];
            }
        }
    }
    if ($link === null) {
        fail($failures, '/suscribir/', 'no confirmation email with a /suscribir/confirmar/ link was sent to ' . $email);
    } else {
        if (substr_count($mail, $email) > 2) {
            fail($failures, '/suscribir/', 'a repeat signup within the hour must not mail the address again');
        }
        $bad = request($base . '/suscribir/confirmar/?' . http_build_query(['e' => $email, 't' => str_repeat('0', 64)]));
        if ($bad['status'] !== 400 || $rowsFor($nlFile) !== []) {
            fail($failures, '/suscribir/confirmar/', 'a bad token should answer 400 and confirm nothing, got ' . $bad['status']);
        }
        $ok = request($base . $link);
        $confirmed = $rowsFor($nlFile);
        if ($ok['status'] !== 200 || count($confirmed) !== 1 || ($confirmed[0]['status'] ?? '') !== 'confirmed'
            || !str_contains($ok['body'], 'noindex')) {
            fail($failures, '/suscribir/confirmar/', 'the emailed link should answer 200 (noindex) and append one confirmed row, got '
                . $ok['status'] . ' ' . json_encode($confirmed));
        }
        if ($rowsFor($pendingFile) !== []) {
            fail($failures, '/suscribir/confirmar/', 'a confirmed address should leave newsletter-pending.jsonl');
        }
        $again = request($base . $link);
        if ($again['status'] !== 200 || count($rowsFor($nlFile)) !== 1) {
            fail($failures, '/suscribir/confirmar/', 'replaying the link should be a harmless 200 that writes nothing');
        }
        $r = post($base . '/suscribir/', ['email' => $email, 'ts' => $ts[1][0], 'page' => $guide, 'website' => '']);
        if (!str_contains((string)($r['headers']['location'] ?? ''), 'suscrito=ya') || count($rowsFor($nlFile)) !== 1 || $rowsFor($pendingFile) !== []) {
            fail($failures, '/suscribir/', 'signing up a confirmed address should 303 with ?suscrito=ya and write nothing');
        }
    }
    $r = post($base . '/suscribir/', ['email' => 'not-an-email', 'ts' => $ts[1][0], 'page' => $guide, 'website' => '']);
    if ($r['status'] !== 303 || !str_contains((string)($r['headers']['location'] ?? ''), 'suscripcion=error')) {
        fail($failures, '/suscribir/', 'an invalid email should 303 with ?suscripcion=error');
    }
    return $checks;
}

/**
 * Where the site's markdown lives: content/ when it exists (fixtures, a local admin
 * session), otherwise the tracked content-seed/ — the server owns content/ after the
 * first deploy, so a fresh checkout and CI only ever have the seed. Mirrors build.php.
 */
function contentDir(string $siteDir): string
{
    return is_dir($siteDir . '/content') ? $siteDir . '/content' : $siteDir . '/content-seed';
}

/** True when the content file behind $path is marked noindex or draft. */
function isNoindex(string $siteDir, string $path, array $cfg = []): bool
{
    static $map = null;
    if ($map === null) {
        $map = [];
        $folders = ['page' => 'pages', 'service' => 'services', 'post' => 'posts',
                    'news' => 'news', 'trip' => 'trips', 'activity' => 'activities'];
        foreach ($folders as $type => $folder) {
            foreach (glob(contentDir($siteDir) . '/' . $folder . '/*.md') ?: [] as $file) {
                $raw = (string)file_get_contents($file);
                if (!preg_match('/^---\R(.*?)\R---/s', $raw, $m)) {
                    continue;
                }
                $fm   = $m[1];
                $slug = basename($file, '.md');
                $p    = contentPath($fm, $type, $slug, $cfg);
                $map[$p] = (bool)preg_match('/^noindex:\s*true/m', $fm)
                    || (bool)preg_match('/^draft:\s*true/m', $fm);
            }
        }
    }
    return $map[$path] ?? false;
}

/** The URL a content file resolves to, mirroring Content::meta(). */
function contentPath(string $fm, string $type, string $slug, array $cfg): string
{
    if (preg_match('/^path:\s*(\S+)/m', $fm, $pm)) {
        $p = rtrim('/' . trim($pm[1], "\"'/"), '/') . '/';
        return $p === '/' ? '/' : $p;
    }
    if ($type === 'page' && $slug === 'home') {
        return '/';
    }
    return rtrim((string)($cfg['type_paths'][$type] ?? '/'), '/') . '/' . $slug . '/';
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
    foreach (glob(contentDir($siteDir) . '/*', GLOB_ONLYDIR) ?: [] as $dir) {
        if (!in_array(basename($dir), $known, true)) {
            $issues[] = ['content/' . basename($dir), 'unknown content folder for the enabled types'];
        }
    }
    foreach ($known as $folder) {
        if ($folder === 'data') {
            continue;
        }
        $typePath = array_search($folder, $folders, true);
        foreach (glob(contentDir($siteDir) . '/' . $folder . '/*.md') ?: [] as $file) {
            $rel  = 'content/' . $folder . '/' . basename($file);
            $raw  = (string)file_get_contents($file);
            if (!preg_match('/^---\R(.*?)\R---/s', $raw, $m)) {
                $issues[] = [$rel, 'has no front matter'];
                continue;
            }
            $fm    = $m[1];
            $slug  = basename($file, '.md');
            $draft = (bool)preg_match('/^draft:\s*true/m', $fm);
            $path  = contentPath($fm, (string)$typePath, $slug, $cfg);
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
