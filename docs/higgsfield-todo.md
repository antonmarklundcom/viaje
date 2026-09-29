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

