-- 004: T-23 - make date exclusivity a catalog property, not a hardcoded name.
--
-- Before this, capacity.php compared reservations.main_service against the
-- literal "Venue Rentals". Renaming that service silently disabled the
-- one-booking-per-date rule. The flag follows the service row, so a rename is
-- harmless. Set the flag to 1 for any service that should close a whole date.

ALTER TABLE `services` ADD COLUMN `is_exclusive` TINYINT(1) NOT NULL DEFAULT 0 AFTER `max_cap`;

UPDATE `services` SET `is_exclusive` = 1 WHERE `id` = 'venue';
