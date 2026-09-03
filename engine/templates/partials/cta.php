<?php /** @var array $page @var array $site */
$title = (string)($page['cta_title'] ?? '');
if ($title === '') { return; }
?>
<section class="cta-band">
  <div class="container">
    <h2><?= e($title) ?></h2>
    <?php if (!empty($page['cta_text'])): ?><p><?= e($page['cta_text']) ?></p><?php endif; ?>
    <p class="cta-band__actions">
      <?php if (!empty($page['cta_label'])): ?><a class="btn btn--primary" href="<?= e(url((string)($page['cta_href'] ?? '/'))) ?>"><?= e($page['cta_label']) ?></a><?php endif; ?>
      <a class="btn btn--wa" href="<?= e(Leads::whatsappUrl()) ?>" rel="noopener" target="_blank"><?= partial('icons', ['name' => 'whatsapp']) ?><?= e(t('whatsapp_cta')) ?></a>
    </p>
  </div>
</section>
