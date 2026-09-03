<?php
declare(strict_types=1);

/**
 * The password-protected publishing UI (spec §8). One owner, one password,
 * no user table. Every write goes through validate() and purges the cache.
 */
final class Admin
{
    private const SESSION_NAME = 'vjsess';
    private const IDLE         = 14400;   // 4 h
    private const LOGIN_MAX    = 5;
    private const LOGIN_WIN    = 900;     // 15 min

    /** Row schemas for content/data/<name>.json. */
    public const DATA_SCHEMAS = [
        'faq'          => ['q' => 'text', 'a' => 'textarea', 'tags' => 'list'],
        'testimonials' => ['name' => 'text', 'text' => 'textarea', 'trip' => 'text', 'rating' => 'number'],
        'team'         => ['name' => 'text', 'role' => 'text', 'photo' => 'text', 'bio' => 'textarea'],
        'gallery'      => ['src' => 'text', 'alt' => 'text', 'caption' => 'text', 'category' => 'text'],
    ];

    /** @param array<string,mixed> $query @param array<string,mixed> $post */
    public static function dispatch(string $method, string $path, array $query, array $post): Response
    {
        if ($path === '/admin') {
            return Response::redirect('/admin/', 301);
        }
        if (!str_starts_with($path, '/admin/')) {
            return Router::error(404);
        }
        self::session();

        $route = trim(substr($path, strlen('/admin/')), '/');
        $seg   = $route === '' ? [] : explode('/', $route);

        if ($method === 'POST' && ($seg[0] ?? '') === 'login') {
            return self::doLogin($post);
        }
        if (!self::authed()) {
            if (!Config::adminConfigured()) {
                return self::view('setup', ['title' => I18n::t('admin_setup')]);
            }
            return self::view('login', ['title' => I18n::t('admin_login'), 'error' => (string)($query['e'] ?? '')]);
        }
        if ($method === 'POST' && !self::checkCsrf($post)) {
            return self::view('message', ['title' => I18n::t('admin'), 'message' => I18n::t('admin_csrf')], 403);
        }

        return match (true) {
            $seg === [] || $seg === ['dashboard'] => self::dashboard(),
            ($seg[0] ?? '') === 'logout'          => self::doLogout(),
            ($seg[0] ?? '') === 'content'         => self::content($method, array_slice($seg, 1), $query, $post),
            ($seg[0] ?? '') === 'media'           => self::media($method, $post),
            ($seg[0] ?? '') === 'data'            => self::data($method, $seg[1] ?? '', $post),
            ($seg[0] ?? '') === 'redirects'       => self::redirects($query),
            ($seg[0] ?? '') === 'export'          => self::export($method),
            ($seg[0] ?? '') === 'preview-md'      => Response::html(Markdown::render((string)($post['body'] ?? '')))
                                                        ->withHeader('Cache-Control', 'no-store'),
            default                               => self::view('message', ['title' => '404', 'message' => I18n::t('404_title')], 404),
        };
    }

    /* ---------------------------------------------------------------- auth */

