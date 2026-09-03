<?php /** @var list<array> $items @var string|null $heading */
$items = (array)($items ?? []);
if ($items === []) { return; }
?>
<?php if (!empty($heading)): ?><h2 class="section__title"><?= e($heading) ?></h2><?php endif; ?>
<div class="card-grid">
  <?php foreach ($items as $item): ?><?= partial('card', ['item' => $item]) ?><?php endforeach; ?>
</div>
