<?php /** @var array $page */
$hero = (string)($page['hero'] ?? '');
$kicker = (string)($page['hero_kicker'] ?? ($page['region'] ?? ''));
$title  = (string)($page['hero_title'] ?? $page['title'] ?? '');
$text   = (string)($page['hero_text'] ?? '');
$cta1   = (string)($page['hero_cta_label'] ?? '');
$cta2   = (string)($page['hero_secondary_label'] ?? '');
?>
<section class="hero<?= $hero !== '' ? ' hero--image' : '' ?>">
  <?php if ($hero !== ''): ?>
    <div class="hero__media">
      <?= Images::picture($hero, (string)($page['hero_alt'] ?? ''), ['class' => 'hero__img', 'loading' => 'eager', 'fetchpriority' => 'high'], '100vw') ?>
    </div>
  <?php endif; ?>
  <div class="container hero__inner">
    <?php if ($kicker !== ''): ?><p class="hero__kicker"><?= e($kicker) ?></p><?php endif; ?>
    <h1 class="hero__title"><?= e($title) ?></h1>
    <?php if ($text !== ''): ?><p class="hero__text"><?= e($text) ?></p><?php endif; ?>
    <?php if ($cta1 !== '' || $cta2 !== ''): ?>
      <p class="hero__ctas">
        <?php if ($cta1 !== ''): ?><a class="btn btn--primary" href="<?= e(url((string)($page['hero_cta_href'] ?? '/'))) ?>"><?= e($cta1) ?></a><?php endif; ?>
        <?php if ($cta2 !== ''): ?><a class="btn btn--ghost" href="<?= e(url((string)($page['hero_secondary_href'] ?? '/'))) ?>"><?= e($cta2) ?></a><?php endif; ?>
      </p>
    <?php endif; ?>
  </div>
</section>
