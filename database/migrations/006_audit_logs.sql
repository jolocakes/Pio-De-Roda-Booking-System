-- 006: T-35 - administrative audit trail.
--
-- Records who changed what and when for every state-changing admin action.
-- The client IP is hashed (same privacy approach as login_attempts) so the log
-- is useful for accountability without storing a raw address.

CREATE TABLE IF NOT EXISTS `audit_logs` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `actor_id` INT NULL,
  `actor_username` VARCHAR(50) NULL,
  `action` VARCHAR(50) NOT NULL,
  `target_type` VARCHAR(40) NULL,
  `target_id` VARCHAR(64) NULL,
  `details` TEXT NULL,
  `ip_hash` CHAR(64) NULL,
  `user_agent` VARCHAR(255) NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_audit_created` (`created_at`),
  KEY `idx_audit_actor` (`actor_id`, `created_at`),
  KEY `idx_audit_target` (`target_type`, `target_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
