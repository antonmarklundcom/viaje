<?php /** @var array $page @var array $site */ ?>
<section class="error-page">
  <div class="container container--narrow">
    <p class="error-page__code"><?= e((string)($page['status'] ?? 404)) ?></p>
    <h1 class="page__title"><?= e($page['title']) ?></h1>
    <p class="lede"><?= e($page['text'] ?? '') ?></p>
    <p class="error-page__actions">
      <a class="btn btn--primary" href="/"><?= e(t('404_cta')) ?></a>
      <a class="btn btn--wa" href="<?= e(Leads::whatsappUrl()) ?>" rel="noopener" target="_blank"><?= partial('icons', ['name' => 'whatsapp']) ?><?= e(t('whatsapp_cta')) ?></a>
    </p>
  </div>
</section>
