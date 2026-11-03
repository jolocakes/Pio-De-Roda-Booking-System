-- Database Schema for Hospitality Reservation and Event Booking System for Pio De Roda
-- Database: pdrsystem

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";

-- --------------------------------------------------------


--
-- Table structure for table `admin_users`
--
CREATE TABLE IF NOT EXISTS `admin_users` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `username` VARCHAR(50) NOT NULL UNIQUE,
  `password_hash` VARCHAR(255) NOT NULL,
  `full_name` VARCHAR(100) NOT NULL,
  `role` VARCHAR(50) DEFAULT 'Administrator',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `services`
--
CREATE TABLE IF NOT EXISTS `services` (
  `id` VARCHAR(50) NOT NULL,
  `name` VARCHAR(100) NOT NULL,
  `category` VARCHAR(50) DEFAULT NULL,
  `base_price` DECIMAL(10,2) DEFAULT 0.00,
  `price_display` VARCHAR(100) DEFAULT NULL,
  `per_head` DECIMAL(10,2) DEFAULT NULL,
  `per_head_by_pkg` TINYINT(1) DEFAULT 0,
  `max_cap` INT(11) DEFAULT NULL,
  `description` TEXT DEFAULT NULL,
  `inclusions` JSON DEFAULT NULL,
  `guidelines` JSON DEFAULT NULL,
  `customer_display` JSON DEFAULT NULL,
  `is_active` TINYINT(1) DEFAULT 1,
  `sort_order` INT(11) DEFAULT 0,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `service_packages`
--
CREATE TABLE IF NOT EXISTS `service_packages` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `service_id` VARCHAR(50) NOT NULL,
  `package_code` VARCHAR(50) DEFAULT NULL,
  `package_name` VARCHAR(150) NOT NULL,
  `price` DECIMAL(10,2) DEFAULT NULL,
  `max_cap` INT(11) DEFAULT NULL,
  `is_active` TINYINT(1) DEFAULT 1,
  `sort_order` INT(11) DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `idx_service_packages_service` (`service_id`),
  CONSTRAINT `fk_service_packages_service` FOREIGN KEY (`service_id`) REFERENCES `services` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `service_menus`
--
CREATE TABLE IF NOT EXISTS `service_menus` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `service_id` VARCHAR(50) NOT NULL,
  `menu_code` VARCHAR(50) DEFAULT NULL,
  `category_name` VARCHAR(150) DEFAULT NULL,
  `item_name` VARCHAR(150) NOT NULL,
  `price` DECIMAL(10,2) DEFAULT NULL,
  `dishes` JSON DEFAULT NULL,
  `is_active` TINYINT(1) DEFAULT 1,
  PRIMARY KEY (`id`),
  KEY `idx_service_menus_service` (`service_id`),
  CONSTRAINT `fk_service_menus_service` FOREIGN KEY (`service_id`) REFERENCES `services` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `service_addons`
--
CREATE TABLE IF NOT EXISTS `service_addons` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `service_id` VARCHAR(50) NOT NULL,
  `name` VARCHAR(150) NOT NULL,
  `description` TEXT DEFAULT NULL,
  `price_from` VARCHAR(50) DEFAULT NULL,
  `fixed_price` DECIMAL(10,2) DEFAULT NULL,
  `allow_qty` TINYINT(1) DEFAULT 0,
  `is_active` TINYINT(1) DEFAULT 1,
  PRIMARY KEY (`id`),
  KEY `idx_service_addons_service` (`service_id`),
  CONSTRAINT `fk_service_addons_service` FOREIGN KEY (`service_id`) REFERENCES `services` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `reservations`
--
CREATE TABLE IF NOT EXISTS `reservations` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `reservation_id` VARCHAR(30) NOT NULL UNIQUE,
  `booking_pin_hash` VARCHAR(255) NOT NULL,
  `status` ENUM('Pending', 'Confirmed', 'Cancelled', 'Completed') DEFAULT 'Pending',
  `customer_name` VARCHAR(100) NOT NULL,
  `contact_person` VARCHAR(100) DEFAULT NULL,
  `contact_number` VARCHAR(20) NOT NULL,
  `email` VARCHAR(100) NOT NULL,
  `address` VARCHAR(255) DEFAULT NULL,
  `service_id` VARCHAR(50) DEFAULT NULL,
  `main_service` VARCHAR(100) NOT NULL,
  `event_date` DATE NOT NULL,
  `event_time` VARCHAR(50) NOT NULL,
  `event_type` VARCHAR(100) DEFAULT NULL,
  `guests` INT(11) NOT NULL,
  `selected_package` VARCHAR(100) DEFAULT NULL,
  `main_courses` TEXT DEFAULT NULL,
  `additional_services` TEXT DEFAULT NULL,
  `estimated_total` DECIMAL(10,2) DEFAULT 0.00,
  `payment_method` VARCHAR(50) DEFAULT NULL,
  `submitted_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_reservation_id` (`reservation_id`),
  KEY `idx_event_date` (`event_date`),
  KEY `idx_status` (`status`),
  CONSTRAINT `fk_reservations_service` FOREIGN KEY (`service_id`) REFERENCES `services` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `payments`
--
CREATE TABLE IF NOT EXISTS `payments` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `reservation_id` VARCHAR(30) NOT NULL,
  `amount` DECIMAL(10,2) NOT NULL,
  `proof_image` VARCHAR(255) DEFAULT NULL,
  `status` ENUM('Pending', 'Verified', 'Rejected') DEFAULT 'Pending',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_payments_reservation` (`reservation_id`),
  CONSTRAINT `fk_payments_reservation` FOREIGN KEY (`reservation_id`) REFERENCES `reservations` (`reservation_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `reviews`
--
CREATE TABLE IF NOT EXISTS `reviews` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `reservation_id` VARCHAR(30) DEFAULT NULL,
  `customer_name` VARCHAR(100) NOT NULL,
  `rating` INT(11) NOT NULL CHECK (`rating` >= 1 AND `rating` <= 5),
  `feedback_text` TEXT NOT NULL,
  `archived` TINYINT(1) DEFAULT 0,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_reviews_reservation` (`reservation_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

COMMIT;
