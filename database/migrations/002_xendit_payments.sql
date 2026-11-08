-- 002: Xendit payment references and paid-state columns.
-- Every clause is guarded so the file can heal a partially-migrated database.

ALTER TABLE `payments`
  ADD COLUMN IF NOT EXISTS `provider` VARCHAR(30) NULL DEFAULT 'xendit' AFTER `reservation_id`,
  ADD COLUMN IF NOT EXISTS `provider_reference` VARCHAR(100) NULL AFTER `provider`,
  ADD COLUMN IF NOT EXISTS `external_id` VARCHAR(100) NULL AFTER `provider_reference`,
  ADD COLUMN IF NOT EXISTS `payment_type` VARCHAR(20) NULL AFTER `amount`,
  ADD COLUMN IF NOT EXISTS `payment_channel` VARCHAR(50) NULL AFTER `payment_type`,
  ADD COLUMN IF NOT EXISTS `paid_at` DATETIME NULL AFTER `status`,
  ADD COLUMN IF NOT EXISTS `raw_response` MEDIUMTEXT NULL AFTER `paid_at`;

ALTER TABLE `payments`
  ADD UNIQUE KEY IF NOT EXISTS `uq_payments_provider_ref` (`provider`, `provider_reference`),
  ADD KEY IF NOT EXISTS `idx_payments_external` (`external_id`);

ALTER TABLE `reservations`
  ADD COLUMN IF NOT EXISTS `payment_status` VARCHAR(30) NULL DEFAULT 'Unpaid' AFTER `payment_method`,
  ADD COLUMN IF NOT EXISTS `amount_paid` DECIMAL(10,2) NULL DEFAULT 0.00 AFTER `payment_status`;
