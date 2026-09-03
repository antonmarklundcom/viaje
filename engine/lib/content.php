<?php
declare(strict_types=1);

/**
 * Content index, loaders, listings and pagination (spec §5.1).
 * The index holds front matter only; bodies are parsed on demand.
 */
final class Content
{
    private static ?array $index = null;
    /** @var array<string,array> */
    private static array $loaded = [];
    /** @var list<string> */
    private static array $errors = [];

    public static function indexFile(): string
    {
        return VJ_SITE . '/cache/index.php';
    }

    /** @return array<string,array<string,mixed>> path => meta */
    public static function index(): array
    {
        if (self::$index !== null) {
            return self::$index;
        }
        $sig    = self::signature();
        $cached = null;
        if (!Config::v('debug') && is_file(self::indexFile())) {
            $cached = @include self::indexFile();
        }
        if (is_array($cached) && ($cached['sig'] ?? null) === $sig && isset($cached['paths'])) {
            self::$index  = $cached['paths'];
            self::$errors = $cached['errors'] ?? [];
            return self::$index;
        }
        return self::rebuild($sig);
    }

    /** @return array<string,array<string,mixed>> */
    public static function rebuild(?string $sig = null): array
    {
        $sig ??= self::signature();
        $paths  = [];
        $errors = [];

        foreach (Types::enabledList() as $type) {
            foreach (self::files($type) as $file) {
                $slug = basename($file, '.md');
                if (!Util::isSlug($slug)) {
                    $errors[] = "Invalid filename slug: content/" . Types::folder($type) . "/$slug.md";
                    continue;
                }
                [$fm] = Frontmatter::parseFile((string)@file_get_contents($file));
                $meta = self::meta($type, $slug, $file, $fm);
                $path = $meta['path'];
                if (isset($paths[$path])) {
                    $errors[] = "Duplicate path $path: " . $paths[$path]['slug'] . '.md and ' . $slug . '.md';
                    continue;
                }
                $paths[$path] = $meta;
            }
        }

        foreach ($paths as $path => $meta) {
            if ($meta['draft']) {
                continue;
            }
            if (($meta['description'] ?? '') === '') {
                $errors[] = "Missing description: $path";
            }
            if (($meta['hero'] ?? '') !== '' && ($meta['hero_alt'] ?? '') === '') {
                $errors[] = "Hero without hero_alt: $path";
            }
        }
        foreach (self::unknownFolders() as $folder) {
            $errors[] = "Unknown content folder: content/$folder";
        }

        uasort($paths, static fn(array $a, array $b): int => strcmp((string)$b['date'], (string)$a['date']));

        self::$index  = $paths;
        self::$errors = $errors;
        Util::mkdirp(dirname(self::indexFile()));
        Util::atomicWrite(
            self::indexFile(),
            "<?php\nreturn " . var_export(['sig' => $sig, 'paths' => $paths, 'errors' => $errors], true) . ";\n"
        );
        return $paths;
    }

    /** @return list<string> */
    public static function errors(): array
    {
        self::index();
        return self::$errors;
    }

    public static function purge(): void
    {
        self::$index  = null;
        self::$loaded = [];
        @unlink(self::indexFile());
    }

    /** @return list<string> absolute file paths */
    public static function files(string $type): array
    {
        $dir = Types::dir($type);
        if (!is_dir($dir)) {
            return [];
        }
        $out = glob($dir . '/*.md') ?: [];
        sort($out);
        return $out;
    }

    /** Content folders on disk that no enabled type claims. @return list<string> */
    private static function unknownFolders(): array
    {
        $known = ['data'];
        foreach (Types::enabledList() as $t) {
            $known[] = Types::folder($t);
        }
        $out = [];
        foreach (glob(VJ_SITE . '/content/*', GLOB_ONLYDIR) ?: [] as $dir) {
            $name = basename($dir);
            if (!in_array($name, $known, true)) {
                $out[] = $name;
            }
        }
        return $out;
    }

