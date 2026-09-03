<?php
declare(strict_types=1);

/**
 * The /admin/ area: auth, CSRF, rate limiting and the content write path.
 * Every response here is no-store + noindex. Every POST is CSRF-checked.
 */
final class Admin
{
    public const SESSION_NAME = 'vjsess';
    private const IDLE_SECONDS = 4 * 3600;
    private const LOGIN_MAX    = 5;
    private const LOGIN_WINDOW = 15 * 60;

    /** Data-collection row schemas. Unknown collections fall back to the keys present. */
    private const DATA_SCHEMA = [
        'faq'          => [['q', 'text'], ['a', 'textarea'], ['tags', 'list']],
        'testimonials' => [['name', 'text'], ['text', 'textarea'], ['trip', 'text'], ['rating', 'text']],
        'team'         => [['name', 'text'], ['role', 'text'], ['photo', 'text'], ['bio', 'textarea']],
        'gallery'      => [['src', 'text'], ['alt', 'text'], ['caption', 'text'], ['category', 'text']],
    ];

    /* ------------------------------------------------------------- session */

    public static function isAuthed(): bool
    {
        if (!isset($_SESSION['admin_ok'], $_SESSION['admin_seen'])) {
            return false;
        }
        if (time() - (int)$_SESSION['admin_seen'] > self::IDLE_SECONDS) {
            self::destroySession();
            return false;
        }
        $_SESSION['admin_seen'] = time();
        return $_SESSION['admin_ok'] === true;
    }

    private static function destroySession(): void
    {
        $_SESSION = [];
        if (session_status() === PHP_SESSION_ACTIVE) {
            @session_destroy();
        }
    }

    public static function csrf(): string
    {
        if (empty($_SESSION['csrf'])) {
            $_SESSION['csrf'] = bin2hex(random_bytes(32));
        }
        return (string)$_SESSION['csrf'];
    }

    private static function csrfOk(array $post): bool
    {
        $sent = (string)($post['_csrf'] ?? '');
        $have = (string)($_SESSION['csrf'] ?? '');
        return $sent !== '' && $have !== '' && hash_equals($have, $sent);
    }

    /* ------------------------------------------------------------ dispatch */

    public static function dispatch(string $method, string $path, array $query, array $server): Response
    {
        $sub  = substr($path, strlen('/admin'));   // always starts with "/"
        $sub  = '/' . ltrim($sub, '/');
        $post = $_POST;

        if (!Config::adminConfigured()) {
            if ($method === 'POST') {
                return self::wrap(Response::html(self::view('admin/setup', ['authed' => false]), 403));
            }
            return self::wrap(Response::html(self::view('admin/setup', ['authed' => false]), 200));
        }

        // Login is the only POST allowed while signed out.
        if ($sub === '/login' && $method === 'POST') {
            return self::wrap(self::login($post));
        }
        if ($sub === '/logout' && $method === 'POST') {
            if (!self::csrfOk($post)) {
                return self::wrap(self::forbidden());
            }
            self::destroySession();
            return self::wrap(Response::redirect('/admin/', 303));
        }

        if (!self::isAuthed()) {
            if ($method === 'POST') {
                return self::wrap(Response::redirect('/admin/', 303));
            }
            return self::wrap(Response::html(self::view('admin/login', ['authed' => false, 'error' => null])));
        }

        if ($method === 'POST' && !self::csrfOk($post)) {
            return self::wrap(self::forbidden());
        }

        return self::wrap(self::route($method, $sub, $query, $post));
    }

    private static function route(string $method, string $sub, array $query, array $post): Response
    {
        if ($sub === '/' || $sub === '/dashboard') {
            return self::dashboard();
        }
        if ($sub === '/redirects') {
            return self::redirectsPage($query);
        }
        if ($sub === '/export' && $method === 'POST') {
            return self::export();
        }
        if ($sub === '/preview-md' && $method === 'POST') {
            $md = (string)($post['markdown'] ?? '');
            return Response::html('<div class="prose">' . Markdown::render($md) . '</div>');
        }
        if ($sub === '/media') {
            return $method === 'POST' ? self::mediaUpload($post) : self::media(null);
        }
        if (preg_match('#^/data/([a-z][a-z0-9-]{0,40})$#', $sub, $m)) {
            return $method === 'POST' ? self::dataSave($m[1], $post) : self::dataEdit($m[1], null);
        }
        if (preg_match('#^/content/([a-z]+)/save$#', $sub, $m) && $method === 'POST') {
            return self::save($m[1], $post);
        }
        if (preg_match('#^/content/([a-z]+)/([a-z0-9-]+)/delete$#', $sub, $m) && $method === 'POST') {
            return self::delete($m[1], $m[2]);
        }
        if (preg_match('#^/content/([a-z]+)/new$#', $sub, $m)) {
            return self::edit($m[1], null);
        }
        if (preg_match('#^/content/([a-z]+)/([a-z0-9-]+)/edit$#', $sub, $m)) {
            return self::edit($m[1], $m[2]);
        }
        if (preg_match('#^/content/([a-z]+)/?$#', $sub, $m)) {
            return self::listType($m[1]);
        }
        return Response::html(self::view('admin/dashboard', [
            'authed' => true,
            'notice' => 'Unknown admin page.',
        ] + self::dashboardVars()), 404);
    }

