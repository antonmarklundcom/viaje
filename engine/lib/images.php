<?php
declare(strict_types=1);

/**
 * Upload validation, GD re-encoding and responsive WebP variants.
 * Every path that reaches the filesystem is validated against a strict allowlist first.
 */
final class Images
{
    public const MAX_BYTES = 12 * 1024 * 1024;
    public const WIDTHS    = [480, 960, 1600];
    public const QUALITY   = 82;

    private const ALLOWED_MIME = [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/webp' => 'webp',
    ];

    /**
     * Map an absolute site path to a file on disk, or null when it is not a path we serve.
     * Rejects traversal and anything outside the site/media, site/assets and document root trees.
     */
    public static function localPath(string $webPath): ?string
    {
        if ($webPath === '' || $webPath[0] !== '/' || str_contains($webPath, '..') || str_contains($webPath, "\0")) {
            return null;
        }
        $webPath = explode('?', $webPath)[0];
        if (!preg_match('#^/[A-Za-z0-9/._-]+$#', $webPath)) {
            return null;
        }
        if (str_starts_with($webPath, '/media/')) {
            $root = VJ_SITE . '/media';
            $file = $root . substr($webPath, 6);
        } elseif (str_starts_with($webPath, '/assets/')) {
            $root = VJ_SITE . '/assets';
            $file = $root . substr($webPath, 7);
        } else {
            $root = VJ_ROOT;
            $file = VJ_ROOT . $webPath;
        }
        if (!is_file($file)) {
            return null;
        }
        $real     = realpath($file);
        $realRoot = realpath($root);
        if ($real === false || $realRoot === false || !str_starts_with($real, $realRoot . DIRECTORY_SEPARATOR)) {
            return null;
        }
        return $real;
    }

    /** @return array{0:int,1:int}|null */
    public static function dimensions(string $webPath): ?array
    {
        $file = self::localPath($webPath);
        if ($file === null) {
            return null;
        }
        $info = @getimagesize($file);
        if ($info === false || (int)$info[0] < 1) {
            return null;
        }
        return [(int)$info[0], (int)$info[1]];
    }

    /** srcset string for the generated WebP variants sitting next to $webPath, or null. */
    public static function webpSrcset(string $webPath): ?string
    {
        if (!str_starts_with($webPath, '/media/')) {
            return null;
        }
        $dir  = dirname($webPath);
        $base = pathinfo($webPath, PATHINFO_FILENAME);
        $set  = [];
        foreach (self::WIDTHS as $w) {
            $candidate = $dir . '/' . $base . '-' . $w . '.webp';
            if (self::localPath($candidate) !== null) {
                $set[] = $candidate . ' ' . $w . 'w';
            }
        }
        return $set === [] ? null : implode(', ', $set);
    }

