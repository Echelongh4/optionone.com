-- NovaPOS database backup
-- Generated at 2026-04-09T08:01:07+00:00
SET FOREIGN_KEY_CHECKS=0;
USE `pos_system`;

-- Table structure for `audit_logs`
DROP TABLE IF EXISTS `audit_logs`;
CREATE TABLE `audit_logs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned DEFAULT NULL,
  `action` varchar(100) NOT NULL,
  `entity_type` varchar(100) NOT NULL,
  `entity_id` bigint(20) unsigned DEFAULT NULL,
  `description` text NOT NULL,
  `ip_address` varchar(64) DEFAULT NULL,
  `user_agent` varchar(255) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_audit_logs_user` (`user_id`),
  KEY `idx_audit_logs_entity` (`entity_type`,`entity_id`),
  CONSTRAINT `fk_audit_logs_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=38 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
INSERT INTO `audit_logs` (`id`, `user_id`, `action`, `entity_type`, `entity_id`, `description`, `ip_address`, `user_agent`, `created_at`) VALUES ('1', '2', 'login', 'user', '2', 'Admin logged into the POS system.', '127.0.0.1', 'Seeded browser', '2026-03-18 08:00:00');
INSERT INTO `audit_logs` (`id`, `user_id`, `action`, `entity_type`, `entity_id`, `description`, `ip_address`, `user_agent`, `created_at`) VALUES ('2', '4', 'checkout', 'sale', '3', 'Completed a POS sale.', '127.0.0.1', 'Seeded browser', '2026-03-18 08:10:00');
INSERT INTO `audit_logs` (`id`, `user_id`, `action`, `entity_type`, `entity_id`, `description`, `ip_address`, `user_agent`, `created_at`) VALUES ('3', '2', 'create', 'product', '5', 'Created product USB-C Cable.', '127.0.0.1', 'Seeded browser', '2026-03-05 08:05:00');
INSERT INTO `audit_logs` (`id`, `user_id`, `action`, `entity_type`, `entity_id`, `description`, `ip_address`, `user_agent`, `created_at`) VALUES ('4', '1', 'login', 'user', '1', 'User logged into the POS system.', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-18 13:37:34');
INSERT INTO `audit_logs` (`id`, `user_id`, `action`, `entity_type`, `entity_id`, `description`, `ip_address`, `user_agent`, `created_at`) VALUES ('5', '1', 'login', 'user', '1', 'User logged into the POS system.', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-18 17:03:01');
INSERT INTO `audit_logs` (`id`, `user_id`, `action`, `entity_type`, `entity_id`, `description`, `ip_address`, `user_agent`, `created_at`) VALUES ('6', '1', 'update', 'settings', NULL, 'Updated business and receipt settings.', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-18 17:22:35');
INSERT INTO `audit_logs` (`id`, `user_id`, `action`, `entity_type`, `entity_id`, `description`, `ip_address`, `user_agent`, `created_at`) VALUES ('7', '1', 'checkout', 'sale', '4', 'Completed a POS sale.', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-18 17:24:40');
INSERT INTO `audit_logs` (`id`, `user_id`, `action`, `entity_type`, `entity_id`, `description`, `ip_address`, `user_agent`, `created_at`) VALUES ('8', '1', 'logout', 'user', '1', 'User logged out of the POS system.', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-18 18:12:23');
INSERT INTO `audit_logs` (`id`, `user_id`, `action`, `entity_type`, `entity_id`, `description`, `ip_address`, `user_agent`, `created_at`) VALUES ('9', '1', 'login', 'user', '1', 'User logged into the POS system.', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-19 00:32:13');
INSERT INTO `audit_logs` (`id`, `user_id`, `action`, `entity_type`, `entity_id`, `description`, `ip_address`, `user_agent`, `created_at`) VALUES ('10', '1', 'create', 'user', '6', 'Created user Baafi Samuel.', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-19 00:34:40');
INSERT INTO `audit_logs` (`id`, `user_id`, `action`, `entity_type`, `entity_id`, `description`, `ip_address`, `user_agent`, `created_at`) VALUES ('11', '1', 'logout', 'user', '1', 'User logged out of the POS system.', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-19 00:34:49');
INSERT INTO `audit_logs` (`id`, `user_id`, `action`, `entity_type`, `entity_id`, `description`, `ip_address`, `user_agent`, `created_at`) VALUES ('12', '6', 'login', 'user', '6', 'User logged into the POS system.', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-19 00:35:05');
INSERT INTO `audit_logs` (`id`, `user_id`, `action`, `entity_type`, `entity_id`, `description`, `ip_address`, `user_agent`, `created_at`) VALUES ('13', '6', 'logout', 'user', '6', 'User logged out of the POS system.', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-19 00:35:41');
INSERT INTO `audit_logs` (`id`, `user_id`, `action`, `entity_type`, `entity_id`, `description`, `ip_address`, `user_agent`, `created_at`) VALUES ('14', '1', 'login', 'user', '1', 'User logged into the POS system.', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-19 00:35:47');
INSERT INTO `audit_logs` (`id`, `user_id`, `action`, `entity_type`, `entity_id`, `description`, `ip_address`, `user_agent`, `created_at`) VALUES ('15', '1', 'create', 'user', '7', 'Created user Akua Samuel.', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-19 00:36:32');
INSERT INTO `audit_logs` (`id`, `user_id`, `action`, `entity_type`, `entity_id`, `description`, `ip_address`, `user_agent`, `created_at`) VALUES ('16', '1', 'logout', 'user', '1', 'User logged out of the POS system.', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-19 00:36:39');
INSERT INTO `audit_logs` (`id`, `user_id`, `action`, `entity_type`, `entity_id`, `description`, `ip_address`, `user_agent`, `created_at`) VALUES ('17', '7', 'login', 'user', '7', 'User logged into the POS system.', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-19 00:36:52');
INSERT INTO `audit_logs` (`id`, `user_id`, `action`, `entity_type`, `entity_id`, `description`, `ip_address`, `user_agent`, `created_at`) VALUES ('18', '7', 'logout', 'user', '7', 'User logged out of the POS system.', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-19 00:49:25');
INSERT INTO `audit_logs` (`id`, `user_id`, `action`, `entity_type`, `entity_id`, `description`, `ip_address`, `user_agent`, `created_at`) VALUES ('19', '1', 'login', 'user', '1', 'User logged into the POS system.', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-19 00:49:45');
INSERT INTO `audit_logs` (`id`, `user_id`, `action`, `entity_type`, `entity_id`, `description`, `ip_address`, `user_agent`, `created_at`) VALUES ('20', '1', 'restore', 'database', NULL, 'Restored the database from backup-20260319-011533.sql. Pre-restore backup: pre-restore-20260320-122051.sql.', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-20 12:20:58');
INSERT INTO `audit_logs` (`id`, `user_id`, `action`, `entity_type`, `entity_id`, `description`, `ip_address`, `user_agent`, `created_at`) VALUES ('21', '1', 'logout', 'user', '1', 'User logged out of the POS system.', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-20 17:36:29');
INSERT INTO `audit_logs` (`id`, `user_id`, `action`, `entity_type`, `entity_id`, `description`, `ip_address`, `user_agent`, `created_at`) VALUES ('22', '1', 'login', 'user', '1', 'User logged into the POS system.', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-21 00:44:21');
INSERT INTO `audit_logs` (`id`, `user_id`, `action`, `entity_type`, `entity_id`, `description`, `ip_address`, `user_agent`, `created_at`) VALUES ('23', '1', 'login', 'user', '1', 'User logged into the POS system.', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-22 08:19:18');
INSERT INTO `audit_logs` (`id`, `user_id`, `action`, `entity_type`, `entity_id`, `description`, `ip_address`, `user_agent`, `created_at`) VALUES ('24', '1', 'logout', 'user', '1', 'User logged out of the POS system.', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-22 11:12:49');
INSERT INTO `audit_logs` (`id`, `user_id`, `action`, `entity_type`, `entity_id`, `description`, `ip_address`, `user_agent`, `created_at`) VALUES ('25', '2', 'login', 'user', '2', 'User logged into the POS system.', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-22 11:34:57');
INSERT INTO `audit_logs` (`id`, `user_id`, `action`, `entity_type`, `entity_id`, `description`, `ip_address`, `user_agent`, `created_at`) VALUES ('26', '2', 'logout', 'user', '2', 'User logged out of the POS system.', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-22 11:36:20');
INSERT INTO `audit_logs` (`id`, `user_id`, `action`, `entity_type`, `entity_id`, `description`, `ip_address`, `user_agent`, `created_at`) VALUES ('27', '3', 'login', 'user', '3', 'User logged into the POS system.', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-22 11:36:49');
INSERT INTO `audit_logs` (`id`, `user_id`, `action`, `entity_type`, `entity_id`, `description`, `ip_address`, `user_agent`, `created_at`) VALUES ('28', '1', 'login', 'user', '1', 'User logged into the POS system.', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-23 09:03:25');
INSERT INTO `audit_logs` (`id`, `user_id`, `action`, `entity_type`, `entity_id`, `description`, `ip_address`, `user_agent`, `created_at`) VALUES ('29', '1', 'logout', 'user', '1', 'User logged out of the POS system.', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-23 09:09:49');
INSERT INTO `audit_logs` (`id`, `user_id`, `action`, `entity_type`, `entity_id`, `description`, `ip_address`, `user_agent`, `created_at`) VALUES ('30', '1', 'login', 'user', '1', 'User logged into the POS system.', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-23 09:11:26');
INSERT INTO `audit_logs` (`id`, `user_id`, `action`, `entity_type`, `entity_id`, `description`, `ip_address`, `user_agent`, `created_at`) VALUES ('31', '1', 'logout', 'user', '1', 'User logged out of the POS system.', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-23 09:19:34');
INSERT INTO `audit_logs` (`id`, `user_id`, `action`, `entity_type`, `entity_id`, `description`, `ip_address`, `user_agent`, `created_at`) VALUES ('32', '3', 'login', 'user', '3', 'User logged into the POS system.', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-23 10:11:17');
INSERT INTO `audit_logs` (`id`, `user_id`, `action`, `entity_type`, `entity_id`, `description`, `ip_address`, `user_agent`, `created_at`) VALUES ('33', '1', 'login', 'user', '1', 'User logged into the POS system.', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-26 14:04:49');
INSERT INTO `audit_logs` (`id`, `user_id`, `action`, `entity_type`, `entity_id`, `description`, `ip_address`, `user_agent`, `created_at`) VALUES ('34', '1', 'login', 'user', '1', 'User logged into the POS system.', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-28 17:03:40');
INSERT INTO `audit_logs` (`id`, `user_id`, `action`, `entity_type`, `entity_id`, `description`, `ip_address`, `user_agent`, `created_at`) VALUES ('35', '1', 'backup', 'database', NULL, 'Created database backup backup-20260328-170413.sql.', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-28 17:04:13');
INSERT INTO `audit_logs` (`id`, `user_id`, `action`, `entity_type`, `entity_id`, `description`, `ip_address`, `user_agent`, `created_at`) VALUES ('36', '1', 'backup', 'database', NULL, 'Created database backup backup-20260328-170428.sql.', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-28 17:04:29');
INSERT INTO `audit_logs` (`id`, `user_id`, `action`, `entity_type`, `entity_id`, `description`, `ip_address`, `user_agent`, `created_at`) VALUES ('37', '1', 'login', 'user', '1', 'User logged into the POS system.', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-09 08:00:24');

-- Table structure for `branches`
DROP TABLE IF EXISTS `branches`;
CREATE TABLE `branches` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(150) NOT NULL,
  `code` varchar(40) NOT NULL,
  `address` varchar(255) DEFAULT NULL,
  `phone` varchar(50) DEFAULT NULL,
  `email` varchar(150) DEFAULT NULL,
  `is_default` tinyint(1) NOT NULL DEFAULT 0,
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `code` (`code`),
  KEY `idx_branches_status` (`status`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
INSERT INTO `branches` (`id`, `name`, `code`, `address`, `phone`, `email`, `is_default`, `status`, `created_at`, `updated_at`, `deleted_at`) VALUES ('1', 'Main Branch', 'MAIN', '12 Market Square, Reykjavik', '+3547001000', 'main@novapos.test', '1', 'active', '2026-03-01 08:00:00', '2026-03-01 08:00:00', NULL);
INSERT INTO `branches` (`id`, `name`, `code`, `address`, `phone`, `email`, `is_default`, `status`, `created_at`, `updated_at`, `deleted_at`) VALUES ('2', 'Harbor Branch', 'HARBOR', '88 Ocean Drive, Reykjavik', '+3547001001', 'harbor@novapos.test', '0', 'active', '2026-03-01 08:00:00', '2026-03-01 08:00:00', NULL);

-- Table structure for `categories`
DROP TABLE IF EXISTS `categories`;
CREATE TABLE `categories` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `parent_id` bigint(20) unsigned DEFAULT NULL,
  `name` varchar(150) NOT NULL,
  `slug` varchar(190) NOT NULL,
  `description` text DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `slug` (`slug`),
  KEY `idx_categories_parent` (`parent_id`),
  CONSTRAINT `fk_categories_parent` FOREIGN KEY (`parent_id`) REFERENCES `categories` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
INSERT INTO `categories` (`id`, `parent_id`, `name`, `slug`, `description`, `created_at`, `updated_at`, `deleted_at`) VALUES ('1', NULL, 'Beverages', 'beverages', 'Drinks and refreshments', '2026-03-01 08:00:00', '2026-03-01 08:00:00', NULL);
INSERT INTO `categories` (`id`, `parent_id`, `name`, `slug`, `description`, `created_at`, `updated_at`, `deleted_at`) VALUES ('2', '1', 'Soft Drinks', 'soft-drinks', 'Carbonated beverages', '2026-03-01 08:00:00', '2026-03-01 08:00:00', NULL);
INSERT INTO `categories` (`id`, `parent_id`, `name`, `slug`, `description`, `created_at`, `updated_at`, `deleted_at`) VALUES ('3', NULL, 'Snacks', 'snacks', 'Packaged snacks', '2026-03-01 08:00:00', '2026-03-01 08:00:00', NULL);
INSERT INTO `categories` (`id`, `parent_id`, `name`, `slug`, `description`, `created_at`, `updated_at`, `deleted_at`) VALUES ('4', NULL, 'Electronics', 'electronics', 'Accessories and devices', '2026-03-01 08:00:00', '2026-03-01 08:00:00', NULL);
INSERT INTO `categories` (`id`, `parent_id`, `name`, `slug`, `description`, `created_at`, `updated_at`, `deleted_at`) VALUES ('5', '4', 'Mobile Accessories', 'mobile-accessories', 'Chargers, cables, and adapters', '2026-03-01 08:00:00', '2026-03-01 08:00:00', NULL);

-- Table structure for `customer_credit_transactions`
DROP TABLE IF EXISTS `customer_credit_transactions`;
CREATE TABLE `customer_credit_transactions` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `customer_id` bigint(20) unsigned NOT NULL,
  `sale_id` bigint(20) unsigned DEFAULT NULL,
  `return_id` bigint(20) unsigned DEFAULT NULL,
  `user_id` bigint(20) unsigned DEFAULT NULL,
  `transaction_type` enum('charge','payment','return','void','adjustment') NOT NULL,
  `amount` decimal(12,2) NOT NULL,
  `balance_after` decimal(12,2) NOT NULL DEFAULT 0.00,
  `notes` varchar(255) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_credit_customer` (`customer_id`),
  KEY `idx_credit_sale` (`sale_id`),
  KEY `idx_credit_return` (`return_id`),
  KEY `idx_credit_user` (`user_id`),
  CONSTRAINT `fk_credit_customer` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_credit_return` FOREIGN KEY (`return_id`) REFERENCES `returns` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_credit_sale` FOREIGN KEY (`sale_id`) REFERENCES `sales` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_credit_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Table structure for `customer_groups`
