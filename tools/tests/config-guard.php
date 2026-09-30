<?php
declare(strict_types=1);

/**
 * tools/tests/config-guard.php — the no-Google rule for config (negative tests).
 *   1. Config::thirdPartyHits() finds banned hosts anywhere in a config array, and not in clean ones.
 *   2. Config::load() logs a warning (and still boots) when head_extra/body_extra load one.
 *   3. verify.php's config gate fails a site whose config.php carries one (run against a
 *      throwaway copy of viaje.com.py's config under sites/, removed again afterwards).
 * Exit 0 = pass.
 */

require __DIR__ . '/lib.php';
require_once T_REPO . '/engine/lib/util.php';
require_once T_REPO . '/engine/lib/config.php';

/* ---- 1. the scanner ------------------------------------------------------------------------ */
$bad = [
    'head_extra' => '<script async src="https://www.googletagmanager.com/gtag/js?id=G-X"></script>',
    'body_extra' => '',
    'contact'    => ['map_url' => 'https://maps.app.goo.gl/abc'],
    'socials'    => ['https://www.youtube.com/@viaje'],
    'nested'     => ['deep' => ['x' => '<script src="https://www.google.com/recaptcha/api.js"></script>']],
];
$hits = Config::thirdPartyHits($bad);
t_check('scanner: finds all four banned values', count($hits) === 4, implode(' | ', $hits));
t_check('scanner: reports the key path', in_array('contact.map_url: maps.app.goo.gl', $hits, true), implode(' | ', $hits));
$clean = [
    'head_extra' => '<link rel="preconnect" href="https://www.openstreetmap.org">',
    'footer'     => 'No usamos Google Analytics. Mapas de OpenStreetMap, búsqueda con Bing.',
    'email'      => 'hola@viaje.com.py',
];
t_check('scanner: clean config has no hits', Config::thirdPartyHits($clean) === [], implode(' | ', Config::thirdPartyHits($clean)));
foreach (['config.php', 'config.local.example.php'] as $f) {
    $cfg = require T_REPO . '/sites/viaje.com.py/' . $f;
    t_check("scanner: the real viaje.com.py $f is clean", Config::thirdPartyHits($cfg) === []);
}

/* ---- 2. runtime warning -------------------------------------------------------------------- */
$tmp = sys_get_temp_dir() . '/vj-guard-' . bin2hex(random_bytes(4));
mkdir($tmp);
copy(T_REPO . '/sites/viaje.com.py/config.php', $tmp . '/config.php');
$log = $tmp . '/php-error.log';
ini_set('log_errors', '1');
ini_set('error_log', $log);
define('VJ_SITE', $tmp);

file_put_contents($tmp . '/config.local.php', "<?php\nreturn ['body_extra' => '<script src=\"https://www.google-analytics.com/analytics.js\"></script>'];\n");
$cfg = Config::load($tmp);
$logged = (string)@file_get_contents($log);
t_check('runtime: a banned host in body_extra is logged as a warning', str_contains($logged, 'Config warning: body_extra: www.google-analytics.com'), $logged);
t_check('runtime: the site still boots (config loaded)', ($cfg['domain'] ?? '') === 'viaje.com.py');

@unlink($log);
file_put_contents($tmp . '/config.local.php', "<?php\nreturn ['head_extra' => '<meta name=\"x\" content=\"ok\">'];\n");
Config::load($tmp);
t_check('runtime: clean head_extra logs nothing', trim((string)@file_get_contents($log)) === '');
array_map('unlink', glob($tmp . '/*') ?: []);
rmdir($tmp);

/* ---- 3. verify.php's gate, end to end ------------------------------------------------------ */
$fake = T_REPO . '/sites/zz-config-guard-test';
register_shutdown_function(static function () use ($fake): void {
    foreach (glob($fake . '/*') ?: [] as $f) {
        @unlink($f);
    }
    @rmdir($fake);
});
@mkdir($fake);
$src = (string)file_get_contents(T_REPO . '/sites/viaje.com.py/config.php');
$src = preg_replace("/'head_extra'\\s*=>\\s*'[^']*'/", "'head_extra' => '<link rel=\"stylesheet\" href=\"https://fonts.googleapis.com/css2?family=Fraunces\">'", $src, 1, $n) ?? $src;
if ($n === 0) {
    $src = preg_replace('/return \[/', "return [\n    'head_extra' => '<link rel=\"stylesheet\" href=\"https://fonts.googleapis.com/css2?family=Fraunces\">',", $src, 1) ?? $src;
}
file_put_contents($fake . '/config.php', $src);
file_put_contents($fake . '/urls.txt', "/ 200\n");
// --base points at a closed port: the URL checks fail too, we only look for the config line.
exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(T_REPO . '/tools/verify.php') . ' zz-config-guard-test --base=http://127.0.0.1:9 2>&1', $out, $rc);
$text = implode("\n", $out);
t_check('verify: exits non-zero', $rc !== 0);
t_check('verify: names config.php and the host', (bool)preg_match('#FAIL\s+config\.php\s+third-party host in a config value.*fonts\.googleapis\.com#', $text), $text);

t_done('config-guard');
