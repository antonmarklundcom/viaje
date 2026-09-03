<?php
declare(strict_types=1);

/**
 * The content index, loaders, listings and pagination.
 * Content lives as markdown files under site/content/<folder>/<slug>.md.
 */
final class Content
{
    private static ?array $index = null;

    /* -------------------------------------------------------------- index */

    /**
     * @return array{items: array<string, array<string,mixed>>, errors: list<string>, built: int}
     */
    public static function index(bool $fresh = false): array
    {
        if (!$fresh && self::$index !== null) {
            return self::$index;
        }
        $cacheFile = VJ_SITE . '/cache/index.php';
        $sig       = self::signature();

        if (!$fresh && !Config::v('debug') && is_file($cacheFile)) {
            $cached = @include $cacheFile;
            if (is_array($cached) && ($cached['sig'] ?? null) === $sig) {
                self::$index = $cached;
                return $cached;
            }
        }

        $built = self::build();
        $built['sig'] = $sig;
        Util::mkdirp(dirname($cacheFile));
        Util::atomicWrite($cacheFile, "<?php\nreturn " . var_export($built, true) . ";\n");
        self::$index = $built;
        return $built;
    }

    /** Cheap fingerprint of every content file, so the cache invalidates on any change. */
    private static function signature(): string
    {
        $parts = [];
        foreach (Types::enabledList() as $type) {
            $dir = Types::dir($type);
            foreach (self::files($dir) as $f) {
                $parts[] = $f . ':' . (string)@filemtime($f);
            }
        }
        $dataDir = VJ_SITE . '/content/data';
        foreach (glob($dataDir . '/*.json') ?: [] as $f) {
            $parts[] = $f . ':' . (string)@filemtime($f);
        }
        sort($parts);
        return hash('sha256', implode('|', $parts));
    }

    /** @return list<string> */
    private static function files(string $dir): array
    {
        if (!is_dir($dir)) {
            return [];
        }
        $out = glob($dir . '/*.md') ?: [];
        sort($out);
        return $out;
    }

    private static function build(): array
    {
        $items  = [];
        $errors = [];

        foreach (Types::enabledList() as $type) {
            foreach (self::files(Types::dir($type)) as $file) {
                $slug = pathinfo($file, PATHINFO_FILENAME);
                if (!Util::isSlug($slug)) {
                    $errors[] = "Invalid filename slug: content/" . Types::folder($type) . "/" . basename($file);
                    continue;
                }
                $raw = (string)@file_get_contents($file);
                [$fm, $body] = Frontmatter::parseFile($raw);

                $path = isset($fm['path']) && is_string($fm['path']) && $fm['path'] !== ''
                    ? $fm['path']
                    : Types::pathFor($type, $slug);
                if (!Util::isSafePath($path)) {
                    $errors[] = "Invalid path override '" . (string)($fm['path'] ?? '') . "' in " . Types::folder($type) . "/$slug.md";
                    continue;
                }

                $meta = self::meta($type, $slug, $file, $path, $fm, $body);

                if (isset($items[$path])) {
                    $errors[] = "Duplicate path $path: " . $items[$path]['rel'] . " and " . $meta['rel'];
                    continue;
                }
                $items[$path] = $meta;
            }
        }

        // Unknown folders under content/ are a configuration smell worth surfacing.
        $known = ['data'];
        foreach (Types::all() as $t => $d) {
            $known[] = $d['folder'];
        }
        foreach (glob(VJ_SITE . '/content/*', GLOB_ONLYDIR) ?: [] as $dir) {
            $name = basename($dir);
            if (!in_array($name, $known, true)) {
                $errors[] = "Unknown content folder: content/$name";
            }
        }

        uasort($items, static function (array $a, array $b): int {
            return [$b['date'], $a['title']] <=> [$a['date'], $b['title']];
        });

        return ['items' => $items, 'errors' => $errors, 'built' => time()];
    }

