<?php
/**
 * T-30: HTTP security headers for every PHP response.
 *
 * The static customer pages get the same headers from .htaccess. Keeping a PHP
 * copy means the API and admin pages are protected even when the site is served
 * by something that ignores .htaccess.
 *
 * CSP notes:
 *   - T-61: the customer directory no longer uses inline scripts or inline
 *     event handlers, so it gets a script-src WITHOUT 'unsafe-inline'. The
 *     admin pages (and any API response) still rely on inline handlers and keep
 *     it until those are extracted too.
 *   - Everything else is locked to the site itself plus the font/icon/chart
 *     CDNs the pages actually use.
 *   - frame-src allows Cloudflare Turnstile; frame-ancestors prevents the site
 *     from being embedded by another origin (clickjacking).
 */

if (headers_sent()) {
    return;
}

$isCustomerArea = strpos((string) ($_SERVER['REQUEST_URI'] ?? ''), '/customer/') !== false;
$scriptSrc = $isCustomerArea
    ? "'self' https://cdnjs.cloudflare.com https://cdn.jsdelivr.net https://challenges.cloudflare.com"
    : "'self' 'unsafe-inline' https://cdnjs.cloudflare.com https://cdn.jsdelivr.net https://challenges.cloudflare.com";

header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');
header('Referrer-Policy: strict-origin-when-cross-origin');
header('Permissions-Policy: geolocation=(), microphone=(), camera=()');
header('Cross-Origin-Opener-Policy: same-origin');
header(
    "Content-Security-Policy: default-src 'self'; " .
    "base-uri 'self'; " .
    "object-src 'none'; " .
    "frame-ancestors 'self'; " .
    "form-action 'self'; " .
    "script-src $scriptSrc; " .
    "style-src 'self' 'unsafe-inline' https://cdnjs.cloudflare.com https://fonts.googleapis.com; " .
    "font-src 'self' data: https://fonts.gstatic.com https://cdnjs.cloudflare.com; " .
    "img-src 'self' data: blob: https:; " .
    "connect-src 'self' https://challenges.cloudflare.com; " .
    "frame-src 'self' https://challenges.cloudflare.com; " .
    "media-src 'self'; " .
    "worker-src 'self' blob:"
);

// HSTS only makes sense over HTTPS. Sending it on http://localhost is ignored
// by browsers but would be wrong to promise.
$isHttps = (!empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off')
    || (($_SERVER['SERVER_PORT'] ?? '') === '443');
if ($isHttps) {
    header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
}
