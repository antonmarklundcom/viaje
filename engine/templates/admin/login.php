<?php /** @var string $error */ ?>
<div class="adm-card adm-card--narrow">
  <h1><?= e(t('admin_login')) ?></h1>
  <?php if (!empty($error)): ?><p class="adm-error"><?= e($error) ?></p><?php endif; ?>
  <form method="post" action="/admin/login">
    <label for="pw"><?= e(t('admin_password')) ?></label>
    <input id="pw" name="password" type="password" autocomplete="current-password" required autofocus>
    <button class="adm-btn adm-btn--primary" type="submit"><?= e(t('admin_login')) ?></button>
  </form>
</div>