    private static function wrap(Response $r): Response
    {
        return $r->withHeader('Cache-Control', 'no-store, no-cache, must-revalidate')
                 ->withHeader('X-Robots-Tag', 'noindex, nofollow')
                 ->withHeader('Referrer-Policy', 'same-origin');
    }

    private static function forbidden(): Response
    {
        return Response::html(self::view('admin/login', [
            'authed' => self::isAuthed(),
            'error'  => I18n::t('admin_csrf'),
        ]), 403);
    }

    /* --------------------------------------------------------------- auth */

    private static function login(array $post): Response
    {
        $ip = Util::clientIp();
        if (RateLimit::exceeded('login', $ip, self::LOGIN_MAX, self::LOGIN_WINDOW)) {
            return Response::html(self::view('admin/login', ['authed' => false, 'error' => I18n::t('admin_locked')]), 429);
        }
        if (!self::csrfOk($post)) {
            return self::forbidden();
        }
        $hash = (string)Config::v('admin_password_hash', '');
        $pw   = (string)($post['password'] ?? '');
        if ($pw !== '' && password_verify($pw, $hash)) {
            RateLimit::clear('login', $ip);
            session_regenerate_id(true);
            $_SESSION['admin_ok']   = true;
            $_SESSION['admin_seen'] = time();
            $_SESSION['csrf']       = bin2hex(random_bytes(32));
            return Response::redirect('/admin/dashboard', 303);
        }
        RateLimit::hit('login', $ip, self::LOGIN_WINDOW);
        usleep(300000);
        return Response::html(self::view('admin/login', ['authed' => false, 'error' => I18n::t('admin_bad_login')]), 401);
    }

    /* ---------------------------------------------------------- dashboard */

    private static function dashboardVars(): array
    {
        $index  = Content::index(true);
        $counts = [];
        foreach (Types::enabledList() as $type) {
            $counts[$type] = ['total' => 0, 'draft' => 0];
        }
        $warnings = $index['errors'];
        $recent   = [];
        foreach ($index['items'] as $m) {
            if (!isset($counts[$m['type']])) {
                $counts[$m['type']] = ['total' => 0, 'draft' => 0];
            }
            $counts[$m['type']]['total']++;
            if ($m['draft']) {
                $counts[$m['type']]['draft']++;
            }
            if (!$m['draft'] && $m['description'] === '') {
                $warnings[] = 'Missing description: ' . $m['rel'];
            }
            if ($m['hero'] !== '' && $m['hero_alt'] === '') {
                $warnings[] = 'Hero image without hero_alt: ' . $m['rel'];
            }
            $recent[] = $m + ['mtime' => (int)@filemtime($m['file'])];
        }
        usort($recent, static fn(array $a, array $b): int => $b['mtime'] <=> $a['mtime']);

        return [
            'counts'    => $counts,
            'warnings'  => array_values(array_unique($warnings)),
            'recent'    => array_slice($recent, 0, 10),
            'leadCount' => self::leadCount(),
            'dataNames' => Content::dataNames(),
        ];
    }

    private static function dashboard(): Response
    {
        return Response::html(self::view('admin/dashboard', ['authed' => true] + self::dashboardVars()));
    }

    private static function leadCount(): int
    {
        $n = 0;
        foreach (glob(VJ_SITE . '/data/leads/*.jsonl') ?: [] as $f) {
            $n += max(0, count(file($f, FILE_SKIP_EMPTY_LINES) ?: []));
        }
        return $n;
    }

    /* ------------------------------------------------------------ listing */

