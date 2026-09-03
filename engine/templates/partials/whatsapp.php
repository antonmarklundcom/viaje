<?php /** @var array $site @var array $page */
$title = (string)($page['title'] ?? '');
$text  = (string)($site['contact']['whatsapp_default_text'] ?? '');
if ($title !== '' && in_array((string)($page['type'] ?? ''), ['service', 'trip', 'activity'], true)) {
    $text = trim($text) . ' ' . $title;
}
?>
<a class="wa-float" href="<?= e(Leads::whatsappUrl($text ?: null)) ?>" rel="noopener" target="_blank"
   aria-label="<?= e($title !== '' ? t('whatsapp_about', ['title' => $title]) : t('whatsapp_cta')) ?>">
  <?= partial('icons', ['name' => 'whatsapp']) ?>
</a>
