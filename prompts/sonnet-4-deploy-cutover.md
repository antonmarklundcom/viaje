# Phase 4 — Deploy pipeline, staging, cutover runbook. Paste into a fresh SONNET session on antonmarklundcom/viaje.

Read `plan.md` (§1, §2.4, §4, §5, §7, §8, §9 build log), `KNOWN-ISSUES.md`, `docs/engine-spec.md`
§0, §12, §13, and the `nextjs-deploy-hostinger` skill's Hostinger sections for hPanel, FTP and
Git-deploy mechanics (the app here is PHP, not Node; take only the hosting knowledge).
Engine rules from phase 2 still apply: router, content model, SEO builders, admin write path and
redirect semantics are frozen.

Branch `phase/4-deploy` off latest `main`. One PR. Deliver:

1. `tools/localize-media.php` — downloads every url in `docs/imagery-manifest.json` into
   `sites/<domain>/assets/img/` (jpg + webp at 640/1280/1920 via GD) and rewrites content
   references to the local files. Must work when run from Anton's machine with network; here the
   CDN is blocked, so test with a local fixture manifest pointing at generated PNGs.
2. `.github/workflows/deploy.yml` — one manual-dispatch job for viaje.com.py using
   SamKirkland/FTP-Deploy-Action, uploading `dist/viaje.com.py/` with an exclude list
   (`site/content`, `site/media`, `site/data`, `site/cache`, `site/config.local.php`), gated on
   secrets `FTP_HOST_VIAJE` / `FTP_USER_VIAJE` / `FTP_PASS_VIAJE`. thingstodoinparaguay.com is
   deployed from its own repo (plan §3.2) — no job for it here.
3. `docs/cutover-runbook.md` — numbered steps for Anton, viaje.com.py only: Hostinger website creation,
   PHP version, upload / FTP / Git options, `config.local.php` creation with the password-hash
   command, copying `wp-content/uploads` from the old WP install into the doc root, staging on
   the hostingersite.com temp domain with `staging => true`, running
   `php tools/verify.php <domain> --base=https://<staging>`, DNS/host swap, flipping staging to
   false, Search Console sitemap submission, the 14-day coverage watch, and rollback (WP export +
   uploads).
4. Extend `tools/verify.php`'s remote mode if needed so it runs against a live base URL, and add
   the weekly backup cron script from plan §2.4.

Exit: `php -l` clean; verify.php exits 0 for both sites locally; workflow validates (act or a
dry-run parse); runbook reviewed against plan §5 and §7 items 4, 6, 12; CI green; PR merged;
build-log entry in `plan.md` §9; `KNOWN-ISSUES.md` updated.

## Closing report (no further session is spawned)
End with: the live-ready checklist, the runbook link, everything Anton must do by hand (Hostinger
uploads, DNS, mailbox check, social URLs, wp-content/uploads copy, Search Console), and a proposed
prompt for creating a project skill `viaje-dev` capturing the engine's do-not-touch rules and how
to publish content. If a cross-session message tool is available, send that same report in one
paragraph to the director session `session_016yin5ETmxXTCT2qPPmRBur`; otherwise skip.
