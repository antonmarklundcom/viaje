<?php /** @var list<array> $rows */
$rows = (array)($rows ?? []);
if ($rows === []) { return; }
?>
<section class="team">
  <h2 class="section__title"><?= e(t('team_title')) ?></h2>
  <ul class="team__grid">
    <?php foreach ($rows as $row): ?>
      <li class="team__member">
        <?php if (!empty($row['photo'])): ?><?= Images::picture((string)$row['photo'], (string)($row['name'] ?? ''), ['class' => 'team__photo'], '160px') ?><?php endif; ?>
        <p class="team__name"><?= e($row['name'] ?? '') ?></p>
        <?php if (!empty($row['role'])): ?><p class="team__role"><?= e($row['role']) ?></p><?php endif; ?>
        <?php if (!empty($row['bio'])): ?><p class="team__bio"><?= e($row['bio']) ?></p><?php endif; ?>
      </li>
    <?php endforeach; ?>
  </ul>
</section>
