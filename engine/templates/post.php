<?php /** @var array $page @var array $site @var array $trail */
echo partial('breadcrumbs', ['trail' => $trail ?? []]);
$type      = (string)$page['type'];
$isArticle = in_array($type, ['post', 'news'], true);
$author    = Seo::author($page);
$modified  = ($page['updated'] ?? '') !== '' ? (string)$page['updated'] : (string)$page['date'];
$faq       = array_values(array_filter((array)($page['faq'] ?? []), 'is_array'));
?>
<article class="post">
  <div class="container container--narrow">
    <header class="post__header">
      <?php if (!empty($page['region'])): ?><p class="post__kicker"><?= e($page['region']) ?></p><?php endif; ?>
      <h1 class="page__title"><?= e($page['title']) ?></h1>
      <p class="post__meta">
        <time datetime="<?= e(Seo::isoDate($modified)) ?>"><?= e(t('updated_label')) ?>: <?= e(I18n::date($modified)) ?></time>
        <?php if ($modified !== (string)$page['date']): ?>
          · <time datetime="<?= e(Seo::isoDate((string)$page['date'])) ?>"><?= e(t('published_on')) ?> <?= e(I18n::date((string)$page['date'])) ?></time>
        <?php endif; ?>
        <?php if ($isArticle && $author['name'] !== ''): ?> · <span class="post__author"><?= e(t('by_author')) ?> <?= e($author['name']) ?></span><?php endif; ?>
        · <span class="post__reading"><?= e(t('reading_time')) ?>: <?= (int)($page['reading_time'] ?? 1) ?> <?= e(t('minutes')) ?></span>
      </p>
      <?= partial('wa-cta', ['page' => $page]) ?>
    </header>
    <?php if (($page['hero'] ?? '') !== ''): ?>
      <figure class="post__hero"><?= Images::picture((string)$page['hero'], (string)($page['hero_alt'] ?? ''), ['class' => 'post__hero-img', 'loading' => 'eager', 'fetchpriority' => 'high'], '(max-width: 900px) 100vw, 46rem') ?></figure>
    <?php endif; ?>
    <?= partial('quick-answer', ['page' => $page]) ?>
    <?php if (Types::hasFactbox($type)): ?><?= partial('factbox', ['page' => $page]) ?><?php endif; ?>
    <?php if (count((array)($page['headings'] ?? [])) >= 4): ?>
      <nav class="toc" aria-label="<?= e(t('on_this_page')) ?>">
        <p class="toc__title"><?= e(t('on_this_page')) ?></p>
        <ol>
          <?php foreach ((array)$page['headings'] as $h): ?>
            <?php if ((int)$h['level'] !== 2) { continue; } ?>
            <li><a href="#<?= e($h['id']) ?>"><?= e($h['text']) ?></a></li>
          <?php endforeach; ?>
        </ol>
      </nav>
    <?php endif; ?>
    <div class="prose"><?= $page['html'] ?></div>
    <?php if (Types::hasFactbox($type)): ?><?= partial('itinerary', ['page' => $page]) ?><?php endif; ?>
    <?php if (!empty($page['source_url'])): ?>
      <p class="post__source"><?= e(t('source')) ?>: <a href="<?= e($page['source_url']) ?>" rel="noopener" target="_blank"><?= e($page['source_name'] ?? $page['source_url']) ?></a></p>
    <?php endif; ?>
    <?php if ($faq): ?>
      <?= partial('faq-accordion', ['rows' => $faq, 'heading' => t('faq_about', ['title' => (string)$page['title']])]) ?>
    <?php endif; ?>
    <?php if ($isArticle): ?><?= partial('author-box', ['page' => $page]) ?><?php endif; ?>
  </div>
</article>
<div class="container container--narrow">
  <?= partial('related', ['page' => $page]) ?>
</div>
<section class="guide-lead">
  <div class="container container--narrow">
    <?= partial('lead-form', [
        'page'    => $page,
        'site'    => $site,
        'variant' => 'short',
        'heading' => t('lead_guide_title'),
        'intro'   => t('lead_guide_text'),
    ]) ?>
  </div>
</section>
<?php if ($isArticle): ?>
<div class="container container--narrow">
  <?= partial('newsletter', ['page' => $page]) ?>
</div>
<?php endif; ?>
