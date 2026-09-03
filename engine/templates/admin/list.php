<?php /** @var string $type @var list<array> $items */ ?>
<div class="adm-card">
  <div class="adm-card__head">
    <h1><?= e(Types::label($type, true)) ?></h1>
    <a class="adm-btn adm-btn--primary" href="/admin/content/<?= e($type) ?>/new"><?= e(t('admin_new')) ?></a>
  </div>
  <?php if (!empty($saved)): ?><p class="adm-ok">✓</p><?php endif; ?>
  <table class="adm-table">
    <thead><tr><th>Título</th><th>URL</th><th>Fecha</th><th>Estado</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($items as $item): ?>
      <tr>
        <td><?= e($item['title']) ?></td>
        <td><a href="<?= e($item['path']) ?>" target="_blank" rel="noopener"><code><?= e($item['path']) ?></code></a></td>
        <td><?= e($item['date']) ?></td>
        <td><?= $item['draft'] ? e(t('admin_draft')) : e(t('admin_published')) ?></td>
        <td class="adm-actions">
          <a href="/admin/content/<?= e($type) ?>/<?= e($item['slug']) ?>/edit"><?= e(t('admin_edit')) ?></a>
          <form method="post" action="/admin/content/<?= e($type) ?>/<?= e($item['slug']) ?>/delete"
                onsubmit="return confirm('<?= e(t('admin_delete')) ?>?')">
            <input type="hidden" name="csrf" value="<?= e($csrf ?? '') ?>">
            <button class="adm-link-danger" type="submit"><?= e(t('admin_delete')) ?></button>
          </form>
        </td>
      </tr>
    <?php endforeach; ?>
    <?php if (!$items): ?><tr><td colspan="5" class="adm-muted"><?= e(t('no_items')) ?></td></tr><?php endif; ?>
    </tbody>
  </table>
</div>
