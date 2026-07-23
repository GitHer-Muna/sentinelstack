<?php
declare(strict_types=1);

namespace App;

/**
 * Tiny multi-transport email client. No composer deps.
 *
 * Four transports, picked from .env in this priority order:
 *  1. MAILGUN_API_KEY + MAILGUN_DOMAIN set → Mailgun HTTP API
 *  2. RESEND_API_KEY set                  → Resend HTTP API
 *  3. NOTIFICATION_SMTP_HOST set          → raw SMTP (STARTTLS / implicit TLS /
 *                                           LOGIN auth)
 *  4. nothing set                         → PHP's mail() (requires a working
 *                                           local MTA)
 *
 * Returns bool, never throws — a broken relay can never break the
 * in-app delivery that already happened above the call site.
 *
 * This is intentionally minimal. It does ONE thing (deliver a single
 * plain-text message) and does it well. If we ever need attachments,
 * multiple recipients, or proper MIME multipart, switch to a real
 * library — for transactional single-recipient email, this is enough.
 */
final class Smtp
{
    public static function send(string $to, string $subject, string $body): bool
    {
        $mgKey  = (string) Env::get('MAILGUN_API_KEY', '');
        $mgDom  = (string) Env::get('MAILGUN_DOMAIN', '');
        $rKey   = (string) Env::get('RESEND_API_KEY', '');
        $host   = (string) Env::get('NOTIFICATION_SMTP_HOST', '');

        $tries = [
            ['mailgun', fn() => self::sendViaMailgun($to, $subject, $body, $mgKey, $mgDom)],
            ['resend',  fn() => self::sendViaResend ($to, $subject, $body, $rKey)],
            ['smtp',    fn() => self::sendViaSmtp   ($to, $subject, $body)],
        ];

        foreach ($tries as [$tag, $run]) {
            // Skip a transport that's not configured.
            if ($tag === 'mailgun' && ($mgKey === '' || $mgDom === '')) continue;
            if ($tag === 'resend'  && $rKey   === '') continue;
            if ($tag === 'smtp'    && $host   === '') continue;
            try {
                if ($run() === true) return true;
            } catch (\Throwable $e) {
                error_log("[sentinelstack $tag] " . $e->getMessage());
                // Fall through to the next transport.
            }
        }
        // Transport 4: PHP mail() fallback. Almost never the right
        // answer on a real host — surfacing it here as the last resort.
        return self::sendViaMail($to, $subject, $body);
    }

    /** ── Transport 1: Mailgun HTTP API ──────────────────────────────────── */
    /**
     * Send via Mailgun's HTTP API.
     *
     * Design notes:
     *  - Uses Basic auth with username "api" and the API key as password.
     *  - Sends data as application/x-www-form-urlencoded (simpler than
     *    multipart/form-data with raw sockets).
     *  - HTTP/1.0 + Connection: close so the response body ends cleanly
     *    on EOF — no chunked-encoding parsing needed.
     */
    private static function sendViaMailgun(
        string $to,
        string $subject,
        string $body,
        string $apiKey,
        string $domain,
    ): bool {
        if (preg_match('/[\r\n\x00-\x1F\x7F]/', $apiKey)) {
            error_log('[sentinelstack Mailgun] API key contains control chars — refusing to send');
            return false;
        }
        $fromEmail = (string) Env::get('NOTIFICATION_FROM_EMAIL', 'sentinelstack@localhost');
        $cleanTo   = self::cleanEnvelopeAddress($to,        'to');
        $cleanFrom = self::cleanEnvelopeAddress($fromEmail, 'from');
        if ($cleanTo === null || $cleanFrom === null) return false;

        $params = http_build_query([
            'from'    => 'SentinelStack <' . $cleanFrom . '>',
            'to'      => $cleanTo,
            'subject' => str_replace(["\r", "\n"], ' ', $subject),
            'text'    => $body,
        ], '', '&', PHP_QUERY_RFC3986);

        $host   = 'api.mailgun.net';
        $port   = 443;
        $remote = 'ssl://' . $host . ':' . $port;

        $ctx = stream_context_create([
            'ssl' => [
                'verify_peer'      => true,
                'verify_peer_name' => true,
            ],
        ]);
        $errno = 0; $errstr = '';
        $sock = @stream_socket_client($remote, $errno, $errstr, 10, STREAM_CLIENT_CONNECT, $ctx);
        if (!$sock) {
            error_log("[sentinelstack Mailgun] connect failed: $remote $errstr");
            return false;
        }
        stream_set_timeout($sock, 10);

        $auth = base64_encode('api:' . $apiKey);
        $bodyLen = strlen($params);
        $req = "POST /v3/$domain/messages HTTP/1.0\r\n"
             . "Host: $host\r\n"
             . "Authorization: Basic $auth\r\n"
             . "Content-Type: application/x-www-form-urlencoded\r\n"
             . "Content-Length: $bodyLen\r\n"
             . "Accept: application/json\r\n"
             . "Connection: close\r\n"
             . "User-Agent: SentinelStack/1.0\r\n"
             . "\r\n"
             . $params;
        fwrite($sock, $req);

        // Read status line + headers line-by-line until blank line.
        $statusLine = '';
        $gotStatus  = false;
        while (!feof($sock)) {
            $line = fgets($sock, 4096);
            if ($line === false) break;
            $line = rtrim($line, "\r\n");
            if (!$gotStatus) {
                $statusLine = $line;
                $gotStatus  = true;
                continue;
            }
            if ($line === '') break;
        }
        $respBody = stream_get_contents($sock);
        if ($respBody === false) $respBody = '';
        fclose($sock);

        if (!preg_match('#^HTTP/\S+\s+(\d{3})#', $statusLine, $m)) {
            error_log("[sentinelstack Mailgun] malformed status line: $statusLine");
            return false;
        }
        $code = (int) $m[1];
        if ($code >= 200 && $code < 300) return true;

        $snippet = strlen($respBody) > 512 ? substr($respBody, 0, 512) . '…' : $respBody;
        error_log("[sentinelstack Mailgun] non-2xx $code: $snippet");
        return false;
    }

