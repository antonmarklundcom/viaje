<?php /** @var array $site @var string $title @var bool $authed @var string $content_template */ ?>
<!doctype html>
<html lang="<?= e($site['html_lang']) ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title><?= e($title ?? t('admin')) ?> · <?= e($site['site_name']) ?></title>
<link rel="stylesheet" href="<?= e(asset('/engine/assets/admin.css')) ?>">
</head>
<body class="admin">
<header class="adm-header">
  <div class="adm-header__inner">
    <a class="adm-brand" href="/admin/dashboard"><?= e(t('admin')) ?> · <?= e($site['site_name']) ?></a>
    <?php if (!empty($authed)): ?>
      <nav class="adm-nav">
        <a href="/admin/dashboard"><?= e(t('admin_dashboard')) ?></a>
        <?php foreach ((array)($nav_types ?? []) as $ty): ?>
          <a href="/admin/content/<?= e($ty) ?>/"><?= e(Types::label($ty, true)) ?></a>
        <?php endforeach; ?>
        <a href="/admin/media"><?= e(t('admin_media')) ?></a>
        <a href="/admin/data"><?= e(t('admin_data')) ?></a>
        <a href="/admin/redirects"><?= e(t('admin_redirects')) ?></a>
        <a href="/" target="_blank" rel="noopener"><?= e($site['domain']) ?></a>
        <form method="post" action="/admin/logout" class="adm-logout">
          <input type="hidden" name="csrf" value="<?= e($csrf ?? '') ?>">
          <button type="submit"><?= e(t('admin_logout')) ?></button>
        </form>
      </nav>
    <?php endif; ?>
  </div>
</header>
<main class="adm-main">
<?php require Render::templateFile($content_template); ?>
</main>
<script src="<?= e(asset('/engine/assets/admin.js')) ?>" defer></script>
</body>
</html>
