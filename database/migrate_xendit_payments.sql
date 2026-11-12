-- DEPRECATED: kept for older setup instructions. The canonical, idempotent
-- version is database/migrations/002_xendit_payments.sql, applied by
-- `php database/migrate.php`. This file is still safe to run because
-- run_xendit_migration.php skips duplicate columns/keys.
--
-- Xendit test-payment support for Pio De Roda booking system
-- Run once against the `pdrsystem` database (phpMyAdmin / MySQL client).
-- Back up your database before running (see database/*.sql backups).

-- Extend `payments` to store gateway references (idempotent-friendly via unique key).
ALTER TABLE `payments`
  ADD COLUMN `provider` VARCHAR(30) NULL DEFAULT 'xendit' AFTER `reservation_id`,
  ADD COLUMN `provider_reference` VARCHAR(100) NULL AFTER `provider`,
  ADD COLUMN `external_id` VARCHAR(100) NULL AFTER `provider_reference`,
  ADD COLUMN `payment_type` VARCHAR(20) NULL AFTER `amount`,           -- full | deposit
  ADD COLUMN `payment_channel` VARCHAR(50) NULL AFTER `payment_type`, -- e.g. GCASH, BANK_TRANSFER (from webhook)
  ADD COLUMN `paid_at` DATETIME NULL AFTER `status`,
  ADD COLUMN `raw_response` MEDIUMTEXT NULL AFTER `paid_at`;

ALTER TABLE `payments`
  ADD UNIQUE KEY `uq_payments_provider_ref` (`provider`, `provider_reference`),
  ADD KEY `idx_payments_external` (`external_id`);

-- Optional: track a simple paid state on the reservation without changing existing status enum.
ALTER TABLE `reservations`
  ADD COLUMN `payment_status` VARCHAR(30) NULL DEFAULT 'Unpaid' AFTER `payment_method`,
  ADD COLUMN `amount_paid` DECIMAL(10,2) NULL DEFAULT 0.00 AFTER `payment_status`;

-- NOTE (MySQL 5.7): `ADD COLUMN IF NOT EXISTS` is not supported; running this twice
-- will error on duplicate columns/keys. That is safe to ignore if already applied.
