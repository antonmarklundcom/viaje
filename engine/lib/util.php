<?php
declare(strict_types=1);

/**
 * Small helpers shared by every layer of the engine.
 * No site-specific values live here.
 */

/** Escape for HTML text/attribute context. Always use this for output. */
function e(mixed $v): string
{
    return htmlspecialchars((string)($v ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** Escape for use inside a single-quoted / double-quoted JS string or JSON blob. */
function ejs(mixed $v): string
{
    return json_encode((string)($v ?? ''), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?: '""';
}

final class Util
{
    private const TRANSLIT = [
        'á' => 'a', 'à' => 'a', 'ä' => 'a', 'â' => 'a', 'ã' => 'a', 'å' => 'a', 'ā' => 'a',
        'é' => 'e', 'è' => 'e', 'ë' => 'e', 'ê' => 'e', 'ē' => 'e',
        'í' => 'i', 'ì' => 'i', 'ï' => 'i', 'î' => 'i', 'ī' => 'i',
        'ó' => 'o', 'ò' => 'o', 'ö' => 'o', 'ô' => 'o', 'õ' => 'o', 'ø' => 'o', 'ō' => 'o',
        'ú' => 'u', 'ù' => 'u', 'ü' => 'u', 'û' => 'u', 'ū' => 'u',
        'ñ' => 'n', 'ç' => 'c', 'ý' => 'y', 'ÿ' => 'y',
        'æ' => 'ae', 'œ' => 'oe', 'ß' => 'ss', 'đ' => 'd', 'ð' => 'd', 'þ' => 'th',
        'ẽ' => 'e', 'ĩ' => 'i', 'ũ' => 'u', 'ỹ' => 'y', "'" => '', '’' => '', '"' => '',
    ];

    /** Slug regex used for content filenames and URL segments. */
    public const SLUG_RE = '/^[a-z0-9]+(?:-[a-z0-9]+)*$/';

    public static function slugify(string $s): string
    {
        $s = mb_strtolower(trim($s), 'UTF-8');
        $s = strtr($s, self::TRANSLIT);
        $s = preg_replace('/[^a-z0-9]+/u', '-', $s) ?? '';
        return trim($s, '-');
    }

    public static function isSlug(string $s): bool
    {
        return $s !== '' && strlen($s) <= 120 && (bool)preg_match(self::SLUG_RE, $s);
    }

    /** True when $p is a safe absolute site path: starts and ends with "/", no traversal. */
    public static function isSafePath(string $p): bool
    {
        if ($p === '' || $p[0] !== '/' || !str_ends_with($p, '/')) {
            return false;
        }
        if (str_contains($p, '..') || str_contains($p, '//') || str_contains($p, "\0") || str_contains($p, '\\')) {
            return false;
        }
        return (bool)preg_match('#^/(?:[A-Za-z0-9._~%-]+/)*$#', $p);
    }

    /** Normalise a request path: ensure leading slash, collapse duplicate slashes, strip traversal. */
    public static function normalisePath(string $path): string
    {
        $path = str_replace("\0", '', $path);
        $path = rawurldecode($path);
        $path = str_replace('\\', '/', $path);
        if ($path === '' || $path[0] !== '/') {
            $path = '/' . $path;
        }
        $path = preg_replace('#/+#', '/', $path) ?? '/';
        $out = [];
        foreach (explode('/', trim($path, '/')) as $seg) {
            if ($seg === '' || $seg === '.') {
                continue;
            }
            if ($seg === '..') {
                array_pop($out);
                continue;
            }
            $out[] = $seg;
        }
        $rebuilt = '/' . implode('/', $out);
        if ($rebuilt !== '/' && str_ends_with($path, '/')) {
            $rebuilt .= '/';
        }
        return $rebuilt;
    }

    /** Last path segment has a file extension (so it must never gain a trailing slash). */
    public static function hasExtension(string $path): bool
    {
        $last = basename(rtrim($path, '/'));
        return (bool)preg_match('/\.[A-Za-z0-9]{1,8}$/', $last);
    }

    public static function absoluteUrl(string $pathOrUrl, string $baseUrl): string
    {
        if (preg_match('#^https?://#i', $pathOrUrl)) {
            return $pathOrUrl;
        }
        if ($pathOrUrl === '') {
            return $baseUrl . '/';
        }
        return rtrim($baseUrl, '/') . '/' . ltrim($pathOrUrl, '/');
    }

    /** Write a file atomically (temp file in the same dir + rename). */
    public static function atomicWrite(string $file, string $contents): bool
    {
        $dir = dirname($file);
        if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) {
            return false;
        }
        $tmp = @tempnam($dir, '.tmp');
        if ($tmp === false) {
            return false;
        }
        if (@file_put_contents($tmp, $contents, LOCK_EX) === false) {
            @unlink($tmp);
            return false;
        }
        @chmod($tmp, 0664);
        if (!@rename($tmp, $file)) {
            @unlink($tmp);
            return false;
        }
        return true;
    }

    public static function rrmdir(string $dir): void
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

    public static function mkdirp(string $dir): bool
    {
        return is_dir($dir) || @mkdir($dir, 0775, true) || is_dir($dir);
    }

    /** Trim to a length on a word boundary, appending an ellipsis when cut. */
    public static function truncate(string $s, int $max, string $suffix = '…'): string
    {
        $s = trim(preg_replace('/\s+/u', ' ', $s) ?? '');
        if (mb_strlen($s, 'UTF-8') <= $max) {
            return $s;
        }
        $cut = mb_substr($s, 0, $max, 'UTF-8');
        $sp  = mb_strrpos($cut, ' ', 0, 'UTF-8');
        if ($sp !== false && $sp > $max * 0.6) {
            $cut = mb_substr($cut, 0, $sp, 'UTF-8');
        }
        return rtrim($cut, " ,.;:-") . $suffix;
    }

    public static function stripMarkdown(string $md): string
    {
        $s = preg_replace('/^---\R.*?\R---\R/su', '', $md) ?? $md;
        $s = preg_replace('/^:::.*$/m', '', $s) ?? $s;
        $s = preg_replace('/!\[[^\]]*\]\([^)]*\)/', '', $s) ?? $s;
        $s = preg_replace('/\[([^\]]*)\]\([^)]*\)/', '$1', $s) ?? $s;
        $s = preg_replace('/[`*_>#~]+/', '', $s) ?? $s;
        return trim(preg_replace('/\s+/u', ' ', $s) ?? '');
    }

    public static function clientIp(): string
    {
        $ip = (string)($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0');
        return filter_var($ip, FILTER_VALIDATE_IP) ? $ip : '0.0.0.0';
    }

    /** Stable, filename-safe key for an IP (used by the rate limiter). */
    public static function ipKey(string $ip): string
    {
        return substr(hash('sha256', $ip), 0, 32);
    }

    public static function readJsonFile(string $file): array
    {
        if (!is_file($file)) {
            return [];
        }
        $data = json_decode((string)file_get_contents($file), true);
        return is_array($data) ? $data : [];
    }

    public static function log(string $message): void
    {
        error_log('[engine] ' . $message);
    }
}

/**
 * A complete HTTP response. The bootstrap emits exactly one of these.
 */
final class Response
{
    /** @param array<string,string> $headers */
    public function __construct(
        public int $status = 200,
        public string $body = '',
        public array $headers = [],
    ) {
    }

    public static function html(string $body, int $status = 200, array $headers = []): self
    {
        return new self($status, $body, $headers + ['Content-Type' => 'text/html; charset=utf-8']);
    }

    public static function text(string $body, int $status = 200, string $type = 'text/plain; charset=utf-8'): self
    {
        return new self($status, $body, ['Content-Type' => $type]);
    }

    public static function xml(string $body, int $status = 200): self
    {
        return new self($status, $body, ['Content-Type' => 'application/xml; charset=utf-8']);
    }

    public static function json(array $data, int $status = 200): self
    {
        return new self(
            $status,
            (string)json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            ['Content-Type' => 'application/json; charset=utf-8']
        );
    }

    public static function redirect(string $location, int $status = 301): self
    {
        return new self($status, '', ['Location' => $location, 'Content-Type' => 'text/html; charset=utf-8']);
    }

    public function withHeader(string $name, string $value): self
    {
        $this->headers[$name] = $value;
        return $this;
    }

    public function emit(): void
    {
        if (!headers_sent()) {
            http_response_code($this->status);
            foreach ($this->headers as $k => $v) {
                header($k . ': ' . $v);
            }
        }
        echo $this->body;
    }
}
