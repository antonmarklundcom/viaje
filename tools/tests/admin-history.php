<?php
declare(strict_types=1);

/**
 * tools/tests/admin-history.php — content history through the real admin, over HTTP.
 *
 * Builds viaje.com.py into dist/, writes a temporary config.local.php (random password hash)
 * into the built site only, logs in, and checks: every save snapshots the previous version,
 * Restore brings a version back byte-for-byte (snapshotting the current one first), Restore
 * needs the CSRF token, Restore from seed only shows when a seed file exists, delete keeps a
 * version and the page can be brought back, the newest 20 are kept, data collections work the
 * same way, and the export zip and backup.php include data/history. The temporary config is
 * deleted at the end, and the repo's working tree must be untouched. Exit 0 = pass.
 */

require __DIR__ . '/lib.php';

exec('git -C ' . escapeshellarg(T_REPO) . ' status --porcelain --untracked-files=all', $gitBefore);
$dist  = t_build('viaje.com.py');
$site  = $dist . '/site';
$local = $site . '/config.local.php';
$pw    = bin2hex(random_bytes(12));
file_put_contents($local, "<?php\nreturn ['admin_password_hash' => " . var_export(password_hash($pw, PASSWORD_DEFAULT), true) . "];\n");
register_shutdown_function(static fn() => @unlink($local));

$base = t_serve($dist);
$jar  = tempnam(sys_get_temp_dir(), 'vjjar');
register_shutdown_function(static fn() => @unlink($jar));
$get  = static fn(string $p): array => t_http('GET', $base . $p, null, [], $jar);
$post = static fn(string $p, array $f): array => t_http('POST', $base . $p, $f, [], $jar);

$type = 'activity';
$slug = 'saltos-del-monday';
$file = $site . '/content/activities/' . $slug . '.md';
$seed = $site . '/content-seed/activities/' . $slug . '.md';
$hist = $site . '/data/history/' . $type . '/' . $slug;
$page = '/actividades/' . $slug . '/';
$orig = (string)file_get_contents($file);
$versions = static fn(): array => array_values(array_filter(glob($hist . '/*.md') ?: [], 'is_file'));

/** Every field of the editor form as a browser would send it, with $change applied. */
$formOf = static function (string $html): array {
    $doc = new DOMDocument();
    @$doc->loadHTML('<?xml encoding="utf-8"?>' . $html);
    $form = (new DOMXPath($doc))->query('//form[@id="editor"]')->item(0);
    $out  = [];
    if (!$form instanceof DOMElement) {
        return $out;
    }
    foreach ((new DOMXPath($doc))->query('.//input|.//textarea|.//select', $form) as $el) {
        /** @var DOMElement $el */
        $name = $el->getAttribute('name');
        if ($name === '' || ($el->tagName === 'input' && in_array($el->getAttribute('type'), ['submit', 'button', 'file'], true))) {
            continue;
        }
        if ($el->tagName === 'input' && $el->getAttribute('type') === 'checkbox' && !$el->hasAttribute('checked')) {
            continue;
        }
        $value = match ($el->tagName) {
            'textarea' => $el->textContent,
            'select'   => (static function (DOMElement $s): string {
                foreach ($s->getElementsByTagName('option') as $o) {
                    if ($o->hasAttribute('selected')) {
                        return $o->getAttribute('value');
                    }
                }
                $first = $s->getElementsByTagName('option')->item(0);
                return $first ? $first->getAttribute('value') : '';
            })($el),
            default    => $el->getAttribute('value'),
        };
        $out[] = [$name, $value];
    }
    return $out;
};
$encode = static fn(array $pairs): string => implode('&', array_map(static fn(array $p): string => rawurlencode($p[0]) . '=' . rawurlencode($p[1]), $pairs));
$save = static function (string $bodySuffix) use ($get, $base, $jar, $formOf, $encode, $type, $slug): array {
    $pairs = $formOf($get('/admin/content/' . $type . '/' . $slug . '/edit')['body']);
    foreach ($pairs as &$p) {
        if ($p[0] === 'body') {
            $p[1] = rtrim($p[1]) . "\n\n" . $bodySuffix . "\n";
        }
    }
    unset($p);
    $pairs[] = ['action', 'publish'];
    return t_http('POST', $base . '/admin/content/' . $type . '/save', $encode($pairs), ['Content-Type' => 'application/x-www-form-urlencoded'], $jar);
};
$csrf = static function (string $html): string {
    return preg_match('#name="csrf" value="([a-f0-9]+)"#', $html, $m) ? $m[1] : '';
};

