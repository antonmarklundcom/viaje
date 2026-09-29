<?php
declare(strict_types=1);

/**
 * Contact-form handler (spec §10). Session-free: the form is public, so the
 * anti-abuse measures are a honeypot, a signed timestamp and a per-IP rate limit.
 */
final class Leads
{
    private const MIN_AGE  = 3;          // seconds
    private const MAX_AGE  = 86400;      // 24 h
    private const RATE_MAX = 5;
    private const RATE_WIN = 3600;

    /** Signed value for the form's hidden `ts` field. */
    public static function stamp(): string
    {
        $t = (string)time();
        return $t . '.' . hash_hmac('sha256', $t, Config::secret());
    }

    private static function stampAge(string $value): ?int
    {
        $parts = explode('.', $value, 2);
        if (count($parts) !== 2 || !ctype_digit($parts[0])) {
            return null;
        }
        if (!hash_equals(hash_hmac('sha256', $parts[0], Config::secret()), $parts[1])) {
            return null;
        }
        return time() - (int)$parts[0];
    }

    /** Topic options: configured list, else the enabled service titles. @return list<string> */
    public static function topics(): array
    {
        $topics = array_values(array_filter(array_map('strval', (array)Config::v('leads.topics', []))));
        if ($topics === []) {
            foreach (Content::listType('service') as $s) {
                $topics[] = (string)$s['title'];
            }
        }
        $topics[] = I18n::t('form_topic_default');
        return array_values(array_unique($topics));
    }

