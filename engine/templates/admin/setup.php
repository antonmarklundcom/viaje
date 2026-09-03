<div class="adm-card adm-card--narrow">
  <h1><?= e(t('admin_setup')) ?></h1>
  <p>No <code>admin_password_hash</code> is configured, so the admin is disabled. The public site is unaffected.</p>
  <ol class="adm-steps">
    <li>Run <code>php engine/bin/hash-password.php</code> and copy the line it prints.</li>
    <li>Paste it into <code>site/config.local.php</code> (copy <code>config.local.example.php</code> if it does not exist yet).</li>
    <li>Reload this page.</li>
  </ol>
</div>