    private static function listType(string $type): Response
    {
        if (!Types::enabled($type)) {
            return Response::html(self::view('admin/dashboard', ['authed' => true, 'notice' => 'Unknown type.'] + self::dashboardVars()), 404);
        }
        $items = [];
        foreach (Content::index(true)['items'] as $m) {
            if ($m['type'] === $type) {
                $items[] = $m;
            }
        }
        return Response::html(self::view('admin/list', [
            'authed' => true,
            'type'   => $type,
            'items'  => $items,
        ]));
    }

    /* ------------------------------------------------------------- editor */

    private static function edit(string $type, ?string $slug, array $form = [], array $errors = []): Response
    {
        if (!Types::enabled($type)) {
            return Response::html(self::view('admin/dashboard', ['authed' => true, 'notice' => 'Unknown type.'] + self::dashboardVars()), 404);
        }
        $values   = [];
        $body     = '';
        $advanced = '';
        $isNew    = true;

        if ($slug !== null) {
            $file = Content::fileFor($type, $slug);
            if ($file === null) {
                return Response::html(self::view('admin/dashboard', ['authed' => true, 'notice' => 'That item no longer exists.'] + self::dashboardVars()), 404);
            }
            $isNew = false;
            [$fm, $body] = Frontmatter::parseFile((string)@file_get_contents($file));
            $values          = $fm;
            $values['slug']  = $slug;
            $values['draft'] = (bool)($fm['draft'] ?? false);
            $managed  = Types::managedKeys($type);
            $extra    = array_diff_key($fm, array_flip($managed));
            $advanced = $extra === [] ? '' : Frontmatter::dump($extra);
        } else {
            $values = ['date' => date('Y-m-d'), 'draft' => true];
        }

        if ($form !== []) {
            $values   = array_merge($values, $form);
            $body     = (string)($form['body'] ?? $body);
            $advanced = (string)($form['advanced'] ?? $advanced);
        }

        return Response::html(self::view('admin/edit', [
            'authed'    => true,
            'type'      => $type,
            'slug'      => $slug,
            'is_new'    => $isNew,
            'values'    => $values,
            'body'      => $body,
            'advanced'  => $advanced,
            'errors'    => $errors,
            'fields'    => Types::editorFields($type),
            'media'     => Images::listMedia(60),
        ]), $errors === [] ? 200 : 422);
    }

    /* --------------------------------------------------------------- save */

    private static function save(string $type, array $post): Response
    {
        if (!Types::enabled($type)) {
            return Response::redirect('/admin/dashboard', 303);
        }
        $publish  = ($post['action'] ?? '') === 'publish';
        $goPreview = ($post['action'] ?? '') === 'preview';
        $origSlug = trim((string)($post['orig_slug'] ?? ''));
        $origSlug = Util::isSlug($origSlug) ? $origSlug : '';

        $title = trim((string)($post['title'] ?? ''));
        $slug  = trim((string)($post['slug'] ?? ''));
        if ($slug === '') {
            $slug = Util::slugify($title);
        }
        $slug = Util::slugify($slug);

        $errors = [];
        if ($title === '') {
            $errors[] = 'Title is required.';
        }
        if (!Util::isSlug($slug)) {
            $errors[] = 'The slug must be lowercase letters, digits and single hyphens.';
        }

        // Collect the modelled fields.
        $fm = [];
        foreach (Types::editorFields($type) as $f) {
            $name = (string)$f['name'];
            if ($name === 'slug') {
                continue;
            }
            $raw = $post['f'][$name] ?? null;
            $val = self::fieldValue((string)$f['type'], $raw, $f);
            if ($val !== null && $val !== '' && $val !== []) {
                $fm[$name] = $val;
            }
        }
        $fm['title'] = $title;
        $fm['draft'] = !$publish;

        $description = trim((string)($fm['description'] ?? ''));
        $date        = trim((string)($fm['date'] ?? ''));
        $body        = str_replace(["\r\n", "\r"], "\n", (string)($post['body'] ?? ''));

        // Advanced raw front matter is merged last so it can override anything.
        $advancedRaw = (string)($post['advanced'] ?? '');
        if (trim($advancedRaw) !== '') {
            $extra = Frontmatter::parse($advancedRaw);
            if ($extra === []) {
                $errors[] = 'The advanced front matter could not be parsed; nothing was saved.';
            }
            $fm = array_merge($fm, $extra);
        }

        if (isset($fm['path'])) {
            $p = (string)$fm['path'];
            if (!Util::isSafePath($p)) {
                $errors[] = "The path override must start and end with '/' and contain no '..'.";
            }
        }

        // Slug/path collision checks.
        $targetFile = Types::dir($type) . '/' . $slug . '.md';
        if (Util::isSlug($slug)) {
            if ($slug !== $origSlug && is_file($targetFile)) {
                $errors[] = "Another $type already uses the slug '$slug'.";
            }
            $newPath = isset($fm['path']) && Util::isSafePath((string)$fm['path'])
                ? (string)$fm['path']
                : Types::pathFor($type, $slug);
            foreach (self::reservedPaths() as $reserved => $why) {
                if ($newPath === $reserved) {
                    $errors[] = "The path $newPath is reserved ($why).";
                }
            }
            foreach (Content::index(true)['items'] as $path => $m) {
                if ($path === $newPath && !($m['type'] === $type && $m['slug'] === $origSlug)) {
                    $errors[] = "The path $newPath is already used by " . $m['rel'] . '.';
                }
            }
        }

        if ($publish) {
            if ($description === '') {
                $errors[] = 'A meta description is required to publish.';
            } elseif (mb_strlen($description) > 170) {
                $errors[] = 'The meta description must be 170 characters or fewer (currently ' . mb_strlen($description) . ').';
            }
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
                $errors[] = 'A valid date (YYYY-MM-DD) is required to publish.';
            }
            if (!empty($fm['hero']) && trim((string)($fm['hero_alt'] ?? '')) === '') {
                $errors[] = 'Alt text is required when a hero image is set.';
            }
            if (preg_match('/!\[\s*\]\([^)]*\)/', $body)) {
                $errors[] = 'The body contains an image with empty alt text. Use ![-](…) only for decorative images.';
            }
        }

