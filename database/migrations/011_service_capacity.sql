-- Per-service booking capacity.
--
-- Replaces the old global "2 active bookings per day" cap and the cross-service
-- venue exclusivity. Each service now carries its own:
--   duration_minutes : how long one booking occupies, measured from its start
--   max_per_day      : how many active bookings of this service a date may hold
--   max_concurrent   : how many bookings of this service may overlap in time
--
-- NULL means "no limit". A duration of 0 means the booking does not occupy a
-- time range (it can only clash with another booking at the exact same start).
--
-- Values below are the business's current operating times and capacities.

ALTER TABLE `services` ADD COLUMN IF NOT EXISTS `duration_minutes` INT(11) DEFAULT NULL AFTER `is_exclusive`;
ALTER TABLE `services` ADD COLUMN IF NOT EXISTS `max_per_day` INT(11) DEFAULT NULL AFTER `duration_minutes`;
ALTER TABLE `services` ADD COLUMN IF NOT EXISTS `max_concurrent` INT(11) DEFAULT NULL AFTER `max_per_day`;

-- Venue Rentals — 4 hours, one booking per day.
UPDATE `services` SET `duration_minutes` = 240, `max_per_day` = 1, `max_concurrent` = 1 WHERE `id` = 'venue';

-- Catering with Menu — 4 hours, one booking per day (same as the venue).
UPDATE `services` SET `duration_minutes` = 240, `max_per_day` = 1, `max_concurrent` = 1 WHERE `id` = 'catering-menu';

-- Booths — two events may run at the same time (separate crews/venues).
UPDATE `services` SET `duration_minutes` = 210, `max_per_day` = 2, `max_concurrent` = 2 WHERE `id` = 'noncoffee';
UPDATE `services` SET `duration_minutes` = 210, `max_per_day` = 2, `max_concurrent` = 2 WHERE `id` = 'coffee';
UPDATE `services` SET `duration_minutes` = 150, `max_per_day` = 2, `max_concurrent` = 2 WHERE `id` = 'pizza';

-- Intimate Celebration — 2 hours, up to two per day, but they may not overlap.
UPDATE `services` SET `duration_minutes` = 120, `max_per_day` = 2, `max_concurrent` = 1 WHERE `id` = 'intimate';

-- Equipment Rental — twice a day; it does not hold a time range.
UPDATE `services` SET `duration_minutes` = 0, `max_per_day` = 2, `max_concurrent` = 2 WHERE `id` = 'equipment';

-- Dine-In Menu — 1 to 1.5 hours; no daily or concurrency cap.
UPDATE `services` SET `duration_minutes` = 90, `max_per_day` = NULL, `max_concurrent` = NULL WHERE `id` = 'dinein';
