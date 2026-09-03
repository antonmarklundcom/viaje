<?php /** @var ?array $result @var list<array> $files */ ?>
<div class="adm-card">
  <h1><?= e(t('admin_media')) ?></h1>
  <?php if (is_array($result)): ?>
    <?php if (!empty($result['ok'])): ?>
      <p class="adm-ok">✓ <code><?= e($result['path']) ?></code></p>
      <p><input class="adm-copy" type="text" readonly value="<?= e($result['markdown']) ?>"></p>
    <?php else: ?>
      <p class="adm-error"><?= e($result['error'] ?? '') ?></p>
    <?php endif; ?>
  <?php endif; ?>
  <form method="post" action="/admin/media" enctype="multipart/form-data" class="adm-upload">
    <input type="hidden" name="csrf" value="<?= e($csrf ?? '') ?>">
    <div class="adm-field"><label for="up-file">Archivo (jpg, png, webp · máx 12 MB)</label>
      <input id="up-file" type="file" name="file" accept="image/jpeg,image/png,image/webp" required></div>
    <div class="adm-field"><label for="up-alt"><?= e(t('hero_alt')) ?> <span class="req">*</span></label>
      <input id="up-alt" type="text" name="alt" required maxlength="180"></div>
    <div class="adm-field"><label for="up-name">Nombre de archivo (opcional)</label>
      <input id="up-name" type="text" name="name" maxlength="80"></div>
    <button class="adm-btn adm-btn--primary" type="submit">Subir</button>
  </form>
</div>

<div class="adm-card">
  <h2><?= count($files) ?> imágenes</h2>
  <ul class="adm-media-grid">
    <?php foreach ($files as $file): ?>
      <li>
        <img src="<?= e($file['path']) ?>" alt="" loading="lazy" width="160" height="120">
        <input class="adm-copy" type="text" readonly value="<?= e($file['path']) ?>">
      </li>
    <?php endforeach; ?>
  </ul>
</div>
