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
<?= $seo ?>
<link rel="stylesheet" href="<?= e(asset('/engine/assets/base.css')) ?>">
<link rel="stylesheet" href="<?= e(asset('/theme.css')) ?>">
<?= $site['head_extra'] ?? '' ?>
<?php if (!empty($site['analytics']['ga4']) && empty($site['staging'])): ?>
<script async src="https://www.googletagmanager.com/gtag/js?id=<?= e($site['analytics']['ga4']) ?>"></script>
<script>window.dataLayer=window.dataLayer||[];function gtag(){dataLayer.push(arguments);}gtag('js',new Date());gtag('config','<?= e($site['analytics']['ga4']) ?>');</script>
<?php endif; ?>
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
