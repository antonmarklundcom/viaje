<?php /** @var array $page @var array $site */
$topics = Leads::topics();
$sent   = ($_GET['enviado'] ?? '') === '1';
$err    = ($_GET['error'] ?? '') === '1';
?>
<section class="lead" id="formulario">
  <?php if ($sent): ?>
    <div class="notice notice--ok" role="status">
      <p class="notice__title"><?= e(t('form_ok_title')) ?></p>
      <p><?= e(t('form_ok_text')) ?></p>
      <p><a class="btn btn--wa" href="<?= e(Leads::whatsappUrl()) ?>" rel="noopener" target="_blank"><?= partial('icons', ['name' => 'whatsapp']) ?><?= e(t('whatsapp_cta')) ?></a></p>
    </div>
  <?php endif; ?>
  <?php if ($err): ?>
    <div class="notice notice--error" role="alert">
      <p class="notice__title"><?= e(t('form_error_title')) ?></p>
      <p><?= e(Util::truncate((string)($_GET['msg'] ?? ''), 240)) ?></p>
    </div>
  <?php endif; ?>

  <h2 class="section__title"><?= e(t('form_title')) ?></h2>
  <form class="lead__form" method="post" action="/enviar/" novalidate>
    <p class="lead__field">
      <label for="lf-name"><?= e(t('form_name')) ?> <span class="req" aria-hidden="true">*</span></label>
      <input id="lf-name" name="name" type="text" required autocomplete="name" maxlength="120">
    </p>
    <p class="lead__field">
      <label for="lf-phone"><?= e(t('form_phone')) ?> <span class="req" aria-hidden="true">*</span></label>
      <input id="lf-phone" name="phone" type="tel" inputmode="tel" required autocomplete="tel" maxlength="40">
    </p>
    <p class="lead__field">
      <label for="lf-email"><?= e(t('form_email')) ?></label>
      <input id="lf-email" name="email" type="email" autocomplete="email" maxlength="160">
    </p>
    <p class="lead__field">
      <label for="lf-topic"><?= e(t('form_topic')) ?></label>
      <select id="lf-topic" name="topic">
        <?php foreach ($topics as $topic): ?><option value="<?= e($topic) ?>"><?= e($topic) ?></option><?php endforeach; ?>
      </select>
    </p>
    <p class="lead__field">
      <label for="lf-message"><?= e(t('form_message')) ?> <span class="req" aria-hidden="true">*</span></label>
      <textarea id="lf-message" name="message" rows="5" required maxlength="3000"></textarea>
    </p>
    <p class="lead__hp" aria-hidden="true">
      <label for="lf-website">Website</label>
      <input id="lf-website" name="website" type="text" tabindex="-1" autocomplete="off">
    </p>
    <input type="hidden" name="ts" value="<?= e(Leads::stamp()) ?>">
    <input type="hidden" name="page" value="<?= e((string)($page['path'] ?? '/')) ?>">
    <p class="lead__actions">
      <button class="btn btn--primary" type="submit"><?= e(t('form_submit')) ?></button>
      <a class="btn btn--wa" id="lf-wa" href="<?= e(Leads::whatsappUrl()) ?>" rel="noopener" target="_blank"><?= partial('icons', ['name' => 'whatsapp']) ?><?= e(t('whatsapp_cta')) ?></a>
    </p>
    <p class="lead__privacy"><?= e(t('form_privacy')) ?></p>
  </form>
</section>
