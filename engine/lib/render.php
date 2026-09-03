<?php
declare(strict_types=1);

/**
 * Template rendering and the full-page cache.
 * Templates are plain PHP files; the variables handed to them are documented in spec §7.
 */
final class Render
{
    /** @var array<string,mixed> shared with every template and partial */
    private static array $shared = [];

    public static function share(array $vars): void
    {
        self::$shared = array_merge(self::$shared, $vars);
    }

    public static function shared(): array
    {
        return self::$shared;
    }

    private static function file(string $name): string
    {
        if (!preg_match('#^[a-z0-9]+(?:[-/][a-z0-9]+)*$#', $name)) {
            throw new InvalidArgumentException('Bad template name: ' . $name);
        }
        $file = VJ_ENGINE . '/templates/' . $name . '.php';
        if (!is_file($file)) {
            throw new RuntimeException('Missing template: ' . $name);
        }
        return $file;
    }

    /** Render a template file in isolation and return its output. */
    public static function raw(string $name, array $vars = []): string
    {
        $__file = self::file($name);
        $__vars = array_merge(self::$shared, $vars);
        return (static function () use ($__file, $__vars): string {
            extract($__vars, EXTR_SKIP);
            ob_start();
            include $__file;
            return (string)ob_get_clean();
        })();
    }

    /** Render a page template, then wrap it in the base layout. */
    public static function page(string $template, array $vars = []): string
    {
        $vars['content'] = self::raw($template, $vars);
        return self::raw('base', $vars);
    }

    /* -------------------------------------------------------------- cache */

    private static function cacheDir(): string
    {
        return VJ_SITE . '/cache/pages';
    }

    private static function cacheFile(string $path): string
    {
        return self::cacheDir() . '/' . sha1($path) . '.html';
    }

    public static function cacheable(string $method, string $path): bool
    {
        if ($method !== 'GET') {
            return false;
        }
        if (Config::v('debug') || Config::v('staging')) {
            return false;
        }
        foreach (['/admin', '/preview/', '/enviar/'] as $prefix) {
            if (str_starts_with($path, $prefix)) {
                return false;
            }
        }
        return true;
    }

    public static function cacheGet(string $path): ?string
    {
        $file = self::cacheFile($path);
        if (!is_file($file)) {
            return null;
        }
        $html = @file_get_contents($file);
        return $html === false ? null : $html;
    }

    public static function cachePut(string $path, string $html): void
    {
        if (!Util::mkdirp(self::cacheDir())) {
            return;
        }
        Util::atomicWrite(self::cacheFile($path), $html);
    }

    /** Drop the whole page cache and the content index. Called on every write. */
    public static function purge(): void
    {
        Util::rrmdir(self::cacheDir());
        @unlink(VJ_SITE . '/cache/index.php');
    }
}

/* -------------------------------------------------------- template helpers */

/** Site-absolute URL for a path (keeps the leading slash form used in markup). */
function url(string $path = '/'): string
{
    if (preg_match('#^(https?:)?//#', $path) || str_starts_with($path, 'mailto:') || str_starts_with($path, 'tel:')) {
        return $path;
    }
    return '/' . ltrim($path, '/');
}

/** Absolute URL, for canonical/OG/schema use. */
function abs_url(string $path = '/'): string
{
    return Util::absoluteUrl($path, (string)Config::v('base_url', ''));
}

/** Cache-busted asset URL. */
function asset(string $path): string
{
    $path = url($path);
    $file = null;
    if (str_starts_with($path, '/engine/')) {
        $file = VJ_ENGINE . substr($path, 7);
    } elseif (str_starts_with($path, '/site/')) {
        $file = VJ_SITE . substr($path, 5);
    } elseif (str_starts_with($path, '/assets/')) {
        $file = VJ_SITE . substr($path, 7);
    } else {
        $file = VJ_ROOT . $path;
    }
    $mtime = @filemtime($file);
    return $mtime ? $path . '?v=' . $mtime : $path;
}

/** Render a partial from engine/templates/partials/. */
function partial(string $name, array $vars = []): void
{
    echo Render::raw('partials/' . $name, $vars);
}

/** Inline SVG icon by name (see templates/partials/icons.php). */
function icon(string $name, string $class = 'icon'): string
{
    static $icons = null;
    if ($icons === null) {
        $icons = require VJ_ENGINE . '/templates/partials/icons.php';
    }
    $svg = $icons[$name] ?? $icons['star'] ?? '';
    if ($svg === '') {
        return '';
    }
    return str_replace('<svg ', '<svg class="' . e($class) . '" ', $svg);
}

/** WhatsApp deep link with a prefilled message. */
function wa_url(?string $text = null): string
{
    $number = (string)Config::v('contact.whatsapp_e164', '');
    $msg    = $text !== null && $text !== '' ? $text : (string)Config::v('contact.whatsapp_default_text', '');
    $url    = 'https://wa.me/' . preg_replace('/\D+/', '', $number);
    return $msg === '' ? $url : $url . '?text=' . rawurlencode($msg);
}

/** Format a YYYY-MM-DD date for display in the site language. */
function fmt_date(string $date): string
{
    if ($date === '') {
        return '';
    }
    try {
        $d = new DateTimeImmutable($date, new DateTimeZone('America/Asuncion'));
    } catch (Throwable) {
        return $date;
    }
    $monthsEs = ['', 'enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'];
    if (I18n::lang() === 'es') {
        return $d->format('j') . ' de ' . $monthsEs[(int)$d->format('n')] . ' de ' . $d->format('Y');
    }
    return $d->format('j F Y');
}
