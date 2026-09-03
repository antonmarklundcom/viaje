<?php
declare(strict_types=1);

/**
 * Weekly backup cron (plan §2.4 / §8 — the runbook wires this into Hostinger's
 * Cron Jobs). Zips site/content, site/media and site/data/leads — the same
 * contents as the admin's "Export backup" button (engine/lib/admin.php
 * Admin::export()) — into site/data/backups/<domain>-backup-<timestamp>.zip,
 * then prunes older backups beyond KEEP.
 *
 * Runs from inside a deployed document root, next to engine/ and site/:
 *   php /home/<user>/domains/<domain>/public_html/engine/bin/backup.php
 * engine/ is never served directly except engine/assets/ (.htaccess), so this
 * only runs via CLI/cron, never as a URL.
 */

const KEEP = 8; // ~2 months of weekly backups

if (!class_exists('ZipArchive')) {
    fwrite(STDERR, "ZipArchive is not available on this server.\n");
    exit(1);
}

$site = dirname(__DIR__, 2) . '/site';
if (!is_dir($site)) {
    fwrite(STDERR, "No site/ directory next to engine/ — this script must run from a deployed document root.\n");
    exit(1);
}

$domain = 'site';
if (is_file($site . '/config.php')) {
    $cfg = require $site . '/config.php';
    if (is_array($cfg) && !empty($cfg['domain'])) {
        $domain = (string)$cfg['domain'];
    }
}

$backupDir = $site . '/data/backups';
if (!is_dir($backupDir) && !@mkdir($backupDir, 0775, true) && !is_dir($backupDir)) {
    fwrite(STDERR, "Cannot create $backupDir\n");
    exit(1);
}

$target = $backupDir . '/' . $domain . '-backup-' . date('Ymd-His') . '.zip';
$zip    = new ZipArchive();
if ($zip->open($target, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
    fwrite(STDERR, "Cannot create $target\n");
    exit(1);
}
$added = 0;
foreach (['content', 'media', 'data/leads'] as $rel) {
    $dir = $site . '/' . $rel;
    if (!is_dir($dir)) {
        continue;
    }
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS));
    foreach ($it as $f) {
        /** @var SplFileInfo $f */
        if ($f->isFile()) {
            $zip->addFile($f->getPathname(), $rel . str_replace('\\', '/', substr($f->getPathname(), strlen($dir))));
            $added++;
        }
    }
}
$zip->close();
echo "Wrote $target ($added file(s))\n";

$existing = glob($backupDir . '/' . $domain . '-backup-*.zip') ?: [];
usort($existing, static fn(string $a, string $b): int => filemtime($b) <=> filemtime($a));
foreach (array_slice($existing, KEEP) as $old) {
    @unlink($old);
    echo "Pruned $old\n";
}
exit(0);
