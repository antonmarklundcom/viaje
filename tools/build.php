<?php
declare(strict_types=1);

/**
 * tools/build.php <domain> [--fresh]
 *
 * Assembles dist/<domain>/ — the document root to upload to Hostinger (spec §0).
 * Safe to re-run: the runtime dirs (content, media, data, cache) and config.local.php
 * belong to the server after cutover and are never overwritten. --fresh rebuilds
 * everything from the repo and is what verify.php and serve.php use.
 */

$repo   = dirname(__DIR__);
$domain = $argv[1] ?? '';
$fresh  = in_array('--fresh', array_slice($argv, 1), true);

if ($domain === '' || str_starts_with($domain, '--')) {
    fwrite(STDERR, "Usage: php tools/build.php <domain> [--fresh]\n");
    exit(2);
}
$siteDir = $repo . '/sites/' . $domain;
if (!is_dir($siteDir)) {
    fwrite(STDERR, "No such site: sites/$domain\n");
    exit(2);
}

$dist = $repo . '/dist/' . $domain;
if ($fresh && is_dir($dist)) {
    rrmdir($dist);
}
mkdirp($dist);

// 1. Front controller.
file_put_contents($dist . '/index.php', "<?php\nrequire __DIR__ . '/engine/bootstrap.php';\n");

// 2. .htaccess from the template.
$cfg  = require $siteDir . '/config.php';
$host = (string)(parse_url((string)($cfg['base_url'] ?? ''), PHP_URL_HOST) ?: $domain);
$tpl  = (string)file_get_contents($repo . '/engine/htaccess.template');
file_put_contents($dist . '/.htaccess', strtr($tpl, ['{{DOMAIN}}' => $domain, '{{HOST}}' => $host]));

// 3. Engine (always a full mirror — it is code, never data).
rrmdir($dist . '/engine');
copyTree($repo . '/engine', $dist . '/engine');

// 4. Site: config, theme, urls, assets always; content only when it is not there yet.
$siteOut = $dist . '/site';
mkdirp($siteOut);
foreach (['config.php', 'config.local.example.php', 'theme.css', 'urls.txt'] as $file) {
    if (is_file($siteDir . '/' . $file)) {
        copy($siteDir . '/' . $file, $siteOut . '/' . $file);
    }
}
rrmdir($siteOut . '/assets');
if (is_dir($siteDir . '/assets')) {
    copyTree($siteDir . '/assets', $siteOut . '/assets');
}
if (is_dir($siteDir . '/content') && (!is_dir($siteOut . '/content') || $fresh)) {
    rrmdir($siteOut . '/content');
    copyTree($siteDir . '/content', $siteOut . '/content');
}
foreach (['media', 'data', 'cache'] as $dir) {
    mkdirp($siteOut . '/' . $dir);
}
if (is_dir($siteDir . '/media')) {
    copyTree($siteDir . '/media', $siteOut . '/media', false);
}

// 5. static/ is served verbatim from the document root (legacy /wp-content/uploads/**).
if (is_dir($siteDir . '/static')) {
    copyTree($siteDir . '/static', $dist, false);
}

// 6. A stale page cache would mask the new build.
rrmdir($siteOut . '/cache/pages');
@unlink($siteOut . '/cache/index.php');

echo "Built dist/$domain\n";
exit(0);

/* ------------------------------------------------------------------ utils */

function mkdirp(string $dir): void
{
    if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) {
        fwrite(STDERR, "Cannot create $dir\n");
        exit(1);
    }
}

function rrmdir(string $dir): void
{
    if (!is_dir($dir)) {
        return;
    }
    $it = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ($it as $f) {
        /** @var SplFileInfo $f */
        $f->isDir() ? @rmdir($f->getPathname()) : @unlink($f->getPathname());
    }
    @rmdir($dir);
}

/** Copy a tree. $overwrite=false keeps files that already exist at the target. */
function copyTree(string $src, string $dst, bool $overwrite = true): void
{
    mkdirp($dst);
    $it = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($src, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST
    );
    foreach ($it as $f) {
        /** @var SplFileInfo $f */
        $rel = substr($f->getPathname(), strlen($src) + 1);
        $to  = $dst . '/' . $rel;
        if ($f->isDir()) {
            mkdirp($to);
            continue;
        }
        if (!$overwrite && is_file($to)) {
            continue;
        }
        mkdirp(dirname($to));
        copy($f->getPathname(), $to);
    }
}