DROP TABLE IF EXISTS `customer_groups`;
CREATE TABLE `customer_groups` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(120) NOT NULL,
  `discount_type` enum('none','percentage','fixed') NOT NULL DEFAULT 'none',
  `discount_value` decimal(12,2) NOT NULL DEFAULT 0.00,
  `description` varchar(255) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
INSERT INTO `customer_groups` (`id`, `name`, `discount_type`, `discount_value`, `description`, `created_at`, `updated_at`) VALUES ('1', 'Retail', 'none', '0.00', 'Default customer tier', '2026-03-01 08:00:00', '2026-03-01 08:00:00');
INSERT INTO `customer_groups` (`id`, `name`, `discount_type`, `discount_value`, `description`, `created_at`, `updated_at`) VALUES ('2', 'VIP', 'percentage', '5.00', 'Preferred customers', '2026-03-01 08:00:00', '2026-03-01 08:00:00');

-- Table structure for `customers`
DROP TABLE IF EXISTS `customers`;
CREATE TABLE `customers` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `branch_id` bigint(20) unsigned DEFAULT NULL,
  `customer_group_id` bigint(20) unsigned DEFAULT NULL,
  `first_name` varchar(100) NOT NULL,
  `last_name` varchar(100) NOT NULL,
  `email` varchar(150) DEFAULT NULL,
  `phone` varchar(50) DEFAULT NULL,
  `address` varchar(255) DEFAULT NULL,
  `credit_balance` decimal(12,2) NOT NULL DEFAULT 0.00,
  `loyalty_balance` int(11) NOT NULL DEFAULT 0,
  `special_pricing_type` enum('none','percentage','fixed') NOT NULL DEFAULT 'none',
  `special_pricing_value` decimal(12,2) NOT NULL DEFAULT 0.00,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_customers_branch` (`branch_id`),
  KEY `idx_customers_group` (`customer_group_id`),
  CONSTRAINT `fk_customers_branch` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_customers_group` FOREIGN KEY (`customer_group_id`) REFERENCES `customer_groups` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
INSERT INTO `customers` (`id`, `branch_id`, `customer_group_id`, `first_name`, `last_name`, `email`, `phone`, `address`, `credit_balance`, `loyalty_balance`, `special_pricing_type`, `special_pricing_value`, `created_at`, `updated_at`, `deleted_at`) VALUES ('1', '1', '1', 'John', 'Doe', 'john.doe@testmail.com', '+3547005001', '101 Oak Street', '0.00', '3', 'none', '0.00', '2026-03-02 09:00:00', '2026-03-18 17:24:40', NULL);
INSERT INTO `customers` (`id`, `branch_id`, `customer_group_id`, `first_name`, `last_name`, `email`, `phone`, `address`, `credit_balance`, `loyalty_balance`, `special_pricing_type`, `special_pricing_value`, `created_at`, `updated_at`, `deleted_at`) VALUES ('2', '1', '2', 'Sarah', 'Lee', 'sarah.lee@testmail.com', '+3547005002', '22 Pine Street', '0.00', '3', 'percentage', '5.00', '2026-03-03 10:00:00', '2026-03-18 07:30:00', NULL);

-- Table structure for `expense_categories`
DROP TABLE IF EXISTS `expense_categories`;
CREATE TABLE `expense_categories` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(120) NOT NULL,
  `description` varchar(255) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
INSERT INTO `expense_categories` (`id`, `name`, `description`, `created_at`, `updated_at`, `deleted_at`) VALUES ('1', 'Utilities', 'Electricity, water, and services', '2026-03-01 08:00:00', '2026-03-01 08:00:00', NULL);
INSERT INTO `expense_categories` (`id`, `name`, `description`, `created_at`, `updated_at`, `deleted_at`) VALUES ('2', 'Transport', 'Fuel and travel', '2026-03-01 08:00:00', '2026-03-01 08:00:00', NULL);
INSERT INTO `expense_categories` (`id`, `name`, `description`, `created_at`, `updated_at`, `deleted_at`) VALUES ('3', 'Internet', 'Connectivity costs', '2026-03-01 08:00:00', '2026-03-01 08:00:00', NULL);

