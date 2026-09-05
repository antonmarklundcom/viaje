---
name: higgsfield-image-pipeline
description: Standing process for getting Higgsfield-generated website images into a repo from a Claude Code cloud session without duplicate generation, silent model surprises, or manual download round-trips. Use BEFORE any Higgsfield generate_image / generate_image_batch call in any site repo, whenever a session says "the images aren't in the repo", "can't download from the CDN", "localize the imagery", or a manifest like docs/imagery-manifest.json exists. Overrides the network/download sections of higgsfield-web-imagery and webimg-pipeline.
---

# Higgsfield image pipeline: the one standard path

Written after the viaje.com.py detour (2026-09-05): a cloud session regenerated 21
images that an earlier session had already generated (18, manifest committed, content
wired), misread the model metadata as a silent downgrade, then could not download the
bytes and fell back to a human running PHP locally. Every step below exists to make
one of those four things impossible.

## Rule 0: run the preflight before touching a generation tool

```
bash tools/imagery-preflight.sh
```

(Copy `tools/imagery-preflight.sh` from antonmarklundcom/viaje into any repo that lacks
it. It is plain bash + python3, nothing to install.) It prints:

1. every tracked manifest / plan / brief (`docs/imagery-manifest.json`,
   `docs/image-generation-plan.md`, `assets/img/manifest.json`, `jobs.csv`),
2. every prior commit mentioning image/imagery/higgsfield/localize/webimg,
3. per manifest: how many planned images are already on disk under `assets/img/`,
4. whether THIS session can HEAD the Higgsfield CDN (`*.cloudfront.net`).

Exit 3 means "already generated and localized": do not generate, go edit content.
Exit 4 means "generation would strand the bytes": fix the environment first (Rule 2).
Only exit 0 permits a generation call, and only for the images step 3 listed as missing.

If a manifest exists but images are missing, the job is **localize**, not generate:
the result URLs in the manifest stay downloadable for weeks (the 2026-09-03 URLs still
served on 2026-09-05). Re-generating a planned image is a bug, not a shortcut.

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
> of common package managers" → save. Start a new session in that environment.
> Docs: https://code.claude.com/docs/en/cloud-environments#access-levels

Anton: create one environment named **"Sites (Higgsfield CDN)"** with that list and use it
for every site repo. That is the whole fix; it needs no code, no secrets, no PHP on a
laptop.

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

## Manifest contract (what makes Rule 0 work next time)

One tracked file per site, `docs/imagery-manifest.json`, with `_notes` carrying
`generated`, `cost_preflight`, `actual_spend_credits`, `ledger_checked` and
`download_status`, and `images[]` carrying `id, file, alt_*, ratio, px, model, prompt,
url, width, height, job_id`. The `file` name is decided before generation. Update
`download_status` in the same commit that adds the local files.

## Kick-off line for any site session

> Images: run `bash tools/imagery-preflight.sh` first. If it exits 3 the set exists;
> localize/edit only. Verify models via `transactions`, not the job metadata. Download
> path is the environment allowlist (`*.cloudfront.net`); if the preflight exits 4,
> tell me and stop before generating.
