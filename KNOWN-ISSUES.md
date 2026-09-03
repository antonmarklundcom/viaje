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
    and 3 replace it. `grep -rn "TODO-PHASE" sites/` lists what is left.
