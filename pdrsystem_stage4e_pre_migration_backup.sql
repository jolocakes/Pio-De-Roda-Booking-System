-- MariaDB dump 10.19  Distrib 10.4.32-MariaDB, for Win64 (AMD64)
--
-- Host: localhost    Database: pdrsystem
-- ------------------------------------------------------
-- Server version	10.4.32-MariaDB

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Current Database: `pdrsystem`
--

CREATE DATABASE /*!32312 IF NOT EXISTS*/ `pdrsystem` /*!40100 DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci */;

USE `pdrsystem`;

--
-- Table structure for table `admin_users`
--

DROP TABLE IF EXISTS `admin_users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `admin_users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(50) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `full_name` varchar(100) NOT NULL,
  `role` varchar(50) DEFAULT 'Administrator',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `admin_users`
--

LOCK TABLES `admin_users` WRITE;
/*!40000 ALTER TABLE `admin_users` DISABLE KEYS */;
INSERT INTO `admin_users` VALUES (1,'admin','$2y$10$uYIk79Lrqz8ZTpH7j3jhnObyDDdKP4eYTfm2bKzr0L012b6VJCuxe','Pio De Roda Administrator','Administrator','2026-10-20 11:23:53');
/*!40000 ALTER TABLE `admin_users` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `payments`
--

DROP TABLE IF EXISTS `payments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `payments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `reservation_id` varchar(30) NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `proof_image` varchar(255) DEFAULT NULL,
  `status` enum('Pending','Verified','Rejected') DEFAULT 'Pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_payments_reservation` (`reservation_id`),
  CONSTRAINT `fk_payments_reservation` FOREIGN KEY (`reservation_id`) REFERENCES `reservations` (`reservation_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `payments`
--

LOCK TABLES `payments` WRITE;
/*!40000 ALTER TABLE `payments` DISABLE KEYS */;
/*!40000 ALTER TABLE `payments` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `reservations`
--

DROP TABLE IF EXISTS `reservations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `reservations` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `reservation_id` varchar(30) NOT NULL,
  `booking_pin_hash` varchar(255) NOT NULL,
  `status` enum('Pending','Confirmed','Cancelled','Completed') DEFAULT 'Pending',
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
  `submitted_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `reservation_id` (`reservation_id`),
  KEY `idx_reservation_id` (`reservation_id`),
  KEY `idx_event_date` (`event_date`),
  KEY `idx_status` (`status`),
  KEY `fk_reservations_service` (`service_id`),
  CONSTRAINT `fk_reservations_service` FOREIGN KEY (`service_id`) REFERENCES `services` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `reservations`
--

LOCK TABLES `reservations` WRITE;
/*!40000 ALTER TABLE `reservations` DISABLE KEYS */;
INSERT INTO `reservations` VALUES (4,'PDR-2026-0001','$2y$10$XKPftl/B0ss3.A0eXyuoAeklhSbQTQTxhiurQ.ywooT4hBy.MChX2','Pending','John Louise Cruzada','John Louise Cruzada','09765584105','louisecrzd21@gmail.com',NULL,NULL,'Venue Rentals','2026-10-29','19:00','Birthday',12,'Extension Hall (30 - 35 Persons)',NULL,'[{\"name\":\"Catering with Menu\",\"quantity\":1,\"unitPrice\":1000,\"totalPrice\":1000}]',16000.00,'Cash','2026-10-22 01:37:22'),(5,'PDR-2026-0002','$2y$10$aKrjydNo6vJVbLyIFdoAlel0HcLwv7j4Sh/Fhly4sJz3w5ZOKKHaK','Confirmed','John Louise Cruzada','John Louise Cruzada','09765584105','louisecrzd21@gmail.com',NULL,NULL,'Coffee Booth Package','2026-10-31','17:00','Wedding',12,'Intimate Package (50 Cups)',NULL,'[{\"name\":\"Venue Rentals\",\"quantity\":1,\"unitPrice\":15000,\"totalPrice\":15000}]',21250.00,'Cash','2026-10-22 01:38:56'),(6,'PDR-2026-0003','$2y$10$oaEJ0TxKd5kATsleDE2pZ.Wy5U8HUecrcljyHVYj7v4FlB88vPnDu','Cancelled','John Louise Cruzada','John Louise Cruzada','09765584105','louisecrzd21@gmail.com',NULL,NULL,'Pizza Booth','2026-10-27','13:00','Christmas Party',20,'Intimate Package (50 Persons) with 15 Pizza',NULL,'[{\"name\":\"Coffee Booth Package\",\"quantity\":1,\"unitPrice\":6250,\"totalPrice\":6250}]',13750.00,'Cash','2026-10-22 02:15:20'),(7,'PDR-2026-0004','$2y$10$qbYvzcfiYQ/9NrgbCheGyu8N6mUPc3LfVWkX5ZvgmOMlU/LpbV00u','Confirmed','Sync Test User','Sync Test User','09181234567','synctest@example.com',NULL,NULL,'Cafe','2026-12-01','10:00','Party',10,'Standard',NULL,NULL,3000.00,'Cash','2026-10-22 02:46:16'),(8,'PDR-2026-0005','$2y$10$3LmcrbaezAWIy7CuuNLNrO.lsKhAt4ym2yV5t8JDpWerjq33rrg2u','Pending','John Louise Cruzada','John Louise Cruzada','09765584105','louisecrzd21@gmail.com',NULL,NULL,'Equipment Rental','2026-10-24','20:00','Wedding',50,'Standard Rate',NULL,'[]',300.00,'Cash','2026-10-22 02:57:02'),(9,'PDR-2026-0006','$2y$10$/HMBgF4fx0psIT3cKDPLXuFbd1Feca1LJmeLIQjzSS3/Ym5fOsQDC','Confirmed','John Louise Cruzada','John Louise Cruzada','09765584105','louisecrzd21@gmail.com',NULL,NULL,'Pizza Booth','2026-11-03','15:00','Birthday',30,'Intimate Package (50 Persons) with 15 Pizza',NULL,'[]',7500.00,'Cash','2026-10-24 01:07:51'),(10,'PDR-2026-0007','$2y$10$iO/.2n1mBcBeJDJVehl.fuygj4bcEdexUzAmx7dJse7pfBAHxXope','Confirmed','John Louise Cruzada','John Louise Cruzada','09765584105','louisecrzd21@gmail.com','REMULLA STREET, ESPERANZA IBABA','noncoffee','Non-Coffee Booth Package','2026-11-19','10:00','Birthday',30,'Sparkling Refreshers (50 Cups)',NULL,'[]',4250.00,'Cash','2026-11-04 09:34:03');
/*!40000 ALTER TABLE `reservations` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `reviews`
--

DROP TABLE IF EXISTS `reviews`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `reviews` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `reservation_id` varchar(30) DEFAULT NULL,
  `customer_name` varchar(100) NOT NULL,
  `rating` int(11) NOT NULL CHECK (`rating` >= 1 and `rating` <= 5),
  `feedback_text` text NOT NULL,
  `archived` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_reviews_reservation` (`reservation_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `reviews`
--

LOCK TABLES `reviews` WRITE;
/*!40000 ALTER TABLE `reviews` DISABLE KEYS */;
/*!40000 ALTER TABLE `reviews` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `service_addons`
--

DROP TABLE IF EXISTS `service_addons`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `service_addons` (
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
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `service_addons`
--

LOCK TABLES `service_addons` WRITE;
/*!40000 ALTER TABLE `service_addons` DISABLE KEYS */;
INSERT INTO `service_addons` VALUES ('catering-menu-addon','catering-menu','Catering with Menu','Add catering','catering-menu',NULL,0,1),('coffee-booth-addon','coffee','Coffee Booth Package','Add coffee booth','coffee',NULL,0,1),('dinein-addon','dinein','Dine-In Menu','Add dine-in',NULL,NULL,0,1),('equipment-addon','equipment','Equipment Rental','Add equipment',NULL,NULL,0,1),('intimate-addon','intimate','Intimate Celebration Package','Add intimate package','intimate',NULL,0,1),('noncoffee-booth-addon','noncoffee','Non-Coffee Booth Package','Add non-coffee booth','noncoffee',NULL,0,1),('pizza-booth-addon','pizza','Pizza Booth','Add pizza booth','pizza',NULL,0,1),('venue-addon','venue','Venue Rentals','Add venue','venue',NULL,0,1);
/*!40000 ALTER TABLE `service_addons` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `service_menus`
--

DROP TABLE IF EXISTS `service_menus`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `service_menus` (
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
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `service_menus`
--

LOCK TABLES `service_menus` WRITE;
/*!40000 ALTER TABLE `service_menus` DISABLE KEYS */;
INSERT INTO `service_menus` VALUES (1,'dinein','dinein-menu-a','Menu A','Menu A',600.00,'[\"Plain Rice\",\"Soup\",\"Chicken\"]',1);
/*!40000 ALTER TABLE `service_menus` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `service_packages`
--

DROP TABLE IF EXISTS `service_packages`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `service_packages` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `service_id` varchar(50) NOT NULL,
  `package_code` varchar(100) NOT NULL,
  `package_name` varchar(150) NOT NULL,
  `price` decimal(10,2) NOT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `sort_order` int(11) DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_service_package` (`service_id`,`package_code`),
  KEY `idx_service_packages` (`service_id`,`is_active`),
  CONSTRAINT `fk_service_packages_service` FOREIGN KEY (`service_id`) REFERENCES `services` (`id`) ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=26 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `service_packages`
--

LOCK TABLES `service_packages` WRITE;
/*!40000 ALTER TABLE `service_packages` DISABLE KEYS */;
INSERT INTO `service_packages` VALUES (1,'catering-menu','cat-2-courses','2 Main Courses',1000.00,1,1),(2,'catering-menu','cat-3-courses','3 Main Courses',1100.00,1,2),(3,'catering-menu','cat-4-courses','4 Main Courses',1300.00,1,3),(4,'venue','venue-ext-30-35','Extension Hall (30-35 Persons)',15000.00,1,1),(5,'venue','venue-ext-36-50','Extension Hall (36-50 Persons)',20000.00,1,2),(6,'venue','venue-main-50-65','Main Hall (50-65 Persons)',25000.00,1,3),(7,'venue','venue-main-66-100','Main Hall (66-100 Persons)',30000.00,1,4),(15,'noncoffee','noncoffee-sparkling-50','Sparkling Refreshers (50 Cups)',4250.00,1,1),(16,'noncoffee','noncoffee-sparkling-100','Sparkling Refreshers (100 Cups)',8500.00,1,2),(17,'noncoffee','noncoffee-milktea-50','Creamy Milk Tea (50 Cups)',4250.00,1,3),(18,'noncoffee','noncoffee-milktea-100','Creamy Milk Tea (100 Cups)',8500.00,1,4),(19,'coffee','coffee-intimate-50','Intimate Package (50 Cups)',6250.00,1,1),(20,'coffee','coffee-intimate-100','Intimate Package (100 Cups)',12500.00,1,2),(21,'pizza','pizza-intimate-15','Intimate Package (50 Persons) with 15 Pizza',7500.00,1,1),(22,'pizza','pizza-intimate-20','Intimate Package (50 Persons) with 20 Pizza',10000.00,1,2),(23,'intimate','intimate-package-std','Intimate Package',4000.00,1,1),(24,'intimate','intimate-package-ultimate','Ultimate Package',14000.00,1,2),(25,'equipment','equipment-standard','Standard Rate',300.00,1,1);
/*!40000 ALTER TABLE `service_packages` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `services`
--

DROP TABLE IF EXISTS `services`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `services` (
  `id` varchar(50) NOT NULL,
  `name` varchar(100) NOT NULL,
  `category` varchar(50) DEFAULT NULL,
  `base_price` decimal(10,2) DEFAULT 0.00,
  `price_display` varchar(50) DEFAULT NULL,
  `per_head` decimal(10,2) DEFAULT NULL,
  `per_head_by_pkg` tinyint(1) DEFAULT 0,
  `max_cap` int(11) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `inclusions` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`inclusions`)),
  `guidelines` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`guidelines`)),
  `is_active` tinyint(1) DEFAULT 1,
  `sort_order` int(11) DEFAULT 0,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `services`
--

LOCK TABLES `services` WRITE;
/*!40000 ALTER TABLE `services` DISABLE KEYS */;
INSERT INTO `services` VALUES ('catering-menu','Catering with Menu','Catering',1000.00,'₱1,000 per head',NULL,1,150,'Full-course catering with a customizable menu for any occasion.','[\"Tables and chairs\",\"Food and beverages\",\"Basic decorations\"]','[]',1,0,'2026-10-29 01:55:42'),('coffee','Coffee Booth Package','Booth',6250.00,'₱125 per cup',NULL,0,80,'Mobile coffee bar serving premium beverages.','[\"Booth setup\",\"Assigned staff\"]','[]',1,0,'2026-10-29 02:00:17'),('dinein','Dine-In Menu','Restaurant',600.00,'600',600.00,0,NULL,'Delicious meals served fresh for all dine-in guests.','[\"Al-Fresco Area\"]','[]',1,0,'2026-10-29 01:55:42'),('equipment','Equipment Rental','Equipment',300.00,'300',NULL,0,NULL,'Rental of high-quality event equipment.','[\"Delivery within service area\",\"Basic setup\"]','[]',1,0,'2026-10-29 02:00:17'),('intimate','Intimate Celebration Package','Celebration',4000.00,'₱4,000',NULL,0,50,'Complete intimate celebration packages.','[\"Thematic setup\",\"Basic lighting\"]','[]',1,0,'2026-10-29 02:00:17'),('noncoffee','Non-Coffee Booth Package','Booth',4250.00,'₱85 per cup',NULL,0,80,'A refreshment booth serving fruit teas and milk drinks.','[\"Booth setup\",\"Assigned staff\"]','[]',1,0,'2026-10-29 02:00:17'),('pizza','Pizza Booth','Booth',7500.00,'₱7,500',NULL,0,60,'Freshly baked artisanal pizzas prepared live.','[\"Live station setup\",\"Assigned staff\"]','[]',1,0,'2026-10-29 02:00:17'),('venue','Venue Rentals','Venue',15000.00,'₱15,000',NULL,0,120,'A comfortable and welcoming space for celebrations.','[\"Air-conditioned venue\"]','[]',1,0,'2026-10-29 01:55:42');
/*!40000 ALTER TABLE `services` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Dumping events for database 'pdrsystem'
--

--
-- Dumping routines for database 'pdrsystem'
--
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-11-04 17:41:57
