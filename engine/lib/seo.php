<?php
declare(strict_types=1);

/**
 * <head> construction, JSON-LD @graph, sitemap, feed and robots.txt (spec §6).
 * Nothing here knows a site's name: every value comes from config or content.
 */
final class Seo
{
    /**
     * @param array{
     *   title?:string, description?:string, path?:string, canonical?:string,
     *   noindex?:bool, og_type?:string, image?:string, nodes?:list<array>,
     *   suffix?:bool
     * } $ctx
     */
    public static function head(array $ctx): string
    {
        $cfg   = Config::get();
        $base  = (string)$cfg['base_url'];
        $path  = (string)($ctx['path'] ?? '/');
        $title = trim((string)($ctx['title'] ?? $cfg['site_name']));
        if (($ctx['suffix'] ?? true) && $title !== '') {
            $title .= (string)$cfg['title_suffix'];
        }
        $desc      = Util::truncate((string)($ctx['description'] ?? $cfg['tagline']), 160);
        $canonical = (string)($ctx['canonical'] ?? '') !== ''
            ? (string)$ctx['canonical']
            : $base . $path;
        $noindex = (bool)($ctx['noindex'] ?? false) || (bool)$cfg['staging'];
        $image   = (string)($ctx['image'] ?? '') !== ''
            ? Util::absoluteUrl((string)$ctx['image'], $base)
            : ((string)($cfg['default_og_image'] ?? '') !== '' ? Util::absoluteUrl((string)$cfg['default_og_image'], $base) : '');

        $h  = '<title>' . e($title) . "</title>\n";
        $h .= '<meta name="description" content="' . e($desc) . "\">\n";
        $h .= '<link rel="canonical" href="' . e($canonical) . "\">\n";
        if ($noindex) {
            $h .= '<meta name="robots" content="noindex, nofollow">' . "\n";
        }
        $h .= '<meta property="og:type" content="' . e((string)($ctx['og_type'] ?? 'website')) . "\">\n";
        $h .= '<meta property="og:site_name" content="' . e((string)$cfg['site_name']) . "\">\n";
        $h .= '<meta property="og:locale" content="' . e((string)$cfg['locale_og']) . "\">\n";
        $h .= '<meta property="og:url" content="' . e($canonical) . "\">\n";
        $h .= '<meta property="og:title" content="' . e($title) . "\">\n";
        $h .= '<meta property="og:description" content="' . e($desc) . "\">\n";
        if ($image !== '') {
            $h .= '<meta property="og:image" content="' . e($image) . "\">\n";
            $dim = Images::dimensions($image);
            if ($dim !== null) {
                $h .= '<meta property="og:image:width" content="' . $dim[0] . "\">\n";
                $h .= '<meta property="og:image:height" content="' . $dim[1] . "\">\n";
            }
        }
        $h .= '<meta name="twitter:card" content="' . ($image !== '' ? 'summary_large_image' : 'summary') . "\">\n";
        $h .= '<link rel="alternate" type="application/rss+xml" title="' . e((string)$cfg['site_name']) . '" href="' . e($base) . "/feed/\">\n";
        $h .= '<link rel="icon" href="/assets/favicon.svg" type="image/svg+xml">' . "\n";
        $h .= '<link rel="apple-touch-icon" href="/assets/apple-touch-icon.png">' . "\n";
        $h .= '<meta name="theme-color" content="' . e((string)$cfg['theme_color']) . "\">\n";

        $graph = array_merge([self::orgNode(), self::websiteNode()], $ctx['nodes'] ?? []);
        $json  = json_encode(
            ['@context' => 'https://schema.org', '@graph' => array_values(array_filter($graph))],
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
        );
        $h .= '<script type="application/ld+json">' . str_replace('</', '<\/', (string)$json) . "</script>\n";
        return $h;
    }

    /** Build the head context for a content page. @return array<string,mixed> */
    public static function forPage(array $page): array
    {
        $cfg  = Config::get();
        $base = (string)$cfg['base_url'];
        $type = (string)$page['type'];
        $path = (string)$page['path'];
        $isHome = $path === '/';

        $nodes = [];
        $trail = self::trailFor($page);
        if (count($trail) > 1) {
            $nodes[] = self::breadcrumbNode($trail);
        }
        $node = self::pageNode($page, $trail);
        $offer = $node['@offer'] ?? null;   // a Place cannot carry `offers`: it travels as its own graph node
        unset($node['@offer']);
        $nodes[] = $node;
        if ($offer !== null) {
            $nodes[] = $offer;
        }
        if (($faq = self::faqNode($page)) !== null) {
            $nodes[] = $faq;
        }

        return [
            'title'       => (string)($page['seo_title'] ?: $page['title']),
            'suffix'      => ($page['seo_title'] ?? '') === '' && !$isHome,
            'description' => (string)$page['description'],
            'path'        => $path,
            'canonical'   => (string)($page['canonical'] ?? ''),
            'noindex'     => (bool)$page['noindex'] || (bool)$page['draft'] || !empty($page['preview']),
            'og_type'     => in_array($type, ['post', 'news'], true) ? 'article' : 'website',
            'image'       => (string)($page['hero'] ?? ''),
            'nodes'       => $nodes,
        ];
    }

