-- Additive initial schema for Pio De Roda. Does not drop or alter existing tables.
CREATE DATABASE IF NOT EXISTS pdrsystem CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE pdrsystem;
CREATE TABLE IF NOT EXISTS admins (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(120) NOT NULL,
  email VARCHAR(190) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  role VARCHAR(30) NOT NULL DEFAULT 'admin',
  active TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS reservations (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  reservation_code VARCHAR(32) NOT NULL UNIQUE,
  booking_pin_hash VARCHAR(255) NOT NULL,
  customer_name VARCHAR(160) NOT NULL,
  contact_number VARCHAR(20) NOT NULL,
  email VARCHAR(190) NOT NULL,
  event_date DATE NOT NULL,
  event_time TIME NOT NULL,
  event_type VARCHAR(100) NOT NULL,
  guests SMALLINT UNSIGNED NOT NULL,
  main_service VARCHAR(120) NOT NULL,
  selected_package VARCHAR(160) NOT NULL,
  main_courses JSON NULL,
  additional_services JSON NOT NULL,
  estimated_total DECIMAL(12,2) NOT NULL DEFAULT 0,
  status ENUM('Pending','Confirmed','Rejected','Cancelled') NOT NULL DEFAULT 'Pending',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  updated_by BIGINT UNSIGNED NULL,
  INDEX ix_reservation_date_status (event_date, status),
  INDEX ix_reservation_slot (event_date, event_time, status),
  INDEX ix_reservation_email (email),
  CONSTRAINT fk_reservation_admin FOREIGN KEY (updated_by) REFERENCES admins(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS reservation_status_history (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  reservation_id BIGINT UNSIGNED NOT NULL,
  old_status VARCHAR(20) NULL,
  new_status VARCHAR(20) NOT NULL,
  changed_by BIGINT UNSIGNED NULL,
  changed_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX ix_history_reservation (reservation_id, changed_at),
  CONSTRAINT fk_history_reservation FOREIGN KEY (reservation_id) REFERENCES reservations(id) ON DELETE CASCADE,
  CONSTRAINT fk_history_admin FOREIGN KEY (changed_by) REFERENCES admins(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS reservation_lookup_attempts (
  ip_hash CHAR(64) NOT NULL PRIMARY KEY,
  failures TINYINT UNSIGNED NOT NULL DEFAULT 0,
  locked_until DATETIME NULL,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
