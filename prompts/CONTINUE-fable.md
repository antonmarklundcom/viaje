# Continuation prompt — paste into a fresh Fable 5.1 session on repo antonmarklundcom/viaje

You are the director of the viaje.com.py + thingstodoinparaguay.com rebuild. You direct; Sonnet and Opus
subagents (Agent tool, `model: "sonnet"` / `"opus"`, background) do 95%+ of the work. Never spawn a
subagent, session, or workflow on Fable (fable-cost-guardrail skill). You write specs and do reviews;
you fix only small findings yourself.

## Orient (read these, nothing else first)
1. `plan.md` — §1 locked decisions, §5 URL contract, §6 fixes, §9 build log.
2. `KNOWN-ISSUES.md`.
3. `docs/engine-spec.md` §0 and §15 only (layout + definition of done). The engine is built and frozen.
4. The three phase specs exist: `docs/imagery-brief.md`, `docs/site-spec-viaje.md`, `docs/site-spec-ttdp.md`.
5. `git log --oneline | head` and `git status`. Work on a branch off `main` (the plan/engine PR was merged).
   Branch name `phase/2-content`; one PR for phases 2+3+imagery; a second PR `phase/4-deploy` for phase 4.

Sanity check before spawning anything: `php tools/verify.php viaje.com.py && php tools/verify.php thingstodoinparaguay.com`
must exit 0 on `main`. If not, fix that first (Opus subagent, small scope).

## Step A — Imagery (Sonnet, background, runs in parallel with B)
Spawn a Sonnet agent with: "Read docs/imagery-brief.md and the higgsfield-web-imagery skill at
/root/.claude/skills/synced/*/higgsfield-web-imagery/SKILL.md. Do the cost preflight, then generate the pool via
the Higgsfield MCP tools (generate_image_batch → jobs_wait → show_generation_by_ids), respecting the 700-credit
cap. Write docs/imagery-manifest.json exactly in the brief's shape with the remote result URLs. Do not try
to download images (the CDN is blocked here). Report the credits spent and any ids you dropped."
Note: images 01, 02, 03 use nano_banana_2 at 2K, the rest nano_banana_flash at 1K.

## Step B — viaje.com.py content (Sonnet, background)
Spawn: "Read docs/site-spec-viaje.md and execute it fully. Sources: docs/viaje-com-py-scan.md §3, §4, §6, §7.
Image URLs come from docs/imagery-manifest.json; if it does not exist yet, use the placeholder
`/assets/img/<file>.jpg` from docs/imagery-brief.md with the manifest's future filename and the alt text
you write, and list every placeholder in docs/phase-2-report.md so a follow-up can swap URLs."
When both A and B are done: if B used placeholders, spawn a small Sonnet task to replace them from the manifest.

## Step C — Blog differentiation (Opus, after B)
Spawn an Opus agent: "Read plan.md §6 row 'Blog-post overlap'. Edit only
sites/viaje.com.py/content/posts/destinos-imperdibles-2026.md: keep its URL and slug; give it a distinct
title/H1 and intro around a different search intent (seasonal/when-to-go and weekend-escape planning, in
Paraguayan Spanish); trim the destination sections that duplicate the pillar post
(paraguay-destinos-imprescindibles-2026.md) to 2–3 sentence summaries each, linking to the pillar's matching
H2 anchor; keep every unique fact; keep the :::tip blocks that are unique; update description and
seo_title; do not touch the pillar. Run verify.php afterwards."

## Step D — thingstodoinparaguay.com (Sonnet, background, can run in parallel with B)
Spawn: "Read docs/site-spec-ttdp.md and execute it fully." Same placeholder rule as B.

## Step E — Fable review (you, in this session; keep it tight)
1. `php tools/verify.php` for both sites.
2. Read only: sites/viaje.com.py/config.php, content/pages/home.md, one service file, one post, faq.json,
   and the rendered HTML of `/`, `/contacto/`, `/faq/`, one post (built-in server via `php tools/serve.php`).
   Check: titles/descriptions quality, JSON-LD correctness, no theme leftovers, WhatsApp number, email,
   internal links, alt text. Same for TTDP home + one activity.
3. Fix small things yourself; send agents back for anything larger (SendMessage to the same agent).
4. Append the build-log entry to plan.md §9, update KNOWN-ISSUES.md, commit, push, open the PR
   (`phase/2-content`), ask Anton to merge.

## Step F — Phase 4: deploy + cutover (Sonnet, after the content PR is merged)
Spawn: "Deliver phase 4 from plan.md §8 and §5: (1) tools/localize-media.php that downloads every url in
docs/imagery-manifest.json into sites/<domain>/assets/img/ (jpg + webp at 640/1280/1920 via GD) and rewrites
content references — must work when run from Anton's machine with network; (2) .github/workflows/deploy.yml
with two manual-dispatch jobs (one per domain) using SamKirkland/FTP-Deploy-Action, uploading dist/<domain>/
with an exclude list (site/content, site/media, site/data, site/cache, site/config.local.php), gated on
secrets FTP_HOST_VIAJE/FTP_USER_VIAJE/FTP_PASS_VIAJE and the TTDP equivalents; (3) docs/cutover-runbook.md
with numbered steps for Anton: Hostinger website creation per domain, PHP version, upload/ftp/git options,
config.local.php creation with the password hash command, copying wp-content/uploads from the old WP install
into the doc root, staging on the hostingersite.com temp domain with `staging => true`, running
`php tools/verify.php <domain> --base=https://<staging>`, DNS/host swap, flipping staging to false, Search
Console sitemap submission, the 14-day coverage watch, and the rollback (WP export + uploads); include a
pre-cutover check for thingstodoinparaguay.com's existing sitemap. (4) Extend verify.php's remote mode if
needed so it can run against a live base URL."
Review, commit, PR `phase/4-deploy`, ask Anton to merge.

## Step G — Closing report to Anton
Live-ready checklist, the exact runbook link, what he must do by hand (Hostinger uploads, DNS, mailbox
check, socials URLs, wp-content/uploads copy, GSC), and a prompt to create a project skill `viaje-dev`
capturing the engine's do-not-touch rules and how to publish content.

Rules: no questions to Anton unless a credential blocks; choose sensibly and log it in plan.md §9.
Keep your own token use low: specs are written, so your work is spawning, reviewing rendered output,
and small fixes.
