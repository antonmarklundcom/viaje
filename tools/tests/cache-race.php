<?php
declare(strict_types=1);

/**
 * tools/tests/cache-race.php — the page cache under concurrent first requests after a deploy.
 *
 * Serves a fresh build with 8 PHP workers, warms the cache, then for several rounds: touches a
 * template (a "deploy"), ages cache/.sigcheck past its 60 s window, and fires 20 requests at once.
 * Every response must be a complete 200 page, and afterwards exactly one generation directory
 * may remain, holding only complete pages. Also checks that the fingerprint is not recomputed
 * while the marker is fresh. Exit 0 = pass.
 */

require __DIR__ . '/lib.php';

$dist   = t_build('viaje.com.py');
$base   = t_serve($dist, [], 8);
$pages  = $dist . '/site/cache/pages';
$marker = $dist . '/site/cache/.sigcheck';
$tpl    = $dist . '/engine/templates/post.php';
$paths  = ['/', '/blog/', '/actividades/saltos-del-monday/', '/destinos-imperdibles-2026/', '/servicios/',
           '/agencia-de-viaje/', '/vacaciones/', '/faq/', '/nosotros/', '/actividades/'];

$generations = static function () use ($pages): array {
    return array_values(array_filter(glob($pages . '/*', GLOB_ONLYDIR) ?: [], static fn(string $d): bool => basename($d)[0] !== '.'));
};

/** 20 GETs at once. @return list<array{path:string,status:int,body:string,cache:string}> */
$burst = static function () use ($base, $paths): array {
    $mh = curl_multi_init();
    $handles = [];
    for ($i = 0; $i < 20; $i++) {
        $path = $paths[$i % count($paths)];
        $ch   = curl_init($base . $path);
        $hdr  = '';
        curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 30, CURLOPT_HEADER => true]);
        curl_multi_add_handle($mh, $ch);
        $handles[] = [$path, $ch];
    }
    do {
        $st = curl_multi_exec($mh, $running);
        if ($running) {
            curl_multi_select($mh, 0.5);
        }
    } while ($running && $st === CURLM_OK);
    $out = [];
    foreach ($handles as [$path, $ch]) {
        $raw  = (string)curl_multi_getcontent($ch);
        $hs   = (int)curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        $head = substr($raw, 0, $hs);
        $out[] = [
            'path'   => $path,
            'status' => (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE),
            'body'   => substr($raw, $hs),
            'cache'  => preg_match('/^X-Cache:\s*(\S+)/mi', $head, $m) ? $m[1] : 'MISS',
        ];
        curl_multi_remove_handle($mh, $ch);
        curl_close($ch);
    }
    curl_multi_close($mh);
    return $out;
};

$complete = static fn(string $html): bool => strlen($html) > 2000 && str_contains($html, '<h1') && rtrim($html) !== '' && str_ends_with(rtrim($html), '</html>');

// Warm.
foreach ($paths as $p) {
    t_http('GET', $base . $p);
}
$gens = $generations();
t_check('warm cache: one generation', count($gens) === 1, implode(', ', array_map('basename', $gens)));

// A deploy inside the 60 s window is not noticed yet (no full stat pass), and nothing breaks.
$before = basename($gens[0] ?? '');
touch($tpl, time() + 10);
$r = t_http('GET', $base . '/blog/');
t_check('fresh marker: no recompute, cached page still served', ($r['headers']['x-cache'] ?? '') === 'HIT' && basename($generations()[0] ?? '') === $before);

$hits = $miss = 0;
for ($round = 1; $round <= 6; $round++) {
    touch($tpl, time() + 10 + $round * 10);     // a "deploy": the build signature changes
    touch($marker, time() - 120);                // ...and the 60 s window has passed
    $old = array_map('basename', $generations());
    foreach ($burst() as $res) {
        $res['cache'] === 'HIT' ? $hits++ : $miss++;
        t_check("round $round {$res['path']}: 200", $res['status'] === 200, (string)$res['status']);
        t_check("round $round {$res['path']}: complete page", $complete($res['body']), strlen($res['body']) . ' bytes');
    }
    $gens = $generations();
    t_check("round $round: exactly one generation remains", count($gens) === 1, implode(', ', array_map('basename', $gens)));
    t_check("round $round: it is a new generation", $gens !== [] && !in_array(basename($gens[0]), $old, true));
    foreach ($gens as $g) {
        foreach (glob($g . '/*') ?: [] as $f) {
            t_check("round $round: cached file complete " . basename($f), $complete((string)file_get_contents($f)));
        }
        t_check("round $round: no temp files left", (glob($g . '/.tmp-*') ?: []) === []);
    }
    t_check("round $round: no retired generations left", (glob($pages . '/.old-*', GLOB_ONLYDIR) ?: []) === []);
}
echo "  (bursts: $miss rendered, $hits served from cache)\n";

// The next request after the burst is a cache hit on the surviving generation.
$r = t_http('GET', $base . '/actividades/saltos-del-monday/');
t_check('after the burst: cache HIT', ($r['headers']['x-cache'] ?? '') === 'HIT');

t_done('cache-race');
