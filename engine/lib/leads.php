<?php
declare(strict_types=1);

/**
 * Public contact form handler. Session-free by design: a signed timestamp plus a
 * honeypot plus a per-IP rate limit replace the CSRF token a logged-in form would use.
 */
final class Leads
{
    private const MIN_AGE   = 3;      // seconds; anything faster is a bot
    private const MAX_AGE   = 86400;  // 24 h
    private const MAX_BODY  = 3000;
    private const RATE_MAX  = 5;
    private const RATE_WIN  = 3600;

    /** Signed render timestamp for the hidden `ts` field. */
    public static function token(): string
    {
        $ts = (string)time();
        return $ts . '.' . hash_hmac('sha256', $ts, Config::secret());
    }

    private static function checkToken(string $token): ?string
    {
        $parts = explode('.', $token, 2);
        if (count($parts) !== 2 || !ctype_digit($parts[0])) {
            return 'err_expired';
        }
        $expected = hash_hmac('sha256', $parts[0], Config::secret());
        if (!hash_equals($expected, $parts[1])) {
            return 'err_expired';
        }
        $age = time() - (int)$parts[0];
        if ($age < self::MIN_AGE) {
            return 'err_too_fast';
        }
        if ($age > self::MAX_AGE) {
            return 'err_expired';
        }
        return null;
    }

    /** Topic options: configured list, else the published service titles. */
    public static function topics(): array
    {
        $configured = (array)Config::v('leads.topics', []);
        if ($configured !== []) {
            return array_values(array_map('strval', $configured));
        }
        $out = [];
        if (Types::enabled('service')) {
            foreach (Content::listing('service') as $s) {
                $out[] = $s['title'];
            }
        }
        $out[] = I18n::t('form_topic_default');
        return array_values(array_unique($out));
    }

    /** Where a submission returns to: the contact page if there is one, else home. */
    public static function contactPath(): string
    {
        foreach (Content::index()['items'] as $path => $m) {
            if ($m['type'] === 'page' && ($m['layout'] ?? '') === 'contact' && !$m['draft']) {
                return (string)$path;
            }
        }
        return '/';
    }

    /**
     * @param array<string,mixed> $post
     */
    public static function handle(array $post, array $server): Response
    {
        $wantsJson = self::wantsJson($server);
        $back      = self::contactPath();
        $ip        = Util::clientIp();

        // Honeypot: accept silently so the bot believes it succeeded.
        if (trim((string)($post['website'] ?? '')) !== '') {
            Util::log('lead rejected: honeypot');
            return $wantsJson
                ? Response::json(['ok' => true])
                : Response::redirect($back . '?enviado=1', 303);
        }

        $errors = [];
        if ($err = self::checkToken((string)($post['ts'] ?? ''))) {
            $errors[] = $err;
        }
        if (RateLimit::exceeded('lead', $ip, self::RATE_MAX, self::RATE_WIN)) {
            $errors[] = 'err_rate';
        }

        $name    = trim((string)($post['name'] ?? ''));
        $phone   = trim((string)($post['phone'] ?? ''));
        $email   = trim((string)($post['email'] ?? ''));
        $topic   = trim((string)($post['topic'] ?? ''));
        $message = trim((string)($post['message'] ?? ''));

        if ($name === '' || mb_strlen($name) > 120) {
            $errors[] = 'err_name';
        }
        if (preg_replace('/\D+/', '', $phone) === '') {
            $errors[] = 'err_phone';
        }
        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'err_email';
        }
        if ($message === '') {
            $errors[] = 'err_message';
        } elseif (mb_strlen($message) > self::MAX_BODY) {
            $errors[] = 'err_message_long';
        }
        $topics = self::topics();
        if ($topic === '' || !in_array($topic, $topics, true)) {
            $topic = I18n::t('form_topic_default');
        }

        if ($errors !== []) {
            RateLimit::hit('lead', $ip, self::RATE_WIN);
            if ($wantsJson) {
                return Response::json(['ok' => false, 'errors' => array_map(static fn(string $k): string => I18n::t($k), $errors)], 422);
            }
            return Response::redirect($back . '?error=' . rawurlencode($errors[0]), 303);
        }

        RateLimit::hit('lead', $ip, self::RATE_WIN);

        $lead = [
            'ts'         => gmdate('c'),
            'name'       => $name,
            'phone'      => $phone,
            'phone_e164' => preg_replace('/\D+/', '', $phone),
            'email'      => $email !== '' ? $email : null,
            'topic'      => $topic,
            'message'    => $message,
            'page'       => (string)($post['page'] ?? ''),
            'ua'         => mb_substr((string)($server['HTTP_USER_AGENT'] ?? ''), 0, 200),
            'ip_hash'    => Util::ipKey($ip),
        ];

        self::append($lead);
        self::sendMail($lead);
        self::pushCrm($lead, $post);