-- Table structure for `expenses`
DROP TABLE IF EXISTS `expenses`;
CREATE TABLE `expenses` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `branch_id` bigint(20) unsigned NOT NULL,
  `expense_category_id` bigint(20) unsigned NOT NULL,
  `user_id` bigint(20) unsigned NOT NULL,
  `amount` decimal(12,2) NOT NULL,
  `expense_date` date NOT NULL,
  `description` varchar(255) DEFAULT NULL,
  `receipt_path` varchar(255) DEFAULT NULL,
  `status` enum('draft','approved','rejected') NOT NULL DEFAULT 'approved',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_expenses_branch` (`branch_id`),
  KEY `idx_expenses_category` (`expense_category_id`),
  KEY `fk_expenses_user` (`user_id`),
  CONSTRAINT `fk_expenses_branch` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`),
  CONSTRAINT `fk_expenses_category` FOREIGN KEY (`expense_category_id`) REFERENCES `expense_categories` (`id`),
  CONSTRAINT `fk_expenses_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
INSERT INTO `expenses` (`id`, `branch_id`, `expense_category_id`, `user_id`, `amount`, `expense_date`, `description`, `receipt_path`, `status`, `created_at`, `updated_at`, `deleted_at`) VALUES ('1', '1', '1', '2', '85.00', '2026-03-12', 'Electricity bill', NULL, 'approved', '2026-03-12 17:00:00', '2026-03-12 17:00:00', NULL);
INSERT INTO `expenses` (`id`, `branch_id`, `expense_category_id`, `user_id`, `amount`, `expense_date`, `description`, `receipt_path`, `status`, `created_at`, `updated_at`, `deleted_at`) VALUES ('2', '1', '2', '3', '36.00', '2026-03-15', 'Delivery fuel top-up', NULL, 'approved', '2026-03-15 17:00:00', '2026-03-15 17:00:00', NULL);
INSERT INTO `expenses` (`id`, `branch_id`, `expense_category_id`, `user_id`, `amount`, `expense_date`, `description`, `receipt_path`, `status`, `created_at`, `updated_at`, `deleted_at`) VALUES ('3', '1', '3', '2', '24.00', '2026-03-17', 'Internet subscription', NULL, 'approved', '2026-03-17 17:00:00', '2026-03-17 17:00:00', NULL);

-- Table structure for `inventory`
DROP TABLE IF EXISTS `inventory`;
CREATE TABLE `inventory` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `product_id` bigint(20) unsigned NOT NULL,
  `branch_id` bigint(20) unsigned NOT NULL,
  `quantity_on_hand` decimal(12,2) NOT NULL DEFAULT 0.00,
  `quantity_reserved` decimal(12,2) NOT NULL DEFAULT 0.00,
  `average_cost` decimal(12,2) NOT NULL DEFAULT 0.00,
  `valuation_method` enum('FIFO','LIFO') NOT NULL DEFAULT 'FIFO',
  `last_restocked_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_inventory_product_branch` (`product_id`,`branch_id`),
  KEY `idx_inventory_branch` (`branch_id`),
  CONSTRAINT `fk_inventory_branch` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_inventory_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
INSERT INTO `inventory` (`id`, `product_id`, `branch_id`, `quantity_on_hand`, `quantity_reserved`, `average_cost`, `valuation_method`, `last_restocked_at`, `created_at`, `updated_at`) VALUES ('1', '1', '1', '44.00', '0.00', '0.80', 'FIFO', '2026-03-16 10:00:00', '2026-03-05 08:00:00', '2026-03-18 17:24:40');
INSERT INTO `inventory` (`id`, `product_id`, `branch_id`, `quantity_on_hand`, `quantity_reserved`, `average_cost`, `valuation_method`, `last_restocked_at`, `created_at`, `updated_at`) VALUES ('2', '2', '1', '16.00', '0.00', '2.40', 'FIFO', '2026-03-16 10:00:00', '2026-03-05 08:00:00', '2026-03-18 08:00:00');
INSERT INTO `inventory` (`id`, `product_id`, `branch_id`, `quantity_on_hand`, `quantity_reserved`, `average_cost`, `valuation_method`, `last_restocked_at`, `created_at`, `updated_at`) VALUES ('3', '3', '1', '8.00', '0.00', '1.20', 'FIFO', '2026-03-16 10:00:00', '2026-03-05 08:00:00', '2026-03-18 08:00:00');
INSERT INTO `inventory` (`id`, `product_id`, `branch_id`, `quantity_on_hand`, `quantity_reserved`, `average_cost`, `valuation_method`, `last_restocked_at`, `created_at`, `updated_at`) VALUES ('4', '4', '1', '5.00', '0.00', '8.50', 'FIFO', '2026-03-16 10:00:00', '2026-03-05 08:00:00', '2026-03-18 08:00:00');
INSERT INTO `inventory` (`id`, `product_id`, `branch_id`, `quantity_on_hand`, `quantity_reserved`, `average_cost`, `valuation_method`, `last_restocked_at`, `created_at`, `updated_at`) VALUES ('5', '5', '1', '1.00', '0.00', '2.50', 'FIFO', '2026-03-16 10:00:00', '2026-03-05 08:00:00', '2026-03-18 17:24:40');

-- Table structure for `login_attempts`
DROP TABLE IF EXISTS `login_attempts`;
CREATE TABLE `login_attempts` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `email` varchar(150) NOT NULL,
  `ip_address` varchar(64) NOT NULL,
  `attempted_at` datetime NOT NULL,
  `success` tinyint(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `idx_login_attempts_email_ip` (`email`,`ip_address`,`attempted_at`)
) ENGINE=InnoDB AUTO_INCREMENT=19 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
INSERT INTO `login_attempts` (`id`, `email`, `ip_address`, `attempted_at`, `success`) VALUES ('1', 'superadmin@novapos.test', '::1', '2026-03-18 13:37:34', '1');
INSERT INTO `login_attempts` (`id`, `email`, `ip_address`, `attempted_at`, `success`) VALUES ('2', 'superadmin@novapos.test', '::1', '2026-03-18 17:02:59', '1');
INSERT INTO `login_attempts` (`id`, `email`, `ip_address`, `attempted_at`, `success`) VALUES ('3', 'superadmin@novapos.test', '::1', '2026-03-19 00:31:31', '0');
INSERT INTO `login_attempts` (`id`, `email`, `ip_address`, `attempted_at`, `success`) VALUES ('4', 'superadmin@novapos.test', '::1', '2026-03-19 00:32:12', '1');
INSERT INTO `login_attempts` (`id`, `email`, `ip_address`, `attempted_at`, `success`) VALUES ('5', 'baafisamuel888@gmail.com', '::1', '2026-03-19 00:35:05', '1');
INSERT INTO `login_attempts` (`id`, `email`, `ip_address`, `attempted_at`, `success`) VALUES ('6', 'superadmin@novapos.test', '::1', '2026-03-19 00:35:47', '1');
INSERT INTO `login_attempts` (`id`, `email`, `ip_address`, `attempted_at`, `success`) VALUES ('7', 'samuelbaafi800@gmail.com', '::1', '2026-03-19 00:36:52', '1');
INSERT INTO `login_attempts` (`id`, `email`, `ip_address`, `attempted_at`, `success`) VALUES ('8', 'superadmin@novapos.test', '::1', '2026-03-19 00:49:45', '1');
INSERT INTO `login_attempts` (`id`, `email`, `ip_address`, `attempted_at`, `success`) VALUES ('9', 'superadmin@novapos.test', '::1', '2026-03-21 00:44:21', '1');
INSERT INTO `login_attempts` (`id`, `email`, `ip_address`, `attempted_at`, `success`) VALUES ('10', 'superadmin@novapos.test', '::1', '2026-03-22 08:19:18', '1');
INSERT INTO `login_attempts` (`id`, `email`, `ip_address`, `attempted_at`, `success`) VALUES ('11', 'admin@novapos.test', '::1', '2026-03-22 11:34:57', '1');
INSERT INTO `login_attempts` (`id`, `email`, `ip_address`, `attempted_at`, `success`) VALUES ('12', 'manager@novapos.test', '::1', '2026-03-22 11:36:49', '1');
INSERT INTO `login_attempts` (`id`, `email`, `ip_address`, `attempted_at`, `success`) VALUES ('13', 'superadmin@novapos.test', '::1', '2026-03-23 09:03:24', '1');
INSERT INTO `login_attempts` (`id`, `email`, `ip_address`, `attempted_at`, `success`) VALUES ('14', 'superadmin@novapos.test', '::1', '2026-03-23 09:11:26', '1');
INSERT INTO `login_attempts` (`id`, `email`, `ip_address`, `attempted_at`, `success`) VALUES ('15', 'manager@novapos.test', '::1', '2026-03-23 10:11:17', '1');
INSERT INTO `login_attempts` (`id`, `email`, `ip_address`, `attempted_at`, `success`) VALUES ('16', 'superadmin@novapos.test', '::1', '2026-03-26 14:04:48', '1');
INSERT INTO `login_attempts` (`id`, `email`, `ip_address`, `attempted_at`, `success`) VALUES ('17', 'superadmin@novapos.test', '::1', '2026-03-28 17:03:40', '1');
INSERT INTO `login_attempts` (`id`, `email`, `ip_address`, `attempted_at`, `success`) VALUES ('18', 'superadmin@novapos.test', '::1', '2026-04-09 08:00:23', '1');

-- Table structure for `loyalty_points`
DROP TABLE IF EXISTS `loyalty_points`;
CREATE TABLE `loyalty_points` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `customer_id` bigint(20) unsigned NOT NULL,
  `sale_id` bigint(20) unsigned DEFAULT NULL,
  `points` int(11) NOT NULL,
  `transaction_type` enum('earn','redeem','adjustment') NOT NULL,
  `balance_after` int(11) NOT NULL,
  `notes` varchar(255) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_loyalty_customer` (`customer_id`),
  KEY `fk_loyalty_sale` (`sale_id`),
  CONSTRAINT `fk_loyalty_customer` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_loyalty_sale` FOREIGN KEY (`sale_id`) REFERENCES `sales` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
INSERT INTO `loyalty_points` (`id`, `customer_id`, `sale_id`, `points`, `transaction_type`, `balance_after`, `notes`, `created_at`) VALUES ('1', '1', '1', '1', 'earn', '1', 'Auto-earned from POS sale', '2026-03-17 09:01:00');
INSERT INTO `loyalty_points` (`id`, `customer_id`, `sale_id`, `points`, `transaction_type`, `balance_after`, `notes`, `created_at`) VALUES ('2', '2', '2', '3', 'earn', '3', 'Auto-earned from POS sale', '2026-03-17 14:05:00');
INSERT INTO `loyalty_points` (`id`, `customer_id`, `sale_id`, `points`, `transaction_type`, `balance_after`, `notes`, `created_at`) VALUES ('3', '1', '4', '2', 'earn', '3', 'Auto-earned from POS sale', '2026-03-18 17:24:40');

-- Table structure for `notifications`
DROP TABLE IF EXISTS `notifications`;
CREATE TABLE `notifications` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned DEFAULT NULL,
  `branch_id` bigint(20) unsigned DEFAULT NULL,
  `type` varchar(80) NOT NULL,
  `title` varchar(150) NOT NULL,
  `message` text NOT NULL,
  `link_url` varchar(255) DEFAULT NULL,
  `is_read` tinyint(1) NOT NULL DEFAULT 0,
  `send_email` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_notifications_user` (`user_id`),
  KEY `fk_notifications_branch` (`branch_id`),
  CONSTRAINT `fk_notifications_branch` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_notifications_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
INSERT INTO `notifications` (`id`, `user_id`, `branch_id`, `type`, `title`, `message`, `link_url`, `is_read`, `send_email`, `created_at`) VALUES ('1', '2', '1', 'low_stock', 'Low stock alert', 'FastCharge Adapter reached its low stock threshold.', '/products', '1', '0', '2026-03-18 07:45:00');
INSERT INTO `notifications` (`id`, `user_id`, `branch_id`, `type`, `title`, `message`, `link_url`, `is_read`, `send_email`, `created_at`) VALUES ('2', '2', '1', 'low_stock', 'Low stock alert', 'USB-C Cable is below the configured threshold.', '/products', '1', '0', '2026-03-18 07:46:00');

-- Table structure for `password_reset_tokens`
DROP TABLE IF EXISTS `password_reset_tokens`;
CREATE TABLE `password_reset_tokens` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned NOT NULL,
  `email` varchar(150) NOT NULL,
  `token_hash` char(64) NOT NULL,
  `expires_at` datetime NOT NULL,
  `used_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_password_reset_token_hash` (`token_hash`),
  KEY `idx_password_reset_user` (`user_id`),
  KEY `idx_password_reset_email` (`email`),
  KEY `idx_password_reset_expires` (`expires_at`),
  CONSTRAINT `fk_password_reset_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Table structure for `payments`
DROP TABLE IF EXISTS `payments`;
CREATE TABLE `payments` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `sale_id` bigint(20) unsigned NOT NULL,
  `payment_method` enum('cash','card','mobile_money','split','credit') NOT NULL,
  `amount` decimal(12,2) NOT NULL,
  `reference` varchar(150) DEFAULT NULL,
  `notes` varchar(255) DEFAULT NULL,
  `paid_at` datetime NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_payments_sale` (`sale_id`),
  CONSTRAINT `fk_payments_sale` FOREIGN KEY (`sale_id`) REFERENCES `sales` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
INSERT INTO `payments` (`id`, `sale_id`, `payment_method`, `amount`, `reference`, `notes`, `paid_at`, `created_at`) VALUES ('1', '1', 'cash', '12.00', NULL, NULL, '2026-03-17 09:01:00', '2026-03-17 09:01:00');
INSERT INTO `payments` (`id`, `sale_id`, `payment_method`, `amount`, `reference`, `notes`, `paid_at`, `created_at`) VALUES ('2', '2', 'card', '20.00', 'CARD-7788', NULL, '2026-03-17 14:05:00', '2026-03-17 14:05:00');
INSERT INTO `payments` (`id`, `sale_id`, `payment_method`, `amount`, `reference`, `notes`, `paid_at`, `created_at`) VALUES ('3', '2', 'mobile_money', '15.00', 'MM-9123', NULL, '2026-03-17 14:05:00', '2026-03-17 14:05:00');
INSERT INTO `payments` (`id`, `sale_id`, `payment_method`, `amount`, `reference`, `notes`, `paid_at`, `created_at`) VALUES ('4', '3', 'cash', '10.00', NULL, NULL, '2026-03-18 08:10:00', '2026-03-18 08:10:00');
INSERT INTO `payments` (`id`, `sale_id`, `payment_method`, `amount`, `reference`, `notes`, `paid_at`, `created_at`) VALUES ('5', '4', 'cash', '52.00', '', NULL, '2026-03-18 17:24:40', '2026-03-18 17:24:40');

