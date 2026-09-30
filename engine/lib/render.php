<?php
declare(strict_types=1);

/**
 * Template rendering and the full-page cache (spec §7).
 */
final class Render
{
    private static bool $cacheDisabled = false;

    public static function templateFile(string $name): string
    {
        return VJ_ENGINE . '/templates/' . $name . '.php';
    }

    public static function exists(string $name): bool
    {
        return is_file(self::templateFile($name));
    }

    /**
     * Render a template inside the base layout.
     * @param array<string,mixed> $vars
     */
    public static function page(string $template, array $vars): string
    {
        $vars['content_template'] = $template;
        return self::raw('base', $vars);
    }

    /** Render a template file with no layout. @param array<string,mixed> $vars */
    public static function raw(string $template, array $vars): string
    {
        $file = self::templateFile($template);
        if (!is_file($file)) {
            throw new RuntimeException('Missing template: ' . $template);
        }
        $vars['site'] ??= Config::get();
        $vars['page'] ??= [];
        $vars['seo']  ??= '';
        ob_start();
        (static function (string $__file, array $__vars): void {
            extract($__vars, EXTR_SKIP);
            require $__file;
        })($file, $vars);
        return (string)ob_get_clean();
    }

    /** @param array<string,mixed> $vars */
    public static function partial(string $name, array $vars = []): string
    {
        return self::raw('partials/' . $name, $vars);
    }

    /** Cache-busting URL for a file served from the document root. */
    public static function asset(string $path): string
    {
        // Site assets are addressed from the root but live under site/ (see .htaccess).
        $candidates = [(defined('VJ_ROOT') ? VJ_ROOT : '') . $path];
        if (defined('VJ_SITE')) {
            $candidates[] = VJ_SITE . $path;
        }
        foreach ($candidates as $file) {
            $m = @filemtime($file);
            if ($m !== false) {
                return $path . '?v=' . $m;
            }
        }
        return $path;
    }

    /* -------------------------------------------------------------- cache */

    /**
     * Root of the page cache. Pages live one level down, in a directory per generation (named
     * after the build signature), so a new generation never shares files with the old one.
     */
    public static function dir(): string
    {
        return VJ_SITE . '/cache/pages';
    }

    /** Holds the last computed build signature; its mtime says when it was computed. */
    private static function markerFile(): string
    {
        return VJ_SITE . '/cache/.sigcheck';
    }

    private static function genDir(): string
    {
        return self::dir() . '/' . substr(self::buildSig(), 0, 20);
    }

    private static function key(string $path): string
    {
        return self::genDir() . '/' . sha1($path) . '.html';
    }

    /**
     * Cached pages older than this are re-rendered anyway. Not needed for form correctness any
     * more (forms on cached pages fetch their stamp from /enviar/sello/), just a bound on staleness.
     */
    private const TTL = 6 * 3600;

    /** The full fingerprint (a stat of every file below) is recomputed at most this often. */
    private const SIG_EVERY = 60;

    /**
     * Fingerprint of everything that shapes the HTML: engine code and templates, language files,
     * the site's config and theme, and the content itself. A deploy or a file-manager edit changes
     * it, which retires every cached page — so stale HTML can never outlive the code that made it.
     *
     * Stat-ing ~60 files is not free, so the value is kept in cache/.sigcheck and reused while that
     * file is younger than SIG_EVERY seconds; once it is older, one request (the one that gets the
     * lock) recomputes it and the others keep the previous value meanwhile. A deploy therefore
     * shows within a minute; admin writes call purge(), which drops the marker, so they show at once.
     */
    private static function buildSig(): string
    {
        static $sig = null;
        if ($sig !== null) {
            return $sig;
        }
        $marker = self::markerFile();
        $stored = self::storedSig();
        $mtime  = @filemtime($marker);
        if ($stored !== null && $mtime !== false && $mtime > time() - self::SIG_EVERY) {
            return $sig = $stored;
        }
        Util::mkdirp(dirname($marker));
        $lock = @fopen($marker . '.lock', 'c');
        $mine = $lock !== false && flock($lock, LOCK_EX | LOCK_NB);
        if (!$mine && $stored !== null) {
            if ($lock !== false) {
                fclose($lock);
            }
            return $sig = $stored;           // someone else is recomputing right now
        }
        $sig = self::computeSig();
        Util::atomicWrite($marker, $sig);
        if ($lock !== false) {
            if ($mine) {
                flock($lock, LOCK_UN);
            }
            fclose($lock);
        }
        return $sig;
    }

