<?php /** @var array $page @var array $site @var array $trail */
echo partial('breadcrumbs', ['trail' => $trail ?? []]);
$type    = (string)$page['type'];
$related = Content::listType($type, ['limit' => 3, 'exclude' => (string)$page['path']]);
$author  = (string)($page['author'] ?? '') !== '' ? (string)$page['author'] : (string)($site['author_default']['name'] ?? '');
?>
<article class="post">
  <div class="container container--narrow">
    <header class="post__header">
      <?php if (!empty($page['region'])): ?><p class="post__kicker"><?= e($page['region']) ?></p><?php endif; ?>
      <h1 class="page__title"><?= e($page['title']) ?></h1>
      <p class="post__meta">
        <time datetime="<?= e(Seo::isoDate((string)$page['date'])) ?>"><?= e(t('published_on')) ?> <?= e($page['date']) ?></time>
        <?php if (($page['updated'] ?? '') !== '' && $page['updated'] !== $page['date']): ?>
          · <time datetime="<?= e(Seo::isoDate((string)$page['updated'])) ?>"><?= e(t('updated_on')) ?> <?= e($page['updated']) ?></time>
        <?php endif; ?>
        <?php if ($author !== ''): ?> · <span class="post__author"><?= e(t('by_author')) ?> <?= e($author) ?></span><?php endif; ?>
        · <span class="post__reading"><?= e(t('reading_time')) ?>: <?= (int)($page['reading_time'] ?? 1) ?> <?= e(t('minutes')) ?></span>
      </p>
    </header>
    <?php if (($page['hero'] ?? '') !== ''): ?>
      <figure class="post__hero"><?= Images::picture((string)$page['hero'], (string)($page['hero_alt'] ?? ''), ['class' => 'post__hero-img', 'loading' => 'eager', 'fetchpriority' => 'high']) ?></figure>
    <?php endif; ?>
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
  </div>
</article>
<?php if ($related): ?>
<section class="section container">
  <?= partial('cards', ['items' => $related, 'heading' => t('related_title')]) ?>
</section>
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
