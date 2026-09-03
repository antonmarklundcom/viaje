# Cutover runbook — viaje.com.py

Numbered steps for Anton. Every automated check referenced here (`php tools/verify.php`,
CI, `.github/workflows/deploy.yml`) is already wired up; the steps below are the parts
only a human with Hostinger/DNS/Search Console access can do. thingstodoinparaguay.com
is out of scope — it deploys from its own repo (plan.md §3.2).

Do these in order. Steps 1–8 happen on a **staging** document root first; nothing in
steps 9–13 happens until staging (step 7) passes clean.

---

## 0. Before you start

- Pick an upload path for step 4 and stay with it for every later deploy:
  - **A — GitHub Actions FTP** (recommended, repeatable): add three repo secrets —
    `FTP_HOST_VIAJE`, `FTP_USER_VIAJE`, `FTP_PASS_VIAJE` — under GitHub repo
    **Settings → Secrets and variables → Actions**, get them from hPanel's FTP
    account you create in step 1. Deploys run from **Actions → Deploy viaje.com.py →
    Run workflow**. The job is a no-op (skips) until all three secrets exist.
  - **B — hPanel File Manager**: build locally (`php tools/build.php viaje.com.py
    --fresh`), zip `dist/viaje.com.py/`, upload and extract via hPanel's File Manager.
    More manual, no GitHub secret needed.
  - **C — hPanel Git deploy**: point hPanel's Git integration at this repo; it is not
    a drop-in fit here because hPanel Git deploy publishes the repo root as-is, but
    the document root needs to be the **built** `dist/viaje.com.py/` output, not the
    repo root (`engine/`, `sites/`, `tools/` would all end up served). Usable only by
    also configuring hPanel's "deployment script" hook to run `php tools/build.php
    viaje.com.py --fresh` and pointing the document root at
    `dist/viaje.com.py/` after checkout — more setup than A for the same result, so
    prefer A unless Git deploy is already your habit.
- Know the answers to plan.md §7 items 4 (canonical host — www vs apex, current
  WordPress hosting account), 6 (resolved: option A/B/C above), 12 (the staging
  hostname you'll get in step 1).

## 1. Create the Hostinger website

1. hPanel → **Websites → Add Website**. Choose the account/plan that will host
   `viaje.com.py` (a shared-hosting website, not a Node.js app — this is PHP).
2. If DNS for `viaje.com.py` is not yet pointed at Hostinger, create the site under a
   temporary Hostinger subdomain first (hPanel offers one automatically, e.g.
   `https://<random>.hostingersite.com`) — **this is your staging URL** for steps
   4–8. Note it down; it's plan §7 item 12.
3. hPanel → **Advanced → PHP Configuration** → set **PHP 8.1 or newer** (engine spec
   §0 targets 8.1+; the repo's CI runs 8.4). Enable extensions `gd`, `mbstring`,
   `fileinfo`, `zip` if they're not on by default (GD is required for images, zip for
   the admin export button and the backup cron in step 14).
4. hPanel → **Files → FTP Accounts** → create (or note the existing) FTP
   credentials for this site — host, username, password. These are
   `FTP_HOST_VIAJE` / `FTP_USER_VIAJE` / `FTP_PASS_VIAJE` if you're using path A.

## 2. Localize the imagery (run once, from your own machine, before the first deploy)

The build sandbox that produced `docs/imagery-manifest.json` cannot reach the
Higgsfield CDN, so the site's images still point at remote CDN URLs (KNOWN-ISSUES
#11). From a machine with normal internet access:

```
php tools/localize-media.php viaje.com.py
```

This downloads every manifest image into `sites/viaje.com.py/assets/img/`, generates
the responsive WebP variants the templates already know how to render, and rewrites
every `hero:`/gallery/`default_og_image` reference in `sites/viaje.com.py/` from the
remote URL to the local path. Review the diff (`git status`, `git diff`), commit it,
push, and let CI (`php tools/verify.php viaje.com.py`) confirm nothing broke before
merging to `main` — the deploy in step 4 builds from `main`.

## 3. Build and sanity-check locally

```
php tools/build.php viaje.com.py --fresh
php tools/verify.php viaje.com.py
```

Both must pass before uploading anywhere.

## 4. First deploy — to staging

