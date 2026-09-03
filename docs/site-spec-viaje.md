# Site spec — viaje.com.py (phase 2)

Build contract for the content, theme and page composition of `sites/viaje.com.py/` on the engine from
`docs/engine-spec.md`. The engine is frozen: do not modify anything under `engine/` or `tools/`. If the
engine cannot express something, work around it inside the site (theme.css, content, config) and note it
in `KNOWN-ISSUES.md` under "Phase 2".

Sources of truth, read in full: `docs/viaje-com-py-scan.md` §3 (verbatim copy per page), §4 (FAQ
accordion), §6 (template patterns), §7 (contact). Also `plan.md` §1, §5, §6 and `docs/imagery-manifest.json`
(image URLs + alt text; if a referenced id is missing from the manifest, use the closest subject and note it).

## 1. Identity and theme (`sites/viaje.com.py/theme.css`, config)

- Voice: Paraguayan Spanish, voseo as on the live site ("Dejá", "Descubrí", "Suscribite"), warm and
  confident, anti-catalog ("viajes a medida, no paquetes rígidos").
- Tokens (override in theme.css):
  `--color-bg:#FAF7F2; --color-surface:#FFFFFF; --color-text:#1E2420; --color-muted:#5B655F;
   --color-primary:#0F5C4A; --color-primary-contrast:#FFFFFF; --color-accent:#D9772B;
   --color-border:#E6E0D6; --radius:14px; --font-display:"Fraunces", Georgia, serif;
   --font-body: system-ui, -apple-system, "Segoe UI", Roboto, sans-serif;`
  Load Fraunces (weights 500/600, `opsz` axis) via `config['head_extra']` with `preconnect` to
  fonts.googleapis.com/gstatic and `display=swap`. One font only.
- `config['theme_color'] = '#0F5C4A'`. Logo: `sites/viaje.com.py/assets/logo.svg` — wordmark
  "viaje" in Fraunces with ".com.py" in the accent colour, plus a small compass-rose mark; also
  `favicon.svg` (the mark) and `og-default.jpg` is not needed (use image 01's URL as
  `config['default_og_image']`).
- Header CTA: "Consultar por WhatsApp" (accent button). Floating WhatsApp button on every page except admin.
- Footer: brand blurb (write 2 sentences from the /nosotros/ story: Swedish founder in Paraguay, travel
  designed to measure), "Páginas principales" nav (the 13 real URLs), contact block (phone, `hola@viaje.com.py`,
  Edificio Skytower, Asunción), "Seguinos" socials (Instagram/Facebook placeholders as `null` until
  Anton supplies URLs → the partial skips nulls), copyright "Viaje.com.py © <year> — Todos los derechos reservados."
  Absolutely nothing from the TourDen template survives.

## 2. Page map (every row of plan §5 with status 200)

| Path | File | Type/layout | `seo_title` | Hero image id |
|---|---|---|---|---|
| `/` | `content/pages/home.md` | page/home | `Viaje.com.py — Agencia de viajes en Paraguay: rutas a medida, traslados y asistencia` | 01 |
| `/agencia-de-viaje/` | `content/services/agencia-de-viaje.md` | service | `Agencia de Viaje en Paraguay — Viajes a medida sin paquetes rígidos \| Viaje.com.py` | 22 |
| `/asistencia-personalizada/` | `content/services/asistencia-personalizada.md` | service | `Asistencia Personalizada para Viajeros en Paraguay \| Viaje.com.py` | 27 |
| `/gestion-de-visas/` | `content/services/gestion-de-visas.md` | service | `Gestión de Visas desde Paraguay — Asesoría y trámites \| Viaje.com.py` | 23 |
| `/traslados/` | `content/services/traslados.md` | service | `Traslados Privados en Paraguay — Aeropuerto, ciudades e interior \| Viaje.com.py` | 24 |
| `/vacaciones/` | `content/services/vacaciones.md` | service | `Vacaciones en Paraguay a tu medida — Escapadas y rutas \| Viaje.com.py` | 25 |
| `/servicios/` | config hub | hub (service) + FAQ widget | `Servicios de Viaje en Paraguay \| Viaje.com.py` | 26 |
| `/nosotros/` | `content/pages/nosotros.md` | page/default | `Nosotros — Quiénes somos en Viaje.com.py` | 09 |
| `/faq/` | `content/pages/faq.md` | page/faq | `Preguntas Frecuentes sobre Viajar por Paraguay \| Viaje.com.py` | 05 |
| `/contacto/` | `content/pages/contacto.md` | page/contact | `Contacto — Escribinos por WhatsApp o email \| Viaje.com.py` | 28 |
| `/blog/` | config hub | hub (post) | `Blog de Viajes por Paraguay — Destinos, rutas y consejos \| Viaje.com.py` | 11 |
| `/paraguay-destinos-imprescindibles-2026/` | `content/posts/paraguay-destinos-imprescindibles-2026.md` with `path:` | post | keep the live title verbatim (scan §1), suffix ` \| Viaje.com.py` only if total ≤ 70 chars, else no suffix | 03 |
| `/destinos-imperdibles-2026/` | `content/posts/destinos-imperdibles-2026.md` with `path:` | post | keep the live title verbatim | 04 |