    /**
     * Handle an admin upload.
     * @param array<string,mixed> $file one entry of $_FILES
     * @return array{ok:bool, error?:string, path?:string, markdown?:string, width?:int, height?:int}
     */
    public static function upload(array $file, string $alt, string $nameHint = ''): array
    {
        $err = (int)($file['error'] ?? UPLOAD_ERR_NO_FILE);
        if ($err === UPLOAD_ERR_NO_FILE) {
            return ['ok' => false, 'error' => 'No file was selected.'];
        }
        if ($err !== UPLOAD_ERR_OK) {
            return ['ok' => false, 'error' => 'Upload failed (PHP error code ' . $err . '). The file may exceed the server limit.'];
        }
        $tmp = (string)($file['tmp_name'] ?? '');
        if ($tmp === '' || !is_uploaded_file($tmp)) {
            return ['ok' => false, 'error' => 'Upload failed: not an uploaded file.'];
        }
        if ((int)($file['size'] ?? 0) > self::MAX_BYTES) {
            return ['ok' => false, 'error' => 'The file is larger than 12 MB.'];
        }
        $alt = trim($alt);
        if ($alt === '') {
            return ['ok' => false, 'error' => 'Alt text is required for every image.'];
        }

        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime  = (string)$finfo->file($tmp);
        if (!isset(self::ALLOWED_MIME[$mime])) {
            return ['ok' => false, 'error' => 'Only JPEG, PNG and WebP images are accepted.'];
        }
        $info = @getimagesize($tmp);
        if ($info === false || (int)$info[0] < 1 || (int)$info[1] < 1) {
            return ['ok' => false, 'error' => 'That file is not a readable image.'];
        }
        $sniffed = (string)($info['mime'] ?? '');
        if ($sniffed !== $mime) {
            return ['ok' => false, 'error' => 'The file contents do not match a supported image type.'];
        }

        [$srcW, $srcH] = [(int)$info[0], (int)$info[1]];

        $slug = Util::slugify($nameHint !== '' ? $nameHint : pathinfo((string)($file['name'] ?? 'image'), PATHINFO_FILENAME));
        if (!Util::isSlug($slug)) {
            $slug = 'image-' . date('His');
        }

        $rel = date('Y') . '/' . date('m');
        $dir = VJ_SITE . '/media/' . $rel;
        if (!Util::mkdirp($dir)) {
            return ['ok' => false, 'error' => 'Could not create the media directory.'];
        }

        $hasAlpha = in_array($mime, ['image/png', 'image/webp'], true) && self::hasAlpha($tmp, $mime);
        $ext      = $hasAlpha ? 'png' : 'jpg';

        $unique = $slug;
        $n      = 1;
        while (is_file($dir . '/' . $unique . '.' . $ext) || is_file($dir . '/' . $unique . '.jpg') || is_file($dir . '/' . $unique . '.png')) {
            $unique = $slug . '-' . (++$n);
        }
        $slug     = $unique;
        $destFile = $dir . '/' . $slug . '.' . $ext;
        $webPath  = '/media/' . $rel . '/' . $slug . '.' . $ext;

        if (!function_exists('imagecreatetruecolor')) {
            // No GD: store the original bytes untouched rather than failing the upload (spec §9).
            Util::log('GD unavailable; storing original upload without re-encoding.');
            if (!@move_uploaded_file($tmp, $destFile)) {
                return ['ok' => false, 'error' => 'Could not store the uploaded file.'];
            }
            @chmod($destFile, 0664);
            return self::result($webPath, $alt, $srcW, $srcH);
        }

        $im = self::load($tmp, $mime);
        if ($im === null) {
            return ['ok' => false, 'error' => 'The image could not be decoded.'];
        }

        // Re-encode (this is what strips EXIF and any smuggled payload).
        $saved = $hasAlpha
            ? self::savePng($im, $destFile)
            : self::saveJpeg($im, $destFile);
        if (!$saved) {
            imagedestroy($im);
            return ['ok' => false, 'error' => 'Could not write the image.'];
        }
        @chmod($destFile, 0664);

        if (function_exists('imagewebp')) {
            $made = 0;
            foreach (self::WIDTHS as $w) {
                if ($w > $srcW && $made > 0) {
                    continue;
                }
                $targetW = min($w, $srcW);
                $targetH = max(1, (int)round($srcH * ($targetW / $srcW)));
                $variant = self::resize($im, $targetW, $targetH, $hasAlpha);
                if ($variant === null) {
                    continue;
                }
                @imagewebp($variant, $dir . '/' . $slug . '-' . $w . '.webp', self::QUALITY);
                @chmod($dir . '/' . $slug . '-' . $w . '.webp', 0664);
                imagedestroy($variant);
                $made++;
            }
        }
        imagedestroy($im);

        return self::result($webPath, $alt, $srcW, $srcH);
    }

    private static function result(string $webPath, string $alt, int $w, int $h): array
    {
        return [
            'ok'       => true,
            'path'     => $webPath,
            'alt'      => $alt,
            'width'    => $w,
            'height'   => $h,
            'markdown' => '![' . str_replace([']', '['], '', $alt) . '](' . $webPath . ')',
        ];
    }

    private static function load(string $file, string $mime): ?GdImage
    {
        $im = match ($mime) {
            'image/jpeg' => @imagecreatefromjpeg($file),
            'image/png'  => @imagecreatefrompng($file),
            'image/webp' => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($file) : false,
            default      => false,
        };
        return $im instanceof GdImage ? $im : null;
    }

