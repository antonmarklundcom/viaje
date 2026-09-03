<?php
/** One editor input. @var string $name @var string $kind @var mixed $val @var string $id @var array $f */
switch ($kind):
case 'textarea': ?>
  <textarea id="<?= e($id) ?>" name="<?= e($name) ?>" rows="<?= (int)($f['rows'] ?? 4) ?>"
    <?= isset($f['counter']) ? 'data-counter="' . (int)$f['counter'] . '"' : '' ?>><?= e((string)$val) ?></textarea>
  <?php if (isset($f['counter'])): ?><p class="adm-counter" data-for="<?= e($id) ?>"></p><?php endif; ?>
<?php break;
case 'select': ?>
  <select id="<?= e($id) ?>" name="<?= e($name) ?>">
    <?php foreach ((array)($f['options'] ?? []) as $opt): ?>
      <option value="<?= e($opt) ?>" <?= (string)$val === (string)$opt ? 'selected' : '' ?>><?= e($opt) ?></option>
    <?php endforeach; ?>
  </select>
<?php break;
case 'bool': ?>
  <label class="adm-check"><input type="checkbox" name="<?= e($name) ?>" value="1" <?= $val ? 'checked' : '' ?>> <?= e($name) ?></label>
<?php break;
case 'date': ?>
  <input id="<?= e($id) ?>" name="<?= e($name) ?>" type="date" value="<?= e((string)($val ?: date('Y-m-d'))) ?>">
<?php break;
case 'number': ?>
  <input id="<?= e($id) ?>" name="<?= e($name) ?>" type="number" value="<?= e((string)$val) ?>">
<?php break;
case 'list': ?>
  <textarea id="<?= e($id) ?>" name="<?= e($name) ?>" rows="5" placeholder="uno por línea"><?= e(implode("\n", (array)$val)) ?></textarea>
<?php break;
case 'image': ?>
  <div class="adm-image">
    <input id="<?= e($id) ?>" name="<?= e($name) ?>" type="text" value="<?= e((string)$val) ?>" placeholder="/media/2026/01/foto.jpg">
    <a class="adm-btn" href="/admin/media" target="_blank" rel="noopener"><?= e(t('admin_media')) ?></a>
  </div>
  <?php if ((string)$val !== ''): ?><img class="adm-thumb" src="<?= e((string)$val) ?>" alt="" loading="lazy"><?php endif; ?>
<?php break;
case 'facts': ?>
  <div class="adm-facts">
    <?php foreach ((array)($f['keys'] ?? []) as $k): ?>
      <label><span><?= e(I18n::has('fact_' . $k) ? t('fact_' . $k) : $k) ?></span>
        <input name="<?= e($name) ?>[<?= e($k) ?>]" type="text" value="<?= e((string)(((array)$val)[$k] ?? '')) ?>"></label>
    <?php endforeach; ?>
  </div>
<?php break;
case 'itinerary': ?>
  <div class="adm-rows" data-rows="itinerary">
    <?php $rows = array_values(array_filter((array)$val, 'is_array')); $rows[] = ['day' => '', 'title' => '', 'text' => '']; ?>
    <?php foreach ($rows as $i => $row): ?>
      <fieldset class="adm-row">
        <input name="<?= e($name) ?>[<?= $i ?>][day]" type="text" placeholder="<?= e(t('day')) ?>" value="<?= e((string)($row['day'] ?? '')) ?>">
        <input name="<?= e($name) ?>[<?= $i ?>][title]" type="text" placeholder="<?= e(t('title')) ?>" value="<?= e((string)($row['title'] ?? '')) ?>">
        <textarea name="<?= e($name) ?>[<?= $i ?>][text]" rows="2"><?= e((string)($row['text'] ?? '')) ?></textarea>
      </fieldset>
    <?php endforeach; ?>
  </div>
<?php break;
default: ?>
  <input id="<?= e($id) ?>" name="<?= e($name) ?>" type="text" value="<?= e((string)$val) ?>"
    <?= !empty($f['required']) ? 'required' : '' ?>
    <?= isset($f['counter']) ? 'data-counter="' . (int)$f['counter'] . '"' : '' ?>>
  <?php if (isset($f['counter'])): ?><p class="adm-counter" data-for="<?= e($id) ?>"></p><?php endif; ?>
<?php endswitch; ?>