    private static function computeSig(): string
    {
        $files = array_merge(
            glob(VJ_ENGINE . '/lib/*.php') ?: [],
            glob(VJ_ENGINE . '/templates/*.php') ?: [],
            glob(VJ_ENGINE . '/templates/partials/*.php') ?: [],
            glob(VJ_ENGINE . '/lang/*.php') ?: [],
            [VJ_ENGINE . '/assets/base.css', VJ_ENGINE . '/assets/site.js',
             VJ_SITE . '/config.php', VJ_SITE . '/config.local.php', VJ_SITE . '/theme.css']
        );
        $parts = [];
        foreach ($files as $f) {
            $parts[] = $f . ':' . (string)@filemtime($f);
        }
        return hash('sha256', implode('|', $parts) . '|' . Content::signature());
    }

    /** The signature in the marker file, or null. */
    private static function storedSig(): ?string
    {
        $v = trim((string)@file_get_contents(self::markerFile()));
        return preg_match('/^[a-f0-9]{64}$/', $v) ? $v : null;
    }

    public static function cacheable(): bool
    {
        return !self::$cacheDisabled
            && !Config::v('debug')
            && !Config::v('staging')
            && session_status() !== PHP_SESSION_ACTIVE;
    }

    public static function disableCache(): void
    {
        self::$cacheDisabled = true;
    }

    public static function cacheGet(string $path): ?string
    {
        if (!self::cacheable()) {
            return null;
        }
        $file  = self::key($path);
        $mtime = @filemtime($file);
        if ($mtime === false || $mtime < time() - self::TTL) {
            return null;
        }
        $html = @file_get_contents($file);
        return $html === false || $html === '' ? null : $html;
    }

    /**
     * Store a page in the current generation. Safe under concurrency: a generation directory is
     * only started for the signature the marker holds right now, whoever starts it retires the
     * others (rename, then delete), and each page is written to a temp file and renamed in — a
     * reader sees the old file, the new file or nothing, never half a page. A writer that lost a
     * race (its generation was retired under it) simply fails to cache; it never recreates the
     * directory.
     */
    public static function cachePut(string $path, string $html): void
    {
        if (!self::cacheable() || $html === '') {
            return;
        }
        $gen = self::genDir();
        if (!is_dir($gen)) {
            if (self::storedSig() !== self::buildSig()) {
                return;                      // our signature is already outdated: don't start a stale generation
            }
            if (!@mkdir($gen, 0775, true) && !is_dir($gen)) {
                return;
            }
            self::retire(basename($gen));
        }
        $tmp = $gen . '/.tmp-' . bin2hex(random_bytes(6));
        if (@file_put_contents($tmp, $html) === false) {
            @unlink($tmp);
            return;
        }
        @chmod($tmp, 0664);
        if (!@rename($tmp, self::key($path))) {
            @unlink($tmp);
        }
    }

    /** Remove every generation but $keep. Renamed away first, so no writer can land in a half-deleted one. */
    private static function retire(?string $keep): void
    {
        foreach (glob(self::dir() . '/*', GLOB_ONLYDIR | GLOB_NOSORT) ?: [] as $dir) {
            if (basename($dir) === $keep) {
                continue;
            }
            $trash = self::dir() . '/.old-' . bin2hex(random_bytes(6));
            if (@rename($dir, $trash)) {
                Util::rrmdir($trash);
            }
        }
        foreach (glob(self::dir() . '/.old-*', GLOB_ONLYDIR | GLOB_NOSORT) ?: [] as $dir) {
            Util::rrmdir($dir);              // leftovers of an interrupted prune
        }
    }

    /** Drop the whole page cache, the signature marker and the content index. Called on every write. */
    public static function purge(): void
    {
        @unlink(self::markerFile());
        self::retire(null);
        Content::purge();
    }
}

/** Site-absolute URL helper usable from templates. */
function url(string $path = '/'): string
{
    if (preg_match('#^(https?:)?//#i', $path) || str_starts_with($path, 'mailto:') || str_starts_with($path, 'tel:')) {
        return $path;
    }
    return '/' . ltrim($path, '/');
}

/** Absolute URL (canonical host) for a site path. */
function abs_url(string $path = '/'): string
{
    return Util::absoluteUrl($path, (string)Config::v('base_url', ''));
}

function asset(string $path): string
{
    return Render::asset($path);
}

function partial(string $name, array $vars = []): string
{
    return Render::partial($name, $vars);
}
