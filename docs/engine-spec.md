# Engine spec — shared PHP flat-file engine for viaje.com.py and thingstodoinparaguay.com

This is the build contract for phase 1. Every value here is decided; do not re-open decisions,
do not add frameworks, do not add Composer dependencies. Target runtime: PHP 8.1+ on Hostinger
shared hosting (LiteSpeed/Apache, `.htaccess` honoured, GD + mbstring + fileinfo present).
Local dev/test runtime: PHP 8.4 CLI with the built-in server.

Read `plan.md` §1, §2, §5 first. This document is the detail behind plan §2.

---

## 0. Repo layout (final)

```
engine/                       shared code, no site-specific values anywhere in it
  bootstrap.php               loads config, autoloads lib/, dispatches
  dev-router.php              router script for `php -S` (emulates .htaccess)
  lib/
    config.php                load site/config.php + site/config.local.php (optional), validate required keys
    frontmatter.php           parse/serialize the front-matter subset (§3)
    content.php               index builder, loaders, listings, pagination
    types.php                 content-type registry (§4): folder, default path, template, admin fields, schema builder
    router.php                request → response (§5)
    seo.php                   head builders + JSON-LD (§6)
    render.php                template rendering, page cache (§7)
    markdown.php              Parsedown wrapper + `:::tip` block + external-link rel + heading ids
    images.php                upload validation, GD re-encode, responsive variants (§9)
    leads.php                 contact form handler (§10)
    admin.php                 auth, CSRF, rate limit, admin controllers (§8)
    i18n.php                  t('key') from engine/lang/<lang>.php with site overrides
    util.php                  slugify, e() escaping, absolute_url(), etc.
  vendor/Parsedown.php        vendored from https://raw.githubusercontent.com/erusev/parsedown/1.7.4/Parsedown.php (verbatim)
  templates/
    base.php                  html skeleton, header, footer, head from seo.php
    home.php service.php page.php hub.php post.php faq.php contact.php 404.php
    partials/  card.php breadcrumbs.php faq-accordion.php tip.php factbox.php lead-form.php whatsapp.php pagination.php
    admin/     login.php layout.php dashboard.php list.php edit.php media.php
  assets/
    base.css                  engine design system (tokens as CSS custom properties, see §11)
    site.js                   mobile nav, FAQ, WhatsApp composer, form UX (vanilla, < 6 KB)
    admin.css admin.js        editor UX: SEO counters, snippet preview, upload
  lang/es.php  lang/en.php    UI strings
  bin/hash-password.php       prints a bcrypt hash for config.local.php

sites/<domain>/               one per install (viaje.com.py, thingstodoinparaguay.com)
  config.php                  returns array (§2)
  config.local.example.php    template for secrets; real config.local.php is git-ignored
  theme.css                   token overrides + site-specific CSS
  urls.txt                    URL contract for tools/verify.php (§12)
  content/<type>/<slug>.md    content files
  content/data/*.json         faq.json, testimonials.json, team.json, gallery.json
  assets/                     logo.svg, favicon, hero images shipped with the site
  static/                     served verbatim at the same paths (e.g. static/wp-content/uploads/...)
  media/                      admin uploads (git-ignored except .gitkeep)
  data/                       leads log, backups (git-ignored)
  cache/                      page cache + index (git-ignored)

tools/
  build.php <domain>          assemble dist/<domain>/ (document root): index.php, .htaccess, engine/, site/ (= sites/<domain>), static files copied to root
  verify.php <domain> [--base=http://127.0.0.1:8080]   URL-contract + on-page SEO checks (§12)
  serve.php <domain>          build + `php -S 127.0.0.1:8080 -t dist/<domain> dist/<domain>/engine/dev-router.php`

dist/                         git-ignored build output
.github/workflows/ci.yml      php -l all files; build + verify both sites
```

The built document root for a site looks like:

```
dist/<domain>/
  index.php          <?php require __DIR__.'/engine/bootstrap.php';
  .htaccess          (§13)
  engine/            copy of engine/
  site/              copy of sites/<domain>/ (config, content, theme, media, data, cache)
  <static files>     contents of sites/<domain>/static/ copied to the root
```

