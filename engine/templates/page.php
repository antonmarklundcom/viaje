<?php /** @var array $page @var array $site @var array $trail */
echo partial('breadcrumbs', ['trail' => $trail ?? []]);
?>
<article class="page">
  <div class="container container--narrow">
    <h1 class="page__title"><?= e($page['title']) ?></h1>
    <?php if (($page['hero'] ?? '') !== ''): ?>
      <figure class="page__hero"><?= Images::picture((string)$page['hero'], (string)($page['hero_alt'] ?? ''), ['class' => 'page__hero-img', 'loading' => 'eager', 'fetchpriority' => 'high'], '(max-width: 900px) 100vw, 46rem') ?></figure>
    <?php endif; ?>
    <?= partial('quick-answer', ['page' => $page]) ?>
    <div class="prose"><?= $page['html'] ?></div>
    <?php if (!empty($page['show_contact'])): ?><?= partial('contact-details', ['site' => $site]) ?><?php endif; ?>
    <?php if (!empty($page['show_team'])): ?><?= partial('team', ['rows' => Content::data('team')]) ?><?php endif; ?>
    <?php if (!empty($page['faq_tags'])): ?><?= partial('faq-accordion', ['rows' => Content::faq((array)$page['faq_tags'])]) ?><?php endif; ?>
  </div>
</article>
<?= partial('cta', ['page' => $page, 'site' => $site]) ?>
