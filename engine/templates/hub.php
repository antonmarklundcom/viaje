<?php /** @var array $page @var array $site @var array $items @var array $pager */
$hub = (array)($page['hub'] ?? []);
?>
<?= partial('breadcrumbs', ['trail' => [
    ['name' => t('home'), 'path' => '/'],
    ['name' => (string)$page['title'], 'path' => (string)($page['hub_path'] ?? '/')],
]]) ?>
<?php $hubText = ($page['intro'] ?? '') !== '' ? (string)$page['intro'] : (string)($page['description'] ?? ''); ?>
<?php if (!empty($hub['hero']) && (int)($pager['page'] ?? 1) === 1): ?>
  <?= partial('hero', ['site' => $site, 'page' => [
      'hero'       => (string)$hub['hero'],
      'hero_alt'   => (string)($hub['hero_alt'] ?? ''),
      'hero_title' => (string)$page['title'],
      'hero_text'  => $hubText,
  ]]) ?>
<?php else: ?>
<div class="container container--narrow hub__intro">
  <h1 class="page__title"><?= e($page['title']) ?><?= (int)($pager['page'] ?? 1) > 1 ? e(t('page_suffix', ['n' => (int)$pager['page']])) : '' ?></h1>
  <?php if ($hubText !== ''): ?><p class="lede"><?= e($hubText) ?></p><?php endif; ?>
</div>
<?php endif; ?>
<section class="section container">
  <?php if ($items): ?>
    <?= partial('cards', ['items' => $items]) ?>
    <?= partial('pagination', ['pager' => $pager, 'base' => (string)($page['hub_path'] ?? '/')]) ?>
  <?php else: ?>
    <p class="empty"><?= e(t('no_items')) ?></p>
  <?php endif; ?>
</section>
<?php if (!empty($hub['show_faq'])): ?>
  <div class="container container--narrow">
    <?= partial('faq-accordion', ['rows' => array_slice(Content::faq(['servicios', 'home']), 0, 6)]) ?>
  </div>
<?php endif; ?>
<section class="cta-band">
  <div class="container">
    <h2><?= e(t('form_title')) ?></h2>
    <p class="cta-band__actions">
      <a class="btn btn--wa" href="<?= e(Leads::whatsappUrl()) ?>" rel="noopener" target="_blank"><?= partial('icons', ['name' => 'whatsapp']) ?><?= e(t('whatsapp_cta')) ?></a>
      <a class="btn btn--ghost" href="<?= e(Router::contactPath()) ?>"><?= e(t('contact_us')) ?></a>
    </p>
  </div>
</section>