- **Path A**: Actions → **Deploy viaje.com.py** → Run workflow. Set `server_dir` to
  the staging site's document root (Hostinger calls it out on the website's
  dashboard, typically `/public_html/` for that temp subdomain), `protocol` to
  `ftps` (Hostinger's default; only switch to `ftp` if the connection fails), leave
  `dry_run` off for a real upload. Watch the run; the "Verify before deploying" step
  runs `tools/verify.php` against the build before anything is uploaded.
- **Path B/C**: see step 0.

## 5. `config.local.php` on the server — do this before loading the staging URL

`config.local.php` is git-ignored and never uploaded by the deploy workflow (it's in
the exclude list). Create it directly on the server, in the `site/` folder next to
`config.php` (via File Manager's editor, or an FTP client), starting from
`sites/viaje.com.py/config.local.example.php`:

1. Generate the admin password hash **locally** (so the password never touches shell
   history on the server): `php engine/bin/hash-password.php` (it prompts, or pass
   the password as an argument). Paste the printed `'admin_password_hash' => '...'`
   line into the server's `config.local.php`.
2. **Set both of these while on the staging hostname — this is the one step that is
   easy to skip and breaks staging outright if you do:**
   ```php
   'staging'    => true,
   'force_host' => null,   // or the exact staging hostname, e.g. '<random>.hostingersite.com'
   ```
   `config.php` hard-codes `force_host` to `viaje.com.py`. Without the override above,
   *every* request to the staging hostname 301-redirects straight to
   `https://viaje.com.py/` — which, pre-cutover, is still the old WordPress site — so
   the new build looks broken/invisible even though it deployed correctly. `staging
   => true` also adds the `X-Robots-Tag: noindex, nofollow` header site-wide, so
   Google can crawl if asked but won't index the staging copy.
3. Leave `preview_secret`, `leads.vendercrm.*` and `analytics.ga4` as-is (null) unless
   you have real values now (plan §7 items 9, matches KNOWN-ISSUES #9); the site
   works without them.

## 6. Copy the legacy WordPress uploads

Plan §7 item 5 / KNOWN-ISSUES #3. From the **old** WordPress hosting (hPanel File
Manager or FTP), download the whole `wp-content/uploads/` folder, then upload its
contents into the new site's document root at `site/../wp-content/uploads/` — i.e.
directly under the document root as `wp-content/uploads/...`, matching the legacy
paths byte-for-byte (`sites/viaje.com.py/static/wp-content/uploads/` is the slot in
the repo; `build.php` copies whatever is there to the document root, but since this
folder is large and not practical to route through git, upload it straight to the
server's `wp-content/uploads/` instead — either location works, they land at the
same URL). Spot-check a few known image URLs from the old site once uploaded.

## 7. Verify staging

```
php tools/verify.php viaje.com.py --base=https://<staging-host>
```

This runs the full URL contract (`sites/viaje.com.py/urls.txt`) and the on-page SEO
checks from a real HTTP client against the live staging install — canonical tags are
still asserted against the production `base_url` from `config.php` (correct: canonical
should already say `viaje.com.py`, matching the site’s own no-duplicate-canonical
design, not the staging host). Fix anything that fails before continuing; re-run after
every fix. This is also where `X-Robots-Tag: noindex` becomes checkable
(`curl -I https://<staging-host>/ | grep -i x-robots-tag`, expect it present) —
KNOWN-ISSUES #2 explains why the automated suite can't do that locally.

## 8. Manual QA on staging

- Log into `/admin/` with the password from step 5; confirm the dashboard loads.
- Submit the contact form once with real-looking data; confirm it lands in
  `site/data/leads/<year-month>.jsonl` (via File Manager) and check whether the
  notification email arrives at `hola@viaje.com.py` (KNOWN-ISSUES #8 — `mail()`
  delivery on Hostinger is unverified until this check).
- Click through the nav: home, all 5 services, `/servicios/`, `/actividades/`,
  `/viajes/`, `/blog/` and both posts, `/faq/`, `/nosotros/`, `/contacto/`.
- Confirm plan §7 items still open: real social profile URLs (item 11) and real
  counters if any exist (item 7) — both are safe to leave as-is (skipped/removed) if
  you don't have them yet; neither blocks cutover.

## 9. DNS cutover

1. **48 hours ahead of the swap**, lower the DNS TTL for `viaje.com.py` (and `www` if
   in use) at whichever provider hosts DNS today, so the eventual change propagates
   fast.
2. Confirm plan §7 item 4 — the canonical host the *current* live site uses (`www` or
   apex, `http` or `https`) — `config.php`'s `force_host` is already set to the apex
   `viaje.com.py` with `force_https: true`; if the live site is actually `www`, either
   change `force_host` before this step or plan to 301 `www` → apex at the DNS/host
   level (Hostinger's domain settings, not this engine, since the engine only
   canonicalises the host it's told is canonical).
3. Point `viaje.com.py`'s DNS at the new Hostinger site (A record / nameservers, per
   whatever hPanel's domain screen instructs for this account) — or, if WordPress
   already lives on the same Hostinger account, use hPanel's "Change website" / point
   the domain at the new document root instead of a DNS change.

## 10. Flip staging off

Edit the server's `config.local.php` (the same file from step 5, still git-ignored,
edited in place — nothing to redeploy):

```php
'staging'    => false,
'force_host' => null,   // remove this line entirely — config.php's viaje.com.py default is now correct
```

Re-run **Deploy viaje.com.py** with `server_dir` now pointed at the production
document root (if it differs from the staging one you used in step 4).

## 11. Post-cutover verify

```
php tools/verify.php viaje.com.py --base=https://viaje.com.py
```

Zero failures required. Also manually check the host/scheme canonicalisation this
suite can't assert automatically (KNOWN-ISSUES #2): `curl -I http://viaje.com.py/`
and, if the site used `www` before, `curl -I https://www.viaje.com.py/` — both must
301 to `https://viaje.com.py/` in one hop.

## 12. Search Console

1. Add/confirm the `viaje.com.py` property (domain or URL-prefix, whichever you
   already used) in Google Search Console.
2. Submit `https://viaje.com.py/sitemap.xml`.
3. Use the **URL Inspection** tool on the two ranking posts and the homepage; request
   indexing if they show the old WordPress version cached.

## 13. The 14-day coverage watch

Check Search Console daily for two weeks:

- **Coverage / Pages report**: watch for new errors (404s, redirect errors) on the 13
  real URLs from plan §5 — a spike here means something in the URL contract broke.
- **Performance**: compare impressions/clicks for the 13 real URLs against their
  pre-cutover baseline (if you have GSC history) — a cliff on any one URL is the
  signal to check its response manually.
- The blog differentiation gate (plan §6): if
  `/destinos-imperdibles-2026/` shows ~zero impressions after this window (extend to
  3 months per plan §6), merge it into the pillar post with a 301 instead of keeping
  it separate.

## 14. Weekly backup cron

`engine/bin/backup.php` zips `site/content`, `site/media` and `site/data/leads` into
`site/data/backups/viaje.com.py-backup-<timestamp>.zip` (same contents as the admin's
"Export backup" button) and keeps the newest 8. It's already on the server — deploying
copies all of `engine/`. Wire it into hPanel:

1. hPanel → **Advanced → Cron Jobs** → Create a new cron job.
2. Schedule: **Once a week** (any day/time — e.g. Sunday 03:00).
3. Command: `php /home/<hostinger-username>/domains/viaje.com.py/public_html/engine/bin/backup.php`
   (hPanel shows the exact absolute path for this site on the same screen — copy it
   from there rather than guessing the username).
4. Save, then use hPanel's "Run now" if offered, or wait for the first scheduled run,
   and confirm a zip appears under `site/data/backups/` via File Manager.

## 15. Rollback

If cutover needs to be undone:

1. Point DNS (or hPanel's "Change website") back at the old WordPress hosting — this
   is why step 9.1's low TTL matters, it's the only slow part.
2. The WordPress export and the `wp-content/uploads` copy from step 6 are your
   restore point for the old site; keep both until the 14-day watch (step 13) is
   clean.
3. The new engine's own data (leads collected during the cutover window, any admin
   edits) is not lost by rolling DNS back — it stays in `site/content`, `site/media`
   and `site/data` on the Hostinger account and in the weekly backups (step 14) —
   nothing needs to be re-entered when you go forward again.
