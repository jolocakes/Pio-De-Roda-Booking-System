<?php
/**
 * Cloudflare Turnstile verification (T-32 / T-45).
 *
 * Policy: fail-open when the keys are absent so a fresh clone keeps working
 * without a Cloudflare account. Once both keys are set, a missing or invalid
 * token is rejected. A network outage reaching Cloudflare is logged and allowed
 * through (a gateway outage must not take bookings offline).
 */

require_once __DIR__ . '/env.php';

function pdrCaptchaSiteKey(): string
{
    return trim((string) pdrEnv('PDR_TURNSTILE_SITE_KEY', ''));
}

function pdrCaptchaSecret(): string
{
    return trim((string) pdrEnv('PDR_TURNSTILE_SECRET', ''));
}

function pdrCaptchaEnabled(): bool
{
    return pdrCaptchaSiteKey() !== '' && pdrCaptchaSecret() !== '';
}

/**
 * Verify a Turnstile token. Never throws.
 *
 * Returns ['ok' => bool, 'mode' => 'disabled'|'turnstile'|'unavailable',
 *          'reason' => ?string, 'codes' => array].
 */
function pdrVerifyCaptcha(?string $token, ?string $remoteIp = null, ?string $expectedAction = null): array
{
    if (!pdrCaptchaEnabled()) {
        return ['ok' => true, 'mode' => 'disabled', 'reason' => null, 'codes' => []];
    }

    $token = trim((string) $token);
    if ($token === '') {
        return ['ok' => false, 'mode' => 'turnstile', 'reason' => 'MISSING', 'codes' => []];
    }

    $payload = [
        'secret'   => pdrCaptchaSecret(),
        'response' => $token,
    ];
    if ($remoteIp !== null && $remoteIp !== '') {
        $payload['remoteip'] = $remoteIp;
    }

    $response = null;
    if (function_exists('curl_init')) {
        $ch = curl_init('https://challenges.cloudflare.com/turnstile/v0/siteverify');
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => http_build_query($payload),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 8,
            CURLOPT_CONNECTTIMEOUT => 4,
        ]);
        $response = curl_exec($ch);
        $curlError = curl_error($ch);
        curl_close($ch);
        if ($response === false) {
            error_log('Turnstile network error: ' . $curlError);
            return ['ok' => true, 'mode' => 'unavailable', 'reason' => 'NETWORK', 'codes' => []];
        }
    } else {
        $context = stream_context_create([
            'http' => [
                'method'  => 'POST',
                'header'  => "Content-Type: application/x-www-form-urlencoded\r\n",
                'content' => http_build_query($payload),
                'timeout' => 8,
            ],
        ]);
        $response = @file_get_contents(
            'https://challenges.cloudflare.com/turnstile/v0/siteverify',
            false,
            $context
        );
        if ($response === false) {
            error_log('Turnstile network error: no HTTP stream');
            return ['ok' => true, 'mode' => 'unavailable', 'reason' => 'NETWORK', 'codes' => []];
        }
    }

    $result = json_decode((string) $response, true);
    if (is_array($result) && !empty($result['success'])) {
        if ($expectedAction !== null && isset($result['action']) && $result['action'] !== $expectedAction) {
            return ['ok' => false, 'mode' => 'turnstile', 'reason' => 'ACTION_MISMATCH', 'codes' => []];
        }
        return ['ok' => true, 'mode' => 'turnstile', 'reason' => null, 'codes' => []];
    }

    return [
        'ok'     => false,
        'mode'   => 'turnstile',
        'reason' => 'FAILED',
        'codes'  => is_array($result['error-codes'] ?? null) ? $result['error-codes'] : [],
    ];
}

/** Best-effort client IP for the verification call. */
function pdrCaptchaClientIp(): string
{
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    return is_string($ip) ? $ip : '';
}
