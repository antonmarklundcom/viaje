<?php /** @var array $item */
$item = (array)($item ?? []);
$href = url((string)($item['path'] ?? '/'));
$hero = (string)($item['hero'] ?? '');
?>
<article class="card">
  <?php if ($hero !== ''): ?>
    <a class="card__media" href="<?= e($href) ?>" tabindex="-1" aria-hidden="true">
      <?= Images::picture($hero, (string)($item['hero_alt'] ?? ''), ['class' => 'card__img'], '(max-width: 700px) 100vw, 380px') ?>
    </a>
  <?php endif; ?>
  <div class="card__body">
    <?php if (!empty($item['region'])): ?><p class="card__kicker"><?= e($item['region']) ?></p><?php endif; ?>
    <h3 class="card__title"><a href="<?= e($href) ?>"><?= e($item['title'] ?? '') ?></a></h3>
    <?php $ex = (string)(($item['excerpt'] ?? '') ?: ($item['description'] ?? '')); ?>
    <?php if ($ex !== ''): ?><p class="card__text"><?= e(Util::truncate($ex, 150)) ?></p><?php endif; ?>
    <p class="card__more"><a class="link-more" href="<?= e($href) ?>"><?= e(($item['type'] ?? '') === 'service' ? t('view_service') : t('read_more')) ?><?= partial('icons', ['name' => 'arrow']) ?></a></p>
  </div>
</article>
