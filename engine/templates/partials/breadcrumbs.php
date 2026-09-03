<?php /** @var list<array{name:string,path:string}> $trail */
$trail = (array)($trail ?? []);
if (count($trail) < 2) { return; }
?>
<nav class="breadcrumbs" aria-label="<?= e(t('breadcrumb')) ?>">
  <div class="container">
    <ol>
      <?php $last = count($trail) - 1; foreach ($trail as $i => $step): ?>
        <li>
          <?php if ($i === $last): ?>
            <span aria-current="page"><?= e($step['name']) ?></span>
          <?php else: ?>
            <a href="<?= e(url($step['path'])) ?>"><?= e($step['name']) ?></a>
          <?php endif; ?>
        </li>
      <?php endforeach; ?>
    </ol>
  </div>
</nav>