-- Table structure for `permissions`
DROP TABLE IF EXISTS `permissions`;
CREATE TABLE `permissions` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(150) NOT NULL,
  `module` varchar(100) NOT NULL,
  `description` varchar(255) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`)
) ENGINE=InnoDB AUTO_INCREMENT=13 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
INSERT INTO `permissions` (`id`, `name`, `module`, `description`, `created_at`, `updated_at`) VALUES ('1', 'view_dashboard', 'dashboard', 'View the analytics dashboard.', '2026-03-01 08:00:00', '2026-03-01 08:00:00');
INSERT INTO `permissions` (`id`, `name`, `module`, `description`, `created_at`, `updated_at`) VALUES ('2', 'manage_products', 'products', 'Create and update products.', '2026-03-01 08:00:00', '2026-03-01 08:00:00');
INSERT INTO `permissions` (`id`, `name`, `module`, `description`, `created_at`, `updated_at`) VALUES ('3', 'access_pos', 'pos', 'Access the POS terminal.', '2026-03-01 08:00:00', '2026-03-01 08:00:00');
INSERT INTO `permissions` (`id`, `name`, `module`, `description`, `created_at`, `updated_at`) VALUES ('4', 'manage_inventory', 'inventory', 'Manage stock and inventory.', '2026-03-01 08:00:00', '2026-03-01 08:00:00');
INSERT INTO `permissions` (`id`, `name`, `module`, `description`, `created_at`, `updated_at`) VALUES ('5', 'manage_reports', 'reports', 'Access reporting.', '2026-03-01 08:00:00', '2026-03-01 08:00:00');
INSERT INTO `permissions` (`id`, `name`, `module`, `description`, `created_at`, `updated_at`) VALUES ('6', 'manage_users', 'users', 'Manage user accounts.', '2026-03-01 08:00:00', '2026-03-01 08:00:00');
INSERT INTO `permissions` (`id`, `name`, `module`, `description`, `created_at`, `updated_at`) VALUES ('7', 'manage_settings', 'settings', 'Manage business settings.', '2026-03-01 08:00:00', '2026-03-01 08:00:00');
INSERT INTO `permissions` (`id`, `name`, `module`, `description`, `created_at`, `updated_at`) VALUES ('8', 'manage_expenses', 'expenses', 'Log and review expenses.', '2026-03-01 08:00:00', '2026-03-01 08:00:00');
INSERT INTO `permissions` (`id`, `name`, `module`, `description`, `created_at`, `updated_at`) VALUES ('9', 'manage_customers', 'customers', 'Manage customer profiles.', '2026-03-01 08:00:00', '2026-03-01 08:00:00');
INSERT INTO `permissions` (`id`, `name`, `module`, `description`, `created_at`, `updated_at`) VALUES ('10', 'manage_sales', 'sales', 'View and operate on sales.', '2026-03-01 08:00:00', '2026-03-01 08:00:00');
INSERT INTO `permissions` (`id`, `name`, `module`, `description`, `created_at`, `updated_at`) VALUES ('11', 'approve_voids', 'sales', 'Approve transaction voids.', '2026-03-01 08:00:00', '2026-03-01 08:00:00');
INSERT INTO `permissions` (`id`, `name`, `module`, `description`, `created_at`, `updated_at`) VALUES ('12', 'manage_branches', 'settings', 'Manage branches and transfers.', '2026-03-01 08:00:00', '2026-03-01 08:00:00');

-- Table structure for `product_variants`
DROP TABLE IF EXISTS `product_variants`;
CREATE TABLE `product_variants` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `product_id` bigint(20) unsigned NOT NULL,
  `variant_name` varchar(100) NOT NULL,
  `variant_value` varchar(100) NOT NULL,
  `sku` varchar(120) DEFAULT NULL,
  `barcode` varchar(120) DEFAULT NULL,
  `price_adjustment` decimal(12,2) NOT NULL DEFAULT 0.00,
  `stock_quantity` decimal(12,2) NOT NULL DEFAULT 0.00,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_product_variants_product` (`product_id`),
  CONSTRAINT `fk_product_variants_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
INSERT INTO `product_variants` (`id`, `product_id`, `variant_name`, `variant_value`, `sku`, `barcode`, `price_adjustment`, `stock_quantity`, `created_at`, `updated_at`, `deleted_at`) VALUES ('1', '4', 'Color', 'Black', 'SKU-FASTADP-BLK', '260318200001', '0.00', '2.00', '2026-03-05 08:00:00', '2026-03-05 08:00:00', NULL);
INSERT INTO `product_variants` (`id`, `product_id`, `variant_name`, `variant_value`, `sku`, `barcode`, `price_adjustment`, `stock_quantity`, `created_at`, `updated_at`, `deleted_at`) VALUES ('2', '4', 'Color', 'White', 'SKU-FASTADP-WHT', '260318200002', '0.00', '3.00', '2026-03-05 08:00:00', '2026-03-05 08:00:00', NULL);
INSERT INTO `product_variants` (`id`, `product_id`, `variant_name`, `variant_value`, `sku`, `barcode`, `price_adjustment`, `stock_quantity`, `created_at`, `updated_at`, `deleted_at`) VALUES ('3', '5', 'Length', '1m', 'SKU-USBC001-1M', '260318200003', '0.00', '2.00', '2026-03-05 08:00:00', '2026-03-05 08:00:00', NULL);
INSERT INTO `product_variants` (`id`, `product_id`, `variant_name`, `variant_value`, `sku`, `barcode`, `price_adjustment`, `stock_quantity`, `created_at`, `updated_at`, `deleted_at`) VALUES ('4', '5', 'Length', '2m', 'SKU-USBC001-2M', '260318200004', '1.50', '1.00', '2026-03-05 08:00:00', '2026-03-05 08:00:00', NULL);

-- Table structure for `products`
DROP TABLE IF EXISTS `products`;
CREATE TABLE `products` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `branch_id` bigint(20) unsigned DEFAULT NULL,
  `category_id` bigint(20) unsigned DEFAULT NULL,
  `supplier_id` bigint(20) unsigned DEFAULT NULL,
  `tax_id` bigint(20) unsigned DEFAULT NULL,
  `name` varchar(180) NOT NULL,
  `slug` varchar(190) NOT NULL,
  `sku` varchar(120) NOT NULL,
  `barcode` varchar(120) NOT NULL,
  `description` text DEFAULT NULL,
  `image_path` varchar(255) DEFAULT NULL,
  `unit` enum('pcs','kg','litre','box') NOT NULL DEFAULT 'pcs',
  `price` decimal(12,2) NOT NULL DEFAULT 0.00,
  `cost_price` decimal(12,2) NOT NULL DEFAULT 0.00,
  `low_stock_threshold` decimal(12,2) NOT NULL DEFAULT 0.00,
  `track_stock` tinyint(1) NOT NULL DEFAULT 1,
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `inventory_method` enum('FIFO','LIFO') NOT NULL DEFAULT 'FIFO',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `slug` (`slug`),
  UNIQUE KEY `sku` (`sku`),
  UNIQUE KEY `barcode` (`barcode`),
  KEY `idx_products_branch` (`branch_id`),
  KEY `idx_products_category` (`category_id`),
  KEY `idx_products_supplier` (`supplier_id`),
  KEY `idx_products_tax` (`tax_id`),
  CONSTRAINT `fk_products_branch` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_products_category` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_products_supplier` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_products_tax` FOREIGN KEY (`tax_id`) REFERENCES `taxes` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
INSERT INTO `products` (`id`, `branch_id`, `category_id`, `supplier_id`, `tax_id`, `name`, `slug`, `sku`, `barcode`, `description`, `image_path`, `unit`, `price`, `cost_price`, `low_stock_threshold`, `track_stock`, `status`, `inventory_method`, `created_at`, `updated_at`, `deleted_at`) VALUES ('1', '1', '2', '1', '1', 'Cola 500ml', 'cola-500ml-demo', 'SKU-COLA500', '260318100001', 'Chilled carbonated drink', NULL, 'pcs', '1.50', '0.80', '12.00', '1', 'active', 'FIFO', '2026-03-05 08:00:00', '2026-03-05 08:00:00', NULL);
INSERT INTO `products` (`id`, `branch_id`, `category_id`, `supplier_id`, `tax_id`, `name`, `slug`, `sku`, `barcode`, `description`, `image_path`, `unit`, `price`, `cost_price`, `low_stock_threshold`, `track_stock`, `status`, `inventory_method`, `created_at`, `updated_at`, `deleted_at`) VALUES ('2', '1', '1', '1', '1', 'Orange Juice 1L', 'orange-juice-1l-demo', 'SKU-OJ1000', '260318100002', 'Fresh orange juice', NULL, 'pcs', '3.80', '2.40', '6.00', '1', 'active', 'FIFO', '2026-03-05 08:00:00', '2026-03-05 08:00:00', NULL);
INSERT INTO `products` (`id`, `branch_id`, `category_id`, `supplier_id`, `tax_id`, `name`, `slug`, `sku`, `barcode`, `description`, `image_path`, `unit`, `price`, `cost_price`, `low_stock_threshold`, `track_stock`, `status`, `inventory_method`, `created_at`, `updated_at`, `deleted_at`) VALUES ('3', '1', '3', '1', '1', 'Chocolate Bar', 'chocolate-bar-demo', 'SKU-CHOC100', '260318100003', 'Premium milk chocolate snack', NULL, 'pcs', '2.50', '1.20', '10.00', '1', 'active', 'FIFO', '2026-03-05 08:00:00', '2026-03-05 08:00:00', NULL);
INSERT INTO `products` (`id`, `branch_id`, `category_id`, `supplier_id`, `tax_id`, `name`, `slug`, `sku`, `barcode`, `description`, `image_path`, `unit`, `price`, `cost_price`, `low_stock_threshold`, `track_stock`, `status`, `inventory_method`, `created_at`, `updated_at`, `deleted_at`) VALUES ('4', '1', '5', '2', '2', 'FastCharge Adapter', 'fastcharge-adapter-demo', 'SKU-FASTADP', '260318100004', 'USB fast charger adapter', NULL, 'pcs', '15.00', '8.50', '5.00', '1', 'active', 'FIFO', '2026-03-05 08:00:00', '2026-03-05 08:00:00', NULL);
INSERT INTO `products` (`id`, `branch_id`, `category_id`, `supplier_id`, `tax_id`, `name`, `slug`, `sku`, `barcode`, `description`, `image_path`, `unit`, `price`, `cost_price`, `low_stock_threshold`, `track_stock`, `status`, `inventory_method`, `created_at`, `updated_at`, `deleted_at`) VALUES ('5', '1', '5', '2', '2', 'USB-C Cable', 'usb-c-cable-demo', 'SKU-USBC001', '260318100005', 'Durable USB-C cable', NULL, 'pcs', '6.00', '2.50', '5.00', '1', 'active', 'FIFO', '2026-03-05 08:00:00', '2026-03-05 08:00:00', NULL);

