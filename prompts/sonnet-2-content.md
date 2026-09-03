# Phase 2 — Content and imagery for viaje.com.py. Paste into a fresh SONNET session on antonmarklundcom/viaje.

Read `plan.md` (§1, §4, §5, §6, §7, §9 build log), `KNOWN-ISSUES.md`, `docs/engine-spec.md` §0 and
§15 (layout and how verification works; the engine is built and FROZEN — you do not change the
router, content model, SEO builders, admin write path, or redirect semantics; work around and note
in Backlog). Then the two specs you execute: `docs/imagery-brief.md` and `docs/site-spec-viaje.md`.
**Do NOT execute `docs/site-spec-ttdp.md`** — thingstodoinparaguay.com is built in its own repo (plan
§3.2, §9 director entry); leave `sites/thingstodoinparaguay.com/` untouched. Sources for copy: `docs/viaje-com-py-scan.md` §3, §4, §6, §7.

Sanity check first: `php tools/verify.php viaje.com.py && php tools/verify.php thingstodoinparaguay.com`
must exit 0 on `main`. If not, fix that first with a small, surgical change and log it.

Branch `phase/2-content` off latest `main`. One PR for everything below.

## Work, in this order (step A may run as a background Sonnet subagent in parallel with B)
A. Imagery — read `docs/imagery-brief.md` and the `higgsfield-web-imagery` skill. Do the cost
   preflight, then generate the pool with the Higgsfield MCP tools (generate_image_batch →
   jobs_wait → show_generation_by_ids), respecting the 700-credit cap. Generate only the ids that
   `docs/site-spec-viaje.md` references; skip ids used solely by the archived TTDP spec. Images 01, 02, 03 use
   nano_banana_2 at 2K, the rest nano_banana_flash at 1K. Write `docs/imagery-manifest.json`
   exactly in the brief's shape with the remote result URLs. Do not download images (the CDN is
   blocked here). Record credits spent and any ids dropped in the build log.
B. viaje.com.py — execute `docs/site-spec-viaje.md` fully. Image URLs come from the manifest; if
   it does not exist yet use the placeholder `/assets/img/<file>.jpg` with the manifest's future
   filename and real alt text, list every placeholder in `docs/phase-2-report.md`, and swap them
   from the manifest once A finishes.
C. Blog differentiation — this one edit is Opus-grade (plan §11): spawn an OPUS subagent
   (Agent tool, `model: "opus"`) with: "Read plan.md §6 row 'Blog-post overlap'. Edit only
   sites/viaje.com.py/content/posts/destinos-imperdibles-2026.md: keep URL and slug; distinct
   title/H1 and intro around a different search intent (seasonal/when-to-go and weekend-escape
   planning, Paraguayan Spanish); trim destination sections that duplicate the pillar
   (paraguay-destinos-imprescindibles-2026.md) to 2–3 sentence summaries linking to the pillar's
   matching H2 anchors; keep every unique fact and every unique :::tip; update description and
   seo_title; do not touch the pillar. Run verify.php afterwards."

## Review before the PR (you, adversarially)
`php tools/verify.php` for both sites (the TTDP fixture must still pass, unchanged). Serve viaje with
`php tools/serve.php viaje.com.py` and read the rendered HTML of `/`, `/contacto/`, `/faq/`, one
service and one post: titles and descriptions, JSON-LD validity, no theme leftovers or demo filler, WhatsApp
number and email from plan §1/§7, internal links resolve, every image has alt text. Fix what you find.

Exit: verify.php exits 0 for both sites; no `TODO-PHASE-2` markers remain in `sites/viaje.com.py/`; every page in plan §5
has real copy; `docs/imagery-manifest.json` committed; CI green; PR merged; build-log entry in
`plan.md` §9 (credits spent, placeholders remaining if any, deviations, where phase 4 looks first);
`KNOWN-ISSUES.md` updated. Open items that need Anton (plan §7) are listed in the build log, never
blocking.

## After this phase — hand off to the next (fresh session)
Only when all four gates pass (PR merged green; exit checklist passed; pre-handoff audit done;
build-log entry on `main`): call `create_session` with environment and permission mode inherited
(never `plan`), `model` = Sonnet (`claude-sonnet-5`, never Fable), title `viaje — Phase 4 deploy +
cutover`, prompt exactly: `Read prompts/sonnet-4-deploy-cutover.md in this repo and execute it.`
If `create_session` is unavailable, continue in this window.

On a hard blocker, or after the handoff, if a cross-session message tool is available send a
one-paragraph status to the director session `session_016yin5ETmxXTCT2qPPmRBur`; otherwise skip.
End with the phase report.
