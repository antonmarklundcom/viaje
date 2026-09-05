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
        ['label' => 'Servicios',    'href' => '/servicios/'],
        ['label' => 'Actividades',  'href' => '/actividades/'],
        ['label' => 'Viajes',       'href' => '/viajes/'],
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
            'title'       => 'Servicios de Viaje en Paraguay',
            'description' => 'Traslados, asistencia personalizada, gestión de visas y vacaciones a medida por todo Paraguay.',
            'show_faq'    => true,
        ],
        '/blog/' => [
            'type'        => 'post',
            'nav_label'   => 'Blog',
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
            'title'       => 'Viajes por Paraguay',
            'description' => 'Rutas de varios días armadas a medida, con itinerario, traslados y acompañamiento local.',
        ],
        '/actividades/' => [
            'type'        => 'activity',
            'nav_label'   => 'Actividades',
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
        '/wp-sitemap.xml'                   => '/sitemap.xml',
        '/wp-sitemap-posts-post-1.xml'      => '/sitemap.xml',
        '/wp-sitemap-posts-page-1.xml'      => '/sitemap.xml',
        '/wp-sitemap-taxonomies-category-1.xml' => '/sitemap.xml',
        '/wp-sitemap-users-1.xml'           => '/sitemap.xml',
    ],
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