    private static function session(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }
        $https = ($_SERVER['HTTPS'] ?? '') === 'on'
            || strtolower((string)($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https';
        session_name(self::SESSION_NAME);
        @session_set_cookie_params([
            'lifetime' => 0, 'path' => '/', 'httponly' => true,
            'secure'   => $https, 'samesite' => 'Strict',
        ]);
        @session_start();
    }

    public static function authed(): bool
    {
        if (empty($_SESSION['vj_auth'])) {
            return false;
        }
        if (time() - (int)($_SESSION['vj_seen'] ?? 0) > self::IDLE) {
            $_SESSION = [];
            return false;
        }
        $_SESSION['vj_seen'] = time();
        return true;
    }

    public static function csrf(): string
    {
        if (empty($_SESSION['vj_csrf'])) {
            $_SESSION['vj_csrf'] = bin2hex(random_bytes(16));
        }
        return (string)$_SESSION['vj_csrf'];
    }

    private static function checkCsrf(array $post): bool
    {
        $sent = (string)($post['csrf'] ?? '');
        return $sent !== '' && hash_equals(self::csrf(), $sent);
    }

    private static function doLogin(array $post): Response
    {
        if (!Config::adminConfigured()) {
            return self::view('setup', ['title' => I18n::t('admin_setup')]);
        }
        if (!self::loginRateOk()) {
            return self::view('login', ['title' => I18n::t('admin_login'), 'error' => I18n::t('admin_locked')], 429);
        }
        $hash = (string)Config::v('admin_password_hash', '');
        if (!password_verify((string)($post['password'] ?? ''), $hash)) {
            self::loginFail();
            return self::view('login', ['title' => I18n::t('admin_login'), 'error' => I18n::t('admin_bad_login')], 401);
        }
        session_regenerate_id(true);
        $_SESSION['vj_auth'] = true;
        $_SESSION['vj_seen'] = time();
        self::loginReset();
        return Response::redirect('/admin/dashboard', 303);
    }

    private static function doLogout(): Response
    {
        $_SESSION = [];
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }
        return Response::redirect('/admin/', 303);
    }

    private static function loginFile(): string
    {
        $dir = VJ_SITE . '/cache/ratelimit';
        Util::mkdirp($dir);
        return $dir . '/login-' . Util::ipKey(Util::clientIp()) . '.json';
    }

    private static function loginRateOk(): bool
    {
        $hits = array_filter(
            array_map('intval', Util::readJsonFile(self::loginFile())),
            static fn(int $t): bool => $t > time() - self::LOGIN_WIN
        );
        return count($hits) < self::LOGIN_MAX;
    }

    private static function loginFail(): void
    {
        $hits   = array_values(array_filter(
            array_map('intval', Util::readJsonFile(self::loginFile())),
            static fn(int $t): bool => $t > time() - self::LOGIN_WIN
        ));
        $hits[] = time();
        Util::atomicWrite(self::loginFile(), (string)json_encode($hits));
    }

    private static function loginReset(): void
    {
        @unlink(self::loginFile());
    }

    /* ----------------------------------------------------------- rendering */

    /** @param array<string,mixed> $vars */
    private static function view(string $tpl, array $vars, int $status = 200): Response
    {
        $vars['csrf']   ??= self::authed() ? self::csrf() : '';
        $vars['authed'] ??= self::authed();
        $vars['nav_types'] = Types::enabledList();
        $vars['content_template'] = 'admin/' . $tpl;
        $html = Render::raw('admin/layout', $vars);
        return Response::html($html, $status)
            ->withHeader('Cache-Control', 'no-store')
            ->withHeader('X-Robots-Tag', 'noindex, nofollow')
            ->withHeader('X-Frame-Options', 'DENY');
    }

    /* ---------------------------------------------------------- dashboard */

    private static function dashboard(): Response
    {
        $counts = [];
        $drafts = [];
        $recent = [];
        foreach (Content::index() as $meta) {
            $counts[$meta['type']] = ($counts[$meta['type']] ?? 0) + 1;
            if ($meta['draft']) {
                $drafts[] = $meta;
            }
            $recent[] = $meta + ['mtime' => (int)@filemtime((string)$meta['file'])];
        }
        usort($recent, static fn(array $a, array $b): int => $b['mtime'] <=> $a['mtime']);
        return self::view('dashboard', [
            'title'  => I18n::t('admin_dashboard'),
            'counts' => $counts,
            'drafts' => $drafts,
            'recent' => array_slice($recent, 0, 10),
            'errors' => Content::errors(),
        ]);
    }

    /* ------------------------------------------------------------ content */