    /**
     * Validate and process a submission.
     *
     * @param array<string,mixed> $post
     * @return array{ok:bool,errors:array<string,string>,lead:array<string,mixed>}
     */
    public static function handle(array $post): array
    {
        $lead = [
            'name'    => trim((string)($post['name'] ?? '')),
            'phone'   => trim((string)($post['phone'] ?? '')),
            'email'   => trim((string)($post['email'] ?? '')),
            'topic'   => trim((string)($post['topic'] ?? '')),
            'message' => trim((string)($post['message'] ?? '')),
            'page'    => self::cleanPage((string)($post['page'] ?? '')),
            'page_title' => Util::truncate(trim((string)($post['page_title'] ?? '')), 160, ''),
        ];
        $errors = [];

        // Honeypot: silently accepted upstream, never stored.
        if (trim((string)($post['website'] ?? '')) !== '') {
            return ['ok' => false, 'errors' => ['_spam' => 'honeypot'], 'lead' => $lead];
        }

        $age = self::stampAge((string)($post['ts'] ?? ''));
        if ($age === null || $age > self::MAX_AGE) {
            $errors['ts'] = I18n::t('err_expired');
        } elseif ($age < self::MIN_AGE) {
            $errors['ts'] = I18n::t('err_too_fast');
        }
        if ($lead['name'] === '') {
            $errors['name'] = I18n::t('err_name');
        }
        if ($lead['phone'] === '' || strlen(preg_replace('/\D+/', '', $lead['phone']) ?? '') < 6) {
            $errors['phone'] = I18n::t('err_phone');
        }
        if ($lead['email'] !== '' && !filter_var($lead['email'], FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = I18n::t('err_email');
        }
        if ($lead['message'] === '') {
            $errors['message'] = I18n::t('err_message');
        } elseif (mb_strlen($lead['message'], 'UTF-8') > 3000) {
            $errors['message'] = I18n::t('err_message_long');
        }
        if (!$errors && !self::rateOk()) {
            $errors['rate'] = I18n::t('err_rate');
        }
        if ($errors) {
            return ['ok' => false, 'errors' => $errors, 'lead' => $lead];
        }

        $lead['page_url'] = $lead['page'] !== '' ? abs_url($lead['page']) : '';
        $lead['ip']   = Util::clientIp();
        $lead['when'] = date('c');
        $stored = self::store($lead);
        $mailed = self::mail($lead);
        self::pushCrm($lead);
        // Never tell a visitor "sent" when no delivery path actually took the lead.
        if (!$stored && !$mailed) {
            return ['ok' => false, 'errors' => ['_delivery' => I18n::t('err_delivery')], 'lead' => $lead];
        }
        return ['ok' => true, 'errors' => [], 'lead' => $lead];
    }

    private static function rateOk(string $bucket = 'lead'): bool
    {
        $dir  = VJ_SITE . '/cache/ratelimit';
        Util::mkdirp($dir);
        $file = $dir . '/' . $bucket . '-' . Util::ipKey(Util::clientIp()) . '.json';
        $hits = array_values(array_filter(
            array_map('intval', Util::readJsonFile($file)),
            static fn(int $t): bool => $t > time() - self::RATE_WIN
        ));
        if (count($hits) >= self::RATE_MAX) {
            return false;
        }
        $hits[] = time();
        Util::atomicWrite($file, (string)json_encode($hits));
        return true;
    }

    /** Append to site/data/leads/YYYY-MM.jsonl — always, this is the record of last resort. */
    private static function store(array $lead): bool
    {
        $dir = VJ_SITE . '/data/leads';
        if (!Util::mkdirp($dir)) {
            Util::log('Cannot create leads dir: ' . $dir);
            return false;
        }
        $line = json_encode($lead, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if (@file_put_contents($dir . '/' . date('Y-m') . '.jsonl', $line . "\n", FILE_APPEND | LOCK_EX) === false) {
            Util::log('Cannot append lead to ' . $dir);
            return false;
        }
        return true;
    }

    private static function mail(array $lead): bool
    {
        $to = (string)Config::v('leads.to', '');
        if ($to === '' || !function_exists('mail')) {
            return false;
        }
        $subject = (string)Config::v('leads.subject_prefix', '')
            . ($lead['topic'] !== '' ? $lead['topic'] . ' – ' : '') . $lead['name'];
        $body = implode("\n", [
            I18n::t('form_name') . ': ' . $lead['name'],
            I18n::t('form_phone') . ': ' . $lead['phone'],
            I18n::t('form_email') . ': ' . ($lead['email'] ?: '-'),
            I18n::t('form_topic') . ': ' . ($lead['topic'] ?: '-'),
            '',
            $lead['message'],
            '',
            '-- ' . (string)Config::v('domain') . ' ' . ($lead['page'] ?: ''),
            ...($lead['page_title'] !== '' ? ['-- ' . $lead['page_title'] . ' — ' . $lead['page_url']] : []),
        ]);
        $headers = [
            'From: ' . (string)Config::v('site_name') . ' <no-reply@' . (string)Config::v('domain') . '>',
            'Content-Type: text/plain; charset=UTF-8',
        ];
        if ($lead['email'] !== '' && filter_var($lead['email'], FILTER_VALIDATE_EMAIL)) {
            $headers[] = 'Reply-To: ' . $lead['email'];
        }
        $subject = str_replace(["\r", "\n"], ' ', $subject);
        if (!@mail($to, $subject, $body, implode("\r\n", $headers))) {
            Util::log('mail() failed for lead from ' . $lead['name']);
            return false;
        }
        return true;
    }

    /** Optional VenderCRM push. Never blocks or surfaces to the visitor. */
    private static function pushCrm(array $lead): void
    {
        $endpoint = (string)Config::v('leads.vendercrm.endpoint', '');
        $key      = (string)Config::v('leads.vendercrm.tenant_key', '');
        if ($endpoint === '' || $key === '' || !function_exists('curl_init')) {
            return;
        }
        if (!str_contains($endpoint, '/leads')) {
            $endpoint = rtrim($endpoint, '/') . '/api/v1/leads';
        }
        $payload = array_filter([
            'phone'           => $lead['phone'],
            'name'            => $lead['name'],
            'email'           => $lead['email'],
            'message'         => $lead['message']
                . ($lead['page_title'] !== '' ? "\n\n[" . $lead['page_title'] . ' — ' . $lead['page_url'] . ']' : ''),
            'source'          => (string)Config::v('leads.vendercrm.source', 'web-form'),
            'page_url'        => $lead['page_url'] !== '' ? $lead['page_url'] : null,
            'idempotency_key' => hash('sha256', $lead['phone'] . '|' . gmdate('Y-m-d-H')),
        ], static fn($v) => $v !== null && $v !== '');

        $ch = curl_init($endpoint);
        if ($ch === false) {
            return;
        }
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 5,
            CURLOPT_HTTPHEADER     => ['Content-Type: application/json', 'X-Api-Key: ' . $key],
            CURLOPT_POSTFIELDS     => json_encode($payload, JSON_UNESCAPED_UNICODE),
        ]);
        $res    = curl_exec($ch);
        $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err    = curl_error($ch);
        curl_close($ch);
        if ($status !== 200 && $status !== 201) {
            Util::log(sprintf('VenderCRM lead failed [%d] %s %s', $status, (string)$res, $err));
        }
    }

