<?php
require_once __DIR__ . '/../config/db.php';

try {
    // 1. Alter existing `services` table safely
    $pdo->exec("ALTER TABLE `services` 
        ADD COLUMN IF NOT EXISTS `price_display` VARCHAR(50) DEFAULT NULL AFTER `base_price`,
        ADD COLUMN IF NOT EXISTS `per_head` DECIMAL(10,2) DEFAULT NULL AFTER `price_display`,
        ADD COLUMN IF NOT EXISTS `per_head_by_pkg` TINYINT(1) DEFAULT 0 AFTER `per_head`,
        ADD COLUMN IF NOT EXISTS `max_cap` INT(11) DEFAULT NULL AFTER `per_head_by_pkg`,
        ADD COLUMN IF NOT EXISTS `inclusions` JSON DEFAULT NULL AFTER `description`,
        ADD COLUMN IF NOT EXISTS `guidelines` JSON DEFAULT NULL AFTER `inclusions`,
        ADD COLUMN IF NOT EXISTS `is_active` TINYINT(1) DEFAULT 1 AFTER `guidelines`,
        ADD COLUMN IF NOT EXISTS `sort_order` INT(11) DEFAULT 0 AFTER `is_active`
    ");

    // 2. Create `service_packages` table
    $pdo->exec("CREATE TABLE IF NOT EXISTS `service_packages` (
        `id` INT(11) NOT NULL AUTO_INCREMENT,
        `service_id` VARCHAR(50) NOT NULL,
        `package_code` VARCHAR(100) NOT NULL,
        `package_name` VARCHAR(150) NOT NULL,
        `price` DECIMAL(10,2) NOT NULL,
        `is_active` TINYINT(1) DEFAULT 1,
        `sort_order` INT(11) DEFAULT 0,
        PRIMARY KEY (`id`),
        UNIQUE KEY `uq_service_package` (`service_id`, `package_code`),
        KEY `idx_service_packages` (`service_id`, `is_active`),
        CONSTRAINT `fk_service_packages_service` FOREIGN KEY (`service_id`) REFERENCES `services` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    // 3. Create `service_menus` table
    $pdo->exec("CREATE TABLE IF NOT EXISTS `service_menus` (
        `id` INT(11) NOT NULL AUTO_INCREMENT,
        `service_id` VARCHAR(50) NOT NULL,
        `menu_code` VARCHAR(100) NOT NULL,
        `category_name` VARCHAR(100) NOT NULL,
        `item_name` VARCHAR(150) NOT NULL,
        `price` DECIMAL(10,2) DEFAULT NULL,
        `dishes` JSON DEFAULT NULL,
        `is_active` TINYINT(1) DEFAULT 1,
        PRIMARY KEY (`id`),
        UNIQUE KEY `uq_service_menu` (`service_id`, `menu_code`),
        KEY `idx_service_menus` (`service_id`, `is_active`),
        CONSTRAINT `fk_service_menus_service` FOREIGN KEY (`service_id`) REFERENCES `services` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    // 4. Create `service_addons` table
    $pdo->exec("CREATE TABLE IF NOT EXISTS `service_addons` (
        `id` VARCHAR(50) NOT NULL,
        `service_id` VARCHAR(50) NOT NULL,
        `name` VARCHAR(100) NOT NULL,
        `description` TEXT DEFAULT NULL,
        `price_from` VARCHAR(50) DEFAULT NULL,
        `fixed_price` DECIMAL(10,2) DEFAULT NULL,
        `allow_qty` TINYINT(1) DEFAULT 0,
        `is_active` TINYINT(1) DEFAULT 1,
        PRIMARY KEY (`id`),
        KEY `idx_service_addons_service` (`service_id`),
        CONSTRAINT `fk_service_addons_service` FOREIGN KEY (`service_id`) REFERENCES `services` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    echo "Migration completed successfully.\n";
} catch (Exception $e) {
    echo "Migration failed: " . $e->getMessage() . "\n";
}
