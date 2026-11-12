<?php
/**
 * Public, non-secret runtime configuration for the static customer pages.
 *
 * T-40: exposes the booking window so the calendar disables dates the server
 * would reject anyway.
 * T-45: exposes the Turnstile SITE key (public by design). The secret never
 * leaves the server.
 */
header('Content-Type: application/json');
header('Cache-Control: no-store');

require_once __DIR__ . '/../config/captcha.php';
require_once __DIR__ . '/capacity.php';

echo json_encode([
    'ok'               => true,
    'captchaEnabled'   => pdrCaptchaEnabled(),
    'turnstileSiteKey' => pdrCaptchaEnabled() ? pdrCaptchaSiteKey() : '',
    'minLeadDays'      => PDR_MIN_LEAD_DAYS,
    'maxHorizonDays'   => PDR_MAX_HORIZON_DAYS,
]);
