<?php
declare(strict_types=1);

/**
 * Version history for admin-edited content (the server owns content/, so git has no history of
 * it). Before every admin write, delete or restore, the file about to be replaced is copied to
 * site/data/history/<type>/<slug>/<YYYYmmdd-HHMMSS-micro>.<ext>; the newest KEEP per page stay.
 * `type` is a content type (markdown) or `data` for content/data/<slug>.json.
 */
final class History
{
    public const KEEP = 20;

    private const ID_RE = '/^\d{8}-\d{6}-\d{6}\.(md|json)$/';

    public static function root(): string
    {
        return VJ_SITE . '/data/history';
    }

    public static function dir(string $type, string $slug): string
    {
        return self::root() . '/' . $type . '/' . $slug;
    }

    /** The live file for a history key. */
    public static function target(string $type, string $slug): string
    {
        return $type === 'data'
            ? VJ_SITE . '/content/data/' . $slug . '.json'
            : Types::dir($type) . '/' . $slug . '.md';
    }

    /** The tracked seed copy of the same file (repo content-seed/), or null when there is none. */
    public static function seed(string $type, string $slug): ?string
    {
        $rel  = $type === 'data' ? 'data/' . $slug . '.json' : Types::folder($type) . '/' . $slug . '.md';
        $file = VJ_SITE . '/content-seed/' . $rel;
        return is_file($file) ? $file : null;
    }

    public static function valid(string $type, string $slug): bool
    {
        return ($type === 'data' || Types::enabled($type)) && Util::isSlug($slug);
    }

    /**
     * Keep a copy of the file currently at $file (nothing to keep when it does not exist, and no
     * duplicate when it is identical to the newest version). False only when the copy failed.
     */
    public static function snapshot(string $type, string $slug, ?string $file = null): bool
    {
        $file ??= self::target($type, $slug);
        if (!self::valid($type, $slug) || !is_file($file)) {
            return true;
        }
        $body = @file_get_contents($file);
        if ($body === false) {
            return false;
        }
        $newest = self::versions($type, $slug)[0] ?? null;
        if ($newest !== null && @file_get_contents(self::dir($type, $slug) . '/' . $newest['id']) === $body) {
            return true;
        }
        [$usec, $sec] = explode(' ', microtime());
        $id = date('Ymd-His', (int)$sec) . '-' . sprintf('%06d', (int)round((float)$usec * 1e6) % 1000000)
            . '.' . ($type === 'data' ? 'json' : 'md');
        if (!Util::atomicWrite(self::dir($type, $slug) . '/' . $id, $body)) {
            Util::log('History: cannot write ' . $type . '/' . $slug . '/' . $id);
            return false;
        }
        foreach (array_slice(self::versions($type, $slug), self::KEEP) as $old) {
            @unlink(self::dir($type, $slug) . '/' . $old['id']);
        }
        return true;
    }

    /**
     * Versions, newest first.
     * @return list<array{id:string,time:string,size:int,title:string}>
     */
    public static function versions(string $type, string $slug): array
    {
        if (!self::valid($type, $slug)) {
            return [];
        }
        $out = [];
        foreach (glob(self::dir($type, $slug) . '/*') ?: [] as $f) {
            $id = basename($f);
            if (!preg_match(self::ID_RE, $id)) {
                continue;
            }
            $title = '';
            if ($type !== 'data') {
                [$fm] = Frontmatter::parseFile((string)@file_get_contents($f));
                $title = (string)($fm['title'] ?? '');
            }
            $out[] = [
                'id'    => $id,
                'time'  => substr($id, 0, 4) . '-' . substr($id, 4, 2) . '-' . substr($id, 6, 2) . ' '
                         . substr($id, 9, 2) . ':' . substr($id, 11, 2) . ':' . substr($id, 13, 2),
                'size'  => (int)@filesize($f),
                'title' => $title,
            ];
        }
        usort($out, static fn(array $a, array $b): int => strcmp($b['id'], $a['id']));
        return $out;
    }

    /** Path of one stored version, or null. */
    public static function file(string $type, string $slug, string $id): ?string
    {
        if (!self::valid($type, $slug) || !preg_match(self::ID_RE, $id)) {
            return null;
        }
        $f = self::dir($type, $slug) . '/' . $id;
        return is_file($f) ? $f : null;
    }

    /**
     * Put $source back as the live file, snapshotting what is there now first (so a restore can
     * itself be undone). The caller purges the page cache.
     */
    public static function restoreFrom(string $type, string $slug, string $source): bool
    {
        $body = @file_get_contents($source);
        if ($body === false || !self::snapshot($type, $slug)) {
            return false;
        }
        $target = self::target($type, $slug);
        Util::mkdirp(dirname($target));
        return Util::atomicWrite($target, $body);
    }

    /**
     * Pages of a type that are gone from content/ but still have history (deleted in the admin).
     * @return list<array{slug:string,versions:int,latest:string}>
     */
    public static function deleted(string $type): array
    {
        $out = [];
        foreach (glob(self::root() . '/' . $type . '/*', GLOB_ONLYDIR) ?: [] as $dir) {
            $slug = basename($dir);
            if (!Util::isSlug($slug) || is_file(self::target($type, $slug))) {
                continue;
            }
            $v = self::versions($type, $slug);
            if ($v !== []) {
                $out[] = ['slug' => $slug, 'versions' => count($v), 'latest' => $v[0]['time']];
            }
        }
        return $out;
    }
}
