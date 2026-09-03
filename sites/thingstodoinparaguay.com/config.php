<?php
declare(strict_types=1);

/**
 * thingstodoinparaguay.com — site configuration.
 * Net-new English site on the same engine (plan §3.2, path B). Phase 3 fills the content.
 */

return [
    'domain'       => 'thingstodoinparaguay.com',
    'base_url'     => 'https://thingstodoinparaguay.com',
    'timezone'     => 'America/Asuncion',
    'force_https'  => true,
    'force_host'   => 'thingstodoinparaguay.com',
    'lang'         => 'en',
    'html_lang'    => 'en',
    'locale_og'    => 'en_US',
    'site_name'    => 'Things to do in Paraguay',
    'title_suffix' => ' | Things to do in Paraguay',
    'tagline'      => 'An English-language guide to travelling in Paraguay: waterfalls, Jesuit missions, the Chaco and the river towns.',
    'theme_color'  => '#0F5C4A',
    'staging'      => false,
    'debug'        => false,
    'default_og_image' => null,
    'footer_blurb' => 'An independent English guide to Paraguay, written by the team behind Viaje.com.py in Asunción.',

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
        'hours'                 => 'Mon–Sat 08:00–19:00',
        'whatsapp_default_text' => 'Hi, I found you on thingstodoinparaguay.com and I would like to ask about',
    ],

    'socials' => ['instagram' => null, 'facebook' => null, 'tiktok' => null],

    'schema' => [
        'type'        => 'TravelAgency',
        'logo'        => '/assets/logo.svg',
        'founder'     => 'Anton Marklund',
        'area_served' => 'Paraguay',
        'price_range' => '$$',
    ],
    'author_default' => ['name' => 'Things to do in Paraguay', 'type' => 'Organization'],

    'nav' => [
        ['label' => 'Home',          'href' => '/'],
        ['label' => 'Things to do',  'href' => '/things-to-do/'],
        ['label' => 'Trips',         'href' => '/trips/'],
        ['label' => 'Blog',          'href' => '/blog/'],
        ['label' => 'About',         'href' => '/about/'],
        ['label' => 'Contact',       'href' => '/contact/'],
    ],
    'footer_nav' => [
        ['label' => 'Home',         'href' => '/'],
        ['label' => 'Things to do', 'href' => '/things-to-do/'],
        ['label' => 'Trips',        'href' => '/trips/'],
        ['label' => 'Blog',         'href' => '/blog/'],
        ['label' => 'FAQ',          'href' => '/faq/'],
        ['label' => 'About',        'href' => '/about/'],
        ['label' => 'Contact',      'href' => '/contact/'],
    ],

    'types'      => ['page', 'service', 'post', 'news', 'trip', 'activity'],
    'type_paths' => [
        'page'     => '/',
        'service'  => '/services/',
        'post'     => '/blog/',
        'news'     => '/news/',
        'trip'     => '/trips/',
        'activity' => '/things-to-do/',
    ],

    'hubs' => [
        '/things-to-do/' => [
            'type'        => 'activity',
            'nav_label'   => 'Things to do',
            'title'       => 'Things to do in Paraguay',
            'description' => 'Waterfalls, Jesuit missions, the Chaco, lakes and river towns — what to see in Paraguay and when to go.',
        ],
        '/trips/' => [
            'type'        => 'trip',
            'nav_label'   => 'Trips',
            'title'       => 'Trips around Paraguay',
            'description' => 'Multi-day routes through Paraguay, with day-by-day itineraries and local transport arranged for you.',
        ],
        '/blog/' => [
            'type'        => 'post',
            'nav_label'   => 'Blog',
            'title'       => 'Paraguay travel blog',
            'description' => 'Practical writing about travelling in Paraguay: seasons, roads, costs and what is actually worth the drive.',
            'per_page'    => 12,
        ],
        '/news/' => [
            'type'        => 'news',
            'nav_label'   => 'News',
            'title'       => 'News',
            'description' => 'Announcements and press mentions.',
        ],
        '/services/' => [
            'type'        => 'service',
            'nav_label'   => 'Services',
            'title'       => 'Services',
            'description' => 'How we help English-speaking visitors get around Paraguay.',
        ],
    ],

    // Net-new site: nothing to preserve yet. The phase-4 runbook re-checks the live
    // domain's sitemap before cutover (plan §3.2); if real indexed content shows up,
    // its own URL contract lands here first.
    'redirects' => [],
    'gone'      => [],

    'analytics' => ['ga4' => null],

    'leads' => [
        'to'             => 'hola@viaje.com.py',
        'subject_prefix' => '[thingstodoinparaguay.com] ',
        'topics'         => ['Trip planning', 'Airport transfer', 'Guided day trip', 'General question'],
        'vendercrm'      => ['endpoint' => null, 'tenant_key' => null, 'source' => 'ttdp-web'],
    ],

    'home' => [
        'featured_posts' => 3,
        'faq_tags'       => ['home'],
        'services_order' => [],
    ],
];
