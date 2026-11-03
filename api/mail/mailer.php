<?php
/**
 * Mailer — Pio De Roda confirmation emails.
 *
 * Pure PHP (no libraries — this project has no Composer). Two paths:
 *
 *   1. SMTP configured (config.php host+username+password) -> send for real.
 *   2. Otherwise, or if SMTP fails                          -> save to the
 *      outbox as a .eml file so the email is never lost and can be opened
 *      locally to verify the content.
 *
 * Either way a booking confirmation never breaks the reservation flow: every
 * failure is caught and reported, never thrown at the caller.
 */

require_once __DIR__ . '/config.php';

/**
 * Send one email. Returns ['ok' => bool, 'mode' => 'smtp'|'outbox', 'error' => ?string].
 * Never throws.
 */
function pdrSendMail($toEmail, $subject, $htmlBody, $textBody = '')
{
    $cfg = pdrMailConfig();

    if (!filter_var($toEmail, FILTER_VALIDATE_EMAIL)) {
        return ['ok' => false, 'mode' => 'none', 'error' => 'invalid recipient: ' . $toEmail];
    }

    $headers = pdrMailHeaders($cfg, $toEmail, $subject);
    $mime    = pdrMailMime($headers, $htmlBody, $textBody);

    // --- Path 1: real SMTP -------------------------------------------------
    if (pdrSmtpConfigured($cfg)) {
        $err = pdrSmtpSend($cfg, $toEmail, $mime);
        if ($err === null) {
            pdrMailLog('sent', $toEmail, $subject, 'smtp');
            return ['ok' => true, 'mode' => 'smtp', 'error' => null];
        }
        // SMTP failed — fall through to the outbox so nothing is lost, and
        // keep the reason for the log.
        pdrMailLog('smtp-failed', $toEmail, $subject, $err);
    }

    // --- Path 2: outbox (.eml file) ---------------------------------------
    $fileErr = pdrOutboxSave($cfg, $toEmail, $mime);
    if ($fileErr === null) {
        pdrMailLog('queued', $toEmail, $subject, 'outbox');
        return ['ok' => true, 'mode' => 'outbox', 'error' => null];
    }

    pdrMailLog('failed', $toEmail, $subject, $fileErr);
    return ['ok' => false, 'mode' => 'none', 'error' => $fileErr];
}

/* ==========================================================================
   Config
   ========================================================================== */

function pdrMailConfig()
{
    static $cfg = null;
    if ($cfg === null) {
        $cfg = require __DIR__ . '/config.php';
    }
    return $cfg;
}

function pdrSmtpConfigured(array $cfg)
{
    return !empty($cfg['enabled'])
        && !empty($cfg['host'])
        && !empty($cfg['username'])
        && !empty($cfg['password']);
}

/* ==========================================================================
   Headers / MIME
   ========================================================================== */

function pdrMailHeaders(array $cfg, $toEmail, $subject)
{
    $fromName  = $cfg['from_name'];
    $fromEmail = $cfg['from_email'];
    return [
        'From'     => sprintf('%s <%s>', pdrMailEncodePhrase($fromName), $fromEmail),
        'To'       => $toEmail,
        'Subject'  => pdrMailEncodePhrase($subject),
        'Date'     => date('r'),
        'Message-ID' => '<' . uniqid('pdr.', true) . '@pioderoda.local>',
        'MIME-Version' => '1.0',
    ];
}

/**
 * Build a multipart/alternative message (plain text + HTML) with CRLF endings,
 * ready for SMTP DATA or an .eml file.
 */
function pdrMailMime(array $headers, $htmlBody, $textBody)
{
    $boundary = '=_pdr_' . md5(uniqid('mail', true));

    if ($textBody === '') {
        $textBody = trim(strip_tags(str_replace(['<br>', '<br/>', '<br />'], "\n", $htmlBody)));
    }

    $out = '';
    foreach ($headers as $k => $v) {
        $out .= $k . ': ' . $v . "\r\n";
    }
    $out .= 'Content-Type: multipart/alternative; boundary="' . $boundary . '"' . "\r\n\r\n";

    $out .= '--' . $boundary . "\r\n";
    $out .= "Content-Type: text/plain; charset=UTF-8\r\n";
    $out .= "Content-Transfer-Encoding: 8bit\r\n\r\n";
    $out .= pdrMailCrlf($textBody) . "\r\n\r\n";

    $out .= '--' . $boundary . "\r\n";
    $out .= "Content-Type: text/html; charset=UTF-8\r\n";
    $out .= "Content-Transfer-Encoding: 8bit\r\n\r\n";
    $out .= pdrMailCrlf($htmlBody) . "\r\n\r\n";

    $out .= '--' . $boundary . '--';
    return $out;
}

/** Normalize line endings to CRLF (required by SMTP). */
function pdrMailCrlf($s)
{
    // Order matters: collapse every existing ending to LF FIRST, then raise
    // to CRLF. Doing it the other way round doubles the CRs (\r\n -> \r\n\r\n).
    return str_replace("\n", "\r\n", str_replace(["\r\n", "\r"], "\n", $s));
}

/**
 * RFC 2047 encode a header value that contains non-ASCII characters
 * (₱, –, etc.). ASCII-only values pass through untouched.
 */
function pdrMailEncodePhrase($value)
{
    if (preg_match('/^[\x20-\x7E]*$/', $value)) {
        return $value;
    }
    return '=?UTF-8?B?' . base64_encode($value) . '?=';
}

