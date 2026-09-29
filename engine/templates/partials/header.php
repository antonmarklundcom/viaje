<?php /** @var array $site @var array $page */
$current = (string)($page['path'] ?? '');
?>
<header class="site-header">
  <div class="container site-header__inner">
    <a class="brand" href="/" aria-label="<?= e($site['site_name']) ?>">
      <?php if (!empty($site['schema']['logo'])): ?>
        <img class="brand__logo" src="<?= e($site['schema']['logo']) ?>" alt="<?= e($site['site_name']) ?>" width="150" height="40">
      <?php else: ?>
        <span class="brand__text"><?= e($site['site_name']) ?></span>
      <?php endif; ?>
    </a>
    <a class="header__wa" href="<?= e(Leads::whatsappUrl()) ?>" rel="noopener" target="_blank" aria-label="<?= e(t('whatsapp_cta')) ?>"><?= partial('icons', ['name' => 'whatsapp']) ?></a>
    <button class="nav-toggle" type="button" aria-expanded="false" aria-controls="nav-principal" aria-label="<?= e(t('open_menu')) ?>">
      <span class="nav-toggle__bar"></span><span class="nav-toggle__bar"></span><span class="nav-toggle__bar"></span>
    </button>
    <nav id="nav-principal" class="nav" aria-label="<?= e(t('main_nav')) ?>">
      <ul class="nav__list">
        <?php foreach ((array)($site['nav'] ?? []) as $item): ?>
          <?php
            $href    = (string)($item['href'] ?? '/');
            $columns = (array)($item['columns'] ?? []);
            $flat    = (array)($item['children'] ?? []);
            $hasMenu = $columns !== [] || $flat !== [];
            $active  = $href === $current || ($href !== '/' && str_starts_with($current, $href));
          ?>
          <li<?= $hasMenu ? ' class="has-menu' . ($columns !== [] ? ' has-menu--mega' : '') . '"' : '' ?>>
            <a href="<?= e(url($href)) ?>"<?= $href === $current ? ' aria-current="page"' : '' ?>><?= e($item['label'] ?? '') ?></a>
            <?php if ($hasMenu): ?>
              <button class="nav__sub-toggle" type="button" aria-expanded="false" aria-label="<?= e(($item['label'] ?? '') . ' ▾') ?>"></button>
              <div class="submenu<?= $columns !== [] ? ' submenu--mega' : '' ?>">
                <?php if ($columns !== []): ?>
                  <?php foreach ($columns as $col): ?>
                    <div class="submenu__col">
                      <?php if (!empty($col['heading'])): ?><p class="submenu__heading"><?= e($col['heading']) ?></p><?php endif; ?>
                      <ul>
                        <?php foreach ((array)($col['links'] ?? []) as $l): ?>
                          <li><a href="<?= e(url((string)($l['href'] ?? '/'))) ?>"><?= e($l['label'] ?? '') ?><?php if (!empty($l['desc'])): ?><small><?= e($l['desc']) ?></small><?php endif; ?></a></li>
                        <?php endforeach; ?>
                      </ul>
                    </div>
                  <?php endforeach; ?>
                  <?php if (!empty($item['cta'])): ?>
                    <div class="submenu__cta">
                      <p class="submenu__heading"><?= e($item['cta']['heading'] ?? '') ?></p>
                      <p><?= e($item['cta']['text'] ?? '') ?></p>
                      <a class="btn btn--wa" href="<?= e(Leads::whatsappUrl()) ?>" rel="noopener" target="_blank"><?= partial('icons', ['name' => 'whatsapp']) ?><?= e(t('whatsapp_cta')) ?></a>
                    </div>
                  <?php endif; ?>
                <?php else: ?>
                  <ul>
                    <?php foreach ($flat as $l): ?>
                      <li><a href="<?= e(url((string)($l['href'] ?? '/'))) ?>"><?= e($l['label'] ?? '') ?></a></li>
                    <?php endforeach; ?>
                  </ul>
                <?php endif; ?>
              </div>
            <?php endif; ?>
          </li>
        <?php endforeach; ?>
      </ul>
      <div class="nav__cta">
        <?php if (!empty($site['contact']['phone_display'])): ?>
          <a class="nav__phone" href="tel:<?= e($site['contact']['phone_e164']) ?>"><?= partial('icons', ['name' => 'phone']) ?><?= e($site['contact']['phone_display']) ?></a>
        <?php endif; ?>
        <a class="btn btn--wa" href="<?= e(Leads::whatsappUrl()) ?>" rel="noopener" target="_blank"><?= partial('icons', ['name' => 'whatsapp']) ?><?= e(t('whatsapp_cta')) ?></a>
      </div>
    </nav>
  </div>
</header>
