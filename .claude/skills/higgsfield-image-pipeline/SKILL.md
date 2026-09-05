---
name: higgsfield-image-pipeline
description: Standing process for getting website images into a repo from a Claude Code cloud session, whatever their source (Higgsfield generation, an old WordPress site being migrated, or files from Anton's PC), without duplicate generation, wrong models, or manual download round-trips. Use BEFORE any Higgsfield generate_image call, whenever a site needs images, whenever images must be pulled from an existing/old site (WordPress media, wp-content/uploads), whenever a session says "can't download", "the images aren't in the repo", "localize the imagery", "kör bilder", or "do X website". Overrides the model and network/download sections of higgsfield-web-imagery and webimg-pipeline.
---

# Higgsfield image pipeline: the one standard path

Written after the viaje.com.py detour (2026-09-05): a cloud session regenerated 21
images that an earlier session had already generated (18, manifest committed, content
wired), misread the model metadata as a silent downgrade, then could not download the
bytes and fell back to a human running PHP locally. Every step below exists to make
one of those four things impossible.

## Rule 0: prove "not already done" before touching a generation tool

Do these checks yourself, in the repo, before any `generate_image*` call. If the repo has
`tools/imagery-preflight.sh` (viaje and its successors) run that instead; it does the
same thing and prints a verdict.

1. `git ls-files | grep -Ei 'imagery|image-generation|jobs\.csv|assets/img/manifest'`
   and read anything found (`docs/imagery-manifest.json`, `docs/image-generation-plan.md`,
   `docs/imagery-brief.md`, `jobs.csv`, `assets/img/manifest.json`).
2. `git log --oneline -i -E --grep='imag|higgsfield|localize|webimg|photo'`.
3. For every image named in a manifest, check whether its files already exist under
   `assets/img/` (any `*/assets/img/<basename>*`).
4. `curl -sI -m 15 <any https://*.cloudfront.net URL>`: HTTP 200 means downloads work
   here; 403 or 000 means this environment is not allowlisted (Rule 2).

Verdicts:

- Manifest exists and all files exist: **done**. Do not generate. Edit content only.
- Manifest exists, files missing: **localize**, do not generate. The result URLs stay
  downloadable for weeks (the 2026-09-03 URLs still served on 2026-09-05).
- No manifest: generate, but only after Rule 1 and Rule 2 pass.
- CDN unreachable: stop before spending credits and tell Anton the one-line fix in Rule 2.

Re-generating a planned image is a bug, not a shortcut.

## Rule 1: the model is always `nano_banana_pro`. Verify it by the ledger.

Anton's standing decision (2026-09-05): **every website image is generated with
`nano_banana_pro`** (Nano Banana Pro, 2 credits at 2K). No `nano_banana_2`, no
`nano_banana_2_lite`, no "cheaper model for cards". A plan or skill that names another
Nano Banana model is out of date; use Pro and say so. Budget maths is simply
2 credits x images.

Why the job metadata must not be trusted for this: measured on this account, jobs
requested as `nano_banana_2` come back with `model: "nano_banana_flash"` in `jobs_wait`,
while the credit ledger charges them as "Nano Banana 2". That is a backend alias, not a
substitution, and `nano_banana_flash` is not even a catalog id (`models_explore get`
errors). The old skill table that lists it as the Lite model is wrong. None of this
matters once Pro is the only model, but it is why verification uses the ledger.

Fail-loud procedure, every batch:

1. `models_explore action=get model_id=nano_banana_pro` once per session; it must
   return `resolution` options including `2k` (default).
2. `generate_image get_cost:true` with `model: nano_banana_pro`, `resolution: "2k"` and
   the slot's ratio; write the number into the manifest `_notes.cost_preflight`.
3. Pass `model: "nano_banana_pro"` and `resolution: "2k"` explicitly in every batch
   request. Never let a default choose.
4. After `jobs_wait`: every result must be 2K-class (21:9 about 3168x1344, 16:9 about
   2752x1536). A 1376x768-class result is the wrong model or resolution: stop.
5. `transactions size:<n>`: one ledger line per job, display name "Nano Banana Pro",
   credits equal to the preflight. Any other display name = stop, report job ids to
   Anton, do not place the images. Record the check in `_notes.ledger_checked`.

Never pass `use_unlim`. Never pick a cheaper model on your own initiative.

## Rule 2: the download path is the environment allowlist. Nothing else.

Generation runs through Anthropic's MCP relay and works everywhere. Downloading does
not: a Trusted-level cloud environment answers CONNECT 403 for `*.cloudfront.net`, and
MCP permission toggles cannot change that. The fix is a documented environment setting,
done once per environment and inherited by every later session and every repo:

> claude.ai/code → environment selector → edit the environment → **Network access:
> Custom** → **Allowed domains**: `*.cloudfront.net` → tick "Also include default list
> of common package managers" → save, and make it the **default** environment so no
> session ever has to pick it. Docs:
> https://code.claude.com/docs/en/cloud-environments#access-levels

Status: Anton set this up on his Default environment (update this line with the date
when done). If a session still gets 403, the fix above was not applied; report that,
do not work around it.

With the allowlist in place the session runs the repo's own localizer in-session:

- Engine repos (viaje and successors): `php tools/localize-media.php <domain>`.
  PHP 8.4 with gd/mbstring/fileinfo is preinstalled in the cloud image (verified).
- Template/brochure repos: `npx --yes github:antonmarklundcom/webimg batch . --manifest
  jobs.csv --out assets/img` per the webimg-pipeline skill, `file` column = result URL.

Then commit the assets with the content that references them, push, done.

Rejected alternatives, so they are not re-litigated per project:

- **Higgsfield `sandbox_exec`**: it can fetch the CDN and run webimg (verified), but its
  tool output truncates at ~77 KB, so bytes cannot come back to the session, and pushing
  from there would require handing a GitHub token to a third-party sandbox. No.
- **`media_upload` general files**: lands on the same blocked CDN. No.
- **GitHub MCP `push_files`**: text-only content, cannot carry binaries. No.
- **Human runs a script locally**: the phase-4 fallback that cost the hours. Only if the
  environment setting is impossible for the account, and then say so up front.

## Rule 3: where this workflow runs

Cloud session is the right place, provided Rule 2 is done. The MCP is available there,
the synced skills are visible there, and PHP/Node/webimg all work in-session. There is
no reason to move image work to a local terminal or to a Fable-direct chat, and the
fable-cost-guardrail applies as usual: this is Sonnet/Opus work.

If the preflight exits 4 in a session that must finish today and Anton is not reachable
to change the environment, stop after writing/committing the manifest with result URLs
and say exactly that. Do not spend credits on a set nobody can download.

## Rule 4: images from an existing site (WordPress migration) use the same pipeline

When the site replaces an old WordPress site (thingstodoinparaguay.com and similar),
the images already exist and **nothing is generated**. The source is the old site's
media library; the rest is identical to the Higgsfield path:

1. Inventory: fetch `https://<old-site>/wp-json/wp/v2/media?per_page=100&page=N` until
   empty (falls back to crawling `wp-content/uploads` links from the old pages if the
   REST API is off). Record each image's source URL, the page/post it belonged to, its
   original alt text and caption. Write that into `docs/imagery-manifest.json` with
   `source: "wordpress"` and the planned `file`/`alt` per image, in the same shape as a
   Higgsfield manifest, so Rule 0 finds it next time.
2. Choose: keep only images that map to a slot in the new site spec. Old WP uploads are
   usually many duplicates and thumbnails (`-150x150`, `-300x200` suffixes); take the
   original only. Anything not mapped to a slot is left out, not migrated "for later".
3. Download bytes require the old site's host on the environment allowlist, exactly
   like the CDN: add `<old-site-domain>` (and its CDN host if it uses one, e.g.
   `*.wp.com`, `i0.wp.com`) to Allowed domains next to `*.cloudfront.net`. Same 403 rule:
   if `curl -sI` fails, stop and report; do not ask a human to download.
4. Convert with webimg straight from the URLs (`file` column = source URL), with the new
   SEO slug and alt text, place, verify, commit. Original WP filenames and alt are never
   reused as-is.
5. Gaps: slots with no usable old image are generated with `nano_banana_pro` under Rules
   1 and 2. Mark them `source: "higgsfield"` in the same manifest.

Images from Anton's PC follow `webimg-pipeline` ("When the images start on Anton's PC")
and are then recorded in the manifest with `source: "local"`.

## Manifest contract (what makes Rule 0 work next time)

One tracked file per site, `docs/imagery-manifest.json`, with `_notes` carrying
`generated`, `cost_preflight`, `actual_spend_credits`, `ledger_checked` and
`download_status`, and `images[]` carrying `id, file, alt_*, ratio, px, model, prompt,
url, width, height, job_id`. The `file` name is decided before generation. Update
`download_status` in the same commit that adds the local files.

## What Anton says to start it

Nothing image-specific. "Do X website" or "kör bilder" is enough: this skill is synced
to his account and triggers on any site work that needs images. Art direction, prompt
writing, Elements and slot planning still come from `higgsfield-web-imagery` (Steps 1,
3, 4, 5) and file conversion, naming and alt text from `webimg-pipeline`; this skill
overrides their model choice, environment check and download sections. If those skills
disagree with this one, this one wins.
