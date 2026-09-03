# Phase 2 report — viaje.com.py content, theme and imagery

## What was ported

- The 13 real pages (5 services, `/servicios/`, `/nosotros/`, `/faq/`, `/contacto/`, `/blog/`, home, both blog
  posts) with copy ported verbatim from `docs/viaje-com-py-scan.md` §3, minus the confirmed theme-demo
  leftovers (TourDen footer, "Harbert Spin" bio, Lorem filler, "0+" counters, discount badges, "Tour
  Packages" nav, "No content is added yet." newsletter block, the discount-badge city cards on the
  homepage — none of these are real content per the scan's own §2/§5 findings).
- Theme identity per `docs/site-spec-viaje.md` §1: Fraunces display font (loaded via `head_extra`,
  preconnected to Google Fonts), the color tokens (already scaffolded from phase 1, left as-is — they
  matched the brief exactly), logo/favicon updated to reference Fraunces.
- FAQ: the 6 site-wide Q&As (scan §4) and the 10 `/faq/`-page Q&As (scan §3.9) merged into one deduped
  13-item `content/data/faq.json` — 3 topic pairs merged (road safety, 4x4 necessity, best season), folding
  the unique fact from each pair's shorter answer into the fuller one. The 6 most logistics-focused items
  keep the original site-wide widget's topics and are tagged `home`/`servicios`/`nosotros` so they still
  surface on those three pages; all 13 show on `/faq/`.
- `content/data/testimonials.json`: the single real Rodrigo B. testimonial, verbatim. No testimonials
  invented.
- `content/data/team.json`: the four names from the scan (Marcos Benítez, Lucía Ferreira, Andrés Villalba,
  Raquel Galeano), `role: null` — the scan explicitly found no bios/roles in the flat-text pass, so none
  were invented.
- `content/data/gallery.json`: 6 items on ids 03/04/05/06/11/13, captioned with the homepage's own gallery
  category labels (scan §3.1: Rutas del Agua, Historia Viva, Horizontes del Chaco, Naturaleza Pura,
  Escapadas Urbanas, Sabores Locales — all 6 existed in the scan, so none were invented).
- 6 new activity pages (`/actividades/<slug>/`) and 3 new trip pages (`/viajes/<slug>/`) per
  `docs/site-spec-viaje.md` §3, each linking to its relevant service page and (where the pillar post
  actually covers that destination) to the pillar post's matching H2 anchor.
- `/actividades/` and `/viajes/` hubs enabled and added to the header nav after "Servicios"; `/novedades/`
  stays enabled but out of nav (still empty — unchanged from phase 1, KNOWN-ISSUES #5).
- Imagery: 18 images generated via the Higgsfield MCP (`nano_banana_2` at 2K for the two hero shots, ids
  01 and 03; `nano_banana_flash`/`nano_banana_2_lite` at 1K for the rest) — exactly the ids
  `docs/site-spec-viaje.md` references. **20 credits spent, well under the 700-credit cap; no ids dropped.**
  `docs/imagery-manifest.json` committed with full preflight/spend notes in its `_notes` header. Hero/gallery
  `src` fields in content point directly at the manifest's remote CDN URLs (v1, per the imagery brief — this
  sandbox cannot reach the CDN to download); `tools/localize-media.php` in phase 4 downloads them into
  `sites/viaje.com.py/assets/img/` and rewrites references to the manifest's `file` names.
- `content/pages/contacto.md` embeds a Google Maps iframe for Edificio Skytower, Asunción (Parsedown passes
  raw HTML through by design, spec §3).

## Decisions made (not asked, per autonomy protocol §4)

- **Homepage "Destinos Locales" city cards and their "% Off" discount badges were not ported.** The scan
  flags these as decorative theme-card leftovers with no real discount mechanic elsewhere on the site
  (scan §3.1 note, §8 item 5), and the `home.php` template has no card-grid slot for them — porting them
  would have meant inventing a component outside the frozen engine. Noted here rather than blocking.
- **Hub hero images (ids 11 for `/blog/`, 26 for `/servicios/`) are unused.** `hub.php` has no hero-image
  slot and the `hubs` config schema has no `hero` key — both frozen. The site-wide `default_og_image`
  (image 01) still covers their `og:image`. Not a content gap, a template-capability limit; noted in
  KNOWN-ISSUES.
- **`encarnacion-costanera` activity links to the `fin-de-semana-en-encarnacion` trip instead of a pillar
  post H2 anchor.** Encarnación is not one of the pillar post's 10 destinations, so no matching anchor
  exists; the trip page is a more useful real internal link than forcing a mismatch.
- Post dates: set from the scan's sitemap `lastmod` values where present (flagship `2026-02-03`, the
  second post `2026-04-10`), per `docs/site-spec-viaje.md` §2's content-porting rule, rather than the
  on-page byline date (both bylines read Feb 3 on-page; the scan itself notes the second post's later
  `lastmod` "suggests it was edited more recently").
- Every `description` across all content types was written/adjusted to the 120–160 char range (definition
  of done item 4 makes this a global rule, not just for services).

## Ambiguous in the scan

- The scan could not confirm whether the "% Off" discount badges were ever a real promotion (§8 item 5).
  Treated as decorative per the anti-catalog positioning and not ported; flag to Anton if a real discount
  mechanic should exist.
- Team member roles/bios were never captured live (scan §8 item 3) — `team.json` ships names only.

## Still open (plan §7, not blocking)

- `wp-content/uploads` folder (item 5) — still not supplied; the slot exists and `urls.txt` still has no
  rows for it (KNOWN-ISSUES #3, unchanged this phase since the folder never arrived).
- Real counters or confirmed removal (item 7) — removed per Decision 7, no numbers supplied.
- Social profile URLs (item 11) — footer/schema `sameAs` still `null`, partial skips them cleanly.
- VenderCRM tenant key (item 9) — leads work without it (writes to `site/data/leads/`, mail is best-effort).