        if ($wantsJson) {
            return Response::json(['ok' => true]);
        }
        return Response::redirect($back . '?enviado=1', 303);
    }

    private static function wantsJson(array $server): bool
    {
        if (strtolower((string)($server['HTTP_X_REQUESTED_WITH'] ?? '')) === 'xmlhttprequest') {
            return true;
        }
        return str_contains(strtolower((string)($server['HTTP_ACCEPT'] ?? '')), 'application/json');
    }

    /** The fallback of record: one JSON line per lead, never lost to a mail failure. */
    private static function append(array $lead): void
    {
        $dir = VJ_SITE . '/data/leads';
        if (!Util::mkdirp($dir)) {
            Util::log('lead: could not create ' . $dir);
            return;
        }
        $file = $dir . '/' . date('Y-m') . '.jsonl';
        $line = json_encode($lead, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n";
        if (@file_put_contents($file, $line, FILE_APPEND | LOCK_EX) === false) {
            Util::log('lead: could not append to ' . $file);
        }
    }

    private static function sendMail(array $lead): void
    {
        $to = (string)Config::v('leads.to', '');
        if ($to === '' || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
            return;
        }
        $prefix  = (string)Config::v('leads.subject_prefix', '');
        $subject = $prefix . $lead['topic'] . ' – ' . $lead['name'];
        $subject = str_replace(["\r", "\n"], ' ', $subject);

        $body = "Nueva consulta / new enquiry\n\n"
            . 'Nombre:  ' . $lead['name'] . "\n"
            . 'Teléfono: ' . $lead['phone'] . "\n"
            . 'Email:   ' . ($lead['email'] ?? '-') . "\n"
            . 'Tema:    ' . $lead['topic'] . "\n"
            . 'Página:  ' . $lead['page'] . "\n\n"
            . $lead['message'] . "\n";

        $headers = [
            'From: ' . (string)Config::v('site_name', 'web') . ' <no-reply@' . (string)Config::v('domain', 'localhost') . '>',
            'Content-Type: text/plain; charset=UTF-8',
            'X-Mailer: engine',
        ];
        if (!empty($lead['email']) && filter_var($lead['email'], FILTER_VALIDATE_EMAIL)) {
            $headers[] = 'Reply-To: ' . $lead['email'];
        }
        $ok = @mail($to, $subject, $body, implode("\r\n", $headers));
        if (!$ok) {
            Util::log('lead: mail() failed for ' . $to);
        }
    }

    /**
     * Optional VenderCRM push. Request shape copied from the vendercrm-lead-capture
     * skill (POST /api/v1/leads, X-Api-Key header, idempotency key). Disabled and
     * skipped entirely when either endpoint or tenant_key is missing.
     */
    private static function pushCrm(array $lead, array $post): void
    {
        $endpoint = (string)Config::v('leads.vendercrm.endpoint', '');
        $key      = (string)Config::v('leads.vendercrm.tenant_key', '');
        if ($endpoint === '' || $key === '' || !function_exists('curl_init')) {
            return;
        }
        $attr = [];
        if (!empty($_COOKIE['vc_attr'])) {
            $decoded = json_decode((string)$_COOKIE['vc_attr'], true);
            if (is_array($decoded)) {
                $attr = $decoded;
            }
        }
        $payload = [
            'phone'           => $lead['phone'],
            'name'            => $lead['name'],
            'email'           => $lead['email'],
            'message'         => $lead['message'],
            'source'          => (string)Config::v('leads.vendercrm.source', 'web-form'),
            'page_url'        => $attr['landing_page'] ?? abs_url($lead['page'] !== '' ? $lead['page'] : '/'),
            'referrer'        => $attr['referrer'] ?? null,
            'utm_source'      => $attr['utm_source'] ?? null,
            'utm_medium'      => $attr['utm_medium'] ?? null,
            'utm_campaign'    => $attr['utm_campaign'] ?? null,
            'utm_term'        => $attr['utm_term'] ?? null,
            'utm_content'     => $attr['utm_content'] ?? null,
            'gclid'           => $attr['gclid'] ?? null,
            'fbclid'          => $attr['fbclid'] ?? null,
            'idempotency_key' => hash('sha256', $lead['phone'] . '|' . gmdate('Y-m-d-H')),
        ];
        $payload = array_filter($payload, static fn($v): bool => $v !== null && $v !== '');

        $ch = curl_init(rtrim($endpoint, '/') . '/api/v1/leads');
        if ($ch === false) {
            return;
        }
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 5,
            CURLOPT_CONNECTTIMEOUT => 3,
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                'X-Api-Key: ' . $key,
            ],
            CURLOPT_POSTFIELDS     => (string)json_encode($payload, JSON_UNESCAPED_UNICODE),
        ]);
        $response = curl_exec($ch);
        $status   = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err      = curl_error($ch);
        curl_close($ch);
        if ($status !== 200 && $status !== 201) {
            // Never log the API key; only the status and the response body.
            Util::log(sprintf('VenderCRM lead failed [%d] %s %s', $status, is_string($response) ? substr($response, 0, 300) : '', $err));
        }
    }
}
