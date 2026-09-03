<?php
declare(strict_types=1);

/**
 * UI strings. engine/lang/<lang>.php provides the defaults; a site may override any
 * key through config['labels'].
 */
final class I18n
{
    private static array $strings = [];
    private static string $lang = 'es';

    public static function load(string $lang, array $overrides = []): void
    {
        $lang = preg_match('/^[a-z]{2}$/', $lang) ? $lang : 'es';
        $file = VJ_ENGINE . '/lang/' . $lang . '.php';
        if (!is_file($file)) {
            $file = VJ_ENGINE . '/lang/es.php';
            $lang = 'es';
        }
        $strings = require $file;
        self::$strings = array_replace(is_array($strings) ? $strings : [], $overrides);
        self::$lang    = $lang;
    }

    public static function lang(): string
    {
        return self::$lang;
    }

    /** @param array<string,string|int> $vars replaced as :name */
    public static function t(string $key, array $vars = []): string
    {
        $s = self::$strings[$key] ?? $key;
        foreach ($vars as $k => $v) {
            $s = str_replace(':' . $k, (string)$v, $s);
        }
        return $s;
    }

    public static function has(string $key): bool
    {
        return isset(self::$strings[$key]);
    }
}

/** Template shorthand. Returns the raw string; escape at the point of output. */
function t(string $key, array $vars = []): string
{
    return I18n::t($key, $vars);
}
