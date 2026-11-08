-- 003: payment amount verification (T-15).
-- Adds a review state for webhook amount mismatches and stores the amount the
-- gateway reported so an administrator can compare it with the invoice.
ALTER TABLE `payments`
  MODIFY COLUMN `status` ENUM('Pending','Verified','Rejected','For Review') NULL DEFAULT 'Pending';

ALTER TABLE `payments`
  ADD COLUMN IF NOT EXISTS `paid_amount` DECIMAL(10,2) NULL AFTER `amount`;