    private static function meta(string $type, string $slug, string $file, string $path, array $fm, string $body): array
    {
        $title   = trim((string)($fm['title'] ?? $slug));
        $excerpt = trim((string)($fm['excerpt'] ?? ''));
        if ($excerpt === '') {
            $first   = preg_split('/\n\s*\n/', trim($body))[0] ?? '';
            $excerpt = Util::truncate(Util::stripMarkdown($first), 180);
        }
        $date = self::normDate((string)($fm['date'] ?? ''), $file);

        return [
            'type'        => $type,
            'slug'        => $slug,
            'file'        => $file,
            'rel'         => 'content/' . Types::folder($type) . '/' . $slug . '.md',
            'path'        => $path,
            'title'       => $title,
            'seo_title'   => (string)($fm['seo_title'] ?? ''),
            'description' => trim((string)($fm['description'] ?? '')),
            'date'        => $date,
            'datetime'    => (string)($fm['datetime'] ?? ''),
            'updated'     => self::normDate((string)($fm['updated'] ?? ''), null) ?: $date,
            'author'      => (string)($fm['author'] ?? ''),
            'hero'        => (string)($fm['hero'] ?? ''),
            'hero_alt'    => (string)($fm['hero_alt'] ?? ''),
            'excerpt'     => $excerpt,
            'tags'        => array_values(array_filter(array_map('strval', (array)($fm['tags'] ?? [])))),
            'region'      => (string)($fm['region'] ?? ''),
            'draft'       => (bool)($fm['draft'] ?? false),
            'noindex'     => (bool)($fm['noindex'] ?? false),
            'featured'    => (bool)($fm['featured'] ?? false),
            'order'       => (int)($fm['order'] ?? 999),
            'layout'      => (string)($fm['layout'] ?? 'default'),
            'canonical'   => (string)($fm['canonical'] ?? ''),
            'words'       => str_word_count(Util::stripMarkdown($body)),
        ];
    }

