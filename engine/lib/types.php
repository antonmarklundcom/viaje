<?php
declare(strict_types=1);

/**
 * Content-type registry: folder, template, JSON-LD type and the admin field definitions.
 * Adding a type here is the only place a new type needs registering.
 */
final class Types
{
    /** @return array<string, array<string,mixed>> */
    public static function all(): array
    {
        static $types = null;
        if ($types !== null) {
            return $types;
        }
        $types = [
            'page' => [
                'folder'   => 'pages',
                'template' => 'page',
                'schema'   => 'WebPage',
                'fields'   => [
                    ['name' => 'layout', 'type' => 'select', 'label' => 'layout', 'options' => ['default', 'home', 'faq', 'contact', 'hub']],
                ],
            ],
            'service' => [
                'folder'   => 'services',
                'template' => 'service',
                'schema'   => 'Service',
                'fields'   => [
                    ['name' => 'intro', 'type' => 'textarea', 'label' => 'intro'],
                    ['name' => 'included', 'type' => 'list', 'label' => 'included'],
                    ['name' => 'cta_text', 'type' => 'text', 'label' => 'cta_text'],
                    ['name' => 'order', 'type' => 'number', 'label' => 'order'],
                ],
            ],
            'post' => [
                'folder'   => 'posts',
                'template' => 'post',
                'schema'   => 'BlogPosting',
                'fields'   => [],
            ],
            'news' => [
                'folder'   => 'news',
                'template' => 'post',
                'schema'   => 'NewsArticle',
                'fields'   => [
                    ['name' => 'source_url', 'type' => 'text', 'label' => 'source_url'],
                    ['name' => 'source_name', 'type' => 'text', 'label' => 'source_name'],
                ],
            ],
            'trip' => [
                'folder'   => 'trips',
                'template' => 'post',
                'schema'   => 'TouristTrip',
                'factbox'  => true,
                'fields'   => [
                    ['name' => 'facts', 'type' => 'facts', 'label' => 'facts',
                     'keys' => ['duration', 'price_from', 'currency', 'departure', 'group_size', 'best_season', 'difficulty']],
                    ['name' => 'itinerary', 'type' => 'itinerary', 'label' => 'itinerary'],
                ],
            ],
            'activity' => [
                'folder'   => 'activities',
                'template' => 'post',
                'schema'   => 'TouristAttraction',
                'factbox'  => true,
                'fields'   => [
                    ['name' => 'facts', 'type' => 'facts', 'label' => 'facts',
                     'keys' => ['location', 'duration', 'price_from', 'currency', 'best_season', 'difficulty']],
                    ['name' => 'map_url', 'type' => 'text', 'label' => 'map_url'],
                ],
            ],
        ];
        return $types;
    }

    public static function exists(string $type): bool
    {
        return isset(self::all()[$type]);
    }

    /** Enabled for the current site AND known to the registry. */
    public static function enabled(string $type): bool
    {
        return self::exists($type) && in_array($type, (array)Config::v('types', []), true);
    }

    /** @return list<string> */
    public static function enabledList(): array
    {
        return array_values(array_filter((array)Config::v('types', []), self::exists(...)));
    }

    public static function def(string $type): array
    {
        $all = self::all();
        if (!isset($all[$type])) {
            throw new InvalidArgumentException('Unknown content type: ' . $type);
        }
        return $all[$type];
    }

    public static function folder(string $type): string
    {
        return self::def($type)['folder'];
    }

    /** Absolute directory for a type's content files. Never built from user input without exists(). */
    public static function dir(string $type): string
    {
        return VJ_SITE . '/content/' . self::folder($type);
    }

    /** Default URL path for a slug of this type, from the site's type_paths map. */
    public static function pathFor(string $type, string $slug): string
    {
        $base = (string)Config::v('type_paths.' . $type, '/');
        if (!Util::isSafePath($base)) {
            $base = '/';
        }
        return $base . $slug . '/';
    }

    /** Hub path configured for this type, or null. */
    public static function hubFor(string $type): ?string
    {
        foreach ((array)Config::v('hubs', []) as $path => $hub) {
            if (($hub['type'] ?? null) === $type) {
                return (string)$path;
            }
        }
        return null;
    }

    public static function template(string $type): string
    {
        return self::def($type)['template'];
    }

    public static function hasFactbox(string $type): bool
    {
        return (bool)(self::def($type)['factbox'] ?? false);
    }

    /** Singular label for a type, from lang with per-site override. */
    public static function label(string $type, bool $plural = false): string
    {
        $key      = 'type_' . $type . ($plural ? '_plural' : '');
        $override = Config::v('labels.' . $key);
        if (is_string($override) && $override !== '') {
            return $override;
        }
        return I18n::t($key);
    }

    /**
     * Field definitions the admin editor renders, in order: common, then type-specific.
     * @return list<array<string,mixed>>
     */
    public static function editorFields(string $type): array
    {
        $common = [
            ['name' => 'title', 'type' => 'text', 'label' => 'title', 'required' => true, 'counter' => 60],
            ['name' => 'slug', 'type' => 'text', 'label' => 'slug', 'required' => true],
            ['name' => 'description', 'type' => 'textarea', 'label' => 'description', 'required' => true, 'counter' => 160, 'rows' => 3],
            ['name' => 'seo_title', 'type' => 'text', 'label' => 'seo_title', 'counter' => 60],
            ['name' => 'path', 'type' => 'text', 'label' => 'path', 'help' => 'path_help'],
            ['name' => 'date', 'type' => 'date', 'label' => 'date', 'required' => true],
            ['name' => 'updated', 'type' => 'date', 'label' => 'updated'],
            ['name' => 'author', 'type' => 'text', 'label' => 'author'],
            ['name' => 'hero', 'type' => 'image', 'label' => 'hero'],
            ['name' => 'hero_alt', 'type' => 'text', 'label' => 'hero_alt'],
            ['name' => 'excerpt', 'type' => 'textarea', 'label' => 'excerpt', 'rows' => 2],
            ['name' => 'tags', 'type' => 'list', 'label' => 'tags'],
            ['name' => 'region', 'type' => 'text', 'label' => 'region'],
            ['name' => 'featured', 'type' => 'bool', 'label' => 'featured'],
            ['name' => 'noindex', 'type' => 'bool', 'label' => 'noindex'],
            ['name' => 'canonical', 'type' => 'text', 'label' => 'canonical'],
        ];
        return array_merge($common, self::def($type)['fields']);
    }

    /** Front-matter keys the editor form owns; everything else survives via the Advanced box. */
    public static function managedKeys(string $type): array
    {
        $keys = ['draft'];
        foreach (self::editorFields($type) as $f) {
            if ($f['name'] !== 'slug') {
                $keys[] = $f['name'];
            }
        }
        return $keys;
    }
}
