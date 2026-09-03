<?php /** @var array $redirects @var list<string> $gone @var string $check @var ?string $result */ ?>
<div class="adm-card">
  <h1><?= e(t('admin_redirects')) ?></h1>
  <form method="get" action="/admin/redirects" class="adm-inline">
    <label for="chk">¿Qué hace el router con…?</label>
    <input id="chk" name="check" type="text" value="<?= e($check) ?>" placeholder="/paquetes/">
    <button class="adm-btn" type="submit">Comprobar</button>
  </form>
  <?php if ($result !== null): ?><p class="adm-ok"><code><?= e($check) ?></code> → <strong><?= e($result) ?></strong></p><?php endif; ?>
  <p class="adm-help">Las redirecciones se definen en <code>site/config.php</code> y sólo cambian con un deploy.</p>
</div>

<div class="adm-card">
  <h2>301 (<?= count($redirects) ?>)</h2>
  <table class="adm-table">
    <thead><tr><th>Desde</th><th>Hacia</th></tr></thead>
    <tbody><?php foreach ($redirects as $from => $to): ?><tr><td><code><?= e((string)$from) ?></code></td><td><code><?= e((string)$to) ?></code></td></tr><?php endforeach; ?></tbody>
  </table>
</div>

<div class="adm-card">
  <h2>410 (<?= count($gone) ?>)</h2>
  <ul class="adm-links"><?php foreach ($gone as $path): ?><li><code><?= e((string)$path) ?></code></li><?php endforeach; ?></ul>
</div>
