<?php /** @var array $counts @var array $drafts @var array $recent @var list<string> $errors */ ?>
<div class="adm-card">
  <h1><?= e(t('admin_dashboard')) ?></h1>
  <ul class="adm-counts">
    <?php foreach ((array)($nav_types ?? []) as $ty): ?>
      <li><a href="/admin/content/<?= e($ty) ?>/"><span class="adm-count"><?= (int)($counts[$ty] ?? 0) ?></span><?= e(Types::label($ty, true)) ?></a></li>
    <?php endforeach; ?>
  </ul>
</div>

<?php if (!empty($errors)): ?>
<div class="adm-card adm-card--warn">
  <h2>Content warnings (<?= count($errors) ?>)</h2>
  <ul><?php foreach ($errors as $err): ?><li><?= e($err) ?></li><?php endforeach; ?></ul>
</div>
<?php endif; ?>

<div class="adm-card">
  <h2><?= e(t('admin_draft')) ?> (<?= count((array)$drafts) ?>)</h2>
  <?php if ($drafts): ?>
  <table class="adm-table">
    <tbody>
    <?php foreach ($drafts as $d): ?>
      <tr><td><?= e($d['title']) ?></td><td><code><?= e($d['path']) ?></code></td>
        <td><a href="/admin/content/<?= e($d['type']) ?>/<?= e($d['slug']) ?>/edit"><?= e(t('admin_edit')) ?></a></td></tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  <?php else: ?><p class="adm-muted">—</p><?php endif; ?>
</div>

<div class="adm-card">
  <h2>Últimas ediciones</h2>
  <table class="adm-table">
    <thead><tr><th>Título</th><th>URL</th><th>Tipo</th><th>Estado</th><th></th></tr></thead>
    <tbody>
    <?php foreach ((array)$recent as $r): ?>
      <tr>
        <td><?= e($r['title']) ?></td>
        <td><code><?= e($r['path']) ?></code></td>
        <td><?= e(Types::label((string)$r['type'])) ?></td>
        <td><?= $r['draft'] ? e(t('admin_draft')) : e(t('admin_published')) ?></td>
        <td><a href="/admin/content/<?= e($r['type']) ?>/<?= e($r['slug']) ?>/edit"><?= e(t('admin_edit')) ?></a></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</div>

<div class="adm-card">
  <h2><?= e(t('admin_export')) ?></h2>
  <form method="post" action="/admin/export">
    <input type="hidden" name="csrf" value="<?= e($csrf ?? '') ?>">
    <button class="adm-btn" type="submit"><?= e(t('admin_export')) ?> (zip)</button>
  </form>
</div>
