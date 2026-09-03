<?php
declare(strict_types=1);

/**
 * tools/localize-media.php <domain> [--manifest=path] [--force]
 *
 * Downloads every image in an imagery manifest (docs/imagery-manifest.json by
 * default) into sites/<domain>/assets/img/, re-encodes the full-size original
 * as a .jpg (the manifest's "file" name) and generates WebP responsive
 * variants at the same widths engine/lib/images.php's Images::WIDTHS uses
 * (480/960/1600 — that file is frozen this phase, so the widths are kept in
 * sync here by hand, not by including it) so the existing Images::picture()
 * <picture> markup picks the variants up with no engine change. Every
 * occurrence of a manifest image's remote "url" anywhere under
 * sites/<domain>/ (content front matter, content/data/*.json, config.php) is
 * then rewritten to the local "/assets/img/<file>" path.
 *
 * Run from a machine with real network access (Anton's — the CDN is blocked
 * in the build sandbox): php tools/localize-media.php viaje.com.py
 *
 * Safe to re-run: an image whose full-size file already exists on disk is
 * skipped unless --force. A URL with no remaining reference under
 * sites/<domain>/ (already rewritten by an earlier run) is simply not found
 * by the rewrite pass — that is not an error.
 */

const WIDTHS  = [480, 960, 1600]; // must match engine/lib/images.php Images::WIDTHS
const QUALITY = 82;               // must match Images::QUALITY

$repo         = dirname(__DIR__);
$domain       = $argv[1] ?? '';
$manifestPath = $repo . '/docs/imagery-manifest.json';
$force        = false;
foreach (array_slice($argv, 2) as $arg) {
    if (str_starts_with($arg, '--manifest=')) {
        $manifestPath = substr($arg, 11);
    } elseif ($arg === '--force') {
        $force = true;
    }
}
if ($domain === '' || str_starts_with($domain, '--')) {
    fwrite(STDERR, "Usage: php tools/localize-media.php <domain> [--manifest=path] [--force]\n");
    exit(2);
}
$siteDir = $repo . '/sites/' . $domain;
if (!is_dir($siteDir)) {
    fwrite(STDERR, "No such site: sites/$domain\n");
    exit(2);
}
if (!is_file($manifestPath)) {
    fwrite(STDERR, "No such manifest: $manifestPath\n");
    exit(2);
}
if (!function_exists('imagecreatefromstring')) {
    fwrite(STDERR, "GD is required (imagecreatefromstring missing).\n");
    exit(2);
}

$manifest = json_decode((string)file_get_contents($manifestPath), true);
if (!is_array($manifest) || !is_array($manifest['images'] ?? null)) {
    fwrite(STDERR, "Manifest has no \"images\" array: $manifestPath\n");
    exit(2);
}

$imgDir = $siteDir . '/assets/img';
mkdirp($imgDir);

$downloaded = 0;
$skipped    = 0;
$failed     = 0;
$rewrites   = [];   // remote url => local path, for the content pass

foreach ($manifest['images'] as $img) {
    $file = (string)($img['file'] ?? '');
    $url  = (string)($img['url'] ?? '');
    $id   = (string)($img['id'] ?? '?');
    if ($file === '' || $url === '') {
        fwrite(STDERR, "  SKIP  id $id: manifest entry missing \"file\" or \"url\"\n");
        $failed++;
        continue;
    }
    $rewrites[$url] = '/assets/img/' . $file;

    $target = $imgDir . '/' . $file;
    if (is_file($target) && !$force) {
        echo "  SKIP  id $id: $file already downloaded\n";
        $skipped++;
        continue;
    }

    $bytes = fetchBytes($url);
    if ($bytes === null) {
        fwrite(STDERR, "  FAIL  id $id: could not fetch $url\n");
        $failed++;
        continue;
    }
    $src = @imagecreatefromstring($bytes);
    if (!$src instanceof GdImage) {
        fwrite(STDERR, "  FAIL  id $id: GD could not decode the downloaded bytes\n");
        $failed++;
        continue;
    }

    saveJpeg($src, $target);
    $stem   = pathinfo($file, PATHINFO_FILENAME);
    $w      = imagesx($src);
    $h      = imagesy($src);
    $variantsMade = 0;
    foreach (WIDTHS as $width) {
        if ($width > $w && $variantsMade > 0) {
            continue;
        }
        $scaled = $width >= $w ? $src : imagescale($src, $width);
        if ($scaled === false) {
            continue;
        }
        $variant = $imgDir . '/' . $stem . '-' . min($width, $w) . '.webp';
        if (function_exists('imagewebp') && @imagewebp($scaled, $variant, QUALITY)) {
            $variantsMade++;
        }
        if ($scaled !== $src) {
            imagedestroy($scaled);
        }
        if ($width >= $w) {
            break;
        }
    }
    imagedestroy($src);
    echo "  OK    id $id: $file ({$w}x{$h}, $variantsMade webp variant(s))\n";
    $downloaded++;
}

/* ---------------------------------------------------- rewrite references */
$rewritten = 0;
if ($rewrites !== []) {
    foreach (textFiles($siteDir) as $file) {
        $content = (string)file_get_contents($file);
        $new     = strtr($content, $rewrites);
        if ($new !== $content) {
            file_put_contents($file, $new);
            $rewritten++;
        }
    }
}

printf(
    "\n%s — %d downloaded, %d skipped, %d failed; %d file(s) rewritten\n",
    $domain,
    $downloaded,
    $skipped,
    $failed,
    $rewritten
);
exit($failed > 0 ? 1 : 0);

/* ================================================================= helpers */

function mkdirp(string $dir): void
{
    if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) {
        fwrite(STDERR, "Cannot create $dir\n");
        exit(1);
    }
}

/** Fetch bytes from an http(s) URL, a file:// URL, or a plain local path (fixtures/tests). */
function fetchBytes(string $url): ?string
{
    if (str_starts_with($url, 'file://')) {
        $path = substr($url, 7);
        return is_file($path) ? (string)file_get_contents($path) : null;
    }
    if (!preg_match('#^https?://#i', $url)) {
        return is_file($url) ? (string)file_get_contents($url) : null;
    }
    $ctx = stream_context_create(['http' => [
        'method'  => 'GET',
        'timeout' => 30,
        'header'  => "User-Agent: viaje-localize-media/1.0\r\n",
    ]]);
    $bytes = @file_get_contents($url, false, $ctx);
    return $bytes === false ? null : $bytes;
}

function saveJpeg(GdImage $im, string $target): void
{
    $w    = imagesx($im);
    $h    = imagesy($im);
    $flat = imagecreatetruecolor($w, $h);
    imagefill($flat, 0, 0, (int)imagecolorallocate($flat, 255, 255, 255));
    imagecopy($flat, $im, 0, 0, 0, 0, $w, $h);
    @imagejpeg($flat, $target, QUALITY);
    imagedestroy($flat);
}

/** @return list<string> every .md/.json/.php file under $siteDir */
function textFiles(string $siteDir): array
{
    $out = [];
    $it  = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($siteDir, FilesystemIterator::SKIP_DOTS)
    );
    foreach ($it as $f) {
        /** @var SplFileInfo $f */
        if ($f->isFile() && preg_match('/\.(md|json|php)$/', $f->getFilename())) {
            $out[] = $f->getPathname();
        }
    }
    return $out;
}