/* ==========================================================================
   SMTP client (STARTTLS / SSL / plain, AUTH LOGIN)
   ========================================================================== */

/**
 * Send one prepared message over SMTP. Returns null on success or an error
 * string. Never throws.
 */
function pdrSmtpSend(array $cfg, $toEmail, $mime)
{
    $host    = $cfg['host'];
    $port    = (int) ($cfg['port'] ?? 587);
    $enc     = strtolower($cfg['encryption'] ?? 'tls');
    $timeout = (int) ($cfg['timeout'] ?? 8);

    $remote = ($enc === 'ssl' ? 'ssl://' : 'tcp://') . $host . ':' . $port;
    $errno  = 0;
    $errstr = '';
    $fp = @stream_socket_client($remote, $errno, $errstr, $timeout);
    if (!$fp) {
        return "connect $remote failed: $errstr ($errno)";
    }
    stream_set_timeout($fp, $timeout);

    $read = function () use ($fp) {
        $data = '';
        while (($line = fgets($fp, 515)) !== false) {
            $data .= $line;
            if (isset($line[3]) && $line[3] === ' ') break; // last line of a reply
        }
        return $data;
    };
    $cmd = function ($line) use ($fp, $read) {
        fwrite($fp, $line . "\r\n");
        return $read();
    };
    $code = function ($reply) {
        return (int) substr($reply, 0, 3);
    };

    try {
        // 220 greeting
        if ($code($read()) !== 220) {
            fclose($fp);
            return 'no SMTP greeting';
        }
        // EHLO
        if ($code($cmd('EHLO ' . (gethostname() ?: 'pioderoda'))) !== 250) {
            fclose($fp);
            return 'EHLO rejected';
        }
        // STARTTLS on plain connections
        if ($enc === 'tls') {
            if ($code($cmd('STARTTLS')) !== 220) {
                fclose($fp);
                return 'STARTTLS rejected';
            }
            if (!stream_socket_enable_crypto($fp, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                fclose($fp);
                return 'TLS handshake failed';
            }
            // EHLO must be repeated after upgrading the connection
            if ($code($cmd('EHLO ' . (gethostname() ?: 'pioderoda'))) !== 250) {
                fclose($fp);
                return 'EHLO after STARTTLS failed';
            }
        }
        // AUTH LOGIN
        if ($code($cmd('AUTH LOGIN')) !== 334) {
            fclose($fp);
            return 'AUTH LOGIN rejected';
        }
        if ($code($cmd(base64_encode($cfg['username']))) !== 334) {
            fclose($fp);
            return 'username rejected';
        }
        if ($code($cmd(base64_encode($cfg['password']))) !== 235) {
            fclose($fp);
            return 'password rejected';
        }
        // Envelope
        if ($code($cmd('MAIL FROM:<' . $cfg['from_email'] . '>')) !== 250) {
            fclose($fp);
            return 'MAIL FROM rejected';
        }
        $rc = $code($cmd('RCPT TO:<' . $toEmail . '>'));
        if ($rc !== 250 && $rc !== 251) {
            fclose($fp);
            return 'RCPT TO rejected';
        }
        if ($code($cmd('DATA')) !== 354) {
            fclose($fp);
            return 'DATA rejected';
        }
        // Body: dot-stuffing required — a lone "." line would end the message.
        $body = preg_replace('/^\./m', '..', pdrMailCrlf($mime));
        fwrite($fp, $body . "\r\n.\r\n");
        $endCode = $code($read());
        $cmd('QUIT');
        fclose($fp);
        if ($endCode !== 250) {
            return 'message rejected by server (code ' . $endCode . ')';
        }
        return null;
    } catch (Throwable $e) {
        if (is_resource($fp)) fclose($fp);
        return 'SMTP error: ' . $e->getMessage();
    }
}

/* ==========================================================================
   Outbox — unsent emails saved as openable .eml files
   ========================================================================== */

/** Write the message to the outbox. Returns null on success, else an error. */
function pdrOutboxSave(array $cfg, $toEmail, $mime)
{
    $dir = $cfg['outbox'];
    if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) {
        return 'cannot create outbox directory';
    }

    $safeTo = preg_replace('/[^A-Za-z0-9._-]/', '_', $toEmail);
    $name   = date('Ymd-His') . '_' . substr($safeTo, 0, 40) . '.eml';
    $path   = rtrim($dir, '/\\') . DIRECTORY_SEPARATOR . $name;

    if (@file_put_contents($path, pdrMailCrlf($mime)) === false) {
        return 'cannot write outbox file';
    }
    return null;
}

/**
 * One-line audit trail of every send attempt. Appended to outbox/mail.log —
 * readable with any text editor, never served over HTTP (outbox has a deny
 * .htaccess, and .log is not a web-served type here).
 */
function pdrMailLog($status, $toEmail, $subject, $detail = '')
{
    $cfg = pdrMailConfig();
    $dir = $cfg['outbox'];
    if (!is_dir($dir)) {
        @mkdir($dir, 0775, true);
    }
    $line = sprintf(
        "%s\t%s\tto=%s\tsubject=%s\t%s\n",
        date('Y-m-d H:i:s'),
        $status,
        $toEmail,
        str_replace(["\r", "\n"], ' ', $subject),
        str_replace(["\r", "\n"], ' ', $detail)
    );
    @file_put_contents(rtrim($dir, '/\\') . DIRECTORY_SEPARATOR . 'mail.log', $line, FILE_APPEND);
}