    /** ── Transport 2: Resend HTTP API ──────────────────────────────────── */
    /**
     * Send via Resend's JSON-over-HTTPS API.
     */
    private static function sendViaResend(string $to, string $subject, string $body, string $apiKey): bool
    {
        if (preg_match('/[\r\n\x00-\x1F\x7F]/', $apiKey)) {
            error_log('[sentinelstack Resend] API key contains control chars — refusing to send');
            return false;
        }
        $fromEmail = (string) Env::get('NOTIFICATION_FROM_EMAIL', 'sentinelstack@localhost');
        $cleanTo   = self::cleanEnvelopeAddress($to,        'to');
        $cleanFrom = self::cleanEnvelopeAddress($fromEmail, 'from');
        if ($cleanTo === null || $cleanFrom === null) return false;

        $payload = json_encode([
            'from'    => 'SentinelStack <' . $cleanFrom . '>',
            'to'      => [$cleanTo],
            'subject' => str_replace(["\r", "\n"], ' ', $subject),
            'text'    => $body,
        ], JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
        if ($payload === false) {
            error_log('[sentinelstack Resend] json_encode failed: ' . json_last_error_msg());
            return false;
        }

        $host   = 'api.resend.com';
        $port   = 443;
        $remote = 'ssl://' . $host . ':' . $port;

        $ctx = stream_context_create([
            'ssl' => [
                'verify_peer'      => true,
                'verify_peer_name' => true,
            ],
        ]);
        $errno = 0; $errstr = '';
        $sock = @stream_socket_client($remote, $errno, $errstr, 10, STREAM_CLIENT_CONNECT, $ctx);
        if (!$sock) {
            error_log("[sentinelstack Resend] connect failed: $remote $errstr");
            return false;
        }
        stream_set_timeout($sock, 10);

        $bodyLen = strlen($payload);
        $req = "POST /emails HTTP/1.0\r\n"
             . "Host: $host\r\n"
             . "Authorization: Bearer $apiKey\r\n"
             . "Content-Type: application/json\r\n"
             . "Content-Length: $bodyLen\r\n"
             . "Accept: application/json\r\n"
             . "Connection: close\r\n"
             . "User-Agent: SentinelStack/1.0\r\n"
             . "\r\n"
             . $payload;
        fwrite($sock, $req);

        $statusLine = '';
        $gotStatus  = false;
        while (!feof($sock)) {
            $line = fgets($sock, 4096);
            if ($line === false) break;
            $line = rtrim($line, "\r\n");
            if (!$gotStatus) {
                $statusLine = $line;
                $gotStatus  = true;
                continue;
            }
            if ($line === '') break;
        }
        $respBody = stream_get_contents($sock);
        if ($respBody === false) $respBody = '';
        fclose($sock);

        if (!preg_match('#^HTTP/\S+\s+(\d{3})#', $statusLine, $m)) {
            error_log("[sentinelstack Resend] malformed status line: $statusLine");
            return false;
        }
        $code = (int) $m[1];
        if ($code >= 200 && $code < 300) return true;

        $snippet = strlen($respBody) > 512 ? substr($respBody, 0, 512) . '…' : $respBody;
        error_log("[sentinelstack Resend] non-2xx $code: $snippet");
        return false;
    }

    /** ── Transport 3: raw SMTP ─────────────────────────────────────────── */
    private static function sendViaSmtp(string $to, string $subject, string $body): bool
    {
        $host       = (string) Env::get('NOTIFICATION_SMTP_HOST');
        $port       = (int)    Env::get('NOTIFICATION_SMTP_PORT', '587');
        $user       = (string) Env::get('NOTIFICATION_SMTP_USER', '');
        $pass       = (string) Env::get('NOTIFICATION_SMTP_PASS', '');
        $enc        = strtolower((string) Env::get('NOTIFICATION_SMTP_ENCRYPTION', 'starttls'));
        $fromEmail  = (string) Env::get('NOTIFICATION_FROM_EMAIL', 'sentinelstack@localhost');

        $subject = str_replace(["\r", "\n"], ' ', $subject);

        $cleanTo   = self::cleanEnvelopeAddress($to,        'to');
        $cleanFrom = self::cleanEnvelopeAddress($fromEmail, 'from');
        if ($cleanTo === null || $cleanFrom === null) return false;

        $remote = ($enc === 'tls' ? 'ssl://' : '') . $host . ':' . $port;

        $ctx = stream_context_create([
            'ssl' => [
                'verify_peer'      => true,
                'verify_peer_name' => true,
            ],
        ]);
        $errno = 0; $errstr = '';
        $sock = @stream_socket_client(
            $remote,
            $errno,
            $errstr,
            10,
            STREAM_CLIENT_CONNECT,
            $ctx
        );
        if (!$sock) {
            error_log("[sentinelstack Smtp] connect failed: $remote $errstr");
            return false;
        }
        stream_set_timeout($sock, 10);

        self::expect($sock, 220, 'greeting');

        $hostName = gethostname() ?: 'localhost';
        self::write($sock, "EHLO $hostName");
        self::expect($sock, 250, 'EHLO');

        if ($enc === 'starttls') {
            self::write($sock, 'STARTTLS');
            self::expect($sock, 220, 'STARTTLS');
            if (!stream_socket_enable_crypto($sock, true, STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT | STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT | STREAM_CRYPTO_METHOD_TLSv1_1_CLIENT | STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                fclose($sock);
                error_log('[sentinelstack Smtp] STARTTLS handshake failed');
                return false;
            }
            self::write($sock, "EHLO $hostName");
            self::expect($sock, 250, 'EHLO after STARTTLS');
        }

        if ($user !== '') {
            self::write($sock, 'AUTH LOGIN');
            self::expect($sock, 334, 'AUTH LOGIN');
            self::write($sock, base64_encode($user));
            self::expect($sock, 334, 'username');
            self::write($sock, base64_encode($pass));
            self::expect($sock, 235, 'password');
        }

        self::write($sock, "MAIL FROM:<$cleanFrom>");
        self::expect($sock, 250, 'MAIL FROM');
        self::write($sock, "RCPT TO:<$cleanTo>");
        self::expect($sock, [250, 251], 'RCPT TO');
        self::write($sock, 'DATA');
        self::expect($sock, 354, 'DATA');

        $headers = [
            'From: SentinelStack <' . $cleanFrom . '>',
            'To: <' . $cleanTo . '>',
            'Subject: ' . $subject,
            'Date: ' . date('r'),
            'MIME-Version: 1.0',
            'Content-Type: text/plain; charset=utf-8',
            'Content-Transfer-Encoding: 8bit',
        ];
        $payload = implode("\r\n", $headers) . "\r\n\r\n" . $body . "\r\n.\r\n";
        fwrite($sock, $payload);
        self::expect($sock, 250, 'message body');

        self::write($sock, 'QUIT');
        fclose($sock);
        return true;
    }

    /** ── Transport 4: PHP mail() fallback (needs a local MTA) ──────────── */
    private static function sendViaMail(string $to, string $subject, string $body): bool
    {
        $from = (string) Env::get('NOTIFICATION_FROM_EMAIL', 'sentinelstack@localhost');
        $headers = "From: $from\r\n" .
                   "MIME-Version: 1.0\r\n" .
                   "Content-Type: text/plain; charset=utf-8\r\n";
        return @mail($to, $subject, $body, $headers);
    }

    /** ── SMTP protocol helpers ─────────────────────────────────────────── */
    private static function write($sock, string $line): void
    {
        fwrite($sock, $line . "\r\n");
    }

    /**
     * Validate-and-isolate an address for safe envelope interpolation.
     */
    private static function cleanEnvelopeAddress(string $addr, string $context): ?string
    {
        $addr = trim($addr);
        if ($addr === '' || !preg_match('/^[^@\s]+@[^@\s]+$/', $addr)) {
            error_log("[sentinelstack Smtp] $context: rejected non-RFC address");
            return null;
        }
        if (preg_match('/[\r\n<>"\x00-\x1F\x7F]/', $addr)) {
            error_log("[sentinelstack Smtp] $context: rejected address with control chars");
            return null;
        }
        return $addr;
    }

    /**
     * Read one server reply line and assert its code matches $expect.
     */
    private static function expect($sock, $expect, string $context): void
    {
        $code = 0;
        while (!feof($sock)) {
            $line = fgets($sock, 1024);
            if ($line === false) break;
            $line = rtrim($line, "\r\n");
            if (preg_match('/^(\d{3})([\- ])/', $line, $m)) {
                $code = (int) $m[1];
                if ($m[2] === ' ') break;
            }
        }
        $expectArr = is_array($expect) ? $expect : [$expect];
        if (!in_array($code, $expectArr, true)) {
            throw new \RuntimeException("SMTP $context expected " . implode('/', $expectArr) . " got $code");
        }
    }
}