    /** @return list<array{name:string,path:string}> */
    public static function trailFor(array $page): array
    {
        $trail = [['name' => I18n::t('home'), 'path' => '/']];
        if (($page['path'] ?? '/') === '/') {
            return $trail;
        }
        $hub = Types::hubFor((string)$page['type']);
        if ($hub !== null && $hub !== $page['path']) {
            $hubCfg  = (array)Config::v('hubs.' . $hub, []);
            $trail[] = ['name' => (string)($hubCfg['nav_label'] ?? Types::label((string)$page['type'], true)), 'path' => $hub];
        }
        $trail[] = ['name' => (string)$page['title'], 'path' => (string)$page['path']];
        return $trail;
    }

    /* ------------------------------------------------------------- nodes */

    public static function orgNode(): array
    {
        $cfg  = Config::get();
        $base = (string)$cfg['base_url'];
        $c    = (array)($cfg['contact'] ?? []);
        $s    = (array)($cfg['schema'] ?? []);
        $node = [
            '@type' => (string)($s['type'] ?? 'Organization'),
            '@id'   => $base . '#org',
            'name'  => (string)$cfg['site_name'],
            'url'   => $base . '/',
        ];
        if (!empty($s['logo'])) {
            $node['logo'] = Util::absoluteUrl((string)$s['logo'], $base);
            $node['image'] = $node['logo'];
        }
        if (!empty($c['phone_e164'])) {
            $node['telephone'] = (string)$c['phone_e164'];
        }
        if (!empty($c['email'])) {
            $node['email'] = (string)$c['email'];
        }
        $addr = (array)($c['address'] ?? []);
        if ($addr) {
            $node['address'] = array_filter([
                '@type'           => 'PostalAddress',
                'streetAddress'   => $addr['street'] ?? null,
                'addressLocality' => $addr['city'] ?? null,
                'addressRegion'   => $addr['region'] ?? null,
                'addressCountry'  => $addr['country'] ?? null,
            ]);
        }
        $same = array_values(array_filter(array_map('strval', array_values((array)($cfg['socials'] ?? [])))));
        if ($same) {
            $node['sameAs'] = $same;
        }
        if (!empty($s['area_served'])) {
            $node['areaServed'] = (string)$s['area_served'];
        }
        if (!empty($s['founder'])) {
            $node['founder'] = ['@type' => 'Person', 'name' => (string)$s['founder']];
        }
        if (!empty($s['price_range'])) {
            $node['priceRange'] = (string)$s['price_range'];
        }
        if (!empty($cfg['tagline'])) {
            $node['description'] = (string)$cfg['tagline'];
        }
        return $node;
    }

    public static function websiteNode(): array
    {
        $base = (string)Config::v('base_url');
        return [
            '@type'         => 'WebSite',
            '@id'           => $base . '#website',
            'url'           => $base . '/',
            'name'          => (string)Config::v('site_name'),
            'inLanguage'    => (string)Config::v('html_lang'),
            'publisher'     => ['@id' => $base . '#org'],
        ];
    }

    /** @param list<array{name:string,path:string}> $trail */
    public static function breadcrumbNode(array $trail): array
    {
        $base  = (string)Config::v('base_url');
        $items = [];
        foreach ($trail as $i => $step) {
            $items[] = [
                '@type'    => 'ListItem',
                'position' => $i + 1,
                'name'     => $step['name'],
                'item'     => $base . $step['path'],
            ];
        }
        return ['@type' => 'BreadcrumbList', 'itemListElement' => $items];
    }