        if ($errors !== []) {
            $form = ['slug' => $slug, 'title' => $title, 'body' => $body, 'advanced' => $advancedRaw] + $fm;
            return self::edit($type, $origSlug !== '' ? $origSlug : null, $form, $errors);
        }

        // Bump `updated` when a published item's title or body changed.
        if ($publish && $origSlug !== '') {
            $oldFile = Content::fileFor($type, $origSlug);
            if ($oldFile !== null) {
                [$oldFm, $oldBody] = Frontmatter::parseFile((string)@file_get_contents($oldFile));
                if (trim($oldBody) !== trim($body) || (string)($oldFm['title'] ?? '') !== $title) {
                    $fm['updated'] = date('Y-m-d');
                }
            }
        }

        $fm = self::orderKeys($fm);
        $out = Frontmatter::dumpFile($fm, $body);
        if (!Util::atomicWrite($targetFile, $out)) {
            return self::edit($type, $origSlug !== '' ? $origSlug : null,
                ['slug' => $slug, 'title' => $title, 'body' => $body, 'advanced' => $advancedRaw] + $fm,
                ['Could not write the content file. Check directory permissions.']);
        }
        if ($origSlug !== '' && $origSlug !== $slug) {
            $old = Types::dir($type) . '/' . $origSlug . '.md';
            if (is_file($old)) {
                @unlink($old);
            }
        }
        Render::purge();
        Content::index(true);

