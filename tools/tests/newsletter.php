<?php
declare(strict_types=1);

/**
 * tools/tests/newsletter.php — the double opt-in paths verify.php cannot reach:
 *   - mail() fails → the visitor is told so (no success notice) and the pending row is kept;
 *   - pending rows older than 7 days are pruned on the next write;
 *   - a link signed for a day more than 7 days ago is rejected, one from 7 days ago is accepted.
 * verify.php covers the happy path (signup → email → confirm → replay). Exit 0 = pass.
 */

require __DIR__ . '/lib.php';

$dist  = t_build('viaje.com.py');
$leads = $dist . '/site/data/leads';
@mkdir($leads, 0775, true);
$pendingFile = $leads . '/newsletter-pending.jsonl';

// A stale pending row (8 days old) that the next write must prune, and a 7-day-old one that must survive.
$old = time() - 8 * 86400;
$wk  = time() - 7 * 86400 + 600;
file_put_contents($pendingFile,
    json_encode(['email' => 'stale@example.com', 'status' => 'pending', 'ts' => $old, 'mail' => 'sent', 'when' => date('c', $old)]) . "\n"
    . json_encode(['email' => 'week@example.com', 'status' => 'pending', 'ts' => $wk, 'mail' => 'sent', 'page' => '', 'when' => date('c', $wk)]) . "\n");

/* ---- 1. mail() fails: honest error, pending row kept ---------------------------------- */
$base = t_serve($dist, ['sendmail_path' => '/bin/false']);
preg_match('#name="ts" value="([^"]+)"#', t_http('GET', $base . '/contacto/')['body'], $m);
$ts = $m[1] ?? '';
t_check('contact page carries a server-rendered stamp', $ts !== '');
sleep(4);   // Leads::MIN_AGE

$email = 'mailfail@example.com';
$r = t_http('POST', $base . '/suscribir/', ['email' => $email, 'ts' => $ts, 'page' => '/', 'website' => '']);
$loc = urldecode((string)($r['headers']['location'] ?? ''));
t_check('mail failure → 303 with ?suscripcion=error', $r['status'] === 303 && str_contains($loc, 'suscripcion=error'), $loc);
t_check('mail failure → the message says the email could not be sent', str_contains($loc, 'No pudimos enviarte el email de confirmación'), $loc);
t_check('mail failure → no success flag', !str_contains($loc, 'suscrito='), $loc);
$rows = array_column(t_jsonl($pendingFile), null, 'email');
t_check('mail failure → pending row kept, marked mail=failed', ($rows[$email]['mail'] ?? '') === 'failed', json_encode($rows[$email] ?? null));
t_check('stale (8-day) pending row pruned on write', !isset($rows['stale@example.com']));
t_check('7-day-old pending row kept', isset($rows['week@example.com']));
t_check('nothing confirmed', !is_file($leads . '/newsletter.jsonl'));

$j = t_http('POST', $base . '/suscribir/', ['email' => $email, 'ts' => $ts, 'page' => '/', 'website' => ''], ['Accept' => 'application/json']);
$jd = json_decode($j['body'], true);
t_check('mail failure, JSON → 422 with a _mail error', $j['status'] === 422 && isset($jd['errors']['_mail']), $j['status'] . ' ' . $j['body']);

/* ---- 2. link age: 8 days → rejected, 7 days → accepted ------------------------------------ */
$secret = trim((string)file_get_contents($dist . '/site/data/.secret'));
$token  = static fn(string $e, int $daysAgo): string => hash_hmac('sha256', 'newsletter|' . $e . '|' . date('Ymd', strtotime("-$daysAgo days")), $secret);
// The engine runs in the site's timezone; so does date() here once we match it.
date_default_timezone_set((string)((require $dist . '/site/config.php')['timezone'] ?? 'America/Asuncion'));

$r = t_http('GET', $base . '/suscribir/confirmar/?' . http_build_query(['e' => 'week@example.com', 't' => $token('week@example.com', 8)]));
t_check('a link signed 8 days ago is rejected (400)', $r['status'] === 400, (string)$r['status']);
$r = t_http('GET', $base . '/suscribir/confirmar/?' . http_build_query(['e' => 'week@example.com', 't' => $token('week@example.com', 7)]));
t_check('a link signed 7 days ago still confirms (200)', $r['status'] === 200, (string)$r['status']);
$conf = t_jsonl($leads . '/newsletter.jsonl');
t_check('…and appends one confirmed row', count($conf) === 1 && $conf[0]['email'] === 'week@example.com' && $conf[0]['status'] === 'confirmed');
$r = t_http('GET', $base . '/suscribir/confirmar/?' . http_build_query(['e' => 'stale@example.com', 't' => $token('stale@example.com', 0)]));
t_check('a valid token for an address with no pending row is rejected', $r['status'] === 400, (string)$r['status']);
$r = t_http('GET', $base . '/suscribir/confirmar/?' . http_build_query(['e' => 'week@example.com', 't' => $token('other@example.com', 0)]));
t_check('a token for another address is rejected', $r['status'] === 400, (string)$r['status']);
$r = t_http('GET', $base . '/suscribir/confirmar/?e=x&t=' . str_repeat('z', 64));
t_check('garbage parameters → 400', $r['status'] === 400);
t_check('confirm page is noindex and not cached', str_contains($r['body'], 'noindex') && ($r['headers']['cache-control'] ?? '') === 'no-store');

t_done('newsletter');
