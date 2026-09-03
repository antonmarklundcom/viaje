<?php /** @var string $name @var array $schema @var list<array> $rows @var bool $saved */
$rows   = array_values(array_filter((array)$rows, 'is_array'));
$rows[] = [];
$rows[] = [];
?>
<form class="adm-card" method="post" action="/admin/data/<?= e($name) ?>">
  <input type="hidden" name="csrf" value="<?= e($csrf ?? '') ?>">
  <div class="adm-card__head">
    <h1><?= e($name) ?>.json</h1>
    <button class="adm-btn adm-btn--primary" type="submit"><?= e(t('admin_publish')) ?></button>
  </div>
  <?php if (!empty($saved)): ?><p class="adm-ok">✓</p><?php endif; ?>
  <p class="adm-help">Las filas vacías se descartan al guardar.</p>
  <?php foreach ($rows as $i => $row): ?>
    <fieldset class="adm-row adm-row--data">
      <legend>#<?= $i + 1 ?></legend>
      <?php foreach ($schema as $key => $kind): ?>
        <label class="adm-field">
          <span><?= e($key) ?></span>
          <?php $value = $row[$key] ?? ''; if (is_array($value)) { $value = implode(', ', $value); } ?>
          <?php if ($kind === 'textarea'): ?>
            <textarea name="rows[<?= $i ?>][<?= e($key) ?>]" rows="3"><?= e((string)$value) ?></textarea>
          <?php else: ?>
            <input name="rows[<?= $i ?>][<?= e($key) ?>]" type="<?= $kind === 'number' ? 'number' : 'text' ?>" value="<?= e((string)$value) ?>">
          <?php endif; ?>
        </label>
      <?php endforeach; ?>
    </fieldset>
  <?php endforeach; ?>
  <button class="adm-btn adm-btn--primary" type="submit"><?= e(t('admin_publish')) ?></button>
</form>
