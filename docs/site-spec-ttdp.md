# Site spec — thingstodoinparaguay.com (phase 3)

Net-new English site on the shared engine (`docs/engine-spec.md`). Engine and tools are frozen; work only
inside `sites/thingstodoinparaguay.com/`. Read `plan.md` §1 and §3.2, `docs/imagery-manifest.json`, and —
as factual source material only — `docs/viaje-com-py-scan.md` §3.12 and §3.13 (the two Spanish blog posts).
Never translate them; write original English articles for an international reader who is planning a
trip to Paraguay. Cross-link to viaje.com.py as the place to book.

## 1. Identity and theme

- Voice: clear, friendly, practical English (US spelling), first-hand tone, no fluff, no "hidden gem"
  clichés. Audience: travellers from the US, Europe and neighbouring countries; expats; digital nomads.
- Tokens: `--color-bg:#FFFDF8; --color-surface:#FFFFFF; --color-text:#22201C; --color-muted:#66605A;
  --color-primary:#B4432A; --color-primary-contrast:#FFFFFF; --color-accent:#F2B33D; --color-border:#EDE6DA;
  --radius:12px; --font-display:"Bricolage Grotesque", "Segoe UI", sans-serif; --font-body: system-ui, sans-serif;`
  Load Bricolage Grotesque (600/700) via `config['head_extra']`. `theme_color` `#B4432A`.
- Logo: wordmark "Things to do in Paraguay" as an SVG with a small sun mark; favicon.svg = the mark.
- Contact NAP identical to viaje (same company). Email `hello@thingstodoinparaguay.com`? **No** — use
  `hola@viaje.com.py` (a mailbox that exists) and WhatsApp `595995628862`. Footer says "Operated by
  Viaje.com.py, a travel agency based in Asunción" with a link.
- Nav: Home · Things to do · Trips · Blog · About · Contact. Header CTA "Plan my trip" → `/contact/`.
- `type_paths`: activity `/things-to-do/`, trip `/trips/`, post `/blog/`, news `/news/`, service `/services/`
  (services disabled in nav; no service pages on this site).

## 2. Pages

| Path | File | Layout | `seo_title` | Hero id |
|---|---|---|---|---|
| `/` | `content/pages/home.md` | home | `Things to Do in Paraguay — Places, Trips and Practical Tips` | 02 |
| `/things-to-do/` | hub (activity) | | `Things to Do in Paraguay: 12 Places Worth the Trip` | 03 |
| `/trips/` | hub (trip) | | `Paraguay Trips and Itineraries — Weekend to Two Weeks` | 15 |
| `/blog/` | hub (post) | | `Paraguay Travel Blog — Guides and First-Hand Advice` | 11 |
| `/about/` | `content/pages/about.md` | default | `About Things to Do in Paraguay` | 09 |
| `/contact/` | `content/pages/contact.md` | contact | `Contact — Plan a Trip to Paraguay` | 28 |
| `/faq/` | `content/pages/faq.md` | faq | `Paraguay Travel FAQ — Safety, Visas, Money, Seasons` | 05 |

Home: hero kicker "Independent guide to Paraguay", H1 "Things to do in Paraguay", 2-sentence hook,
CTAs "Explore places" → `/things-to-do/` and "Plan my trip" → `/contact/`; `features` (4): "Written from
Asunción", "Honest logistics", "Book with a local agency", "Updated for 2026"; `gallery` 6 items (ids 03 04
05 06 13 10); `testimonials: false`; `show_posts`; FAQ subset tag `home`; CTA band "Want it organised for
you?" linking to `/contact/` and to `https://viaje.com.py/agencia-de-viaje/`.

About: who runs it (Viaje.com.py, a Swedish-founded travel agency in Asunción — facts from the viaje
`/nosotros/` copy in scan §3.8, rewritten in English, no invented bios), why the site exists, how
recommendations are chosen, editorial policy line.

FAQ (`content/data/faq.json`): 12 English Q&As covering visas (say rules change; check official sources —
do not state specific visa policies as facts), money (guaraní, cards vs cash), safety, best season, getting
around (buses, car hire, transfers), language, tereré etiquette, SIM cards, tipping, drinking water,
Chaco heat, Iguazú day-trip from Ciudad del Este. Tag 5 with `home`.

## 3. Content

Activities (`content/activities/`), 600–900 words each, `facts` (location, duration, price_from "Varies",
currency USD, best_season, difficulty), 4–5 H2s ("Why go", "What to do", "Getting there", "When to go",
"Practical tips"), one `:::tip`, `map_url` to a Google Maps search URL, internal links to 2 other
activities and 1 trip, and a "Book it" paragraph linking to viaje.com.py:
1 `saltos-del-monday-waterfalls` (03) 2 `jesuit-missions-trinidad-and-jesus` (04) 3 `the-paraguayan-chaco` (05)
4 `lake-ypacarai-and-san-bernardino` (06) 5 `encarnacion-river-beaches` (07) 6 `salto-suizo-and-the-ybytyruzu-hills` (08)
7 `cerro-cora-national-park` (09) 8 `paraguay-river-and-the-pantanal` (10) 9 `asuncion-historic-center-walk` (11)
10 `aregua-pottery-town` (12) 11 `laguna-blanca-white-sand-lagoon` (16) 12 `caacupe-basilica` (19)

Trips (`content/trips/`), 500–700 words, day-by-day `itinerary`, `facts` (duration, group_size, departure
"Asunción", price_from "Custom quote"):
1 `paraguay-in-one-week` (01) 2 `chaco-road-trip-3-days` (15) 3 `encarnacion-and-the-missions-weekend` (04)
4 `asuncion-city-break-2-days` (02)

Blog posts (`content/posts/`), 900–1400 words, original, with `:::tip` blocks and a sources/notes line:
1 `best-time-to-visit-paraguay` (17) 2 `is-paraguay-safe-for-travelers` (11) 3 `how-to-get-around-paraguay` (24)
4 `what-to-eat-in-paraguay` (14) 5 `paraguay-10-day-itinerary` (21)

Every description 120–160 chars. Every hero has `alt_en`. Dates: spread across 2026-07 → 2026-09.

## 4. Definition of done (phase 3)

1. `php tools/verify.php thingstodoinparaguay.com` exits 0 with `urls.txt` covering every page above.
2. `grep -ri "lorem\|TODO" sites/thingstodoinparaguay.com/` finds nothing.
3. Rendered checks as in the viaje spec §4.5 (H1, breadcrumbs, JSON-LD, WhatsApp number, footer link to viaje.com.py).
4. `docs/phase-3-report.md` with a content inventory and any facts you were unsure of (flagged, not asserted).
