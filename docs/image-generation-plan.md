# Image Generation Plan — viaje.com.py

21 images needed. All current hero/gallery images are remote CDN placeholders — none are local yet.
Generate each with Higgsfield (Nanobanana Pro), paste the resulting URL back here for Anton, then
we run them through `webimg` (see `.claude/skills/webimg-pipeline` or the synced skill) to convert,
resize and rename.

## Consistent visual style (paste as a prefix/style note on every prompt if Nanobanana Pro supports a style field)

> Warm, cinematic travel-photography realism. Natural light, golden-hour or soft overcast tones,
> slightly desaturated greens, warm amber highlights. Shot on a full-frame DSLR, 35mm or 50mm lens,
> shallow depth of field where relevant, no visible text, no logos, no watermarks, no people looking
> directly at camera (except the 4 team portraits). Paraguay-specific landscapes and architecture —
> avoid generic stock-photo look, avoid Brazilian/Argentine Iguazú imagery, avoid anything not
> geographically accurate to Paraguay.

---

## Hero / gallery images (17)

### 1. hero-home
- **Filename (`--name`):** `4x4-camino-tierra-roja-paraguay`
- **Aspect ratio:** 21:9
- **Alt text:** "Camioneta 4x4 avanzando por un camino de tierra roja al amanecer entre lapachos en flor"
- **Prompt:** "A rugged white 4x4 pickup truck driving away from camera down a long red dirt road (typical of rural Paraguay), flanked by blooming pink and yellow lapacho trees. Early morning golden-hour light, long soft shadows, low sun flare in the upper corner, dust trail behind the wheels. Wide cinematic landscape composition, horizon low in frame, big open pastel-blue sky with a few warm-lit clouds. Photorealistic, shot on 35mm lens, shallow depth of field on the truck, background softly blurred. No people visible, no text, no logos."

### 2. hero-nosotros
- **Filename:** `colinas-cerro-cora-niebla-matutina`
- **Aspect ratio:** 21:9
- **Alt text:** "Colinas de Cerro Corá cubiertas de niebla matutina en Paraguay"
- **Prompt:** "Rolling green hills of Cerro Corá National Park in northern Paraguay at sunrise, soft morning mist settling in the valleys between the hills, layered ridgelines fading into haze toward the horizon. Warm golden light breaking through the mist from the left, scattered low scrub vegetation and a few isolated trees on the hilltops. Wide panoramic landscape shot, photorealistic, no people, no buildings, no text."

### 3. hero-contacto
- **Filename:** `asuncion-skyline-atardecer-rio-paraguay`
- **Aspect ratio:** 21:9
- **Alt text:** "Perfil urbano de Asunción al atardecer visto desde la bahía sobre el río Paraguay"
- **Prompt:** "Skyline of Asunción, Paraguay, photographed from across the bay at dusk. Modern low-rise buildings and the Costanera waterfront silhouetted against a deep orange-to-purple sunset sky, warm reflections shimmering on the calm river water in the foreground. A few lights beginning to turn on in windows. Wide cinematic composition, photorealistic, no people, no text, no visible signage."

### 4. hero-faq / chaco
- **Filename:** `sabana-palmeras-chaco-paraguayo-atardecer`
- **Aspect ratio:** 21:9
- **Alt text:** "Sabana de palmeras del Chaco paraguayo bajo un cielo ámbar al atardecer"
- **Prompt:** "Vast open savanna of the Paraguayan Chaco at golden hour, scattered tall caranday palm trees silhouetted against an amber and orange sky, dry golden grassland stretching to a flat horizon. A dirt track curves gently through the frame. Warm dusty light, long shadows from the palms, wide cinematic landscape composition, photorealistic, no people, no vehicles, no text."