    /** Cheap change signature over every content file and data collection. */
    private static function signature(): string
    {
        $parts = [];
        foreach (Types::enabledList() as $type) {
            foreach (self::files($type) as $f) {
                $parts[] = $f . ':' . (string)@filemtime($f);
            }
        }
        foreach (glob(VJ_SITE . '/content/data/*.json') ?: [] as $f) {
            $parts[] = $f . ':' . (string)@filemtime($f);
        }
        return hash('sha256', implode('|', $parts));
    }

    /** @return array<string,mixed> */
    private static function meta(string $type, string $slug, string $file, array $fm): array
    {
        $path = isset($fm['path']) && is_string($fm['path']) && $fm['path'] !== ''
            ? self::normaliseOverride((string)$fm['path'])
            : (($type === 'page' && $slug === 'home') ? '/' : Types::pathFor($type, $slug));

        return [
            'type'        => $type,
            'slug'        => $slug,
            'file'        => $file,
            'path'        => $path,
            'title'       => (string)($fm['title'] ?? $slug),
            'seo_title'   => (string)($fm['seo_title'] ?? ''),
            'description' => (string)($fm['description'] ?? ''),
            'date'        => (string)($fm['date'] ?? date('Y-m-d', (int)@filemtime($file))),
            'datetime'    => (string)($fm['datetime'] ?? ''),
            'updated'     => (string)($fm['updated'] ?? ''),
            'author'      => (string)($fm['author'] ?? ''),
            'excerpt'     => (string)($fm['excerpt'] ?? ''),
            'hero'        => (string)($fm['hero'] ?? ''),
            'hero_alt'    => (string)($fm['hero_alt'] ?? ''),
            'tags'        => array_values(array_filter(array_map('strval', (array)($fm['tags'] ?? [])))),
            'region'      => (string)($fm['region'] ?? ''),
            'layout'      => (string)($fm['layout'] ?? 'default'),
            'order'       => (int)($fm['order'] ?? 0),
            'featured'    => (bool)($fm['featured'] ?? false),
            'draft'       => (bool)($fm['draft'] ?? false),
            'noindex'     => (bool)($fm['noindex'] ?? false),
            'canonical'   => (string)($fm['canonical'] ?? ''),
        ];
    }

    private static function normaliseOverride(string $p): string
    {
        $p = '/' . trim($p, '/');
        if ($p !== '/') {
            $p .= '/';
        }
        $p = Util::normalisePath($p);
        return Util::isSafePath($p) ? $p : '/';
    }

    /* ------------------------------------------------------------ loading */

    /** Full page: meta + front matter + rendered body. */
    public static function load(string $file): ?array
    {
        if (isset(self::$loaded[$file])) {
            return self::$loaded[$file];
        }
        if (!is_file($file)) {
            return null;
        }
        [$fm, $body] = Frontmatter::parseFile((string)file_get_contents($file));
        $meta = null;
        foreach (self::index() as $m) {
            if ($m['file'] === $file) {
                $meta = $m;
                break;
            }
        }
        if ($meta === null) {
            return null;
        }
        $page          = array_merge($fm, $meta);
        $page['fm']    = $fm;
        $page['body']  = $body;
        $page['html']  = Markdown::render($body);
        $page['plain'] = Util::stripMarkdown($body);
        if (($page['excerpt'] ?? '') === '') {
            $page['excerpt'] = Util::truncate($page['plain'], 180);
        }
        $words                = max(1, str_word_count(strip_tags($page['plain']), 0, 'áéíóúñüÁÉÍÓÚÑÜ'));
        $page['reading_time'] = max(1, (int)ceil($words / 200));
        $page['headings']     = Markdown::headings($page['html']);
        self::$loaded[$file]  = $page;
        return $page;
    }

    public static function byPath(string $path): ?array
    {
        $meta = self::index()[$path] ?? null;
        return $meta === null ? null : self::load($meta['file']);
    }

