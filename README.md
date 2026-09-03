# viaje.com.py + thingstodoinparaguay.com

A shared PHP flat-file engine and two sites built on it. No database, no Composer,
no build step for the browser — it runs on any Hostinger shared plan with PHP 8.1+,
`gd`, `mbstring` and `fileinfo`.

- `plan.md` — the migration plan, the locked decisions and the URL contract.
- `docs/engine-spec.md` — the build contract for the engine.
- `KNOWN-ISSUES.md` — what is deliberately unfinished.

```
engine/            shared code (router, content model, SEO, admin, templates, assets)
sites/<domain>/    one folder per install: config, theme, content, media, urls.txt
tools/             build.php · verify.php · serve.php
dist/<domain>/     build output — the document root you upload (git-ignored)
```

## Local development

```bash
php tools/serve.php viaje.com.py            # builds dist/ and serves on :8080
php tools/serve.php thingstodoinparaguay.com 8081
```

`serve.php` rebuilds `dist/<domain>/` from the repo every time, so edit files under
`engine/` and `sites/<domain>/` — never inside `dist/`.

## Verifying

```bash
php tools/verify.php viaje.com.py           # boots its own server, checks everything
php tools/verify.php viaje.com.py --base=https://staging.example.com
```

`verify.php` asserts every row of `sites/<domain>/urls.txt` (status and redirect
target, redirects disabled), then on every 200 HTML page: one `<title>`, one meta
description, one self-referencing canonical, one `<h1>`, an `alt` on every image, a
JSON-LD graph containing the Organization node, and none of the old theme's leftover
strings. It also validates `sitemap.xml` and `/feed/` and scans `content/` for
duplicate paths, missing descriptions and heroes without alt text. Exit code 0 means
everything passed; this is the CI merge gate.

## Adding a site

1. `cp -r sites/thingstodoinparaguay.com sites/<new-domain>` and empty its `content/`.
2. Edit `config.php`: `domain`, `base_url`, `force_host`, `lang`, `site_name`,
   `contact`, `nav`, `hubs`, `type_paths`. Required keys are validated at boot and a
   missing one fails with a clear message.
3. Write `urls.txt` — one line per URL you promise to keep, `<path> <status> [target]`.
4. `php tools/verify.php <new-domain>`.

## Publishing content

Content lives in `sites/<domain>/content/<type>/<slug>.md`: a small YAML front matter
block plus markdown. Types are `page`, `service`, `post`, `news`, `trip`, `activity`.
`path:` overrides the URL, which is how legacy WordPress URLs are kept byte-for-byte.

Markdown gets three extensions: `:::tip Título` … `:::` callouts (also `:::note`
and `:::warning`), automatic ids on every `h2`/`h3` for deep links, and `<picture>`
with WebP sources for images that have generated variants.

Day to day you do not touch files: **`/admin/`** is the publishing UI. It lists and
edits every type from one form, generates the slug, counts title and description
length against a live Google-snippet preview, uploads images (refusing to save one
without alt text, then generating 480/960/1600 WebP variants), previews markdown,
saves drafts, publishes, and edits `content/data/*.json` (FAQ, testimonials, team,
gallery) as rows. Publishing writes the file atomically, clears the page cache and
regenerates the sitemap and feed on the next request.

### Setting the admin password

```bash
php engine/bin/hash-password.php
```

Paste the line it prints into `sites/<domain>/config.local.php` (start from
`config.local.example.php`; the file is git-ignored and never leaves the server).
Until it exists, `/admin/` shows a setup page and the public site is unaffected.

## Deploying

```bash
php tools/build.php viaje.com.py
```

That assembles `dist/viaje.com.py/` — `index.php`, `.htaccess`, `engine/`, `site/`
and the contents of `static/` copied to the root. Upload that directory as the
document root (hPanel File Manager, FTP or hPanel Git). Re-running it is safe: the
engine is mirrored fresh, but `site/content`, `site/media`, `site/data`,
`site/cache` and `site/config.local.php` are never overwritten — after cutover the
server is the source of truth for those (plan §2.4). Use `--fresh` to rebuild
everything from the repo, which is what `verify.php` and `serve.php` do.

The delivery mechanism, staging and the cutover runbook are phase 4; see plan §8.