### 5. hero-destinos1 (Jesuit ruins post)
- **Filename:** `ruinas-jesuiticas-trinidad-luz-dorada`
- **Aspect ratio:** 16:9
- **Alt text:** "Arcos de piedra de las ruinas jesuíticas de Trinidad del Paraná iluminados por luz dorada"
- **Prompt:** "Weathered sandstone ruins of the Jesuit mission of Trinidad del Paraná, Paraguay — tall stone arches and partial walls covered in patches of moss and lichen, ornate carved stone details still visible. Golden-hour sunlight raking across the ruins from a low angle, casting long warm shadows through the arches onto the grass. Wide shot, some green lawn and a few palm trees in the background, clear soft-blue sky. Photorealistic architectural travel photography, no people, no text, no tourists in frame."

### 6. hero-saltosmonday
- **Filename:** `saltos-del-monday-cascada-selva`
- **Aspect ratio:** 16:9
- **Alt text:** "Cascada de los Saltos del Monday cayendo entre la vegetación selvática de Paraguay"
- **Prompt:** "Saltos del Monday waterfall in Paraguay — a wide multi-tiered waterfall cascading over dark basalt rock into a misty pool below, dense green subtropical jungle vegetation framing both sides. Fine spray creating a soft mist and a faint rainbow catching the light. Midday sunlight filtering through canopy leaves at the edges of frame. Photorealistic nature photography, wide-angle composition, no people, no railings or infrastructure, no text."

### 7. hero-agencia
- **Filename:** `escritorio-planificacion-viaje-mapa-paraguay`
- **Aspect ratio:** 4:3
- **Alt text:** "Escritorio visto desde arriba con un mapa de Paraguay, libreta, café y teléfono para planificar un viaje"
- **Prompt:** "Top-down flat-lay photograph of a travel-planning desk: an open paper map of Paraguay in the center, a leather-bound notebook with handwritten notes, a cup of coffee with steam rising, a smartphone showing a blank dark screen, a pair of reading glasses, and a pen resting diagonally across the map. Warm natural window light from one side, soft shadows, rustic wooden desk surface. Photorealistic product/lifestyle photography, no visible text or logos on any item, no people, no brand names."

### 8. hero-asistencia
- **Filename:** `viajero-ventana-aeropuerto-amanecer`
- **Aspect ratio:** 16:9
- **Alt text:** "Viajero observando un avión en la pista desde la ventana del aeropuerto al amanecer"
- **Prompt:** "A traveler seen from behind, silhouetted, standing at a large airport terminal window, looking out at a commercial airplane parked on the tarmac during a soft pink-and-orange sunrise. Reflections of the terminal ceiling lights faintly visible in the glass. Carry-on suitcase resting beside the person's feet. Warm, hopeful atmosphere, photorealistic travel photography, wide shot, person's face not visible, no text, no airline logos or livery visible on the plane."

### 9. hero-visas
- **Filename:** `pasaporte-documentos-tramite-visa-escritorio`
- **Aspect ratio:** 4:3
- **Alt text:** "Pasaporte, formularios impresos y tarjeta de embarque organizados sobre un escritorio para un trámite de visa"
- **Prompt:** "Close-up flat-lay of a passport (plain dark blue cover, no visible national emblem or text), a stack of printed visa application forms, a boarding pass, and a black pen, arranged neatly on a wooden desk. Soft directional window light from the left creating gentle shadows, shallow depth of field with the passport in sharp focus and the background papers slightly soft. Photorealistic documentary-style photography, no legible text on any document, no people, no logos."

### 10. hero-traslados
- **Filename:** `van-traslado-carretera-paraguay-amanecer`
- **Aspect ratio:** 16:9
- **Alt text:** "Van de traslado privado circulando por una carretera tranquila de Paraguay al amanecer"
- **Prompt:** "A black passenger van (no visible logos or plates) driving along a quiet two-lane paved road in rural Paraguay during early morning, soft golden sunrise light hitting the side of the vehicle, open farmland and scattered trees on either side of the road, faint mist near the ground. Slight motion blur on the wheels to convey movement, sharp focus on the van body. Wide cinematic composition, photorealistic, no people visible through windows, no text or logos, no other traffic."

