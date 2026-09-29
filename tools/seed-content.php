<?php
declare(strict_types=1);

/**
 * tools/seed-content.php [domain] [--dry-run] [--site-dir=DIR]
 *
 * Copies sites/<domain>/content-seed/ → sites/<domain>/content/, but ONLY when
 * content/ is empty or missing. After the first deploy the server owns content/
 * (the /admin/ panel writes there and a Git deploy must never overwrite it), so:
 *
 *   - empty content/      → seed is copied, exit 0
 *   - non-empty content/  → nothing is touched, exit 0 (safe to run again)
 *   - no content-seed/    → error, exit 1
 *
 * Run it once on the server after the first Git deploy:
 *   php tools/seed-content.php
 */

$repo   = dirname(__DIR__);
$args   = array_slice($argv, 1);
$dry    = in_array('--dry-run', $args, true);
$domain = 'viaje.com.py';
$dir    = null;
foreach ($args as $arg) {
    if (str_starts_with($arg, '--site-dir=')) {
        $dir = rtrim(substr($arg, 11), '/');
    } elseif (!str_starts_with($arg, '--')) {
        $domain = $arg;
    }
}
$siteDir = $dir ?? $repo . '/sites/' . $domain;
if (!is_dir($siteDir)) {
    fwrite(STDERR, "No such site directory: $siteDir\n");
    exit(2);
}

$seed    = $siteDir . '/content-seed';
$content = $siteDir . '/content';
if (!is_dir($seed)) {
    fwrite(STDERR, "No seed to copy: $seed does not exist.\n");
    exit(1);
}

$existing = countFiles($content);
if ($existing > 0) {
    echo "content/ already holds $existing file(s) — leaving it alone (the server owns it).\n";
    exit(0);
}

$files = listFiles($seed);
if ($files === []) {
    fwrite(STDERR, "The seed $seed is empty; nothing to copy.\n");
    exit(1);
}
if ($dry) {
    printf("Dry run: would copy %d file(s) from content-seed/ to content/.\n", count($files));
    exit(0);
}

$copied = 0;
foreach ($files as $rel) {
    $to = $content . '/' . $rel;
    if (!is_dir(dirname($to)) && !@mkdir(dirname($to), 0775, true) && !is_dir(dirname($to))) {
        fwrite(STDERR, "Cannot create " . dirname($to) . "\n");
        exit(1);
    }
    if (!@copy($seed . '/' . $rel, $to)) {
        fwrite(STDERR, "Cannot write $to\n");
        exit(1);
    }
    $copied++;
}

// A stale page cache or content index would keep serving the pre-seed (empty) site.
foreach ([$siteDir . '/cache/pages', $siteDir . '/cache/index.php'] as $stale) {
    is_dir($stale) ? rrmdir($stale) : @unlink($stale);
}
@mkdir($siteDir . '/data', 0775, true);
@file_put_contents($siteDir . '/data/.content-seeded', date('c') . " $copied files\n");

printf("Seeded %d file(s) into %s\n", $copied, $content);
exit(0);

/* ------------------------------------------------------------------ utils */

/** Non-dotfile count under $dir (a lone .gitkeep does not make content/ "non-empty"). */
function countFiles(string $dir): int
{
    return is_dir($dir) ? count(listFiles($dir)) : 0;
}

/** @return list<string> paths relative to $dir, dotfiles skipped */
function listFiles(string $dir): array
{
    $out = [];
    $it  = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS));
    foreach ($it as $f) {
        /** @var SplFileInfo $f */
        if ($f->isFile() && !str_starts_with($f->getFilename(), '.')) {
            $out[] = substr($f->getPathname(), strlen($dir) + 1);
        }
    }
    sort($out);
    return $out;
}

function rrmdir(string $dir): void
{
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
