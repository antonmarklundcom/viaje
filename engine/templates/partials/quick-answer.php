<?php /** @var array $page — front matter `quick_answer`: a short, self-contained answer to the page's main question. */
$qa = trim((string)($page['quick_answer'] ?? ''));
if ($qa === '') { return; }
?>
<aside class="quick" aria-label="<?= e(t('quick_answer')) ?>">
  <p class="quick__label"><?= e(t('quick_answer')) ?></p>
  <div class="quick__body"><?= Markdown::small($qa) ?></div>
</aside>
