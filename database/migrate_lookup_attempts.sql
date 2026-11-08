-- T-05: brute-force protection for reservation lookup / PIN verification.
-- Run once against the `pdrsystem` database (phpMyAdmin / MySQL client).
-- Safe to run repeatedly (CREATE TABLE IF NOT EXISTS).

CREATE TABLE IF NOT EXISTS `lookup_attempts` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `ip_hash` CHAR(64) NOT NULL,
  `reservation_id` VARCHAR(30) NOT NULL DEFAULT '',
  `success` TINYINT(1) NOT NULL DEFAULT 0,
  `attempted_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_lookup_ip_time` (`ip_hash`, `success`, `attempted_at`),
  KEY `idx_lookup_res_time` (`reservation_id`, `success`, `attempted_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Optional retention sweep (run manually or from cron):
-- DELETE FROM lookup_attempts WHERE attempted_at < (NOW() - INTERVAL 7 DAY);
