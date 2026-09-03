# viaje.com.py + thingstodoinparaguay.com — Migration & Build Plan

Status: **APPROVED 2026-09-03 (Anton). Decisions in §1 are locked. Build in progress; see §9.**
Source of truth for existing content: `viaje-com-py-scan.md` (Anton's manual extraction, 2026-09-02).
Only §1, §2, §6, §7, §8 of that file were read for this plan. §3/§5 are consumed by the build sessions.

| Phase | Model | Prompt file (written after approval) | What it delivers |
|---|---|---|---|
| 0 | Fable (this conversation) | — | This plan, the engine spec, the URL contract. |
| 1 | Opus | `prompts/opus-1-engine.md` | Shared PHP flat-file engine: router, content model, SEO head, admin publishing, redirects, sitemap, lead form. |
| 2 | Sonnet | `prompts/sonnet-2-content.md` | viaje.com.py theme + every real page/post ported from scan §3, images, FAQ merge, footer/brand fixes. |
| 3 | — | **superseded 2026-09-03** | thingstodoinparaguay.com is built in its own repo, `antonmarklundcom/thingstodoinparaguay` (own plan.md, native PHP + SQLite, its own phase table). Nothing in this repo ships for that domain. The `sites/thingstodoinparaguay.com/` seed stays only as the engine's second-site test fixture. |
| 4 | Sonnet | `prompts/sonnet-4-deploy-cutover.md` | Deploy pipeline, staging, URL-audit script, cutover runbook, Search Console steps — viaje.com.py only. |
| R | Fable (Anton opens it) | — | One pre-cutover review of the staging site against the URL contract. Optional; see §11. |

---

## 1. Decisions — LOCKED (Anton, 2026-09-03). Build sessions never reopen these.

Anton's approval notes: two separate Hostinger HTML/PHP installs (one per domain), no further questions, keep the old URLs in v1, make the best possible site, Fable directs from its own conversation and Sonnet/Opus do 95%+ of the work.

Defaults chosen for the items that were open (no questions asked, per Anton):
- Positioning: **domestic Paraguay tourism** (the ranking content). The cinematic outbound prompt stays parked in `docs/`.
- ~~thingstodoinparaguay.com is built net-new in English on the same engine (path B).~~ **Superseded 2026-09-03:** the domain turned out to be live with 121 indexed URLs, and it is being rebuilt with its own URL contract in `antonmarklundcom/thingstodoinparaguay`. This repo ships viaje.com.py only.
- Deploy: `tools/build.php` produces a per-site document root in `dist/<domain>/` that works with hPanel File Manager upload, FTP, or hPanel Git; a GitHub Actions FTP job is included but stays inert until FTP secrets exist.
- Legacy `/wp-content/uploads/` images: the scan holds no image URLs and this environment cannot reach the live site. The `static/wp-content/uploads/` slot is structured; Anton copies the folder from hPanel before cutover (runbook step). New site imagery comes from the image pass in phase 2/3.
- Counters removed; email `hola@viaje.com.py`; blog posts differentiated (§6); FAQ merged.

1. **Replace WordPress with a shared PHP flat-file engine**, one codebase, two site configs. Not a static generator, not a database CMS. Reasoning in §2.
2. **Publishing = a password-protected `/admin/` form** that writes a markdown file and uploads images. No code touched per post. Content also lives as plain files, so it is diff-able and backup-able.
3. **Every real path in scan §1 resolves to the same URL, 200, self-canonical.** Placeholder paths get 301s or 410s (table in §5). WordPress image URLs under `/wp-content/uploads/` are kept byte-for-byte.
4. **Contact email = `hola@viaje.com.py`** everywhere. The `.com` variants are typos. Anton confirms the mailbox exists (§7).
5. **`/paquetes/`, `/paquete-individual/`, `/servicio-unico/` are not rebuilt.** The site's own copy is explicitly anti-catalog. "Paquetes" leaves the nav; the paths 301 to `/servicios/`.
6. **The two real blog posts are kept as two URLs and differentiated, not merged.** Default position; §6 explains, and GSC data can flip it.
7. **"0+" counters are removed**, replaced by non-numeric proof (founder story, testimonial, "desde 20XX"), unless Anton supplies real figures.
8. **Contact form is real:** PHP handler, WhatsApp-first composer, email delivery, optional VenderCRM lead push (existing skill covers the endpoint).
9. **JSON-LD on every page:** `TravelAgency` (site-wide), `BlogPosting` + `BreadcrumbList` on posts, `FAQPage` on `/faq/` only, `WebSite` on home.
10. **Fable is never a subagent, spawned session, or build-phase model.** Phases run on Opus and Sonnet only (fable-cost-guardrail).

Open items that need Anton before the corresponding phase: §7.

---

## 2. Architecture — how "publish a post with good SEO" works on shared Hostinger

### 2.1 Options considered

| Option | Publish flow | Verdict |
|---|---|---|
| Markdown in git → static generator (Eleventy/Hugo) → FTP via GitHub Actions | Commit a `.md` file, wait for the action | Rejected. "Commit a file" is touching code for most people, builds fail silently, image handling is manual, and Actions→FTP is the flaky link. |
| Off-the-shelf PHP flat-file CMS (Grav, Kirby, Pico) | Their admin | Rejected. Grav/Kirby are as heavy as WordPress to reason about, ship their own upgrade treadmill, and constrain URL routing in ways that fight the "exact legacy paths" requirement. |
| Database CMS (Node/Next + MySQL admin) | Custom admin | Rejected. Burns a scarce Hostinger Node slot on a content site; overkill for ~20 pages. |
| **Custom PHP flat-file engine + tiny admin** | Fill a form, click Publish | **Chosen.** ~1.5k lines of PHP, zero dependencies beyond a vendored markdown parser, runs on any Hostinger shared plan, and we own every byte of the `<head>`. |

### 2.2 The engine

```
engine/                      shared, identical on both sites
  index.php                  front controller (.htaccess rewrites everything here)
  lib/router.php             path → content file | redirect | 404/410
  lib/content.php            front-matter + markdown loader, type registry, listing/pagination
  lib/seo.php                <title>, meta description, canonical, OG/Twitter, JSON-LD builders
  lib/render.php             template rendering, page cache (HTML files in cache/, invalidated on publish)
  lib/markdown/Parsedown.php vendored single file
  lib/images.php             upload, resize to max widths, WebP + fallback, requires alt text
  lib/leads.php              form handler: validation, honeypot, rate limit, mail(), WhatsApp URL, VenderCRM push
  templates/                 base.php, home.php, service.php, hub.php, post.php, faq.php, contact.php, 404.php
  admin/                     login, list, edit form (per type), image upload, SEO preview, publish/draft
  sitemap.php, feed.php, robots.php

sites/viaje.com.py/          per-site
  config.php                 domain, lang (es-PY), brand, NAP, socials, nav, footer, schema defaults, redirect map
  config.local.php           admin password hash, SMTP/VenderCRM keys — NOT in git
  theme.css                  design tokens + overrides on top of engine base CSS
  content/<type>/<slug>.md   pages, services, trips, activities, posts, news
  assets/                    logo, hero images
  static/wp-content/uploads/ legacy WordPress images at their original paths

sites/thingstodoinparaguay.com/   same shape, lang en, its own content and theme
```

**Content types** (one folder each, one template each): `page`, `service`, `trip`, `activity`, `post`, `news`. Each markdown file carries front matter:

```
title, seo_title (optional), description (required, length-checked), path (optional override),
date, updated, author, hero, hero_alt (required if hero), excerpt, tags, region,
draft, noindex, canonical (override), schema extras per type (e.g. trip: duration, price_from)
```

**Default URL scheme per type**, with `path:` overriding for legacy slugs:

| Type | Default path | Hub |
|---|---|---|
| page | `/<slug>/` | — |
| service | `/<slug>/` (matches the 5 existing) | `/servicios/` |
| trip | `/viajes/<slug>/` | `/viajes/` |
| activity | `/actividades/<slug>/` | `/actividades/` |
| post | `/blog/<slug>/` (the two legacy posts override to root via `path:`) | `/blog/` |
| news | `/novedades/<slug>/` | `/novedades/` |

Hubs list their type's published items newest-first with pagination at `/blog/page/2/`. Every hub gets a self-canonical; the missing canonical on `/blog/` is fixed structurally.

**What the admin form does that makes SEO "good by default":**
- Title and meta description fields with live pixel/character counters and a Google-snippet preview.
- Slug auto-generated from title, editable, checked against the router for collisions and against the redirect map.
- Image upload refuses to save without alt text; generates WebP + responsive widths; stores under `/media/<yyyy>/<file>`.
- A "tip callout" block syntax (`:::tip … :::`) so the flagship post's inline-tip pattern becomes a reusable styled component.
- Publish writes the `.md`, clears the page cache, regenerates `sitemap.xml` and the RSS feed, pings nothing (Google dropped sitemap pings; Search Console picks it up).
- Draft/preview mode via a signed URL, served with `X-Robots-Tag: noindex`.

**Security baseline for `/admin/`**: single bcrypt-hashed password in `config.local.php`, PHP session with `SameSite=Strict`, CSRF token on every write, login rate-limit, uploads restricted by MIME sniff and re-encoded through GD, `.htaccess` denies direct execution in `media/` and `content/`. No user table, no roles: one owner, two sites.

### 2.3 Shared engine or separate?

**One engine, two deployments.** The sites share everything except config, theme tokens, and content. Bug fixes and admin improvements land once. The engine is language-aware from the first commit (all UI strings in `lang/es.php` / `lang/en.php`, `<html lang>` from config). They do not share a database or a login, and there is no hreflang between them because they are not translations of each other.

### 2.4 Content on the server vs. content in git

After cutover, **the server is the source of truth for `content/` and `media/`** (the admin writes there). Git holds the engine plus the seeded migration content. The deploy step (§8) syncs `engine/`, `sites/<x>/config.php`, `theme.css`, `assets/` and **never overwrites** `content/`, `media/`, `config.local.php`, or `cache/`. The admin has an "Export backup (zip)" button, and phase 4 adds a weekly Hostinger cron that zips content + media into a dated backup folder.

---

## 3. What the two sites are

### 3.1 viaje.com.py — migration
Rebuild the 13 real URLs from scan §1 with identical paths, port the copy from §3 verbatim (with the fixes in §6), add the new content types so future trips/activities/news have a home.

**Positioning conflict to resolve (§7 item 1):** the repo already contains `PROMPT-viaje-cinematic-scroll.md`, which positions viaje.com.py as *outbound* travel (USA, Europe, Asia from Asunción) with a JS-driven single-screen homepage. The live site that currently ranks is *inbound/domestic* Paraguay tourism. The ranking pages are the domestic ones. Recommendation: keep the domestic positioning for the migration and treat the cinematic page as a later homepage-hero experiment, not the launch homepage. A homepage whose text lives inside a scroll-driven sticky stage is a worse crawl surface than the current one.

### 3.2 thingstodoinparaguay.com — RESOLVED: Path A, built in its own repo

**Resolved 2026-09-03 (director).** The domain is live with real content (121 URLs). Its extraction, URL contract and rebuild live in `antonmarklundcom/thingstodoinparaguay` on a separate native PHP + SQLite codebase. No phase in this repo builds, fills or deploys that site; `docs/site-spec-ttdp.md` is archived, and `sites/thingstodoinparaguay.com/` remains only as the engine's second-site verify fixture. The text below is kept for history.
This session cannot reach the domain (network egress is blocked here, and was for viaje.com.py too). The plan handles both cases; **Anton answers which one applies (§7 item 2)**:

- **Path A — it is live with real content.** It needs the same extraction pass as viaje (sitemap → every URL → verbatim copy, titles, metas, images, redirect candidates). Anton runs the same manual scan, or a session with network access does it. Phase 3 then ports it exactly like phase 2 does for viaje, with its own URL contract table.
- **Path B — parked, empty, or a default install.** Net-new English site on the same engine. Phase 3 configures it, writes 8–12 seed articles (activity and trip pages keyed to real Paraguay destinations, using the viaje posts as factual source material but written fresh in English, no translation), and sets up the cross-links: TTDP articles link to viaje's Spanish service pages as the "book it" path, and viaje's English-speaking visitors are pointed to TTDP.

Either way the engine (phase 1) is unaffected, so phase 3 can be scoped precisely after the answer arrives without blocking phases 1–2.

---

## 4. Autonomy protocol (copied into every phase prompt)

1. Work until the phase's exit criteria all pass; never ask permission for in-plan work.
2. One PR per phase: branch `phase/<id>` off latest main; create, watch, and merge the PR when green. A red build is the session's own work. Never start on top of an unmerged previous phase.
3. Minor non-blocking issues → `KNOWN-ISSUES.md`, keep building.
4. Stop and ask only for: a missing credential with no graceful fallback, or a decision that would force a rewrite if guessed wrong (URL contract changes, content model shape, admin security model). Everything else: choose reasonably, record it in the build log, continue.
5. Missing env/config values never block: document in `config.local.example.php`, degrade gracefully.
6. Every phase prompt is re-runnable: check what exists on the branch first, continue from the first unmet exit criterion.
7. Sonnet-phase hard limits: no changes to the router, content model, SEO builders, admin write path, or redirect semantics. Work around and note in Backlog.
8. **Model cost guardrail:** Fable is never used for build phases, subagents, or spawned sessions. If a session believes it needs Fable, it stops and asks Anton with the reason.
9. Phase handoff only after four gates: PR merged green; exit checklist passed; pre-handoff audit (re-run verify scripts, adversarially re-read own merged diff); build-log entry committed. Then spawn the next phase as a new session (`create_session`, inherit environment and permission mode, never `plan`, model per the phase table, prompt `Read prompts/<next>.md in this repo and execute it.`).
10. Build log: before merging, append a dated 5–10 line entry to §9.

---

## 5. URL contract (viaje.com.py) — the non-negotiable table

Every row is checked by `tools/url-audit.php` (phase 4) against staging and again after cutover.

| Legacy path | Action | Target / notes |
|---|---|---|
| `/` | 200, keep | Gets a real `<title>` and description (currently none). H1 stays. |
| `/agencia-de-viaje/` | 200, keep | service template |
| `/asistencia-personalizada/` | 200, keep | service template |
| `/gestion-de-visas/` | 200, keep | service template |
| `/traslados/` | 200, keep | service template |
| `/vacaciones/` | 200, keep | service template |
| `/servicios/` | 200, keep | hub template + site-wide FAQ widget |
| `/nosotros/` | 200, keep | page template |
| `/faq/` | 200, keep | faq template, merged 16→deduped set, `FAQPage` schema |
| `/contacto/` | 200, keep | contact template with the real form |
| `/blog/` | 200, keep | hub template, **self-canonical added** |
| `/paraguay-destinos-imprescindibles-2026/` | 200, keep | post, `path:` override to root |
| `/destinos-imperdibles-2026/` | 200, keep | post, `path:` override to root |
| `/paquetes/` | **301** | → `/servicios/` |
| `/paquete-individual/` | **301** | → `/servicios/` |
| `/servicio-unico/` | **301** | → `/servicios/` |
| `/elementor-9/` | **410** | orphan empty draft |
| `/hello-world/` | **410** | WP default post; no equity worth passing |
| `/category/uncategorized/` | **301** | → `/blog/` |
| `/wp-sitemap.xml` and `/wp-sitemap-*.xml` | **301** | → `/sitemap.xml` |
| `/feed/` | 200, keep | engine RSS (WP emitted one; readers/aggregators may hold it) |
| `/wp-content/uploads/**` | 200, keep | served as static files at original paths (image search + hotlinks) |
| `/wp-json/*`, `/xmlrpc.php`, `/wp-login.php`, `?s=` | 404/410 | nothing to preserve |
| Missing trailing slash on any of the above | 301 | → trailing-slash form |
| http / www variants | 301 | → whatever the live site canonicalises to today (verify in §7 item 4) |

Rules the router enforces: exact match, one 301 hop maximum, no redirect chains through the WP forms, canonical always equals the final URL, 404 template returns a real 404 status.

**Ranking-preservation rules beyond URLs:**
- Titles of the 13 real pages: keep the existing H1 and slug; the new `<title>` is the H1 plus a short brand suffix, and hand-written meta descriptions are added. No rewriting the ranking posts' body copy except the differentiation in §6.
- Internal links: the header nav keeps every real destination; footer nav links the 13 real URLs.
- Staging is served with `X-Robots-Tag: noindex` (header, not `robots.txt`, so Google can still crawl it if asked and the header is dropped at cutover).
- Cutover runbook (phase 4): lower DNS TTL 48h ahead if hosting changes; swap; remove noindex header; submit `sitemap.xml` in Search Console; check Coverage and the "Pages" report daily for two weeks; keep the WordPress export and a full `wp-content/uploads` copy as rollback.

---

## 6. Fixes baked in (from scan §2 / §8)

| Gap | Fix | Phase |
|---|---|---|
| Missing `<title>` on home, template-default titles elsewhere | Hand-written `seo_title` per page, H1-first | 2 |
| No meta descriptions anywhere | Required field; Sonnet writes one per page from §3 copy | 2 |
| No canonical on `/blog/` | Structural: every route emits self-canonical | 1 |
| No JSON-LD | `TravelAgency` site-wide (NAP from config), `BlogPosting`+`BreadcrumbList` on posts, `FAQPage` on `/faq/`, `WebSite` on home | 1 (builders), 2 (data) |
| Email inconsistency | Single `contact.email` in config = `hola@viaje.com.py`; nothing hard-coded in templates | 1 + 2 |
| No contact form | `lib/leads.php` + contact template: name, phone (WhatsApp), email, message, trip type; WhatsApp composer button as the primary CTA; email + optional VenderCRM push | 1 (handler), 2 (page) |
| Missing image alt text | Admin requires alt; migration alt text written per image by Sonnet from surrounding copy and §8 item 4 category labels | 1 + 2 |
| "TourDen" footer, fake "Tour Packages" menu, "Harbert Spin" author widget, "BLOG Details" lorem kicker, "No content is added yet." blocks | None of it is ported. Footer = real brand blurb, real nav, contact block. Posts show a real author (Anton / "Equipo Viaje.com.py"). | 2 |
| "0+" counters | Removed (see Decision 7) | 2 |
| Two FAQ sets (6 + 10) | One canonical deduped set in `content/faq.md`; `/faq/` shows all, the site-wide widget shows a tagged subset of 5–6; schema only on `/faq/` | 2 |
| Blog-post overlap / cannibalization | **Differentiate, don't merge.** Keep `/paraguay-destinos-imprescindibles-2026/` as the pillar untouched. Re-angle `/destinos-imperdibles-2026/`: distinct title/H1 and intro around a different intent (seasonal/when-to-go or weekend-escape planning), trim the destination sections that duplicate the pillar to short summaries linking to the pillar's H2 anchors. Reasoning: both are indexed, both are real, and a 301 forfeits whatever the second one ranks for on its own. **Gate:** if Search Console shows the second post with ~zero impressions over 3 months, merge and 301 instead. This one edit is an Opus task inside phase 2 (judgment over two long texts), not Sonnet. | 2 |
| Iguazú stock hero on both posts | Replace on the differentiated post with a Paraguay image; keep on the pillar unless Anton objects | 2 |
| Discount badges "10% Off" etc. | Not ported | 2 |
| Team bios (names only) | Names + roles only, no invented bios; expandable later via admin | 2 |
| Newsletter "No content is added yet." | Dropped; can return later as a lead-form variant | — |

---

## 7. Human inputs (Anton) — and when each is first needed

| # | Needed | First needed by |
|---|---|---|
| 1 | Positioning: domestic-tourism (live site) vs outbound (cinematic prompt). Recommendation: domestic for launch. | Phase 2 |
| 2 | ~~thingstodoinparaguay.com: live with content, or empty/parked?~~ Resolved: live; handled in its own repo. | — |
| 3 | Confirm `hola@viaje.com.py` mailbox exists (or create it in hPanel). | Phase 2 |
| 4 | Canonical host of the live site (www or not, https) and current hosting: is WP on the same Hostinger account the new site will use? | Phase 4 |
| 5 | The `wp-content/uploads` folder (download via hPanel File Manager and drop into `sites/viaje.com.py/static/wp-content/uploads/`, or share access). This sandbox cannot fetch from the live site. | Phase 2 |
| 6 | What "hostinger-html-php-deploy" refers to: an existing script/skill of yours, hPanel's Git deploy, or FTP? It is not present in this session. | Phase 4 |
| 7 | Real numbers for the counters, or confirm removal. | Phase 2 |
| 8 | Live check the current contact page: does any form actually exist (network tab)? Affects nothing structural, only whether an old form endpoint should be redirected. | Phase 2 |
| 9 | VenderCRM tenant key for viaje leads (optional; form works without it). | Phase 2 |
| 10 | Search Console access confirmed for both domains; a 3-month performance export for the two blog posts (decides §6 merge gate). | Phase 2 / 4 |
| 11 | Social profile URLs for the footer and `sameAs` schema. | Phase 2 |
| 12 | Staging hostname (a Hostinger `*.hostingersite.com` or a subdomain). | Phase 4 |

---

## 8. Repo, GitHub, and deploy

- **Repo:** `antonmarklundcom/viaje` becomes the monorepo (`engine/`, `sites/`, `tools/`, `prompts/`, `plan.md`, `KNOWN-ISSUES.md`). `PROMPT-viaje-cinematic-scroll.md` moves to `docs/` untouched.
- **Branching:** `main` protected by convention; `phase/<id>` branches, one PR per phase, merged green before the next starts. Plan PR first, so phase 1 branches from a main that contains the plan.
- **CI (cheap, GitHub Actions):** `php -l` on every file, a PHPUnit-free smoke test (`tools/url-audit.php --local`) that boots the engine with PHP's built-in server and asserts every row of §5 plus title/description/canonical presence on every content file. Green CI is the merge gate.
- **Deploy (phase 4):** `tools/build.php <site>` assembles `dist/<site>/` (engine + site config/theme/assets + `.htaccess`). Delivery to Hostinger via whichever mechanism item 6 in §7 names; default assumption is hPanel Git deploy of the `dist` branch or an Actions FTP step with an explicit **exclude list** (`content/`, `media/`, `cache/`, `config.local.php`). Staging first, always.
- **Cutover:** the runbook in §5, executed by Anton with the phase-4 checklist, not by an agent (DNS and hosting swaps are the irreversible steps).

---

## 9. Build log & handoff

### 2026-09-03 — Director restart (Fable, session `archive-exhausted-chats`)
- The first Fable director session ran phase 1 as an in-session Opus subagent and died at the usage
  limit with the engine ~⅓ built; the snapshot was merged to `main` as a WIP commit. That shape
  (Fable kept alive while a subagent works) is the cache-waste pattern §11 rejects.
- Fix: phases now run as fresh Opus/Sonnet sessions that chain themselves, per §4 item 9. Prompt
  files written: `prompts/opus-1-engine.md` (finish the engine from the snapshot),
  `prompts/sonnet-2-content.md` (phases 2 + 3 + imagery, one PR), `prompts/sonnet-4-deploy-cutover.md`.
  `prompts/CONTINUE-fable.md` is superseded by these and kept only for reference.
- Phase 1 spawned on Opus from this session. Fable is next needed only for the pre-cutover review (§11).

### 2026-09-03 — Phase 1 complete (Opus, session `phase-1-engine`)

**What exists.** The whole of engine spec §0 is on disk and green: `bootstrap.php`, `dev-router.php`,
`htaccess.template`, `lib/{config,frontmatter,i18n,types,util,markdown,content,seo,render,images,leads,
admin,router}.php`, 9 page templates + 17 partials + 8 admin templates, `assets/{base.css,site.js,
admin.css,admin.js}`, `lang/{es,en}.php` (159 keys each, in parity), `bin/hash-password.php`,
`tools/{build,verify,serve}.php`, both `sites/<domain>/` seeds, `.github/workflows/ci.yml`, `README.md`,
`KNOWN-ISSUES.md`. The five inherited lib files were read against their spec sections and kept; the only
change to them was making the timezone a config key (see deviations). 64 PHP files, `php -l` clean.

**Deviations from the spec, and why.**
1. *Trailing-slash 301 only fires towards a path that resolves.* Spec §5 step 3 redirects every
   extension-less path without a slash. That turned `/wp-json/wp/v2/posts` — which plan §5 requires to
   404 — into a 301 to a 404. The router now checks the slash form against content, hubs, hub pagination,
   redirects and the gone list first, and 404s directly otherwise. No redirect chain, contract satisfied.
2. *No `sitemap.php` / `feed.php` / `robots.php` files.* Plan §2.2 sketches them; spec §0's `lib/` listing
   does not include them. They are `Seo::sitemap()/feed()/robots()` behind the router's fixed routes.
3. *Timezone is `config['timezone']`, defaulting to `America/Asuncion`.* Spec §6 hard-codes it in
   bootstrap, which would have been the one site-specific value inside `engine/` (spec §15.7).
4. *The contact page and any request with a query string are never page-cached.* The lead form carries a
   signed, time-limited `ts`; caching it would eventually serve an expired stamp, and `?enviado=1` would
   have poisoned the cached `/contacto/` with a success banner.
5. *Empty hubs are left out of `sitemap.xml`.* `/novedades/`, `/viajes/`, `/actividades/` are enabled on
   viaje but have no content yet.
Everything else in KNOWN-ISSUES.md is a gap, not a deviation.

**Smoke-test evidence** (PHP 8.4 built-in server on the built `dist/`):
- `php tools/verify.php viaje.com.py` → `129 checks, 0 failure(s)`;
  `php tools/verify.php thingstodoinparaguay.com` → `90 checks, 0 failure(s)`.
- Admin (spec §15.3): login `303 → /admin/dashboard`; upload wrote
  `foto-prueba.png` + `foto-prueba-480.webp` + `foto-prueba-960.webp`; publish `303`;
  the public page rendered
  `<picture><source type="image/webp" srcset="/media/2026/09/foto-prueba-480.webp 480w, …960w">` and
  `<aside class="tip tip--tip"><p class="tip__label">Dato de viajero">…`, `<h2 id="un-subtitulo">`;
  second load `X-Cache: HIT`; `sitemap.xml` and `/feed/` both listed it; after delete the URL returned
  `404` and the file was in `site/data/trash/`.
- Lead form (spec §15.4): valid POST → `303 → /contacto/?enviado=1` and
  `{"name":"Ana Prueba","phone":"0995628862",…,"when":"2026-09-03T08:44:47-03:00"}` in
  `site/data/leads/2026-09.jsonl`; honeypot → `303 …?enviado=1` with **no** line written; a stamp younger
  than 3 s and a forged stamp → `303 …?error=1`. Login rate limit: `401 401 401 401 401 429`.
- Hardening: `/engine/lib/config.php`, `/site/config.php`, `/site/content/pages/home.md` → `403`;
  `/engine/assets/base.css` → `200`; preview with a valid HMAC → `200 + X-Robots-Tag: noindex, nofollow`,
  with a bad token → `404`; a POST with a wrong CSRF token → `403`; with no password hash configured
  `/admin/` shows the setup page and the public site still returns `200`.
- JSON-LD spot-check: `FAQPage` only on `/faq/`, `BlogPosting` + `BreadcrumbList` on posts, `Service` on
  service pages, `TravelAgency` + `WebSite` everywhere. Exactly one `<h1>` on all eight sampled URLs.

**Where phase 2 should look first.** `docs/site-spec-viaje.md` is the build contract; the engine and
`tools/` are frozen for it (autonomy protocol item 7). Start from `grep -rn "TODO-PHASE-2" sites/` — every
stub carries one. Real copy goes into the existing files (the front matter keys the templates read are
already there: `intro`, `included`, `features`, `facts`, `itinerary`, `faq_tags`). The FAQ merge lands in
`sites/viaje.com.py/content/data/faq.json`, testimonials/team/gallery in the sibling JSON files — all four
are editable at `/admin/data/<name>`. `urls.txt` must gain a row for every new activity/trip URL and the
`/wp-content/uploads/**` images once Anton supplies the folder. `verify.php` is the gate: it already fails
on a missing description, a hero without alt, a duplicate path or any leftover "TourDen"/"Lorem"/"0+".

### 2026-09-03 — Director (Fable, session `session_016yin5ETmxXTCT2qPPmRBur`): phase 3 superseded
- thingstodoinparaguay.com is live with 121 indexed URLs and is being rebuilt in its own repo
  (`antonmarklundcom/thingstodoinparaguay`, O1–O2 merged there). Building it a second time on this
  engine (old phase 3 / step D of the phase-2 prompt) would have produced two competing sites for one
  domain. Phase 3 is struck from the table; phase 2 builds viaje.com.py only; phase 4 deploys viaje only.
- `sites/thingstodoinparaguay.com/` and its CI verify step stay as-is (engine fixture, `TODO-PHASE-3`
  markers are expected there). `docs/site-spec-ttdp.md` is archived, not executed.
- Imagery pool: generate only the ids `docs/site-spec-viaje.md` references; skip TTDP-only ids.
- Director session id for status messages is now `session_016yin5ETmxXTCT2qPPmRBur`.
- Phase 2 spawned on Sonnet from the director session.

### 2026-09-03 — Phase 2 complete (Sonnet, branch `phase/2-content`)

**What exists.** Every real page/post from `docs/viaje-com-py-scan.md` §3 ported into
`sites/viaje.com.py/` per `docs/site-spec-viaje.md`: 5 service pages, home, nosotros, faq,
contacto, `/blog/`, both posts — theme-demo leftovers dropped (TourDen footer, discount
badges, "0+" counters, lorem, newsletter widget). Fraunces display font wired via
`head_extra`. FAQ merged 16→13 deduped Q&As. 6 new activity pages + 3 new trip pages seed
`/actividades/` and `/viajes/` (now in nav), cross-linked to service pages and to the
pillar post's H2 anchors. `docs/imagery-manifest.json`: 18 images generated via the
Higgsfield MCP, **20 of the 700-credit cap spent, no ids dropped**; every hero/gallery
`src` points at the manifest's remote CDN URL (v1 — this sandbox cannot reach the CDN to
download; phase 4's `tools/localize-media.php` does that). Blog-post differentiation
(plan §6) done by an Opus subagent inside this phase: `destinos-imperdibles-2026.md`
re-angled to a seasonal/weekend-planning intent, `paraguay-destinos-imprescindibles-2026.md`
untouched.

**Verification.** `php tools/verify.php viaje.com.py` → 217 checks, 0 failures.
`php tools/verify.php thingstodoinparaguay.com` → unchanged, 90 checks, 0 failures (fixture
untouched). `grep -rn "TODO-PHASE-2" sites/viaje.com.py/` → empty. `grep -ri
"tourden|harbert|lorem|viaje\.com[^.]" sites/viaje.com.py/` → empty. Manual read of the
built-in server's rendered HTML for `/`, one service, `/faq/`, `/contacto/`, both posts,
one activity and one trip: single `<h1>`, correct JSON-LD types (`FAQPage` only on
`/faq/`), WhatsApp links carry `595995628862`, footer/mailto is `hola@viaje.com.py`, no
`<img>` without `alt`, every internal link added (pillar anchors, activity/trip
cross-links) resolves 200.

**Deviations, logged in `KNOWN-ISSUES.md` "Phase 2":** homepage "Destinos Locales" city
cards / discount badges not ported (decorative leftovers per scan §8 item 5, no
card-grid slot in the frozen `home.php`); hub hero images (manifest ids 11, 26) unused
(`hub.php` has no hero slot); imagery stays remote-URL v1 until phase 4 localizes it.

**Where phase 4 should look first.** `docs/phase-2-report.md` has the full decision log.
Still-open human inputs from plan §7: `wp-content/uploads` folder (item 5, still not
supplied — `urls.txt` has no rows for it), real counters or confirmed removal (item 7,
removed by default), social profile URLs (item 11, `null`/skipped), VenderCRM tenant key
(item 9, leads work without it). Phase 4 is deploy + cutover only; it does not need to
touch content.

## 10. Backlog
- Cinematic scroll homepage hero as an opt-in section (needs SEO-safe text fallback).
- Newsletter capture.
- Real trip/package products if the anti-catalog positioning changes.
- Second admin user / roles (not needed for one owner).
- Per-type schema extras (`TouristTrip` on trips) once trips exist.

---

## 11. Model / effort map and the orchestration shape

**ROI split**

| Work | Model | Why |
|---|---|---|
| Plan, engine spec, URL contract, this document | Fable, this conversation | Ambiguity and irreversible-risk decisions are the work here. |
| Phase 1 engine: router, content model, SEO builders, admin write path, upload security, redirect semantics | **Opus** | The one place a defect poisons everything after it; needs judgment on security and edge cases the spec cannot enumerate fully. ~1.5k lines. |
| Phase 2 viaje port: theme, templates fill, 13 pages + 2 posts from §3, alt text, metas, FAQ merge, footer | **Sonnet** | Fully specified, reference content exists, repetitive. |
| The blog-post differentiation edit (inside phase 2) | **Opus** subagent spawned by the phase-2 session for that one task | Editorial judgment over two long texts; small token cost. |
| ~~Phase 3 TTDP~~ | — | Superseded: built in `antonmarklundcom/thingstodoinparaguay`. |
| Phase 4 deploy, audit script, runbook | **Sonnet** | Mechanical once §7 item 6 is answered. |
| Pre-cutover review of staging against §5 | **Fable, in a conversation Anton opens** (or Opus if budget matters more) | Ranking loss is the one irreversible outcome; a single review pass is cheap relative to it. |

**Shape: Fable is not the long-running orchestrator.** Fable does the two ends (spec now, review before cutover). Execution runs as fresh Opus/Sonnet sessions per phase, each spawning the next. Details and the reasoning for rejecting the "Fable leads, spawns Sonnet subagents" shape are in the conversation summary and in the phase prompts' handoff footers.
