-- 007: T-24 - staff-controlled calendar blackout dates.
--
-- A blackout closes a whole date for every service (holiday, maintenance,
-- owner's event). It is separate from customer bookings so staff can explain
-- WHY a date is closed, and removing it re-opens the date immediately.

CREATE TABLE IF NOT EXISTS `blackout_dates` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `blackout_date` DATE NOT NULL,
  `reason` VARCHAR(255) NULL,
  `created_by` INT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_blackout_date` (`blackout_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
