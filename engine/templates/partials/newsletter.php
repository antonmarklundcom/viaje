<?php /** @var array $page — email signup, stored in data/leads/newsletter.jsonl (no third-party service). */
$ok  = ($_GET['suscrito'] ?? '') === '1';
$err = ($_GET['suscripcion'] ?? '') === 'error';
?>
<section class="newsletter" id="suscribirse" aria-labelledby="nl-title">
  <?php if ($ok): ?>
    <div class="notice notice--ok" role="status">
      <p class="notice__title"><?= e(t('nl_ok_title')) ?></p>
      <p><?= e(t('nl_ok_text')) ?></p>
    </div>
  <?php endif; ?>
  <?php if ($err): ?>
    <div class="notice notice--error" role="alert">
      <p class="notice__title"><?= e(t('nl_error_title')) ?></p>
      <p><?= e(Util::truncate((string)($_GET['msg'] ?? ''), 240)) ?></p>
    </div>
  <?php endif; ?>
  <h2 class="newsletter__title" id="nl-title"><?= e(t('nl_title')) ?></h2>
  <p><?= e(t('nl_text')) ?></p>
  <form class="newsletter__form" method="post" action="/suscribir/" novalidate>
    <p class="newsletter__field">
      <label class="visually-hidden" for="nl-email"><?= e(t('nl_email')) ?></label>
      <input id="nl-email" name="email" type="email" required autocomplete="email" maxlength="160" placeholder="<?= e(t('nl_email')) ?>">
    </p>
    <p class="lead__hp" aria-hidden="true">
      <label for="nl-website">Website</label>
      <input id="nl-website" name="website" type="text" tabindex="-1" autocomplete="off">
    </p>
    <input type="hidden" name="ts" value="<?= e(Leads::stamp()) ?>">
    <input type="hidden" name="page" value="<?= e((string)($page['path'] ?? '/')) ?>">
    <button class="btn btn--primary" type="submit"><?= e(t('nl_submit')) ?></button>
  </form>
  <p class="newsletter__privacy"><?= e(t('nl_privacy')) ?></p>
</section>