### 11. hero-vacaciones
- **Filename:** `familia-caminando-orilla-lago-tarde-dorada`
- **Aspect ratio:** 16:9
- **Alt text:** "Familia caminando hacia la orilla de un lago cargando toallas y una hielera en la tarde dorada"
- **Prompt:** "A family of four seen from behind, walking together toward a lake shore, carrying colorful beach towels and a small cooler, in warm late-afternoon golden light. Sandy shoreline path leading to calm lake water reflecting the golden sky, a few trees casting long shadows across the path. Candid, joyful, unposed feeling, photorealistic lifestyle travel photography, faces not clearly visible, no text, no logos on clothing or gear."

### 12. hero-encarnacion
- **Filename:** `costanera-encarnacion-playa-palmeras`
- **Aspect ratio:** 16:9
- **Alt text:** "Costanera de Encarnación con playa de arena y palmeras junto al río Paraná"
- **Prompt:** "The Costanera waterfront promenade of Encarnación, Paraguay, with a wide sandy beach (Playa San José) along the Paraná river, tall palm trees lining a paved walking path, calm turquoise-brown river water extending to the horizon. Bright midday sun with a few scattered clouds, clean modern promenade architecture with low walls and lampposts. Photorealistic travel photography, wide landscape composition, a few small distant figures for scale but no clear faces, no text, no signage."

### 13. hero-chaco-route
- **Filename:** `ruta-transchaco-horizonte-filadelfia`
- **Aspect ratio:** 21:9
- **Alt text:** "Ruta Transchaco extendiéndose recta hacia el horizonte cerca de Filadelfia, Paraguay"
- **Prompt:** "The Transchaco Highway, a straight two-lane paved road cutting through flat dry Chaco scrubland toward a distant vanishing point on the horizon near Filadelfia, Paraguay. Sparse thorny bushes and low trees on both sides, a wide pale-blue sky with a few wispy clouds, heat-haze shimmer visible far down the road. Midday harsh light emphasizing the vastness and emptiness of the landscape. Wide cinematic road-trip composition, photorealistic, no vehicles, no people, no text or road signs with legible writing."

### 14. hero-ypacarai
- **Filename:** `muelle-madera-lago-ypacarai-san-bernardino`
- **Aspect ratio:** 16:9
- **Alt text:** "Muelle de madera adentrándose en el Lago Ypacaraí frente a San Bernardino al atardecer"
- **Prompt:** "A weathered wooden dock extending out into the calm waters of Lake Ypacaraí near San Bernardino, Paraguay, during evening golden hour. The lake surface is glassy, reflecting a warm orange and pink sunset sky, with a faint silhouette of shoreline trees and a few small boats moored in the distance. Dock planks show natural wood grain and slight wear. Wide symmetrical composition centered on the dock leading the eye to the horizon, photorealistic, no people, no text."

### 15. hero-saltosuizo
- **Filename:** `salto-suizo-cascada-ybytyruzu-bosque`
- **Aspect ratio:** 16:9
- **Alt text:** "Salto Suizo, una delgada cascada cayendo entre el bosque de Ybytyruzú hacia una poza natural"
- **Prompt:** "Salto Suizo waterfall in the Ybytyruzú mountain range, Paraguay — a narrow, tall ribbon of water falling from a rocky cliff edge through dense green forest into a clear natural pool below. Sunlight filtering through the forest canopy creates dappled light and a soft mist near the base of the falls. Rich greens, moss-covered rocks, a few ferns in the foreground. Vertical sense of scale, photorealistic nature photography, no people, no infrastructure, no text."

### 16. gallery-asuncion-calle
- **Filename:** `calle-colonial-asuncion-arquitectura`
- **Aspect ratio:** 4:3
- **Alt text:** "Calle colonial de Asunción con fachadas históricas y arquitectura tradicional paraguaya"
- **Prompt:** "A narrow colonial-era street in downtown Asunción, Paraguay, lined with low historic buildings featuring pastel-colored facades (faded yellow, blue, white), wrought-iron balconies, and old wooden shutters. Warm late-afternoon sunlight raking across the building fronts, gentle shadows in the street, a few weathered cobblestones visible on the road surface. Photorealistic urban travel photography, no people in sharp focus, no legible signage or text, no modern cars prominent in frame."

