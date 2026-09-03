<?php
declare(strict_types=1);

/**
 * Site configuration loading and validation.
 * config.php holds public values; config.local.php holds secrets and is git-ignored.
 */
final class Config
{
    /** Keys that must exist (dot notation) or boot fails with a clear message. */
    private const REQUIRED = [
        'domain', 'base_url', 'lang', 'html_lang', 'site_name',
        'contact.phone_display', 'contact.phone_e164', 'contact.whatsapp_e164', 'contact.email',
        'types', 'type_paths',
    ];

    private static ?array $cfg = null;

    public static function load(string $siteDir): array
    {
        $main = $siteDir . '/config.php';
        if (!is_file($main)) {
            throw new RuntimeException('Missing site config: ' . $main);
        }
        $cfg = require $main;
        if (!is_array($cfg)) {
            throw new RuntimeException('Site config must return an array: ' . $main);
        }

        $localFile = $siteDir . '/config.local.php';
        if (is_file($localFile)) {
            $local = require $localFile;
            if (is_array($local)) {
                $cfg = array_replace_recursive($cfg, $local);
            }
        }

        $cfg = self::withDefaults($cfg);
        self::validate($cfg);

        $cfg['base_url'] = rtrim((string)$cfg['base_url'], '/');
        self::$cfg = $cfg;
        return $cfg;
    }

    public static function get(): array
    {
        if (self::$cfg === null) {
            throw new RuntimeException('Config not loaded');
        }
        return self::$cfg;
    }

    /** Read a dot-path out of the loaded config. */
    public static function v(string $path, mixed $default = null): mixed
    {
        $node = self::$cfg ?? [];
        foreach (explode('.', $path) as $part) {
            if (!is_array($node) || !array_key_exists($part, $node)) {
                return $default;
            }
            $node = $node[$part];
        }
        return $node ?? $default;
    }

    private static function withDefaults(array $c): array
    {
        $d = [
            'timezone'         => 'America/Asuncion',
            'force_https'      => true,
            'force_host'       => null,
            'locale_og'        => 'es_ES',
            'title_suffix'     => '',
            'tagline'          => '',
            'staging'          => false,
            'debug'            => false,
            'theme_color'      => '#0f766e',
            'default_og_image' => null,
            'head_extra'       => '',
            'body_extra'       => '',
            'socials'          => [],
            'schema'           => [],
            'labels'           => [],
            'nav'              => [],
            'footer_nav'       => [],
            'hubs'             => [],
            'redirects'        => [],
            'gone'             => [],
            'analytics'        => ['ga4' => null],
            'home'             => [],
            'author_default'   => ['name' => '', 'type' => 'Organization'],
            'admin_password_hash' => null,
            'preview_secret'   => null,
            'leads'            => [],
            'per_page'         => 12,
            'footer_blurb'     => '',
        ];
        $c = array_replace_recursive($d, $c);

        $c['leads'] = array_replace_recursive([
            'to'             => $c['contact']['email'] ?? '',
            'subject_prefix' => '[' . ($c['domain'] ?? 'site') . '] ',
            'topics'         => [],
            'vendercrm'      => ['endpoint' => null, 'tenant_key' => null, 'source' => 'web-form'],
        ], is_array($c['leads']) ? $c['leads'] : []);

        return $c;
    }

    private static function validate(array $c): void
    {
        $missing = [];
        foreach (self::REQUIRED as $key) {
            $node = $c;
            foreach (explode('.', $key) as $part) {
                if (!is_array($node) || !array_key_exists($part, $node) || $node[$part] === null || $node[$part] === '') {
                    $missing[] = $key;
                    continue 2;
                }
                $node = $node[$part];
            }
        }
        if ($missing) {
            throw new RuntimeException('Site config is missing required key(s): ' . implode(', ', $missing));
        }
        if (!preg_match('#^https?://[^/\s]+$#', (string)$c['base_url'])) {
            throw new RuntimeException("Config 'base_url' must be an absolute URL with no trailing slash, got: " . (string)$c['base_url']);
        }
        if (!is_array($c['types']) || $c['types'] === []) {
            throw new RuntimeException("Config 'types' must be a non-empty list of content types.");
        }
        foreach ($c['types'] as $t) {
            if (!isset($c['type_paths'][$t])) {
                throw new RuntimeException("Config 'type_paths' has no entry for enabled type '" . (string)$t . "'.");
            }
            $p = (string)$c['type_paths'][$t];
            if (!Util::isSafePath($p)) {
                throw new RuntimeException("Config 'type_paths.$t' must be an absolute path starting and ending with '/', got: $p");
            }
        }
        foreach (array_keys($c['hubs']) as $hubPath) {
            if (!Util::isSafePath((string)$hubPath)) {
                throw new RuntimeException("Config 'hubs' key must be an absolute path starting and ending with '/', got: " . (string)$hubPath);
            }
        }
    }

    /** True when the admin can be used at all. */
    public static function adminConfigured(): bool
    {
        $h = self::v('admin_password_hash');
        return is_string($h) && strlen($h) > 20;
    }

    /** Secret used to sign preview links and lead-form timestamps. Falls back to a per-install file. */
    public static function secret(): string
    {
        $s = self::v('preview_secret');
        if (is_string($s) && strlen($s) >= 16) {
            return $s;
        }
        $file = VJ_SITE . '/data/.secret';
        if (is_file($file)) {
            $v = trim((string)file_get_contents($file));
            if ($v !== '') {
                return $v;
            }
        }
        $v = bin2hex(random_bytes(32));
        Util::mkdirp(dirname($file));
        Util::atomicWrite($file, $v);
        return $v;
    }
}