    /** @param list<array{name:string,path:string}> $trail */
    private static function pageNode(array $page, array $trail): array
    {
        $base = (string)Config::v('base_url');
        $url  = $base . (string)$page['path'];
        $type = (string)$page['type'];
        $schema = Types::exists($type) ? (string)Types::def($type)['schema'] : 'WebPage';
        if ($type === 'page' && ($page['layout'] ?? '') === 'faq') {
            $schema = 'FAQPage';
        }
        $img = ($page['hero'] ?? '') !== '' ? Util::absoluteUrl((string)$page['hero'], $base) : null;

        $node = array_filter([
            '@type'       => $schema,
            '@id'         => $url . '#' . strtolower($schema),
            'name'        => (string)$page['title'],
            'headline'    => in_array($schema, ['BlogPosting', 'NewsArticle'], true) ? (string)$page['title'] : null,
            'description' => (string)$page['description'] ?: null,
            'url'         => $url,
            'image'       => $img,
            'inLanguage'  => (string)Config::v('html_lang'),
            'isPartOf'    => ['@id' => $base . '#website'],
        ], static fn($v) => $v !== null && $v !== '');

        if (in_array($schema, ['BlogPosting', 'NewsArticle'], true)) {
            $node['datePublished'] = self::isoDate((string)$page['date'], (string)($page['datetime'] ?? ''));
            $node['dateModified']  = self::isoDate((string)($page['updated'] ?: $page['date']), '');
            $node['author']        = self::authorNode($page);
            $node['publisher']     = ['@id' => $base . '#org'];
            $node['mainEntityOfPage'] = ['@type' => 'WebPage', '@id' => $url];
            $keywords = array_values(array_unique(array_filter(array_merge(
                [(string)($page['keyword'] ?? '')],
                array_map('strval', (array)($page['tags'] ?? []))
            ))));
            if ($keywords) {
                $node['keywords'] = implode(', ', $keywords);
            }
            if (($words = str_word_count((string)($page['plain'] ?? ''), 0, 'áéíóúñüÁÉÍÓÚÑÜ')) > 0) {
                $node['wordCount'] = $words;
            }
            unset($node['name']);
        }
        if ($schema === 'WebPage') {
            $node['datePublished'] = self::isoDate((string)$page['date'], (string)($page['datetime'] ?? ''));
            $node['dateModified']  = self::isoDate((string)($page['updated'] ?: $page['date']), '');
        }
        if ($schema === 'Service') {
            $node['provider']    = ['@id' => $base . '#org'];
            $node['serviceType'] = (string)$page['title'];
            if (($area = Config::v('schema.area_served')) !== null) {
                $node['areaServed'] = (string)$area;
            }
        }
        if ($schema === 'FAQPage') {
            $entities = [];
            foreach (Content::faq() as $row) {
                if (($row['q'] ?? '') === '') {
                    continue;
                }
                $entities[] = [
                    '@type'          => 'Question',
                    'name'           => (string)$row['q'],
                    'acceptedAnswer' => ['@type' => 'Answer', 'text' => Util::stripMarkdown((string)($row['a'] ?? ''))],
                ];
            }
            if ($entities) {
                $node['mainEntity'] = $entities;
            }
        }
        if ($schema === 'TouristAttraction') {
            $facts = (array)($page['facts'] ?? []);
            $where = trim((string)($facts['location'] ?? ''));
            if ($where !== '') {
                $node['address'] = array_filter([
                    '@type'           => 'PostalAddress',
                    'addressLocality' => $where,
                    'addressCountry'  => (string)Config::v('contact.address.country', ''),
                ]);
            }
            if (!empty($page['map_url'])) {
                $node['hasMap'] = (string)$page['map_url'];
            }
            if (!empty($page['region'])) {
                $node['containedInPlace'] = ['@type' => 'AdministrativeArea', 'name' => (string)$page['region']];
            }
        }
        if ($schema === 'TouristTrip') {
            $node['provider'] = ['@id' => $base . '#org'];
            $steps = array_values(array_filter((array)($page['itinerary'] ?? []), 'is_array'));
            if ($steps) {
                $items = [];
                foreach ($steps as $i => $step) {
                    $label = trim((string)($step['day'] ?? '') . ' ' . (string)($step['title'] ?? ''));
                    $items[] = array_filter([
                        '@type'       => 'ListItem',
                        'position'    => $i + 1,
                        'name'        => $label !== '' ? $label : I18n::t('day') . ' ' . ($i + 1),
                        'description' => (string)($step['text'] ?? ''),
                    ], static fn($v) => $v !== '');
                }
                $node['itinerary'] = ['@type' => 'ItemList', 'numberOfItems' => count($items), 'itemListElement' => $items];
            }
        }
        if (in_array($schema, ['TouristTrip', 'TouristAttraction'], true) && ($price = self::priceOf((array)($page['facts'] ?? []))) !== null) {
            $offer = [
                '@type'         => 'Offer',
                'price'         => $price['price'],
                'priceCurrency' => $price['currency'],
                'url'           => $url,
                'seller'        => ['@id' => $base . '#org'],
            ];
            if ($schema === 'TouristTrip') {
                $node['offers'] = $offer;   // Trip.offers
            } else {
                // Place has no `offers`: the Offer names the attraction as what it sells.
                $node['@offer'] = $offer + ['itemOffered' => ['@id' => $url . '#touristattraction']];
            }
        }
        if (count($trail) > 1) {
            $node['breadcrumb'] = self::breadcrumbNode($trail);
        }
        return $node;
    }

