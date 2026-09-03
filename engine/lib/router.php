<?php
declare(strict_types=1);

/**
 * Request → Response (spec §5). Evaluation order is fixed and load-bearing:
 * canonical host, fixed routes, trailing slash, redirects/gone, content, hubs, 404.
 */
final class Router
{
    /** @param array<string,mixed> $query @param array<string,mixed> $post */
    public static function dispatch(string $method, string $rawPath, array $query = [], array $post = []): Response
    {
        $method = strtoupper($method);
        $path   = Util::normalisePath($rawPath);

        // A response that depends on the query string (?enviado=1, ?error=1) must
        // never be written to, or served from, the path-keyed page cache.
        if ($query !== []) {
            Render::disableCache();
        }

        if ($method !== 'GET' && $method !== 'HEAD' && $method !== 'POST') {
            return self::error(405);
        }

        // 1. Canonical scheme/host.
        $canon = self::canonicalRedirect($path, $query);
        if ($canon !== null) {
            return $canon;
        }

        // 2. Fixed routes.
        if (str_starts_with($path, '/admin')) {
            return Admin::dispatch($method, $path, $query, $post);
        }
        if ($path === '/enviar/' || $path === '/enviar') {
            return self::enviar($method, $post);
        }
        if ($method === 'POST') {
            return self::error(405);
        }
        if (str_starts_with($path, '/preview/')) {
            return self::preview($path, $query);
        }
        if ($path === '/sitemap.xml') {
            return Response::xml(Seo::sitemap());
        }
        if ($path === '/robots.txt') {
            return Response::text(Seo::robots());
        }
        if ($path === '/feed') {
            return Response::redirect('/feed/', 301);
        }
        if ($path === '/feed/' || $path === '/feed.xml') {
            return Response::xml(Seo::feed())->withHeader('Cache-Control', 'public, max-age=900');
        }

        // WordPress search URLs carry no equity and must not resolve (plan §5).
        if (isset($query['s']) && $query['s'] !== '') {
            return self::error(404);
        }

        // Static assets that live under site/ but are addressed from the root.
        $static = self::staticFile($path);
        if ($static !== null) {
            return $static;
        }

        // 3. Trailing slash — but only towards something that exists, so a missing
        // path 404s directly instead of taking a hop to a 404 (spec §5: no chains).
        if ($path !== '/' && !str_ends_with($path, '/') && !Util::hasExtension($path)) {
            if (self::resolves($path . '/')) {
                return Response::redirect(self::withQuery($path . '/', $query), 301);
            }
            return self::error(404);
        }

        // 4. Redirects and gone.
        $redirects = (array)Config::v('redirects', []);
        if (isset($redirects[$path])) {
            return Response::redirect(self::withQuery((string)$redirects[$path], $query), 301);
        }
        if (in_array($path, (array)Config::v('gone', []), true)) {
            return self::error(410);
        }

        // Cached HTML short-circuit.
        $cached = Render::cacheGet($path);
        if ($cached !== null) {
            return Response::html($cached)
                ->withHeader('X-Cache', 'HIT')
                ->withHeader('Cache-Control', 'public, max-age=300');
        }

        // 5. Content.
        $page = Content::byPath($path);
        if ($page !== null) {
            if ($page['draft']) {
                return self::error(404);
            }
            return self::renderContent($page);
        }

        // 6. Hubs, with pagination.
        $hub = self::matchHub($path);
        if ($hub !== null) {
            return $hub;
        }

        return self::error(404);
    }

    /* ------------------------------------------------------------ helpers */

    /** True when $path (slash form) is something the router would answer with. */
    private static function resolves(string $path): bool
    {
        if (array_key_exists($path, (array)Config::v('redirects', []))
            || in_array($path, (array)Config::v('gone', []), true)
            || Content::metaByPath($path) !== null) {
            return true;
        }
        $hubs = (array)Config::v('hubs', []);
        if (isset($hubs[$path])) {
            return true;
        }
        return (bool)preg_match('#^(/.*/)page/\d+/$#', $path, $m) && isset($hubs[$m[1]]);
    }

