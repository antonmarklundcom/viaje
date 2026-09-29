<?php /** @var array $page — byline card with the author's short bio (config['authors']). */
$a = Seo::author($page);
if ($a['name'] === '') { return; }
$more = $a['url'] !== '' ? $a['url'] : '';
?>
<aside class="author-box" aria-label="<?= e(t('about_author')) ?>">
  <p class="author-box__label"><?= e(t('about_author')) ?></p>
  <p class="author-box__name"><?= e($a['name']) ?><?php if ($a['role'] !== ''): ?> <span class="author-box__role">· <?= e($a['role']) ?></span><?php endif; ?></p>
  <?php if ($a['bio'] !== ''): ?><p class="author-box__bio"><?= e($a['bio']) ?></p><?php endif; ?>
  <?php if ($more !== ''): ?><p class="author-box__more"><a class="link-more" href="<?= e(url($more)) ?>"><?= e(t('author_more')) ?><?= partial('icons', ['name' => 'arrow']) ?></a></p><?php endif; ?>
</aside>