    private static function hasAlpha(string $file, string $mime): bool
    {
        if ($mime === 'image/png') {
            $bytes = @file_get_contents($file, false, null, 0, 32);
            if ($bytes === false || strlen($bytes) < 26) {
                return false;
            }
            $colorType = ord($bytes[25]);
            return in_array($colorType, [4, 6], true) || $colorType === 3;
        }
        return $mime === 'image/webp';
    }

    private static function resize(GdImage $src, int $w, int $h, bool $alpha): ?GdImage
    {
        $dst = @imagecreatetruecolor($w, $h);
        if (!$dst instanceof GdImage) {
            return null;
        }
        if ($alpha) {
            imagealphablending($dst, false);
            imagesavealpha($dst, true);
            $transparent = imagecolorallocatealpha($dst, 0, 0, 0, 127);
            imagefilledrectangle($dst, 0, 0, $w, $h, (int)$transparent);
        } else {
            $white = imagecolorallocate($dst, 255, 255, 255);
            imagefilledrectangle($dst, 0, 0, $w, $h, (int)$white);
        }
        imagecopyresampled($dst, $src, 0, 0, 0, 0, $w, $h, imagesx($src), imagesy($src));
        return $dst;
    }

    private static function saveJpeg(GdImage $im, string $dest): bool
    {
        $w   = imagesx($im);
        $h   = imagesy($im);
        $flat = @imagecreatetruecolor($w, $h);
        if (!$flat instanceof GdImage) {
            return false;
        }
        $white = imagecolorallocate($flat, 255, 255, 255);
        imagefilledrectangle($flat, 0, 0, $w, $h, (int)$white);
        imagecopy($flat, $im, 0, 0, 0, 0, $w, $h);
        $ok = @imagejpeg($flat, $dest, self::QUALITY);
        imagedestroy($flat);
        return $ok;
    }

    private static function savePng(GdImage $im, string $dest): bool
    {
        imagealphablending($im, false);
        imagesavealpha($im, true);
        return @imagepng($im, $dest, 6);
    }

    /**
     * All uploaded media as web paths, newest first (variants excluded).
     * @return list<array{path:string,size:int,mtime:int,width:?int,height:?int}>
     */
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
            if (!$f->isFile()) {
                continue;
            }
            $name = $f->getFilename();
            if (preg_match('/-(?:' . implode('|', self::WIDTHS) . ')\.webp$/', $name)) {
                continue;
            }
            if (!preg_match('/\.(jpe?g|png|webp)$/i', $name)) {
                continue;
            }
            $rel = str_replace('\\', '/', substr($f->getPathname(), strlen($root)));
            $out[] = [
                'path'  => '/media' . $rel,
                'size'  => $f->getSize(),
                'mtime' => $f->getMTime(),
            ];
        }
        usort($out, static fn(array $a, array $b): int => $b['mtime'] <=> $a['mtime']);
        return array_slice($out, 0, $limit);
    }

    /**
     * <picture> markup for a hero/card image. $eager marks the LCP image.
     */
    public static function picture(string $src, string $alt, bool $eager = false, string $class = '', string $sizes = '100vw'): string
    {
        if ($src === '') {
            return '';
        }
        $dims   = self::dimensions($src);
        $srcset = self::webpSrcset($src);
        $attrs  = 'src="' . e($src) . '" alt="' . e($alt) . '"';
        if ($class !== '') {
            $attrs .= ' class="' . e($class) . '"';
        }
        if ($dims !== null) {
            $attrs .= ' width="' . $dims[0] . '" height="' . $dims[1] . '"';
        }
        $attrs .= $eager
            ? ' loading="eager" fetchpriority="high" decoding="async"'
            : ' loading="lazy" decoding="async"';
        $img = '<img ' . $attrs . '>';
        if ($srcset === null) {
            return $img;
        }
        return '<picture><source type="image/webp" srcset="' . e($srcset) . '" sizes="' . e($sizes) . '">' . $img . '</picture>';
    }
}