    private static function normDate(string $v, ?string $fallbackFile): string
    {
        $v = trim($v);
        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})/', $v, $m)) {
            return $m[1] . '-' . $m[2] . '-' . $m[3];
        }
        if ($fallbackFile !== null && is_file($fallbackFile)) {
            return date('Y-m-d', (int)filemtime($fallbackFile));
        }
        return '';
    }

    /* ------------------------------------------------------------- lookups */

    public static function metaByPath(string $path): ?array
    {
        return self::index()['items'][$path] ?? null;
    }

    public static function metaBySlug(string $type, string $slug): ?array
    {
        foreach (self::index()['items'] as $m) {
            if ($m['type'] === $type && $m['slug'] === $slug) {
                return $m;
            }
        }
        return null;
    }

    public static function fileFor(string $type, string $slug): ?string
    {
        if (!Types::enabled($type) || !Util::isSlug($slug)) {
            return null;
        }
        $file = Types::dir($type) . '/' . $slug . '.md';
        return is_file($file) ? $file : null;
    }

    /** Full page: metadata + raw front matter + rendered body. */
    public static function load(array $meta): ?array
    {
        if (!is_file($meta['file'])) {
            return null;
        }
        $raw = (string)@file_get_contents($meta['file']);
        [$fm, $body] = Frontmatter::parseFile($raw);

        $page = array_merge($fm, $meta);
        $page['fm']           = $fm;
        $page['body']         = $body;
        $page['html']         = Markdown::render($body);
        $page['reading_time'] = Markdown::readingTime($body);
        if (($page['author'] ?? '') === '') {
            $page['author'] = (string)Config::v('author_default.name', '');
        }
        return $page;
    }

    public static function loadByPath(string $path): ?array
    {
        $meta = self::metaByPath($path);
        return $meta === null ? null : self::load($meta);
    }

    /* ------------------------------------------------------------ listings */

    /**
     * Published items of a type, newest first (services by `order`, then title).
     * @return list<array<string,mixed>>
     */
    public static function listing(string $type, array $opts = []): array
    {
        $items = [];
        foreach (self::index()['items'] as $m) {
            if ($m['type'] !== $type) {
                continue;
            }
            if ($m['draft'] && empty($opts['include_drafts'])) {
                continue;
            }
            if (!empty($opts['tag']) && !in_array($opts['tag'], $m['tags'], true)) {
                continue;
            }
            if (!empty($opts['exclude_path']) && $m['path'] === $opts['exclude_path']) {
                continue;
            }
            if (!empty($opts['featured']) && !$m['featured']) {
                continue;
            }
            $items[] = $m;
        }
        if ($type === 'service' || !empty($opts['by_order'])) {
            usort($items, static fn(array $a, array $b): int => [$a['order'], $a['title']] <=> [$b['order'], $b['title']]);
        } else {
            usort($items, static fn(array $a, array $b): int => [$b['date'], $b['title']] <=> [$a['date'], $a['title']]);
        }
        if (!empty($opts['order'])) {
            $items = self::applyManualOrder($items, (array)$opts['order']);
        }
        if (!empty($opts['limit'])) {
            $items = array_slice($items, 0, (int)$opts['limit']);
        }
        return $items;
    }

    private static function applyManualOrder(array $items, array $slugs): array
    {
        $bySlug = [];
        foreach ($items as $i) {
            $bySlug[$i['slug']] = $i;
        }
        $out = [];
        foreach ($slugs as $s) {
            if (isset($bySlug[(string)$s])) {
                $out[] = $bySlug[(string)$s];
                unset($bySlug[(string)$s]);
            }
        }
        return array_merge($out, array_values($bySlug));
    }

    /** All published items across every enabled type (sitemap, feed). */
    public static function allPublished(): array
    {
        $out = [];
        foreach (self::index()['items'] as $m) {
            if (!$m['draft']) {
                $out[] = $m;
            }
        }
        return $out;
    }

    /**
     * @return array{items: list<array<string,mixed>>, page:int, pages:int, total:int, per_page:int}
     */
    public static function paginate(array $items, int $page, int $perPage): array
    {
        $total   = count($items);
        $perPage = max(1, $perPage);
        $pages   = max(1, (int)ceil($total / $perPage));
        $page    = max(1, min($page, $pages));
        return [
            'items'    => array_slice($items, ($page - 1) * $perPage, $perPage),
            'page'     => $page,
            'pages'    => $pages,
            'total'    => $total,
            'per_page' => $perPage,
        ];
    }

    /* ---------------------------------------------------------- data files */

    /** content/data/<name>.json — always a list of rows. */
    public static function data(string $name): array
    {
        if (!preg_match('/^[a-z][a-z0-9-]{0,40}$/', $name)) {
            return [];
        }
        $rows = Util::readJsonFile(VJ_SITE . '/content/data/' . $name . '.json');
        return array_values(array_filter($rows, 'is_array'));
    }

    /** FAQ rows, optionally filtered to a tag set. */
    public static function faq(array $tags = [], int $limit = 0): array
    {
        $rows = self::data('faq');
        if ($tags !== []) {
            $rows = array_values(array_filter($rows, static function (array $r) use ($tags): bool {
                return array_intersect($tags, array_map('strval', (array)($r['tags'] ?? []))) !== [];
            }));
        }
        return $limit > 0 ? array_slice($rows, 0, $limit) : $rows;
    }

    /** Names of the editable data collections that exist for this site. */
    public static function dataNames(): array
    {
        $out = [];
        foreach (glob(VJ_SITE . '/content/data/*.json') ?: [] as $f) {
            $n = pathinfo($f, PATHINFO_FILENAME);
            if (preg_match('/^[a-z][a-z0-9-]{0,40}$/', $n)) {
                $out[] = $n;
            }
        }
        sort($out);
        return $out;
    }
}
