<?php /** @var list<array> $rows */
$rows = (array)($rows ?? []);
if ($rows === []) { return; }
?>
<section class="testimonials">
  <h2 class="section__title"><?= e(t('testimonials_title')) ?></h2>
  <div class="testimonials__grid">
    <?php foreach ($rows as $row): ?>
      <figure class="testimonial">
        <?php $r = (int)($row['rating'] ?? 0); if ($r > 0): ?>
          <p class="testimonial__rating" aria-label="<?= $r ?>/5"><?php for ($i = 0; $i < min(5, $r); $i++) { echo partial('icons', ['name' => 'star']); } ?></p>
        <?php endif; ?>
        <blockquote><p><?= e($row['text'] ?? '') ?></p></blockquote>
        <figcaption><?= e($row['name'] ?? '') ?><?php if (!empty($row['trip'])): ?> <span class="testimonial__trip">· <?= e($row['trip']) ?></span><?php endif; ?></figcaption>
      </figure>
    <?php endforeach; ?>
  </div>
</section>