    /** wa.me link with a prefilled message. */
    public static function whatsappUrl(?string $text = null): string
    {
        $number = preg_replace('/\D+/', '', (string)Config::v('contact.whatsapp_e164', '')) ?? '';
        $text ??= (string)Config::v('contact.whatsapp_default_text', '');
        return 'https://wa.me/' . $number . ($text !== '' ? '?text=' . rawurlencode($text) : '');
    }

    /**
     * WhatsApp text that carries the page it was sent from: the site's default opener, the
     * page title and its canonical URL, so whoever answers knows exactly what was being read.
     * Home, hubs and pages without a title fall back to the plain default.
     */
    public static function pageText(array $page, ?string $opener = null): string
    {
        $opener = trim($opener ?? (string)Config::v('contact.whatsapp_default_text', ''));
        $title  = trim((string)($page['title'] ?? ''));
        $path   = (string)($page['path'] ?? '/');
        // "quiero consultar por <título>" only reads right for things a visitor can ask about.
        $askable = in_array((string)($page['type'] ?? ''), ['service', 'trip', 'activity', 'post', 'news'], true);
        if (!$askable || $title === '' || $path === '/' || !empty($page['is_hub']) || !empty($page['status'])) {
            return $opener;
        }
        return trim($opener . ' ' . $title) . ' (' . abs_url($path) . ')';
    }

    /** A site path that exists as content, or ''. Keeps the `page` field from becoming an open redirect. */
    public static function cleanPage(string $path): string
    {
        $path = trim($path);
        if ($path === '' || !Util::isSafePath($path) || Content::metaByPath($path) === null) {
            return '';
        }
        return $path;
    }

    /* ---------------------------------------------------------- newsletter */

    /**
     * Newsletter signup: an email, stored to site/data/leads/newsletter.jsonl. No third-party
     * service. Same anti-abuse set as the contact form (honeypot, signed timestamp, per-IP rate).
     *
     * @param array<string,mixed> $post
     * @return array{ok:bool,errors:array<string,string>,page:string}
     */
    public static function subscribe(array $post): array
    {
        $page  = self::cleanPage((string)($post['page'] ?? ''));
        $email = strtolower(trim((string)($post['email'] ?? '')));
        if (trim((string)($post['website'] ?? '')) !== '') {
            return ['ok' => false, 'errors' => ['_spam' => 'honeypot'], 'page' => $page];
        }
        $errors = [];
        $age = self::stampAge((string)($post['ts'] ?? ''));
        if ($age === null || $age > self::MAX_AGE) {
            $errors['ts'] = I18n::t('err_expired');
        } elseif ($age < self::MIN_AGE) {
            $errors['ts'] = I18n::t('err_too_fast');
        }
        if ($email === '' || strlen($email) > 160 || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = I18n::t('err_nl_email');
        }
        if (!$errors && !self::rateOk('newsletter')) {
            $errors['rate'] = I18n::t('err_nl_rate');
        }
        if ($errors) {
            return ['ok' => false, 'errors' => $errors, 'page' => $page];
        }

        $dir  = VJ_SITE . '/data/leads';
        $file = $dir . '/newsletter.jsonl';
        if (!Util::mkdirp($dir)) {
            Util::log('Cannot create leads dir: ' . $dir);
            return ['ok' => false, 'errors' => ['_store' => I18n::t('err_nl_store')], 'page' => $page];
        }
        // One row per address: a repeat signup is a success that writes nothing.
        foreach (is_file($file) ? (file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: []) : [] as $line) {
            $row = json_decode($line, true);
            if (is_array($row) && strtolower((string)($row['email'] ?? '')) === $email) {
                return ['ok' => true, 'errors' => [], 'page' => $page];
            }
        }
        $line = json_encode(
            ['email' => $email, 'page' => $page, 'consent' => true, 'when' => date('c')],
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
        );
        if (@file_put_contents($file, $line . "\n", FILE_APPEND | LOCK_EX) === false) {
            Util::log('Cannot append newsletter signup to ' . $file);
            return ['ok' => false, 'errors' => ['_store' => I18n::t('err_nl_store')], 'page' => $page];
        }
        return ['ok' => true, 'errors' => [], 'page' => $page];
    }
}
