<?php /** @var array $page @var array $site */
$features = array_filter((array)($page['features'] ?? []), 'is_array');
$stats    = array_filter((array)($page['stats'] ?? []), 'is_array');
?>
<?= partial('hero', ['page' => $page, 'site' => $site]) ?>
<?php if (($page['html'] ?? '') !== ''): ?>
<section class="section container container--narrow">
  <div class="prose"><?= $page['html'] ?></div>
</section>
<?php endif; ?>

<?php if ($features): ?>
<section class="section container features">
  <ul class="features__grid">
    <?php foreach ($features as $f): ?>
      <li class="feature">
        <?= partial('icons', ['name' => (string)($f['icon'] ?? 'star')]) ?>
        <h2 class="feature__title"><?= e($f['title'] ?? '') ?></h2>
        <p><?= e($f['text'] ?? '') ?></p>
      </li>
    <?php endforeach; ?>
  </ul>
</section>
<?php endif; ?>

<?php if ($stats): ?>
<section class="section container stats">
  <ul class="stats__grid">
    <?php foreach ($stats as $s): ?>
      <li class="stat"><span class="stat__value"><?= e($s['value'] ?? '') ?></span><span class="stat__label"><?= e($s['label'] ?? '') ?></span></li>
    <?php endforeach; ?>
  </ul>
</section>
<?php endif; ?>

<?php if (!empty($page['show_services']) && Types::enabled('service')): ?>
<section class="section container">
  <?= partial('cards', ['items' => Content::listType('service', ['limit' => 6]), 'heading' => t('our_services')]) ?>
  <?php $hub = Types::hubFor('service'); if ($hub !== null): ?>
    <p class="section__more"><a class="link-more" href="<?= e($hub) ?>"><?= e(t('read_more')) ?><?= partial('icons', ['name' => 'arrow']) ?></a></p>
  <?php endif; ?>
</section>
<?php endif; ?>

<?php if (!empty($page['gallery'])): ?>
<section class="section container"><?= partial('gallery', ['rows' => Content::data('gallery')]) ?></section>
<?php endif; ?>

<?php if (!empty($page['testimonials'])): ?>
<section class="section container"><?= partial('testimonials', ['rows' => Content::data('testimonials')]) ?></section>
<?php endif; ?>

<?php if (!empty($page['show_posts']) && Types::enabled('post')): ?>
<section class="section container">
  <?= partial('cards', ['items' => Content::listType('post', ['limit' => (int)($site['home']['featured_posts'] ?? 3)]), 'heading' => t('latest_posts')]) ?>
  <?php $hub = Types::hubFor('post'); if ($hub !== null): ?>
    <p class="section__more"><a class="link-more" href="<?= e($hub) ?>"><?= e(t('read_more')) ?><?= partial('icons', ['name' => 'arrow']) ?></a></p>
  <?php endif; ?>
</section>
<?php endif; ?>

<?php $faqTags = (array)($page['faq_tags'] ?? $site['home']['faq_tags'] ?? []); ?>
<?php if ($faqTags): ?>
<section class="section container container--narrow"><?= partial('faq-accordion', ['rows' => array_slice(Content::faq($faqTags), 0, 6)]) ?></section>
<?php endif; ?>

<?= partial('cta', ['page' => $page, 'site' => $site]) ?>
