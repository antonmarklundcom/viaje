<?php /** @var list<string> $names */ ?>
<div class="adm-card">
  <h1><?= e(t('admin_data')) ?></h1>
  <ul class="adm-links">
    <?php foreach ($names as $name): ?><li><a href="/admin/data/<?= e($name) ?>"><?= e($name) ?>.json</a></li><?php endforeach; ?>
  </ul>
</div>