Service pages keep their H1 exactly as on the live site. Every `description` is hand-written, 120–160
chars, states the benefit and includes "Paraguay" naturally. Every service page front matter carries
`included:` (the 6 "Servicios Incluídos" items verbatim) and `intro:` (the hero paragraph).

Content porting rules:
- Copy is ported **verbatim** from scan §3, including headings and paragraph order. Fix only
  obvious typos, the `hola@viaje.com` typo, and remove theme leftovers (lorem, "BLOG Details",
  "Harbert Spin", "No content is added yet.", "Tour Packages", discount badges, "0+" counters).
- The two blog posts: port verbatim, H2 numbering intact, italic `*Dato de viajero:*` / `*Tip fotográfico:*`
  / `*Cuándo ir:*` style callouts converted to `:::tip <label>` blocks. Author: `Equipo Viaje.com.py`
  (the flagship's inline byline "Por: Yanina / Equipo Viaje.com.py" becomes `author: Yanina — Equipo Viaje.com.py`).
  `date` = the sitemap's date if present in the scan, else `2026-04-17`. **Do not differentiate or trim the
  posts** — a separate editorial step does that after you finish.
- Home (`layout: home`): hero from scan §3.1 (kicker, H1, hook, CTAs → primary "Consultar por WhatsApp",
  secondary "Ver servicios" → `/servicios/`), intro copy as body, `features:` = the 3–4 "why us" blocks from
  §3.1 with icons, `stats:` omitted, `gallery: true` (write `content/data/gallery.json` with 6 items using
  ids 03 04 05 06 11 13 and the §3.1 category labels; if only two labels exist in the scan, invent the
  remaining four sensibly: "Rutas del Agua", "Escapadas Urbanas", "Historia y Cultura", "Naturaleza y
  Aventura", "Chaco y Pantanal", "Sabores de Paraguay"), `testimonials: true` (testimonials.json with the
  single real Rodrigo B. testimonial — do not invent more), `show_services`, `show_posts`, `faq_tags: [home]`,
  CTA band from the §3.1 closing CTA ("Solicitá un presupuesto gratuito").
- Nosotros: verbatim story; `team.json` with the four names and roles only if roles are in the scan,
  otherwise names only and `role: null`; no bios invented. Add the site-wide FAQ widget (`faq_tags: [nosotros]`).
- FAQ: merge §4's six site-wide Q&As and §3.9's ten into `content/data/faq.json`. Deduplicate by topic
  (road safety, 4x4, seasons appear in both): keep the fuller answer, fold unique facts from the other
  into it. Tag every item with `faq`; tag the 5–6 most logistics-focused with `home`, `servicios`,
  `nosotros`. The `/faq/` page shows all.
- Contact page: body = the §3.10 intro; the engine's lead form + WhatsApp composer render below;
  `topics` in config = the five service titles + "Otra consulta". Add a Google Maps embed of
  "Edificio Skytower, Asunción" as an `<iframe loading="lazy">` in the body **only if** the engine's
  markdown passes raw HTML through (Parsedown does); title attribute in Spanish.
- Every image gets its `alt_es` from the manifest. Hero `hero_alt` required.

## 3. New seed content (so hubs are useful on day one)

Activities (`content/activities/`, path `/actividades/<slug>/`), each 450–650 words in Spanish with a
`facts` box (location, duration, price_from as "Consultar", best_season, difficulty), 3–4 H2s, one `:::tip`,
internal links to the relevant service page and to the flagship post's matching H2 anchor. Facts come
from the two blog posts in scan §3.12/§3.13 and general knowledge; never invent prices or opening hours
(say "consultar"):
1. `saltos-del-monday` (id 03)  2. `misiones-jesuiticas-trinidad-jesus` (04)  3. `chaco-paraguayo` (05)
4. `lago-ypacarai-san-bernardino` (06)  5. `encarnacion-costanera` (07)  6. `salto-suizo-ybytyruzu` (08)

Trips (`content/trips/`, path `/viajes/<slug>/`), 350–500 words, `itinerary` list, `facts` with duration
and group_size, price_from "A medida": 1. `fin-de-semana-en-encarnacion` (07) 2. `ruta-del-chaco-3-dias` (15)
3. `escapada-a-las-misiones-jesuiticas` (04).

Enable hubs `/actividades/` and `/viajes/` in config with proper titles/descriptions; add "Actividades" and
"Viajes" to the header nav after "Servicios". `/novedades/` stays enabled but is not in the nav until it has
content (config option `nav_hide_empty_hubs` if the engine has it; otherwise simply omit it from nav).

## 4. Definition of done (phase 2)

1. `php tools/verify.php viaje.com.py` exits 0 (urls.txt extended with the new activity/trip/hub URLs, all 200).
2. `grep -ri "tourden\|harbert\|lorem\|viaje\.com[^.]" sites/viaje.com.py/` finds nothing (the email regex catches `hola@viaje.com` without `.py`).
3. No `TODO-PHASE-2` markers remain.
4. Every content file has `description` (120–160 chars) and, when it has a hero, `hero_alt`.
5. Open the home, one service, `/faq/`, `/contacto/`, one post and one activity in the built-in server and
   read the rendered HTML: one H1, breadcrumbs correct, JSON-LD types correct (`FAQPage` only on /faq/),
   the WhatsApp links contain `595995628862`, footer email is `hola@viaje.com.py`.
6. Write a short `docs/phase-2-report.md`: what was ported, decisions made, anything ambiguous in the scan.
