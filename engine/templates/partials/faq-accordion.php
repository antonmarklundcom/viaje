<?php /** @var list<array> $rows @var string|null $heading */
$rows = (array)($rows ?? []);
if ($rows === []) { return; }
?>
<section class="faq">
  <?php $heading = $heading ?? t('faq_title'); ?>
  <?php if ($heading !== false): ?><h2 class="section__title"><?= e($heading) ?></h2><?php endif; ?>
  <div class="faq__list">
    <?php foreach ($rows as $row): ?>
      <?php if (($row['q'] ?? '') === '') { continue; } ?>
      <details class="faq__item">
        <summary><?= e($row['q']) ?><span class="faq__mark" aria-hidden="true"></span></summary>
        <div class="faq__answer prose"><?= Markdown::small((string)($row['a'] ?? '')) ?></div>
      </details>
    <?php endforeach; ?>
  </div>
  <?php if ($heading !== false && ($faqPath = Router::faqPath()) !== null): ?>
    <p class="faq__more"><a class="link-more" href="<?= e($faqPath) ?>"><?= e(t('faq_all')) ?><?= partial('icons', ['name' => 'arrow']) ?></a></p>
  <?php endif; ?>
</section>
