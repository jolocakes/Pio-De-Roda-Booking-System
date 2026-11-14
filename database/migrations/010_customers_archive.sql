-- 010: T-20 customer directory + T-22 soft-delete/archive support.
--
-- The customer directory is a real table (derived from reservations on first
-- run and kept in sync by api/submit_reservation.php). Keeping it in its own
-- table means a customer can be archived independently of their bookings.
--
-- Every statement is idempotent so this migration can be re-run safely.

CREATE TABLE IF NOT EXISTS `customers` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `email` varchar(100) NOT NULL,
  `full_name` varchar(100) NOT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `address` varchar(255) DEFAULT NULL,
  `status` enum('Active','Inactive') NOT NULL DEFAULT 'Active',
  `archived_at` datetime DEFAULT NULL,
  `archive_reason` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_customers_email` (`email`),
  KEY `idx_customers_archived` (`archived_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE `reservations`
  ADD COLUMN IF NOT EXISTS `archived_at` datetime DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS `archive_reason` varchar(255) DEFAULT NULL;

ALTER TABLE `reviews`
  ADD COLUMN IF NOT EXISTS `archived_at` datetime DEFAULT NULL;

-- Backfill the directory from the most recent reservation per email address.
INSERT INTO `customers` (`email`, `full_name`, `phone`, `address`)
SELECT r.`email`, r.`customer_name`, r.`contact_number`, r.`address`
  FROM `reservations` r
  JOIN (
        SELECT `email`, MAX(`id`) AS max_id
          FROM `reservations`
         WHERE `email` <> ''
         GROUP BY `email`
       ) latest ON latest.max_id = r.`id`
 WHERE r.`email` <> ''
ON DUPLICATE KEY UPDATE
  `full_name` = VALUES(`full_name`),
  `phone` = VALUES(`phone`),
  `address` = VALUES(`address`);
