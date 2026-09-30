<?php /** @var string $type @var string $slug @var list<array> $versions @var bool $exists @var bool $has_seed
 * @var ?array $show @var string $done @var string $edit_url @var string $base_url @var string $action */ ?>
<div class="adm-card">
  <div class="adm-card__head">
    <h1><?= e($title ?? '') ?></h1>
    <div class="adm-actions">
      <?php if ($exists): ?><a class="adm-btn" href="<?= e($edit_url) ?>"><?= e(t('admin_edit')) ?></a><?php endif; ?>
      <?php if ($has_seed): ?>
        <form method="post" action="<?= e($action) ?>/restore-seed" onsubmit="return confirm(<?= e(ejs(t('admin_restore_seed_confirm'))) ?>)">
          <input type="hidden" name="csrf" value="<?= e($csrf ?? '') ?>">
          <button class="adm-btn" type="submit"><?= e(t('admin_restore_seed')) ?></button>
        </form>
      <?php endif; ?>
    </div>
  </div>
  <?php if ($done !== ''): ?><p class="adm-ok">✓ <?= e(t($done === 'seed' ? 'admin_restored_seed' : 'admin_restored')) ?></p><?php endif; ?>
  <?php if (!$exists): ?><p class="adm-help"><?= e(t('admin_history_gone')) ?></p><?php endif; ?>
  <p class="adm-help"><?= e(t('admin_history_help', ['n' => History::KEEP])) ?></p>
  <table class="adm-table">
    <thead><tr><th><?= e(t('admin_history_when')) ?></th><th><?= e(t('admin_history_title')) ?></th><th>Bytes</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($versions as $v): ?>
      <tr<?= ($show['id'] ?? '') === $v['id'] ? ' class="is-current"' : '' ?>>
        <td><code><?= e($v['time']) ?></code></td>
        <td><?= e($v['title']) ?></td>
        <td><?= e((string)$v['size']) ?></td>
        <td class="adm-actions">
          <a href="<?= e($base_url) ?>?v=<?= e(rawurlencode($v['id'])) ?>"><?= e(t('admin_history_view')) ?></a>
          <form method="post" action="<?= e($action) ?>/restore" onsubmit="return confirm(<?= e(ejs(t('admin_restore_confirm'))) ?>)">
            <input type="hidden" name="csrf" value="<?= e($csrf ?? '') ?>">
            <input type="hidden" name="version" value="<?= e($v['id']) ?>">
            <button class="adm-btn" type="submit"><?= e(t('admin_restore')) ?></button>
          </form>
        </td>
      </tr>
    <?php endforeach; ?>
    <?php if (!$versions): ?><tr><td colspan="4" class="adm-muted"><?= e(t('admin_history_none')) ?></td></tr><?php endif; ?>
    </tbody>
  </table>
</div>
<?php if ($show !== null): ?>
<div class="adm-card">
  <h2><?= e($show['id']) ?></h2>
  <pre class="adm-mono adm-history-text"><?= e($show['text']) ?></pre>
</div>
<?php endif; ?>