    /** @param list<string> $seg */
    private static function content(string $method, array $seg, array $query, array $post): Response
    {
        $type = (string)($seg[0] ?? '');
        if (!Types::enabled($type)) {
            return self::view('message', ['title' => '404', 'message' => 'Unknown type'], 404);
        }
        $rest = array_slice($seg, 1);

        if ($method === 'POST' && ($rest[0] ?? '') === 'save') {
            return self::save($type, $post);
        }
        if ($method === 'POST' && ($rest[1] ?? '') === 'delete') {
            return self::delete($type, (string)$rest[0]);
        }
        if ($rest === []) {
            $items = [];
            foreach (Content::index() as $meta) {
                if ($meta['type'] === $type) {
                    $items[] = $meta;
                }
            }
            return self::view('list', [
                'title' => Types::label($type, true),
                'type'  => $type,
                'items' => $items,
                'saved' => (string)($query['saved'] ?? ''),
            ]);
        }
        if (($rest[0] ?? '') === 'new') {
            return self::editor($type, null);
        }
        if (($rest[1] ?? '') === 'edit') {
            return self::editor($type, (string)$rest[0]);
        }
        return self::view('message', ['title' => '404', 'message' => I18n::t('404_title')], 404);
    }

    private static function editor(string $type, ?string $slug, array $values = [], array $errors = []): Response
    {
        $body = '';
        $fm   = [];
        if ($slug !== null && Util::isSlug($slug)) {
            $file = Types::dir($type) . '/' . $slug . '.md';
            if (!is_file($file)) {
                return self::view('message', ['title' => '404', 'message' => I18n::t('404_title')], 404);
            }
            [$fm, $body] = Frontmatter::parseFile((string)file_get_contents($file));
        }
        $managed = Types::managedKeys($type);
        $extra   = array_diff_key($fm, array_flip($managed));
        $values  = $values ?: array_merge($fm, [
            'slug'      => $slug ?? '',
            'body'      => $body,
            'advanced'  => $extra ? Frontmatter::dump($extra) : '',
        ]);
        $values['body'] ??= $body;
        $values['slug'] ??= (string)$slug;

        return self::view('edit', [
            'title'   => $slug === null ? I18n::t('admin_new') . ' · ' . Types::label($type) : I18n::t('admin_edit') . ' · ' . (string)($fm['title'] ?? $slug),
            'type'    => $type,
            'slug'    => $slug,
            'fields'  => Types::editorFields($type),
            'values'  => $values,
            'errors'  => $errors,
            'preview_url' => $slug !== null
                ? '/preview/' . $type . '/' . $slug . '/?t=' . hash_hmac('sha256', $type . '/' . $slug, Config::secret())
                : null,
        ]);
    }

