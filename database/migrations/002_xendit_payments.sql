-- 002: Xendit payment references and paid-state columns.
--
-- One statement per object on purpose: if a database was partially migrated,
-- the runner (database/migrate.php) skips the duplicate-column/key error for
-- that one statement and continues with the rest.
--
-- Plain ADD COLUMN syntax (no IF NOT EXISTS) keeps the file valid on both
-- MariaDB and MySQL 8; idempotency comes from the schema_migrations ledger plus
-- the runner's duplicate-object handling.

ALTER TABLE `payments` ADD COLUMN `provider` VARCHAR(30) NULL DEFAULT 'xendit' AFTER `reservation_id`;
ALTER TABLE `payments` ADD COLUMN `provider_reference` VARCHAR(100) NULL AFTER `provider`;
ALTER TABLE `payments` ADD COLUMN `external_id` VARCHAR(100) NULL AFTER `provider_reference`;
ALTER TABLE `payments` ADD COLUMN `payment_type` VARCHAR(20) NULL AFTER `amount`;
ALTER TABLE `payments` ADD COLUMN `payment_channel` VARCHAR(50) NULL AFTER `payment_type`;
ALTER TABLE `payments` ADD COLUMN `paid_at` DATETIME NULL AFTER `status`;
ALTER TABLE `payments` ADD COLUMN `raw_response` MEDIUMTEXT NULL AFTER `paid_at`;

ALTER TABLE `payments` ADD UNIQUE KEY `uq_payments_provider_ref` (`provider`, `provider_reference`);
ALTER TABLE `payments` ADD KEY `idx_payments_external` (`external_id`);

ALTER TABLE `reservations` ADD COLUMN `payment_status` VARCHAR(30) NULL DEFAULT 'Unpaid' AFTER `payment_method`;
ALTER TABLE `reservations` ADD COLUMN `amount_paid` DECIMAL(10,2) NULL DEFAULT 0.00 AFTER `payment_status`;
