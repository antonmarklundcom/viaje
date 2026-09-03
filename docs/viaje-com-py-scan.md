# Viaje.com.py — Full Content & SEO Extraction Scan

Scanned live via browser (page source / DOM) on 2026-09-02. Source: XML sitemap index at `https://viaje.com.py/wp-sitemap.xml` → 4 sub-sitemaps (posts, pages, category taxonomy, users). All 19 indexed URLs were visited directly; none guessed or fabricated. Note: sitemap "Last Modified" timestamps (e.g. `2026-04-17`) are the site server's own clock/timezone and don't necessarily reflect real edit dates — informational only.

Site owner: Anton Marklund (Swedish founder living in Paraguay — confirmed via `/nosotros/` bio). This is being rebuilt as a static HTML/PHP site on Hostinger, replacing this WordPress+Elementor install, without losing current Google rankings.

---

## 1. URL Inventory

| Path | Type | Status | Title tag (raw, as rendered) |
|---|---|---|---|
| `/` | Home | **REAL** | *(missing — browser tab shows raw "viaje.com.py")* |
| `/agencia-de-viaje/` | Service page | **REAL** | Agencia de Viaje – viaje.com.py |
| `/asistencia-personalizada/` | Service page | **REAL** | Asistencia Personalizada – viaje.com.py |
| `/gestion-de-visas/` | Service page | **REAL** | Gestión de Visas – viaje.com.py |
| `/traslados/` | Service page | **REAL** | Traslados – viaje.com.py |
| `/vacaciones/` | Service page | **REAL** | Vacaciones – viaje.com.py |
| `/servicios/` | Services hub | **REAL** | Servicios – viaje.com.py |
| `/paquetes/` | Packages hub | **PLACEHOLDER** (100% theme demo) | Paquetes – viaje.com.py |
| `/paquete-individual/` | Package detail | **PLACEHOLDER** (100% theme demo) | Paquete individual – viaje.com.py |
| `/servicio-unico/` | Service detail | **PLACEHOLDER** (100% theme demo) | servicio único – viaje.com.py |
| `/nosotros/` | About | **REAL** | Nosotros – viaje.com.py |
| `/faq/` | FAQ | **REAL** | FAQ – viaje.com.py |
| `/contacto/` | Contact | **REAL** | Contacto – viaje.com.py |
| `/blog/` | Blog index | **REAL** (lists 2 real + 1 placeholder post) | Blog – viaje.com.py |
| `/elementor-9/` | Orphan draft page | **PLACEHOLDER / EMPTY** — no title, no body | Elementor #9 – viaje.com.py |
| `/paraguay-destinos-imprescindibles-2026/` | Blog post | **REAL** (flagship, long-form) | Paraguay Profundo: 10 Destinos Imprescindibles que Redefinen el Turismo Interno este 2026 – viaje.com.py |
| `/destinos-imperdibles-2026/` | Blog post | **REAL** | Destinos Imperdibles en Paraguay para este 2026: ¡Jaha! – viaje.com.py |
| `/hello-world/` | Blog post | **PLACEHOLDER** (WP default first post) | Hello world! – viaje.com.py |
| `/category/uncategorized/` | Category archive | **PLACEHOLDER / DUPLICATE** of `/blog/` (default WP taxonomy, never customized) | Uncategorized – viaje.com.py |

**19/19 sitemap URLs visited. No 404s encountered. No JSON-LD structured data found on any page.**

---

## 2. Site-wide observations

