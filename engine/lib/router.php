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
        if ($path === '/enviar/sello/' || $path === '/enviar/sello') {
            return self::sello($method);
        }
        if ($path === '/enviar/' || $path === '/enviar') {
            return self::enviar($method, $post);
        }
        if ($path === '/suscribir/confirmar/' || $path === '/suscribir/confirmar') {
            return self::confirmar($query);
        }
        if ($path === '/suscribir/' || $path === '/suscribir') {
            return self::suscribir($method, $post);
        }
        if ($method === 'POST') {
            return self::error(405);
        }
        // IndexNow key file: /<key>.txt must answer with the key itself (tools/indexnow.php).
        $key = (string)Config::v('indexnow.key', '');
        if ($key !== '' && $path === '/' . $key . '.txt') {
            return Response::text($key);
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
        foreach ((array)Config::v('redirect_patterns', []) as $pattern => $target) {
            if (preg_match((string)$pattern, $path) === 1) {
                return Response::redirect(self::withQuery((string)$target, $query), 301);
            }
        }
        if (in_array($path, (array)Config::v('gone', []), true)) {
            return self::error(410);
        }

        // Cached HTML short-circuit.
        $cached = Render::cacheGet($path);
        if ($cached !== null) {
            return self::pageResponse($cached)->withHeader('X-Cache', 'HIT');
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
        // The contact page renders a signed stamp into its form (so it works without JS);
        // caching it would eventually serve an expired one. Every other form fetches its
        // stamp from /enviar/sello/ and is safe to cache.
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
            ->withHeader('Vary', 'Accept-Encoding')
            ->withHeader('Link', '<' . Render::asset('/engine/assets/base.css') . '>; rel=preload; as=style')
            ->withHeader('X-Content-Type-Options', 'nosniff')
            ->withHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
        if (Config::v('staging')) {
            $res = $res->withHeader('X-Robots-Tag', 'noindex, nofollow');
        }
        // Revalidation: a repeat visit costs a 304 instead of the whole document.
        if ($status === 200 && !Config::v('debug') && Render::cacheable()) {
            $etag = '"' . substr(sha1($html), 0, 20) . '"';
            $res  = $res->withHeader('ETag', $etag);
            if (trim((string)($_SERVER['HTTP_IF_NONE_MATCH'] ?? '')) === $etag) {
                $res = new Response(304, '', $res->headers);
            }
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
        // A form on a guide sends the visitor back to that guide; anywhere else it is the contact page.
        $back = Leads::cleanPage((string)($post['page'] ?? ''));
        $contact = $back !== '' ? $back : self::contactPath();
        if ($method !== 'POST') {
            return Response::redirect(self::contactPath(), 302);
        }
        $result = Leads::handle($post);
        $wantsJson = str_contains((string)($_SERVER['HTTP_ACCEPT'] ?? ''), 'application/json')
            || ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') !== '';

        if (!$result['ok'] && isset($result['errors']['_spam'])) {
            // Accept silently so the bot believes it succeeded.
            return $wantsJson
                ? Response::json(['ok' => true])
                : Response::redirect($contact . '?enviado=1#formulario', 303);
        }
        if (!$result['ok']) {
            if ($wantsJson) {
                return Response::json(['ok' => false, 'errors' => $result['errors']], 422);
            }
            $qs = ['error' => '1', 'msg' => implode(' ', $result['errors'])];
            return Response::redirect($contact . '?' . http_build_query($qs) . '#formulario', 303);
        }
        return $wantsJson
            ? Response::json(['ok' => true])
            : Response::redirect($contact . '?enviado=1#formulario', 303);
    }

    /**
     * GET /enviar/sello/ — a fresh signed time stamp for a form's hidden `ts` field. Pages are
     * cached for hours, so forms on them carry none; assets/site.js fetches one on first use.
     */
    private static function sello(string $method): Response
    {
        if ($method === 'POST') {
            return self::error(405);
        }
        return Response::json(['ts' => Leads::stamp()])
            ->withHeader('Cache-Control', 'no-store')
            ->withHeader('X-Robots-Tag', 'noindex');
    }

    /* ---------------------------------------------------------- /suscribir/ */

    private static function suscribir(string $method, array $post): Response
    {
        $back = Leads::cleanPage((string)($post['page'] ?? ''));
        $to   = $back !== '' ? $back : '/';
        if ($method !== 'POST') {
            return Response::redirect($to, 302);
        }
        $result = Leads::subscribe($post);
        $wantsJson = str_contains((string)($_SERVER['HTTP_ACCEPT'] ?? ''), 'application/json')
            || ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') !== '';

        if (!$result['ok'] && isset($result['errors']['_spam'])) {
            return $wantsJson
                ? Response::json(['ok' => true])
                : Response::redirect($to . '?suscrito=1#suscribirse', 303);
        }
        if (!$result['ok']) {
            if ($wantsJson) {
                return Response::json(['ok' => false, 'errors' => $result['errors']], 422);
            }
            $qs = ['suscripcion' => 'error', 'msg' => implode(' ', $result['errors'])];
            return Response::redirect($to . '?' . http_build_query($qs) . '#suscribirse', 303);
        }
        // `suscrito=1`: confirmation email sent; `suscrito=ya`: the address was confirmed before.
        $flag = $result['status'] === 'already' ? 'ya' : '1';
        return $wantsJson
            ? Response::json(['ok' => true, 'status' => $result['status']])
            : Response::redirect($to . '?suscrito=' . $flag . '#suscribirse', 303);
    }

    /** GET /suscribir/confirmar/?e=&t= — the link from the confirmation email. Never cached, noindex. */
    private static function confirmar(array $query): Response
    {
        Render::disableCache();
        $r = Leads::confirm((string)($query['e'] ?? ''), (string)($query['t'] ?? ''));
        [$title, $text] = match ($r['status']) {
            'confirmed' => [I18n::t('nl_confirmed_title'), I18n::t('nl_confirmed_text')],
            'already'   => [I18n::t('nl_confirmed_title'), I18n::t('nl_confirm_again')],
            default     => [I18n::t('nl_confirm_bad_title'), I18n::t('nl_confirm_bad_text')],
        };
        $vars = [
            'page' => [
                'type' => 'page', 'path' => '/suscribir/confirmar/', 'title' => $title, 'text' => $text,
                'ok' => $r['ok'], 'back' => $r['page'] !== '' ? $r['page'] : '/', 'html' => '',
            ],
            'seo' => Seo::head(['title' => $title, 'description' => $text, 'path' => '/', 'noindex' => true, 'suffix' => true]),
        ];
        return Response::html(Render::page('notice', $vars), $r['ok'] ? 200 : 400)
            ->withHeader('Cache-Control', 'no-store')
            ->withHeader('X-Robots-Tag', 'noindex, nofollow')
            ->withHeader('Referrer-Policy', 'no-referrer');
    }

    /** The path of the site-wide FAQ page (layout `faq`), or null. */
    public static function faqPath(): ?string
    {
        foreach (Content::index() as $path => $meta) {
            if ($meta['type'] === 'page' && ($meta['layout'] ?? '') === 'faq' && !$meta['draft']) {
                return (string)$path;
            }
        }
        return null;
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
