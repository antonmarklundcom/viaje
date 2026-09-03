<?php /** @var array $page @var array $site @var array $trail */
echo partial('breadcrumbs', ['trail' => $trail ?? []]);
?>
<article class="page">
  <div class="container container--narrow">
    <h1 class="page__title"><?= e($page['title']) ?></h1>
    <?php if (($page['html'] ?? '') !== ''): ?><div class="prose"><?= $page['html'] ?></div><?php endif; ?>
    <?= partial('faq-accordion', ['rows' => Content::faq(), 'heading' => false]) ?>
  </div>
</article>
<section class="cta-band">
  <div class="container">
    <h2><?= e(t('form_title')) ?></h2>
    <p class="cta-band__actions">
      <a class="btn btn--wa" href="<?= e(Leads::whatsappUrl()) ?>" rel="noopener" target="_blank"><?= partial('icons', ['name' => 'whatsapp']) ?><?= e(t('whatsapp_cta')) ?></a>
      <a class="btn btn--ghost" href="<?= e(Router::contactPath()) ?>"><?= e(t('contact_us')) ?></a>
    </p>
  </div>
</section>