    /**
     * The byline for a page: name, kind, role, bio and profile URL. Bios come from config['authors']
     * (keyed by the exact author name, which is why this does not go through Config::v's dot paths).
     *
     * @return array{name:string,type:string,role:string,bio:string,url:string}
     */
    public static function author(array $page): array
    {
        $default = (string)Config::v('author_default.name', (string)Config::v('site_name'));
        $name    = (string)($page['author'] ?? '');
        if ($name === '') {
            $name = $default;
        }
        $cfg  = (array)(((array)Config::v('authors', []))[$name] ?? []);
        // The configured default author may be the organisation itself.
        $type = (string)($cfg['type'] ?? (($name === $default)
            ? (string)Config::v('author_default.type', 'Organization')
            : 'Person'));
        return [
            'name' => $name,
            'type' => $type,
            'role' => (string)($cfg['role'] ?? ''),
            'bio'  => (string)($cfg['bio'] ?? ''),
            'url'  => (string)($cfg['url'] ?? ''),
        ];
    }

    private static function authorNode(array $page): array
    {
        $a    = self::author($page);
        $base = (string)Config::v('base_url');
        $node = ['@type' => $a['type'], 'name' => $a['name']];
        if ($a['url'] !== '') {
            $node['url'] = Util::absoluteUrl($a['url'], $base);
        }
        if ($a['bio'] !== '') {
            $node['description'] = $a['bio'];
        }
        if ($a['role'] !== '' && $a['type'] === 'Person') {
            $node['jobTitle'] = $a['role'];
            $node['worksFor'] = ['@id' => $base . '#org'];
        }
        return $node;
    }

    /** FAQPage node for a page's own `faq:` list (never the site-wide /faq/, which pageNode() handles). */
    private static function faqNode(array $page): ?array
    {
        $rows = array_values(array_filter((array)($page['faq'] ?? []), 'is_array'));
        if ($rows === [] || (($page['layout'] ?? '') === 'faq')) {
            return null;
        }
        $entities = [];
        foreach ($rows as $row) {
            if (trim((string)($row['q'] ?? '')) === '' || trim((string)($row['a'] ?? '')) === '') {
                continue;
            }
            $entities[] = [
                '@type'          => 'Question',
                'name'           => (string)$row['q'],
                'acceptedAnswer' => ['@type' => 'Answer', 'text' => Util::stripMarkdown((string)$row['a'])],
            ];
        }
        if ($entities === []) {
            return null;
        }
        $url = (string)Config::v('base_url') . (string)$page['path'];
        return [
            '@type'      => 'FAQPage',
            '@id'        => $url . '#faq',
            'url'        => $url,
            'inLanguage' => (string)Config::v('html_lang'),
            'isPartOf'   => ['@id' => (string)Config::v('base_url') . '#website'],
            'mainEntity' => $entities,
        ];
    }

    /**
     * Price out of a free-text fact ("Desde 150.000 Gs", "USD 45,50"). Null unless both an amount
     * and a currency can be read — "Consultar" and "A medida" must not become an Offer.
     *
     * @return array{price:string,currency:string}|null
     */
    public static function priceOf(array $facts): ?array
    {
        $raw = trim((string)($facts['price_from'] ?? ''));
        if ($raw === '' || !preg_match('/\d[\d.,]*/', $raw, $m)) {
            return null;
        }
        $num = rtrim($m[0], '.,');
        if (preg_match('/^\d{1,3}([.,]\d{3})+$/', $num)) {
            $num = str_replace(['.', ','], '', $num);            // 150.000 → thousands separators
        } else {
            $num = str_replace(',', '.', $num);                  // 45,50 → decimal
        }
        if (!is_numeric($num) || (float)$num <= 0) {
            return null;
        }
        $cur = strtoupper(trim((string)($facts['currency'] ?? '')));
        if ($cur === '') {
            $cur = match (true) {
                (bool)preg_match('/(Gs\.?|₲|guaran|PYG)/iu', $raw) => 'PYG',
                (bool)preg_match('/(US\$|USD|U\$S|\$)/iu', $raw)   => 'USD',
                default                                             => '',
            };
        }
        if (!preg_match('/^[A-Z]{3}$/', $cur)) {
            return null;
        }
        return ['price' => $num, 'currency' => $cur];
    }