    private static function save(string $type, array $post): Response
    {
        $publish = ($post['action'] ?? '') === 'publish';
        $orig    = (string)($post['orig_slug'] ?? '');
        // Everything below builds file paths from $orig; anything but a slug is "new".
        if ($orig !== '' && !Util::isSlug($orig)) {
            $orig = '';
        }
        $slug    = Util::slugify((string)($post['slug'] ?? '')) ?: Util::slugify((string)($post['title'] ?? ''));
        $body    = str_replace(["\r\n", "\r"], "\n", (string)($post['body'] ?? ''));
        $errors  = [];

        if (!Util::isSlug($slug)) {
            $errors['slug'] = I18n::t('err_slug');
        }
        $file = Types::dir($type) . '/' . $slug . '.md';
        if ($slug !== $orig && is_file($file)) {
            $errors['slug'] = I18n::t('err_slug_taken');
        }

        // Assemble front matter: existing keys first (so unknown ones survive), then the form.
        $fm = [];
        if ($orig !== '' && Util::isSlug($orig) && is_file(Types::dir($type) . '/' . $orig . '.md')) {
            [$fm] = Frontmatter::parseFile((string)file_get_contents(Types::dir($type) . '/' . $orig . '.md'));
        }
        foreach (Types::editorFields($type) as $field) {
            $name = (string)$field['name'];
            if ($name === 'slug') {
                continue;
            }
            $raw = $post[$name] ?? null;
            $fm[$name] = self::fieldValue((string)$field['type'], $raw, $field);
            if ($fm[$name] === null || $fm[$name] === '' || $fm[$name] === []) {
                unset($fm[$name]);
            }
        }
        $fm['draft'] = !$publish;

        $advanced = trim((string)($post['advanced'] ?? ''));
        if ($advanced !== '') {
            $fm = array_replace($fm, Frontmatter::parse($advanced));
        }

        // Path/route collision checks.
        $path = isset($fm['path']) && $fm['path'] !== ''
            ? '/' . trim((string)$fm['path'], '/') . '/'
            : (($type === 'page' && $slug === 'home') ? '/' : Types::pathFor($type, $slug));
        $path = Util::normalisePath($path);
        if (!Util::isSafePath($path)) {
            $errors['path'] = I18n::t('err_path');
        }
        if (array_key_exists($path, (array)Config::v('redirects', []))) {
            $errors['path'] = I18n::t('err_path_redirect');
        }
        foreach (['/admin/', '/preview/', '/enviar/', '/feed/'] as $reserved) {
            if (str_starts_with($path, $reserved)) {
                $errors['path'] = I18n::t('err_path_reserved');
            }
        }
        foreach (Content::index() as $existingPath => $meta) {
            if ($existingPath === $path && !($meta['type'] === $type && $meta['slug'] === $orig)) {
                $errors['path'] = I18n::t('err_path_taken');
            }
        }
        if (array_key_exists($path, (array)Config::v('hubs', [])) && !($type === 'page')) {
            $errors['path'] = I18n::t('err_path_hub');
        }

        if ($publish) {
            if (trim((string)($fm['title'] ?? '')) === '') {
                $errors['title'] = I18n::t('err_title');
            }
            $desc = trim((string)($fm['description'] ?? ''));
            if ($desc === '') {
                $errors['description'] = I18n::t('err_description');
            } elseif (mb_strlen($desc, 'UTF-8') > 170) {
                $errors['description'] = I18n::t('err_description_long');
            }
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)($fm['date'] ?? ''))) {
                $errors['date'] = I18n::t('err_date');
            }
            if (($fm['hero'] ?? '') !== '' && trim((string)($fm['hero_alt'] ?? '')) === '') {
                $errors['hero_alt'] = I18n::t('err_hero_alt');
            }
            if (preg_match('/!\[\s*\]\(/', $body)) {
                $errors['body'] = I18n::t('err_img_alt');
            }
        }

        if ($errors) {
            $values = array_merge($fm, ['slug' => $slug, 'body' => $body, 'advanced' => $advanced]);
            return self::editor($type, $orig !== '' ? $orig : null, $values, $errors);
        }

        // `updated` moves to today when the body or title changed on a published item.
        if ($publish && $orig !== '') {
            $old = @file_get_contents(Types::dir($type) . '/' . $orig . '.md');
            if ($old !== false) {
                [$oldFm, $oldBody] = Frontmatter::parseFile($old);
                if (trim($oldBody) !== trim($body) || ($oldFm['title'] ?? '') !== ($fm['title'] ?? '')) {
                    $fm['updated'] = date('Y-m-d');
                }
            }
        }
        $fm = self::orderKeys($fm);

        Util::mkdirp(dirname($file));
        if (!Util::atomicWrite($file, Frontmatter::dumpFile($fm, $body))) {
            return self::view('message', ['title' => I18n::t('admin'), 'message' => 'Write failed: ' . $file], 500);
        }
        if ($orig !== '' && $orig !== $slug) {
            @unlink(Types::dir($type) . '/' . $orig . '.md');
        }
        Render::purge();

        if (($post['then'] ?? '') === 'preview') {
            return Response::redirect('/preview/' . $type . '/' . $slug . '/?t=' . hash_hmac('sha256', $type . '/' . $slug, Config::secret()), 303);
        }
        return Response::redirect('/admin/content/' . $type . '/' . $slug . '/edit?saved=1', 303);
    }

    /** Keep a stable, readable key order in written files. */
    private static function orderKeys(array $fm): array
    {
        $order = ['title', 'seo_title', 'description', 'path', 'layout', 'date', 'datetime', 'updated',
                  'author', 'hero', 'hero_alt', 'excerpt', 'tags', 'region', 'order', 'featured',
                  'draft', 'noindex', 'canonical'];
        $out = [];
        foreach ($order as $k) {
            if (array_key_exists($k, $fm)) {
                $out[$k] = $fm[$k];
            }
        }
        foreach ($fm as $k => $v) {
            $out[$k] ??= $v;
        }
        return $out;
    }

    private static function fieldValue(string $type, mixed $raw, array $field): mixed
    {
        return match ($type) {
            'bool'   => (bool)$raw,
            'number' => $raw === null || $raw === '' ? null : (int)$raw,
            'list'   => array_values(array_filter(array_map(
                'trim',
                is_array($raw) ? array_map('strval', $raw) : (preg_split('/\r?\n/', (string)$raw) ?: [])
            ), static fn(string $s): bool => $s !== '')),
            'facts'  => self::mapValue($raw, (array)($field['keys'] ?? [])),
            'itinerary' => self::itineraryValue($raw),
            default  => trim((string)($raw ?? '')),
        };
    }

    private static function mapValue(mixed $raw, array $keys): array
    {
        $out = [];
        if (!is_array($raw)) {
            return $out;
        }
        foreach ($keys as $k) {
            $v = trim((string)($raw[$k] ?? ''));
            if ($v !== '') {
                $out[$k] = $v;
            }
        }
        return $out;
    }

    private static function itineraryValue(mixed $raw): array
    {
        if (!is_array($raw)) {
            return [];
        }
        $out = [];
        foreach ($raw as $row) {
            if (!is_array($row)) {
                continue;
            }
            $title = trim((string)($row['title'] ?? ''));
            $text  = trim((string)($row['text'] ?? ''));
            if ($title === '' && $text === '') {
                continue;
            }
            $out[] = array_filter([
                'day'   => trim((string)($row['day'] ?? '')),
                'title' => $title,
                'text'  => $text,
            ], static fn($v) => $v !== '');
        }
        return $out;
    }

    private static function delete(string $type, string $slug): Response
    {
        if (!Util::isSlug($slug)) {
            return self::view('message', ['title' => '404', 'message' => I18n::t('404_title')], 404);
        }
        $file = Types::dir($type) . '/' . $slug . '.md';
        if (is_file($file)) {
            $trash = VJ_SITE . '/data/trash';
            Util::mkdirp($trash);
            @rename($file, $trash . '/' . date('Ymd-His') . '-' . $type . '-' . $slug . '.md');
            Render::purge();
        }
        return Response::redirect('/admin/content/' . $type . '/?saved=deleted', 303);
    }

    /* -------------------------------------------------------------- media */

    private static function media(string $method, array $post): Response
    {
        $result = null;
        if ($method === 'POST') {
            $result = Images::upload($_FILES['file'] ?? [], (string)($post['alt'] ?? ''), (string)($post['name'] ?? ''));
            if ($result['ok']) {
                Render::purge();
            }
        }
        return self::view('media', [
            'title'  => I18n::t('admin_media'),
            'result' => $result,
            'files'  => Images::listMedia(),
        ]);
    }

    /* --------------------------------------------------------------- data */

    private static function data(string $method, string $name, array $post): Response
    {
        if (!isset(self::DATA_SCHEMAS[$name])) {
            return self::view('data-index', ['title' => I18n::t('admin_data'), 'names' => array_keys(self::DATA_SCHEMAS)]);
        }
        $schema = self::DATA_SCHEMAS[$name];
        $saved  = false;
        if ($method === 'POST') {
            $rows = [];
            foreach ((array)($post['rows'] ?? []) as $row) {
                if (!is_array($row)) {
                    continue;
                }
                $clean = [];
                foreach ($schema as $key => $kind) {
                    $v = $row[$key] ?? '';
                    $clean[$key] = match ($kind) {
                        'list'   => array_values(array_filter(array_map('trim', preg_split('/\s*,\s*/', (string)$v) ?: []))),
                        'number' => (string)$v === '' ? null : (int)$v,
                        default  => trim((string)$v),
                    };
                }
                if (implode('', array_map(static fn($v) => is_array($v) ? implode('', $v) : (string)$v, $clean)) === '') {
                    continue;
                }
                $rows[] = $clean;
            }
            Util::mkdirp(VJ_SITE . '/content/data');
            Util::atomicWrite(
                VJ_SITE . '/content/data/' . $name . '.json',
                (string)json_encode($rows, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n"
            );
            Render::purge();
            $saved = true;
        }
        return self::view('data', [
            'title'  => I18n::t('admin_data') . ' · ' . $name,
            'name'   => $name,
            'schema' => $schema,
            'rows'   => Util::readJsonFile(VJ_SITE . '/content/data/' . $name . '.json'),
            'saved'  => $saved,
        ]);
    }

    /* ---------------------------------------------------------- redirects */

    private static function redirects(array $query): Response
    {
        $check  = trim((string)($query['check'] ?? ''));
        $result = null;
        if ($check !== '') {
            $path   = Util::normalisePath('/' . trim($check, '/') . '/');
            $result = self::resolve($path);
        }
        return self::view('redirects', [
            'title'     => I18n::t('admin_redirects'),
            'redirects' => (array)Config::v('redirects', []),
            'gone'      => (array)Config::v('gone', []),
            'check'     => $check,
            'result'    => $result,
        ]);
    }

    /** What the router would do with $path, without issuing the request. */
    public static function resolve(string $path): string
    {
        $redirects = (array)Config::v('redirects', []);
        if (isset($redirects[$path])) {
            return '301 → ' . (string)$redirects[$path];
        }
        if (in_array($path, (array)Config::v('gone', []), true)) {
            return '410';
        }
        $meta = Content::metaByPath($path);
        if ($meta !== null) {
            return $meta['draft'] ? '404 (' . I18n::t('admin_draft') . ')' : '200 ' . $meta['type'] . '/' . $meta['slug'] . '.md';
        }
        if (array_key_exists($path, (array)Config::v('hubs', []))) {
            return '200 hub';
        }
        if (in_array($path, ['/sitemap.xml', '/robots.txt', '/feed/'], true)) {
            return '200 (engine route)';
        }
        return '404';
    }

    /* ------------------------------------------------------------- export */

    private static function export(string $method): Response
    {
        if ($method !== 'POST') {
            return Response::redirect('/admin/dashboard', 303);
        }
        if (!class_exists('ZipArchive')) {
            return self::view('message', ['title' => I18n::t('admin_export'), 'message' => 'ZipArchive is not available on this server.'], 500);
        }
        $tmp = tempnam(sys_get_temp_dir(), 'vjexp');
        if ($tmp === false) {
            return self::view('message', ['title' => I18n::t('admin_export'), 'message' => 'Cannot create a temporary file.'], 500);
        }
        $zip = new ZipArchive();
        if ($zip->open($tmp, ZipArchive::OVERWRITE) !== true) {
            return self::view('message', ['title' => I18n::t('admin_export'), 'message' => 'Cannot create the archive.'], 500);
        }
        foreach (['content', 'media', 'data/leads'] as $rel) {
            $dir = VJ_SITE . '/' . $rel;
            if (!is_dir($dir)) {
                continue;
            }
            $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS));
            foreach ($it as $f) {
                /** @var SplFileInfo $f */
                if ($f->isFile()) {
                    $zip->addFile($f->getPathname(), $rel . str_replace('\\', '/', substr($f->getPathname(), strlen($dir))));
                }
            }
        }
        $zip->close();
        $body = (string)file_get_contents($tmp);
        @unlink($tmp);
        $name = Config::v('domain') . '-backup-' . date('Ymd-His') . '.zip';
        return (new Response(200, $body, [
            'Content-Type'        => 'application/zip',
            'Content-Disposition' => 'attachment; filename="' . $name . '"',
            'Cache-Control'       => 'no-store',
        ]));
    }
}
