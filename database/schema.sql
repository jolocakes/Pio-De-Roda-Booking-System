-- Pio De Roda Hospitality Reservation and Event Booking System
-- Canonical database schema (tables + columns + indexes).
--
-- Generated from the live database after migrations 001-010.
-- Import this file for a fresh install, then run database/migrate.php if
-- you are adding migrations to an existing database.
--
-- All CREATE TABLE statements use IF NOT EXISTS so importing twice is safe.

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS=0;

CREATE TABLE IF NOT EXISTS `admin_users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(50) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `full_name` varchar(100) NOT NULL,
  `role` varchar(50) DEFAULT 'Administrator',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS `audit_logs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `actor_id` int(11) DEFAULT NULL,
  `actor_username` varchar(50) DEFAULT NULL,
  `action` varchar(50) NOT NULL,
  `target_type` varchar(40) DEFAULT NULL,
  `target_id` varchar(64) DEFAULT NULL,
  `details` text DEFAULT NULL,
  `ip_hash` char(64) DEFAULT NULL,
  `user_agent` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_audit_created` (`created_at`),
  KEY `idx_audit_actor` (`actor_id`,`created_at`),
  KEY `idx_audit_target` (`target_type`,`target_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS `blackout_dates` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `blackout_date` date NOT NULL,
  `reason` varchar(255) DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_blackout_date` (`blackout_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS `catalog_guidelines` (
  `id` varchar(64) NOT NULL,
  `heading` varchar(150) NOT NULL,
  `items` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`items`)),
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  KEY `idx_catalog_guidelines_active` (`is_active`,`sort_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS `customers` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `email` varchar(100) NOT NULL,
  `full_name` varchar(100) NOT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `address` varchar(255) DEFAULT NULL,
  `status` enum('Active','Inactive') NOT NULL DEFAULT 'Active',
  `archived_at` datetime DEFAULT NULL,
  `archive_reason` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_customers_email` (`email`),
  KEY `idx_customers_archived` (`archived_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS `login_attempts` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `ip_hash` char(64) NOT NULL,
  `username` varchar(100) NOT NULL DEFAULT '',
  `success` tinyint(1) NOT NULL DEFAULT 0,
  `attempted_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_login_ip_time` (`ip_hash`,`attempted_at`),
  KEY `idx_login_user_time` (`username`,`attempted_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
CREATE TABLE IF NOT EXISTS `lookup_attempts` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `ip_hash` char(64) NOT NULL,
  `reservation_id` varchar(30) NOT NULL DEFAULT '',
  `success` tinyint(1) NOT NULL DEFAULT 0,
  `attempted_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_lookup_ip_time` (`ip_hash`,`success`,`attempted_at`),
  KEY `idx_lookup_res_time` (`reservation_id`,`success`,`attempted_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS `payments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `reservation_id` varchar(30) NOT NULL,
  `provider` varchar(30) DEFAULT 'xendit',
  `provider_reference` varchar(100) DEFAULT NULL,
  `external_id` varchar(100) DEFAULT NULL,
  `amount` decimal(10,2) NOT NULL,
  `paid_amount` decimal(10,2) DEFAULT NULL,
  `payment_type` varchar(20) DEFAULT NULL,
  `payment_channel` varchar(50) DEFAULT NULL,
  `proof_image` varchar(255) DEFAULT NULL,
  `status` enum('Pending','Verified','Rejected','For Review') DEFAULT 'Pending',
  `paid_at` datetime DEFAULT NULL,
  `raw_response` mediumtext DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_payments_provider_ref` (`provider`,`provider_reference`),
  KEY `idx_payments_reservation` (`reservation_id`),
  KEY `idx_payments_external` (`external_id`),
  CONSTRAINT `fk_payments_reservation` FOREIGN KEY (`reservation_id`) REFERENCES `reservations` (`reservation_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS `reservations` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `reservation_id` varchar(30) NOT NULL,
  `booking_pin_hash` varchar(255) NOT NULL,
  `status` enum('Pending','Confirmed','Cancelled','Completed','Rejected') DEFAULT 'Pending',
  `reschedule_count` int(10) unsigned NOT NULL DEFAULT 0,
  `customer_name` varchar(100) NOT NULL,
  `contact_person` varchar(100) DEFAULT NULL,
  `contact_number` varchar(20) NOT NULL,
  `email` varchar(100) NOT NULL,
  `address` varchar(255) DEFAULT NULL,
  `service_id` varchar(50) DEFAULT NULL,
  `main_service` varchar(100) NOT NULL,
  `event_date` date NOT NULL,
  `event_time` varchar(50) NOT NULL,
  `event_type` varchar(100) DEFAULT NULL,
  `guests` int(11) NOT NULL,
  `selected_package` varchar(100) DEFAULT NULL,
  `main_courses` text DEFAULT NULL,
  `additional_services` text DEFAULT NULL,
  `estimated_total` decimal(10,2) DEFAULT 0.00,
  `payment_method` varchar(50) DEFAULT NULL,
  `payment_status` varchar(30) DEFAULT 'Unpaid',
  `amount_paid` decimal(10,2) DEFAULT 0.00,
  `submitted_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `archived_at` datetime DEFAULT NULL,
  `archive_reason` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `reservation_id` (`reservation_id`),
  KEY `idx_reservation_id` (`reservation_id`),
  KEY `idx_event_date` (`event_date`),
  KEY `idx_status` (`status`),
  KEY `fk_reservations_service` (`service_id`),
  CONSTRAINT `fk_reservations_service` FOREIGN KEY (`service_id`) REFERENCES `services` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS `reviews` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `reservation_id` varchar(30) DEFAULT NULL,
  `customer_name` varchar(100) NOT NULL,
  `rating` int(11) NOT NULL CHECK (`rating` >= 1 and `rating` <= 5),
  `feedback_text` text NOT NULL,
  `archived` tinyint(1) DEFAULT 0,
  `status` enum('Pending','Approved','Hidden') NOT NULL DEFAULT 'Pending',
  `admin_note` varchar(255) DEFAULT NULL,
  `moderated_by` int(11) DEFAULT NULL,
  `moderated_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `archived_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_reviews_reservation` (`reservation_id`),
  KEY `idx_reviews_reservation` (`reservation_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS `schema_migrations` (
  `filename` varchar(191) NOT NULL,
  `checksum` char(64) NOT NULL,
  `applied_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`filename`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
CREATE TABLE IF NOT EXISTS `service_addons` (
  `id` varchar(50) NOT NULL,
  `service_id` varchar(50) NOT NULL,
  `name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `price_from` varchar(50) DEFAULT NULL,
  `fixed_price` decimal(10,2) DEFAULT NULL,
  `allow_qty` tinyint(1) DEFAULT 0,
  `is_active` tinyint(1) DEFAULT 1,
  PRIMARY KEY (`id`),
  KEY `idx_service_addons_service` (`service_id`),
  CONSTRAINT `fk_service_addons_service` FOREIGN KEY (`service_id`) REFERENCES `services` (`id`) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS `service_menus` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `service_id` varchar(50) NOT NULL,
  `menu_code` varchar(100) NOT NULL,
  `category_name` varchar(100) NOT NULL,
  `item_name` varchar(150) NOT NULL,
  `price` decimal(10,2) DEFAULT NULL,
  `dishes` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`dishes`)),
  `is_active` tinyint(1) DEFAULT 1,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_service_menu` (`service_id`,`menu_code`),
  KEY `idx_service_menus` (`service_id`,`is_active`),
  CONSTRAINT `fk_service_menus_service` FOREIGN KEY (`service_id`) REFERENCES `services` (`id`) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS `service_packages` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `service_id` varchar(50) NOT NULL,
  `package_code` varchar(100) NOT NULL,
  `package_name` varchar(150) NOT NULL,
  `price` decimal(10,2) NOT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `sort_order` int(11) DEFAULT 0,
  `max_cap` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_service_package` (`service_id`,`package_code`),
  KEY `idx_service_packages` (`service_id`,`is_active`),
  CONSTRAINT `fk_service_packages_service` FOREIGN KEY (`service_id`) REFERENCES `services` (`id`) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS `services` (
  `id` varchar(50) NOT NULL,
  `name` varchar(100) NOT NULL,
  `category` varchar(50) DEFAULT NULL,
  `base_price` decimal(10,2) DEFAULT 0.00,
  `price_display` varchar(50) DEFAULT NULL,
  `per_head` decimal(10,2) DEFAULT NULL,
  `per_head_by_pkg` tinyint(1) DEFAULT 0,
  `max_cap` int(11) DEFAULT NULL,
  `is_exclusive` tinyint(1) NOT NULL DEFAULT 0,
  `description` text DEFAULT NULL,
  `inclusions` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`inclusions`)),
  `guidelines` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`guidelines`)),
  `customer_display` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`customer_display`)),
  `is_active` tinyint(1) DEFAULT 1,
  `sort_order` int(11) DEFAULT 0,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS=1;
