<?php /** @var list<array> $rows */
$rows = (array)($rows ?? []);
if ($rows === []) { return; }
?>
<section class="gallery">
  <h2 class="section__title"><?= e(t('gallery_title')) ?></h2>
  <div class="gallery__grid">
    <?php foreach ($rows as $row): ?>
      <?php if (($row['src'] ?? '') === '') { continue; } ?>
      <figure class="gallery__item">
        <?= Images::picture((string)$row['src'], (string)($row['alt'] ?? ''), ['class' => 'gallery__img'], '(max-width: 700px) 50vw, 300px') ?>
        <?php if (!empty($row['caption'])): ?><figcaption><?= e($row['caption']) ?></figcaption><?php endif; ?>
      </figure>
    <?php endforeach; ?>
  </div>
</section>
