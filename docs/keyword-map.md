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
| `/viajes/` | viajes por Paraguay | hub | exact (`viajes`) |
| `/viajes/fin-de-semana-en-encarnacion/` | fin de semana en Encarnación | trip | exact |
| `/viajes/ruta-del-chaco-3-dias/` | ruta del Chaco en 3 días | trip | exact |
| `/viajes/escapada-a-las-misiones-jesuiticas/` | escapada a las Misiones Jesuíticas | trip | exact |

## Adding a page

1. Pick a phrase nobody above uses (search this file first).
2. Put it in `keyword:`, in the `seo_title` (start with it, ≤ 60 characters including any brand
   suffix), in the H1 (`title`) and in the first paragraph.
3. Write a 140–160 character `description` that gives a reason to click.
4. Add the row here. `php tools/verify.php viaje.com.py --strict` must stay clean.