- **No `<title>` tag** on the homepage (browser shows the raw domain). Every other page does have an auto-generated `Page Name – viaje.com.py` title, but these are Yoast/theme defaults, not hand-written SEO titles — none differ in structure or include target keywords.
- **No meta description on any page** — confirmed absent (`null`) on every single URL checked, homepage through blog posts.
- **Zero JSON-LD / schema.org structured data** anywhere on the site (no LocalBusiness, Organization, Article, or BreadcrumbList schema).
- **No canonical tag on `/blog/`** (only page found missing one — everywhere else canonical = self-URL, correctly).
- **Theme demo brand leftovers ("TourDen")** appear in the footer of **every single page**: tagline "Tu guía definitiva para descubrir el corazón de Sudamérica" under an untouched "TourDen" heading, plus a **"Tour Packages" footer menu of 6 fake links** (Maldives Luxury Escape, Thailand Adventure Tour, Dubai Holiday Package, India Golden Triangle Tour, Nepal Nature Discovery, River Rafting Thrill Tour) that lead nowhere relevant. **Do not migrate this footer block as-is — replace with real Viaje branding/links.**
- **Email address inconsistency across the whole site**: contact sections mid-page use `hola@viaje.com.py`; the footer (every page) and the `/contacto/` page's own "Correo Electrónico" field both show **`hola@viaje.com` (missing `.py`)** — almost certainly a typo/bug carried site-wide. Needs a decision on the correct address before rebuild.
- **Phone/WhatsApp**: `+595 995 628 862` — consistent everywhere.
- **Address**: Edificio Skytower, Asunción, Paraguay — consistent everywhere.
- **Broken animated counters**: "Experiencia Viajando/de Viaje" and "Destinos Cubiertos" both render as **"0+"** on homepage and `/nosotros/` — the counter widget has no real number wired in (or requires a scroll-trigger not captured by static extraction — flag for a manual live check with real interaction before assuming it's simply unset).
- **Two separate, non-overlapping FAQ accordions exist** (both fully extracted below, section 4):
  - **Site-wide FAQ widget** (embedded on `/`, `/servicios/`, `/nosotros/`) — 6 Q&As, travel-logistics focused.
  - **Standalone `/faq/` page** — 10 different Q&As, more general travel-in-Paraguay focused.
  - These do not duplicate each other but do overlap in topic (both cover road safety, 4x4 need, seasons) — worth consolidating into one FAQ set in the rebuild.
- **Testimonials carousel**: only one testimonial slide is present in the DOM/static extraction — "Rodrigo B." (Ybytyruzú trip). No others found; likely genuinely just one testimonial live, not a hidden carousel — but no `type="testimonial"` schema either, so it's plain text, easy to port.
- **Blog author bylines are theme-demo leftovers**: post sidebar "About Direction" widget shows **"Harbert Spin"** with Lorem ipsum bio on all 3 blog posts — never replaced with a real author identity. One real post *does* have an inline byline instead ("Por: Yanina / Equipo Viaje.com.py") — inconsistent authorship handling.
- **`/blog/` and `/category/uncategorized/` are duplicate-content pages** — both list the exact same 3 posts (2 real + hello-world). Since no category taxonomy was ever set up, `/category/uncategorized/` is pure WordPress default cruft and should NOT be recreated in the new site (would just be a duplicate URL with no unique purpose).
- **Header nav** (identical on every page): Inicio · Paquetes · Servicios · Blog · FAQ · Nosotros · Contacto
- **Footer structure** (identical on every page): brand blurb (currently wrong "TourDen" text) → social ("Seguinos") → "Páginas Principales" nav list → "Tour Packages" fake list → Contact block (phone/email/address) → copyright line "Viaje PY © Copyright 2026 - Reservados todos los derechos."
- **Contact page has no working `<form>` element** — only a WordPress search `<form>` was detected in the DOM (`action="/" method="get" field name="s"`). The "Envíanos Un Mensaje" heading has no actual form beneath it captured in static DOM — likely an unconfigured/removed Elementor form widget, or one that loads via a plugin not firing in a static pass. **Needs manual verification** (open in a live browser, check network tab) before assuming there's no lead-capture mechanism at all — this is the single biggest functional gap for a rebuild, since there's currently no visible way for a site visitor to submit a message other than calling/WhatsApp/email directly.
- Homepage has an embedded newsletter-style line ("Suscribite y recibí las mejores rutas...") immediately followed by **"No content is added yet."** — this confirms an Elementor widget (likely a Mailchimp/newsletter form block) was placed but never configured. Same "No content is added yet." placeholder also appears right under the hero CTA area on the homepage.

---

## 3. Per-page content (REAL pages only, verbatim)

### 3.1 `/` — Homepage

- **Title tag**: *(none — bug, must be fixed in rebuild)*
- **Meta description**: none
- **H1**: none detected (hero heading is styled text, not wrapped in an actual `<h1>` — check hero markup carefully in rebuild; found only H2/H3 in the heading scan)
- **Canonical**: `https://viaje.com.py/`

**Body copy (verbatim, in page order):**

> TU GUÍA DEFINITIVA DE TURISMO EN PARAGUAY
> **Descubrí El Paraguay Que Pocos Conocen**
> Encontrá los mejores destinos, rutas gastronómicas y rincones ocultos de nuestra tierra.
> [CTA: Envíanos Un Mensaje] · +595 995 628 862 · [CTA: Solicita Un Presupuesto Gratuito]

> SOBRE NOSOTROS — **¿Por Qué Hacemos Lo Que Hacemos?**
> En Paraguay, decimos que el camino es tan importante como el destino. Viaje.com.py nació de una curiosidad incansable por redescubrir lo que tenemos cerca: el sonido de un salto escondido, el sabor de una chipa recién salida del horno de barro y la hospitalidad que solo se encuentra en nuestras posadas.
>
> No somos sólo una web de turismo; somos el mapa de los que eligen quedarse un poco más para ver el atardecer, de los que preguntan "¿cómo se llega?" y de los que saben que Paraguay no se visita, Paraguay se siente.

> UNAS PALABRAS DEL EQUIPO
> "Mi meta con este proyecto es que viajar por Paraguay sea algo accesible y auténtico para todos. No quiero que solo veas fotos lindas, sino que tengas la información real para llegar a esos lugares y vivirlos por tu cuenta.
>
> Al final del día, lo que buscamos es que cada viaje te deje algo más que una imagen: que te deje una buena historia que contar."
> — **Anton Marklund**, Director de Viaje.com.py

> **Nuestra Visión**: Ser el punto de encuentro digital donde cada rincón del país, desde el Chaco hasta el Paraná, sea accesible, valorado y amado por todos los paraguayos y el mundo.
>
> **Nuestra Misión**: Democratizar el acceso a la aventura local. Buscamos inspirarte a viajar de forma auténtica, apoyando a las comunidades locales y revelando los secretos que los mapas convencionales suelen ignorar.

> Experiencia Viajando: **0+** *(broken counter)* · Destinos Cubiertos: **0+** *(broken counter)*

> Sumate A La Ruta — No queremos que solo leas sobre estos lugares. Queremos que los vivas, que saques tus propias conclusiones (y tus mejores fotos) y que te enamores de tu tierra una y otra vez.
> [Newsletter block: "Hacemos Que Cada Viaje Por Paraguay Sea Inolvidable. Suscribite y recibí las mejores rutas y consejos directamente en tu correo." → **"No content is added yet."** (unconfigured widget)]
> Llamanos: +595 995 628 862 · Email: hola@viaje.com.py

**DESTINOS LOCALES — "Ciudades Más Visitadas"**
Explorá los centros urbanos más vibrantes de Paraguay. Desde la historia colonial de la capital hasta la energía comercial y natural del este.

| Ciudad | Discount badge | Blurb |
|---|---|---|
| Asunción | 10% Off | Cultura, historia y gastronomía. |
| Ciudad del Este | 12% Off | Compras, energía y naturaleza. |
| Encarnación | 15% Off | Playas, costanera y carnaval. |
| San Bernardino | 10% Off | Lago Ypacaraí y vida nocturna. |
| Villarrica | 10% Off | Cultura, parques y el Ybytyruzú. |
| Concepción | 12% Off | Historia fluvial y acceso al norte. |
| Luque | 15% Off | Orfebrería, artesanía y cercanía. |
| Pedro Juan Caballero | 10% Off | Compras y ecoturismo en el Amambay. |

*(Note: the "% Off" discount badges appear to be a decorative theme-card pattern reused from the original tour-package template — there's no visible discount mechanic anywhere else on the site. Verify whether these numbers mean anything real or are leftover styling before porting.)*

**SERVICIOS — "Todo Lo Que Necesitás Para Tu Aventura"**
Nos encargamos de la logística para que vos solo te preocupes de disfrutar el paisaje. (años de experiencia: **1+**)
Cards: Agencia de Viaje · Asistencia Personalizada · Gestión de Visas · Traslados Privados · Vacaciones *(each links to its own service page — see §3.2–3.6)*

**¿POR QUÉ ELEGIRNOS? — "Tu Seguridad Es Nuestra Prioridad En El Camino"**
No solo te damos un destino; te acompañamos en todo el trayecto. En Viaje.com.py nos aseguramos de que cada kilómetro recorrido sea seguro, auténtico y libre de preocupaciones.

4-step process:
1. Conocimiento Local Real
2. Flexibilidad a Tu Medida
3. Soporte en Todo Momento
4. Experiencias Sin Fricciones

**ALIANZAS — "Formamos Red Con Los Mejores Del País"**
Creemos en el trabajo colaborativo. Por eso, nos aliamos con las posadas más acogedoras, los guías más experimentados y las empresas de logística más seguras de Paraguay para garantizar que cada parte de tu viaje sea de primer nivel.

**GALERÍA — "Postales De Nuestra Tierra"**
Category labels shown (photo gallery grid, no captions found): Rutas del Agua · Escapadas Urbanas · Sabores Locales · Historia Viva · Horizontes del Chaco · Naturaleza Pura

**NUESTROS GUÍAS — "Conocé A Nuestros Expertos Locales"**
Detrás de cada recomendación y cada ruta en esta web, hay un equipo de profesionales que comparte una misma pasión: recorrer Paraguay. No somos solo planificadores de viajes; somos viajeros, fotógrafos y especialistas en logística que conocen de primera mano los caminos, las distancias y los secretos de cada destino.
Team members named (no bios visible in flat text — likely bios exist per-card but weren't captured; check individually if profile pages/detail exist): Marcos Benítez · Lucía Ferreira · Andrés Villalba · Raquel Galeano

**FAQ block** (site-wide widget — see §4.1 for full extracted Q&As) + **Testimonials**: "Rodrigo B." — *"Me animé a conocer el Ybytyruzú con la guía de Viaje.com.py y fue la mejor decisión. La info sobre los senderos es súper precisa y me sentí seguro en todo momento. ¡Un Paraguay que no conocía!"*

**CONTACTANOS — "Planificá Tu Próxima Aventura Con Nosotros"**
¿Tenés una idea en mente o necesitás ayuda para elegir tu próximo destino? Estamos acá para asesorarte. Queremos que tu viaje por Paraguay sea exactamente como lo soñaste: auténtico, tranquilo y sin vueltas. Escribinos y armemos tu ruta juntos.
[Ver Video] · [Envianos un mensaje!] *(no visible form — see gap noted in §2)*

**NUESTRO BLOG — "Historias, Rutas Y Secretos De Nuestro Viaje"**
Teasers for: Hello world! · Destinos Imperdibles en Paraguay para este 2026: ¡Jaha! · Paraguay Profundo: 10 Destinos Imprescindibles...

**Images referenced (content-relevant, not decorative theme assets):**
`asu-viaje.jpg`, `turismo-autor-viaje.jpg`, `viaje-paraguay.jpg`, service-icon PNGs (`agencia-viaje`, `asistencia-personalizada-viaje`, `gestion-visa-viaje`, `traslado-privado-viaje`, `vacaciones-viaje`), `seguridad-viaje-paraguay.png`, gallery images `image-13` through `image-17` (no alt text — all blank `alt=""`), plus two Higgsfield-generated images (`hf_20260411_...png`) with blank alt.
**Gap: virtually every image on the homepage has an empty `alt` attribute** — a real accessibility/SEO loss to fix in the rebuild.

---

### 3.2 `/agencia-de-viaje/` — Agencia de Viaje (service page)

- **Title**: Agencia de Viaje – viaje.com.py
- **Meta description**: none
- **Canonical**: `https://viaje.com.py/agencia-de-viaje/`
- **H2 (functions as page H1)**: Agencia de Viaje
- **Breadcrumb**: Inicio / Agencia de Viaje

**Intro/hero copy:**
> Dejá atrás los paquetes rígidos de catálogo. Nos especializamos en diseñar itinerarios a medida que conectan con la esencia del país, desde los saltos más escondidos hasta expediciones privadas en el Chaco. Combinamos logística inteligente con acceso exclusivo para transformar coordenadas en el mapa en experiencias memorables y fluidas.

**H2: Diseño De Itinerarios Y Rutas Curadas**
> No creemos en los paquetes rígidos de catálogo que solo rozan la superficie. Como agencia, nuestro trabajo es profundizar: entender qué tipo de viajero sos y diseñar una ruta que te conecte con el Paraguay real. Nos especializamos en la logística de lo complejo, transformando coordenadas en el mapa en experiencias fluidas y memorables.
>
> Desde expediciones fotográficas en el Chaco hasta retiros de desconexión en el Ybytyruzú, cada viaje que planificamos pasa por un proceso de curaduría riguroso. Seleccionamos cada parada, cada guía local y cada estancia basándonos en estándares de calidad, autenticidad y seguridad, asegurando que cada kilómetro recorrido valga la pena.

**Servicios Incluídos** (bullet list): Diseño de Hoja de Ruta · Selección de Alojamiento · Coordinación de Guías · Reserva de Experiencias · Logística de Suministros · Optimización de Tiempos

**H3: Acceso A Lo Inexplorado**
> Lo que nos diferencia es nuestra red de contactos en los 17 departamentos del país. Muchas de las joyas naturales de Paraguay no tienen un sitio web ni están en las aplicaciones de reserva tradicionales. Nosotros abrimos esas puertas para vos: desde el acceso a reservas privadas y comunidades indígenas hasta la coordinación con estancias históricas que solo reciben visitas bajo recomendación previa. Es la diferencia entre ser un turista y ser un invitado.

**H3: Logística Inteligente Y Sin Fricciones**
> Planificar un viaje por el interior requiere considerar variables que a menudo se pasan por alto: tiempos de desplazamiento reales, disponibilidad de servicios en zonas remotas y estados estacionales de los caminos. Nuestra gestión integral se encarga de que la transición entre destinos sea invisible para vos. Manejamos las reservas, coordinamos los tiempos y verificamos cada detalle técnico para que tu única preocupación sea estar presente en el momento.

**CTA/contact block on page**: +595 995 628 862 · hola@viaje.com.py · Asunción, Paraguay

**Images**: `agencia-viaje-paraguay.png`, `agencia-viaje-paraguay-planificacion-asuncion.png`, `viaje-agencia-paraguay.png`, `asistencia-viaje1.png` (all `alt=""`)

---

### 3.3 `/asistencia-personalizada/` — Asistencia Personalizada (service page)

- **Title**: Asistencia Personalizada – viaje.com.py
- **Meta description**: none
- **Canonical**: `https://viaje.com.py/asistencia-personalizada/`
- **H2 (page H1)**: Asistencia Personalizada
- **Breadcrumb**: Inicio / Asistencia Personalizada

**Intro/hero copy:**
> No creemos en itinerarios de catálogo. Nos especializamos en diseñar rutas que conectan con el Paraguay auténtico, desde los saltos más escondidos del Guairá hasta la inmensidad del Chaco. Analizamos tus intereses para armar una logística que optimice tu tiempo y te permita descubrir lugares que no aparecen en los buscadores convencionales.

**H2: Asistencia Personalizada Y Soporte En Ruta**
> Viajar por Paraguay es una experiencia gratificante, pero la logística en tiempo real puede presentar desafíos. Nuestro servicio de asistencia no es un simple centro de atención telefónica; es un vínculo directo con expertos locales que conocen el estado de las rutas, los cambios climáticos repentinos y los mejores contactos en cada departamento.
>
> Nos encargamos de que nunca te sientas solo en el camino. Ya sea que necesites recalcular una ruta por lluvia en el Chaco o busques una recomendación gastronómica de último minuto en un pueblo remoto, estamos a un mensaje de distancia para asegurar que tu experiencia sea fluida y segura.

**Servicios Incluídos**: Soporte vía WhatsApp 24/7 · Actualización de estado de rutas · Recomendaciones locales curadas · Asesoramiento en logística climática · Contacto directo con proveedores locales · Gestión de reservas de emergencia

**H3: El Valor De La Respuesta Inmediata**
> Entendemos que en la ruta, el tiempo es el recurso más valioso. Por eso, nuestra asistencia personalizada elimina la fricción de la incertidumbre. Si un tramo de tierra se vuelve intransitable por una tormenta repentina o si una reserva local necesita una confirmación urgente, nuestro equipo actúa como tu centro de operaciones. No perdés tiempo buscando soluciones en Google con señal inestable; nosotros ya tenemos el contacto directo y la solución lista para vos.

**H3: Un Puente Entre Vos Y La Cultura Local**
> Más allá de la logística, este servicio funciona como un concierge cultural. Paraguay se mueve por contactos y relaciones personales, y nosotros ponemos nuestra red a tu disposición. Te ayudamos a interactuar con comunidades locales, encontrar artesanos específicos o acceder a estancias que no están abiertas al público general. Es la tranquilidad de viajar con un aliado que conoce los códigos, los horarios y las personas que hacen que un viaje pase de ser bueno a ser inolvidable.

**Images**: `viaje-py.png`, `asistencia-viaje.png`, `asistencia-viaje1.png`, `asistencia-viaje2.png` (all `alt=""`)

---

### 3.4 `/gestion-de-visas/` — Gestión de Visas (service page)

- **Title**: Gestión de Visas – viaje.com.py
- **Meta description**: none
- **Canonical**: `https://viaje.com.py/gestion-de-visas/`
- **H2 (page H1)**: Gestión de Visas
- **Breadcrumb**: Inicio / Gestión de Visas

**Intro/hero copy:**
> Simplificamos los procesos burocráticos para tu ingreso y permanencia en el país. Ofrecemos una gestión experta en trámites migratorios para turistas, inversores y extranjeros que buscan establecerse en Paraguay, garantizando que cada documento cumpla con las normativas vigentes de forma ágil y segura.

**H2: Gestión Documental Y Soporte Migratorio**
> Entender las leyes migratorias locales puede ser un desafío. Nuestro servicio de gestión de visas se encarga de todo el proceso administrativo, desde la revisión inicial de documentos hasta la presentación ante las autoridades competentes. Actuamos como tu representante local, asegurando que tu trámite avance sin errores que puedan causar demoras innecesarias.
>
> No solo gestionamos papeles; brindamos una estrategia migratoria adaptada a tus objetivos. Ya sea que necesites una visa de turista, una residencia temporal o permanente, o asesoría para la radicación por inversión, nuestro equipo te acompaña paso a paso. Nos mantenemos actualizados con los cambios constantes en las normativas para brindarte una solución legalmente sólida y eficiente.

**Servicios Incluídos**: Visas de Turista y Negocios · Residencia Temporal y Permanente · Radicación por Inversión (SUACE) · Legalización y Apostillado · Renovación de Documentación · Asesoría Legal Migratoria

**H3: Especialistas En Radicación E Inversión**
> Paraguay ofrece excelentes oportunidades para extranjeros, pero la clave está en una correcta gestión documental desde el origen. Asesoramos en la obtención de certificados, legalizaciones y apostillados necesarios para que tu expediente sea impecable. Nuestra experiencia nos permite anticiparnos a los requerimientos de la Dirección General de Migraciones, optimizando los tiempos de respuesta.

**H3: Acompañamiento Y Seguimiento Personalizado**
> La gestión no termina con la entrega de documentos. Realizamos un seguimiento activo de cada expediente y te mantenemos informado sobre el estado de tu trámite en tiempo real. Para trámites presenciales, coordinamos toda la logística de citas y acompañamiento, asegurando que tu experiencia con las instituciones públicas sea rápida, profesional y libre de fricciones.

**Images**: `apostilla-gestion-visa-paraguay.png`, `pasaportes-viaje-paraguay.png`, `gestion-visa-viaje-1024x687.png`, `asistencia-viaje2.png` (all `alt=""`)

---

### 3.5 `/traslados/` — Traslados Privados (service page)

- **Title**: Traslados – viaje.com.py
- **Meta description**: none
- **Canonical**: `https://viaje.com.py/traslados/`
- **H2 (page H1)**: Traslados Privados
- **Breadcrumb**: Inicio / Traslados Privados

**Intro/hero copy:**
> Ofrecemos un servicio de transporte dedicado que combina la seguridad de una conducción profesional con el confort de una flota de alta gama. Ya sea para traslados ejecutivos en la ciudad o expediciones a zonas remotas del interior, garantizamos una logística de movilidad puntual, discreta y eficiente.

**H2: Logística De Transporte De Alta Gama**
> La movilidad en Paraguay requiere experiencia y vehículos adecuados. Nuestro servicio de traslados privados está diseñado para quienes priorizan el tiempo y la seguridad. Contamos con una flota moderna y conductores profesionales capacitados en rutas nacionales, asegurando que cada trayecto, desde el aeropuerto hasta el interior del país, sea una experiencia de confort absoluto.
>
> Nos encargamos de la coordinación integral de tus desplazamientos. Monitoreamos los tiempos de vuelo, el estado de las rutas y las condiciones climáticas para ajustar la logística en tiempo real. Brindamos una solución de transporte que se adapta a tu agenda, ofreciendo flexibilidad y un respaldo operativo constante para que llegues a destino sin complicaciones.

**Servicios Incluídos**: Traslados Aeropuerto-Hotel · Disponibilidad por Hora · Logística para el Interior · Transporte Ejecutivo · Monitoreo en Tiempo Real · Servicios Interurbanos

**H3: Flota Versátil Y Equipamiento Premium**
> Seleccionamos el vehículo ideal según el tipo de trayecto y terreno. Desde sedanes de lujo para movilidad urbana hasta unidades 4×4 de alto rendimiento preparadas para los caminos desafiantes del Chaco o la zona de los Saltos. Todas nuestras unidades cumplen con rigurosos estándares de mantenimiento y están equipadas para brindar una experiencia de viaje superior, incluyendo conectividad y climatización controlada.

**H3: Conductores Profesionales Y Protocolo**
> La seguridad vial es nuestra prioridad. Nuestros conductores no solo conocen las rutas palmo a palmo, sino que operan bajo estrictos protocolos de puntualidad y discreción. Recibimos a nuestros clientes con asistencia personalizada en puntos de llegada y garantizamos un trato profesional que entiende las necesidades del viajero ejecutivo y el turista de lujo.

**Images**: `traslado-asuncion-viaje.png`, `asistencia-viaje.png`, `traslado-viaje.png`, `traslado-privado-viaje-1024x687.png` (all `alt=""`)

---

### 3.6 `/vacaciones/` — Vacaciones (service page)

- **Title**: Vacaciones – viaje.com.py
- **Meta description**: none
- **Canonical**: `https://viaje.com.py/vacaciones/`
- **H2 (page H1)**: Vacaciones
- **Breadcrumb**: Inicio / Vacaciones

**Intro/hero copy:**
> Diseñamos y gestionamos paquetes vacacionales donde la logística no es una preocupación para vos. Seleccionamos destinos exclusivos y estancias de primer nivel en todo el país, asegurando una coordinación impecable desde tu salida hasta tu retorno. Disfrutá de un Paraguay auténtico con el respaldo de una gestión profesional.

**H2: Viajes De Placer Con Logística De Autor**
> Nos encargamos de que tus días de descanso sean exactamente eso: descanso. Diseñamos paquetes vacacionales integrales donde la coordinación logística es nuestra prioridad. Seleccionamos destinos y establecimientos de primer nivel en todo Paraguay, asegurando que cada detalle operativo esté resuelto antes de que llegues a tu destino.
>
> Desde la selección de estancias privadas con servicios exclusivos hasta la reserva de actividades de lujo en entornos naturales, gestionamos toda la cadena de servicios. Nuestro objetivo es que disfrutes de una experiencia fluida, sin imprevistos y con el respaldo constante de una agencia que conoce los estándares de exigencia del viajero actual.

**Servicios Incluídos**: Paquetes All-Inclusive Personalizados · Reserva de Alojamiento Premium · Actividades Exclusivas · Transporte Dedicado · Soporte de Conserjería · Seguros y Cobertura de Viaje

**H3: Selección De Destinos Y Estancias**
> No trabajamos con opciones genéricas. Evaluamos personalmente cada hotel, posada y estancia para garantizar que cumplen con requisitos de privacidad, confort y seguridad. Ya sea una escapada de fin de semana en San Bernardino o una expedición de lujo al Pantanal Paraguayo, tu estadía está respaldada por una curaduría que prioriza la calidad del servicio y la exclusividad del entorno.

**H3: Gestión De Itinerarios Recreativos**
> Unas vacaciones exitosas dependen de una agenda bien ejecutada. Coordinamos actividades exclusivas como navegación privada, safaris fotográficos o accesos a reservas restringidas, asegurando que los proveedores locales cumplan con los horarios y la calidad pactada. Nosotros manejamos los tiempos y la burocracia técnica, permitiéndote aprovechar cada minuto de tu tiempo libre.

**Images**: `viaje-py.png`, `asistencia-viaje.png`, `asistencia-viaje1.png`, `asistencia-viaje2.png` (all `alt=""`) — *(note: this page reuses the same generic image files as `/asistencia-personalizada/` and `/vacaciones/` — no unique vacation-specific photography currently)*

---

### 3.7 `/servicios/` — Servicios (hub page)

- **Title**: Servicios – viaje.com.py
- **Meta description**: none
- **Canonical**: `https://viaje.com.py/servicios/`
- **H2 (page H1)**: Nuestros Servicios
- **Breadcrumb**: Inicio / Servicios

**Intro/hero copy:**
> Nos encargamos de la complejidad operativa para que tu única responsabilidad sea el destino. Desde trámites migratorios hasta logística en terrenos remotos, ofrecemos un respaldo sólido basado en el conocimiento real del territorio paraguayo.

**H2: Todo Lo Que Necesitás Para Tu Aventura**
> Nos encargamos de la logística para que vos solo te preocupes de disfrutar el paisaje.
(Cards linking to all 5 service pages: Agencia de Viaje, Asistencia Personalizada, Gestión de Visas, Traslados Privados, Vacaciones)

Followed by the site-wide FAQ widget (6 Q&As — see §4.1) and testimonial CTA. No unique body copy beyond the hero + service cards + shared FAQ block.

---

### 3.8 `/nosotros/` — Nosotros (About)

- **Title**: Nosotros – viaje.com.py
- **Meta description**: none
- **Canonical**: `https://viaje.com.py/nosotros/`
- **H2 (page H1)**: Nosotros
- **Breadcrumb**: Inicio / Nosotros

**Intro/hero copy:**
> Somos la brújula para los que buscan lo auténtico. Desde los saltos más escondidos hasta la mesa más tradicional, estamos acá para que redescubras tu tierra.

**EL PROPÓSITO — Tu País, Como Nunca Lo Viste**
> Viaje.com.py nace de la mirada curiosa que no se conforma con lo obvio. Creemos que Paraguay tiene una riqueza invisible para el que tiene prisa, pero reveladora para el que se detiene a observar. Nuestra misión es documentar y compartir esos rincones que no aparecen en las guías convencionales, impulsando un turismo responsable que celebre nuestra cultura, nuestra gente y nuestra tierra virgen.

**Mensaje Del Fundador**
> "Hace dos años dejé los paisajes nórdicos de Suecia para buscar algo diferente, pero nunca imaginé que encontraría un hogar. Paraguay me recibió con una calidez que no conocía; no solo por su clima, sino por su gente. Me fascinó esa mezcla única de calma y energía, de tradición guaraní y horizontes infinitos.
>
> Como alguien que eligió este país, mi visión es devolverle a esta tierra un poco de lo mucho que me dio. Viaje.com.py es mi tributo a Paraguay: un espacio creado para que vos, que vivís acá, o vos, que venís de lejos, redescubras la magia de lo auténtico. Mi compromiso es mostrarte el país a través de ojos que no dejan de sorprenderse, recordándote que la verdadera aventura no está en los kilómetros, sino en la profundidad de lo que descubrimos en el camino."
> — **Anton Marklund**, Fundador de Viaje

**Nuestra Misión**: "Inspirar a paraguayos y viajeros del mundo a redescubrir Paraguay a través de contenido auténtico, práctico y visualmente honesto. Buscamos democratizar el acceso a la aventura local, apoyando a las comunidades y emprendimientos que mantienen viva nuestra identidad, transformando un simple viaje en una conexión profunda con nuestra tierra."

**Nuestra Visión**: "Convertirnos en la plataforma de referencia del turismo interno en Paraguay para el 2028, siendo reconocidos por nuestra capacidad de revelar lo extraordinario en lo cotidiano. Soñamos con un país donde cada salto, cada cerro y cada pueblo histórico sea valorado, protegido y visitado con orgullo, posicionando a Paraguay como el destino de naturaleza y cultura más genuino de Sudamérica."

Experiencia De Viaje: **0+** *(broken counter, same issue as homepage)* · Destinos Cubiertos: **0+** *(broken counter)*

**Nuestros Valores**:
- **Autenticidad** — Si está en nuestra web, es porque estuvimos ahí. No recomendamos lugares que no hayamos sentido.
- **Sostenibilidad** — Promovemos un turismo que cuida el agua, respeta el monte y valora el silencio.
- **Comunidad** — Creemos que el turismo es el motor que debe impulsar a las familias rurales y a las pequeñas posadas locales.

**NUESTROS GUÍAS — Conocé A Nuestros Expertos Locales**
> Detrás de cada recomendación y cada ruta en esta web, hay un equipo de profesionales que comparte una misma pasión: recorrer Paraguay. No somos solo planificadores de viajes; somos viajeros, fotógrafos y especialistas en logística que conocen de primera mano los caminos, las distancias y los secretos de cada destino.

Team: Marcos Benítez · Lucía Ferreira · Andrés Villalba · Raquel Galeano *(names only — no individual bios/roles captured in flat text; check if per-card bio text exists behind hover/expand in a live pass)*

Followed by the site-wide FAQ widget (6 Q&As, same as `/servicios/`) and the same "Rodrigo B." testimonial.

---

### 3.9 `/faq/` — FAQ page

- **Title**: FAQ – viaje.com.py
- **Meta description**: none
- **Canonical**: `https://viaje.com.py/faq/`
- **H1/hero**: (standard hero pattern — title "FAQ", breadcrumb Inicio / FAQ; specific hero paragraph not separately captured but follows the same template as other pages)

**Full accordion content (10 Q&As, extracted from DOM — this is a DIFFERENT, non-overlapping set from the homepage/servicios/nosotros widget):**

1. **¿Es seguro viajar en auto por el interior?**
   Sí, las rutas principales están en buen estado. Recomendamos siempre viajar de día para disfrutar del paisaje y evitar baches imprevistos en caminos secundarios.
2. **¿Necesito una 4x4 para recorrer Paraguay?**
   Para los destinos principales (Itapúa, Guairá, Paraguarí) no es necesario. Sin embargo, para adentrarse en el Chaco profundo o ciertos saltos, un vehículo alto es ideal.
3. **¿Hay transporte público a los destinos turísticos?**
   Sí, Paraguay tiene una red de buses muy amplia. Desde la Terminal de Asunción salen buses a casi todos los rincones del país.
4. **¿Cómo están las rutas en el Chaco?**
   La Transchaco ha tenido muchas mejoras, pero siempre es bueno consultar nuestro reporte de estado de rutas antes de salir.
5. **¿Cuál es la mejor época para viajar?**
   De marzo a junio y de septiembre a noviembre. El calor no es tan extremo y los paisajes están bien verdes.
6. **¿Es difícil subir los cerros de Paraguay?**
   La mayoría (como el Cerro Acahay o el Tres Kandu) tienen dificultad media alta. Se recomienda calzado con buen agarre y mucha agua.
7. **¿Hay buena señal de celular en las reservas naturales?**
   En los picos de los cerros suele haber señal, pero en los valles o selvas profundas la conexión es limitada. ¡Ideal para desconectarse!
8. **¿Paraguay es apto para viajar con niños?**
   ¡Totalmente! Es un país muy familiar y la mayoría de los destinos tienen actividades para los más chicos.
9. **¿Aceptan tarjetas de crédito en el interior?**
   En ciudades grandes sí, pero en pueblos pequeños o posadas rurales, el efectivo (Guaraníes) es el rey.
10. **¿Qué es una "Posada Turística"?**
    Son casas familiares adaptadas para recibir turistas. Es la mejor forma de vivir la hospitalidad paraguaya y comer comida casera.

---

### 3.10 `/contacto/` — Contacto

- **Title**: Contacto – viaje.com.py
- **Meta description**: none
- **Canonical**: `https://viaje.com.py/contacto/`
- **H2 (page H1)**: Contactanos
- **Breadcrumb**: Inicio / Contacto

**Intro/hero copy:**
> Ya sea que necesites asesoría migratoria, traslados de alta gama o el diseño integral de tus vacaciones, nuestro equipo está listo para brindarte una respuesta ágil y profesional. Tu tranquilidad en el camino empieza con una conversación.

**Contact info block:**
- Celular: +595 995 628 862
- Correo Electrónico: **Hola@Viaje.Com** *(yet another variant — no `.py`, note the display capitalization too — three different renderings of the same address exist across the site: `hola@viaje.com.py`, `hola@viaje.com`, `Hola@Viaje.Com`)*
- Dirección: Edificio Skytower, Asunción, Paraguay

**"Envíanos Un Mensaje"** heading, followed by: "No te pierdas el día a día de nuestros viajes y las alertas de feriados largos." — *(this reads like a newsletter-signup line, not a contact-form intro; the actual form, if any, did not appear in the static DOM — see gap flagged in §2)*

**Forms detected on page**: only the WordPress default search form (`action="/" method="get"`, field `s`). **No dedicated contact/quote form was found in the DOM.** Needs a live manual check (click around, check Network tab for AJAX form submission, check for a hidden Contact Form 7 / WPForms / Elementor form shortcode) before concluding there truly is no lead form — but as scanned, there is no visible functional form.

---

### 3.11 `/blog/` — Blog index

- **Title**: Blog – viaje.com.py
- **Meta description**: none
- **Canonical**: none (missing — flag)
- **H2**: Archive blog

Lists teaser cards for all 3 posts (Hello world!, Destinos Imperdibles en Paraguay para este 2026: ¡Jaha!, Paraguay Profundo: 10 Destinos Imprescindibles...). No unique body copy beyond the archive loop + standard hero/footer.

---

### 3.12 `/paraguay-destinos-imprescindibles-2026/` — Blog post (flagship, REAL)

- **Title**: Paraguay Profundo: 10 Destinos Imprescindibles que Redefinen el Turismo Interno este 2026 – viaje.com.py
- **Meta description**: none
- **Canonical**: `https://viaje.com.py/paraguay-destinos-imprescindibles-2026/`
- **Published**: February 3, 2026 (per on-page byline; sitemap lastmod shows 2026-02-03T19:23:33+00:00, consistent)
- **H1**: Paraguay Profundo: 10 Destinos Imprescindibles Que Redefinen El Turismo Interno Este 2026
- **Byline**: "Por: Yanina / Equipo Viaje.com.py" *(this is the one post with a real-sounding inline byline, distinct from the generic sidebar "Harbert Spin" widget which also still appears)*

**Full body copy (verbatim):**

Por: Yanina / Equipo Viaje.com.py

Viajar por Paraguay es un ejercicio de intimidad. No buscamos la grandilocuencia artificial de otros destinos; aquí buscamos la autenticidad. El turismo interno en 2026 ha madurado: ya no se trata solo de "llegar y mirar", sino de entender nuestra historia, conectar con la tierra roja y desafiar nuestros sentidos.

Hemos seleccionado y desarrollado a profundidad los 10 destinos que todo paraguayo debe vivir, no solo visitar. Esta es tu guía definitiva, cultural y sensorial, para redescubrir el corazón de América del Sur.

**1. San Bernardino: Historia Viva Y Atardeceres De Acuarela**

San Bernardino ha trascendido su etiqueta de "ciudad veraniega" para convertirse en un refugio cultural y gastronómico durante todo el año. Fundada en 1881 por colonos alemanes, la ciudad conserva un aire nostálgico que se mezcla con la modernidad. Caminar por su casco histórico es leer la historia de inmigrantes que soñaron con una nueva vida a orillas del Ypacaraí. Lugares como el emblemático Hotel del Lago no son solo alojamiento; son museos vivos donde se respira la Belle Époque paraguaya y se escuchan ecos de huéspedes ilustres como Antoine de Saint-Exupéry.

Pero la verdadera magia ocurre cuando el sol baja. La geografía de "San Ber", con su suave pendiente hacia el lago, ofrece los atardeceres más fotogénicos del país. En 2026, la tendencia se ha movido hacia los cerros aledaños y la zona de Altos, donde han florecido propuestas de Glamping de alto nivel. Estos espacios permiten dormir bajo las estrellas con comodidades de hotel, rodeados de bosque nativo, ofreciendo una desconexión total del ruido urbano.

*Dato de viajero: No te limites a la avenida principal. Busca la Escalinata de la Virgen; la subida ofrece una perspectiva aérea del lago y la ciudad que pocos aprovechan. Es el punto perfecto para entender la escala del Ypacaraí.*

**2. Las Misiones Jesuíticas (Trinidad Y Jesús): El Barroco Guaraní**

Declaradas Patrimonio de la Humanidad por la UNESCO, las reducciones de Santísima Trinidad del Paraná y Jesús de Tavarangüé son, indiscutiblemente, el tesoro arquitectónico más importante del país. Aquí, la historia no se lee, se toca. Estas "ciudades de Dios" en la selva fueron el escenario de una utopía social única en el mundo, donde el talento musical y artístico indígena se fusionó con la técnica europea. En Trinidad, los frisos de los ángeles con rasgos indígenas tocando instrumentos autóctonos (como maracas) son la prueba pétrea de este sincretismo cultural.

La experiencia cambia radicalmente según la hora. De día, la piedra arenisca rojiza contrasta con el cielo azul intenso y el verde del césped, creando una paleta de colores vibrante. De noche, el Recorrido de Luces y Sonidos transforma las ruinas en un escenario místico. Escuchar música barroca misional (de Domenico Zipoli) caminando entre columnas de 300 años, bajo un cielo estrellado, es una experiencia espiritual que pone la piel de gallina y nos conecta con nuestros ancestros.

*Tip fotográfico: En la Misión de Jesús de Tavarangüé, busca los arcos trilobulados de estilo morisco (únicos en la región). Encuadra el sol poniéndose a través de estos arcos para una composición dramática y llena de historia.*

**3. Saltos Del Monday: La Furia Del Agua En El Bosque Atlántico**

Mientras las miradas internacionales suelen irse a las cataratas vecinas, los paraguayos sabemos que los Saltos del Monday (en Presidente Franco) ofrecen una experiencia mucho más íntima y visceral. Con tres caídas principales de más de 40 metros de altura, el río Monday se desploma con una fuerza que hace vibrar el suelo. Este parque conserva una de las últimas muestras importantes del Bosque Atlántico del Alto Paraná, un ecosistema denso y húmedo que alberga especies únicas.

La infraestructura ha mejorado notablemente para este 2026. El ascensor panorámico permite descender casi hasta el nivel del río, donde la bruma constante (el rocío de la caída) te empapa la cara, permitiéndote sentir la potencia de la naturaleza. Es un lugar cargado de leyendas guaraníes, que hablan del río como una entidad viva. Para los aventureros, el parque ofrece senderismo y arborismo, pero la actividad principal sigue ser la contemplación: pararse en los miradores y dejar que el ruido blanco del agua limpie la mente.

*Enfoque Eco: Observa los vencejos de cascada (esas aves que vuelan atravesando la cortina de agua). Anidan en la roca detrás del salto, un espectáculo de la biología que demuestra cómo la vida se abre paso en condiciones extremas.*

**4. Colonia Independencia Y Salto Suizo: El Alma Del Ybytyruzú**

Ubicada bajo la sombra de la Cordillera del Ybytyruzú, Colonia Independencia es un enclave donde el tiempo parece correr más lento. La fuerte influencia alemana se nota en la arquitectura, los rostros de su gente y, sobre todo, en su gastronomía (es imposible irse sin probar una torta de miel o sus embutidos). Es el destino ideal para el "turismo de bienestar", donde el aire puro de las sierras y el vino artesanal local curan el estrés.

La joya de la corona es el Salto Suizo. Llegar a él es una pequeña aventura que requiere vehículos altos o una caminata saludable entre cañaverales y monte. Al llegar, te encuentras con una caída de agua de más de 60 metros que cae, fina y elegante, creando una piscina natural rodeada de vegetación exuberante. Es un lugar de silencio y respeto. Además, la zona ofrece miradores como el del Cerro Akatí, desde donde se tiene, posiblemente, la mejor vista panorámica de toda la Región Oriental del Paraguay.

*Cultura local: Visita las bodegas artesanales en noviembre o diciembre durante la fiesta de la uva. La tradición vitivinícola aquí es antigua y muy querida por los locales.*

**5. Laguna Blanca: Un "Caribe" De Agua Dulce**

Laguna Blanca (ubicado en Santa Rosa del Aguaray, departamento de San Pedro) es una rareza geológica que debemos proteger con celo. Técnicamente, es el único lago natural del país que se asienta sobre arena calcárea, lo que provoca un fenómeno increíble: el agua es totalmente transparente. A diferencia de las aguas oscuras o arcillosas típicas de nuestros ríos, aquí puedes ver tus pies (y a los peces) a metros de profundidad. Es, visualmente, lo más cercano a una playa caribeña que tenemos en nuestra mediterraneidad.

Al ser una Reserva Natural Privada, el enfoque es el ecoturismo responsable. Es el sitio perfecto para iniciarse en el buceo de superficie (snorkel) o el kayakismo tranquilo, también ofrecen paseos en lancha. El entorno es un área de transición entre el Bosque Atlántico y el Cerrado, lo que significa una biodiversidad riquísima, especialmente en aves. Acampar en sus orillas, sobre la arena blanca y fina, escuchando el sonido de la naturaleza sin contaminación lumínica, es una de las experiencias más puras que ofrece Paraguay.

*Conciencia: El ecosistema de Laguna Blanca es frágil. Como viajeros, nuestra responsabilidad es no dejar huella, usar protectores solares biodegradables y respetar la fauna local.*

**6. Salto Cristal: El Cenote Paraguayo**

Escondido en el corazón de Paraguarí, el Salto Cristal es sinónimo de recompensa. El acceso no es para cualquiera: requiere descender una escalera empinada y rústica a través de un bosque húmedo. Pero el esfuerzo se paga con creces al llegar abajo. La formación geográfica recuerda a un cenote: una piscina natural inmensa, rodeada de paredes de roca altas y vegetación colgante, con una cascada majestuosa alimentando el lago.

La atmósfera aquí es casi prehistórica. La luz del sol entra de forma cenital al mediodía, iluminando el agua con tonos esmeralda y turquesa. Es un lugar muy popular en verano, por lo que recomendamos visitarlo temprano en la mañana o en días de semana para captar la verdadera esencia mística del lugar. Además del baño refrescante, el entorno invita a la fotografía de naturaleza macro: musgos, helechos y mariposas abundan en las piedras húmedas.

*Ten en cuenta: El fondo es de piedra y puede ser resbaladizo. La seguridad y la comodidad te permitirán disfrutar más del entorno.*

**7. El Chaco Y Las Lagunas Saladas: Belleza Inhóspita**

El Chaco paraguayo es un gusto adquirido, reservado para viajeros que buscan belleza en la austeridad. El sistema de Lagunas Saladas en el Chaco Central (Loma Plata/Filadelfia) rompe con el mito de que el Chaco es "seco y aburrido". Estas humedales estacionales son espejos de agua que reflejan un cielo inmenso y son el hogar de miles de flamencos rosados, cisnes coscoroba y otras aves migratorias. El contraste del rosado de las aves con el suelo blanquecino y la vegetación espinosa es de una belleza surrealista.

Más allá de la naturaleza, este viaje es una inmersión cultural en la historia de las colonias menonitas y las comunidades indígenas. Visitar los Centros de Interpretación o los museos locales es fundamental para entender cómo el ser humano ha logrado convivir con un entorno tan hostil y fascinante. Es un lugar de silencios profundos, atardeceres que incendian el horizonte de naranja y violeta, y noches donde la Vía Láctea se ve a simple vista.

*Cuándo ir: La mejor época suele ser entre julio y septiembre, cuando las lagunas tienen agua pero no hay lluvias que dificultan los caminos de tierra, y la concentración de aves es mayor.*

**8. Complejo Itaipú Y Hernandarias: Gigantes De Ingeniería**

Itaipú Binacional no es solo una represa; es un monumento a la capacidad humana que ha sabido integrarse al turismo. La visita técnica es impresionante por la escala de los vertederos y las turbinas, pero en 2026, el foco está en la experiencia completa en Hernandarias. La costanera y la playa de Hernandarias ofrecen un espacio de ocio moderno y seguro frente al Lago Itaipú, ideal para familias y deportes al aire libre.

El imperdible cultural es, sin duda, la Iluminación Monumental de la represa. Ver esa mole de concreto encenderse progresivamente al ritmo de una orquesta sinfónica es un espectáculo conmovedor que mezcla arte y tecnología.

Además, el complejo alberga el Museo de la Tierra Guaraní, un espacio interactivo y moderno que narra miles de años de historia regional, desde los primeros habitantes hasta la construcción de la hidroeléctrica, con un respeto profundo por la cultura ancestral.

*Actividad: Aprovecha la Reserva Tatí Yupí, parte del complejo, para hacer cicloturismo en senderos seguros rodeados de naturaleza preservada.*

**9. Cerro Tres Kandú: El Techo Del Paraguay**

Para los amantes del desafío físico y la montaña, el Cerro Tres Kandú (o Perõ) en el departamento de Guairá es la meta definitiva. Con sus 842 metros, es el punto más alto del territorio nacional.

El ascenso no es un paseo; es un trekking exigente de aproximadamente 3 horas que requiere voluntad y esfuerzo, atravesando "estaciones" que van aumentando en dificultad y pendiente, muchas de ellas asistidas por cuerdas y cabos de acero.

A medida que subís, notas cómo cambia la vegetación, pasando del bosque subtropical a una selva más húmeda y nubosa en la cima. Llegar a la cumbre es un momento de euforia personal. La vista domina todo el valle, las rutas lejanas y los pueblos diminutos abajo. Es un lugar para sentarse en las rocas, respirar el aire puro de altura y sentir la inmensidad del paisaje paraguayo.

Muchos viajeros eligen acampar en la cima (con equipo adecuado) para ver el amanecer más alto del país.

*Nota: Es vital llevar mucha agua y buen calzado. No es solo caminar, es escalar.*

**10. Areguá: Creatividad, Barro Y Lago**

Areguá tiene un alma bohemia que enamora. Situada a orillas del Lago Ypacaraí, esta ciudad ha sido declarada "Ciudad Creativa" por la UNESCO, y se nota en cada esquina.

Es famosa por su alfarería y cerámica; caminar por sus calles es visitar una galería de arte a cielo abierto donde el barro toma formas infinitas, desde macetas utilitarias hasta esculturas surrealistas. Las casonas coloniales con sus amplios corredores cuentan historias de un pasado aristocrático y veraniego.

El punto neurálgico es la Iglesia de la Candelaria, ubicada en la loma más alta. Desde su atrio se tiene una vista panorámica espectacular del lago y los tejados de la ciudad, especialmente mágica al atardecer. Pero Areguá también guarda secretos como el Castillo Carlota Palmerola y sus jardines, o las formaciones geológicas de los Cerros Koi y Chororí, cuyas piedras de arenisca columnar son una rareza geológica (solo existen formaciones similares en Canadá y Sudáfrica).

*Sabor estacional: Si visitas la ciudad entre agosto y septiembre, la ciudad se tiñe de rojo con el Festival de la Frutilla. Probar los postres locales hechos con la fruta recién cosechada es una tradición obligatoria.*

**El Viaje Empieza En Casa**

A menudo, pasamos horas soñando con pasaportes sellados y paisajes lejanos, olvidando que la verdadera aventura comienza justo donde estamos parados. Recorrer Paraguay en este 2026 no es solo una opción turística; es un acto de redescubrimiento y orgullo.

Desde la aridez magnética del Chaco hasta la fuerza imparable de nuestros saltos en el este; desde la mística silenciosa de las ruinas jesuíticas hasta la vibrante energía de nuestros veranos en San Bernardino. Nuestro país es un mosaico de texturas, idiomas y sabores que merece ser explorado con ojos de turista curioso.

Cada kilómetro que recorres dentro de nuestra tierra apoya a comunidades locales, artesanos, guías y emprendedores que, como vos, aman este suelo.

En viaje.com.py, no solo vendemos destinos; diseñamos recuerdos. Sabemos que el mejor viaje no es el que te lleva más lejos, sino el que más te marca el corazón.

¿Qué historia vas a contar este fin de semana? ¡Paraguay te está esperando!

**Images**: `New-Project-2025-12-01T100830.517.jpg` (featured/hero), `cataratas-iguacu1-scaled.jpg` (note: this is an Iguazú Falls stock photo, NOT a Paraguay-specific photo of Saltos del Monday — worth checking if factually mismatched/generic-stock before reuse), all `alt=""`.

**Sidebar cruft on this post**: "About Direction — Harbert Spin" (Lorem ipsum bio, placeholder, ignore) + "Recent Posts" widget (links back to the 3-post list) + "Follow Us:" social icons.

---

### 3.13 `/destinos-imperdibles-2026/` — Blog post (REAL)

- **Title**: Destinos Imperdibles en Paraguay para este 2026: ¡Jaha! – viaje.com.py
- **Meta description**: none
- **Canonical**: `https://viaje.com.py/destinos-imperdibles-2026/`
- **Published**: February 3, 2026 (byline date; sitemap lastmod 2026-04-10T19:13:29+00:00 — later than the other post despite same on-page publish date, suggesting this one was edited more recently)
- **H1**: Destinos Imperdibles En Paraguay Para Este 2026: ¡Jaha!
- **Byline shown**: "By Viaje.com.py" (generic site byline, not a named author — inconsistent with the sibling post's "Por: Yanina" byline)

**Full body copy (verbatim):**

Si algo aprendimos en los últimos años, es que no hace falta cruzar el océano para encontrar paraísos. Paraguay está en su mejor momento: Asunción acaba de ser nombrada por la revista Condé Nast Traveler como uno de los mejores destinos de Sudamérica para 2026, y el turismo interno está más vivo que nunca.

Ya sea que busques desconectarte en un cerro, disfrutar de la arena en el sur o descubrir la nueva movida gastronómica de la capital, aquí te dejamos el Top 5 de destinos para paraguayos este 2026.

**1. Asunción: El Renacer Del Centro Histórico**

La capital ya no es solo un lugar de paso. En 2026, el Centro Histórico vibra con una energía renovada.

- Qué hacer: Caminá por la calle Palma (¡sí, a "palmear" se ha dicho!), visitá el renovado Puerto de Asunción y perdete en los colores de Loma San Jerónimo.
- Tip Gastronómico: No te pierdas la "Nueva Cocina Paraguaya" en lugares como Pakuri o Cocina Clandestina. El sabor de nuestra tierra, pero con un toque de autor que te va a volar la cabeza.

**2. Encarnación: Mucho Más Que Carnaval**

La "Perla del Sur" sigue siendo la reina del verano, pero en 2026 se consolida como un destino de todo el año.

- El Plan: Un atardecer en la Playa San José con un buen tereré es irreemplazable.
- Cultura: Aprovechá la cercanía para visitar las Misiones Jesuíticas de Trinidad y Jesús, Patrimonio de la Humanidad, que este año cuentan con recorridos nocturnos de luces y sonidos mejorados.

**3. Colonia Independencia (Guairá): Naturaleza Y Tradición**

Si lo tuyo es el verde y el aire puro, el Guairá te espera.

- Aventura: Subir al Salto Suizo o al Cerro de la Cruz es un "must" para cualquier paraguayo que ame el trekking.
- El toque local: Disfrutá de la herencia alemana con una buena tabla de embutidos y una cerveza artesanal bien fría después de la caminata.

**4. Saltos Del Monday (Presidente Franco): La Fuerza Del Agua**

A pasos de Ciudad del Este, los Saltos del Monday demuestran que no tenemos nada que envidiar a las grandes maravillas del mundo.

- Novedad 2026: El parque ha sumado nuevas pasarelas y actividades de aventura como tirolesa sobre el río. Es el destino ideal para una escapada de fin de semana que combine compras en CDE y naturaleza pura.

**5. El Chaco Paraguayo: Para Los Corazones Aventureros**

El Chaco está de moda. Con la conectividad mejorada, llegar a Filadelfia o Loma Plata es más sencillo que nunca.

- Experiencia: Es el lugar para el avistaje de fauna y para entender la historia de los Fortines. En 2026, el turismo cultural en las comunidades indígenas ha crecido, ofreciendo una visión auténtica y respetuosa de nuestra identidad.

**¿Cómo Planear Tu Viaje?**

En viaje.com.py queremos que tu única preocupación sea cargar el termo. Recordá que para las Posadas Turísticas siempre es mejor reservar con tiempo, especialmente en feriados largos.

¿Cuál de estos destinos te falta tachar de tu lista? ¡Contanos en los comentarios o compartí tus fotos con el hashtag #ViajePY!

**Images**: `cataratas-iguacu1-1024x572.jpg` (featured), `New-Project-2025-12-01T100830.517.jpg`, `cataratas-iguacu1-scaled.jpg` — *(same Iguazú stock photo reused again — same generic-photo caveat as the other post)*, all `alt=""`.

**Content overlap warning**: This post and `/paraguay-destinos-imprescindibles-2026/` cover **significantly overlapping ground** — both list "Saltos del Monday," "Colonia Independencia/Salto Suizo," and Chaco content, with this shorter post reading like an earlier/simpler draft of the same theme the flagship post later expanded on. **Possible keyword cannibalization** — worth deciding in the rebuild whether to merge these into one authoritative long-form post plus a shorter seasonal update, rather than porting both as fully separate URLs targeting the same intent.

---

## 4. Site-wide FAQ accordion (used on `/`, `/servicios/`, `/nosotros/`)

Six Q&As, extracted from the DOM (accordion is collapsed by default but content is present, not lazy-loaded):

1. **¿Es seguro viajar por el interior de Paraguay por mi cuenta?**
   Totalmente. Paraguay es un país hospitalario por naturaleza. Sin embargo, lo ideal es viajar con información actualizada sobre el estado de los caminos (especialmente si vas al Chaco o zonas de saltos) y tener contactos locales. Nosotros te damos esa "hoja de ruta" para que te muevas con total confianza.
2. **¿Cuál es la mejor época para visitar los saltos de agua?**
   Si querés ver los saltos en su máximo esplendor, la época de lluvias (primavera/verano) es ideal. Pero ojo: el calor puede ser intenso. Si buscás un clima más agradable para hacer senderismo, de mayo a agosto es perfecto, aunque los caudales pueden estar un poco más bajos.
3. **¿Necesito una camioneta 4x4 para conocer los destinos que recomiendan?**
   No para todos. Muchos de los mejores lugares son accesibles con un auto sedán convencional. Sin embargo, para joyas escondidas en el Chaco o ciertos accesos en el Guairá, un vehículo alto es clave. En cada una de nuestras guías te especificamos qué tipo de vehículo necesitás para no pasar malos ratos.
4. **¿Cómo funcionan sus servicios de traslados privados?**
   Es simple: nos decís dónde estás y a dónde querés ir. No es un taxi común; es un servicio con conductores que conocen las rutas turísticas, en vehículos cómodos y con espacio para tu equipo de aventura. Ideal si querés olvidarte de manejar y solo mirar el paisaje.
5. **¿Qué pasa si llueve durante mi viaje programado?**
   En Paraguay el clima es dinámico. Si tenés un itinerario con nosotros, siempre tenemos un "Plan B" bajo la manga (actividades bajo techo, museos o rutas gastronómicas) para que la lluvia no arruine tu experiencia, sino que le dé otro color.
6. **¿Cómo puedo reservar un paquete de vacaciones?**
   Podés elegir uno de nuestros itinerarios sugeridos o escribirnos directamente para armar algo desde cero. No creemos en paquetes rígidos; nos gusta conversar, entender qué buscás y armar algo que realmente te haga sentido.

*(Note: this is a distinct set from the 10 Q&As on the standalone `/faq/` page in §3.9 — both exist, neither duplicates the other in question text, though several topics overlap conceptually. Recommend the rebuild consolidate into one canonical FAQ set, or clearly differentiate "logistics FAQ" from "general travel FAQ" if keeping both.)*

---

## 5. Placeholder / theme-demo pages — confirmed NOT real content

These must **not** be migrated as if they were real Viaje.com.py content. Full verbatim capture below only to prove they are pure Lorem-ipsum/demo boilerplate — none of this text should appear in the rebuild.

### `/servicio-unico/`
Title: "servicio único". Hero: "Services Details" + Lorem ipsum. Body: "Custom Tour Packages" with Lorem ipsum paragraphs, fake service list repeated 3x identically (Custom Tour Packages / Hotel & Resort Booking / International Trips / Travel Guide & Support), closing Lorem ipsum paragraph. 100% unedited Elementor/theme demo content, in English, structurally unrelated to the real Spanish service pages.

### `/paquete-individual/`
Title: "Paquete individual". Hero: "Packages Details" + Lorem ipsum. Body: fake "Thailand Adventure Tour" package with price "$820", "Pax: 4, Time: 7 Day 6 Night", Location "Thailand", fake phone "+1 (800) 555-6789", fake email "info@electix.com" (theme's demo agency name "Electix"), Lorem-ipsum-adjacent "Pianissimos of dulcimers..." nonsense filler text, garbled "Photo Gallery" captions ("Fact that a reader will be distr acted bioiiy" — literal Lorem-Picsum-style theme captions). 100% unedited demo content.

### `/paquetes/`
Title: "Paquetes". Hero: "Our Packages" + Lorem ipsum. Body: 8 fake destination cards — Maldives, Thailand, Canada, India, Dubai, Nepal, Colombia, Greece — each with fake "% Off" badges and fake USD prices ($100–$300 range). 100% unedited demo content, zero connection to Paraguay.

### `/elementor-9/`
Title: "Elementor #9". Completely empty draft — only the theme chrome (header/footer/sidebar widgets) renders; the actual page body between "Elementor #9" heading and "About Direction" sidebar is blank. This is an orphaned draft page Elementor auto-created and never deleted. **Should not be recreated at all** — not even as a redirect target, just omit it.

### `/hello-world/`
Title: "Hello world!". Standard unedited WordPress "first post" default: "Welcome to WordPress. This is your first post. Edit or delete it, then start writing!" Published Dec 16, 2025 (site creation date, effectively). **Should not be migrated.**

### `/category/uncategorized/`
Default WordPress taxonomy archive, never customized (hero still shows generic "Archive Blog" + Lorem ipsum subtitle). Lists the same 3 posts as `/blog/` — pure duplicate-content URL. **Should not be recreated** in the rebuild; if the new site has categories/tags at all, they should be real, purpose-built taxonomy terms, not this default leftover.

---

## 6. Recurring template pattern (for componentizing the HTML/PHP rebuild)

The site is built on WordPress + Elementor with (apparently) a travel-agency theme ("TourDen," per footer/demo leftovers) that was partially customized. Clear, reusable structural patterns emerge:

### 6.1 Global chrome (every page)
- **Header**: logo + horizontal nav (Inicio · Paquetes · Servicios · Blog · FAQ · Nosotros · Contacto) + phone number displayed + presumably a CTA button (not always captured in flat text but present as `+595 995 628 862` header element on homepage)
- **Footer** (4-5 columns): Brand blurb (needs real Viaje copy, not "TourDen") → Social icons ("Seguinos") → "Páginas Principales" nav list → *(remove)* "Tour Packages" fake list → Contact block (phone/email/address) → Copyright bar

### 6.2 "Simple content page" template — used by all 5 service pages + arguably reusable for `/nosotros/`, `/faq/`, `/contacto/`
Pattern, top to bottom:
1. **Hero band**: Page title (H1-equivalent) + one-paragraph intro/hook + breadcrumb (INICIO / PAGE NAME)
2. Left/utility sidebar block repeated on every service page: "Todos los Servicios" link, mini contact card (phone, email, address), "Seguinos" social icons
3. **Main body**: One `H2` "concept" heading + 2 paragraphs of body copy, followed by a **"Servicios Incluídos"** bullet list (always exactly 6 items formatted as short noun phrases)
4. Two supporting `H3` subsections, each 1 paragraph, deepening two different angles of the same service (e.g. "what makes us different" + "how the logistics/support actually works")
5. Standard footer

This is a **highly reusable single component** — call it the "Service Page" template: `hero + intro + 6-item feature list + 2 supporting subsections`. All 5 service pages (`agencia-de-viaje`, `asistencia-personalizada`, `gestion-de-visas`, `traslados`, `vacaciones`) follow this exact shape with zero structural deviation — only copy changes. This should become one PHP template/partial (`service-page.php`) fed by per-service content data (could live as one row each in a small JSON/array/CMS table: slug, title, hero copy, 2 body paragraphs, 6 feature bullets, 2 subsection headings+paragraphs, image set).

### 6.3 "Blog post" template — used by both real posts (and the placeholder ones, structurally)
Pattern:
1. Hero: "BLOG Details" kicker + generic Lorem-ipsum sub-line *(remove/replace)* + breadcrumb
2. Byline row: author + date + comment count
3. **H1** = post title
4. Body: long-form article, using `H2` for major numbered sections ("1. Destino X: Subtitle"), occasional `H3` for sub-callouts, italicized "tip" callout paragraphs embedded inline (e.g. "*Dato de viajero:*", "*Tip fotográfico:*", "*Cuándo ir:*") — this italic-callout pattern is a nice content device worth preserving as a real reusable style (a highlighted "insider tip" box) in the rebuild rather than plain italics.
5. Sidebar: "About Direction" author bio widget *(replace "Harbert Spin" placeholder with real author info, or remove and rely on the inline byline pattern used in the flagship post)*, "Recent Posts" list, social icons
6. Standard footer

Recommend the rebuild's blog template support: title, single hero image, author name (real), publish date, body (markdown/HTML with numbered `H2` sections), and an optional inline "tip callout" shortcode/component — since that's a genuine, reusable content pattern already present in the best existing post.

### 6.4 "Hub/index" template — `/servicios/`, `/paquetes/` (once repopulated with real data), `/blog/`
Pattern: hero band → card grid (title + short blurb + link) pulling from the same content type as the detail template → (on `/servicios/` specifically) the site-wide FAQ widget is appended → footer.

### 6.5 FAQ accordion component
Reusable Q&A accordion widget, used in two different contexts (embedded mid-page on 3 pages, and as a full page on `/faq/`). Rebuild as one component: array of `{question, answer}` pairs, rendered as a collapsible accordion. Recommend **merging the two currently-separate Q&A sets** (6 + 10 = 16 total, with light topical overlap) into one canonical, deduplicated FAQ content set reused across pages, rather than maintaining two parallel dataset going forward.

---

## 7. Contact / lead-capture summary

- **Phone/WhatsApp**: `+595 995 628 862` (consistent, this is the number to keep)
- **Email**: inconsistent across the site — `hola@viaje.com.py` (2 occurrences, body content) vs `hola@viaje.com` (footer, every page) vs `Hola@Viaje.Com` (contact page field). **Decide the correct final address before rebuild** — almost certainly `hola@viaje.com.py` is intended (matches the domain) and the other two are typos to fix.
- **Address**: Edificio Skytower, Asunción, Paraguay (consistent)
- **No functional contact form detected** anywhere in the static DOM — only the WordPress default search form. The "Envíanos Un Mensaje" / "Solicita Un Presupuesto Gratuito" CTAs likely rely on manual click-to-call/WhatsApp/mailto rather than an actual on-page form, OR a form exists but loads via a mechanism (JS-injected iframe, deferred plugin script) that a static-DOM pass didn't catch. **Recommend a live manual check** (open the site, click "Envíanos Un Mensaje", inspect Network tab) before finalizing the rebuild's lead-capture approach — but plan to build a real WhatsApp-first + optional form lead flow regardless, since that's this user's standard pattern for other Paraguay sites.

---

## 8. Gaps / open questions

1. **No functional contact form found** — needs live verification (see §7). Rebuild should almost certainly add a proper WhatsApp-composer + lead form regardless (per user's established pattern on other PY sites).
2. **Broken "0+" stat counters** (Experience/Destinos Cubiertos on `/` and `/nosotros/`) — real numbers were never set. Need actual figures from the site owner (years active, destinations covered) before rebuild, or remove the counters entirely.
3. **Team member bios** (Marcos Benítez, Lucía Ferreira, Andrés Villalba, Raquel Galeano) — only names captured; no visible per-person bio/role text in the flat-text pass. If bios exist behind a hover/expand interaction, they weren't captured — verify live, or ask the site owner directly for bio copy.
4. **Gallery images have no captions/alt text** — "Postales de Nuestra Tierra" section on homepage shows category labels (Rutas del Agua, Escapadas Urbanas, etc.) but it's unclear which photos map to which category without a live visual check.
5. **Discount badges ("10% Off", "12% Off", "15% Off") on the homepage city cards** — no discount mechanic exists anywhere else on the site. Likely leftover decorative styling from the tour-package theme template, not a real live promotion. Confirm with site owner whether to keep, remove, or make real.
6. **Iguazú Falls stock photo (`cataratas-iguacu1...jpg`) used as the hero image for two Paraguay blog posts** — Iguazú is on the Argentina/Brazil border, not technically inside Paraguay (though near Ciudad del Este) — verify this is intentional (regional context) vs. a placeholder stock photo mismatch before reusing.
7. **Significant content overlap between the two real blog posts** — both largely cover the same "top Paraguay destinations 2026" theme with real duplication (Saltos del Monday, Chaco, Colonia Independencia/Salto Suizo all appear in both). Recommend deciding whether to merge/consolidate into one canonical pillar post + a shorter seasonal companion, to avoid self-cannibalizing target keywords in the new site's SEO.
8. **Whether `/paquetes/` and `/paquete-individual/` should be rebuilt as real "package" content, or dropped entirely** — currently 100% theme demo. If real trip/package products exist off-site (WhatsApp-negotiated, no fixed catalog per the "no rigid catalog" messaging repeated on every service page), these two URLs may simply not need real-content replacements — worth confirming intent (the copy on `/agencia-de-viaje/` explicitly says "Dejá atrás los paquetes rígidos de catálogo" — deliberately anti-catalog positioning — which is in tension with having `/paquetes/` and `/paquete-individual/` URLs at all).
9. **No structured data (JSON-LD) anywhere** — for a local travel-agency business, `LocalBusiness`/`TravelAgency` schema plus `Article` schema on blog posts would be a meaningful SEO gain in the rebuild, not present to preserve from the old site but worth flagging as a build requirement.
10. **`viaje.com.py` vs the second site `thingstodoinparaguay.com`** mentioned by the user for the rebuild — this scan covers only `viaje.com.py`; `thingstodoinparaguay.com` has not been scanned and would need its own pass if it's an existing live site with content to preserve, or is a net-new build.
