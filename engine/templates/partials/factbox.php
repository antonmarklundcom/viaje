<?php /** @var array $page */
$facts = array_filter((array)($page['facts'] ?? []), static fn($v) => $v !== null && $v !== '');
if ($facts === []) { return; }
?>
<aside class="factbox" aria-label="<?= e(t('facts_title')) ?>">
  <h2 class="factbox__title"><?= e(t('facts_title')) ?></h2>
  <dl>
    <?php foreach ($facts as $key => $value): ?>
      <div class="factbox__row">
        <dt><?= e(I18n::has('fact_' . $key) ? t('fact_' . $key) : ucfirst(str_replace('_', ' ', (string)$key))) ?></dt>
        <dd><?= e(is_array($value) ? implode(', ', $value) : (string)$value) ?></dd>
      </div>
    <?php endforeach; ?>
  </dl>
  <?php if (!empty($page['map_url'])): ?>
    <p class="factbox__map"><a class="link-more" href="<?= e($page['map_url']) ?>" rel="noopener" target="_blank"><?= e(t('view_map')) ?></a></p>
  <?php endif; ?>
</aside>
