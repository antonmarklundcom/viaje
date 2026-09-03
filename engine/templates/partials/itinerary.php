<?php /** @var array $page */
$steps = array_filter((array)($page['itinerary'] ?? []), 'is_array');
if ($steps === []) { return; }
?>
<section class="itinerary">
  <h2 class="section__title"><?= e(t('itinerary_title')) ?></h2>
  <ol class="itinerary__list">
    <?php foreach ($steps as $i => $step): ?>
      <li class="itinerary__step">
        <p class="itinerary__day"><?= e(($step['day'] ?? '') !== '' ? (string)$step['day'] : t('day') . ' ' . ($i + 1)) ?></p>
        <?php if (!empty($step['title'])): ?><h3 class="itinerary__title"><?= e($step['title']) ?></h3><?php endif; ?>
        <?php if (!empty($step['text'])): ?><p><?= e($step['text']) ?></p><?php endif; ?>
      </li>
    <?php endforeach; ?>
  </ol>
</section>