/* ---- log in ------------------------------------------------------------------------------ */
$get('/admin/');
$r = $post('/admin/login', ['password' => $pw]);
t_check('login with the temporary hash', $r['status'] === 303 && str_contains((string)($r['headers']['location'] ?? ''), '/admin/dashboard'), (string)$r['status']);

/* ---- 1. saves snapshot the previous version ------------------------------------------------ */
$get($page);                                                   // cache the public page
$r = $save('Edición de prueba uno.');
t_check('save 1 → 303', $r['status'] === 303, (string)$r['status']);
$f1 = (string)file_get_contents($file);
t_check('save 1 changed the file', $f1 !== $orig && str_contains($f1, 'Edición de prueba uno.'));
t_check('save 1 kept the previous version (byte-identical)', count($versions()) === 1 && file_get_contents($versions()[0]) === $orig);
t_check('the public page shows the edit at once (cache purged)', str_contains($get($page)['body'], 'Edición de prueba uno.'));
$save('Edición de prueba dos.');
t_check('save 2 → two versions', count($versions()) === 2);

/* ---- 2. history screen, restore ------------------------------------------------------------- */
$h = $get('/admin/content/' . $type . '/' . $slug . '/history');
t_check('history screen lists both versions with Restore buttons', $h['status'] === 200 && substr_count($h['body'], 'name="version"') === 2);
t_check('history screen offers Restore from seed (the seed file exists)', is_file($seed) && str_contains($h['body'], '/restore-seed'));
t_check('edit page links to the history with its count', str_contains($get('/admin/content/' . $type . '/' . $slug . '/edit')['body'], '/history">Historial (2)'));
$oldest = basename($versions()[0]);
$view = $get('/admin/content/' . $type . '/' . $slug . '/history?v=' . rawurlencode($oldest));
t_check('a version can be viewed', $view['status'] === 200 && str_contains($view['body'], 'adm-history-text'));

$r = $post('/admin/content/' . $type . '/' . $slug . '/restore', ['version' => $oldest]);
t_check('restore without CSRF → 403, file unchanged', $r['status'] === 403 && str_contains((string)file_get_contents($file), 'Edición de prueba dos.'));
$r = $post('/admin/content/' . $type . '/' . $slug . '/restore', ['version' => $oldest, 'csrf' => $csrf($h['body'])]);
t_check('restore → 303 back to the history', $r['status'] === 303 && str_contains((string)($r['headers']['location'] ?? ''), '/history?restored=1'));
t_check('restore brought the original back byte-for-byte', file_get_contents($file) === $orig);
t_check('restore snapshotted the version it replaced', count($versions()) === 3 && str_contains((string)file_get_contents($versions()[2]), 'Edición de prueba dos.'));
t_check('the public page shows the restored text at once', !str_contains($get($page)['body'], 'Edición de prueba'));
$r = $post('/admin/content/' . $type . '/' . $slug . '/restore', ['version' => '../../../config.local.php', 'csrf' => $csrf($h['body'])]);
t_check('a bogus version id is refused', $r['status'] === 404 && file_get_contents($file) === $orig);

/* ---- 3. restore from seed ------------------------------------------------------------------ */
$save('Edición de prueba tres.');
$r = $post('/admin/content/' . $type . '/' . $slug . '/restore-seed', ['csrf' => $csrf($h['body'])]);
t_check('restore from seed → the seed file, byte-for-byte', $r['status'] === 303 && file_get_contents($file) === file_get_contents($seed));
$post('/admin/content/page/no-such-page/restore-seed', ['csrf' => $csrf($h['body'])]);
$noSeed = $get('/admin/content/page/no-such-page/history');
t_check('no seed file → no Restore-from-seed button', $noSeed['status'] === 200 && !str_contains($noSeed['body'], '/restore-seed'));
t_check('no seed file → nothing created', !is_file($site . '/content/pages/no-such-page.md'));

