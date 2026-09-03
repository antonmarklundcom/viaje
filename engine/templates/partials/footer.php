<?php /** @var array $site */
$c = (array)($site['contact'] ?? []);
$socials = array_filter((array)($site['socials'] ?? []));
?>
<footer class="site-footer">
  <div class="container site-footer__grid">
    <div class="site-footer__brand">
      <p class="site-footer__name"><?= e($site['site_name']) ?></p>
      <?php if (!empty($site['footer_blurb'])): ?><p class="site-footer__blurb"><?= e($site['footer_blurb']) ?></p><?php endif; ?>
      <?php if ($socials): ?>
        <p class="site-footer__socials"><span class="visually-hidden"><?= e(t('follow_us')) ?></span>
          <?php foreach ($socials as $name => $href): ?>
            <a href="<?= e($href) ?>" rel="noopener" target="_blank" aria-label="<?= e(ucfirst((string)$name)) ?>"><?= partial('icons', ['name' => (string)$name]) ?></a>
          <?php endforeach; ?>
        </p>
      <?php endif; ?>
    </div>
    <nav class="site-footer__nav" aria-label="<?= e(t('footer_nav')) ?>">
      <ul>
        <?php foreach ((array)($site['footer_nav'] ?: $site['nav']) as $item): ?>
          <li><a href="<?= e(url((string)($item['href'] ?? '/'))) ?>"><?= e($item['label'] ?? '') ?></a></li>
        <?php endforeach; ?>
      </ul>
    </nav>
    <div class="site-footer__contact">
      <p class="site-footer__label"><?= e(t('contact_us')) ?></p>
      <ul>
        <?php if (!empty($c['phone_display'])): ?><li><a href="tel:<?= e($c['phone_e164']) ?>"><?= e($c['phone_display']) ?></a></li><?php endif; ?>
        <?php if (!empty($c['email'])): ?><li><a href="mailto:<?= e($c['email']) ?>"><?= e($c['email']) ?></a></li><?php endif; ?>
        <?php if (!empty($c['address']['street'])): ?><li><?= e(trim(($c['address']['street'] ?? '') . ', ' . ($c['address']['city'] ?? ''), ', ')) ?></li><?php endif; ?>
        <?php if (!empty($c['hours'])): ?><li><?= e(t('hours')) ?>: <?= e($c['hours']) ?></li><?php endif; ?>
      </ul>
    </div>
  </div>
  <div class="container site-footer__legal">
    <p>© <?= date('Y') ?> <?= e($site['site_name']) ?>. <?= e(t('all_rights')) ?></p>
  </div>
</footer>
