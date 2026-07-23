#!/usr/bin/env php
<?php

/**
 * SentinelStack — development SMTP catcher.
 *
 * Started by `composer dev:catcher` (or directly via `php bin/dev-catcher.php`)
 * during local development. Listens on 127.0.0.1:1025, the port the .env
 * references for NOTIFICATION_SMTP_HOST, accepts any mail (no auth, no
 * TLS), and writes a one-line summary plus the raw message body to
 * /tmp/smtp-catcher.log. Runs as a long-lived foreground process until
 * killed with Ctrl-C; the matching dev-test loop lives in README's
 * Notifications section.
 *
 * Replace with a real SMTP relay (MailHog, Gmail SES, Resend, etc.) before
 * sending actual email to real recipients — this only acknowledges the
 * SMTP handshake and discards the body. It's a harness, not infrastructure.
 */

declare(strict_types=1);

$host = '127.0.0.1';
$port = 1025;
$log  = '/tmp/smtp-catcher.log';
$bind = "tcp://$host:$port";

// `so_reuseaddr` only — we deliberately do NOT use `so_reuseport` here. There
// is exactly one dev catcher at a time; if a stale one is still bound, the
// new run is the bug, not a load-balanced partner.
$ctx = stream_context_create(['socket' => ['so_reuseaddr' => true]]);
$server = @stream_socket_server($bind, $errno, $errstr, STREAM_SERVER_BIND | STREAM_SERVER_LISTEN, $ctx);
if (!$server) {
    fwrite(STDERR, "bind $bind failed: $errstr\n");
    exit(1);
}
file_put_contents($log, sprintf("[catcher] listening on %s pid=%d\n", $bind, getmypid()), FILE_APPEND);

while (($conn = @stream_socket_accept($server, -1)) !== false) {
    fwrite($conn, "220 sentinelstack.local ESMTP catcher\r\n");
    $from   = null;
    $toList = [];
    $buf    = '';
    $inData = false;

    while (!feof($conn)) {
        $line = fgets($conn, 4096);
        if ($line === false) break;
        $line  = rtrim($line, "\r\n");
        $upper = strtoupper($line);

        if ($inData) {
            if ($line === '.') {
                $inData = false;
                $entry = sprintf(
                    "[%s] from=%s to=%s\n%s\n----\n",
                    date('c'),
                    $from ?? '?',
                    implode(',', $toList) ?: '?',
                    $buf
                );
                file_put_contents($log, $entry, FILE_APPEND);
                $buf = '';
                fwrite($conn, "250 OK accepted\r\n");
                continue;
            }
            // Strip the leading dot that SMTP clients add to lines
            // starting with `.` so they don't collide with the end-of-data
            // marker. We log the canonical form.
            $buf .= ($line !== '' && $line[0] === '.' ? substr($line, 1) : $line) . "\n";
            continue;
        }

        if (str_starts_with($upper, 'EHLO') || str_starts_with($upper, 'HELO')) {
            fwrite($conn, "250-catcher at your service\r\n250 OK\r\n");
        } elseif (str_starts_with($upper, 'MAIL FROM')) {
            $from = preg_match('/<([^>]*)>/', $line, $m) ? ($m[1] ?? '?') : $line;
            fwrite($conn, "250 OK\r\n");
        } elseif (str_starts_with($upper, 'RCPT TO')) {
            $toList[] = preg_match('/<([^>]*)>/', $line, $m) ? ($m[1] ?? '?') : $line;
            fwrite($conn, "250 OK\r\n");
        } elseif ($upper === 'DATA') {
            $inData = true;
            fwrite($conn, "354 End data with <CR><LF>.<CR><LF>\r\n");
        } elseif ($upper === 'QUIT') {
            fwrite($conn, "221 Bye\r\n");
            break;
        } elseif ($upper === 'RSET') {
            $from   = null;
            $toList = [];
            fwrite($conn, "250 OK\r\n");
        } elseif ($upper === 'NOOP') {
            fwrite($conn, "250 OK\r\n");
        } else {
            fwrite($conn, "250 OK\r\n");
        }
    }
    @fclose($conn);
}