`build.php` must be safe to re-run and must never delete `site/content`, `site/media`, `site/data`, `site/cache`, `site/config.local.php` in an existing dist. It copies engine and site config/theme/assets/content over, and skips those runtime dirs when they already exist (they are the server's source of truth after cutover, see plan §2.4).

---

## 1. Request lifecycle

1. `.htaccess` serves existing files directly; everything else → `index.php`.
2. `bootstrap.php`: `declare(strict_types=1)`, error reporting to log (never to output in production; `config['debug']` true prints), load config, start a session **only** for `/admin/*`, `/enviar/`, `/preview/*`.
3. `router::dispatch($method, $path, $query)` returns a `Response` (status, headers, body). Bootstrap emits it.
4. Page cache short-circuits step 3 for cacheable GETs (§7).

---

## 2. Site config (`sites/<domain>/config.php`)

Returns an array. Required keys are validated at boot; a missing required key throws with a clear message.

```php
return [
  'domain'         => 'viaje.com.py',
  'base_url'       => 'https://viaje.com.py',      // no trailing slash; canonical host
  'force_https'    => true,
  'force_host'     => 'viaje.com.py',              // null = don't redirect host; 301 www→apex or apex→www
  'lang'           => 'es',                        // engine/lang/es.php
  'html_lang'      => 'es-PY',
  'locale_og'      => 'es_PY',
  'site_name'      => 'Viaje.com.py',
  'title_suffix'   => ' | Viaje.com.py',           // appended when seo_title is not set (not on home)
  'tagline'        => '...',
  'staging'        => false,                       // true ⇒ X-Robots-Tag: noindex + meta robots noindex on every response
  'debug'          => false,
  'contact' => [
    'phone_display' => '+595 995 628 862',
    'phone_e164'    => '+595995628862',
    'whatsapp_e164' => '595995628862',
    'email'         => 'hola@viaje.com.py',
    'address'       => ['street' => 'Edificio Skytower', 'city' => 'Asunción', 'region' => 'Asunción', 'country' => 'PY', 'country_name' => 'Paraguay'],
    'hours'         => 'Lun–Sáb 08:00–19:00',
    'whatsapp_default_text' => 'Hola Viaje.com.py, quiero consultar por ...',
  ],
  'socials'  => ['instagram' => 'https://...', 'facebook' => '...', 'tiktok' => null],  // nulls skipped
  'schema'   => ['type' => 'TravelAgency', 'logo' => '/assets/logo.png', 'founder' => 'Anton Marklund', 'area_served' => 'Paraguay', 'price_range' => '$$'],
  'author_default' => ['name' => 'Equipo Viaje.com.py', 'type' => 'Organization'],
  'nav'      => [['label' => 'Inicio', 'href' => '/'], ...],
  'footer_nav' => [...],
  'types'    => ['page','service','post','news','trip','activity'],   // enabled types, in this site
  'hubs'     => [  // hub path => type; hub pages get their own title/description here
    '/servicios/'   => ['type' => 'service',  'title' => '...', 'description' => '...', 'show_faq' => true],
    '/blog/'        => ['type' => 'post',     'title' => '...', 'description' => '...', 'per_page' => 12],
    '/novedades/'   => ['type' => 'news',     ...],
    '/viajes/'      => ['type' => 'trip',     ...],
    '/actividades/' => ['type' => 'activity', ...],
  ],
  'type_paths' => ['service' => '/', 'post' => '/blog/', 'news' => '/novedades/', 'trip' => '/viajes/', 'activity' => '/actividades/', 'page' => '/'],
  'redirects' => ['/paquetes/' => '/servicios/', ...],   // 301, exact path match after trailing-slash normalisation
  'gone'      => ['/elementor-9/', '/hello-world/'],    // 410
  'analytics' => ['ga4' => null],                        // emitted only if set, not on staging
  'leads'     => ['to' => 'hola@viaje.com.py', 'subject_prefix' => '[viaje.com.py] ', 'vendercrm' => ['endpoint' => null, 'tenant_key' => null]],
  'home'      => ['featured_posts' => 3, 'faq_tags' => ['home'], 'services_order' => ['agencia-de-viaje', ...]],
];
```

`config.local.php` (same shape, merged over config.php with array_replace_recursive) holds: `admin_password_hash`, `preview_secret`, `leads.vendercrm.tenant_key`, `analytics.ga4`, `debug`, `staging`. `config.local.example.php` documents every key. If `admin_password_hash` is missing, `/admin/` shows a setup page explaining how to generate it and refuses login; the public site works regardless.

---

## 3. Content files and front matter

File: `sites/<domain>/content/<type>/<slug>.md`. `<slug>` is the filename and the default URL slug; the filename regex is `^[a-z0-9]+(?:-[a-z0-9]+)*$`.

Front matter is a **deliberately small YAML subset** between `---` lines. Implement the parser in `frontmatter.php`; do not vendor a YAML library. Supported:

- `key: value` — value is a string; `"quoted"` and `'quoted'` strip quotes; `true/false` → bool; integers → int; `null`/empty → null.
- `key:` followed by indented `- item` lines → list of scalars.
- `key:` followed by indented `sub: value` lines → one level of nested map. Lists of maps are supported only as `- key: value` blocks with further indented `key: value` lines (needed for `included`, `facts`, `features`). Nothing deeper.
- Multi-line strings: `key: >` folded block (join with spaces) and `key: |` literal block.
- `#` comments on their own line.

Serialization (`frontmatter::dump`) must round-trip everything the admin writes.

Common fields (all types):

| Field | Required | Notes |
|---|---|---|
| `title` | yes | the H1 |
| `seo_title` | no | full `<title>` override; else `title . title_suffix` (home: `title` alone) |
| `description` | yes for publish | meta description; admin blocks publish if empty or > 170 chars; renders trimmed to 160 |
| `path` | no | absolute path override, must start/end with `/`; used for legacy URLs |
| `date` | yes | `YYYY-MM-DD`; `datetime` optional `YYYY-MM-DDTHH:MM` |
| `updated` | no | `YYYY-MM-DD`; falls back to `date` |
| `author` | no | string; falls back to `config.author_default.name` |
| `hero` | no | absolute path under `/media/`, `/assets/`, or `/wp-content/uploads/` |
| `hero_alt` | required if `hero` | |
| `excerpt` | no | card blurb; else first paragraph of body, stripped, max 180 chars |
| `tags` | no | list |
| `region` | no | string (e.g. Chaco, Itapúa) |
| `draft` | no | bool; drafts never render publicly, never in sitemap/feed/listings |
| `noindex` | no | bool |
| `canonical` | no | absolute URL override |
| `layout` | pages only | `default` (default) `home` `faq` `contact` `hub` |
| `order` | services | int, listing order |
| `featured` | no | bool |

Type-specific fields are in §4. Unknown keys are preserved (round-tripped) and exposed to templates.

Body: Markdown (Parsedown, safe mode OFF because only the admin writes content) with these extensions in `markdown.php`:

- `:::tip Título opcional` … `:::` → `<aside class="tip"><p class="tip__label">Título</p>…</aside>`. Also `:::note` and `:::warning` with the same shape. Nested markdown inside is rendered.
- Every `h2`/`h3` gets an `id` slug (deduplicated), so posts can be deep-linked (`/post/#3-chaco`).
- External links get `rel="noopener"`; links to `wa.me` get `class="wa-link"`.
- `![alt](src)` images render as `<figure><img loading="lazy" decoding="async" alt src width height><figcaption>` when a title is given; width/height are read from the file when it exists locally. Images with empty alt are rendered with `alt=""` only if the markdown alt is literally `-` (decorative); otherwise an empty alt is an admin validation error, not a runtime one.

---

## 4. Content types (`types.php`)

| Type | Folder | Default path | Template | Extra front matter | JSON-LD |
|---|---|---|---|---|---|
| `page` | `content/pages/` | `/<slug>/` | by `layout` | `layout`, home-only fields (§4.1) | `WebPage` (home: `WebSite` + `WebPage`), `FAQPage` when layout=faq |
| `service` | `content/services/` | `/<slug>/` | `service.php` | `intro` (1 para), `included` (list of 4–8 strings), `cta_text` | `Service` (provider → org) + `BreadcrumbList` |
| `post` | `content/posts/` | `/blog/<slug>/` | `post.php` | `reading_time` (auto) | `BlogPosting` + `BreadcrumbList` |
| `news` | `content/news/` | `/novedades/<slug>/` | `post.php` | `source_url`, `source_name` (for media mentions) | `NewsArticle` + `BreadcrumbList` |
| `trip` | `content/trips/` | `/viajes/<slug>/` | `post.php` with factbox | `facts`: `duration`, `price_from`, `currency` (PYG/USD), `departure`, `group_size`, `best_season`, `difficulty`; `itinerary` list of `{day, title, text}` | `TouristTrip` + `BreadcrumbList` |
| `activity` | `content/activities/` | `/actividades/<slug>/` | `post.php` with factbox | `facts`: `location`, `duration`, `price_from`, `currency`, `best_season`, `difficulty`; `map_url` | `TouristAttraction` + `BreadcrumbList` |

Type labels (singular/plural, hub H1, breadcrumb names) come from `lang/<lang>.php` and can be overridden per site in `config['labels']`.

### 4.1 Home page fields (`content/pages/home.md`, `layout: home`)

```
hero_kicker, hero_title, hero_text, hero_cta_label, hero_cta_href, hero_secondary_label, hero_secondary_href, hero (image), hero_alt
features:            # 3–4 "why us" blocks
  - title: ...
    text: ...
    icon: compass    # name of an inline SVG icon in templates/partials/icons.php (compass, map, shield, chat, car, passport, sun, star)
stats:               # optional; omitted entirely when empty (no "0+")
  - value: "..."
    label: "..."
gallery: true        # renders content/data/gallery.json
testimonials: true   # renders content/data/testimonials.json
show_services: true
show_posts: true
faq_tags: [home]     # subset of faq.json by tag
cta_title, cta_text, cta_label, cta_href
```

Body markdown = the intro section under the hero.

### 4.2 Data collections (`content/data/*.json`)

- `faq.json`: `[{ "q": "...", "a": "markdown", "tags": ["home","servicios"] }]` — one canonical set. The `/faq/` page (layout `faq`) renders all; `faq-accordion.php` partial renders a tag-filtered subset elsewhere. `FAQPage` JSON-LD **only** on the faq layout page.
- `testimonials.json`: `[{ "name": "Rodrigo B.", "text": "...", "trip": "Ybytyruzú", "rating": 5 }]`
- `team.json`: `[{ "name": "...", "role": "...", "photo": null, "bio": null }]`
- `gallery.json`: `[{ "src": "/media/...", "alt": "...", "caption": "...", "category": "Rutas del Agua" }]`

The admin edits these with a simple form (add/remove rows) — see §8.

---

## 5. Router (`router.php`)

Order of evaluation for `GET`/`HEAD`:

1. Host/scheme canonicalisation: if `force_https` and request is http, or `force_host` set and host differs → 301 to canonical (skip when host is `127.0.0.1`/`localhost`).
2. Fixed routes: `/sitemap.xml`, `/feed/` (and `/feed` → 301 `/feed/`), `/robots.txt`, `/admin` and `/admin/*`, `/preview/*`, `/enviar/` (POST only; GET → 302 to contact page).
3. Path normalisation: decode, collapse `//`, strip query for matching. If the path has an extension-less last segment and no trailing slash → 301 to the slash form (`/blog` → `/blog/`). Paths with a file extension never get a slash appended (they fall through to 404).
4. `redirects` map → 301. `gone` list → 410 with the 404 template body (status 410).
5. Content index lookup (§5.1): exact path → render.
6. Hub routes: `/hub/` and `/hub/page/N/` (N ≥ 2; `/hub/page/1/` → 301 `/hub/`).
7. 404 template, status 404. Never soft-404.

`POST` is accepted only for `/enviar/` and `/admin/*`; anything else → 405.

### 5.1 Content index

`content::index()` scans all enabled type folders, parses front matter only (not the body), and produces `path → ['type','slug','file','title','date','updated','draft','noindex',…]`. Cached in `site/cache/index.php` (var_export) keyed by the max mtime of content dirs; rebuilt when any content file is newer, or when `admin` publishes. Duplicate paths across files are a hard error surfaced in the admin dashboard and in `verify.php`.

Legacy WordPress paths are matched by exact `path:` override on the content file — no special casing in the router.

---

## 6. SEO head (`seo.php`)

`seo::head(array $ctx): string` builds, in this order:

```
<title>…</title>
<meta name="description" content="…">
<link rel="canonical" href="ABS">
<meta name="robots" content="noindex, nofollow">            only when noindex/draft/preview/staging
<meta property="og:type|og:site_name|og:locale|og:url|og:title|og:description|og:image(+width/height when known)">
<meta name="twitter:card" content="summary_large_image|summary">
<link rel="alternate" type="application/rss+xml" title="…" href="/feed/">
<link rel="icon" …> <link rel="apple-touch-icon" …>
<meta name="theme-color" content="var from config['theme_color']">
<script type="application/ld+json">…</script>            one script, `@graph` array
```

Rules:
- `<title>`: `seo_title` if set, else `title . title_suffix`; on the home page never append the suffix. Hub pages use `config.hubs[path].title`. Paginated hubs append ` – Página N` (from lang file).
- Canonical is always the absolute final URL of the page (`base_url . path`), pagination included, unless `canonical` override. Query strings are never part of the canonical.
- `og:image`: `hero` → absolute; else `config['default_og_image']`.
- Staging: also send header `X-Robots-Tag: noindex, nofollow` on every response including sitemap.

JSON-LD `@graph` always contains the `Organization` node (`@type` from `config.schema.type`, `@id` `base_url#org`, name, url, logo, telephone, email, address as `PostalAddress`, `sameAs` from socials, `areaServed`, `founder` as Person when set, `priceRange`) and a `WebSite` node (`@id base_url#website`, `publisher` → org). Per-page nodes per §4. `BreadcrumbList` = Home → (hub) → page. Dates in ISO 8601 with timezone `-03:00`... use `America/Asuncion` from `date_default_timezone_set` in bootstrap and format with `c`.

---

## 7. Rendering and cache (`render.php`)

- Templates are plain PHP files receiving `$site` (config), `$page` (content array with `html` body), `$t` (i18n closure), `$seo` (head html), and helpers `e()`, `url()`, `asset()`.
- `asset('/engine/assets/base.css')` appends `?v=<filemtime>` for cache busting.
- Full-page cache: for GET, status 200, no session, not preview, not admin, not staging-debug: write `site/cache/pages/<sha1(path)>.html` and serve it on the next hit with header `X-Cache: HIT`. Invalidation: `render::purge()` deletes the directory; called on every admin publish/unpublish/upload/data-save and by `tools/build.php`. Cache also bypassed when `config.debug`.
- Response headers: `Cache-Control: public, max-age=300` for pages; `Content-Type: text/html; charset=utf-8`; `X-Content-Type-Options: nosniff`; `Referrer-Policy: strict-origin-when-cross-origin`; `Content-Security-Policy` is **not** set (inline JSON-LD and site scripts; keep it simple).

---

## 8. Admin (`/admin/`)

Routes (all under `/admin/`, GET unless noted):

| Route | Purpose |
|---|---|
| `/admin/` | login form (POST `/admin/login`) or dashboard when authenticated |
| `/admin/logout` (POST) | |
| `/admin/dashboard` | counts per type, drafts, last 10 edits, index errors (duplicate paths, missing descriptions/alt), link to export |
| `/admin/content/<type>/` | list: title, path, date, status, edit link, "new" button |
| `/admin/content/<type>/new` and `/admin/content/<type>/<slug>/edit` | editor (POST `/admin/content/<type>/save`) |
| `/admin/content/<type>/<slug>/delete` (POST) | moves the file to `site/data/trash/<timestamp>-<slug>.md`, purges cache |
| `/admin/data/<name>` | row editor for `content/data/<name>.json` (POST save) |
| `/admin/media` | upload (POST), list of `/media/**` with copy-snippet buttons |
| `/admin/export` (POST) | streams a zip of `content/`, `media/`, `data/leads` |
| `/admin/redirects` | read-only view of config redirects + gone list, and a check field: "does path X resolve?" |

Auth & hardening:
- Single password, bcrypt hash in `config.local.php`. `password_verify`. Login rate limit: 5 failures per 15 minutes per IP, stored in `site/cache/ratelimit/`. Session cookie: `httponly`, `secure` when https, `samesite=Strict`, name `vjsess`. Regenerate id on login. Idle timeout 4 hours.
- CSRF token in session, hidden field on every form, checked on every POST; mismatched → 403.
- `Cache-Control: no-store` and `X-Robots-Tag: noindex` on all admin responses; `robots.txt` disallows `/admin/`.
- Slug: auto from title (`slugify`: transliterate accents, lowercase, hyphens), editable; server rejects slugs failing the regex, colliding with another file, with a hub path, a redirect source, or a fixed route.

Editor (`templates/admin/edit.php` + `admin.js`):
- Fields generated from `types.php` field definitions (text, textarea, markdown, date, bool, list, image, facts-map, itinerary-list).
- `title` and `description` show live counters with pixel-ish estimates (title 580px ≈ 60 chars, description 160 chars) and a Google-snippet preview (title, URL, description).
- Markdown body textarea with a toolbar: bold, H2, H3, link, image (opens upload), tip block. Rendered preview pane below via POST `/admin/preview-md` (returns HTML fragment).
- "Save draft" (draft: true) and "Publish" (draft: false). Publish validation: `title`, `description` non-empty and ≤ 170 chars, `date` valid, `hero_alt` when `hero`, no `![]()` with empty alt in the body, slug valid. Errors listed at top, nothing written.
- Preview button: saves as draft and opens `/preview/<type>/<slug>/?t=<hmac_sha256(type/slug, preview_secret)>`, which renders the page with noindex regardless of draft state.
- Files are written atomically (temp file + rename), with `updated` set to today on publish when the body or title changed.
- Raw front matter "Advanced" collapsible textarea for keys the form does not model; it is merged last.

---

## 9. Images (`images.php`)

- Upload via admin: field `file` + required `alt`. Accept jpeg/png/webp; verify with `finfo` **and** `getimagesize`; max 12 MB; reject otherwise.
- Store original re-encoded (strip metadata) as `site/media/YYYY/MM/<slug>.<jpg|png>` (`slug` from provided name or original filename), plus variants `<slug>-480.webp`, `-960.webp`, `-1600.webp` (only widths smaller than or equal to the original; always at least one). Quality 82.
- Return a markdown snippet `![alt](/media/YYYY/MM/<slug>.jpg)` and the `hero` path. Templates rendering `hero` emit `<picture>` with the webp `srcset` when variants exist next to the file; markdown images do the same via `markdown.php` when the src is under `/media/`.
- Legacy images under `/wp-content/uploads/...` are plain files in `site/static/` (copied to the doc root by build) and are referenced by their original absolute path. The engine treats them like any other image; no variants.
- If GD is unavailable, store the original only and log a warning; never fail the upload.

---

## 10. Lead form (`leads.php`, `partials/lead-form.php`)

Form fields: `name` (required), `phone` (required, free text, normalised to digits for WhatsApp), `email` (optional, validated when present), `topic` (select, options from `config['leads']['topics']` or the enabled service titles), `message` (required, ≤ 3000 chars), honeypot `website` (must be empty; rendered off-screen, `tabindex=-1`, `autocomplete=off`), `ts` (render timestamp signed with `preview_secret`; reject submissions younger than 3 seconds or older than 24 h), CSRF not required (public form, session-free), rate limit 5 per IP per hour.

On valid POST:
1. Append a JSON line to `site/data/leads/YYYY-MM.jsonl` (always; this is the fallback of record).
2. `mail()` to `config.leads.to` with `Reply-To` = submitter email when present, subject `subject_prefix + topic + ' – ' + name`, plain-text body. Failure is logged, not shown.
3. If `leads.vendercrm.endpoint` and `tenant_key` are set: POST JSON to the endpoint (see `/root/.claude/skills/synced/*/vendercrm-lead-capture/references/php.md` for the exact payload/headers; copy that contract). Timeout 5 s; failure logged.
4. Redirect 303 to the contact page with `?enviado=1` (the template shows a success banner and a "continuar por WhatsApp" button prefilled with the same message). If the request has `Accept: application/json` or `X-Requested-With`, return JSON `{ok:true}` instead.

WhatsApp: `partials/whatsapp.php` renders the floating button and any inline CTA as `https://wa.me/<whatsapp_e164>?text=<urlencoded>`; the message defaults to `config.contact.whatsapp_default_text` and, on service/trip/activity pages, includes the page title. `site.js` updates the contact page's WhatsApp link live as the visitor types name/topic/message.

---

## 11. Design system (`engine/assets/base.css`, templates)

Mobile-first, no framework, no build step. The engine ships a complete, good-looking default; sites override tokens in `theme.css`.

Tokens (`:root` in base.css, every one overridable):
`--color-bg, --color-surface, --color-text, --color-muted, --color-primary, --color-primary-contrast, --color-accent, --color-border, --radius, --shadow, --font-body, --font-display, --measure (65ch), --container (1200px)`. Dark mode is **not** required.

Components (each a partial or a base.css block): header (logo, nav, phone link, WhatsApp CTA button, hamburger under 900px), hero (image with overlay, kicker, H1, text, two CTAs), breadcrumbs, card grid (2/3 columns), article body typography (`.prose`: h2/h3 rhythm, lists, blockquote, figure, tables, `.tip` aside), fact box (definition list), itinerary (ordered steps), FAQ accordion (native `<details>/<summary>` with a plus/minus indicator — no JS required), testimonials (simple grid, no carousel), gallery (responsive grid with captions), team grid, CTA band, lead form, footer (brand blurb, nav, contact block with `tel:`/`mailto:` links, socials, copyright with current year), pagination, skip link, focus styles, `prefers-reduced-motion` respected.

Performance/a11y bar: no render-blocking JS (`defer`), single CSS file, system font stack by default (a site may add one Google Font in theme.css via `<link>` slot `config['head_extra']`), all images lazy except the hero (`fetchpriority="high"`), landmarks (`header/nav/main/footer`), one H1 per page, colour contrast ≥ 4.5:1 for text tokens in the default theme.

---

## 12. Verification (`tools/verify.php`) and URL contract (`sites/<domain>/urls.txt`)

`urls.txt` format, one per line, `#` comments:

```
/                                   200
/agencia-de-viaje/                  200
/paquetes/                          301 /servicios/
/elementor-9/                       410
/blog                               301 /blog/
/sitemap.xml                        200
/feed/                              200
/definitely-missing/                404
```

`verify.php <domain> [--base=URL]`:
1. If `--base` not given: run `build.php`, start `php -S 127.0.0.1:8080` with the dev router in the background, wait for it, run checks, stop it.
2. For each line: request with redirects **disabled**; assert status; for 301/302 assert `Location` equals the expected target (relative or absolute both accepted).
3. For every 200 HTML page: exactly one `<title>` (non-empty), exactly one `meta[name=description]` (non-empty), exactly one `link[rel=canonical]` whose href equals `base_url + path`, exactly one `<h1>`, every `<img>` has an `alt` attribute, every `application/ld+json` block parses as JSON and contains the Organization node, no occurrence of the strings `TourDen`, `Harbert`, `Lorem`, `No content is added yet`, `0+`. Sitemap: valid XML, every `<loc>` returns 200 when requested (cap at 200 URLs), and every 200 content URL from `urls.txt` appears in it unless noindex. Feed: valid XML.
4. Also scan `content/` for: duplicate paths, published items with missing `description`, `hero` without `hero_alt`, unknown type folders.
5. Print a table; exit 1 on any failure.

`tools/build.php` and `verify.php` must run on Linux with only PHP; no shell dependencies beyond `php` itself.

CI (`.github/workflows/ci.yml`): `php -l` over every `.php` file; `php tools/verify.php viaje.com.py` and `php tools/verify.php thingstodoinparaguay.com` on PHP 8.4 (`shivammathur/setup-php`, extensions `gd, mbstring, fileinfo, zip`).

---

## 13. `.htaccess` (generated by `build.php` from `engine/htaccess.template` with config values)

- `RewriteEngine On`; `RewriteBase /`.
- HTTPS + host canonicalisation are handled in PHP (§5) so the rules stay portable; the template includes a commented-out Apache variant.
- `RewriteCond %{REQUEST_FILENAME} !-f` / `!-d` → `index.php` (`[L,QSA]`). Directory requests without index are also routed to PHP (`DirectorySlash Off`, and `RewriteCond -d` combined with a rule that sends `/site/…` and `/engine/…` to 403).
- Deny direct access: `site/content`, `site/data`, `site/cache`, `site/config*.php`, `engine/` (except `engine/assets/`), any `*.md`, `*.json` under `site/` — using `<IfModule mod_rewrite>` rules returning 403 (portable across Apache/LiteSpeed; do not rely on `<Directory>`).
- Static caching: `ExpiresByType` (if mod_expires) 1 year for css/js/images/fonts, and `Header set Cache-Control` when mod_headers exists, all inside `<IfModule>`.
- `php_value upload_max_filesize 16M` / `post_max_size 20M` inside `<IfModule mod_php>`; Hostinger LiteSpeed may ignore; harmless.
- `Options -Indexes`.

---

## 14. Seed sites (phase 1 delivers these skeletons; phases 2–3 fill them)

`sites/viaje.com.py/`: config with the real NAP from plan §1/§7 (phone `+595 995 628 862`, email `hola@viaje.com.py`, Edificio Skytower, Asunción), `lang: es`, nav `Inicio · Servicios · Blog · FAQ · Nosotros · Contacto`, hubs `/servicios/` and `/blog/` (news/trips/activities hubs enabled but empty), redirects and gone list exactly as plan §5, and **stub content files for every 200 row in plan §5** (title from scan §1, one-line description, one-paragraph body with a `TODO-PHASE-2` marker so it's visible) so that `verify.php viaje.com.py` passes end-to-end on the skeleton. `urls.txt` = plan §5 in full.

`sites/thingstodoinparaguay.com/`: config with `lang: en`, `html_lang: en`, same contact NAP, nav `Home · Things to do · Trips · Blog · About · Contact`, hubs `/things-to-do/` (activity), `/trips/` (trip), `/blog/` (post), stub home/about/contact/faq pages, `urls.txt` with those. `type_paths` for this site: activity `/things-to-do/`, trip `/trips/`, post `/blog/`, news `/news/`, service `/services/`.

`engine/lang/en.php` and `es.php` complete for every UI string used by templates (labels, buttons, breadcrumbs "Inicio/Home", "Leer más/Read more", form labels and errors, 404 copy, pagination, "Publicado el", "Actualizado", "Tiempo de lectura", "Servicios incluidos", "Preguntas frecuentes", "Consultar por WhatsApp", type singular/plural names).

---

## 15. Definition of done (phase 1)

1. `php -l` clean on every file.
2. `php tools/verify.php viaje.com.py` and `php tools/verify.php thingstodoinparaguay.com` exit 0.
3. Admin smoke test performed by the builder with the built-in server: set a password hash in `dist/.../site/config.local.php`, log in, create a post with an uploaded image (any generated PNG), publish, confirm the public page renders with `<picture>`, the sitemap lists it, the feed lists it, and the page cache HIT header appears on second load; then delete it and confirm 404.
4. Lead form smoke test: POST via curl with valid fields → 303 and a line in `site/data/leads/`; honeypot filled → rejected; too-fast `ts` → rejected.
5. `README.md` at repo root: local dev (`php tools/serve.php <domain>`), how to add a site, how to publish content in the admin, how to deploy (points to plan §8 and the `dist/` layout), how to set the admin password.
6. `KNOWN-ISSUES.md` lists anything deliberately left out.
7. No site-specific strings in `engine/`. `grep -r "viaje" engine/` returns only the dev fixtures/comments, never copy.