### 17. gallery-terere
- **Filename:** `terere-guampa-bombilla-tradicion-paraguaya`
- **Aspect ratio:** 4:3
- **Alt text:** "Guampa y bombilla de tereré con hierba mate sobre una mesa de madera, tradición paraguaya"
- **Prompt:** "Close-up still-life photograph of a traditional Paraguayan tereré setup: a cow-horn guampa (drinking vessel) filled with green yerba mate, a metal bombilla straw resting in it, a thermos with ice water beside it, condensation droplets visible on the thermos. Placed on a rustic wooden table outdoors, soft natural daylight, shallow depth of field with the guampa in sharp focus. Warm, authentic, everyday atmosphere. Photorealistic macro/still-life photography, no people, no text, no brand labels."

---

## Team portraits (4)

Same lighting/style for all four for visual consistency: professional but warm environmental
headshot, shot outdoors or against a softly blurred neutral background (office window light or
greenery), natural skin tones, subject looking slightly off-camera or with a relaxed genuine
smile, business-casual attire suited to a Paraguayan travel agency. Aspect ratio 1:1, `--position top`.

### 18. team-marcos-benitez
- **Filename:** `retrato-equipo-marcos-benitez-agencia-viajes`
- **Alt text:** "Retrato de Marcos Benítez, integrante del equipo de la agencia de viajes"
- **Prompt:** "Professional environmental portrait of a Paraguayan man in his late 30s named Marcos, dark hair, short well-groomed beard, warm confident smile, wearing a light-blue button-up shirt with sleeves slightly rolled. Photographed from the chest up, softly blurred bright office interior in the background with hints of greenery near a window. Natural soft daylight from the left, shallow depth of field, photorealistic corporate headshot style, looking slightly off-camera, no text, no logos on clothing."

### 19. team-lucia-ferreira
- **Filename:** `retrato-equipo-lucia-ferreira-agencia-viajes`
- **Alt text:** "Retrato de Lucía Ferreira, integrante del equipo de la agencia de viajes"
- **Prompt:** "Professional environmental portrait of a Paraguayan woman in her early 30s named Lucía, long dark wavy hair, friendly genuine smile, wearing a terracotta-colored blouse. Photographed from the chest up, softly blurred bright office interior in the background with a hint of a world map or plants. Natural soft daylight from the left, shallow depth of field, photorealistic corporate headshot style, looking slightly off-camera, no text, no logos on clothing."

### 20. team-andres-villalba
- **Filename:** `retrato-equipo-andres-villalba-agencia-viajes`
- **Alt text:** "Retrato de Andrés Villalba, integrante del equipo de la agencia de viajes"
- **Prompt:** "Professional environmental portrait of a Paraguayan man in his mid-40s named Andrés, short greying hair, calm approachable expression with a slight smile, wearing a navy-blue polo shirt. Photographed from the chest up, softly blurred bright office interior in the background. Natural soft daylight from the left, shallow depth of field, photorealistic corporate headshot style, looking slightly off-camera, no text, no logos on clothing."

### 21. team-raquel-galeano
- **Filename:** `retrato-equipo-raquel-galeano-agencia-viajes`
- **Alt text:** "Retrato de Raquel Galeano, integrante del equipo de la agencia de viajes"
- **Prompt:** "Professional environmental portrait of a Paraguayan woman in her late 20s named Raquel, shoulder-length straight dark hair, bright warm smile, wearing a mustard-yellow blouse. Photographed from the chest up, softly blurred bright office interior in the background with soft window light. Natural soft daylight from the left, shallow depth of field, photorealistic corporate headshot style, looking slightly off-camera, no text, no logos on clothing."

---

## After generation

1. Generate each image in Higgsfield with Nanobanana Pro using the prompt above.
2. Paste the resulting Higgsfield result URLs back into this chat (id → URL is enough, e.g. "1: https://...").
3. I'll fetch each via MCP and run them through `webimg convert` using the exact `--name`, `--alt`, and `--ar` above, output into `sites/viaje.com.py/assets/img/`.
4. I'll wire the generated `<picture>` snippets into the front matter / templates and update `data/team.json` with the photo paths.
5. Run `tools/verify.php` to confirm every image slot resolves and alt text passes.
