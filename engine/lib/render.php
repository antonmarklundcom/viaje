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

    public static function dir(): string
    {
        return VJ_SITE . '/cache/pages';
    }

    private static function key(string $path): string
    {
        return self::dir() . '/' . sha1($path) . '.html';
    }

    /**
     * Cached pages older than this are re-rendered anyway. Not needed for form correctness any
     * more (forms on cached pages fetch their stamp from /enviar/sello/), just a bound on staleness.
     */
    private const TTL = 6 * 3600;

    /**
     * Fingerprint of everything that shapes the HTML: engine code and templates, language files,
     * the site's config and theme, and the content itself. A deploy or a file-manager edit changes
     * it, which retires every cached page — so stale HTML can never outlive the code that made it.
     */
    private static function buildSig(): string
    {
        static $sig = null;
        if ($sig !== null) {
            return $sig;
        }
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
        return $sig = hash('sha256', implode('|', $parts) . '|' . Content::signature());
    }

    private static function sigFile(): string
    {
        return self::dir() . '/.sig';
    }

    private static function sigOk(): bool
    {
        $f = self::sigFile();
        return is_file($f) && trim((string)@file_get_contents($f)) === self::buildSig();
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
        $file = self::key($path);
        if (!is_file($file) || !self::sigOk() || (int)@filemtime($file) < time() - self::TTL) {
            return null;
        }
        $html = @file_get_contents($file);
        return $html === false ? null : $html;
    }

    public static function cachePut(string $path, string $html): void
    {
        if (!self::cacheable()) {
            return;
        }
        if (!self::sigOk()) {
            Util::rrmdir(self::dir());   // first write since a deploy or edit: drop the old generation
            Util::mkdirp(self::dir());
            Util::atomicWrite(self::sigFile(), self::buildSig());
        }
        Util::atomicWrite(self::key($path), $html);
    }

    /** Drop the whole page cache and the content index. Called on every write. */
    public static function purge(): void
    {
        Util::rrmdir(self::dir());
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