    private static function canonicalRedirect(string $path, array $query): ?Response
    {
        $host = (string)($_SERVER['HTTP_HOST'] ?? '');
        $host = strtolower(preg_replace('/:\d+$/', '', $host) ?? '');
        if ($host === '' || $host === 'localhost' || $host === '127.0.0.1' || str_starts_with($host, '127.')) {
            return null;
        }
        $https = ($_SERVER['HTTPS'] ?? '') === 'on'
            || ($_SERVER['SERVER_PORT'] ?? '') === '443'
            || strtolower((string)($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https';
        $wantHost  = (string)Config::v('force_host', '');
        $wantHttps = (bool)Config::v('force_https', false);

        $needHost  = $wantHost !== '' && $host !== strtolower($wantHost);
        $needHttps = $wantHttps && !$https;
        if (!$needHost && !$needHttps) {
            return null;
        }
        $target = ($wantHttps ? 'https://' : ($https ? 'https://' : 'http://'))
            . ($wantHost !== '' ? $wantHost : $host)
            . self::withQuery($path, $query);
        return Response::redirect($target, 301);
    }

    public static function withQuery(string $path, array $query): string
    {
        if ($query === []) {
            return $path;
        }
        $qs = http_build_query($query);
        return $qs === '' ? $path : $path . '?' . $qs;
    }

    /** Serve /media/**, /assets/** and /theme.css from the site dir (LiteSpeed rewrites these too). */
    private static function staticFile(string $path): ?Response
    {
        if (!preg_match('#^/(media|assets)/[^\0]+$#', $path) && $path !== '/theme.css') {
            return null;
        }
        if (str_contains($path, '..')) {
            return null;
        }
        $file = VJ_SITE . $path;
        if (!is_file($file)) {
            return null;
        }
        $ext  = strtolower(pathinfo($file, PATHINFO_EXTENSION));
        $mime = match ($ext) {
            'css'          => 'text/css; charset=utf-8',
            'js'           => 'application/javascript; charset=utf-8',
            'svg'          => 'image/svg+xml',
            'png'          => 'image/png',
            'jpg', 'jpeg'  => 'image/jpeg',
            'webp'         => 'image/webp',
            'gif'          => 'image/gif',
            'ico'          => 'image/x-icon',
            'woff2'        => 'font/woff2',
            'pdf'          => 'application/pdf',
            default        => 'application/octet-stream',
        };
        if ($mime === 'application/octet-stream' && !in_array($ext, ['woff', 'ttf'], true)) {
            return null;
        }
        return (new Response(200, (string)file_get_contents($file), ['Content-Type' => $mime]))
            ->withHeader('Cache-Control', 'public, max-age=31536000');
    }

    /** Hub listing and `/hub/page/N/`. */
    private static function matchHub(string $path): ?Response
    {
        $hubs = (array)Config::v('hubs', []);
        $page = 1;
        $hubPath = $path;
        if (preg_match('#^(/.*/)page/(\d+)/$#', $path, $m)) {
            $hubPath = $m[1];
            $page    = (int)$m[2];
            if ($page <= 1) {
                return Response::redirect($hubPath, 301);
            }
        }
        if (!isset($hubs[$hubPath])) {
            return null;
        }
        $hub  = (array)$hubs[$hubPath];
        $type = (string)($hub['type'] ?? '');
        if (!Types::enabled($type)) {
            return null;
        }
        $perPage = (int)($hub['per_page'] ?? Config::v('per_page', 12));
        $items   = Content::listType($type);
        $pager   = Content::paginate($items, $page, $perPage);
        if ($page > $pager['pages']) {
            return self::error(404);
        }

        $canonPath = $pager['page'] > 1 ? $hubPath . 'page/' . $pager['page'] . '/' : $hubPath;
        $title     = (string)($hub['title'] ?? Types::label($type, true));
        $seoTitle  = $title . ($pager['page'] > 1 ? I18n::t('page_suffix', ['n' => $pager['page']]) : '');

        $ctx = [
            'title'       => $seoTitle,
            'suffix'      => false,
            'description' => (string)($hub['description'] ?? ''),
            'path'        => $canonPath,
            'og_type'     => 'website',
            'image'       => (string)($hub['hero'] ?? ''),
            'nodes'       => [
                Seo::breadcrumbNode([
                    ['name' => I18n::t('home'), 'path' => '/'],
                    ['name' => $title, 'path' => $hubPath],
                ]),
                [
                    '@type'       => 'CollectionPage',
                    '@id'         => abs_url($canonPath) . '#collection',
                    'name'        => $title,
                    'description' => (string)($hub['description'] ?? ''),
                    'url'         => abs_url($canonPath),
                    'isPartOf'    => ['@id' => (string)Config::v('base_url') . '#website'],
                ],
            ],
        ];

        $vars = [
            'page' => [
                'type'        => $type,
                'path'        => $canonPath,
                'title'       => $title,
                'description' => (string)($hub['description'] ?? ''),
                'intro'       => (string)($hub['intro'] ?? ''),
                'hub'         => $hub,
                'hub_path'    => $hubPath,
                'is_hub'      => true,
                'html'        => '',
            ],
            'pager' => $pager,
            'items' => $pager['items'],
            'seo'   => Seo::head($ctx),
        ];
        $html = Render::page('hub', $vars);
        Render::cachePut($path, $html);
        return self::pageResponse($html);
    }

    /** Render a content page through its type/layout template. */
    public static function renderContent(array $page, bool $preview = false): Response
    {
        $page['preview'] = $preview;
        $template = Types::template((string)$page['type']);
        if ($page['type'] === 'page') {
            $layout = (string)($page['layout'] ?? 'default');
            $template = match ($layout) {
                'home'    => 'home',
                'faq'     => 'faq',
                'contact' => 'contact',
                'hub'     => 'page',
                default   => 'page',
            };
        }
        if (!Render::exists($template)) {
            $template = 'page';
        }

        $vars = [
            'page'  => $page,
            'trail' => Seo::trailFor($page),
            'seo'   => Seo::head(Seo::forPage($page)),
        ];
        $html = Render::page($template, $vars);
        // The contact page carries a signed, time-limited form stamp; caching it
        // would eventually serve an expired one.
        if (!$preview && $template !== 'contact') {
            Render::cachePut((string)$page['path'], $html);
        }
        $res = self::pageResponse($html);
        return $preview ? $res->withHeader('X-Robots-Tag', 'noindex, nofollow')->withHeader('Cache-Control', 'no-store') : $res;
    }

    private static function pageResponse(string $html, int $status = 200): Response
    {
        $res = Response::html($html, $status)
            ->withHeader('Cache-Control', $status === 200 ? 'public, max-age=300' : 'no-store')
            ->withHeader('X-Content-Type-Options', 'nosniff')
            ->withHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
        if (Config::v('staging')) {
            $res = $res->withHeader('X-Robots-Tag', 'noindex, nofollow');
        }
        return $res;
    }

    /** 404 and 410 share the template; only the status and copy differ. */
    public static function error(int $status): Response
    {
        $isGone = $status === 410;
        $title  = I18n::t($isGone ? '410_title' : '404_title');
        $ctx = [
            'title'       => $title,
            'description' => I18n::t($isGone ? '410_text' : '404_text'),
            'path'        => '/',
            'noindex'     => true,
            'suffix'      => true,
        ];
        $vars = [
            'page' => [
                'type'   => 'page',
                'path'   => '/',
                'title'  => $title,
                'text'   => I18n::t($isGone ? '410_text' : '404_text'),
                'status' => $status,
                'html'   => '',
            ],
            'seo' => Seo::head($ctx),
        ];
        if ($status === 405) {
            return Response::text('Method Not Allowed', 405);
        }
        return self::pageResponse(Render::page('404', $vars), $status);
    }

    /* ------------------------------------------------------------ /enviar/ */

    private static function enviar(string $method, array $post): Response
    {
        $contact = self::contactPath();
        if ($method !== 'POST') {
            return Response::redirect($contact, 302);
        }
        $result = Leads::handle($post);
        $wantsJson = str_contains((string)($_SERVER['HTTP_ACCEPT'] ?? ''), 'application/json')
            || ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') !== '';

        if (!$result['ok'] && isset($result['errors']['_spam'])) {
            // Accept silently so the bot believes it succeeded.
            return $wantsJson
                ? Response::json(['ok' => true])
                : Response::redirect($contact . '?enviado=1', 303);
        }
        if (!$result['ok']) {
            if ($wantsJson) {
                return Response::json(['ok' => false, 'errors' => $result['errors']], 422);
            }
            $qs = ['error' => '1', 'msg' => implode(' ', $result['errors'])];
            return Response::redirect($contact . '?' . http_build_query($qs), 303);
        }
        return $wantsJson
            ? Response::json(['ok' => true])
            : Response::redirect($contact . '?enviado=1', 303);
    }

    /** The path of the page using the contact layout, or /contacto/ style default. */
    public static function contactPath(): string
    {
        foreach (Content::index() as $path => $meta) {
            if ($meta['type'] === 'page' && ($meta['layout'] ?? '') === 'contact' && !$meta['draft']) {
                return (string)$path;
            }
        }
        return '/';
    }

    /* ------------------------------------------------------------ /preview/ */

    private static function preview(string $path, array $query): Response
    {
        if (!preg_match('#^/preview/([a-z]+)/([a-z0-9-]+)/?$#', $path, $m)) {
            return self::error(404);
        }
        [$all, $type, $slug] = $m;
        if (!Types::enabled($type) || !Util::isSlug($slug)) {
            return self::error(404);
        }
        $token = (string)($query['t'] ?? '');
        $want  = hash_hmac('sha256', $type . '/' . $slug, Config::secret());
        if (!hash_equals($want, $token)) {
            return self::error(404);
        }
        $file = Types::dir($type) . '/' . $slug . '.md';
        if (!is_file($file)) {
            return self::error(404);
        }
        Render::disableCache();
        [$fm, $body] = Frontmatter::parseFile((string)file_get_contents($file));
        $page = Content::load($file);
        if ($page === null) {
            // Not in the index (e.g. duplicate path): render from the file directly.
            $page = array_merge($fm, [
                'type' => $type, 'slug' => $slug, 'file' => $file,
                'path' => Types::pathFor($type, $slug),
                'title' => (string)($fm['title'] ?? $slug),
                'description' => (string)($fm['description'] ?? ''),
                'date' => (string)($fm['date'] ?? date('Y-m-d')),
                'updated' => (string)($fm['updated'] ?? ''),
                'draft' => (bool)($fm['draft'] ?? false),
                'noindex' => true, 'canonical' => '',
                'hero' => (string)($fm['hero'] ?? ''), 'hero_alt' => (string)($fm['hero_alt'] ?? ''),
                'tags' => (array)($fm['tags'] ?? []), 'layout' => (string)($fm['layout'] ?? 'default'),
                'html' => Markdown::render($body), 'excerpt' => '', 'headings' => [], 'reading_time' => 1,
            ]);
        }
        return self::renderContent($page, true);
    }
}