/* ---- 4. delete keeps a version; the page comes back from the history ---------------------- */
$before = count($versions());
$save('Edición de prueba cuatro.');
$r = $post('/admin/content/' . $type . '/' . $slug . '/delete', ['csrf' => $csrf($h['body'])]);
t_check('delete → the page is gone', $r['status'] === 303 && !is_file($file) && $get($page)['status'] === 404);
$last = (string)file_get_contents($versions()[count($versions()) - 1]);
t_check('delete snapshotted the deleted version', count($versions()) === $before + 2 && str_contains($last, 'Edición de prueba cuatro.'));
$list = $get('/admin/content/' . $type . '/');
t_check('the list shows it under "deleted, with history"', str_contains($list['body'], $slug . '/history'));
$newest = basename($versions()[count($versions()) - 1]);
$post('/admin/content/' . $type . '/' . $slug . '/restore', ['version' => $newest, 'csrf' => $csrf($h['body'])]);
t_check('restoring after delete recreates the page', is_file($file) && $get($page)['status'] === 200 && str_contains($get($page)['body'], 'Edición de prueba cuatro.'));

/* ---- 5. only the newest 20 are kept ---------------------------------------------------------- */
for ($i = 1; $i <= 22; $i++) {
    $save('Poda ' . $i . '.');
}
$v = $versions();
t_check('history keeps the newest 20', count($v) === 20, (string)count($v));
t_check('…and they are the newest', str_contains((string)file_get_contents($v[19]), 'Poda 21.') && !str_contains((string)file_get_contents($v[0]), 'Edición de prueba uno.'));
$post('/admin/content/' . $type . '/' . $slug . '/restore-seed', ['csrf' => $csrf($h['body'])]);

/* ---- 6. data collections ------------------------------------------------------------------ */
$dataFile = $site . '/content/data/faq.json';
$dataOrig = (string)file_get_contents($dataFile);
$d = $get('/admin/data/faq');
$rows = json_decode($dataOrig, true);
$fields = ['csrf' => $csrf($d['body'])];
foreach ($rows as $i => $row) {
    $fields["rows[$i][q]"]    = $row['q'] . ($i === 0 ? ' (prueba)' : '');
    $fields["rows[$i][a]"]    = $row['a'];
    $fields["rows[$i][tags]"] = implode(', ', (array)($row['tags'] ?? []));
}
$post('/admin/data/faq', $fields);
$dv = glob($site . '/data/history/data/faq/*.json') ?: [];
t_check('data save snapshotted the previous faq.json', count($dv) === 1 && file_get_contents($dv[0]) === $dataOrig && str_contains((string)file_get_contents($dataFile), '(prueba)'));
$post('/admin/data/faq/restore', ['version' => basename($dv[0]), 'csrf' => $fields['csrf']]);
t_check('data restore → faq.json byte-for-byte', file_get_contents($dataFile) === $dataOrig);
t_check('data page links to its history', str_contains($get('/admin/data/faq')['body'], '/admin/data/faq/history'));

/* ---- 7. export zip and backup.php include data/history ------------------------------------- */
$zipBody = t_http('POST', $base . '/admin/export', ['csrf' => $fields['csrf']], [], $jar)['body'];
$zf = tempnam(sys_get_temp_dir(), 'vjzip');
file_put_contents($zf, $zipBody);
$zip = new ZipArchive();
$names = [];
if ($zip->open($zf) === true) {
    for ($i = 0; $i < $zip->numFiles; $i++) {
        $names[] = (string)$zip->getNameIndex($i);
    }
    $zip->close();
}
@unlink($zf);
t_check('admin export zip includes data/history/', (bool)preg_grep('#^data/history/activity/' . $slug . '/\d{8}-\d{6}-\d{6}\.md$#', $names), count($names) . ' entries');
exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($dist . '/engine/bin/backup.php') . ' 2>&1', $bo, $brc);
$bz = glob($site . '/data/backups/*.zip') ?: [];
$bn = [];
if ($bz !== [] && $zip->open($bz[0]) === true) {
    for ($i = 0; $i < $zip->numFiles; $i++) {
        $bn[] = (string)$zip->getNameIndex($i);
    }
    $zip->close();
}
t_check('backup.php zip includes data/history/', $brc === 0 && (bool)preg_grep('#^data/history/data/faq/#', $bn), implode(' ', $bo));

/* ---- cleanup --------------------------------------------------------------------------------- */
@unlink($local);
t_check('temporary config.local.php deleted', !is_file($local));
exec('git -C ' . escapeshellarg(T_REPO) . ' status --porcelain --untracked-files=all', $st);
t_check('repo working tree: no test artifacts (same git status as before the test)', $st === $gitBefore, implode(' | ', array_diff($st, $gitBefore)));

t_done('admin-history');
