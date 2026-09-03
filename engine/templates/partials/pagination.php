<?php /** @var array $pager @var string $base */
$pager = (array)($pager ?? []);
$base  = (string)($base ?? '/');
if ((int)($pager['pages'] ?? 1) < 2) { return; }
$link = static fn(int $n): string => $n <= 1 ? $base : $base . 'page/' . $n . '/';
$cur  = (int)$pager['page'];
?>
<nav class="pagination" aria-label="<?= e(t('pagination')) ?>">
  <?php if ($cur > 1): ?><a class="pagination__prev" rel="prev" href="<?= e($link($cur - 1)) ?>"><?= e(t('prev_page')) ?></a><?php endif; ?>
  <ol class="pagination__list">
    <?php for ($n = 1; $n <= (int)$pager['pages']; $n++): ?>
      <li><?php if ($n === $cur): ?><span aria-current="page"><?= $n ?></span><?php else: ?><a href="<?= e($link($n)) ?>"><?= $n ?></a><?php endif; ?></li>
    <?php endfor; ?>
  </ol>
  <?php if ($cur < (int)$pager['pages']): ?><a class="pagination__next" rel="next" href="<?= e($link($cur + 1)) ?>"><?= e(t('next_page')) ?></a><?php endif; ?>
</nav>
