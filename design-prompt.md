# Design brief: viaje.com.py (paste into Claude Design canvas)

## Business summary (from plan.md, locked decisions §1 and §3.1)

viaje.com.py is a **domestic Paraguay travel agency** — not a booking-engine OTA, not
an outbound-travel brand. Plan §1 locks the positioning explicitly:

> "Positioning: **domestic Paraguay tourism** (the ranking content). The cinematic
> outbound prompt stays parked in `docs/`."

And §3.1: "Rebuild the 13 real URLs from scan §1 with identical paths, port the copy
... The live site that currently ranks is *inbound/domestic* Paraguay tourism."

The site sells five real agency services (not packaged tours — plan §1 decision 5 is
explicit that `/paquetes/` and per-package pages are **not** rebuilt; "the site's own
copy is explicitly anti-catalog"):

- Agencia de viaje (general travel agency services)
- Asistencia personalizada (personalized/concierge assistance)
- Gestión de visas (visa management)
- Traslados (transfers/transport)
- Vacaciones (vacation planning)

Around that core it also publishes destination content: `trip` pages (`/viajes/`),
`activity` pages (`/actividades/`), blog `post`s (`/blog/`, two flagship SEO posts
about must-see Paraguay destinations), and `news` (`/novedades/`). Everything is in
Spanish (es-PY), targeting people planning travel *within* Paraguay, not Paraguayans
travelling abroad.

## Target audience

Travelers and tourists (mostly Paraguayan or Spanish-speaking regional visitors)
researching things to do and see inside Paraguay, who want a real human travel agency
to help plan/organize a trip, get transfers, or handle visa paperwork — not a
self-serve package catalog. Trust and personal service are the sell, not price
comparison.

## Tone / voice

Warm, trustworthy, knowledgeable local expert — not generic corporate travel-agency
stock-photo blandness. Think "a real Paraguayan agency that knows the country," not
"Booking.com clone." Confident and inviting, never salesy/discount-driven — note: the
old WordPress theme had fake "10% Off" discount badges on a homepage city-card
section (plan §6, "Discount badges... Not ported") and that gimmick is deliberately
gone. Do not reintroduce discount-badge/coupon visual language.

## Key content types the design must represent

1. **Service pages** — the 5 services above, each its own page (template: `service`)
2. **Trip pages** — `/viajes/<slug>/`, destination-focused itinerary-style content
3. **Activity pages** — `/actividades/<slug>/`, single experience/activity content
4. **Blog/news** — `/blog/` and `/novedades/` hubs, SEO-driven destination articles
5. **Services hub** (`/servicios/`) with a site-wide FAQ widget
6. **Contact** (`/contacto/`) — the real lead-capture flow

## Deliverable: 3 artboards

Design **three pages** as separate artboards on the canvas:

1. **Homepage** — hero, trust/intro framing (real agency, not a catalog), the 5
   services as a clear list/grid (link out, not "buy now" cards), a taste of
   trip/activity content (cards linking to `/viajes/` and `/actividades/`), a blog
   teaser section, and a prominent contact/WhatsApp CTA. Do NOT include a
   "Destinos Locales" city-cards-with-discount-badges section — that was cut from the
   old WordPress site (flagged in `KNOWN-ISSUES.md` as a decorative/fake-discount
   leftover) and should only reappear in this design if you treat it explicitly as an
   optional idea needing Anton's confirmation, clearly labeled as such.
2. **A trip or activity page** — hero, intro/description, an itinerary or highlights
   section, practical facts (duration, what's included — front matter supports
   `duration`, `price_from`, `included`, `itinerary`), photo gallery, and a
   contact/inquiry CTA at the bottom (this is a lead-gen page, not a checkout page —
   no "add to cart" / price-and-buy UI).
3. **A blog/post page** — hero (note: hub pages currently have no hero-image slot in
   the engine; a single post page does support one), title/meta block, long-form
   article body with headings and an inline callout/tip box (the engine supports
   `:::tip Título ... :::`-style styled callouts — show one as a distinct visual
   component), and related-content links (to a service and to another trip/activity)
   near the end.

## Must-have sections across the set

- Header/nav reflecting real site structure: Servicios, Viajes, Actividades, Blog,
  Nosotros, FAQ, Contacto
- A WhatsApp-first contact CTA (the real lead flow is WhatsApp-first with an
  optional form to `contacto/leads.php` that writes to JSONL, pushes to email and
  optionally VenderCRM) — make WhatsApp the visually primary contact action, a
  standard form secondary
- FAQ presence (site FAQ widget on services hub / homepage footer area)
- Footer with real brand info, nav, and contact details (no fake stats/counters —
  the old "0+" counters were explicitly removed per plan §1 decision 7)

## Style direction

- Vibrant, trustworthy travel/tourism brand feel: warm colors evocative of Paraguay
  (sun, red earth, greenery, riverside — think warm ochres/terracottas paired with a
  deep green or blue, not a cold corporate blue/gray SaaS palette)
- Inviting, photo-forward — large destination imagery should carry the emotional
  weight, text should feel like a knowledgeable friend, not brochure copy
- Explicitly NOT generic corporate: avoid stock "handshake"/suit-and-tie travel-agency
  clichés, avoid dense enterprise-SaaS layouts, avoid the discount-badge/coupon look
- Typography: something with warmth/character for headings paired with a clean,
  legible body face (the real build already uses a Fraunces display font — feel free
  to reflect a similar serif-display-plus-clean-sans pairing)

## Constraints for the design (so it stays buildable)

- **Mobile-first.** This is a real production PHP flat-file site — design mobile
  layouts as first-class, not an afterthought scaled down from desktop.
- **No heavy JS-framework interactions.** The engine is plain PHP + vanilla JS/CSS,
  no React/Vue/build step. Keep interaction patterns simple: standard nav, maybe a
  lightweight image carousel/gallery and an accordion for FAQ, nothing that implies
  a SPA, complex client-side state, or a scroll-hijacking/sticky-canvas experience.
  (A cinematic scroll-driven homepage concept exists in `docs/` as a *parked, later*
  experiment — it is explicitly NOT the current build target; design the standard
  crawlable homepage instead.)
- **Real photography is not yet in place.** All current imagery is placeholder,
  pointing at temporary remote CDN URLs pending localization. Specify photo *style*
  guidance (subject matter, framing, color grading, aspect ratios per slot) rather
  than depending on specific final images — treat every photo area as a labeled
  placeholder slot with a one-line art-direction note (e.g. "wide shot, golden-hour,
  river/estancia landscape, warm grade").
- Respect the real front-matter/data model where it affects layout: services have no
  price/buy UI (anti-catalog); trips/activities can show `duration` and `price_from`
  as informational facts, not a purchase flow; posts support a styled tip-callout
  component; hub pages (blog, servicios) currently have no hero-image slot in the
  engine, so don't design a hub hero as if it will render — if you want one, flag it
  as a "needs engine support" callout.
