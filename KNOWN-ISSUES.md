# Known issues

Things deliberately left out or worked around. Each entry says which phase should
pick it up. Nothing here blocks the phase it was written in.

## Phase 1 — engine

1. **`wp-sitemap-*.xml` redirects are exact paths, not a wildcard.** The router's
   `redirects` map matches exact paths (spec §5 step 4). The five sitemap files the
   live WordPress actually emits are listed in `sites/viaje.com.py/config.php`; any
   other `wp-sitemap-*.xml` variant 404s instead of 301-ing. Add rows if Search
   Console reports others. *(Phase 4 — cutover audit.)*

2. **http → https and www → apex are not exercised locally.** `Router::canonicalRedirect()`
   implements both, but it skips `localhost`/`127.0.0.1` by design, so `verify.php`
   cannot assert them. They are verified against staging in the phase-4 runbook, along
   with which host the live site canonicalises to today (plan §7 item 4).

3. **`/wp-content/uploads/**` rows are not in `urls.txt` yet.** The folder is empty
   until Anton copies it off hPanel (plan §7 item 5). The slot
   (`sites/viaje.com.py/static/wp-content/uploads/`) exists and `build.php` copies it
   to the document root verbatim. *(Phase 2.)*

4. **The trailing-slash redirect only fires towards paths that exist.** Spec §5 step 3
   says an extension-less path with no trailing slash 301s to the slash form. The engine
   does that only when the slash form actually resolves (content, hub, hub pagination,
   redirect or gone); otherwise it 404s directly. This avoids a 301 hop to a 404 for
   junk paths such as `/wp-json/wp/v2/posts`, which the URL contract requires to 404.

5. **`sitemap.php`, `feed.php` and `robots.php` are not separate files.** Plan §2.2
   sketches them as files; spec §0's `lib/` listing does not include them. They are
   built by `Seo::sitemap()`, `Seo::feed()` and `Seo::robots()` and served from the
   router's fixed routes, so there is one fewer file and no duplicated config access.

6. **No image cropping or focal point.** Uploads are re-encoded and scaled to width;
   there is no crop UI. Hero images should be uploaded at roughly 3:2. *(Backlog.)*

7. **The admin has no rich-text editor.** It is a markdown textarea with a toolbar and
   a server-rendered preview, by design (spec §8). *(Backlog.)*

8. **`mail()` delivery is unverified.** `leads.php` writes every lead to
   `site/data/leads/YYYY-MM.jsonl` first and treats mail failure as non-fatal, so no
   lead can be lost to a mail misconfiguration. Whether Hostinger's `mail()` actually
   delivers to `hola@viaje.com.py` is a phase-4 check on the real host.

9. **VenderCRM push is untested against a live endpoint.** The payload and headers
   follow the `vendercrm-lead-capture` skill's PHP reference. It stays inert until
   `leads.vendercrm.endpoint` and `tenant_key` are set in `config.local.php`
   (plan §7 item 9).

10. **Content is seeded with `TODO-PHASE-2` / `TODO-PHASE-3` markers.** Every URL in the
    contract resolves and passes the SEO checks, but the copy is placeholder. Phases 2
    and 3 replace it. `grep -rn "TODO-PHASE" sites/` lists what is left. *(Phase 2 cleared
    every `TODO-PHASE-2` marker in `sites/viaje.com.py/`; `TODO-PHASE-3` markers remain in
    `sites/thingstodoinparaguay.com/` by design — that site is the engine's second-site
    fixture, not a phase this repo runs. See plan.md §9 director entry, 2026-09-03.)*

## Phase 2 — viaje.com.py content