        if ($goPreview) {
            return Response::redirect(Router::previewUrl($type, $slug), 303);
        }
        return Response::redirect('/admin/content/' . rawurlencode($type) . '/' . rawurlencode($slug) . '/edit?saved=1', 303);
    }

    /** Paths the router owns; content must never claim one. */
    private static function reservedPaths(): array
    {
        $out = [
            '/sitemap.xml' => 'sitemap',
            '/robots.txt'  => 'robots',
            '/feed/'       => 'feed',
            '/admin/'      => 'admin',
            '/enviar/'     => 'form handler',
        ];
        foreach (array_keys((array)Config::v('hubs', [])) as $h) {
            $out[(string)$h] = 'hub page';
        }
        foreach (array_keys((array)Config::v('redirects', [])) as $r) {
            $out[(string)$r] = 'redirect source';
        }
        foreach ((array)Config::v('gone', []) as $g) {
            $out[(string)$g] = 'gone list';
        }
        return $out;
    }

    private static function orderKeys(array $fm): array
    {
        $order = ['title', 'seo_title', 'description', 'path', 'date', 'datetime', 'updated', 'author',
                  'hero', 'hero_alt', 'excerpt', 'tags', 'region', 'layout', 'order', 'featured',
                  'draft', 'noindex', 'canonical'];
        $out = [];
        foreach ($order as $k) {
            if (array_key_exists($k, $fm)) {
                $out[$k] = $fm[$k];
            }
        }
        foreach ($fm as $k => $v) {
            if (!array_key_exists($k, $out)) {
                $out[$k] = $v;
            }
        }
        return $out;
    }

    private static function fieldValue(string $type, mixed $raw, array $def): mixed
    {
        switch ($type) {
            case 'bool':
                return !empty($raw) ? true : null;
            case 'number':
                $s = trim((string)$raw);
                return $s === '' ? null : (int)$s;
            case 'list':
                if (is_array($raw)) {
                    $items = $raw;
                } else {
                    $items = preg_split('/\r?\n/', (string)$raw) ?: [];
                }
                $items = array_values(array_filter(array_map('trim', array_map('strval', $items)), static fn(string $s): bool => $s !== ''));
                return $items === [] ? null : $items;
            case 'facts':
                $out = [];
                foreach ((array)($def['keys'] ?? []) as $k) {
                    $v = trim((string)(is_array($raw) ? ($raw[$k] ?? '') : ''));
                    if ($v !== '') {
                        $out[$k] = ctype_digit($v) ? (int)$v : $v;
                    }
                }
                return $out === [] ? null : $out;
            case 'itinerary':
                $out = [];
                $days   = (array)(is_array($raw) ? ($raw['day'] ?? []) : []);
                $titles = (array)(is_array($raw) ? ($raw['title'] ?? []) : []);
                $texts  = (array)(is_array($raw) ? ($raw['text'] ?? []) : []);
                foreach ($titles as $i => $tt) {
                    $tt = trim((string)$tt);
                    $tx = trim((string)($texts[$i] ?? ''));
                    if ($tt === '' && $tx === '') {
                        continue;
                    }
                    $out[] = array_filter([
                        'day'   => trim((string)($days[$i] ?? '')) !== '' ? (int)$days[$i] : ($i + 1),
                        'title' => $tt,
                        'text'  => $tx,
                    ], static fn($v): bool => $v !== '' && $v !== null);
                }
                return $out === [] ? null : $out;
            default:
                $s = trim((string)$raw);
                return $s === '' ? null : $s;
        }
    }

    /* ------------------------------------------------------------- delete */

    private static function delete(string $type, string $slug): Response
    {
        $file = Content::fileFor($type, $slug);
        if ($file === null) {
            return Response::redirect('/admin/dashboard', 303);
        }
        $trash = VJ_SITE . '/data/trash';
        Util::mkdirp($trash);
        $dest = $trash . '/' . date('Ymd-His') . '-' . $type . '-' . $slug . '.md';
        if (!@rename($file, $dest)) {
            @copy($file, $dest);
            @unlink($file);
        }
        Render::purge();
        Content::index(true);
        return Response::redirect('/admin/content/' . rawurlencode($type) . '/?deleted=1', 303);
    }

    /* -------------------------------------------------------------- media */

    private static function media(?array $result): Response
    {
        return Response::html(self::view('admin/media', [
            'authed' => true,
            'items'  => Images::listMedia(),
            'result' => $result,
        ]));
    }

    private static function mediaUpload(array $post): Response
    {
        $file   = $_FILES['file'] ?? null;
        $result = is_array($file)
            ? Images::upload($file, (string)($post['alt'] ?? ''), (string)($post['name'] ?? ''))
            : ['ok' => false, 'error' => 'No file was received.'];
        if ($result['ok'] ?? false) {
            Render::purge();
        }
        return self::media($result);
    }

    /* --------------------------------------------------------- data files */

    public static function dataSchema(string $name, array $rows): array
    {
        if (isset(self::DATA_SCHEMA[$name])) {
            return self::DATA_SCHEMA[$name];
        }
        $keys = [];
        foreach ($rows as $r) {
            foreach (array_keys((array)$r) as $k) {
                $keys[(string)$k] = true;
            }
        }
        if ($keys === []) {
            $keys = ['title' => true, 'text' => true];
        }
        return array_map(static fn(string $k): array => [$k, 'text'], array_keys($keys));
    }

    private static function dataEdit(string $name, ?string $notice): Response
    {
        $rows = Content::data($name);
        return Response::html(self::view('admin/data', [
            'authed' => true,
            'name'   => $name,
            'rows'   => $rows,
            'schema' => self::dataSchema($name, $rows),
            'notice' => $notice,
        ]));
    }

    private static function dataSave(string $name, array $post): Response
    {
        if (!preg_match('/^[a-z][a-z0-9-]{0,40}$/', $name)) {
            return Response::redirect('/admin/dashboard', 303);
        }
        $existing = Content::data($name);
        $schema   = self::dataSchema($name, $existing);
        $rowsIn   = (array)($post['row'] ?? []);
        $out      = [];
        $count    = 0;
        foreach ($schema as [$key, $kind]) {
            $count = max($count, count((array)($rowsIn[$key] ?? [])));
        }
        for ($i = 0; $i < $count; $i++) {
            $row   = [];
            $empty = true;
            foreach ($schema as [$key, $kind]) {
                $v = trim((string)(($rowsIn[$key][$i] ?? '')));
                if ($v !== '') {
                    $empty = false;
                }
                if ($kind === 'list') {
                    $row[$key] = array_values(array_filter(array_map('trim', explode(',', $v)), static fn(string $s): bool => $s !== ''));
                } elseif ($key === 'rating' && $v !== '') {
                    $row[$key] = (int)$v;
                } else {
                    $row[$key] = $v === '' ? null : $v;
                }
            }
            if (!$empty) {
                $out[] = $row;
            }
        }
        $file = VJ_SITE . '/content/data/' . $name . '.json';
        Util::mkdirp(dirname($file));
        $json = json_encode($out, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($json === false || !Util::atomicWrite($file, $json . "\n")) {
            return self::dataEdit($name, 'Could not write the file.');
        }
        Render::purge();
        Content::index(true);
        return Response::redirect('/admin/data/' . rawurlencode($name) . '?saved=1', 303);
    }

    /* ---------------------------------------------------------- redirects */

    private static function redirectsPage(array $query): Response
    {
        $check  = trim((string)($query['check'] ?? ''));
        $result = null;
        if ($check !== '') {
            $path = Util::normalisePath('/' . ltrim($check, '/'));
            $redirects = (array)Config::v('redirects', []);
            if (isset($redirects[$path])) {
                $result = ['status' => 301, 'detail' => 'redirects to ' . (string)$redirects[$path]];
            } elseif (in_array($path, (array)Config::v('gone', []), true)) {
                $result = ['status' => 410, 'detail' => 'listed as gone'];
            } elseif (($m = Content::metaByPath($path)) !== null) {
                $result = ['status' => $m['draft'] ? 404 : 200, 'detail' => $m['rel'] . ($m['draft'] ? ' (draft)' : '')];
            } elseif (isset(((array)Config::v('hubs', []))[$path])) {
                $result = ['status' => 200, 'detail' => 'hub page'];
            } else {
                $result = ['status' => 404, 'detail' => 'nothing matches this path'];
            }
            $result['path'] = $path;
        }
        return Response::html(self::view('admin/redirects', [
            'authed'    => true,
            'redirects' => (array)Config::v('redirects', []),
            'gone'      => (array)Config::v('gone', []),
            'check'     => $check,
            'result'    => $result,
        ]));
    }

    /* ------------------------------------------------------------- export */

    private static function export(): Response
    {
        if (!class_exists('ZipArchive')) {
            return Response::text('The zip extension is not available on this server.', 500);
        }
        $tmp = tempnam(sys_get_temp_dir(), 'export');
        if ($tmp === false) {
            return Response::text('Could not create a temporary file.', 500);
        }
        $zip = new ZipArchive();
        if ($zip->open($tmp, ZipArchive::OVERWRITE | ZipArchive::CREATE) !== true) {
            @unlink($tmp);
            return Response::text('Could not create the archive.', 500);
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
                    $zip->addFile($f->getPathname(), $rel . '/' . ltrim(str_replace('\\', '/', substr($f->getPathname(), strlen($dir))), '/'));
                }
            }
        }
        $zip->close();
        $body = (string)@file_get_contents($tmp);
        @unlink($tmp);
        $name = (string)Config::v('domain', 'site') . '-' . date('Ymd-His') . '.zip';
        return new Response(200, $body, [
            'Content-Type'        => 'application/zip',
            'Content-Disposition' => 'attachment; filename="' . $name . '"',
            'Content-Length'      => (string)strlen($body),
        ]);
    }

    /* --------------------------------------------------------------- view */

    public static function view(string $tpl, array $vars): string
    {
        $vars['content'] = Render::raw($tpl, $vars);
        return Render::raw('admin/layout', $vars);
    }
}
