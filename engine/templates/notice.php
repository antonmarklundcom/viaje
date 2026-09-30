<?php /** @var array $page — a one-off message page (newsletter confirmation), always noindex. */ ?>
<section class="error-page">
  <div class="container container--narrow">
    <h1 class="page__title"><?= e($page['title']) ?></h1>
    <p class="lede"><?= e($page['text'] ?? '') ?></p>
    <p class="error-page__actions">
      <a class="btn btn--primary" href="<?= e(url((string)($page['back'] ?? '/'))) ?>"><?= e(t('nl_confirm_back')) ?></a>
    </p>
  </div>
</section>
