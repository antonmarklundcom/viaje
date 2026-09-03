<?php /** @var array $page @var array $site @var array $trail */
echo partial('breadcrumbs', ['trail' => $trail ?? []]);
$c = (array)($site['contact'] ?? []);
?>
<article class="contact">
  <div class="container contact__grid">
    <div class="contact__intro">
      <h1 class="page__title"><?= e($page['title']) ?></h1>
      <?php if (($page['html'] ?? '') !== ''): ?><div class="prose"><?= $page['html'] ?></div><?php endif; ?>
      <ul class="contact__list">
        <?php if (!empty($c['phone_display'])): ?>
          <li><?= partial('icons', ['name' => 'phone']) ?><a href="tel:<?= e($c['phone_e164']) ?>"><?= e($c['phone_display']) ?></a></li>
        <?php endif; ?>
        <?php if (!empty($c['email'])): ?>
          <li><?= partial('icons', ['name' => 'mail']) ?><a href="mailto:<?= e($c['email']) ?>"><?= e($c['email']) ?></a></li>
        <?php endif; ?>
        <?php if (!empty($c['address']['street'])): ?>
          <li><?= partial('icons', ['name' => 'pin']) ?><span><?= e(trim(($c['address']['street'] ?? '') . ', ' . ($c['address']['city'] ?? '') . ', ' . ($c['address']['country_name'] ?? ''), ', ')) ?></span></li>
        <?php endif; ?>
        <?php if (!empty($c['hours'])): ?>
          <li><?= partial('icons', ['name' => 'clock']) ?><span><?= e($c['hours']) ?></span></li>
        <?php endif; ?>
      </ul>
    </div>
    <div class="contact__form">
      <?= partial('lead-form', ['page' => $page, 'site' => $site]) ?>
    </div>
  </div>
</article>
