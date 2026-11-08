<?php
/**
 * Lightweight per-IP rate limiting for public endpoints (contact form, etc.).
 *
 * State lives in files outside the web root, keyed by a salted hash of the
 * client IP — no raw addresses and no customer data are stored. The limit is
 * best-effort: if the temp directory is unavailable the request is allowed
 * (a broken cache must not take the contact form down).
 */

require_once __DIR__ . '/../config/env.php';

function pdrRateLimitIpHash(string $bucket): string
{
    $ip = (string) ($_SERVER['REMOTE_ADDR'] ?? 'unknown');
    $secret = (string) pdrEnv('PDR_LOOKUP_SECRET', 'pdr-rate-limit-local');
    return hash('sha256', $secret . '|' . $bucket . '|' . $ip);
}

/**
 * @return array{allowed:bool,retryAfter:int}
 */
function pdrRateLimit(string $bucket, int $max, int $windowSeconds): array
{
    $dir = rtrim(sys_get_temp_dir(), '\\/') . DIRECTORY_SEPARATOR . 'pdr-ratelimit';
    if (!is_dir($dir) && !@mkdir($dir, 0700, true) && !is_dir($dir)) {
        return ['allowed' => true, 'retryAfter' => 0];
    }

    $file = $dir . DIRECTORY_SEPARATOR . pdrRateLimitIpHash($bucket) . '.json';
    $now = time();
    $fh = @fopen($file, 'c+');
    if ($fh === false) {
        return ['allowed' => true, 'retryAfter' => 0];
    }

    try {
        if (!flock($fh, LOCK_EX)) {
            return ['allowed' => true, 'retryAfter' => 0];
        }

        $raw = stream_get_contents($fh);
        $stamps = [];
        if (is_string($raw) && $raw !== '') {
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                foreach ($decoded as $ts) {
                    if (is_int($ts) && $ts > $now - $windowSeconds) {
                        $stamps[] = $ts;
                    }
                }
            }
        }

        if (count($stamps) >= $max) {
            $oldest = min($stamps);
            return ['allowed' => false, 'retryAfter' => max(1, ($oldest + $windowSeconds) - $now)];
        }

        $stamps[] = $now;
        ftruncate($fh, 0);
        rewind($fh);
        fwrite($fh, json_encode($stamps));
        fflush($fh);

        // Opportunistic cleanup of stale buckets.
        if (random_int(1, 50) === 1) {
            foreach ((array) glob($dir . DIRECTORY_SEPARATOR . '*.json') as $stale) {
                if (@filemtime($stale) < $now - 86400) {
                    @unlink($stale);
                }
            }
        }

        return ['allowed' => true, 'retryAfter' => 0];
    } finally {
        flock($fh, LOCK_UN);
        fclose($fh);
    }
}