-- Table structure for `purchase_order_items`
DROP TABLE IF EXISTS `purchase_order_items`;
CREATE TABLE `purchase_order_items` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `purchase_order_id` bigint(20) unsigned NOT NULL,
  `product_id` bigint(20) unsigned NOT NULL,
  `quantity` decimal(12,2) NOT NULL,
  `received_quantity` decimal(12,2) NOT NULL DEFAULT 0.00,
  `unit_cost` decimal(12,2) NOT NULL,
  `tax_rate` decimal(8,2) NOT NULL DEFAULT 0.00,
  `total` decimal(12,2) NOT NULL,
  `received_total` decimal(12,2) NOT NULL DEFAULT 0.00,
  `last_received_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `fk_po_items_order` (`purchase_order_id`),
  KEY `fk_po_items_product` (`product_id`),
  CONSTRAINT `fk_po_items_order` FOREIGN KEY (`purchase_order_id`) REFERENCES `purchase_orders` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_po_items_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
INSERT INTO `purchase_order_items` (`id`, `purchase_order_id`, `product_id`, `quantity`, `received_quantity`, `unit_cost`, `tax_rate`, `total`, `received_total`, `last_received_at`, `created_at`, `updated_at`) VALUES ('1', '1', '1', '40.00', '0.00', '0.80', '7.50', '34.40', '0.00', NULL, '2026-03-10 09:00:00', '2026-03-10 09:00:00');
INSERT INTO `purchase_order_items` (`id`, `purchase_order_id`, `product_id`, `quantity`, `received_quantity`, `unit_cost`, `tax_rate`, `total`, `received_total`, `last_received_at`, `created_at`, `updated_at`) VALUES ('2', '1', '2', '20.00', '0.00', '2.40', '7.50', '51.60', '0.00', NULL, '2026-03-10 09:00:00', '2026-03-10 09:00:00');
INSERT INTO `purchase_order_items` (`id`, `purchase_order_id`, `product_id`, `quantity`, `received_quantity`, `unit_cost`, `tax_rate`, `total`, `received_total`, `last_received_at`, `created_at`, `updated_at`) VALUES ('3', '1', '3', '30.00', '0.00', '1.20', '7.50', '38.70', '0.00', NULL, '2026-03-10 09:00:00', '2026-03-10 09:00:00');

-- Table structure for `purchase_orders`
DROP TABLE IF EXISTS `purchase_orders`;
CREATE TABLE `purchase_orders` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `branch_id` bigint(20) unsigned NOT NULL,
  `supplier_id` bigint(20) unsigned NOT NULL,
  `created_by` bigint(20) unsigned NOT NULL,
  `po_number` varchar(120) NOT NULL,
  `status` enum('draft','ordered','partial_received','received','cancelled') NOT NULL DEFAULT 'draft',
  `subtotal` decimal(12,2) NOT NULL DEFAULT 0.00,
  `tax_total` decimal(12,2) NOT NULL DEFAULT 0.00,
  `total` decimal(12,2) NOT NULL DEFAULT 0.00,
  `notes` text DEFAULT NULL,
  `ordered_at` datetime DEFAULT NULL,
  `expected_at` datetime DEFAULT NULL,
  `received_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `po_number` (`po_number`),
  KEY `idx_purchase_orders_branch` (`branch_id`),
  KEY `idx_purchase_orders_supplier` (`supplier_id`),
  KEY `fk_purchase_orders_user` (`created_by`),
  CONSTRAINT `fk_purchase_orders_branch` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`),
  CONSTRAINT `fk_purchase_orders_supplier` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`id`),
  CONSTRAINT `fk_purchase_orders_user` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
INSERT INTO `purchase_orders` (`id`, `branch_id`, `supplier_id`, `created_by`, `po_number`, `status`, `subtotal`, `tax_total`, `total`, `notes`, `ordered_at`, `expected_at`, `received_at`, `created_at`, `updated_at`, `deleted_at`) VALUES ('1', '1', '1', '2', 'PO-20260310-001', 'ordered', '120.00', '9.00', '129.00', 'Replenishment for drinks and snacks', '2026-03-10 09:00:00', '2026-03-20 12:00:00', NULL, '2026-03-10 09:00:00', '2026-03-22 08:21:33', NULL);

-- Table structure for `return_items`
DROP TABLE IF EXISTS `return_items`;
CREATE TABLE `return_items` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `return_id` bigint(20) unsigned NOT NULL,
  `sale_item_id` bigint(20) unsigned NOT NULL,
  `product_id` bigint(20) unsigned NOT NULL,
  `quantity` decimal(12,2) NOT NULL,
  `unit_price` decimal(12,2) NOT NULL,
  `tax_total` decimal(12,2) NOT NULL DEFAULT 0.00,
  `line_total` decimal(12,2) NOT NULL,
  `reason` varchar(255) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `fk_return_items_return` (`return_id`),
  KEY `fk_return_items_sale_item` (`sale_item_id`),
  KEY `fk_return_items_product` (`product_id`),
  CONSTRAINT `fk_return_items_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`),
  CONSTRAINT `fk_return_items_return` FOREIGN KEY (`return_id`) REFERENCES `returns` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_return_items_sale_item` FOREIGN KEY (`sale_item_id`) REFERENCES `sale_items` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Table structure for `returns`
DROP TABLE IF EXISTS `returns`;
CREATE TABLE `returns` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `sale_id` bigint(20) unsigned NOT NULL,
  `user_id` bigint(20) unsigned NOT NULL,
  `customer_id` bigint(20) unsigned DEFAULT NULL,
  `return_number` varchar(120) NOT NULL,
  `reason` varchar(255) DEFAULT NULL,
  `status` enum('pending','completed','rejected') NOT NULL DEFAULT 'completed',
  `subtotal` decimal(12,2) NOT NULL DEFAULT 0.00,
  `tax_total` decimal(12,2) NOT NULL DEFAULT 0.00,
  `total_refund` decimal(12,2) NOT NULL DEFAULT 0.00,
  `approved_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `return_number` (`return_number`),
  KEY `idx_returns_sale` (`sale_id`),
  KEY `fk_returns_user` (`user_id`),
  KEY `fk_returns_customer` (`customer_id`),
  KEY `fk_returns_approved_by` (`approved_by`),
  CONSTRAINT `fk_returns_approved_by` FOREIGN KEY (`approved_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_returns_customer` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_returns_sale` FOREIGN KEY (`sale_id`) REFERENCES `sales` (`id`),
  CONSTRAINT `fk_returns_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Table structure for `role_permissions`
DROP TABLE IF EXISTS `role_permissions`;
CREATE TABLE `role_permissions` (
  `role_id` bigint(20) unsigned NOT NULL,
  `permission_id` bigint(20) unsigned NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`role_id`,`permission_id`),
  KEY `fk_role_permissions_permission` (`permission_id`),
  CONSTRAINT `fk_role_permissions_permission` FOREIGN KEY (`permission_id`) REFERENCES `permissions` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_role_permissions_role` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
INSERT INTO `role_permissions` (`role_id`, `permission_id`, `created_at`) VALUES ('1', '1', '2026-03-01 08:00:00');
INSERT INTO `role_permissions` (`role_id`, `permission_id`, `created_at`) VALUES ('1', '2', '2026-03-01 08:00:00');
INSERT INTO `role_permissions` (`role_id`, `permission_id`, `created_at`) VALUES ('1', '3', '2026-03-01 08:00:00');
INSERT INTO `role_permissions` (`role_id`, `permission_id`, `created_at`) VALUES ('1', '4', '2026-03-01 08:00:00');
INSERT INTO `role_permissions` (`role_id`, `permission_id`, `created_at`) VALUES ('1', '5', '2026-03-01 08:00:00');
INSERT INTO `role_permissions` (`role_id`, `permission_id`, `created_at`) VALUES ('1', '6', '2026-03-01 08:00:00');
INSERT INTO `role_permissions` (`role_id`, `permission_id`, `created_at`) VALUES ('1', '7', '2026-03-01 08:00:00');
INSERT INTO `role_permissions` (`role_id`, `permission_id`, `created_at`) VALUES ('1', '8', '2026-03-01 08:00:00');
INSERT INTO `role_permissions` (`role_id`, `permission_id`, `created_at`) VALUES ('1', '9', '2026-03-01 08:00:00');
INSERT INTO `role_permissions` (`role_id`, `permission_id`, `created_at`) VALUES ('1', '10', '2026-03-01 08:00:00');
INSERT INTO `role_permissions` (`role_id`, `permission_id`, `created_at`) VALUES ('1', '11', '2026-03-01 08:00:00');
INSERT INTO `role_permissions` (`role_id`, `permission_id`, `created_at`) VALUES ('1', '12', '2026-03-01 08:00:00');
INSERT INTO `role_permissions` (`role_id`, `permission_id`, `created_at`) VALUES ('2', '1', '2026-03-01 08:00:00');
INSERT INTO `role_permissions` (`role_id`, `permission_id`, `created_at`) VALUES ('2', '2', '2026-03-01 08:00:00');
INSERT INTO `role_permissions` (`role_id`, `permission_id`, `created_at`) VALUES ('2', '3', '2026-03-01 08:00:00');
INSERT INTO `role_permissions` (`role_id`, `permission_id`, `created_at`) VALUES ('2', '4', '2026-03-01 08:00:00');
INSERT INTO `role_permissions` (`role_id`, `permission_id`, `created_at`) VALUES ('2', '5', '2026-03-01 08:00:00');
INSERT INTO `role_permissions` (`role_id`, `permission_id`, `created_at`) VALUES ('2', '6', '2026-03-01 08:00:00');
INSERT INTO `role_permissions` (`role_id`, `permission_id`, `created_at`) VALUES ('2', '7', '2026-03-01 08:00:00');
INSERT INTO `role_permissions` (`role_id`, `permission_id`, `created_at`) VALUES ('2', '8', '2026-03-01 08:00:00');
INSERT INTO `role_permissions` (`role_id`, `permission_id`, `created_at`) VALUES ('2', '9', '2026-03-01 08:00:00');
INSERT INTO `role_permissions` (`role_id`, `permission_id`, `created_at`) VALUES ('2', '10', '2026-03-01 08:00:00');
INSERT INTO `role_permissions` (`role_id`, `permission_id`, `created_at`) VALUES ('2', '11', '2026-03-01 08:00:00');
INSERT INTO `role_permissions` (`role_id`, `permission_id`, `created_at`) VALUES ('2', '12', '2026-03-01 08:00:00');
INSERT INTO `role_permissions` (`role_id`, `permission_id`, `created_at`) VALUES ('3', '1', '2026-03-01 08:00:00');
INSERT INTO `role_permissions` (`role_id`, `permission_id`, `created_at`) VALUES ('3', '2', '2026-03-01 08:00:00');
INSERT INTO `role_permissions` (`role_id`, `permission_id`, `created_at`) VALUES ('3', '3', '2026-03-01 08:00:00');
INSERT INTO `role_permissions` (`role_id`, `permission_id`, `created_at`) VALUES ('3', '4', '2026-03-01 08:00:00');
INSERT INTO `role_permissions` (`role_id`, `permission_id`, `created_at`) VALUES ('3', '5', '2026-03-01 08:00:00');
INSERT INTO `role_permissions` (`role_id`, `permission_id`, `created_at`) VALUES ('3', '8', '2026-03-01 08:00:00');
INSERT INTO `role_permissions` (`role_id`, `permission_id`, `created_at`) VALUES ('3', '9', '2026-03-01 08:00:00');
INSERT INTO `role_permissions` (`role_id`, `permission_id`, `created_at`) VALUES ('3', '10', '2026-03-01 08:00:00');
INSERT INTO `role_permissions` (`role_id`, `permission_id`, `created_at`) VALUES ('3', '11', '2026-03-01 08:00:00');
INSERT INTO `role_permissions` (`role_id`, `permission_id`, `created_at`) VALUES ('4', '1', '2026-03-01 08:00:00');
INSERT INTO `role_permissions` (`role_id`, `permission_id`, `created_at`) VALUES ('4', '3', '2026-03-01 08:00:00');
INSERT INTO `role_permissions` (`role_id`, `permission_id`, `created_at`) VALUES ('4', '9', '2026-03-01 08:00:00');
INSERT INTO `role_permissions` (`role_id`, `permission_id`, `created_at`) VALUES ('4', '10', '2026-03-01 08:00:00');

