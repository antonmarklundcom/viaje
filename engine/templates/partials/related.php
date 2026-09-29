<?php /** @var array $page — "Te puede interesar": links to other activities, trips, services and guides. */
$items = Content::related($page, (int)($max ?? 5));
if ($items === []) { return; }
?>
<aside class="related" aria-labelledby="related-title">
  <h2 class="section__title" id="related-title"><?= e(t('related_interest')) ?></h2>
  <ul class="related__list">
    <?php foreach ($items as $item): ?>
      <li class="related__item">
        <a class="related__link" href="<?= e(url((string)$item['path'])) ?>">
          <span class="related__type"><?= e(Types::label((string)$item['type'])) ?></span>
          <span class="related__title"><?= e($item['title']) ?></span>
          <?php $ex = (string)(($item['excerpt'] ?? '') ?: ($item['description'] ?? '')); ?>
          <?php if ($ex !== ''): ?><span class="related__text"><?= e(Util::truncate($ex, 110)) ?></span><?php endif; ?>
        </a>
      </li>
    <?php endforeach; ?>
  </ul>
</aside>
