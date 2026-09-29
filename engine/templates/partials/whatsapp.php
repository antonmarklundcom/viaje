<?php /** @var array $site @var array $page — floating button on desktop, sticky bar on phones. */
$href  = Leads::whatsappUrl(Leads::pageText($page) ?: null);
$title = (string)($page['title'] ?? '');
$label = $title !== '' && ($page['path'] ?? '/') !== '/' && empty($page['is_hub']) && empty($page['status'])
    ? t('whatsapp_about', ['title' => $title]) : t('whatsapp_cta');
$phone = (string)($site['contact']['phone_e164'] ?? '');
?>
<a class="wa-float" href="<?= e($href) ?>" rel="noopener" target="_blank" aria-label="<?= e($label) ?>">
  <?= partial('icons', ['name' => 'whatsapp']) ?>
</a>
<div class="wa-bar" role="region" aria-label="<?= e(t('whatsapp_cta')) ?>">
  <a class="wa-bar__wa" href="<?= e($href) ?>" rel="noopener" target="_blank"><?= partial('icons', ['name' => 'whatsapp']) ?><span><?= e(t('whatsapp_short')) ?></span></a>
  <?php if ($phone !== ''): ?><a class="wa-bar__call" href="tel:<?= e($phone) ?>" aria-label="<?= e(t('call_us')) ?>"><?= partial('icons', ['name' => 'phone']) ?><span><?= e(t('call_now')) ?></span></a><?php endif; ?>
</div>