-- Table structure for `roles`
DROP TABLE IF EXISTS `roles`;
CREATE TABLE `roles` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `description` varchar(255) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
INSERT INTO `roles` (`id`, `name`, `description`, `created_at`, `updated_at`) VALUES ('1', 'Super Admin', 'Platform owner with unrestricted access.', '2026-03-01 08:00:00', '2026-03-01 08:00:00');
INSERT INTO `roles` (`id`, `name`, `description`, `created_at`, `updated_at`) VALUES ('2', 'Admin', 'Operational administrator with wide permissions.', '2026-03-01 08:00:00', '2026-03-01 08:00:00');
INSERT INTO `roles` (`id`, `name`, `description`, `created_at`, `updated_at`) VALUES ('3', 'Manager', 'Branch manager handling reports and oversight.', '2026-03-01 08:00:00', '2026-03-01 08:00:00');
INSERT INTO `roles` (`id`, `name`, `description`, `created_at`, `updated_at`) VALUES ('4', 'Cashier', 'Frontline operator for sales transactions.', '2026-03-01 08:00:00', '2026-03-01 08:00:00');

-- Table structure for `sale_items`
DROP TABLE IF EXISTS `sale_items`;
CREATE TABLE `sale_items` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `sale_id` bigint(20) unsigned NOT NULL,
  `product_id` bigint(20) unsigned NOT NULL,
  `variant_id` bigint(20) unsigned DEFAULT NULL,
  `product_name` varchar(180) NOT NULL,
  `sku` varchar(120) DEFAULT NULL,
  `barcode` varchar(120) DEFAULT NULL,
  `quantity` decimal(12,2) NOT NULL,
  `unit_price` decimal(12,2) NOT NULL,
  `discount_type` enum('fixed','percent') NOT NULL DEFAULT 'fixed',
  `discount_value` decimal(12,2) NOT NULL DEFAULT 0.00,
  `discount_total` decimal(12,2) NOT NULL DEFAULT 0.00,
  `tax_rate` decimal(8,2) NOT NULL DEFAULT 0.00,
  `tax_total` decimal(12,2) NOT NULL DEFAULT 0.00,
  `line_total` decimal(12,2) NOT NULL DEFAULT 0.00,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_sale_items_sale` (`sale_id`),
  KEY `idx_sale_items_product` (`product_id`),
  KEY `fk_sale_items_variant` (`variant_id`),
  CONSTRAINT `fk_sale_items_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`),
  CONSTRAINT `fk_sale_items_sale` FOREIGN KEY (`sale_id`) REFERENCES `sales` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_sale_items_variant` FOREIGN KEY (`variant_id`) REFERENCES `product_variants` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=12 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
INSERT INTO `sale_items` (`id`, `sale_id`, `product_id`, `variant_id`, `product_name`, `sku`, `barcode`, `quantity`, `unit_price`, `discount_type`, `discount_value`, `discount_total`, `tax_rate`, `tax_total`, `line_total`, `created_at`, `updated_at`) VALUES ('1', '1', '1', NULL, 'Cola 500ml', 'SKU-COLA500', '260318100001', '4.00', '1.50', 'fixed', '0.00', '0.00', '7.50', '0.45', '6.45', '2026-03-17 09:01:00', '2026-03-17 09:01:00');
INSERT INTO `sale_items` (`id`, `sale_id`, `product_id`, `variant_id`, `product_name`, `sku`, `barcode`, `quantity`, `unit_price`, `discount_type`, `discount_value`, `discount_total`, `tax_rate`, `tax_total`, `line_total`, `created_at`, `updated_at`) VALUES ('2', '1', '3', NULL, 'Chocolate Bar', 'SKU-CHOC100', '260318100003', '2.00', '2.50', 'fixed', '0.00', '0.00', '7.50', '0.38', '5.38', '2026-03-17 09:01:00', '2026-03-17 09:01:00');
INSERT INTO `sale_items` (`id`, `sale_id`, `product_id`, `variant_id`, `product_name`, `sku`, `barcode`, `quantity`, `unit_price`, `discount_type`, `discount_value`, `discount_total`, `tax_rate`, `tax_total`, `line_total`, `created_at`, `updated_at`) VALUES ('3', '2', '4', NULL, 'FastCharge Adapter', 'SKU-FASTADP', '260318100004', '1.00', '15.00', 'fixed', '0.00', '0.00', '15.00', '2.25', '17.25', '2026-03-17 14:05:00', '2026-03-17 14:05:00');
INSERT INTO `sale_items` (`id`, `sale_id`, `product_id`, `variant_id`, `product_name`, `sku`, `barcode`, `quantity`, `unit_price`, `discount_type`, `discount_value`, `discount_total`, `tax_rate`, `tax_total`, `line_total`, `created_at`, `updated_at`) VALUES ('4', '2', '5', NULL, 'USB-C Cable', 'SKU-USBC001', '260318100005', '2.00', '6.00', 'fixed', '0.00', '0.00', '15.00', '1.80', '13.80', '2026-03-17 14:05:00', '2026-03-17 14:05:00');
INSERT INTO `sale_items` (`id`, `sale_id`, `product_id`, `variant_id`, `product_name`, `sku`, `barcode`, `quantity`, `unit_price`, `discount_type`, `discount_value`, `discount_total`, `tax_rate`, `tax_total`, `line_total`, `created_at`, `updated_at`) VALUES ('5', '3', '2', NULL, 'Orange Juice 1L', 'SKU-OJ1000', '260318100002', '2.00', '3.80', 'fixed', '0.60', '0.60', '7.50', '0.53', '7.53', '2026-03-18 08:10:00', '2026-03-18 08:10:00');
INSERT INTO `sale_items` (`id`, `sale_id`, `product_id`, `variant_id`, `product_name`, `sku`, `barcode`, `quantity`, `unit_price`, `discount_type`, `discount_value`, `discount_total`, `tax_rate`, `tax_total`, `line_total`, `created_at`, `updated_at`) VALUES ('8', '4', '1', NULL, 'Cola 500ml', 'SKU-COLA500', '260318100001', '2.00', '1.50', 'fixed', '0.00', '0.00', '7.50', '0.23', '3.23', '2026-03-18 17:24:40', '2026-03-18 17:24:40');
INSERT INTO `sale_items` (`id`, `sale_id`, `product_id`, `variant_id`, `product_name`, `sku`, `barcode`, `quantity`, `unit_price`, `discount_type`, `discount_value`, `discount_total`, `tax_rate`, `tax_total`, `line_total`, `created_at`, `updated_at`) VALUES ('9', '4', '5', NULL, 'USB-C Cable', 'SKU-USBC001', '260318100005', '1.00', '6.00', 'fixed', '0.00', '0.00', '15.00', '0.90', '6.90', '2026-03-18 17:24:40', '2026-03-18 17:24:40');
INSERT INTO `sale_items` (`id`, `sale_id`, `product_id`, `variant_id`, `product_name`, `sku`, `barcode`, `quantity`, `unit_price`, `discount_type`, `discount_value`, `discount_total`, `tax_rate`, `tax_total`, `line_total`, `created_at`, `updated_at`) VALUES ('10', '4', '1', NULL, 'Cola 500ml', 'SKU-COLA500', '260318100001', '2.00', '1.50', 'fixed', '0.00', '0.00', '7.50', '0.23', '3.23', '2026-03-18 17:24:40', '2026-03-18 17:24:40');
INSERT INTO `sale_items` (`id`, `sale_id`, `product_id`, `variant_id`, `product_name`, `sku`, `barcode`, `quantity`, `unit_price`, `discount_type`, `discount_value`, `discount_total`, `tax_rate`, `tax_total`, `line_total`, `created_at`, `updated_at`) VALUES ('11', '4', '5', NULL, 'USB-C Cable', 'SKU-USBC001', '260318100005', '1.00', '6.00', 'fixed', '0.00', '0.00', '15.00', '0.90', '6.90', '2026-03-18 17:24:40', '2026-03-18 17:24:40');

