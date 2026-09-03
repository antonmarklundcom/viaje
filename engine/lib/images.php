<?php
declare(strict_types=1);

/**
 * Image resolution, responsive variants and admin uploads (spec §9).
 */
final class Images
{
    public const WIDTHS   = [480, 960, 1600];
    public const QUALITY  = 82;
    public const MAX_BYTES = 12 * 1024 * 1024;
    private const ALLOWED = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];

    /** Map a site-absolute image path to a file on disk, or null. */
    public static function localFile(string $src): ?string
    {
        if ($src === '') {
            return null;
        }
        $base = (string)Config::v('base_url', '');
        if ($base !== '' && str_starts_with($src, $base)) {
            $src = substr($src, strlen($base));
        }
        if (preg_match('#^https?://#i', $src)) {
            return null;
        }
        $src = (string)parse_url($src, PHP_URL_PATH);
        if ($src === '' || $src[0] !== '/' || str_contains($src, '..')) {
            return null;
        }
        $candidates = [];
        if (str_starts_with($src, '/media/') || str_starts_with($src, '/assets/')) {
            $candidates[] = VJ_SITE . $src;
        }
        $candidates[] = VJ_SITE . '/static' . $src;
        if (defined('VJ_ROOT')) {
            $candidates[] = VJ_ROOT . $src;
        }
        foreach ($candidates as $file) {
            if (is_file($file)) {
                return $file;
            }
        }
        return null;
    }

    /** @return array{0:int,1:int}|null */
    public static function dimensions(string $src): ?array
    {
        static $cache = [];
        if (array_key_exists($src, $cache)) {
            return $cache[$src];
        }
        $file = self::localFile($src);
        $out  = null;
        if ($file !== null) {
            $info = @getimagesize($file);
            if (is_array($info) && $info[0] > 0) {
                $out = [(int)$info[0], (int)$info[1]];
            }
        }
        return $cache[$src] = $out;
    }

    /** srcset string over existing `-<w>.webp` variants next to the source, or null. */
    public static function webpSrcset(string $src): ?string
    {
        $file = self::localFile($src);
        if ($file === null || str_ends_with(strtolower($src), '.webp')) {
            return null;
        }
        $dir  = dirname($src);
        $stem = pathinfo($src, PATHINFO_FILENAME);
        $parts = [];
        foreach (self::WIDTHS as $w) {
            $variant = $dir . '/' . $stem . '-' . $w . '.webp';
            if (self::localFile($variant) !== null) {
                $parts[] = $variant . ' ' . $w . 'w';
            }
        }
        return $parts === [] ? null : implode(', ', $parts);
    }

    /**
     * <picture> markup for a hero or card image.
     * @param array<string,string|null> $attrs extra <img> attributes
     */
    public static function picture(string $src, string $alt, array $attrs = [], string $sizes = '100vw'): string
    {
        if ($src === '') {
            return '';
        }
        $a = ['src' => $src, 'alt' => $alt] + $attrs;
        $dim = self::dimensions($src);
        if ($dim !== null && !isset($a['width'])) {
            $a['width']  = (string)$dim[0];
            $a['height'] = (string)$dim[1];
        }
        $a['loading']  ??= 'lazy';
        $a['decoding'] ??= 'async';
        $img = '<img';
        foreach ($a as $k => $v) {
            if ($v === null) {
                continue;
            }
            $img .= ' ' . $k . '="' . e($v) . '"';
        }
        $img .= '>';

        $srcset = self::webpSrcset($src);
        if ($srcset === null) {
            return $img;
        }
        return '<picture><source type="image/webp" srcset="' . e($srcset) . '" sizes="' . e($sizes) . '">' . $img . '</picture>';
    }

    /* ------------------------------------------------------------- upload */

    /**
     * Validate and store an uploaded image.
     *
     * @param array{name?:string,type?:string,tmp_name?:string,error?:int,size?:int} $file
     * @return array{ok:bool,error?:string,path?:string,markdown?:string,variants?:list<string>}
     */
    public static function upload(array $file, string $alt, string $nameHint = ''): array
    {
        $err = (int)($file['error'] ?? UPLOAD_ERR_NO_FILE);
        if ($err !== UPLOAD_ERR_OK) {
            return ['ok' => false, 'error' => I18n::t($err === UPLOAD_ERR_INI_SIZE || $err === UPLOAD_ERR_FORM_SIZE ? 'err_upload_size' : 'err_upload')];
        }
        $tmp = (string)($file['tmp_name'] ?? '');
        if ($tmp === '' || !is_file($tmp)) {
            return ['ok' => false, 'error' => I18n::t('err_upload')];
        }
        if ((int)($file['size'] ?? 0) > self::MAX_BYTES) {
            return ['ok' => false, 'error' => I18n::t('err_upload_size')];
        }
        if (trim($alt) === '') {
            return ['ok' => false, 'error' => I18n::t('err_upload_alt')];
        }

        $mime = false;
        if (class_exists('finfo')) {
            $fi   = new finfo(FILEINFO_MIME_TYPE);
            $mime = $fi->file($tmp);
        }
        $info = @getimagesize($tmp);
        if (!is_array($info) || !isset(self::ALLOWED[(string)$mime]) || !isset($info['mime']) || $info['mime'] !== $mime) {
            return ['ok' => false, 'error' => I18n::t('err_upload_type')];
        }
        $ext = self::ALLOWED[(string)$mime];

        $stem = Util::slugify($nameHint !== '' ? $nameHint : pathinfo((string)($file['name'] ?? 'image'), PATHINFO_FILENAME));
        if ($stem === '') {
            $stem = 'imagen';
        }
        $rel = '/media/' . date('Y') . '/' . date('m');
        $dir = VJ_SITE . $rel;
        if (!Util::mkdirp($dir)) {
            return ['ok' => false, 'error' => I18n::t('err_upload')];
        }
        $slug = $stem;
        $n    = 1;
        while (is_file($dir . '/' . $slug . '.' . $ext)) {
            $slug = $stem . '-' . (++$n);
        }
        $target   = $dir . '/' . $slug . '.' . $ext;
        $variants = [];

        $src = self::loadGd($tmp, (string)$mime);
        if ($src === null) {
            // No GD (or unreadable by GD): store the original untouched, spec §9.
            if (!@move_uploaded_file($tmp, $target) && !@copy($tmp, $target)) {
                return ['ok' => false, 'error' => I18n::t('err_upload')];
            }
            Util::log('GD unavailable; stored original without variants: ' . $target);
        } else {
            self::saveGd($src, $target, $ext);
            $w = imagesx($src);
            foreach (self::WIDTHS as $width) {
                if ($width > $w && $variants !== []) {
                    continue;
                }
                $scaled = $width >= $w ? $src : imagescale($src, $width);
                if ($scaled === false) {
                    continue;
                }
                $vfile = $dir . '/' . $slug . '-' . min($width, $w) . '.webp';
                if (function_exists('imagewebp') && @imagewebp($scaled, $vfile, self::QUALITY)) {
                    $variants[] = $rel . '/' . basename($vfile);
                }
                if ($scaled !== $src) {
                    imagedestroy($scaled);
                }
                if ($width >= $w) {
                    break;
                }
            }
            imagedestroy($src);
        }
        @chmod($target, 0664);

        $path = $rel . '/' . $slug . '.' . $ext;
        return [
            'ok'       => true,
            'path'     => $path,
            'markdown' => '![' . str_replace([']', '['], '', trim($alt)) . '](' . $path . ')',
            'variants' => $variants,
        ];
    }

    private static function loadGd(string $file, string $mime): ?GdImage
    {
        if (!function_exists('imagecreatefromjpeg')) {
            return null;
        }
        $im = match ($mime) {
            'image/jpeg' => @imagecreatefromjpeg($file),
            'image/png'  => @imagecreatefrompng($file),
            'image/webp' => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($file) : false,
            default      => false,
        };
        return $im instanceof GdImage ? $im : null;
    }

    private static function saveGd(GdImage $im, string $target, string $ext): void
    {
        switch ($ext) {
            case 'png':
                imagealphablending($im, false);
                imagesavealpha($im, true);
                @imagepng($im, $target, 6);
                break;
            case 'webp':
                @imagewebp($im, $target, self::QUALITY);
                break;
            default:
                @imagejpeg($im, $target, self::QUALITY);
        }
    }

    /** Every file under site/media, newest first. @return list<array{path:string,size:int,mtime:int}> */
    public static function listMedia(int $limit = 300): array
    {
        $root = VJ_SITE . '/media';
        if (!is_dir($root)) {
            return [];
        }
        $out = [];
        $it  = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));
        foreach ($it as $f) {
            /** @var SplFileInfo $f */
            if (!$f->isFile() || !preg_match('/\.(jpe?g|png|webp)$/i', $f->getFilename())) {
                continue;
            }
            if (preg_match('/-(?:' . implode('|', self::WIDTHS) . ')\.webp$/i', $f->getFilename())) {
                continue;
            }
            $out[] = [
                'path'  => '/media' . str_replace('\\', '/', substr($f->getPathname(), strlen($root))),
                'size'  => (int)$f->getSize(),
                'mtime' => (int)$f->getMTime(),
            ];
        }
        usort($out, static fn(array $a, array $b): int => $b['mtime'] <=> $a['mtime']);
        return array_slice($out, 0, $limit);
    }
}