    public static function metaByPath(string $path): ?array
    {
        return self::index()[$path] ?? null;
    }

    public static function bySlug(string $type, string $slug): ?array
    {
        foreach (self::index() as $meta) {
            if ($meta['type'] === $type && $meta['slug'] === $slug) {
                return self::load($meta['file']);
            }
        }
        // Drafts are absent from the index only if the file is missing; they are indexed.
        return null;
    }

    /**
     * Published items of a type, newest first (services by `order` then title).
     *
     * @param array{limit?:int,tag?:string,featured?:bool,exclude?:string} $opts
     * @return list<array<string,mixed>>
     */
    public static function listType(string $type, array $opts = []): array
    {
        $items = [];
        foreach (self::index() as $meta) {
            if ($meta['type'] !== $type || $meta['draft'] || $meta['noindex']) {
                continue;
            }
            if (isset($opts['tag']) && !in_array($opts['tag'], $meta['tags'], true)) {
                continue;
            }
            if (!empty($opts['featured']) && !$meta['featured']) {
                continue;
            }
            if (isset($opts['exclude']) && $meta['path'] === $opts['exclude']) {
                continue;
            }
            $items[] = $meta;
        }
        if ($type === 'service') {
            usort($items, static function (array $a, array $b): int {
                $o = ($a['order'] ?: 999) <=> ($b['order'] ?: 999);
                return $o !== 0 ? $o : strcmp((string)$a['title'], (string)$b['title']);
            });
            $order = (array)Config::v('home.services_order', []);
            if ($order) {
                usort($items, static function (array $a, array $b) use ($order): int {
                    $ia = array_search($a['slug'], $order, true);
                    $ib = array_search($b['slug'], $order, true);
                    $ia = $ia === false ? 999 : $ia;
                    $ib = $ib === false ? 999 : $ib;
                    return $ia <=> $ib;
                });
            }
        } else {
            usort($items, static fn(array $a, array $b): int => strcmp((string)$b['date'], (string)$a['date']));
        }
        if (isset($opts['limit'])) {
            $items = array_slice($items, 0, max(0, (int)$opts['limit']));
        }
        return array_values($items);
    }

    /** All published, indexable paths — the sitemap source. @return list<array<string,mixed>> */
    public static function published(): array
    {
        $out = [];
        foreach (self::index() as $meta) {
            if (!$meta['draft'] && !$meta['noindex']) {
                $out[] = $meta;
            }
        }
        return $out;
    }

    /**
     * @param list<mixed> $items
     * @return array{items:list<mixed>,page:int,pages:int,per_page:int,total:int}
     */
    public static function paginate(array $items, int $page, int $perPage): array
    {
        $perPage = max(1, $perPage);
        $total   = count($items);
        $pages   = max(1, (int)ceil($total / $perPage));
        $page    = max(1, min($page, $pages));
        return [
            'items'    => array_slice($items, ($page - 1) * $perPage, $perPage),
            'page'     => $page,
            'pages'    => $pages,
            'per_page' => $perPage,
            'total'    => $total,
        ];
    }

    /* --------------------------------------------------------- collections */

    /** content/data/<name>.json. @return list<array<string,mixed>> */
    public static function data(string $name): array
    {
        if (!Util::isSlug($name)) {
            return [];
        }
        static $cache = [];
        if (!isset($cache[$name])) {
            $rows = Util::readJsonFile(VJ_SITE . '/content/data/' . $name . '.json');
            $cache[$name] = array_values(array_filter($rows, 'is_array'));
        }
        return $cache[$name];
    }

    /** FAQ rows, optionally filtered by tag. @return list<array<string,mixed>> */
    public static function faq(array $tags = []): array
    {
        $rows = self::data('faq');
        if ($tags === []) {
            return $rows;
        }
        return array_values(array_filter($rows, static function (array $r) use ($tags): bool {
            return (bool)array_intersect($tags, array_map('strval', (array)($r['tags'] ?? [])));
        }));
    }
}