11. **Imagery is v1 (remote URLs), not downloaded.** `docs/imagery-manifest.json`'s 18
    images are referenced by their Higgsfield CDN `url` directly in content front matter
    (`hero:`) and `content/data/gallery.json` — this sandbox cannot reach the CDN to
    download them. `Images::picture()` renders them as plain `<img>` (no local dimensions,
    no WebP responsive variants, since `Images::localFile()` returns null for an external
    URL). `tools/localize-media.php` (phase 4) downloads them into
    `sites/viaje.com.py/assets/img/` under the manifest's `file` names and rewrites every
    reference. *(Phase 4 delivered the tool, tested against a local fixture manifest —
    the CDN is still unreachable from this sandbox, so the real run happens on Anton's
    machine per `docs/cutover-runbook.md` step 2; content still points at the CDN until
    then.)*

12. **Hub hero images (manifest ids 11, 26) are unused.** `docs/site-spec-viaje.md`'s page
    map assigns hero id 11 to `/blog/` and 26 to `/servicios/`, but `hub.php` has no
    hero-image slot and the `hubs` config schema has no `hero` key (both frozen this
    phase). The site-wide `default_og_image` (id 01) still covers their `og:image`.
    *(Backlog — would need an engine change to add a hub hero slot.)*

13. **Homepage "Destinos Locales" city cards were not ported.** The scan (§3.1, §8 item 5)
    flags these — and their "% Off" discount badges — as decorative theme-card leftovers
    with no real discount mechanic anywhere else on the live site, and `home.php` has no
    card-grid slot for them. Confirm with Anton whether a real discount ever existed
    before considering adding a city-highlights section as new scope. *(Backlog.)*

## Phase 4 — deploy

14. **`localize-media.php` generates WebP variants at 480/960/1600px, not the
    640/1280/1920px the phase prompt named.** `engine/lib/images.php`'s `Images::WIDTHS`
    (frozen this phase) is what `Images::webpSrcset()` actually looks for next to a
    source file; generating at the prompt's literal widths would have produced files
    the templates never picked up, silently defeating the point of localizing. Kept the
    widths in sync by hand (a comment in both files says so) rather than including
    `images.php` from a standalone CLI tool that has no bootstrap/`VJ_SITE` context.

15. **`config.php`'s `force_host: 'viaje.com.py'` 301-redirects every request on a
    staging hostname straight to the production domain**, breaking staging outright,
    unless the server's `config.local.php` overrides `force_host` (to `null` or the
    staging host) — confirmed by testing against a faked `Host` header locally; the
    existing local-dev bypass (KNOWN-ISSUES #2) only covers `127.0.0.1`/`localhost`,
    not a real staging hostname. `config.local.example.php` and
    `docs/cutover-runbook.md` step 5 now call this out explicitly. Not an engine
    change (frozen); a documentation/runbook fix.

16. **`tools/verify.php --base=<url>` needed no code change for staging/live remote
    checks.** Read closely and tested against a locally-served stand-in: the URL
    contract, sitemap and on-page SEO checks all already work against an arbitrary
    `--base`, and the canonical check intentionally keeps asserting the production
    `base_url` from `config.php` regardless of `--base` (staging is meant to look
    exactly like production except for the noindex header, per plan §5). The one gap —
    asserting the http→https / www→apex host redirect itself — stays a manual `curl -I`
    check in the runbook (step 11), same as KNOWN-ISSUES #2 already scopes it, because
    which host is canonical is still open (plan §7 item 4, first needed by this phase)
    and automating a check for a value that isn't decided yet would just be guessed
    at.

17. **`engine/bin/backup.php` (the weekly cron) keeps the newest 8 zips per domain**, a
    default not specified in plan §2.4/§8, chosen for roughly two months of weekly
    history without unbounded growth on shared-hosting disk quotas. Adjust the `KEEP`
    constant if that's wrong for Anton's plan. Zips the same three directories as the
    admin's "Export backup" button (`content`, `media`, `data/leads`) so both stay in
    sync by construction.

18. **`.github/workflows/deploy.yml` pins `SamKirkland/FTP-Deploy-Action@v4.3.5`.** The
    job is inert (a step fails fast with a clear message) until repo secrets
    `FTP_HOST_VIAJE` / `FTP_USER_VIAJE` / `FTP_PASS_VIAJE` exist — plan §7 item 6,
    `docs/cutover-runbook.md` step 0.
