<?php
declare(strict_types=1);

/**
 * Copy to config.local.php on the server (it is git-ignored) and fill in.
 * Every key is optional: the public site works without this file, only the
 * admin and the optional integrations need it.
 *
 * Generate the password hash with:  php engine/bin/hash-password.php
 */

return [
    // Required for /admin/ to accept a login. Without it the admin shows a setup page.
    'admin_password_hash' => null,

    // Signs preview links and the lead form's timestamp. Any 32+ random chars.
    // When null the engine generates one into site/data/.secret on first use.
    'preview_secret' => null,

    'leads' => [
        'vendercrm' => [
            'endpoint'   => null,   // e.g. https://crm.example.com  (=> /api/v1/leads)
            'tenant_key' => null,   // X-Api-Key
        ],
    ],

    'analytics' => ['ga4' => null],  // e.g. 'G-XXXXXXXXXX'

    // Staging installs: noindex header + meta on every response, no analytics.
    'staging' => false,
    'debug'   => false,
];
