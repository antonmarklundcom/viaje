<?php
declare(strict_types=1);

/**
 * viaje.com.py — site configuration.
 * Secrets (admin password hash, preview secret, CRM key) live in config.local.php.
 */

return [
    'domain'       => 'viaje.com.py',
    'base_url'     => 'https://viaje.com.py',
    'timezone'     => 'America/Asuncion',
    'force_https'  => true,
    'force_host'   => 'viaje.com.py',
    'lang'         => 'es',
    'html_lang'    => 'es-PY',
    'locale_og'    => 'es_PY',
    'site_name'    => 'Viaje.com.py',
    'title_suffix' => ' | Viaje.com.py',
    'tagline'      => 'Agencia de viajes en Paraguay: rutas a medida, traslados, asistencia y gestión de visas.',
    'theme_color'  => '#0F5C4A',
    'staging'      => false,
    'debug'        => false,
    'default_og_image' => '/assets/img/camino-de-tierra-roja-4x4-paraguay.jpg',
    // No third-party <head> extras: no Google Fonts, analytics or embeds (system fonts + own CSS only).
    'head_extra' => '',
    'footer_blurb' => 'Viajes a medida por Paraguay, diseñados con quienes conocen el país de cerca. Sin paquetes rígidos.',

    'contact' => [
        'phone_display' => '+595 995 628 862',
        'phone_e164'    => '+595995628862',
        'whatsapp_e164' => '595995628862',
        'email'         => 'hola@viaje.com.py',
        'address'       => [
            'street'       => 'Edificio Skytower',
            'city'         => 'Asunción',
            'region'       => 'Asunción',
            'country'      => 'PY',
            'country_name' => 'Paraguay',
        ],
        'hours'                 => 'Lun–Sáb 08:00–19:00',
        // OpenStreetMap search link (no embedded map, no third-party requests on page load).
        'map_url'               => 'https://www.openstreetmap.org/search?query=Edificio%20Skytower%2C%20Asunci%C3%B3n%2C%20Paraguay',
        'whatsapp_default_text' => 'Hola Viaje.com.py, quiero consultar por',
    ],

    // Anton supplies the real profile URLs (plan §7 item 11); null entries are skipped.
    'socials' => ['instagram' => null, 'facebook' => null, 'tiktok' => null],

    'schema' => [
        'type'        => 'TravelAgency',
        'logo'        => '/assets/logo.svg',
        'founder'     => 'Anton Marklund',
        'area_served' => 'Paraguay',
        'price_range' => '$$',
    ],
    'author_default' => ['name' => 'Equipo Viaje.com.py', 'type' => 'Organization'],
    // Bylines: shown in the author box under each article and emitted as the schema author.
    // Bios only say what nosotros.md already says; add more here rather than in templates.
    'authors' => [
        'Equipo Viaje.com.py' => [
            'type' => 'Organization',
            'role' => 'Equipo editorial',
            'bio'  => 'Viajeros, fotógrafos y especialistas en logística que recorren Paraguay y conocen de primera mano los caminos, las distancias y los secretos de cada destino.',
            'url'  => '/nosotros/',
        ],
        // The pillar post's WordPress byline; the bio only repeats what the team page says.
        'Yanina — Equipo Viaje.com.py' => [
            'type' => 'Person',
            'role' => 'Equipo Viaje.com.py',
            'bio'  => 'Parte del equipo que recorre Paraguay y escribe las guías de Viaje.com.py, con los caminos, las distancias y los secretos de cada destino de primera mano.',
            'url'  => '/nosotros/',
        ],
        'Anton Marklund' => [
            'type' => 'Person',
            'role' => 'Fundador de Viaje.com.py',
            'bio'  => 'Dejó Suecia para vivir en Paraguay y fundó Viaje.com.py para mostrar el país con la mirada curiosa de quien lo eligió como hogar.',
            'url'  => '/nosotros/',
        ],
    ],

    // IndexNow (Bing and other engines): the key is public by design; the engine answers /<key>.txt
    // with it and tools/indexnow.php submits changed URLs. See docs/cutover-runbook.md.
    'indexnow' => ['key' => '8778ae5792160fc55cb5287df6c13406'],

    // "Paquetes" leaves the nav (plan §1 item 5). Empty hubs stay out until they have content.
    'nav' => [
        ['label' => 'Inicio',       'href' => '/'],
        ['label' => 'Servicios',    'href' => '/servicios/',
            'columns' => [
                ['heading' => 'Servicios', 'links' => [
                    ['label' => 'Agencia de Viaje',         'href' => '/agencia-de-viaje/',         'desc' => 'Viajes a medida'],
                    ['label' => 'Vacaciones',               'href' => '/vacaciones/',               'desc' => 'Paquetes sin catálogo'],
                    ['label' => 'Traslados Privados',       'href' => '/traslados/',                'desc' => 'Aeropuerto y ciudades'],
                    ['label' => 'Asistencia Personalizada', 'href' => '/asistencia-personalizada/', 'desc' => 'Acompañamiento 24/7'],
                    ['label' => 'Gestión de Visas',         'href' => '/gestion-de-visas/',         'desc' => 'Trámites'],
                ]],
                ['heading' => 'Qué hacer', 'links' => [
                    ['label' => 'Saltos del Monday',           'href' => '/actividades/saltos-del-monday/'],
                    ['label' => 'Salto Suizo y Ybytyruzú',     'href' => '/actividades/salto-suizo-ybytyruzu/'],
                    ['label' => 'Lago Ypacaraí',               'href' => '/actividades/lago-ypacarai-san-bernardino/'],
                    ['label' => 'Costanera de Encarnación',    'href' => '/actividades/encarnacion-costanera/'],
                    ['label' => 'Ver todas las actividades →', 'href' => '/actividades/'],
                ]],
                ['heading' => 'Rutas', 'links' => [
                    ['label' => 'Ruta del Chaco en 3 días',     'href' => '/viajes/ruta-del-chaco-3-dias/'],
                    ['label' => 'Fin de semana en Encarnación', 'href' => '/viajes/fin-de-semana-en-encarnacion/'],
                    ['label' => 'Misiones Jesuíticas',          'href' => '/viajes/escapada-a-las-misiones-jesuiticas/'],
                    ['label' => 'Ver todos los viajes →',       'href' => '/viajes/'],
                ]],
                ['heading' => 'Guías', 'links' => [
                    ['label' => 'Destinos imprescindibles',     'href' => '/paraguay-destinos-imprescindibles-2026/'],
                    ['label' => 'Destinos imperdibles 2026',    'href' => '/destinos-imperdibles-2026/'],
                    ['label' => 'Preguntas frecuentes',         'href' => '/faq/'],
                    ['label' => 'Blog de viajes →',             'href' => '/blog/'],
                ]],
            ],
            'cta' => ['heading' => 'Armamos tu viaje', 'text' => 'Contanos fechas y presupuesto y te respondemos por WhatsApp.'],
        ],
        // Mega menu, same shape as Servicios: four columns of at most 8 links, then the CTA card.
        ['label' => 'Actividades',  'href' => '/actividades/',
            'columns' => [
                ['heading' => 'Saltos y agua', 'links' => [
                    ['label' => 'Saltos del Monday',       'href' => '/actividades/saltos-del-monday/'],
                    ['label' => 'Salto Suizo y Ybytyruzú', 'href' => '/actividades/salto-suizo-ybytyruzu/'],
                    ['label' => 'Salto Cristal',           'href' => '/actividades/salto-cristal/'],
                    ['label' => 'Laguna Blanca',           'href' => '/actividades/laguna-blanca/'],
                ]],
                ['heading' => 'Cerros y Chaco', 'links' => [
                    ['label' => 'Cerro Tres Kandú',  'href' => '/actividades/cerro-tres-kandu/'],
                    ['label' => 'Chaco Paraguayo',   'href' => '/actividades/chaco-paraguayo/'],
                ]],
                ['heading' => 'Historia y cultura', 'links' => [
                    ['label' => 'Misiones Jesuíticas',     'href' => '/actividades/misiones-jesuiticas-trinidad-jesus/'],
                    ['label' => 'Itaipú y Hernandarias',   'href' => '/actividades/itaipu-hernandarias/'],
                ]],
                ['heading' => 'Lago y costanera', 'links' => [
                    ['label' => 'Lago Ypacaraí',               'href' => '/actividades/lago-ypacarai-san-bernardino/'],
                    ['label' => 'Areguá',                      'href' => '/actividades/aregua/'],
                    ['label' => 'Costanera de Encarnación',    'href' => '/actividades/encarnacion-costanera/'],
                    ['label' => 'Ver todas las actividades →', 'href' => '/actividades/'],
                ]],
            ],
            'cta' => ['heading' => 'Armamos tu viaje', 'text' => 'Contanos fechas y presupuesto y te respondemos por WhatsApp.'],
        ],
        ['label' => 'Viajes',       'href' => '/viajes/', 'children' => [
            ['label' => 'Ruta del Chaco en 3 días',     'href' => '/viajes/ruta-del-chaco-3-dias/'],
            ['label' => 'Fin de semana en Encarnación', 'href' => '/viajes/fin-de-semana-en-encarnacion/'],
            ['label' => 'Misiones Jesuíticas',          'href' => '/viajes/escapada-a-las-misiones-jesuiticas/'],
        ]],
        ['label' => 'Blog',         'href' => '/blog/'],
        ['label' => 'FAQ',          'href' => '/faq/'],
        ['label' => 'Nosotros',     'href' => '/nosotros/'],
        ['label' => 'Contacto',     'href' => '/contacto/'],
    ],
    'footer_nav' => [
        ['label' => 'Inicio',                  'href' => '/'],
        ['label' => 'Servicios',               'href' => '/servicios/'],
        ['label' => 'Agencia de Viaje',        'href' => '/agencia-de-viaje/'],
        ['label' => 'Asistencia Personalizada','href' => '/asistencia-personalizada/'],
        ['label' => 'Gestión de Visas',        'href' => '/gestion-de-visas/'],
        ['label' => 'Traslados',               'href' => '/traslados/'],
        ['label' => 'Vacaciones',              'href' => '/vacaciones/'],
        ['label' => 'Blog',                    'href' => '/blog/'],
        ['label' => 'FAQ',                     'href' => '/faq/'],
        ['label' => 'Nosotros',                'href' => '/nosotros/'],
        ['label' => 'Contacto',                'href' => '/contacto/'],
    ],

    // tools/verify.php opt-ins: guide-page checks (answer box, FAQ, related links, lead form) and
    // one unique focus keyword per URL. Read by the tool only; the engine ignores it.
    'verify' => ['guides' => true, 'keywords' => true],

    'types'      => ['page', 'service', 'post', 'news', 'trip', 'activity'],
    'type_paths' => [
        'page'     => '/',
        'service'  => '/',
        'post'     => '/blog/',
        'news'     => '/novedades/',
        'trip'     => '/viajes/',
        'activity' => '/actividades/',
    ],

    'hubs' => [
        '/servicios/' => [
            'type'        => 'service',
            'nav_label'   => 'Servicios',
            'hero'        => '/assets/img/camino-rural-rio-atardecer-paraguay.jpg',
            'hero_alt'    => 'Vista aérea al atardecer de un camino de tierra roja entre campos verdes, un pueblo con iglesia y un río que refleja el sol en Paraguay.',
            'keyword'     => 'servicios de viaje en Paraguay',
            'title'       => 'Servicios de Viaje en Paraguay',
            'description' => 'Servicios de viaje en Paraguay: agencia a medida, traslados privados, asistencia 24/7, vacaciones y gestión de visas. Elegí el que necesitás y consultanos.',
            'show_faq'    => true,
        ],
        '/blog/' => [
            'type'        => 'post',
            'nav_label'   => 'Blog',
            'hero'        => '/assets/img/diario-de-viaje-mapa-terere-paraguay.jpg',
            'hero_alt'    => 'Un diario de viaje abierto, un mapa plegado y una guampa de tereré sobre una mesa de madera junto a una ventana con vista a un lapacho florecido.',
            'keyword'     => 'blog de viajes por Paraguay',
            'title'       => 'Blog de Viajes por Paraguay',
            'description' => 'Blog de viajes por Paraguay: destinos, rutas, cuándo ir y consejos prácticos para armar tu escapada, en guías escritas por quienes recorren el país.',
            'per_page'    => 12,
        ],
        '/novedades/' => [
            'type'        => 'news',
            'nav_label'   => 'Novedades',
            'keyword'     => 'novedades de turismo en Paraguay',
            'title'       => 'Novedades de Turismo en Paraguay',
            'description' => 'Novedades de turismo en Paraguay: anuncios y apariciones en medios de Viaje.com.py, con lo que se dice del país y cómo armar tu próxima escapada.',
        ],
        '/viajes/' => [
            'type'        => 'trip',
            'nav_label'   => 'Viajes',
            'hero'        => '/assets/img/posada-galeria-hamaca-atardecer-paraguay.jpg',
            'hero_alt'    => 'La galería de una posada rural con una hamaca y un tereré sobre una mesa, frente a colinas verdes y un atardecer anaranjado.',
            'keyword'     => 'viajes por Paraguay',
            'title'       => 'Viajes por Paraguay',
            'description' => 'Viajes por Paraguay de varios días, armados a medida con itinerario, traslados y acompañamiento local. Elegí una ruta y ajustamos fechas y paradas con vos.',
        ],
        '/actividades/' => [
            'type'        => 'activity',
            'nav_label'   => 'Actividades',
            'hero'        => '/assets/img/mirador-ybytyruzu-amanecer-senderistas.jpg',
            'hero_alt'    => 'Dos senderistas de espaldas en un mirador rocoso del Ybytyruzú al amanecer, con niebla en los valles y una cascada a lo lejos.',
            'keyword'     => 'qué hacer en Paraguay',
            'title'       => 'Qué hacer en Paraguay: actividades y destinos',
            'description' => 'Qué hacer en Paraguay: saltos, misiones jesuíticas, el Chaco, lagos y costaneras, con cuándo ir y cómo llegar. Elegí tu destino y pedinos la ruta armada.',
        ],
    ],

    // plan §5 — the URL contract. Exact paths, one hop, no chains.
    'redirects' => [
        '/paquetes/'                        => '/servicios/',
        '/paquete-individual/'              => '/servicios/',
        '/servicio-unico/'                  => '/servicios/',
        '/category/uncategorized/'          => '/blog/',
        '/author/yanina/'                   => '/nosotros/',
        '/author/marklundmailgmail-com/'    => '/nosotros/',
        '/wp-sitemap.xml'                   => '/sitemap.xml',
        '/wp-sitemap-posts-post-1.xml'      => '/sitemap.xml',
        '/wp-sitemap-posts-page-1.xml'      => '/sitemap.xml',
        '/wp-sitemap-taxonomies-category-1.xml' => '/sitemap.xml',
        '/wp-sitemap-users-1.xml'           => '/sitemap.xml',
    ],
    // Any other wp-sitemap-*.xml variant WordPress may have emitted (KNOWN-ISSUES #1).
    'redirect_patterns' => ['#^/wp-sitemap[^/]*\.xml$#' => '/sitemap.xml'],
    'gone' => ['/elementor-9/', '/hello-world/'],

    'leads' => [
        'to'             => 'hola@viaje.com.py',
        'subject_prefix' => '[viaje.com.py] ',
        'topics'         => [],   // empty ⇒ the enabled service titles
        'vendercrm'      => ['endpoint' => null, 'tenant_key' => null, 'source' => 'viaje-web'],
    ],

    'home' => [
        'featured_posts' => 3,
        'faq_tags'       => ['home'],
        'services_order' => ['agencia-de-viaje', 'asistencia-personalizada', 'traslados', 'vacaciones', 'gestion-de-visas'],
    ],
];
