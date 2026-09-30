<?php
declare(strict_types=1);

/**
 * Shared helpers for tools/tests/*.php: build a site into dist/, serve it with PHP's built-in
 * server, make raw HTTP requests, and count results. Run by tools/ci-local.sh.
 */

const T_REPO = __DIR__ . '/../..';

$GLOBALS['t_fails'] = 0;
$GLOBALS['t_n']     = 0;

function t_check(string $name, bool $ok, string $detail = ''): void
{
    $GLOBALS['t_n']++;
    if (!$ok) {
        $GLOBALS['t_fails']++;
        echo "  FAIL  $name" . ($detail !== '' ? " — $detail" : '') . "\n";
    }
}

function t_done(string $label): never
{
    echo "$label: {$GLOBALS['t_n']} checks, {$GLOBALS['t_fails']} failure(s)\n";
    exit($GLOBALS['t_fails'] === 0 ? 0 : 1);
}

/** Fresh build of dist/<domain>; returns the dist dir. */
function t_build(string $domain): string
{
    exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(T_REPO . '/tools/build.php') . ' ' . escapeshellarg($domain) . ' --fresh', $out, $rc);
    if ($rc !== 0) {
        fwrite(STDERR, "build failed\n");
        exit(1);
    }
    return realpath(T_REPO . '/dist/' . $domain) ?: T_REPO . '/dist/' . $domain;
}

/**
 * Serve $dist on a free port. $ini: extra -d settings. $workers > 1 lets requests run in
 * parallel (PHP_CLI_SERVER_WORKERS). Stopped automatically at exit. Returns the base URL.
 */
function t_serve(string $dist, array $ini = [], int $workers = 1): string
{
    $sock = stream_socket_server('tcp://127.0.0.1:0');
    $name = (string)stream_socket_get_name($sock, false);
    fclose($sock);
    $port = (int)substr($name, (int)strrpos($name, ':') + 1);
    $args = '';
    foreach ($ini as $k => $v) {
        $args .= ' -d ' . escapeshellarg($k . '=' . $v);
    }
    $cmd = ($workers > 1 ? 'PHP_CLI_SERVER_WORKERS=' . $workers . ' ' : '') . 'exec ' . escapeshellarg(PHP_BINARY) . $args
        . ' -S 127.0.0.1:' . $port . ' -t ' . escapeshellarg($dist) . ' ' . escapeshellarg($dist . '/engine/dev-router.php');
    $proc = proc_open($cmd, [1 => ['file', '/dev/null', 'a'], 2 => ['file', '/dev/null', 'a']], $pipes);
    register_shutdown_function(static function () use ($proc): void {
        if (is_resource($proc)) {
            proc_terminate($proc);
            proc_close($proc);
        }
    });
    for ($i = 0; $i < 100; $i++) {
        $fp = @fsockopen('127.0.0.1', $port, $errno, $errstr, 0.2);
        if ($fp) {
            fclose($fp);
            return 'http://127.0.0.1:' . $port;
        }
        usleep(100000);
    }
    fwrite(STDERR, "server did not start\n");
    exit(1);
}

/**
 * One HTTP request, redirects not followed.
 * @param array<string,string> $headers
 * @return array{status:int,headers:array<string,string>,body:string}
 */
function t_http(string $method, string $url, array|string|null $form = null, array $headers = [], ?string $cookieJar = null): array
{
    $ch = curl_init($url);
    $h  = [];
    foreach ($headers as $k => $v) {
        $h[] = $k . ': ' . $v;
    }
    $resHeaders = [];
    curl_setopt_array($ch, [
        CURLOPT_CUSTOMREQUEST  => $method,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_TIMEOUT        => 30,
        CURLOPT_HTTPHEADER     => $h,
        CURLOPT_HEADERFUNCTION => static function ($ch, string $line) use (&$resHeaders): int {
            if (str_contains($line, ':')) {
                [$k, $v] = explode(':', $line, 2);
                $resHeaders[strtolower(trim($k))] = trim($v);
            }
            return strlen($line);
        },
    ]);
    if ($form !== null) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, is_array($form) ? http_build_query($form) : $form);
    }
    if ($cookieJar !== null) {
        curl_setopt($ch, CURLOPT_COOKIEJAR, $cookieJar);
        curl_setopt($ch, CURLOPT_COOKIEFILE, $cookieJar);
    }
    $body   = curl_exec($ch);
    $status = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    curl_close($ch);
    return ['status' => $status, 'headers' => $resHeaders, 'body' => (string)$body];
}

/** A fresh signed form stamp from GET /enviar/sello/. */
function t_stamp(string $base): string
{
    $r = t_http('GET', $base . '/enviar/sello/');
    $j = json_decode($r['body'], true);
    return is_array($j) ? (string)($j['ts'] ?? '') : '';
}

/** JSONL file → decoded rows. @return list<array> */
function t_jsonl(string $file): array
{
    $lines = is_file($file) ? (file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: []) : [];
    return array_values(array_filter(array_map(static fn(string $l) => json_decode($l, true), $lines), 'is_array'));
}
