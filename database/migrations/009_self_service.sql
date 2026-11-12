-- 009: T-37 - self-service cancellation and rescheduling.
--
-- A customer may reschedule once per booking. The counter gives the server a
-- hard limit without a separate history table.

ALTER TABLE `reservations`
  ADD COLUMN `reschedule_count` INT UNSIGNED NOT NULL DEFAULT 0 AFTER `status`;
