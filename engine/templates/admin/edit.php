<?php
/** @var string $type @var ?string $slug @var list<array> $fields @var array $values @var array $errors @var ?string $preview_url */
$v = static function (string $name, mixed $default = '') use ($values): mixed {
    return $values[$name] ?? $default;
};
$err = static fn(string $name): string => (string)($errors[$name] ?? '');
$saved = ($_GET['saved'] ?? '') === '1';
$publicPath = (string)($values['path'] ?? '');
?>
<form class="adm-editor" method="post" action="/admin/content/<?= e($type) ?>/save" id="editor">
  <input type="hidden" name="csrf" value="<?= e($csrf ?? '') ?>">
  <input type="hidden" name="orig_slug" value="<?= e((string)($slug ?? '')) ?>">
  <input type="hidden" name="then" value="" id="then">

  <div class="adm-card__head">
    <h1><?= e($title ?? '') ?></h1>
    <div class="adm-actions">
      <?php if ($preview_url !== null): ?><a class="adm-btn" href="<?= e($preview_url) ?>" target="_blank" rel="noopener"><?= e(t('admin_preview')) ?></a><?php endif; ?>
      <button class="adm-btn" type="submit" name="action" value="draft"><?= e(t('admin_save_draft')) ?></button>
      <button class="adm-btn adm-btn--primary" type="submit" name="action" value="publish"><?= e(t('admin_publish')) ?></button>
    </div>
  </div>

  <?php if ($saved): ?><p class="adm-ok">✓ <?= e(t('admin_published')) ?></p><?php endif; ?>
  <?php if ($errors): ?>
    <div class="adm-card adm-card--warn">
      <p><strong><?= e(t('form_error_title')) ?></strong></p>
      <ul><?php foreach ($errors as $k => $msg): ?><li><?= e($k) ?>: <?= e($msg) ?></li><?php endforeach; ?></ul>
    </div>
  <?php endif; ?>

  <div class="adm-cols">
    <div class="adm-col adm-col--main">
      <div class="adm-card">
        <?php foreach ($fields as $f): ?>
          <?php
          $name  = (string)$f['name'];
          $kind  = (string)$f['type'];
          $label = I18n::has((string)$f['label']) ? t((string)$f['label']) : (string)$f['label'];
          $val   = $name === 'slug' ? (string)($values['slug'] ?? '') : $v($name);
          $id    = 'f-' . $name;
          if (in_array($name, ['seo_title', 'path', 'canonical', 'noindex', 'excerpt', 'tags', 'region', 'featured', 'updated', 'author'], true)) { continue; }
          ?>
          <div class="adm-field<?= $err($name) !== '' ? ' has-error' : '' ?>">
            <label for="<?= e($id) ?>"><?= e($label) ?><?= !empty($f['required']) ? ' <span class="req">*</span>' : '' ?></label>
            <?php require Render::templateFile('admin/field'); ?>
            <?php if ($err($name) !== ''): ?><p class="adm-error"><?= e($err($name)) ?></p><?php endif; ?>
          </div>
        <?php endforeach; ?>

        <div class="adm-field">
          <label for="f-body">Markdown</label>
          <div class="adm-toolbar" data-target="f-body">
            <button type="button" data-md="bold">B</button>
            <button type="button" data-md="h2">H2</button>
            <button type="button" data-md="h3">H3</button>
            <button type="button" data-md="link">link</button>
            <button type="button" data-md="image">img</button>
            <button type="button" data-md="tip">:::tip</button>
          </div>
          <textarea id="f-body" name="body" rows="24" class="adm-mono"><?= e((string)$v('body')) ?></textarea>
          <p><button class="adm-btn" type="button" id="md-preview-btn">Preview</button></p>
          <div class="prose adm-preview" id="md-preview" hidden></div>
        </div>
      </div>
    </div>

    <aside class="adm-col adm-col--side">
      <div class="adm-card">
        <h2>SEO</h2>
        <div class="adm-field">
          <label for="f-seo_title"><?= e(t('seo_title')) ?></label>
          <input id="f-seo_title" name="seo_title" type="text" value="<?= e((string)$v('seo_title')) ?>" data-counter="60">
          <p class="adm-counter" data-for="f-seo_title"></p>
        </div>
        <div class="adm-field">
          <label for="f-path"><?= e(t('path')) ?></label>
          <input id="f-path" name="path" type="text" value="<?= e((string)$v('path')) ?>" placeholder="/mi-url/">
          <p class="adm-help"><?= e(t('path_help')) ?></p>
          <?php if ($err('path') !== ''): ?><p class="adm-error"><?= e($err('path')) ?></p><?php endif; ?>
        </div>
        <div class="adm-snippet" id="snippet">
          <p class="adm-snippet__url"><?= e((string)($site['base_url'] ?? '')) ?><span id="snippet-path"><?= e($publicPath) ?></span></p>
          <p class="adm-snippet__title" id="snippet-title"></p>
          <p class="adm-snippet__desc" id="snippet-desc"></p>
        </div>
        <div class="adm-field">
          <label for="f-canonical"><?= e(t('canonical')) ?></label>
          <input id="f-canonical" name="canonical" type="text" value="<?= e((string)$v('canonical')) ?>">
        </div>
        <label class="adm-check"><input type="checkbox" name="noindex" value="1" <?= !empty($values['noindex']) ? 'checked' : '' ?>> <?= e(t('noindex')) ?></label>
        <label class="adm-check"><input type="checkbox" name="featured" value="1" <?= !empty($values['featured']) ? 'checked' : '' ?>> <?= e(t('featured')) ?></label>
      </div>

      <div class="adm-card">
        <h2>Meta</h2>
        <div class="adm-field">
          <label for="f-updated"><?= e(t('updated')) ?></label>
          <input id="f-updated" name="updated" type="date" value="<?= e((string)$v('updated')) ?>">
        </div>
        <div class="adm-field">
          <label for="f-author"><?= e(t('author')) ?></label>
          <input id="f-author" name="author" type="text" value="<?= e((string)$v('author')) ?>">
        </div>
        <div class="adm-field">
          <label for="f-excerpt"><?= e(t('excerpt')) ?></label>
          <textarea id="f-excerpt" name="excerpt" rows="3"><?= e((string)$v('excerpt')) ?></textarea>
        </div>
        <div class="adm-field">
          <label for="f-tags"><?= e(t('tags')) ?></label>
          <textarea id="f-tags" name="tags" rows="3" placeholder="uno por línea"><?= e(implode("\n", (array)$v('tags', []))) ?></textarea>
        </div>
        <div class="adm-field">
          <label for="f-region"><?= e(t('region')) ?></label>
          <input id="f-region" name="region" type="text" value="<?= e((string)$v('region')) ?>">
        </div>
      </div>

      <div class="adm-card">
        <h2>Advanced</h2>
        <p class="adm-help">Front matter que el formulario no modela. Se aplica al final.</p>
        <textarea name="advanced" rows="6" class="adm-mono"><?= e((string)$v('advanced')) ?></textarea>
      </div>
    </aside>
  </div>
</form>
