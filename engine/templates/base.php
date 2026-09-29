<?php
/** @var array $site @var array $page @var string $seo @var string $content_template */
$bodyClass = 'page-' . preg_replace('/[^a-z0-9]+/', '-', (string)($page['type'] ?? 'page'))
    . (($page['path'] ?? '') === '/' ? ' is-home' : '');
?>
<!doctype html>
<html lang="<?= e($site['html_lang']) ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="preload" href="<?= e(asset('/engine/assets/base.css')) ?>" as="style">
<?php // The hero is the largest thing above the fold: fetch it before the parser gets to it.
// Only templates that actually draw the hero image (contact and FAQ do not) get a preload.
$heroSrc = '';
$heroSizes = '(max-width: 900px) 100vw, 46rem';
if (in_array($content_template ?? '', ['home', 'page', 'service', 'post'], true)) {
    $heroSrc   = (string)($page['hero'] ?? '');
    $heroSizes = ($content_template === 'home') ? '100vw' : $heroSizes;
} elseif (($content_template ?? '') === 'hub' && (int)($pager['page'] ?? 1) === 1) {
    $heroSrc   = (string)($page['hub']['hero'] ?? '');
    $heroSizes = '100vw';
}
if ($heroSrc !== '') {
    echo Images::preload($heroSrc, $heroSizes);
}
?>
<?= $seo ?>
<link rel="stylesheet" href="<?= e(asset('/engine/assets/base.css')) ?>">
<?php $theme = is_file(VJ_SITE . '/theme.css') ? (string)file_get_contents(VJ_SITE . '/theme.css') : ''; ?>
<?php if ($theme !== '' && strlen($theme) < 6000): ?>
<style><?= str_replace('</', '<\/', preg_replace(['~/\*.*?\*/~s', '~\s+~', '~\s*([{};:,])\s*~'], ['', ' ', '$1'], $theme) ?? $theme) ?></style>
<?php else: ?>
<link rel="stylesheet" href="<?= e(asset('/theme.css')) ?>">
<?php endif; ?>
<?= $site['head_extra'] ?? '' ?>
</head>
<body class="<?= e($bodyClass) ?>">
<a class="skip-link" href="#contenido"><?= e(t('skip_to_content')) ?></a>
<?= partial('header', ['site' => $site, 'page' => $page]) ?>
<main id="contenido">
<?php require Render::templateFile($content_template); ?>
</main>
<?= partial('footer', ['site' => $site, 'page' => $page]) ?>
<?= partial('whatsapp', ['site' => $site, 'page' => $page]) ?>
<script src="<?= e(asset('/engine/assets/site.js')) ?>" defer></script>
<?= $site['body_extra'] ?? '' ?>
</body>
</html>
