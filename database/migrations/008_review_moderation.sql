-- 008: T-33 - review submission + moderation.
--
-- Customers submit a review from the Booking Status page after their event is
-- Completed. Every review starts as Pending and is invisible until an admin
-- approves it. The unique reservation key enforces one review per booking.

ALTER TABLE `reviews`
  ADD COLUMN `status` ENUM('Pending','Approved','Hidden') NOT NULL DEFAULT 'Pending' AFTER `archived`;
ALTER TABLE `reviews`
  ADD COLUMN `admin_note` VARCHAR(255) NULL AFTER `status`;
ALTER TABLE `reviews`
  ADD COLUMN `moderated_by` INT NULL AFTER `admin_note`;
ALTER TABLE `reviews`
  ADD COLUMN `moderated_at` DATETIME NULL AFTER `moderated_by`;
ALTER TABLE `reviews`
  ADD UNIQUE KEY `uq_reviews_reservation` (`reservation_id`);
