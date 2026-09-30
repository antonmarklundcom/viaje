# Keyword map — viaje.com.py

One primary search phrase per URL, and no two URLs share one. The phrase lives in each page's
`keyword:` front matter (hubs: `keyword` in `sites/viaje.com.py/config.php`) and must appear in the
**title**, the **H1** and the **first paragraph**. `php tools/verify.php viaje.com.py` warns when it
does not, fails when two pages claim the same phrase, and warns when this table and the front matter
disagree — so change both together.

**Slug.** URLs are the contract (plan §5) and never change, so the slug can only carry the phrase
where the original WordPress slug already did. "Slug match" says how close each one is; "legacy"
means the slug is frozen and the phrase is carried by title, H1 and first paragraph instead.

**Different intent, not competition.** The two Misiones pages share words but not searches:
the activity page answers *"how do I visit them"*, the trip page answers *"can you organise it for
me"*. The same split holds for Encarnación (costanera guide vs. weekend route) and the Chaco
(destination guide vs. 3-day route). The home page targets the broad *turismo en Paraguay*;
`/viajes/` owns *viajes por Paraguay*; the agency page owns *agencia de viajes en Paraguay*.

| URL | Keyword | Type | Slug match |
|---|---|---|---|
| `/` | turismo en Paraguay | home | n/a |
| `/agencia-de-viaje/` | agencia de viajes en Paraguay | service | partial (`agencia-de-viaje`) |
| `/asistencia-personalizada/` | asistencia personalizada | service | exact |
| `/gestion-de-visas/` | gestión de visas | service | exact |
| `/traslados/` | traslados privados | service | partial (`traslados`) |
| `/vacaciones/` | vacaciones en Paraguay | service | partial (`vacaciones`) |
| `/servicios/` | servicios de viaje en Paraguay | hub | partial (`servicios`) |
| `/nosotros/` | quiénes somos | page | legacy |
| `/faq/` | preguntas frecuentes | page | legacy (`faq`) |
| `/contacto/` | contacto Viaje.com.py | page | partial (`contacto`) |
| `/blog/` | blog de viajes por Paraguay | hub | partial (`blog`) |
| `/paraguay-destinos-imprescindibles-2026/` | destinos imprescindibles | post | exact |
| `/destinos-imperdibles-2026/` | escapada de fin de semana en Paraguay | post | legacy (re-angled, plan §6) |
| `/actividades/` | qué hacer en Paraguay | hub | partial (`actividades`) |
| `/actividades/saltos-del-monday/` | Saltos del Monday | activity | exact |
| `/actividades/misiones-jesuiticas-trinidad-jesus/` | Misiones Jesuíticas de Trinidad y Jesús | activity | exact |
| `/actividades/chaco-paraguayo/` | Chaco paraguayo | activity | exact |
| `/actividades/lago-ypacarai-san-bernardino/` | Lago Ypacaraí y San Bernardino | activity | exact |
| `/actividades/encarnacion-costanera/` | Costanera de Encarnación | activity | partial (words reversed) |
| `/actividades/salto-suizo-ybytyruzu/` | Salto Suizo | activity | exact |
| `/actividades/laguna-blanca/` | Laguna Blanca | activity | exact |
| `/actividades/salto-cristal/` | Salto Cristal | activity | exact |
| `/actividades/itaipu-hernandarias/` | Itaipú y Hernandarias | activity | exact |
| `/actividades/cerro-tres-kandu/` | Cerro Tres Kandú | activity | exact |
| `/actividades/aregua/` | qué hacer en Areguá | activity | partial (`aregua`) |
| `/novedades/` | novedades de turismo en Paraguay | hub | partial (`novedades`) |
| `/novedades/asuncion-conde-nast-traveler-2026/` | Asunción en Condé Nast Traveler | news | partial (`asuncion-conde-nast-traveler`) |
| `/viajes/` | viajes por Paraguay | hub | exact (`viajes`) |
| `/viajes/fin-de-semana-en-encarnacion/` | fin de semana en Encarnación | trip | exact |
| `/viajes/ruta-del-chaco-3-dias/` | ruta del Chaco en 3 días | trip | exact |
| `/viajes/escapada-a-las-misiones-jesuiticas/` | escapada a las Misiones Jesuíticas | trip | exact |

## How to add a destination page

A new activity (or trip, post, news item) is a file in `sites/viaje.com.py/content-seed/<folder>/<slug>.md`
(`activities/`, `trips/`, `posts/`, `news/`). Use `content-seed/activities/saltos-del-monday.md` as the model, and only
write facts the site already states (`docs/viaje-com-py-scan.md`, `docs/site-spec-viaje.md`, other pages): an unknown
price is `Consultar`, an unknown hour or distance is left out.

1. **Keyword.** Pick a phrase nobody above uses (search this file first) and put it in `keyword:`.
2. **Front matter.** `title` (the H1, carries the keyword), `seo_title` (starts with the keyword, ≤ 60 characters
   including ` | Viaje.com.py`), `description` (140–160 characters, a reason to click), `keyword`, `quick_answer`
   (40–60 words), `date`, `updated`, `region`, `tags`, `related` (2–3 existing paths), `facts` (only known ones;
   `price_from: Consultar`), `faq` (3–5 `q`/`a` pairs whose questions are not already in
   `content-seed/data/faq.json`). `hero` + `hero_alt` (a descriptive sentence, ≥ 15 characters) only when an image of
   that subject exists in `assets/img/`; otherwise no hero, and list the image in `docs/higgsfield-todo.md`.
3. **Body.** 300–450 words: `##` sections, one `:::tip`, the keyword in the first paragraph, a link to a service
   (`/traslados/`, `/agencia-de-viaje/`, `/asistencia-personalizada/`) and, for the pillar's ten destinations, a link back to
   the matching anchor of `/paraguay-destinos-imprescindibles-2026/`. Never link a page to itself.
4. **URL contract.** Add the new path to `sites/viaje.com.py/urls.txt` as `<path> 200`.
5. **Menus.** Add it to the `nav` in `sites/viaje.com.py/config.php` (max 8 links per column; keep "Ver todas las …").
   Also list it in some other page's `related:` or body so it is not linked only from the header/footer.
6. **This file.** Add a row to the table above with the exact keyword from the front matter.
7. **Check.** `bash tools/ci-local.sh` must end with `ci-local: all steps passed`
   (`php tools/verify.php viaje.com.py --strict` at 0 failures and 0 warnings), then open the page with
   `php tools/serve.php viaje.com.py`.
8. **Live site.** Seed changes do not reach the running site by themselves; see "Publishing seed changes to the live
   site" in `docs/cutover-runbook.md`.
