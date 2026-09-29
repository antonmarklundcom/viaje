<?php
declare(strict_types=1);

/**
 * viaje.com.py — site configuration.
 * Secrets (admin password hash, preview secret, CRM key, GA4) live in config.local.php.
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
    'head_extra' => '<link rel="preconnect" href="https://fonts.googleapis.com">'
        . '<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>'
        . '<link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,500;9..144,600&display=swap" rel="stylesheet">',
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
        ['label' => 'Actividades',  'href' => '/actividades/', 'children' => [
            ['label' => 'Saltos del Monday',        'href' => '/actividades/saltos-del-monday/'],
            ['label' => 'Salto Suizo y Ybytyruzú',  'href' => '/actividades/salto-suizo-ybytyruzu/'],
            ['label' => 'Lago Ypacaraí',            'href' => '/actividades/lago-ypacarai-san-bernardino/'],
            ['label' => 'Misiones Jesuíticas',      'href' => '/actividades/misiones-jesuiticas-trinidad-jesus/'],
            ['label' => 'Costanera de Encarnación', 'href' => '/actividades/encarnacion-costanera/'],
            ['label' => 'Chaco Paraguayo',          'href' => '/actividades/chaco-paraguayo/'],
        ]],
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
            'title'       => 'Servicios de Viaje en Paraguay',
            'description' => 'Traslados, asistencia personalizada, gestión de visas y vacaciones a medida por todo Paraguay.',
            'show_faq'    => true,
        ],
        '/blog/' => [
            'type'        => 'post',
            'nav_label'   => 'Blog',
            'hero'        => '/assets/img/diario-de-viaje-mapa-terere-paraguay.jpg',
            'hero_alt'    => 'Un diario de viaje abierto, un mapa plegado y una guampa de tereré sobre una mesa de madera junto a una ventana con vista a un lapacho florecido.',
            'title'       => 'Blog de Viajes por Paraguay',
            'description' => 'Destinos, rutas y consejos prácticos para recorrer Paraguay durante todo el año.',
            'per_page'    => 12,
        ],
        '/novedades/' => [
            'type'        => 'news',
            'nav_label'   => 'Novedades',
            'title'       => 'Novedades',
            'description' => 'Anuncios y apariciones en medios de Viaje.com.py.',
        ],
        '/viajes/' => [
            'type'        => 'trip',
            'nav_label'   => 'Viajes',
            'hero'        => '/assets/img/posada-galeria-hamaca-atardecer-paraguay.jpg',
            'hero_alt'    => 'La galería de una posada rural con una hamaca y un tereré sobre una mesa, frente a colinas verdes y un atardecer anaranjado.',
            'title'       => 'Viajes por Paraguay',
            'description' => 'Rutas de varios días armadas a medida, con itinerario, traslados y acompañamiento local.',
        ],
        '/actividades/' => [
            'type'        => 'activity',
            'nav_label'   => 'Actividades',
            'hero'        => '/assets/img/mirador-ybytyruzu-amanecer-senderistas.jpg',
            'hero_alt'    => 'Dos senderistas de espaldas en un mirador rocoso del Ybytyruzú al amanecer, con niebla en los valles y una cascada a lo lejos.',
            'title'       => 'Actividades y destinos en Paraguay',
            'description' => 'Qué hacer en Paraguay: saltos, misiones jesuíticas, Chaco, lagos y costaneras.',
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

    'analytics' => ['ga4' => null],

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