-- Table structure for `sale_void_requests`
DROP TABLE IF EXISTS `sale_void_requests`;
CREATE TABLE `sale_void_requests` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `sale_id` bigint(20) unsigned NOT NULL,
  `requested_by` bigint(20) unsigned NOT NULL,
  `status` enum('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  `reason` varchar(255) NOT NULL,
  `review_notes` varchar(255) DEFAULT NULL,
  `reviewed_by` bigint(20) unsigned DEFAULT NULL,
  `reviewed_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_void_requests_sale` (`sale_id`),
  KEY `idx_void_requests_status` (`status`),
  KEY `idx_void_requests_requested_by` (`requested_by`),
  KEY `fk_void_requests_reviewed_by` (`reviewed_by`),
  CONSTRAINT `fk_void_requests_requested_by` FOREIGN KEY (`requested_by`) REFERENCES `users` (`id`),
  CONSTRAINT `fk_void_requests_reviewed_by` FOREIGN KEY (`reviewed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_void_requests_sale` FOREIGN KEY (`sale_id`) REFERENCES `sales` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Table structure for `sales`
DROP TABLE IF EXISTS `sales`;
CREATE TABLE `sales` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `branch_id` bigint(20) unsigned NOT NULL,
  `customer_id` bigint(20) unsigned DEFAULT NULL,
  `user_id` bigint(20) unsigned NOT NULL,
  `sale_number` varchar(120) NOT NULL,
  `status` enum('held','completed','voided','refunded','partial_return') NOT NULL DEFAULT 'completed',
  `subtotal` decimal(12,2) NOT NULL DEFAULT 0.00,
  `item_discount_total` decimal(12,2) NOT NULL DEFAULT 0.00,
  `order_discount_total` decimal(12,2) NOT NULL DEFAULT 0.00,
  `loyalty_discount_total` decimal(12,2) NOT NULL DEFAULT 0.00,
  `loyalty_points_redeemed` int(11) NOT NULL DEFAULT 0,
  `tax_total` decimal(12,2) NOT NULL DEFAULT 0.00,
  `grand_total` decimal(12,2) NOT NULL DEFAULT 0.00,
  `amount_paid` decimal(12,2) NOT NULL DEFAULT 0.00,
  `change_due` decimal(12,2) NOT NULL DEFAULT 0.00,
  `notes` text DEFAULT NULL,
  `held_until` datetime DEFAULT NULL,
  `completed_at` datetime DEFAULT NULL,
  `void_reason` varchar(255) DEFAULT NULL,
  `approved_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `sale_number` (`sale_number`),
  KEY `idx_sales_branch` (`branch_id`),
  KEY `idx_sales_customer` (`customer_id`),
  KEY `idx_sales_user` (`user_id`),
  KEY `fk_sales_approved_by` (`approved_by`),
  CONSTRAINT `fk_sales_approved_by` FOREIGN KEY (`approved_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_sales_branch` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`),
  CONSTRAINT `fk_sales_customer` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_sales_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
INSERT INTO `sales` (`id`, `branch_id`, `customer_id`, `user_id`, `sale_number`, `status`, `subtotal`, `item_discount_total`, `order_discount_total`, `loyalty_discount_total`, `loyalty_points_redeemed`, `tax_total`, `grand_total`, `amount_paid`, `change_due`, `notes`, `held_until`, `completed_at`, `void_reason`, `approved_by`, `created_at`, `updated_at`, `deleted_at`) VALUES ('1', '1', '1', '4', 'SAL-202603170901-101', 'completed', '11.00', '0.00', '0.00', '0.00', '0', '0.83', '11.83', '12.00', '0.17', 'Morning beverage sale', NULL, '2026-03-17 09:01:00', NULL, NULL, '2026-03-17 09:01:00', '2026-03-17 09:01:00', NULL);
INSERT INTO `sales` (`id`, `branch_id`, `customer_id`, `user_id`, `sale_number`, `status`, `subtotal`, `item_discount_total`, `order_discount_total`, `loyalty_discount_total`, `loyalty_points_redeemed`, `tax_total`, `grand_total`, `amount_paid`, `change_due`, `notes`, `held_until`, `completed_at`, `void_reason`, `approved_by`, `created_at`, `updated_at`, `deleted_at`) VALUES ('2', '1', '2', '4', 'SAL-202603171405-102', 'completed', '27.00', '0.00', '0.00', '0.00', '0', '4.05', '31.05', '35.00', '3.95', 'Accessory bundle', NULL, '2026-03-17 14:05:00', NULL, NULL, '2026-03-17 14:05:00', '2026-03-17 14:05:00', NULL);
INSERT INTO `sales` (`id`, `branch_id`, `customer_id`, `user_id`, `sale_number`, `status`, `subtotal`, `item_discount_total`, `order_discount_total`, `loyalty_discount_total`, `loyalty_points_redeemed`, `tax_total`, `grand_total`, `amount_paid`, `change_due`, `notes`, `held_until`, `completed_at`, `void_reason`, `approved_by`, `created_at`, `updated_at`, `deleted_at`) VALUES ('3', '1', NULL, '4', 'SAL-202603180810-103', 'completed', '7.60', '0.60', '0.00', '0.00', '0', '0.53', '7.53', '10.00', '2.47', 'Discounted juice sale', NULL, '2026-03-18 08:10:00', NULL, NULL, '2026-03-18 08:10:00', '2026-03-18 08:10:00', NULL);
INSERT INTO `sales` (`id`, `branch_id`, `customer_id`, `user_id`, `sale_number`, `status`, `subtotal`, `item_discount_total`, `order_discount_total`, `loyalty_discount_total`, `loyalty_points_redeemed`, `tax_total`, `grand_total`, `amount_paid`, `change_due`, `notes`, `held_until`, `completed_at`, `void_reason`, `approved_by`, `created_at`, `updated_at`, `deleted_at`) VALUES ('4', '1', '1', '1', 'HLD-202603181030-104', 'completed', '18.00', '0.00', '0.00', '0.00', '0', '2.25', '20.25', '52.00', '31.75', 'Customer is browsing more items', NULL, '2026-03-18 17:24:40', NULL, NULL, '2026-03-18 10:30:00', '2026-03-18 17:24:40', NULL);

-- Table structure for `settings`
DROP TABLE IF EXISTS `settings`;
CREATE TABLE `settings` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `key_name` varchar(150) NOT NULL,
  `value_text` text DEFAULT NULL,
  `type` varchar(50) NOT NULL DEFAULT 'string',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `key_name` (`key_name`)
) ENGINE=InnoDB AUTO_INCREMENT=21 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
INSERT INTO `settings` (`id`, `key_name`, `value_text`, `type`, `created_at`, `updated_at`) VALUES ('1', 'business_name', 'ECHELONGH TECHNOLOGY LTD', 'string', '2026-03-01 08:00:00', '2026-03-18 17:22:35');
INSERT INTO `settings` (`id`, `key_name`, `value_text`, `type`, `created_at`, `updated_at`) VALUES ('2', 'business_address', '12 Market Square, Reykjavik', 'string', '2026-03-01 08:00:00', '2026-03-18 17:22:35');
INSERT INTO `settings` (`id`, `key_name`, `value_text`, `type`, `created_at`, `updated_at`) VALUES ('3', 'business_phone', '+233548719221', 'string', '2026-03-01 08:00:00', '2026-03-18 17:22:35');
INSERT INTO `settings` (`id`, `key_name`, `value_text`, `type`, `created_at`, `updated_at`) VALUES ('4', 'currency', 'GHS', 'string', '2026-03-01 08:00:00', '2026-03-18 17:22:35');
INSERT INTO `settings` (`id`, `key_name`, `value_text`, `type`, `created_at`, `updated_at`) VALUES ('5', 'receipt_header', 'Thank you for shopping with ECHELONGH TECHNOLOGY LTD', 'string', '2026-03-01 08:00:00', '2026-03-18 17:22:35');
INSERT INTO `settings` (`id`, `key_name`, `value_text`, `type`, `created_at`, `updated_at`) VALUES ('6', 'receipt_footer', 'Goods sold are subject to store policy.', 'string', '2026-03-01 08:00:00', '2026-03-18 17:22:35');
INSERT INTO `settings` (`id`, `key_name`, `value_text`, `type`, `created_at`, `updated_at`) VALUES ('7', 'barcode_format', 'CODE128', 'string', '2026-03-01 08:00:00', '2026-03-18 17:22:35');
INSERT INTO `settings` (`id`, `key_name`, `value_text`, `type`, `created_at`, `updated_at`) VALUES ('8', 'tax_default', 'VAT 7.5%', 'string', '2026-03-01 08:00:00', '2026-03-18 17:22:35');
INSERT INTO `settings` (`id`, `key_name`, `value_text`, `type`, `created_at`, `updated_at`) VALUES ('9', 'multi_branch_enabled', 'true', 'boolean', '2026-03-01 08:00:00', '2026-03-18 17:22:35');
INSERT INTO `settings` (`id`, `key_name`, `value_text`, `type`, `created_at`, `updated_at`) VALUES ('13', 'business_email', '', 'string', '2026-03-18 17:22:35', '2026-03-18 17:22:35');
INSERT INTO `settings` (`id`, `key_name`, `value_text`, `type`, `created_at`, `updated_at`) VALUES ('20', 'business_logo_path', '', 'string', '2026-03-18 17:22:35', '2026-03-18 17:22:35');

-- Table structure for `stock_movements`
DROP TABLE IF EXISTS `stock_movements`;
CREATE TABLE `stock_movements` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `product_id` bigint(20) unsigned NOT NULL,
  `branch_id` bigint(20) unsigned NOT NULL,
  `user_id` bigint(20) unsigned DEFAULT NULL,
  `movement_type` enum('purchase','sale','return','adjustment','transfer_in','transfer_out','void','opening') NOT NULL,
  `reason` varchar(255) DEFAULT NULL,
  `reference_type` varchar(80) DEFAULT NULL,
  `reference_id` bigint(20) unsigned DEFAULT NULL,
  `quantity_change` decimal(12,2) NOT NULL,
  `balance_after` decimal(12,2) NOT NULL,
  `unit_cost` decimal(12,2) NOT NULL DEFAULT 0.00,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_stock_movements_product` (`product_id`),
  KEY `idx_stock_movements_branch` (`branch_id`),
  KEY `idx_stock_movements_user` (`user_id`),
  CONSTRAINT `fk_stock_movements_branch` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_stock_movements_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_stock_movements_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
INSERT INTO `stock_movements` (`id`, `product_id`, `branch_id`, `user_id`, `movement_type`, `reason`, `reference_type`, `reference_id`, `quantity_change`, `balance_after`, `unit_cost`, `created_at`) VALUES ('1', '1', '1', NULL, 'opening', 'Initial stock', 'product', '1', '48.00', '48.00', '0.80', '2026-03-05 08:00:00');
INSERT INTO `stock_movements` (`id`, `product_id`, `branch_id`, `user_id`, `movement_type`, `reason`, `reference_type`, `reference_id`, `quantity_change`, `balance_after`, `unit_cost`, `created_at`) VALUES ('2', '2', '1', NULL, 'opening', 'Initial stock', 'product', '2', '16.00', '16.00', '2.40', '2026-03-05 08:00:00');
INSERT INTO `stock_movements` (`id`, `product_id`, `branch_id`, `user_id`, `movement_type`, `reason`, `reference_type`, `reference_id`, `quantity_change`, `balance_after`, `unit_cost`, `created_at`) VALUES ('3', '3', '1', NULL, 'opening', 'Initial stock', 'product', '3', '8.00', '8.00', '1.20', '2026-03-05 08:00:00');
INSERT INTO `stock_movements` (`id`, `product_id`, `branch_id`, `user_id`, `movement_type`, `reason`, `reference_type`, `reference_id`, `quantity_change`, `balance_after`, `unit_cost`, `created_at`) VALUES ('4', '4', '1', NULL, 'opening', 'Initial stock', 'product', '4', '5.00', '5.00', '8.50', '2026-03-05 08:00:00');
INSERT INTO `stock_movements` (`id`, `product_id`, `branch_id`, `user_id`, `movement_type`, `reason`, `reference_type`, `reference_id`, `quantity_change`, `balance_after`, `unit_cost`, `created_at`) VALUES ('5', '5', '1', NULL, 'opening', 'Initial stock', 'product', '5', '3.00', '3.00', '2.50', '2026-03-05 08:00:00');
INSERT INTO `stock_movements` (`id`, `product_id`, `branch_id`, `user_id`, `movement_type`, `reason`, `reference_type`, `reference_id`, `quantity_change`, `balance_after`, `unit_cost`, `created_at`) VALUES ('6', '1', '1', '1', 'sale', 'POS sale completed', 'sale', '4', '-2.00', '46.00', '0.80', '2026-03-18 17:24:40');
INSERT INTO `stock_movements` (`id`, `product_id`, `branch_id`, `user_id`, `movement_type`, `reason`, `reference_type`, `reference_id`, `quantity_change`, `balance_after`, `unit_cost`, `created_at`) VALUES ('7', '5', '1', '1', 'sale', 'POS sale completed', 'sale', '4', '-1.00', '2.00', '2.50', '2026-03-18 17:24:40');
INSERT INTO `stock_movements` (`id`, `product_id`, `branch_id`, `user_id`, `movement_type`, `reason`, `reference_type`, `reference_id`, `quantity_change`, `balance_after`, `unit_cost`, `created_at`) VALUES ('8', '1', '1', '1', 'sale', 'POS sale completed', 'sale', '4', '-2.00', '44.00', '0.80', '2026-03-18 17:24:40');
INSERT INTO `stock_movements` (`id`, `product_id`, `branch_id`, `user_id`, `movement_type`, `reason`, `reference_type`, `reference_id`, `quantity_change`, `balance_after`, `unit_cost`, `created_at`) VALUES ('9', '5', '1', '1', 'sale', 'POS sale completed', 'sale', '4', '-1.00', '1.00', '2.50', '2026-03-18 17:24:40');

-- Table structure for `stock_transfer_items`
DROP TABLE IF EXISTS `stock_transfer_items`;
CREATE TABLE `stock_transfer_items` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `stock_transfer_id` bigint(20) unsigned NOT NULL,
  `product_id` bigint(20) unsigned NOT NULL,
  `quantity` decimal(12,2) NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `fk_stock_transfer_items_transfer` (`stock_transfer_id`),
  KEY `fk_stock_transfer_items_product` (`product_id`),
  CONSTRAINT `fk_stock_transfer_items_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`),
  CONSTRAINT `fk_stock_transfer_items_transfer` FOREIGN KEY (`stock_transfer_id`) REFERENCES `stock_transfers` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
INSERT INTO `stock_transfer_items` (`id`, `stock_transfer_id`, `product_id`, `quantity`, `created_at`) VALUES ('1', '1', '5', '2.00', '2026-03-18 12:00:00');

-- Table structure for `stock_transfers`
DROP TABLE IF EXISTS `stock_transfers`;
CREATE TABLE `stock_transfers` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `source_branch_id` bigint(20) unsigned NOT NULL,
  `destination_branch_id` bigint(20) unsigned NOT NULL,
  `created_by` bigint(20) unsigned NOT NULL,
  `reference_number` varchar(120) NOT NULL,
  `status` enum('draft','in_transit','completed','cancelled') NOT NULL DEFAULT 'draft',
  `notes` varchar(255) DEFAULT NULL,
  `transfer_date` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `reference_number` (`reference_number`),
  KEY `fk_stock_transfers_source` (`source_branch_id`),
  KEY `fk_stock_transfers_destination` (`destination_branch_id`),
  KEY `fk_stock_transfers_user` (`created_by`),
  CONSTRAINT `fk_stock_transfers_destination` FOREIGN KEY (`destination_branch_id`) REFERENCES `branches` (`id`),
  CONSTRAINT `fk_stock_transfers_source` FOREIGN KEY (`source_branch_id`) REFERENCES `branches` (`id`),
  CONSTRAINT `fk_stock_transfers_user` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
INSERT INTO `stock_transfers` (`id`, `source_branch_id`, `destination_branch_id`, `created_by`, `reference_number`, `status`, `notes`, `transfer_date`, `created_at`, `updated_at`) VALUES ('1', '1', '2', '2', 'TRF-20260318-001', 'draft', 'Demo transfer placeholder for multi-branch scaffolding.', NULL, '2026-03-18 12:00:00', '2026-03-18 12:00:00');

-- Table structure for `suppliers`
DROP TABLE IF EXISTS `suppliers`;
CREATE TABLE `suppliers` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `branch_id` bigint(20) unsigned DEFAULT NULL,
  `name` varchar(150) NOT NULL,
  `contact_person` varchar(150) DEFAULT NULL,
  `email` varchar(150) DEFAULT NULL,
  `phone` varchar(50) DEFAULT NULL,
  `address` varchar(255) DEFAULT NULL,
  `tax_number` varchar(100) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_suppliers_branch` (`branch_id`),
  CONSTRAINT `fk_suppliers_branch` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
