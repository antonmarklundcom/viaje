<?php
declare(strict_types=1);

/**
 * Everything inside <head>, plus sitemap / feed / robots bodies.
 * One JSON-LD script per page, always an @graph.
 */
final class Seo
{
    /**
     * @param array<string,mixed> $ctx keys: path, title, description, page, is_home,
     *                                 page_num, og_type, noindex, hub, extra_graph
     */
    public static function head(array $ctx): string
    {
        $cfg     = Config::get();
        $base    = (string)$cfg['base_url'];
        $page    = is_array($ctx['page'] ?? null) ? $ctx['page'] : [];
        $path    = (string)($ctx['path'] ?? '/');
        $isHome  = (bool)($ctx['is_home'] ?? false);
        $pageNum = (int)($ctx['page_num'] ?? 1);

        $title = self::title($ctx, $page, $isHome, $pageNum);

        $description = trim((string)($ctx['description'] ?? ($page['description'] ?? '')));
        $description = Util::truncate($description, 160, '');

        $canonical = (string)($page['canonical'] ?? '');
        if ($canonical === '' || !preg_match('#^https?://#', $canonical)) {
            $canonical = $base . $path;
        }

        $noindex = (bool)($ctx['noindex'] ?? false)
            || (bool)($page['noindex'] ?? false)
            || (bool)($page['draft'] ?? false)
            || (bool)$cfg['staging'];

        $hero    = (string)($page['hero'] ?? '');
        $ogImage = $hero !== '' ? Util::absoluteUrl($hero, $base) : null;
        if ($ogImage === null && !empty($cfg['default_og_image'])) {
            $ogImage = Util::absoluteUrl((string)$cfg['default_og_image'], $base);
        }
        $ogType = (string)($ctx['og_type'] ?? ($isHome ? 'website' : 'article'));

        $h  = '<title>' . e($title) . "</title>\n";
        if ($description !== '') {
            $h .= '<meta name="description" content="' . e($description) . "\">\n";
        }
        $h .= '<link rel="canonical" href="' . e($canonical) . "\">\n";
        if ($noindex) {
            $h .= '<meta name="robots" content="noindex, nofollow">' . "\n";
        }
        $h .= '<meta property="og:type" content="' . e($ogType) . "\">\n";
        $h .= '<meta property="og:site_name" content="' . e((string)$cfg['site_name']) . "\">\n";
        $h .= '<meta property="og:locale" content="' . e((string)$cfg['locale_og']) . "\">\n";
        $h .= '<meta property="og:url" content="' . e($canonical) . "\">\n";
        $h .= '<meta property="og:title" content="' . e($title) . "\">\n";
        if ($description !== '') {
            $h .= '<meta property="og:description" content="' . e($description) . "\">\n";
        }
        if ($ogImage !== null) {
            $h .= '<meta property="og:image" content="' . e($ogImage) . "\">\n";
            $dims = $hero !== '' ? Images::dimensions($hero) : null;
            if ($dims !== null) {
                $h .= '<meta property="og:image:width" content="' . $dims[0] . "\">\n";
                $h .= '<meta property="og:image:height" content="' . $dims[1] . "\">\n";
            }
        }
        $h .= '<meta name="twitter:card" content="' . ($ogImage !== null ? 'summary_large_image' : 'summary') . "\">\n";
        $h .= '<link rel="alternate" type="application/rss+xml" title="' . e((string)$cfg['site_name']) . '" href="' . e($base) . "/feed/\">\n";
        $h .= '<link rel="icon" href="/assets/favicon.svg" type="image/svg+xml">' . "\n";
        $h .= '<link rel="apple-touch-icon" href="/assets/apple-touch-icon.png">' . "\n";
        $h .= '<meta name="theme-color" content="' . e((string)$cfg['theme_color']) . "\">\n";

        $graph = self::graph($ctx, $page, $canonical, $title, $description);
        $h .= '<script type="application/ld+json">'
            . json_encode(['@context' => 'https://schema.org', '@graph' => $graph], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
            . "</script>\n";

        return $h;
    }

    private static function title(array $ctx, array $page, bool $isHome, int $pageNum): string
    {
        $cfg    = Config::get();
        $suffix = (string)$cfg['title_suffix'];

        $seoTitle = trim((string)($page['seo_title'] ?? ''));
        if ($seoTitle === '') {
            $seoTitle = trim((string)($ctx['seo_title'] ?? ''));
        }
        if ($seoTitle !== '') {
            $title = $seoTitle;
        } else {
            $raw   = trim((string)($ctx['title'] ?? ($page['title'] ?? $cfg['site_name'])));
            $title = $isHome ? $raw : $raw . $suffix;
        }
        if ($pageNum > 1) {
            $title .= I18n::t('page_suffix', ['n' => $pageNum]);
        }
        return $title;
    }

    /* ------------------------------------------------------------ JSON-LD */

    public static function orgId(): string
    {
        return (string)Config::v('base_url') . '#org';
    }

    public static function websiteId(): string
    {
        return (string)Config::v('base_url') . '#website';
    }

    public static function organizationNode(): array
    {
        $cfg  = Config::get();
        $base = (string)$cfg['base_url'];
        $c    = (array)($cfg['contact'] ?? []);
        $node = [
            '@type' => (string)($cfg['schema']['type'] ?? 'Organization'),
            '@id'   => self::orgId(),
            'name'  => (string)$cfg['site_name'],
            'url'   => $base . '/',
        ];
        if (!empty($cfg['schema']['logo'])) {
            $node['logo'] = [
                '@type' => 'ImageObject',
                'url'   => Util::absoluteUrl((string)$cfg['schema']['logo'], $base),
            ];
            $node['image'] = $node['logo']['url'];
        }
        if (!empty($cfg['tagline'])) {
            $node['description'] = (string)$cfg['tagline'];
        }
        if (!empty($c['phone_e164'])) {
            $node['telephone'] = (string)$c['phone_e164'];
        }
        if (!empty($c['email'])) {
            $node['email'] = (string)$c['email'];
        }
        $addr = (array)($c['address'] ?? []);
        if ($addr !== []) {
            $node['address'] = array_filter([
                '@type'           => 'PostalAddress',
                'streetAddress'   => $addr['street'] ?? null,
                'addressLocality' => $addr['city'] ?? null,
                'addressRegion'   => $addr['region'] ?? null,
                'addressCountry'  => $addr['country'] ?? null,
            ], static fn($v) => $v !== null && $v !== '');
        }
        $sameAs = array_values(array_filter(array_map('strval', (array)($cfg['socials'] ?? []))));
        if ($sameAs !== []) {
            $node['sameAs'] = $sameAs;
        }
        if (!empty($cfg['schema']['area_served'])) {
            $node['areaServed'] = (string)$cfg['schema']['area_served'];
        }
        if (!empty($cfg['schema']['founder'])) {
            $node['founder'] = ['@type' => 'Person', 'name' => (string)$cfg['schema']['founder']];
        }
        if (!empty($cfg['schema']['price_range'])) {
            $node['priceRange'] = (string)$cfg['schema']['price_range'];
        }
        if (!empty($c['hours'])) {
            $node['openingHours'] = (string)$c['hours'];
        }
        return $node;
    }

    private static function websiteNode(): array
    {
        $cfg = Config::get();
        return [
            '@type'      => 'WebSite',
            '@id'        => self::websiteId(),
            'url'        => (string)$cfg['base_url'] . '/',
            'name'       => (string)$cfg['site_name'],
            'inLanguage' => (string)$cfg['html_lang'],
            'publisher'  => ['@id' => self::orgId()],
        ];
    }

    private static function graph(array $ctx, array $page, string $canonical, string $title, string $description): array
    {
        $cfg   = Config::get();
        $graph = [self::organizationNode(), self::websiteNode()];

        $type     = (string)($page['type'] ?? ($ctx['schema_type'] ?? 'WebPage'));
        $isHome   = (bool)($ctx['is_home'] ?? false);
        $schema   = 'WebPage';
        if ($page !== [] && Types::exists($type)) {
            $schema = (string)Types::def($type)['schema'];
        }
        if (($page['layout'] ?? '') === 'home' || $isHome) {
            $schema = 'WebPage';
        }

        $node = [
            '@type'      => $schema,
            '@id'        => $canonical . '#page',
            'url'        => $canonical,
            'name'       => $title,
            'inLanguage' => (string)$cfg['html_lang'],
            'isPartOf'   => ['@id' => self::websiteId()],
        ];
        if ($description !== '') {
            $node['description'] = $description;
        }
        if (!empty($page['hero'])) {
            $node['image'] = Util::absoluteUrl((string)$page['hero'], (string)$cfg['base_url']);
        }

        if (in_array($schema, ['BlogPosting', 'NewsArticle'], true)) {
            $node['headline']      = (string)($page['title'] ?? $title);
            $node['datePublished'] = self::isoDate((string)($page['date'] ?? ''), (string)($page['datetime'] ?? ''));
            $node['dateModified']  = self::isoDate((string)($page['updated'] ?? ($page['date'] ?? '')));
            $node['author']        = [
                '@type' => (string)Config::v('author_default.type', 'Organization'),
                'name'  => (string)($page['author'] ?? Config::v('author_default.name', '')),
            ];
            $node['publisher']     = ['@id' => self::orgId()];
            $node['mainEntityOfPage'] = ['@id' => $canonical . '#page'];
        } elseif ($schema === 'Service') {
            $node['provider']    = ['@id' => self::orgId()];
            $node['serviceType'] = (string)($page['title'] ?? $title);
            if (!empty($cfg['schema']['area_served'])) {
                $node['areaServed'] = (string)$cfg['schema']['area_served'];
            }
        } elseif ($schema === 'TouristTrip' || $schema === 'TouristAttraction') {
            $node['provider'] = ['@id' => self::orgId()];
            $facts = (array)($page['facts'] ?? []);
            if (!empty($facts['duration'])) {
                $node['itinerary'] = null;
                unset($node['itinerary']);
                $node['duration'] = (string)$facts['duration'];
            }
            if (!empty($facts['price_from'])) {
                $node['offers'] = array_filter([
                    '@type'         => 'Offer',
                    'price'         => (string)$facts['price_from'],
                    'priceCurrency' => (string)($facts['currency'] ?? 'PYG'),
                    'url'           => $canonical,
                ]);
            }
        }
        $graph[] = $node;

        if (($page['layout'] ?? '') === 'faq') {
            $faq = Content::faq();
            if ($faq !== []) {
                $graph[] = [
                    '@type'      => 'FAQPage',
                    '@id'        => $canonical . '#faq',
                    'mainEntity' => array_values(array_map(static function (array $r): array {
                        return [
                            '@type'          => 'Question',
                            'name'           => (string)($r['q'] ?? ''),
                            'acceptedAnswer' => [
                                '@type' => 'Answer',
                                'text'  => Util::stripMarkdown((string)($r['a'] ?? '')),
                            ],
                        ];
                    }, $faq)),
                ];
            }
        }

        $crumbs = (array)($ctx['breadcrumbs'] ?? []);
        if (count($crumbs) > 1) {
            $graph[] = [
                '@type'           => 'BreadcrumbList',
                '@id'             => $canonical . '#breadcrumb',
                'itemListElement' => array_values(array_map(static function (array $c, int $i): array {
                    return array_filter([
                        '@type'    => 'ListItem',
                        'position' => $i + 1,
                        'name'     => (string)$c['label'],
                        'item'     => isset($c['href']) && $c['href'] !== null
                            ? Util::absoluteUrl((string)$c['href'], (string)Config::v('base_url'))
                            : null,
                    ], static fn($v) => $v !== null);
                }, $crumbs, array_keys($crumbs))),
            ];
        }

        foreach ((array)($ctx['extra_graph'] ?? []) as $extra) {
            if (is_array($extra)) {
                $graph[] = $extra;
            }
        }
        return $graph;
    }

    public static function isoDate(string $date, string $datetime = ''): string
    {
        $src = $datetime !== '' ? $datetime : ($date !== '' ? $date . 'T09:00' : 'now');
        try {
            return (new DateTimeImmutable($src, new DateTimeZone('America/Asuncion')))->format('c');
        } catch (Throwable) {
            return (new DateTimeImmutable('now', new DateTimeZone('America/Asuncion')))->format('c');
        }
    }

    /* --------------------------------------------------- sitemap and feed */

    public static function sitemap(): string
    {
        $cfg  = Config::get();
        $base = (string)$cfg['base_url'];
        $urls = [];

        $home = Content::metaByPath('/');
        $urls['/'] = [
            'lastmod'  => $home ? ($home['updated'] ?: $home['date']) : date('Y-m-d'),
            'priority' => '1.0',
        ];

        foreach (array_keys((array)$cfg['hubs']) as $hubPath) {
            $urls[(string)$hubPath] = ['priority' => '0.8'];
        }

        foreach (Content::allPublished() as $m) {
            if ($m['noindex']) {
                continue;
            }
            $urls[$m['path']] = [
                'lastmod'  => $m['updated'] ?: $m['date'],
                'priority' => $m['type'] === 'page' ? '0.7' : '0.6',
            ];
        }

        $xml  = '<?xml version="1.0" encoding="UTF-8"?>' . "\n"
              . '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
        foreach ($urls as $path => $meta) {
            $xml .= "  <url>\n";
            $xml .= '    <loc>' . e($base . $path) . "</loc>\n";
            if (!empty($meta['lastmod'])) {
                $xml .= '    <lastmod>' . e((string)$meta['lastmod']) . "</lastmod>\n";
            }
            $xml .= '    <priority>' . e((string)$meta['priority']) . "</priority>\n";
            $xml .= "  </url>\n";
        }
        $xml .= '</urlset>' . "\n";
        return $xml;
    }

    public static function feed(int $limit = 20): string
    {
        $cfg  = Config::get();
        $base = (string)$cfg['base_url'];

        $items = [];
        foreach (Content::allPublished() as $m) {
            if ($m['noindex'] || !in_array($m['type'], ['post', 'news', 'trip', 'activity'], true)) {
                continue;
            }
            $items[] = $m;
        }
        usort($items, static fn(array $a, array $b): int => [$b['date'], $b['title']] <=> [$a['date'], $a['title']]);
        $items = array_slice($items, 0, $limit);

        $build = $items ? self::rfc822($items[0]['updated'] ?: $items[0]['date']) : self::rfc822(date('Y-m-d'));

        $xml  = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $xml .= '<rss version="2.0" xmlns:atom="http://www.w3.org/2005/Atom">' . "\n<channel>\n";
        $xml .= '  <title>' . e((string)$cfg['site_name']) . "</title>\n";
        $xml .= '  <link>' . e($base) . "/</link>\n";
        $xml .= '  <description>' . e((string)$cfg['tagline']) . "</description>\n";
        $xml .= '  <language>' . e((string)$cfg['html_lang']) . "</language>\n";
        $xml .= '  <lastBuildDate>' . e($build) . "</lastBuildDate>\n";
        $xml .= '  <atom:link href="' . e($base) . '/feed/" rel="self" type="application/rss+xml"/>' . "\n";
        foreach ($items as $m) {
            $url = $base . $m['path'];
            $xml .= "  <item>\n";
            $xml .= '    <title>' . e($m['title']) . "</title>\n";
            $xml .= '    <link>' . e($url) . "</link>\n";
            $xml .= '    <guid isPermaLink="true">' . e($url) . "</guid>\n";
            $xml .= '    <pubDate>' . e(self::rfc822($m['date'])) . "</pubDate>\n";
            $xml .= '    <description>' . e($m['description'] !== '' ? $m['description'] : $m['excerpt']) . "</description>\n";
            $xml .= "  </item>\n";
        }
        $xml .= "</channel>\n</rss>\n";
        return $xml;
    }

    private static function rfc822(string $date): string
    {
        try {
            return (new DateTimeImmutable(($date ?: 'now') . ' 09:00', new DateTimeZone('America/Asuncion')))->format(DATE_RSS);
        } catch (Throwable) {
            return (new DateTimeImmutable('now'))->format(DATE_RSS);
        }
    }

    public static function robots(): string
    {
        $cfg = Config::get();
        if ($cfg['staging']) {
            return "User-agent: *\nDisallow: /\n";
        }
        $out  = "User-agent: *\n";
        $out .= "Disallow: /admin/\n";
        $out .= "Disallow: /preview/\n";
        $out .= "Disallow: /enviar/\n";
        $out .= "\nSitemap: " . $cfg['base_url'] . "/sitemap.xml\n";
        return $out;
    }
}
