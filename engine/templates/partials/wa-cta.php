<?php /** @var array $page — WhatsApp button for the first screen of a page, with the page as context. */ ?>
<p class="wa-cta">
  <a class="btn btn--wa" href="<?= e(Leads::whatsappUrl(Leads::pageText($page))) ?>" rel="noopener" target="_blank"><?= partial('icons', ['name' => 'whatsapp']) ?><?= e(t('whatsapp_cta')) ?></a>
</p>
