<?php /** @var array $page @var array $site @var array $trail */
echo partial('breadcrumbs', ['trail' => $trail ?? []]);
?>
<article class="contact">
  <div class="container contact__grid">
    <div class="contact__intro">
      <h1 class="page__title"><?= e($page['title']) ?></h1>
      <?php if (($page['html'] ?? '') !== ''): ?><div class="prose"><?= $page['html'] ?></div><?php endif; ?>
      <?= partial('contact-details', ['site' => $site]) ?>
    </div>
    <div class="contact__form">
      <?= partial('lead-form', ['page' => $page, 'site' => $site, 'server_stamp' => true]) ?>
    </div>
  </div>
</article>
