<?php
declare(strict_types=1);

/**
 * Request → Response. The evaluation order in dispatch() is the URL contract
 * (spec §5) and later phases must not change it.
 */
final class Router
{
    public static function dispatch(string $method, string $rawPath, array $query = [], array $server = []): Response
    {
        $method = strtoupper($method);
        $qs     = $query === [] ? '' : '?' . http_build_query($query);

        // 1. Host / scheme canonicalisation.
        if ($method === 'GET' || $method === 'HEAD') {
            $canon = self::canonicalRedirect($rawPath . $qs, $server);
            if ($canon !== null) {
                return $canon;
            }
        }

        $path = Util::normalisePath($rawPath);

        // 2. Fixed routes (before slash normalisation: these own their exact form).
        $fixed = self::fixedRoute($method, $path, $query, $server);
        if ($fixed !== null) {
            return $fixed;
        }

        if ($method !== 'GET' && $method !== 'HEAD') {
            return self::error(405, ['Allow' => 'GET, HEAD']);
        }

        // 3. Trailing-slash normalisation.
        if ($path !== '/' && !str_ends_with($path, '/')) {
            if (Util::hasExtension($path)) {
                return self::error(404);
            }
            return Response::redirect($path . '/' . $qs, 301);
        }
        // Collapsed duplicate slashes or resolved dot segments: send the clean form.
        if ($path !== rawurldecode($rawPath)) {
            return Response::redirect($path . $qs, 301);
        }

        // 4. Redirect map and gone list.
        $redirects = (array)Config::v('redirects', []);
        if (isset($redirects[$path])) {
            $target = (string)$redirects[$path];
            return Response::redirect($target, 301);
        }
        if (in_array($path, (array)Config::v('gone', []), true)) {
            return self::error(410);
        }

        // 5. Content.
        $meta = Content::metaByPath($path);
        if ($meta !== null && !$meta['draft']) {
            return self::renderContent($meta);
        }

        // 6. Hubs.
        $hub = self::hubRoute($path);
        if ($hub !== null) {
            return $hub;
        }

        // 7. Real 404.
        return self::error(404);
    }

    /* ------------------------------------------------------------ step 1 */