INSERT INTO `suppliers` (`id`, `branch_id`, `name`, `contact_person`, `email`, `phone`, `address`, `tax_number`, `created_at`, `updated_at`, `deleted_at`) VALUES ('1', '1', 'FreshFlow Distributors', 'Amina Cole', 'orders@freshflow.test', '+3547002000', '45 Harbor Street', 'SUP-111', '2026-03-01 08:00:00', '2026-03-01 08:00:00', NULL);
INSERT INTO `suppliers` (`id`, `branch_id`, `name`, `contact_person`, `email`, `phone`, `address`, `tax_number`, `created_at`, `updated_at`, `deleted_at`) VALUES ('2', '1', 'RetailHub Wholesale', 'Jon Eriksen', 'sales@retailhub.test', '+3547003000', '18 Tech Avenue', 'SUP-222', '2026-03-01 08:00:00', '2026-03-01 08:00:00', NULL);

-- Table structure for `taxes`
DROP TABLE IF EXISTS `taxes`;
CREATE TABLE `taxes` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `rate` decimal(8,2) NOT NULL DEFAULT 0.00,
  `inclusive` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
INSERT INTO `taxes` (`id`, `name`, `rate`, `inclusive`, `created_at`, `updated_at`) VALUES ('1', 'VAT 7.5%', '7.50', '0', '2026-03-01 08:00:00', '2026-03-01 08:00:00');
INSERT INTO `taxes` (`id`, `name`, `rate`, `inclusive`, `created_at`, `updated_at`) VALUES ('2', 'GST 15%', '15.00', '0', '2026-03-01 08:00:00', '2026-03-01 08:00:00');
INSERT INTO `taxes` (`id`, `name`, `rate`, `inclusive`, `created_at`, `updated_at`) VALUES ('3', 'Zero Rated', '0.00', '0', '2026-03-01 08:00:00', '2026-03-01 08:00:00');

-- Table structure for `users`
DROP TABLE IF EXISTS `users`;
CREATE TABLE `users` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `branch_id` bigint(20) unsigned DEFAULT NULL,
  `role_id` bigint(20) unsigned NOT NULL,
  `first_name` varchar(100) NOT NULL,
  `last_name` varchar(100) NOT NULL,
  `email` varchar(150) NOT NULL,
  `phone` varchar(50) DEFAULT NULL,
  `password` varchar(255) NOT NULL,
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `remember_token` varchar(64) DEFAULT NULL,
  `remember_expires_at` datetime DEFAULT NULL,
  `last_login_at` datetime DEFAULT NULL,
  `last_activity_at` datetime DEFAULT NULL,
  `failed_login_attempts` int(11) NOT NULL DEFAULT 0,
  `locked_until` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`),
  KEY `idx_users_role` (`role_id`),
  KEY `idx_users_branch` (`branch_id`),
  CONSTRAINT `fk_users_branch` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_users_role` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
INSERT INTO `users` (`id`, `branch_id`, `role_id`, `first_name`, `last_name`, `email`, `phone`, `password`, `status`, `remember_token`, `remember_expires_at`, `last_login_at`, `last_activity_at`, `failed_login_attempts`, `locked_until`, `created_at`, `updated_at`, `deleted_at`) VALUES ('1', '1', '1', 'Nova', 'Owner', 'superadmin@novapos.test', '+3547004001', '$2y$10$apDcTKnQx8WNS6LBzPDSuucMxeAjijPrl7bhOMyJMm.ZX9aNDJuhi', 'active', NULL, NULL, '2026-04-09 08:00:23', '2026-04-09 08:00:23', '0', NULL, '2026-03-01 08:00:00', '2026-04-09 08:00:23', NULL);
INSERT INTO `users` (`id`, `branch_id`, `role_id`, `first_name`, `last_name`, `email`, `phone`, `password`, `status`, `remember_token`, `remember_expires_at`, `last_login_at`, `last_activity_at`, `failed_login_attempts`, `locked_until`, `created_at`, `updated_at`, `deleted_at`) VALUES ('2', '1', '2', 'Leah', 'Admin', 'admin@novapos.test', '+3547004002', '$2y$10$apDcTKnQx8WNS6LBzPDSuucMxeAjijPrl7bhOMyJMm.ZX9aNDJuhi', 'active', NULL, NULL, '2026-03-22 11:34:57', '2026-03-22 11:34:57', '0', NULL, '2026-03-01 08:00:00', '2026-03-22 11:34:57', NULL);
INSERT INTO `users` (`id`, `branch_id`, `role_id`, `first_name`, `last_name`, `email`, `phone`, `password`, `status`, `remember_token`, `remember_expires_at`, `last_login_at`, `last_activity_at`, `failed_login_attempts`, `locked_until`, `created_at`, `updated_at`, `deleted_at`) VALUES ('3', '1', '3', 'Mika', 'Manager', 'manager@novapos.test', '+3547004003', '$2y$10$apDcTKnQx8WNS6LBzPDSuucMxeAjijPrl7bhOMyJMm.ZX9aNDJuhi', 'active', NULL, NULL, '2026-03-23 10:11:17', '2026-03-23 10:11:17', '0', NULL, '2026-03-01 08:00:00', '2026-03-23 10:11:17', NULL);
INSERT INTO `users` (`id`, `branch_id`, `role_id`, `first_name`, `last_name`, `email`, `phone`, `password`, `status`, `remember_token`, `remember_expires_at`, `last_login_at`, `last_activity_at`, `failed_login_attempts`, `locked_until`, `created_at`, `updated_at`, `deleted_at`) VALUES ('4', '1', '4', 'Kai', 'Cashier', 'cashier@novapos.test', '+3547004004', '$2y$10$apDcTKnQx8WNS6LBzPDSuucMxeAjijPrl7bhOMyJMm.ZX9aNDJuhi', 'active', NULL, NULL, '2026-03-18 08:15:00', '2026-03-18 08:40:00', '0', NULL, '2026-03-01 08:00:00', '2026-03-18 08:40:00', NULL);
INSERT INTO `users` (`id`, `branch_id`, `role_id`, `first_name`, `last_name`, `email`, `phone`, `password`, `status`, `remember_token`, `remember_expires_at`, `last_login_at`, `last_activity_at`, `failed_login_attempts`, `locked_until`, `created_at`, `updated_at`, `deleted_at`) VALUES ('5', '2', '3', 'Nora', 'Harbor Manager', 'harbor.manager@novapos.test', '+3547004005', '$2y$10$apDcTKnQx8WNS6LBzPDSuucMxeAjijPrl7bhOMyJMm.ZX9aNDJuhi', 'active', NULL, NULL, '2026-03-18 08:18:00', '2026-03-18 08:42:00', '0', NULL, '2026-03-01 08:00:00', '2026-03-18 08:42:00', NULL);
INSERT INTO `users` (`id`, `branch_id`, `role_id`, `first_name`, `last_name`, `email`, `phone`, `password`, `status`, `remember_token`, `remember_expires_at`, `last_login_at`, `last_activity_at`, `failed_login_attempts`, `locked_until`, `created_at`, `updated_at`, `deleted_at`) VALUES ('6', '1', '4', 'Baafi', 'Samuel', 'baafisamuel888@gmail.com', '0548719221', '$2y$10$AjW0nEmcBLIkzx0WzkJyv.vvu7Bf9eXUr7X9voRt7de/TZrjBbgxq', 'active', NULL, NULL, '2026-03-19 00:35:05', '2026-03-19 00:35:05', '0', NULL, '2026-03-19 00:34:40', '2026-03-19 00:35:41', NULL);
INSERT INTO `users` (`id`, `branch_id`, `role_id`, `first_name`, `last_name`, `email`, `phone`, `password`, `status`, `remember_token`, `remember_expires_at`, `last_login_at`, `last_activity_at`, `failed_login_attempts`, `locked_until`, `created_at`, `updated_at`, `deleted_at`) VALUES ('7', '2', '4', 'Akua', 'Samuel', 'samuelbaafi800@gmail.com', '0548719221', '$2y$10$pmDs7EpfrnYnrpuB.8EiZOQGxMMHGFKXBmIyVv.BgLjqwg08C5MQ6', 'active', NULL, NULL, '2026-03-19 00:36:52', '2026-03-19 00:36:52', '0', NULL, '2026-03-19 00:36:32', '2026-03-19 00:36:52', NULL);

SET FOREIGN_KEY_CHECKS=1;