    public static function isoDate(string $date, string $datetime = ''): string
    {
        $raw = $datetime !== '' ? $datetime : ($date !== '' ? $date . 'T09:00' : date('Y-m-d\TH:i'));
        try {
            return (new DateTimeImmutable($raw, new DateTimeZone(VJ_TZ)))->format('c');
        } catch (Throwable) {
            return (new DateTimeImmutable('now', new DateTimeZone(VJ_TZ)))->format('c');
        }
    }

    /* ------------------------------------------------- sitemap / feed / robots */

    /** W3C date (YYYY-MM-DD) for sitemap <lastmod>: `updated:` when set, else the publish date. */
    private static function lastmod(array $meta): string
    {
        $d = (string)(($meta['updated'] ?? '') !== '' ? $meta['updated'] : ($meta['date'] ?? ''));
        return preg_match('/^\d{4}-\d{2}-\d{2}/', $d) ? substr($d, 0, 10) : date('Y-m-d');
    }

    public static function sitemap(): string
    {
        $base = (string)Config::v('base_url');
        $urls = [];
        foreach (Content::published() as $meta) {
            $urls[$base . $meta['path']] = self::lastmod($meta);
        }
        foreach ((array)Config::v('hubs', []) as $path => $hub) {
            $type = (string)($hub['type'] ?? '');
            // An empty hub is not worth a sitemap row.
            if (!Types::enabled($type)) {
                continue;
            }
            $items = Content::listType($type);
            if ($items === []) {
                continue;
            }
            // A hub changes when its newest child does.
            $urls[$base . $path] ??= max(array_map(static fn(array $m): string => self::lastmod($m), $items));
        }
        ksort($urls);

        $xml  = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
        foreach ($urls as $loc => $mod) {
            $xml .= "  <url>\n    <loc>" . e($loc) . "</loc>\n    <lastmod>" . e($mod) . "</lastmod>\n  </url>\n";
        }
        return $xml . "</urlset>\n";
    }

    public static function feed(): string
    {
        $cfg   = Config::get();
        $base  = (string)$cfg['base_url'];
        $items = [];
        foreach (['post', 'news'] as $type) {
            if (Types::enabled($type)) {
                $items = array_merge($items, Content::listType($type));
            }
        }
        usort($items, static fn(array $a, array $b): int => strcmp((string)$b['date'], (string)$a['date']));
        $items = array_slice($items, 0, 20);

        $xml  = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $xml .= '<rss version="2.0" xmlns:atom="http://www.w3.org/2005/Atom">' . "\n<channel>\n";
        $xml .= '  <title>' . e((string)$cfg['site_name']) . "</title>\n";
        $xml .= '  <link>' . e($base) . "/</link>\n";
        $xml .= '  <description>' . e((string)$cfg['tagline']) . "</description>\n";
        $xml .= '  <language>' . e((string)$cfg['html_lang']) . "</language>\n";
        $xml .= '  <atom:link href="' . e($base) . '/feed/" rel="self" type="application/rss+xml"/>' . "\n";
        foreach ($items as $meta) {
            $link = $base . $meta['path'];
            $xml .= "  <item>\n";
            $xml .= '    <title>' . e((string)$meta['title']) . "</title>\n";
            $xml .= '    <link>' . e($link) . "</link>\n";
            $xml .= '    <guid isPermaLink="true">' . e($link) . "</guid>\n";
            $xml .= '    <pubDate>' . date(DATE_RSS, strtotime(self::isoDate((string)$meta['date'])) ?: time()) . "</pubDate>\n";
            $xml .= '    <description>' . e((string)($meta['excerpt'] ?: $meta['description'])) . "</description>\n";
            $xml .= "  </item>\n";
        }
        return $xml . "</channel>\n</rss>\n";
    }

    public static function robots(): string
    {
        $base = (string)Config::v('base_url');
        if (Config::v('staging')) {
            return "User-agent: *\nDisallow: /\n";
        }
        return "User-agent: *\n"
            . "Disallow: /admin/\n"
            . "Disallow: /preview/\n"
            . "Disallow: /enviar/\n"
            . "Disallow: /suscribir/\n"
            . "Allow: /\n\n"
            . 'Sitemap: ' . $base . "/sitemap.xml\n";
    }
}
