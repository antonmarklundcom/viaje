# Phase 1 — Engine. Paste into a fresh OPUS session on repo antonmarklundcom/viaje.

Read `plan.md` first (§1 locked decisions, §2 architecture, §4 autonomy protocol, §5 URL contract,
§9 build log), then `docs/engine-spec.md` IN FULL. Build exactly what the spec says, nothing more.

## State you inherit
A previous Opus subagent got about a third of the way and its work was snapshotted to `main`
(commit "WIP: engine build in progress"): `engine/lib/{config,frontmatter,i18n,types,util}.php`,
`engine/lang/{es,en}.php`, `engine/vendor/Parsedown.php` (1.7.4, verbatim). Everything else in
spec §0 is missing: `bootstrap.php`, `dev-router.php`, `lib/{content,router,seo,render,markdown,
images,leads,admin}.php`, all `templates/`, `assets/`, `bin/`, `htaccess.template`, `tools/`,
both `sites/<domain>/` seeds (§14), `.github/workflows/ci.yml`, `README.md`, `KNOWN-ISSUES.md`.

Start by reading the existing lib files against their spec sections and fixing them where they
deviate; keep what is correct. Do not rewrite for style.

## Rules
- Branch `phase/1-engine` off latest `main`. One PR. Autonomy protocol plan §4 applies in full.
- PHP 8.4 with `gd` and `mbstring` is installed here; run the built-in server for smoke tests.
- No site-specific strings in `engine/` (spec §15.7). Site values live only in `sites/<domain>/`.
- The URL contract (plan §5 → `sites/viaje.com.py/urls.txt`) is non-negotiable; `tools/verify.php`
  must check every row (spec §12).
- Never use Fable for anything (plan §1 item 10). Subagents, if you use any, are Sonnet.
- Minor gaps go to `KNOWN-ISSUES.md`; never stall on one.

## Exit (spec §15, all seven items) plus
- CI workflow green on the PR; PR merged to `main`.
- Build-log entry appended to `plan.md` §9: what exists, deviations from the spec and why, the
  smoke-test evidence (curl output excerpts), and where phase 2 should look first.

## After this phase — hand off to the next (fresh session)
Only when all four gates pass (PR merged green; exit checklist passed; you re-ran verify.php on
`main` and adversarially re-read your merged diff; build-log entry on `main`): call
`create_session` with environment and permission mode inherited (never `plan`), `model` =
Sonnet (`claude-sonnet-5`, never Fable), title `viaje — Phase 2+3 content + imagery`, prompt exactly:
`Read prompts/sonnet-2-content.md in this repo and execute it.`
If `create_session` is unavailable, continue in this window.

If you hit a hard blocker (credential, locked decision that cannot work) or after the handoff, and a
cross-session message tool is available, send a one-paragraph status to the director session
`session_01Lch1f62XQuwvsDniCpbkGq`; if no such tool exists, skip it. Then end with the phase report.
