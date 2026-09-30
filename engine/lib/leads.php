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

    private const NL_CONFIRM_DAYS = 7;      // a confirmation link (and its pending row) lives this long
    private const NL_RESEND_AFTER = 3600;   // a repeat signup re-sends the email at most this often

    /**
     * Newsletter signup with double opt-in. No third-party service. Same anti-abuse set as the
     * contact form (honeypot, signed timestamp, per-IP rate).
     *
     * A signup writes a pending row to data/leads/newsletter-pending.jsonl and mails a link to
     * /suscribir/confirmar/; only a click on that link appends the address to newsletter.jsonl
     * (append-only, one `"status":"confirmed"` row per address). Pending rows older than
     * NL_CONFIRM_DAYS are pruned on the next write. `status` in the result: 'pending' (email
     * sent) or 'already' (the address was confirmed before; nothing sent).
     *
     * @param array<string,mixed> $post
     * @return array{ok:bool,errors:array<string,string>,page:string,status:string}
     */
    public static function subscribe(array $post): array
    {
        $page  = self::cleanPage((string)($post['page'] ?? ''));
        $email = strtolower(trim((string)($post['email'] ?? '')));
        $fail  = static fn(array $errors): array => ['ok' => false, 'errors' => $errors, 'page' => $page, 'status' => ''];
        if (trim((string)($post['website'] ?? '')) !== '') {
            return $fail(['_spam' => 'honeypot']);
        }
        $errors = [];
        $age = self::stampAge((string)($post['ts'] ?? ''));
        if ($age === null || $age > self::MAX_AGE) {
            $errors['ts'] = I18n::t('err_expired');
        } elseif ($age < self::MIN_AGE) {
            $errors['ts'] = I18n::t('err_too_fast');
        }
        if (!self::validEmail($email)) {
            $errors['email'] = I18n::t('err_nl_email');
        }
        if (!$errors && !self::rateOk('newsletter')) {
            $errors['rate'] = I18n::t('err_nl_rate');
        }
        if ($errors) {
            return $fail($errors);
        }
        $storeError = ['_store' => I18n::t('err_nl_store', ['email' => (string)Config::v('contact.email', '')])];

        // Already confirmed: a repeat signup is a success that sends and writes nothing.
        if (self::nlConfirmed($email)) {
            return ['ok' => true, 'errors' => [], 'page' => $page, 'status' => 'already'];
        }
        $fh = self::nlPendingOpen();
        if ($fh === null) {
            return $fail($storeError);
        }
        try {
            $rows = self::nlPendingRead($fh);
            $mine = $rows[$email] ?? null;
            // One pending row per address; don't mail the same inbox again within the hour.
            if ($mine !== null && ($mine['mail'] ?? '') === 'sent' && time() - (int)($mine['ts'] ?? 0) < self::NL_RESEND_AFTER) {
                return self::nlPendingWrite($fh, $rows)
                    ? ['ok' => true, 'errors' => [], 'page' => $page, 'status' => 'pending']
                    : $fail($storeError);
            }
            $day   = date('Ymd');
            $token = self::nlToken($email, $day);
            $sent  = self::nlMail($email, $token);
            $rows[$email] = [
                'email' => $email, 'page' => $page, 'status' => 'pending', 'token' => $token, 'day' => $day,
                'when' => date('c'), 'ts' => time(), 'mail' => $sent ? 'sent' : 'failed',
            ];
            if (!self::nlPendingWrite($fh, $rows)) {
                return $fail($storeError);
            }
        } finally {
            flock($fh, LOCK_UN);
            fclose($fh);
        }
        // The pending row stays either way; the visitor is told the truth about the email.
        if (!$sent) {
            return $fail(['_mail' => I18n::t('err_nl_mail', ['email' => (string)Config::v('contact.email', '')])]);
        }
        return ['ok' => true, 'errors' => [], 'page' => $page, 'status' => 'pending'];
    }

    /**
     * The link from the confirmation email. `status`: 'confirmed' (row appended now), 'already'
     * (confirmed before — a replayed link is harmless) or 'invalid' (bad, expired or pruned).
     *
     * @return array{ok:bool,status:string,page:string}
     */
    public static function confirm(string $email, string $token): array
    {
        $email   = strtolower(trim($email));
        $invalid = ['ok' => false, 'status' => 'invalid', 'page' => ''];
        if (!self::validEmail($email) || !preg_match('/^[a-f0-9]{64}$/', $token)) {
            return $invalid;
        }
        $valid = false;
        for ($i = 0; $i <= self::NL_CONFIRM_DAYS && !$valid; $i++) {
            $valid = hash_equals(self::nlToken($email, date('Ymd', strtotime("-$i days"))), $token);
        }
        if (!$valid) {
            return $invalid;
        }
        $fh = self::nlPendingOpen();
        if ($fh === null) {
            return $invalid;
        }
        try {
            // Checked under the lock, so two clicks at once still write one row.
            if (self::nlConfirmed($email)) {
                return ['ok' => true, 'status' => 'already', 'page' => ''];
            }
            $rows = self::nlPendingRead($fh);
            $row  = $rows[$email] ?? null;
            if ($row === null) {
                return $invalid;
            }
            $line = json_encode([
                'email' => $email, 'page' => (string)($row['page'] ?? ''), 'status' => 'confirmed', 'consent' => true,
                'requested' => (string)($row['when'] ?? ''), 'when' => date('c'),
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            if (@file_put_contents(self::nlFile(), $line . "\n", FILE_APPEND | LOCK_EX) === false) {
                Util::log('Cannot append newsletter confirmation to ' . self::nlFile());
                return $invalid;
            }
            unset($rows[$email]);
            self::nlPendingWrite($fh, $rows);
            return ['ok' => true, 'status' => 'confirmed', 'page' => (string)($row['page'] ?? '')];
        } finally {
            flock($fh, LOCK_UN);
            fclose($fh);
        }
    }

    private static function validEmail(string $email): bool
    {
        return $email !== '' && strlen($email) <= 160 && filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }

    /** HMAC of address + signup day: the link proves the inbox received it, and expires with the day window. */
    private static function nlToken(string $email, string $day): string
    {
        return hash_hmac('sha256', 'newsletter|' . $email . '|' . $day, Config::secret());
    }

    public static function nlFile(): string
    {
        return VJ_SITE . '/data/leads/newsletter.jsonl';
    }

    public static function nlPendingFile(): string
    {
        return VJ_SITE . '/data/leads/newsletter-pending.jsonl';
    }

    /** True when newsletter.jsonl holds a confirmed row for the address (rows without `status` predate double opt-in). */
    private static function nlConfirmed(string $email): bool
    {
        $file = self::nlFile();
        foreach (is_file($file) ? (file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: []) : [] as $line) {
            $row = json_decode($line, true);
            if (is_array($row) && ($row['status'] ?? '') === 'confirmed' && strtolower((string)($row['email'] ?? '')) === $email) {
                return true;
            }
        }
        return false;
    }

    /** The pending file, opened and exclusively locked. @return resource|null */
    private static function nlPendingOpen()
    {
        $file = self::nlPendingFile();
        if (!Util::mkdirp(dirname($file))) {
            Util::log('Cannot create leads dir: ' . dirname($file));
            return null;
        }
        $fh = @fopen($file, 'c+');
        if ($fh === false || !flock($fh, LOCK_EX)) {
            Util::log('Cannot open ' . $file);
            return null;
        }
        return $fh;
    }

    /** Pending rows by address, minus the expired ones. @param resource $fh @return array<string,array> */
    private static function nlPendingRead($fh): array
    {
        rewind($fh);
        $rows = [];
        $cut  = time() - self::NL_CONFIRM_DAYS * 86400;
        foreach (preg_split('/\R/', (string)stream_get_contents($fh)) ?: [] as $line) {
            $row = json_decode($line, true);
            if (is_array($row) && isset($row['email']) && (int)($row['ts'] ?? 0) >= $cut) {
                $rows[strtolower((string)$row['email'])] = $row;
            }
        }
        return $rows;
    }

    /** @param resource $fh @param array<string,array> $rows */
    private static function nlPendingWrite($fh, array $rows): bool
    {
        $out = '';
        foreach ($rows as $row) {
            $out .= json_encode($row, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n";
        }
        $ok = ftruncate($fh, 0) && rewind($fh) && fwrite($fh, $out) === strlen($out) && fflush($fh);
        if (!$ok) {
            Util::log('Cannot write ' . self::nlPendingFile());
        }
        return $ok;
    }

    /** The confirmation email. False when mail() refused it (the caller says so to the visitor). */
    private static function nlMail(string $email, string $token): bool
    {
        if (!function_exists('mail')) {
            return false;
        }
        $site = (string)Config::v('site_name');
        $link = abs_url('/suscribir/confirmar/') . '?' . http_build_query(['e' => $email, 't' => $token]);
        $body = I18n::t('nl_mail_body', ['link' => $link, 'days' => self::NL_CONFIRM_DAYS, 'site' => $site]);
        $headers = [
            'From: ' . mb_encode_mimeheader($site, 'UTF-8') . ' <no-reply@' . (string)Config::v('domain') . '>',
            'MIME-Version: 1.0',
            'Content-Type: text/plain; charset=UTF-8',
            'Content-Transfer-Encoding: 8bit',
        ];
        $subject = mb_encode_mimeheader(I18n::t('nl_mail_subject', ['site' => $site]), 'UTF-8');
        if (!@mail($email, $subject, $body, implode("\r\n", $headers))) {
            Util::log('mail() failed for newsletter confirmation to ' . $email);
            return false;
        }
        return true;
    }
}
