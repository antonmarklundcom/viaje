# Imagery brief — shared Paraguay photo pool for viaje.com.py and thingstodoinparaguay.com

One pool of generated editorial travel photographs, reused by both sites (different crops, scrims,
captions). Generated once via the Higgsfield MCP; results are referenced by their remote URLs in v1
because this build environment cannot download from the Higgsfield CDN. `tools/localize-media.php`
(phase 4) downloads them into `sites/<domain>/assets/img/` and rewrites references.

Output of the imagery pass: `docs/imagery-manifest.json` — array of objects
`{ "id", "file", "alt_es", "alt_en", "ratio", "px", "model", "prompt", "url", "width", "height", "job_id" }`.
`file` is the future local filename (lowercase, hyphenated, keyword-bearing, `.jpg`). `url` is the remote result.

## Art direction (applies to every prompt)

Photoreal editorial travel photography, as shot for a premium travel magazine on a 35 mm full-frame
camera, natural light, golden hour or soft overcast, true-to-life colour with warm earth tones
(red laterite soil, deep tropical green, amber sky), slight film grain, wide establishing framing
with a clear subject, honest and un-staged. Paraguay-specific details where relevant: red dirt
roads, lapacho trees, palm savanna, tereré, colonial Jesuit stone, riverbanks. No text, no logos,
no watermarks, no signage with legible words, no captions, no people's faces in close-up (people are
small, back-turned or mid-distance), no fabricated landmarks, no waterfalls that look like Iguazú
unless the subject is Saltos del Monday.

Negative block appended to every prompt: `no text, no watermark, no logo, no caption, no border,
no illustration, no cartoon, no oversaturated HDR, no fisheye, no close-up faces, no duplicated limbs`.

Consistency: generate image 01 first, register it as a reference Element (`show_reference_elements`
create) and embed `<<<element_id>>>` in every following prompt (models that support Elements only).

## Models and budget

- Cost preflight first (`get_cost: true` on `generate_image`) for `nano_banana_2` at 2048 px and
  `nano_banana_flash` at 1024 px; report both numbers in the manifest header notes.
- Heroes marked `2K` → `nano_banana_2`, 2048 px. Everything else → `nano_banana_flash`, 1024 px.
- Hard cap for this pass: **700 credits**. If the preflight shows the plan exceeds the cap, drop
  all non-hero images to `nano_banana_flash` and, if still over, reduce the pool from the bottom of
  the list (highest ids first). Never exceed the cap.
- `use_unlim` must be omitted/false; this account pays from credits.

## The pool (id, ratio, model, subject → direction)

Landmarks and landscapes
01 21:9 2K `camino-de-tierra-roja-4x4-paraguay` — a 4x4 pickup small in frame on a red laterite road winding through green rolling hills at dawn, mist in the valleys, lapacho trees. (viaje.com.py home hero)
02 21:9 2K `asuncion-costanera-atardecer` — Asunción riverside costanera at sunset, Paraguay River wide and calm, city skyline warm-lit, a few people strolling far away. (thingstodoinparaguay.com home hero)
03 16:9 2K `saltos-del-monday-cascada` — Saltos del Monday waterfalls near Ciudad del Este, wide curtain of white water into a jungle gorge, mist and rainbow, viewing platform tiny at the edge.
04 16:9 flash `ruinas-jesuiticas-trinidad-atardecer` — Jesuit mission ruins of La Santísima Trinidad del Paraná, red sandstone arches and bell tower in low golden light, long shadows on grass.
05 16:9 flash `chaco-paraguayo-palmares-atardecer` — the Paraguayan Chaco, flat palm savanna with scattered karanda'y palms, huge amber sky, a dirt track, dry season dust.
06 16:9 flash `lago-ypacarai-san-bernardino` — Lago Ypacaraí seen from San Bernardino, wooden pier, calm water, hills behind, late afternoon.
07 16:9 flash `encarnacion-playa-san-jose` — Encarnación's Playa San José, river beach with soft sand, costanera promenade, palm trees, summer light.
08 16:9 flash `salto-suizo-colonia-independencia` — Salto Suizo waterfall in the Ybytyruzú hills, tall thin cascade into a forest pool, hikers small on rocks.
09 16:9 flash `cerro-cora-parque-nacional` — Cerro Corá national park, rounded hills rising from savanna, morning haze, a lone tree.
10 16:9 flash `rio-paraguay-pantanal-lancha` — a small boat on the Paraguay River in the Pantanal, water lilies, egrets taking off, soft dawn.
11 16:9 flash `centro-historico-asuncion-calle` — a colonial street in Asunción's historic centre, pastel façades, lapacho in bloom, a person walking away.
12 4:3 flash `aregua-ceramica-artesanal` — Areguá artisan pottery stalls, terracotta and painted ceramics on wooden tables, cobbled street, warm afternoon.
13 4:3 flash `terere-guampa-yerba-mate` — tereré ritual: a guampa with bombilla, thermos with ice, fresh yerba and yuyos on a wooden table, dappled shade.
14 4:3 flash `chipa-y-asado-paraguayo` — Paraguayan food: fresh chipa in a basket next to a rustic asado, plates on a country table.
15 16:9 flash `ruta-transchaco-filadelfia` — the Ruta Transchaco near Filadelfia, dead-straight highway to the horizon, red shoulders, immense sky, a truck far away.
16 16:9 flash `laguna-blanca-arena-blanca` — Laguna Blanca in San Pedro, crystal water over white sand, a canoe, cerrado vegetation.
17 16:9 flash `humedales-neembucu-aves` — Ñeembucú wetlands, esteros with reeds, a flock of birds low over the water, pink dawn.
18 16:9 flash `vinedos-colonia-independencia` — vineyards of Colonia Independencia on hillside terraces, Ybytyruzú hills behind, late light.
19 4:3 flash `basilica-de-caacupe` — Basilica of Caacupé, blue dome and towers, plaza with pilgrims far away, clear sky.
20 4:3 flash `vela-lago-ypacarai` — small sailboats on Lago Ypacaraí, breezy afternoon, whitecaps, hills.
21 16:9 flash `cruce-de-rio-4x4-aventura` — a 4x4 fording a shallow red-brown river in the countryside, splash frozen, palms on the bank.

Service and trade illustrations (no captions that claim identity; faces never close-up)
22 16:9 flash `agencia-de-viaje-planificacion-mapa` — a travel planner's desk from above: paper map of Paraguay, notebook, coffee, phone, pen; hands only.
23 16:9 flash `gestion-de-visas-pasaporte-documentos` — passport, printed forms, boarding pass and a stamp on a tidy desk, soft window light; no legible text.
24 16:9 flash `traslado-privado-van-amanecer` — a clean black passenger van on a quiet highway at dawn, headlights on, hills behind.
25 16:9 flash `vacaciones-familia-lago` — a family seen from behind walking toward a lake shore with towels and a cooler, golden light.
26 16:9 flash `guia-mirador-cerros` — a guide and two travellers from behind at a hilltop lookout over green valleys, arms pointing at the horizon.
27 16:9 flash `asistencia-personalizada-aeropuerto` — a traveller at an airport window at dawn holding a phone, plane on the tarmac beyond, calm.
28 16:9 flash `asuncion-skyline-oficinas` — Asunción modern skyline with glass office towers at blue hour, seen from across the river.

## Alt text

Write `alt_es` (Paraguayan Spanish) and `alt_en` per image: one descriptive sentence, place name included where natural, no "imagen de", no keyword stuffing.
