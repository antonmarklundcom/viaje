<?php /** @var array $page @var array $site @var string|null $variant 'short' = name, WhatsApp and a prefilled message (end of guides) */
$short   = ($variant ?? '') === 'short';
$topics  = Leads::topics();
$sent    = ($_GET['enviado'] ?? '') === '1';
$err     = ($_GET['error'] ?? '') === '1';
$pTitle  = (string)($page['title'] ?? '');
$pPath   = (string)($page['path'] ?? '/');
// The page the visitor is on travels with the lead: hidden fields for the record, and the same
// title + URL appended to the WhatsApp text (assets/site.js keeps it in sync while they type).
$context = ($pTitle !== '' && ($page['layout'] ?? '') !== 'contact') ? $pTitle . ' (' . abs_url($pPath) . ')' : '';
$waHref  = Leads::whatsappUrl(Leads::pageText($page));
?>
<section class="lead" id="formulario">
  <?php if ($sent): ?>
    <div class="notice notice--ok" role="status">
      <p class="notice__title"><?= e(t('form_ok_title')) ?></p>
      <p><?= e(t('form_ok_text')) ?></p>
      <p><a class="btn btn--wa" href="<?= e($waHref) ?>" rel="noopener" target="_blank"><?= partial('icons', ['name' => 'whatsapp']) ?><?= e(t('whatsapp_cta')) ?></a></p>
    </div>
  <?php endif; ?>
  <?php if ($err): ?>
    <div class="notice notice--error" role="alert">
      <p class="notice__title"><?= e(t('form_error_title')) ?></p>
      <p><?= e(Util::truncate((string)($_GET['msg'] ?? ''), 240)) ?></p>
    </div>
  <?php endif; ?>

  <h2 class="section__title"><?= e(!empty($heading) ? (string)$heading : t('form_title')) ?></h2>
  <?php if (!empty($intro)): ?><p class="lead__intro"><?= e((string)$intro) ?></p><?php endif; ?>
  <form class="lead__form<?= $short ? ' lead__form--short' : '' ?>" method="post" action="/enviar/" novalidate>
    <p class="lead__field">
      <label for="lf-name"><?= e(t('form_name')) ?> <span class="req" aria-hidden="true">*</span></label>
      <input id="lf-name" name="name" type="text" required autocomplete="name" maxlength="120">
    </p>
    <p class="lead__field">
      <label for="lf-phone"><?= e(t('form_phone')) ?> <span class="req" aria-hidden="true">*</span></label>
      <input id="lf-phone" name="phone" type="tel" inputmode="tel" required autocomplete="tel" maxlength="40">
    </p>
    <?php if ($short): ?>
      <input type="hidden" name="topic" id="lf-topic" value="<?= e(t('lead_topic_page', ['title' => $pTitle])) ?>">
    <?php else: ?>
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
    <?php endif; ?>
    <p class="lead__field">
      <label for="lf-message"><?= e(t('form_message')) ?> <span class="req" aria-hidden="true">*</span></label>
      <textarea id="lf-message" name="message" rows="<?= $short ? 3 : 5 ?>" required maxlength="3000"><?= $short ? e(t('lead_guide_message', ['title' => $pTitle])) : '' ?></textarea>
    </p>
    <p class="lead__hp" aria-hidden="true">
      <label for="lf-website">Website</label>
      <input id="lf-website" name="website" type="text" tabindex="-1" autocomplete="off">
    </p>
    <input type="hidden" name="ts" value="<?= e(Leads::stamp()) ?>">
    <input type="hidden" name="page" value="<?= e($pPath) ?>">
    <input type="hidden" name="page_title" value="<?= e($pTitle) ?>">
    <p class="lead__actions">
      <button class="btn btn--primary" type="submit"><?= e(t('form_submit')) ?></button>
      <a class="btn btn--wa" id="lf-wa" href="<?= e($waHref) ?>" data-context="<?= e($context) ?>" rel="noopener" target="_blank"><?= partial('icons', ['name' => 'whatsapp']) ?><?= e(t('whatsapp_cta')) ?></a>
    </p>
    <p class="lead__privacy"><?= e(t('form_privacy')) ?></p>
  </form>
</section>
