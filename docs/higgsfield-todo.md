# Higgsfield generation TODO — viaje.com.py

Generated 2026-09-05, cross-referenced against:
1. This session's harvest from the live WordPress site (viaje.com.py) — only 3 images passed the
   usability bar (real, high-res, on-subject, unwatermarked): see the harvest list in the session summary.
2. Image sets already sitting in `sites/viaje.com.py/assets/img/` from a **separate, concurrent session**
   (18 image sets covering all 5 services, all 6 activities, and the 3 trips — see note below). Those
   already close most of the gap this list would otherwise contain.

**Note on concurrent work:** while this session was running, another local session appears to have
generated and converted imagery for this same repo directly into `assets/img/` (untracked, not yet
committed). Its files are not duplicated here. If that work is superseded or reverted, re-derive this
list from `sites/viaje.com.py/urls.txt` and `docs/imagery-brief.md`.

## Confirmed remaining gap

| Page / slot | Subject needed | Aspect ratio | Notes |
|---|---|---|---|
| `content/data/team.json` — all 4 team members | Individual portrait headshots of each team member (or a placeholder-appropriate stock-alike if no real staff photos exist) | 1:1 | `photo: null` for all 4 in the current data file; no candidate exists anywhere in the live WP media library either. This is the one item with zero source material of any kind. |

No other confirmed gaps — every service, activity, and trip slug has at least one candidate image
already on disk (from the concurrent session) or from this session's harvest. Recommend re-auditing
once that other session's work is reconciled, in case any of its filename-to-subject mappings are
approximate (e.g. `salto-suizo-colonia-independencia.jpg` for the Salto Suizo Ybytyruzú page — verify
the actual subject before wiring it in).

## On-page SEO pass (2026-09-29) — nothing new is blocked, one slot stays empty

No image was generated for this pass; every page already has a hero with descriptive alt text,
width/height and WebP variants. The only slots that still want art are the people ones, and the
site renders fine without them (the author box and the team grid skip a missing photo):

| Slot | File to create | Alt text | Aspect ratio | Prompt |
|---|---|---|---|---|
| Author avatar — Anton Marklund (author box on posts) | `assets/img/autor-anton-marklund.jpg` | Retrato de Anton Marklund, fundador de Viaje.com.py | 1:1 | Natural-light portrait of a friendly Nordic-looking man in his 30s, casual linen shirt, standing outdoors on a red-earth road in Paraguay at golden hour, soft green hills behind, shallow depth of field, honest documentary style, no text, no logos. |
| Author avatar — Equipo Viaje.com.py | `assets/img/autor-equipo-viaje.jpg` | Equipo de Viaje.com.py recorriendo un camino de tierra en Paraguay | 1:1 | Three travellers seen from behind walking a red-earth road toward a hazy valley at sunrise, backpacks and a tereré thermos, warm documentary light, no faces, no text. |
| `content/data/team.json` portraits (4 rows, `photo: null`) | `assets/img/equipo-<nombre>.jpg` | Retrato de <nombre>, <rol> en Viaje.com.py | 1:1 | Only with real staff photos; do not generate lookalikes of real people. |

To wire an avatar in later: add a `photo` key under the author in `config.php` `authors` and render it
in `engine/templates/partials/author-box.php` (a 5-line change), then run `tools/verify.php --strict`.


## Content pass 2 (2026-09-30) — hero images for the new activity pages

Five activity pages were added (`/actividades/laguna-blanca/`, `salto-cristal/`, `itaipu-hernandarias/`,
`cerro-tres-kandu/`, `aregua/`). No image was generated. Nothing on disk shows any of these five subjects
(the closest, `guia-mirador-cerros.jpg`, is a lookout over a dirt road, not a summit trek), so the pages ship
**without a hero**: a wrong one is worse than none, and `hero_alt` is only required when `hero` is set.

When these are made, save as `assets/img/<file>.jpg` with 480/960/1600 WebP variants next to it (like the
existing files), then add `hero:` and `hero_alt:` to the page's front matter in `/admin/`.

| Page | File to create | Alt text | Aspect ratio | Prompt |
|---|---|---|---|---|
| `/actividades/laguna-blanca/` | `assets/img/laguna-blanca-agua-transparente-san-pedro.jpg` | Agua transparente sobre arena blanca en Laguna Blanca, con un kayak en la orilla y bosque al fondo. | 3:2 | Photoreal editorial travel photograph of a small natural lake with crystal-clear shallow water over pale calcareous sand, a single kayak pulled up on the white sand shore, native forest and cerrado vegetation behind, soft morning light, true-to-life colour, no people facing camera, no text, no logos. |
| `/actividades/salto-cristal/` | `assets/img/salto-cristal-cenote-paraguari.jpg` | Una cascada cae a una piscina natural de agua esmeralda rodeada de paredes de roca y vegetación colgante. | 3:2 | Photoreal editorial travel photograph of a cenote-like natural pool enclosed by tall rock walls with hanging vegetation, a waterfall feeding it, midday sunlight entering from above and turning the water emerald and turquoise, ferns and moss on wet stones, no people, no text, no logos. |
| `/actividades/itaipu-hernandarias/` | `assets/img/itaipu-hernandarias-costanera-represa.jpg` | La costanera de Hernandarias frente al Lago Itaipú, con la represa iluminada al atardecer. | 3:2 | Photoreal editorial travel photograph of a lakeside promenade at dusk facing a very large concrete hydroelectric dam lit up in soft colours, families walking, calm reservoir water, warm evening light, no readable signage, no text, no logos. |
| `/actividades/aregua/` | `assets/img/aregua-ceramica-iglesia-candelaria-lago.jpg` | Piezas de cerámica en una calle de Areguá con la Iglesia de la Candelaria y el lago Ypacaraí al fondo. | 3:2 | Photoreal editorial travel photograph of a small hillside town street with handmade clay pots and ceramics on display, a hilltop church at the end of the street and a wide lake glimpsed below at golden hour, no readable signage, no text, no logos. |
| `/actividades/cerro-tres-kandu/` | `assets/img/cerro-tres-kandu-cumbre-trekking.jpg` | Senderistas ascienden por un tramo de roca con cabos de acero en el Cerro Tres Kandú, entre bosque nuboso. | 3:2 | Photoreal editorial travel photograph of hikers seen from behind climbing a steep rocky trail section with steel cables as handholds, misty cloud forest around them, a wide valley visible below, early light, no faces, no text, no logos. |