    private static function canonicalRedirect(string $pathWithQuery, array $server): ?Response
    {
        $host = strtolower((string)($server['HTTP_HOST'] ?? ''));
        if ($host === '') {
            return null;
        }
        $bare = explode(':', $host)[0];
        if (in_array($bare, ['127.0.0.1', 'localhost', '::1', '0.0.0.0'], true)) {
            return null;
        }
        $https = (!empty($server['HTTPS']) && strtolower((string)$server['HTTPS']) !== 'off')
            || strtolower((string)($server['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https';

        $forceHttps = (bool)Config::v('force_https', true);
        $forceHost  = Config::v('force_host');
        $needScheme = $forceHttps && !$https;
        $needHost   = is_string($forceHost) && $forceHost !== '' && $bare !== strtolower($forceHost);

        if (!$needScheme && !$needHost) {
            return null;
        }
        $targetHost = $needHost ? (string)$forceHost : $host;
        $scheme     = ($forceHttps || $https) ? 'https' : 'http';
        return Response::redirect($scheme . '://' . $targetHost . $pathWithQuery, 301);
    }

    /* ------------------------------------------------------------ step 2 */

    private static function fixedRoute(string $method, string $path, array $query, array $server): ?Response
    {
        if ($path === '/sitemap.xml') {
            return Response::xml(Seo::sitemap());
        }
        if (preg_match('#^/wp-sitemap[a-z0-9-]*\.xml$#', $path)) {
            return Response::redirect('/sitemap.xml', 301);
        }
        if ($path === '/robots.txt') {
            return Response::text(Seo::robots());
        }
        if ($path === '/feed') {
            return Response::redirect('/feed/', 301);
        }
        if ($path === '/feed/') {
            return Response::xml(Seo::feed());
        }
        if ($path === '/enviar' ) {
            return Response::redirect('/enviar/', 301);
        }
        if ($path === '/enviar/') {
            if ($method === 'POST') {
                return Leads::handle($_POST, $server);
            }
            return Response::redirect(Leads::contactPath(), 302);
        }
        if ($path === '/admin') {
            return Response::redirect('/admin/', 301);
        }
        if (str_starts_with($path, '/admin/')) {
            return Admin::dispatch($method, $path, $query, $server);
        }
        if (str_starts_with($path, '/preview/')) {
            return self::preview($path, $query);
        }
        return null;
    }

    /* -------------------------------------------------------------- hubs */

    private static function hubRoute(string $path): ?Response
    {
        $hubs = (array)Config::v('hubs', []);

        if (isset($hubs[$path])) {
            return self::renderHub($path, (array)$hubs[$path], 1);
        }
        if (preg_match('#^(/.*/)page/(\d+)/$#', $path, $m) && isset($hubs[$m[1]])) {
            $n = (int)$m[2];
            if ($n <= 1) {
                return Response::redirect($m[1], 301);
            }
            return self::renderHub($m[1], (array)$hubs[$m[1]], $n);
        }
        return null;
    }

    private static function renderHub(string $hubPath, array $hub, int $pageNum): Response
    {
        $type = (string)($hub['type'] ?? '');
        if (!Types::enabled($type)) {
            return self::error(404);
        }
        $perPage = (int)($hub['per_page'] ?? Config::v('per_page', 12));
        $all     = Content::listing($type);
        $paged   = Content::paginate($all, $pageNum, $perPage);

        if ($pageNum > 1 && $pageNum > $paged['pages']) {
            return self::error(404);
        }

        $canonicalPath = $paged['page'] > 1 ? $hubPath . 'page/' . $paged['page'] . '/' : $hubPath;

        $crumbs = [
            ['label' => I18n::t('home'), 'href' => '/'],
            ['label' => (string)($hub['title'] ?? Types::label($type, true)), 'href' => null],
        ];

        $page = [
            'type'        => $type,
            'title'       => (string)($hub['title'] ?? Types::label($type, true)),
            'seo_title'   => (string)($hub['seo_title'] ?? ''),
            'description' => (string)($hub['description'] ?? ''),
            'layout'      => 'hub',
            'hero'        => (string)($hub['hero'] ?? ''),
            'hero_alt'    => (string)($hub['hero_alt'] ?? ''),
            'html'        => '',
        ];
        if (!empty($hub['intro'])) {
            $page['html'] = Markdown::render((string)$hub['intro']);
        }

        $seo = Seo::head([
            'path'        => $canonicalPath,
            'page'        => $page,
            'title'       => $page['title'],
            'description' => $page['description'],
            'og_type'     => 'website',
            'page_num'    => $paged['page'],
            'breadcrumbs' => $crumbs,
            'noindex'     => $paged['page'] > 1,
        ]);

        $html = Render::page('hub', [
            'page'        => $page,
            'seo'         => $seo,
            'hub'         => $hub,
            'hub_path'    => $hubPath,
            'items'       => $paged['items'],
            'paged'       => $paged,
            'breadcrumbs' => $crumbs,
            'path'        => $canonicalPath,
            'show_faq'    => (bool)($hub['show_faq'] ?? false),
        ]);
        return Response::html($html);
    }

    /* ----------------------------------------------------------- content */

    public static function breadcrumbsFor(array $meta): array
    {
        $crumbs = [['label' => I18n::t('home'), 'href' => '/']];
        if ($meta['path'] === '/') {
            return $crumbs;
        }
        $hubPath = Types::hubFor((string)$meta['type']);
        if ($hubPath !== null && $hubPath !== $meta['path']) {
            $hub = (array)Config::v('hubs.' . $hubPath, []);
            $crumbs[] = [
                'label' => (string)($hub['title'] ?? Types::label((string)$meta['type'], true)),
                'href'  => $hubPath,
            ];
        }
        $crumbs[] = ['label' => (string)$meta['title'], 'href' => null];
        return $crumbs;
    }

    public static function renderContent(array $meta, bool $preview = false): Response
    {
        $page = Content::load($meta);
        if ($page === null) {
            return self::error(404);
        }
        $isHome  = $page['path'] === '/';
        $layout  = (string)($page['layout'] ?? 'default');
        $type    = (string)$page['type'];

        $template = Types::template($type);
        if ($type === 'page') {
            $template = match ($layout) {
                'home'    => 'home',
                'faq'     => 'faq',
                'contact' => 'contact',
                'hub'     => 'page',
                default   => 'page',
            };
        }

        $crumbs = self::breadcrumbsFor($meta);

        $seo = Seo::head([
            'path'        => $page['path'],
            'page'        => $page,
            'title'       => $page['title'],
            'description' => $page['description'],
            'is_home'     => $isHome,
            'og_type'     => in_array($type, ['post', 'news'], true) ? 'article' : 'website',
            'breadcrumbs' => $crumbs,
            'noindex'     => $preview,
        ]);

        $html = Render::page($template, [
            'page'        => $page,
            'seo'         => $seo,
            'breadcrumbs' => $crumbs,
            'path'        => $page['path'],
            'is_home'     => $isHome,
            'preview'     => $preview,
        ]);

        $headers = [];
        if ($preview) {
            $headers['X-Robots-Tag'] = 'noindex, nofollow';
            $headers['Cache-Control'] = 'no-store';
        }
        return Response::html($html, 200, $headers);
    }

    /* ---------------------------------------------------------- previews */

    private static function preview(string $path, array $query): Response
    {
        if (!preg_match('#^/preview/([a-z]+)/([a-z0-9-]+)/?$#', $path, $m)) {
            return self::error(404);
        }
        [$all, $type, $slug] = $m;
        if (!Types::enabled($type) || !Util::isSlug($slug)) {
            return self::error(404);
        }
        $token    = (string)($query['t'] ?? '');
        $expected = self::previewToken($type, $slug);
        if ($token === '' || !hash_equals($expected, $token)) {
            return self::error(404);
        }
        $meta = Content::metaBySlug($type, $slug);
        if ($meta === null) {
            return self::error(404);
        }
        return self::renderContent($meta, true);
    }

    public static function previewToken(string $type, string $slug): string
    {
        return hash_hmac('sha256', $type . '/' . $slug, Config::secret());
    }

    public static function previewUrl(string $type, string $slug): string
    {
        return '/preview/' . rawurlencode($type) . '/' . rawurlencode($slug) . '/?t=' . self::previewToken($type, $slug);
    }

    /* -------------------------------------------------------- error pages */

    public static function error(int $status, array $headers = []): Response
    {
        $isGone = $status === 410;
        $page   = [
            'type'        => 'page',
            'title'       => I18n::t($isGone ? '410_title' : '404_title'),
            'description' => I18n::t($isGone ? '410_text' : '404_text'),
            'layout'      => 'default',
            'html'        => '',
            'noindex'     => true,
        ];
        $crumbs = [['label' => I18n::t('home'), 'href' => '/']];
        $seo = Seo::head([
            'path'        => '/',
            'page'        => $page,
            'title'       => $page['title'],
            'description' => $page['description'],
            'noindex'     => true,
            'og_type'     => 'website',
            'breadcrumbs' => $crumbs,
        ]);
        $html = Render::page('404', [
            'page'        => $page,
            'seo'         => $seo,
            'status'      => $status,
            'breadcrumbs' => $crumbs,
            'path'        => '/',
        ]);
        return Response::html($html, $status, $headers + ['X-Robots-Tag' => 'noindex']);
    }
}
