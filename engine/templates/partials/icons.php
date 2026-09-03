<?php
/** Inline SVG icon set. @var string $name */
$icons = [
    'compass'  => '<circle cx="12" cy="12" r="9"/><polygon points="15.5,8.5 10.5,10.5 8.5,15.5 13.5,13.5"/>',
    'map'      => '<polygon points="3,6 9,3 15,6 21,3 21,18 15,21 9,18 3,21"/><path d="M9 3v15M15 6v15"/>',
    'shield'   => '<path d="M12 3l7 3v6c0 4-3 7-7 9-4-2-7-5-7-9V6z"/><path d="M9 12l2 2 4-4"/>',
    'chat'     => '<path d="M21 12a8 8 0 0 1-8 8H7l-4 3 1-5a8 8 0 1 1 17-6z"/>',
    'car'      => '<path d="M4 15l1.5-5A2 2 0 0 1 7.4 8.6h9.2a2 2 0 0 1 1.9 1.4L20 15"/><rect x="3" y="15" width="18" height="4" rx="1"/><circle cx="7.5" cy="19" r="1.5"/><circle cx="16.5" cy="19" r="1.5"/>',
    'passport' => '<rect x="5" y="3" width="14" height="18" rx="2"/><circle cx="12" cy="10" r="3"/><path d="M9 17h6"/>',
    'sun'      => '<circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M2 12h2M20 12h2M4.9 4.9l1.4 1.4M17.7 17.7l1.4 1.4M19.1 4.9l-1.4 1.4M6.3 17.7l-1.4 1.4"/>',
    'star'     => '<polygon points="12,3 14.6,9 21,9.6 16.2,13.9 17.6,20.2 12,17 6.4,20.2 7.8,13.9 3,9.6 9.4,9"/>',
    'phone'    => '<path d="M5 4h4l2 5-2.5 1.5a12 12 0 0 0 5 5L15 13l5 2v4a2 2 0 0 1-2.2 2A16 16 0 0 1 3 6.2 2 2 0 0 1 5 4z"/>',
    'mail'     => '<rect x="3" y="5" width="18" height="14" rx="2"/><path d="M3 7l9 6 9-6"/>',
    'clock'    => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
    'pin'      => '<path d="M12 21s7-6 7-11a7 7 0 1 0-14 0c0 5 7 11 7 11z"/><circle cx="12" cy="10" r="2.5"/>',
    'arrow'    => '<path d="M5 12h14M13 6l6 6-6 6"/>',
    'whatsapp' => '<path d="M12 3a9 9 0 0 0-7.8 13.5L3 21l4.7-1.2A9 9 0 1 0 12 3z"/><path d="M8.8 8.3c.2-.5.4-.5.6-.5h.5c.2 0 .4 0 .6.5l.7 1.6c.1.2 0 .4-.1.6l-.4.5c-.1.2-.2.3 0 .6.3.5.9 1.3 1.8 1.8.4.2.6.2.8 0l.5-.6c.2-.2.3-.2.6-.1l1.5.7c.3.1.4.3.4.5s0 .8-.3 1.1c-.3.3-.9.7-1.6.7-1 0-2.6-.7-4-2.1-1.4-1.4-2.2-3-2.2-4 0-.7.4-1.3.6-1.6z"/>',
    'instagram'=> '<rect x="3" y="3" width="18" height="18" rx="5"/><circle cx="12" cy="12" r="4"/><circle cx="17.2" cy="6.8" r="1"/>',
    'facebook' => '<path d="M14 8h2V5h-2a4 4 0 0 0-4 4v2H8v3h2v7h3v-7h2.3l.7-3H13V9a1 1 0 0 1 1-1z"/>',
    'tiktok'   => '<path d="M14 4v9.5a3 3 0 1 1-3-3"/><path d="M14 4c.6 2.2 2.1 3.5 4.5 3.7"/>',
    'youtube'  => '<rect x="3" y="6" width="18" height="12" rx="4"/><polygon points="11,10 15,12 11,14"/>',
];
$name = (string)($name ?? '');
$d    = $icons[$name] ?? $icons['arrow'];
?>
<svg class="icon icon--<?= e($name) ?>" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"
     stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false" width="20" height="20"><?= $d ?></svg>
