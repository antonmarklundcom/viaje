<?php /** @var array $site — name, address, phone, WhatsApp, email and hours, all read from config['contact']. */
$c    = (array)($site['contact'] ?? []);
$addr = (array)($c['address'] ?? []);
$line = trim(implode(', ', array_filter([$addr['street'] ?? '', $addr['city'] ?? '', $addr['country_name'] ?? ''])), ', ');
?>
<section class="contact-card" aria-label="<?= e(t('contact_details')) ?>">
  <h2 class="section__title"><?= e(t('contact_details')) ?></h2>
  <ul class="contact__list">
    <?php if (!empty($c['phone_display'])): ?>
      <li><?= partial('icons', ['name' => 'phone']) ?><a href="tel:<?= e($c['phone_e164']) ?>"><?= e($c['phone_display']) ?></a></li>
    <?php endif; ?>
    <?php if (!empty($c['whatsapp_e164'])): ?>
      <li><?= partial('icons', ['name' => 'whatsapp']) ?><a href="<?= e(Leads::whatsappUrl()) ?>" rel="noopener" target="_blank"><?= e(t('whatsapp_cta')) ?></a></li>
    <?php endif; ?>
    <?php if (!empty($c['email'])): ?>
      <li><?= partial('icons', ['name' => 'mail']) ?><a href="mailto:<?= e($c['email']) ?>"><?= e($c['email']) ?></a></li>
    <?php endif; ?>
    <?php if ($line !== ''): ?>
      <li><?= partial('icons', ['name' => 'pin']) ?><span><?= e($line) ?><?php if (!empty($c['map_url'])): ?> · <a href="<?= e($c['map_url']) ?>" rel="noopener" target="_blank"><?= e(t('view_osm')) ?></a><?php endif; ?></span></li>
    <?php endif; ?>
    <?php if (!empty($c['hours'])): ?>
      <li><?= partial('icons', ['name' => 'clock']) ?><span><?= e(t('hours')) ?>: <?= e($c['hours']) ?></span></li>
    <?php endif; ?>
  </ul>
</section>
