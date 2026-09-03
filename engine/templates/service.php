<?php /** @var array $page @var array $site @var array $trail */
echo partial('breadcrumbs', ['trail' => $trail ?? []]);
$included = array_values(array_filter((array)($page['included'] ?? [])));
?>
<article class="service">
  <div class="container container--narrow">
    <h1 class="page__title"><?= e($page['title']) ?></h1>
    <?php if (($page['intro'] ?? '') !== ''): ?><p class="lede"><?= e($page['intro']) ?></p><?php endif; ?>
    <?php if (($page['hero'] ?? '') !== ''): ?>
      <figure class="page__hero"><?= Images::picture((string)$page['hero'], (string)($page['hero_alt'] ?? ''), ['class' => 'page__hero-img', 'loading' => 'eager', 'fetchpriority' => 'high']) ?></figure>
    <?php endif; ?>
    <div class="prose"><?= $page['html'] ?></div>
    <?php if ($included): ?>
      <section class="included">
        <h2 class="section__title"><?= e(t('included_title')) ?></h2>
        <ul class="included__list">
          <?php foreach ($included as $row): ?><li><?= partial('icons', ['name' => 'shield']) ?><span><?= e((string)$row) ?></span></li><?php endforeach; ?>
        </ul>
      </section>
    <?php endif; ?>
    <?php $faq = Content::faq([(string)$page['slug'], 'servicios']); ?>
    <?= partial('faq-accordion', ['rows' => array_slice($faq, 0, 6)]) ?>
  </div>
</article>
<section class="cta-band">
  <div class="container">
    <h2><?= e(($page['cta_text'] ?? '') !== '' ? (string)$page['cta_text'] : t('form_title')) ?></h2>
    <p class="cta-band__actions">
      <a class="btn btn--wa" href="<?= e(Leads::whatsappUrl(trim((string)($site['contact']['whatsapp_default_text'] ?? '')) . ' ' . (string)$page['title'])) ?>" rel="noopener" target="_blank"><?= partial('icons', ['name' => 'whatsapp']) ?><?= e(t('whatsapp_cta')) ?></a>
      <a class="btn btn--ghost" href="<?= e(Router::contactPath()) ?>"><?= e(t('contact_us')) ?></a>
    </p>
  </div>
</section>
