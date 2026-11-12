-- 005: T-25 - distinguish an admin rejection from a customer/staff cancellation.
--
-- Both buttons used to write "Cancelled", so reporting could not tell a booking
-- the venue refused from one that was called off. "Rejected" is a terminal,
-- non-occupying status just like Cancelled (capacity.php only counts Pending
-- and Confirmed), so existing availability rules are unaffected.

ALTER TABLE `reservations`
  MODIFY COLUMN `status` ENUM('Pending','Confirmed','Cancelled','Completed','Rejected')
  NULL DEFAULT 'Pending';
