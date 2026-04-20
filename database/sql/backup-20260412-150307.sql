-- NovaPOS database backup
-- Generated at 2026-04-12T15:03:07+00:00
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
) ENGINE=InnoDB AUTO_INCREMENT=127 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
INSERT INTO `audit_logs` (`id`, `user_id`, `action`, `entity_type`, `entity_id`, `description`, `ip_address`, `user_agent`, `created_at`) VALUES ('45', NULL, 'legacy_import', 'database', NULL, 'Imported legacy posystem data into pos_system. Categories: 8, customers: 5, products: 328, sales: 1830, users: 4, placeholder products: 1.', '127.0.0.1', 'database/migrate_legacy_posystem.php', '2026-04-11 12:24:00');
INSERT INTO `audit_logs` (`id`, `user_id`, `action`, `entity_type`, `entity_id`, `description`, `ip_address`, `user_agent`, `created_at`) VALUES ('46', '1', 'create', 'product', '454', 'Created product TWYFORD 30 X 30 FGP33139G.', '::1', 'Mozilla/5.0 (Linux; Android 6.0; Nexus 5 Build/MRA58N) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Mobile Safari/537.36', '2026-04-11 14:34:06');
INSERT INTO `audit_logs` (`id`, `user_id`, `action`, `entity_type`, `entity_id`, `description`, `ip_address`, `user_agent`, `created_at`) VALUES ('47', '1', 'update', 'product', '454', 'Updated product TWYFORD 30 X 30 FGP33139G.', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-11 14:36:21');
INSERT INTO `audit_logs` (`id`, `user_id`, `action`, `entity_type`, `entity_id`, `description`, `ip_address`, `user_agent`, `created_at`) VALUES ('48', '1', 'update', 'product', '454', 'Updated product TWYFORD 30 X 30 FGP33139G.', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-11 14:41:29');
INSERT INTO `audit_logs` (`id`, `user_id`, `action`, `entity_type`, `entity_id`, `description`, `ip_address`, `user_agent`, `created_at`) VALUES ('49', '1', 'create', 'product', '1', 'Created product TWYFORD 30 X 30 FGP33139G.', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-11 17:03:17');
INSERT INTO `audit_logs` (`id`, `user_id`, `action`, `entity_type`, `entity_id`, `description`, `ip_address`, `user_agent`, `created_at`) VALUES ('50', '1', 'update', 'product_category', '25', 'Updated product category BLOCKS.', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-11 17:20:20');
INSERT INTO `audit_logs` (`id`, `user_id`, `action`, `entity_type`, `entity_id`, `description`, `ip_address`, `user_agent`, `created_at`) VALUES ('51', '1', 'update', 'product_category', '24', 'Updated product category CEMENT.', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-11 17:21:24');
INSERT INTO `audit_logs` (`id`, `user_id`, `action`, `entity_type`, `entity_id`, `description`, `ip_address`, `user_agent`, `created_at`) VALUES ('52', '1', 'adjust', 'inventory', '1', 'Adjusted stock for TWYFORD 30 X 30 FGP33139G by 52 units.', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-11 17:37:41');
INSERT INTO `audit_logs` (`id`, `user_id`, `action`, `entity_type`, `entity_id`, `description`, `ip_address`, `user_agent`, `created_at`) VALUES ('53', '1', 'adjust', 'inventory', '1', 'Adjusted stock for TWYFORD 30 X 30 FGP33139G by -34 units.', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-11 17:40:46');
INSERT INTO `audit_logs` (`id`, `user_id`, `action`, `entity_type`, `entity_id`, `description`, `ip_address`, `user_agent`, `created_at`) VALUES ('54', '1', 'adjust', 'inventory', '1', 'Adjusted stock for TWYFORD 30 X 30 FGP33139G by 23 units. Reason code: Manual restock (stocktake correction).', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-11 18:02:08');
INSERT INTO `audit_logs` (`id`, `user_id`, `action`, `entity_type`, `entity_id`, `description`, `ip_address`, `user_agent`, `created_at`) VALUES ('55', '1', 'adjust', 'inventory', '1', 'Adjusted stock for TWYFORD 30 X 30 FGP33139G by -2 units. Reason code: Manual restock (stocktake correction).', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-11 18:14:30');
INSERT INTO `audit_logs` (`id`, `user_id`, `action`, `entity_type`, `entity_id`, `description`, `ip_address`, `user_agent`, `created_at`) VALUES ('56', '6', 'login', 'user', '6', 'User logged into the POS system.', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-11 23:57:46');
INSERT INTO `audit_logs` (`id`, `user_id`, `action`, `entity_type`, `entity_id`, `description`, `ip_address`, `user_agent`, `created_at`) VALUES ('57', '6', 'logout', 'user', '6', 'User logged out of the POS system.', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-11 23:58:30');
INSERT INTO `audit_logs` (`id`, `user_id`, `action`, `entity_type`, `entity_id`, `description`, `ip_address`, `user_agent`, `created_at`) VALUES ('58', '7', 'login', 'user', '7', 'User logged into the POS system.', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-11 23:59:20');
INSERT INTO `audit_logs` (`id`, `user_id`, `action`, `entity_type`, `entity_id`, `description`, `ip_address`, `user_agent`, `created_at`) VALUES ('59', '7', 'logout', 'user', '7', 'User logged out of the POS system.', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-12 00:01:12');
INSERT INTO `audit_logs` (`id`, `user_id`, `action`, `entity_type`, `entity_id`, `description`, `ip_address`, `user_agent`, `created_at`) VALUES ('60', '1', 'login', 'user', '1', 'User logged into the POS system.', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-12 00:08:07');
INSERT INTO `audit_logs` (`id`, `user_id`, `action`, `entity_type`, `entity_id`, `description`, `ip_address`, `user_agent`, `created_at`) VALUES ('61', '1', 'delete', 'product_category', '25', 'Deleted product category BLOCKS.', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-12 00:29:47');
INSERT INTO `audit_logs` (`id`, `user_id`, `action`, `entity_type`, `entity_id`, `description`, `ip_address`, `user_agent`, `created_at`) VALUES ('62', '1', 'delete', 'product_category', '24', 'Deleted product category CEMENT.', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-12 00:31:31');
INSERT INTO `audit_logs` (`id`, `user_id`, `action`, `entity_type`, `entity_id`, `description`, `ip_address`, `user_agent`, `created_at`) VALUES ('63', '1', 'delete', 'product_category', '21', 'Deleted product category CHEMICALS.', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-12 00:32:12');
INSERT INTO `audit_logs` (`id`, `user_id`, `action`, `entity_type`, `entity_id`, `description`, `ip_address`, `user_agent`, `created_at`) VALUES ('64', '1', 'delete', 'product_category', '22', 'Deleted product category DOORS.', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-12 00:34:28');
INSERT INTO `audit_logs` (`id`, `user_id`, `action`, `entity_type`, `entity_id`, `description`, `ip_address`, `user_agent`, `created_at`) VALUES ('65', '1', 'delete', 'product_category', '20', 'Deleted product category GROUT.', '::1', 'Mozilla/5.0 (Linux; Android 6.0; Nexus 5 Build/MRA58N) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Mobile Safari/537.36', '2026-04-12 00:34:56');
INSERT INTO `audit_logs` (`id`, `user_id`, `action`, `entity_type`, `entity_id`, `description`, `ip_address`, `user_agent`, `created_at`) VALUES ('66', '1', 'update', 'product_category', '23', 'Updated product category HOME CHARM.', '::1', 'Mozilla/5.0 (Linux; Android 6.0; Nexus 5 Build/MRA58N) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Mobile Safari/537.36', '2026-04-12 00:37:30');
INSERT INTO `audit_logs` (`id`, `user_id`, `action`, `entity_type`, `entity_id`, `description`, `ip_address`, `user_agent`, `created_at`) VALUES ('67', '1', 'update', 'product_category', '23', 'Updated product category HOME CHARM.', '::1', 'Mozilla/5.0 (Linux; Android 6.0; Nexus 5 Build/MRA58N) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Mobile Safari/537.36', '2026-04-12 00:37:30');
INSERT INTO `audit_logs` (`id`, `user_id`, `action`, `entity_type`, `entity_id`, `description`, `ip_address`, `user_agent`, `created_at`) VALUES ('68', '1', 'delete', 'product_category', '23', 'Deleted product category HOME CHARM.', '::1', 'Mozilla/5.0 (Linux; Android 6.0; Nexus 5 Build/MRA58N) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Mobile Safari/537.36', '2026-04-12 00:40:42');
INSERT INTO `audit_logs` (`id`, `user_id`, `action`, `entity_type`, `entity_id`, `description`, `ip_address`, `user_agent`, `created_at`) VALUES ('69', '1', 'login', 'user', '1', 'User logged into the POS system.', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-12 08:22:27');
INSERT INTO `audit_logs` (`id`, `user_id`, `action`, `entity_type`, `entity_id`, `description`, `ip_address`, `user_agent`, `created_at`) VALUES ('70', NULL, 'login', 'user', '15', 'User logged into the POS system.', '::1', 'Mozilla/5.0 (Windows NT; Windows NT 10.0; en-US) WindowsPowerShell/5.1.22621.4249', '2026-04-12 11:23:15');
INSERT INTO `audit_logs` (`id`, `user_id`, `action`, `entity_type`, `entity_id`, `description`, `ip_address`, `user_agent`, `created_at`) VALUES ('71', NULL, 'login', 'user', '16', 'User logged into the POS system.', '::1', 'Mozilla/5.0 (Windows NT; Windows NT 10.0; en-US) WindowsPowerShell/5.1.22621.4249', '2026-04-12 11:23:16');
INSERT INTO `audit_logs` (`id`, `user_id`, `action`, `entity_type`, `entity_id`, `description`, `ip_address`, `user_agent`, `created_at`) VALUES ('72', NULL, 'login', 'user', '15', 'User logged into the POS system.', '::1', 'Mozilla/5.0 (Windows NT; Windows NT 10.0; en-US) WindowsPowerShell/5.1.22621.4249', '2026-04-12 11:29:55');
INSERT INTO `audit_logs` (`id`, `user_id`, `action`, `entity_type`, `entity_id`, `description`, `ip_address`, `user_agent`, `created_at`) VALUES ('73', NULL, 'create', 'product', '2', 'Created product SmokeProd_20260412112954.', '::1', 'Mozilla/5.0 (Windows NT; Windows NT 10.0; en-US) WindowsPowerShell/5.1.22621.4249', '2026-04-12 11:29:55');
INSERT INTO `audit_logs` (`id`, `user_id`, `action`, `entity_type`, `entity_id`, `description`, `ip_address`, `user_agent`, `created_at`) VALUES ('74', '1', 'create', 'product_category', '26', 'Created product category GROUT.', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-12 11:30:35');
INSERT INTO `audit_logs` (`id`, `user_id`, `action`, `entity_type`, `entity_id`, `description`, `ip_address`, `user_agent`, `created_at`) VALUES ('75', NULL, 'login', 'user', '15', 'User logged into the POS system.', '::1', 'Mozilla/5.0 (Windows NT; Windows NT 10.0; en-US) WindowsPowerShell/5.1.22621.4249', '2026-04-12 11:30:45');
INSERT INTO `audit_logs` (`id`, `user_id`, `action`, `entity_type`, `entity_id`, `description`, `ip_address`, `user_agent`, `created_at`) VALUES ('76', '1', 'create', 'product_category', '27', 'Created product category CHEMICALS.', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-12 11:31:06');
INSERT INTO `audit_logs` (`id`, `user_id`, `action`, `entity_type`, `entity_id`, `description`, `ip_address`, `user_agent`, `created_at`) VALUES ('77', '1', 'create', 'product_category', '28', 'Created product category DOORS.', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-12 11:31:18');
INSERT INTO `audit_logs` (`id`, `user_id`, `action`, `entity_type`, `entity_id`, `description`, `ip_address`, `user_agent`, `created_at`) VALUES ('78', '1', 'create', 'product_category', '29', 'Created product category HOME CHARM.', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-12 11:31:31');
INSERT INTO `audit_logs` (`id`, `user_id`, `action`, `entity_type`, `entity_id`, `description`, `ip_address`, `user_agent`, `created_at`) VALUES ('79', '1', 'create', 'product_category', '30', 'Created product category CEMENT.', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-12 11:31:44');
INSERT INTO `audit_logs` (`id`, `user_id`, `action`, `entity_type`, `entity_id`, `description`, `ip_address`, `user_agent`, `created_at`) VALUES ('80', '1', 'create', 'product_category', '31', 'Created product category BLOCKS.', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-12 11:32:11');
INSERT INTO `audit_logs` (`id`, `user_id`, `action`, `entity_type`, `entity_id`, `description`, `ip_address`, `user_agent`, `created_at`) VALUES ('81', NULL, 'login', 'user', '15', 'User logged into the POS system.', '::1', 'Mozilla/5.0 (Windows NT; Windows NT 10.0; en-US) WindowsPowerShell/5.1.22621.4249', '2026-04-12 12:01:51');
INSERT INTO `audit_logs` (`id`, `user_id`, `action`, `entity_type`, `entity_id`, `description`, `ip_address`, `user_agent`, `created_at`) VALUES ('82', NULL, 'login', 'user', '15', 'User logged into the POS system.', '::1', 'Mozilla/5.0 (Windows NT; Windows NT 10.0; en-US) WindowsPowerShell/5.1.22621.4249', '2026-04-12 12:05:47');
INSERT INTO `audit_logs` (`id`, `user_id`, `action`, `entity_type`, `entity_id`, `description`, `ip_address`, `user_agent`, `created_at`) VALUES ('83', NULL, 'login', 'user', '15', 'User logged into the POS system.', '::1', 'Mozilla/5.0 (Windows NT; Windows NT 10.0; en-US) WindowsPowerShell/5.1.22621.4249', '2026-04-12 12:05:47');
INSERT INTO `audit_logs` (`id`, `user_id`, `action`, `entity_type`, `entity_id`, `description`, `ip_address`, `user_agent`, `created_at`) VALUES ('84', NULL, 'login', 'user', '15', 'User logged into the POS system.', '::1', 'Mozilla/5.0 (Windows NT; Windows NT 10.0; en-US) WindowsPowerShell/5.1.22621.4249', '2026-04-12 12:10:27');
INSERT INTO `audit_logs` (`id`, `user_id`, `action`, `entity_type`, `entity_id`, `description`, `ip_address`, `user_agent`, `created_at`) VALUES ('85', NULL, 'create', 'product', '3', 'Created product SmokeProd_20260412121027.', '::1', 'Mozilla/5.0 (Windows NT; Windows NT 10.0; en-US) WindowsPowerShell/5.1.22621.4249', '2026-04-12 12:10:28');
INSERT INTO `audit_logs` (`id`, `user_id`, `action`, `entity_type`, `entity_id`, `description`, `ip_address`, `user_agent`, `created_at`) VALUES ('86', NULL, 'update', 'product', '3', 'Updated product SmokeProdEdit_20260412121027.', '::1', 'Mozilla/5.0 (Windows NT; Windows NT 10.0; en-US) WindowsPowerShell/5.1.22621.4249', '2026-04-12 12:10:28');
INSERT INTO `audit_logs` (`id`, `user_id`, `action`, `entity_type`, `entity_id`, `description`, `ip_address`, `user_agent`, `created_at`) VALUES ('87', NULL, 'login', 'user', '16', 'User logged into the POS system.', '::1', 'Mozilla/5.0 (Windows NT; Windows NT 10.0; en-US) WindowsPowerShell/5.1.22621.4249', '2026-04-12 12:10:28');
INSERT INTO `audit_logs` (`id`, `user_id`, `action`, `entity_type`, `entity_id`, `description`, `ip_address`, `user_agent`, `created_at`) VALUES ('88', NULL, 'adjust', 'inventory', '3', 'Adjusted stock for SmokeProdEdit_20260412121027 by 1 units. Reason code: Manual restock (stocktake correction).', '::1', 'Mozilla/5.0 (Windows NT; Windows NT 10.0; en-US) WindowsPowerShell/5.1.22621.4249', '2026-04-12 12:10:29');
INSERT INTO `audit_logs` (`id`, `user_id`, `action`, `entity_type`, `entity_id`, `description`, `ip_address`, `user_agent`, `created_at`) VALUES ('89', NULL, 'adjust', 'inventory', '3', 'Adjusted stock for SmokeProdEdit_20260412121027 by 1001 units. Reason code: Received from supplier - PO.', '::1', 'Mozilla/5.0 (Windows NT; Windows NT 10.0; en-US) WindowsPowerShell/5.1.22621.4249', '2026-04-12 12:10:29');
INSERT INTO `audit_logs` (`id`, `user_id`, `action`, `entity_type`, `entity_id`, `description`, `ip_address`, `user_agent`, `created_at`) VALUES ('90', NULL, 'delete', 'product', '3', 'Soft-deleted product SmokeProdEdit_20260412121027.', '::1', 'Mozilla/5.0 (Windows NT; Windows NT 10.0; en-US) WindowsPowerShell/5.1.22621.4249', '2026-04-12 12:10:29');
INSERT INTO `audit_logs` (`id`, `user_id`, `action`, `entity_type`, `entity_id`, `description`, `ip_address`, `user_agent`, `created_at`) VALUES ('91', NULL, 'create', 'product', '4', 'Created product SmokeBulkA_20260412121027.', '::1', 'Mozilla/5.0 (Windows NT; Windows NT 10.0; en-US) WindowsPowerShell/5.1.22621.4249', '2026-04-12 12:10:29');
INSERT INTO `audit_logs` (`id`, `user_id`, `action`, `entity_type`, `entity_id`, `description`, `ip_address`, `user_agent`, `created_at`) VALUES ('92', NULL, 'create', 'product', '5', 'Created product SmokeBulkB_20260412121027.', '::1', 'Mozilla/5.0 (Windows NT; Windows NT 10.0; en-US) WindowsPowerShell/5.1.22621.4249', '2026-04-12 12:10:29');
INSERT INTO `audit_logs` (`id`, `user_id`, `action`, `entity_type`, `entity_id`, `description`, `ip_address`, `user_agent`, `created_at`) VALUES ('93', NULL, 'delete', 'product', '4', 'Bulk archived product SmokeBulkA_20260412121027.', '', '', '2026-04-12 12:10:30');
INSERT INTO `audit_logs` (`id`, `user_id`, `action`, `entity_type`, `entity_id`, `description`, `ip_address`, `user_agent`, `created_at`) VALUES ('94', NULL, 'create', 'product_category', '32', 'Created product category SmokeCat_20260412121027.', '::1', 'Mozilla/5.0 (Windows NT; Windows NT 10.0; en-US) WindowsPowerShell/5.1.22621.4249', '2026-04-12 12:10:30');
INSERT INTO `audit_logs` (`id`, `user_id`, `action`, `entity_type`, `entity_id`, `description`, `ip_address`, `user_agent`, `created_at`) VALUES ('95', NULL, 'create', 'product_category', '33', 'Created product category SmokeSub_20260412121027.', '::1', 'Mozilla/5.0 (Windows NT; Windows NT 10.0; en-US) WindowsPowerShell/5.1.22621.4249', '2026-04-12 12:10:30');
INSERT INTO `audit_logs` (`id`, `user_id`, `action`, `entity_type`, `entity_id`, `description`, `ip_address`, `user_agent`, `created_at`) VALUES ('96', NULL, 'update', 'product_category', '31', 'Updated product category SmokeCatEdit_20260412121027.', '::1', 'Mozilla/5.0 (Windows NT; Windows NT 10.0; en-US) WindowsPowerShell/5.1.22621.4249', '2026-04-12 12:10:30');
INSERT INTO `audit_logs` (`id`, `user_id`, `action`, `entity_type`, `entity_id`, `description`, `ip_address`, `user_agent`, `created_at`) VALUES ('97', NULL, 'create', 'product_category', '34', 'Created product category SmokeExtraA_20260412121027.', '::1', 'Mozilla/5.0 (Windows NT; Windows NT 10.0; en-US) WindowsPowerShell/5.1.22621.4249', '2026-04-12 12:10:31');
INSERT INTO `audit_logs` (`id`, `user_id`, `action`, `entity_type`, `entity_id`, `description`, `ip_address`, `user_agent`, `created_at`) VALUES ('98', NULL, 'create', 'product_category', '35', 'Created product category SmokeExtraB_20260412121027.', '::1', 'Mozilla/5.0 (Windows NT; Windows NT 10.0; en-US) WindowsPowerShell/5.1.22621.4249', '2026-04-12 12:10:31');
INSERT INTO `audit_logs` (`id`, `user_id`, `action`, `entity_type`, `entity_id`, `description`, `ip_address`, `user_agent`, `created_at`) VALUES ('99', NULL, 'login', 'user', '15', 'User logged into the POS system.', '::1', 'unknown', '2026-04-12 12:19:39');
INSERT INTO `audit_logs` (`id`, `user_id`, `action`, `entity_type`, `entity_id`, `description`, `ip_address`, `user_agent`, `created_at`) VALUES ('100', NULL, 'create', 'product', '6', 'Created product SmokeProd_20260412141939.', '::1', 'unknown', '2026-04-12 12:19:39');
INSERT INTO `audit_logs` (`id`, `user_id`, `action`, `entity_type`, `entity_id`, `description`, `ip_address`, `user_agent`, `created_at`) VALUES ('101', NULL, 'update', 'product', '6', 'Updated product SmokeProdEdit_20260412141939.', '::1', 'unknown', '2026-04-12 12:19:39');
INSERT INTO `audit_logs` (`id`, `user_id`, `action`, `entity_type`, `entity_id`, `description`, `ip_address`, `user_agent`, `created_at`) VALUES ('102', NULL, 'login', 'user', '16', 'User logged into the POS system.', '::1', 'unknown', '2026-04-12 12:19:39');
INSERT INTO `audit_logs` (`id`, `user_id`, `action`, `entity_type`, `entity_id`, `description`, `ip_address`, `user_agent`, `created_at`) VALUES ('103', NULL, 'adjust', 'inventory', '6', 'Adjusted stock for SmokeProdEdit_20260412141939 by 1 units. Reason code: Manual restock (stocktake correction).', '::1', 'unknown', '2026-04-12 12:19:40');
INSERT INTO `audit_logs` (`id`, `user_id`, `action`, `entity_type`, `entity_id`, `description`, `ip_address`, `user_agent`, `created_at`) VALUES ('104', NULL, 'adjust', 'inventory', '6', 'Adjusted stock for SmokeProdEdit_20260412141939 by 1001 units. Reason code: Received from supplier - PO.', '::1', 'unknown', '2026-04-12 12:19:40');
INSERT INTO `audit_logs` (`id`, `user_id`, `action`, `entity_type`, `entity_id`, `description`, `ip_address`, `user_agent`, `created_at`) VALUES ('105', NULL, 'delete', 'product', '6', 'Soft-deleted product SmokeProdEdit_20260412141939.', '::1', 'unknown', '2026-04-12 12:19:40');
INSERT INTO `audit_logs` (`id`, `user_id`, `action`, `entity_type`, `entity_id`, `description`, `ip_address`, `user_agent`, `created_at`) VALUES ('106', NULL, 'create', 'product', '7', 'Created product SmokeBulkA_20260412141939.', '::1', 'unknown', '2026-04-12 12:19:40');
INSERT INTO `audit_logs` (`id`, `user_id`, `action`, `entity_type`, `entity_id`, `description`, `ip_address`, `user_agent`, `created_at`) VALUES ('107', NULL, 'create', 'product', '8', 'Created product SmokeBulkB_20260412141939.', '::1', 'unknown', '2026-04-12 12:19:40');
INSERT INTO `audit_logs` (`id`, `user_id`, `action`, `entity_type`, `entity_id`, `description`, `ip_address`, `user_agent`, `created_at`) VALUES ('108', NULL, 'create', 'product_category', '36', 'Created product category SmokeCat_20260412141939.', '::1', 'unknown', '2026-04-12 12:19:40');
INSERT INTO `audit_logs` (`id`, `user_id`, `action`, `entity_type`, `entity_id`, `description`, `ip_address`, `user_agent`, `created_at`) VALUES ('109', NULL, 'create', 'product_category', '37', 'Created product category SmokeSub_20260412141939.', '::1', 'unknown', '2026-04-12 12:19:40');
INSERT INTO `audit_logs` (`id`, `user_id`, `action`, `entity_type`, `entity_id`, `description`, `ip_address`, `user_agent`, `created_at`) VALUES ('110', NULL, 'update', 'product_category', '36', 'Updated product category SmokeCatEdit_20260412141939.', '::1', 'unknown', '2026-04-12 12:19:40');
INSERT INTO `audit_logs` (`id`, `user_id`, `action`, `entity_type`, `entity_id`, `description`, `ip_address`, `user_agent`, `created_at`) VALUES ('111', NULL, 'create', 'product_category', '38', 'Created product category SmokeExtraA_20260412141939.', '::1', 'unknown', '2026-04-12 12:19:40');
INSERT INTO `audit_logs` (`id`, `user_id`, `action`, `entity_type`, `entity_id`, `description`, `ip_address`, `user_agent`, `created_at`) VALUES ('112', NULL, 'create', 'product_category', '39', 'Created product category SmokeExtraB_20260412141939.', '::1', 'unknown', '2026-04-12 12:19:40');
INSERT INTO `audit_logs` (`id`, `user_id`, `action`, `entity_type`, `entity_id`, `description`, `ip_address`, `user_agent`, `created_at`) VALUES ('113', '17', 'login', 'user', '17', 'User logged into the POS system.', '::1', 'unknown', '2026-04-12 12:28:34');
INSERT INTO `audit_logs` (`id`, `user_id`, `action`, `entity_type`, `entity_id`, `description`, `ip_address`, `user_agent`, `created_at`) VALUES ('114', '17', 'login', 'user', '17', 'User logged into the POS system.', '::1', 'unknown', '2026-04-12 12:48:47');
INSERT INTO `audit_logs` (`id`, `user_id`, `action`, `entity_type`, `entity_id`, `description`, `ip_address`, `user_agent`, `created_at`) VALUES ('115', '17', 'delete', 'product', '10', 'Bulk archived product FocusSmoke_20260412_144847_502_BulkArchiveA.', '', '', '2026-04-12 12:48:47');
INSERT INTO `audit_logs` (`id`, `user_id`, `action`, `entity_type`, `entity_id`, `description`, `ip_address`, `user_agent`, `created_at`) VALUES ('116', '17', 'delete', 'product', '11', 'Bulk archived product FocusSmoke_20260412_144847_502_BulkArchiveB.', '', '', '2026-04-12 12:48:47');
INSERT INTO `audit_logs` (`id`, `user_id`, `action`, `entity_type`, `entity_id`, `description`, `ip_address`, `user_agent`, `created_at`) VALUES ('117', '17', 'adjust', 'inventory', '9', 'Adjusted stock for FocusSmoke_20260412_144847_502_Guard by 1001 units.', '::1', 'unknown', '2026-04-12 12:48:48');
INSERT INTO `audit_logs` (`id`, `user_id`, `action`, `entity_type`, `entity_id`, `description`, `ip_address`, `user_agent`, `created_at`) VALUES ('118', '17', 'delete', 'product_category', '41', 'Deleted product category FocusSmoke_20260412_144847_502_CatDeleteB.', '::1', 'unknown', '2026-04-12 12:48:48');
INSERT INTO `audit_logs` (`id`, `user_id`, `action`, `entity_type`, `entity_id`, `description`, `ip_address`, `user_agent`, `created_at`) VALUES ('119', '17', 'login', 'user', '17', 'User logged into the POS system.', '::1', 'unknown', '2026-04-12 12:57:53');
INSERT INTO `audit_logs` (`id`, `user_id`, `action`, `entity_type`, `entity_id`, `description`, `ip_address`, `user_agent`, `created_at`) VALUES ('120', '17', 'delete', 'product', '13', 'Bulk archived product FocusSmoke_20260412_145753_954_BulkArchiveA.', '', '', '2026-04-12 12:57:53');
INSERT INTO `audit_logs` (`id`, `user_id`, `action`, `entity_type`, `entity_id`, `description`, `ip_address`, `user_agent`, `created_at`) VALUES ('121', '17', 'delete', 'product', '14', 'Bulk archived product FocusSmoke_20260412_145753_954_BulkArchiveB.', '', '', '2026-04-12 12:57:53');
INSERT INTO `audit_logs` (`id`, `user_id`, `action`, `entity_type`, `entity_id`, `description`, `ip_address`, `user_agent`, `created_at`) VALUES ('122', '17', 'adjust', 'inventory', '12', 'Adjusted stock for FocusSmoke_20260412_145753_954_Guard by 1001 units.', '::1', 'unknown', '2026-04-12 12:57:54');
INSERT INTO `audit_logs` (`id`, `user_id`, `action`, `entity_type`, `entity_id`, `description`, `ip_address`, `user_agent`, `created_at`) VALUES ('123', '17', 'delete', 'product_category', '44', 'Deleted product category FocusSmoke_20260412_145753_954_CatDeleteA.', '::1', 'unknown', '2026-04-12 12:57:54');
INSERT INTO `audit_logs` (`id`, `user_id`, `action`, `entity_type`, `entity_id`, `description`, `ip_address`, `user_agent`, `created_at`) VALUES ('124', '17', 'delete', 'product_category', '45', 'Deleted product category FocusSmoke_20260412_145753_954_CatDeleteB.', '::1', 'unknown', '2026-04-12 12:57:54');
INSERT INTO `audit_logs` (`id`, `user_id`, `action`, `entity_type`, `entity_id`, `description`, `ip_address`, `user_agent`, `created_at`) VALUES ('125', '17', 'login', 'user', '17', 'User logged into the POS system.', '::1', 'unknown', '2026-04-12 14:19:56');
INSERT INTO `audit_logs` (`id`, `user_id`, `action`, `entity_type`, `entity_id`, `description`, `ip_address`, `user_agent`, `created_at`) VALUES ('126', '1', 'create', 'product', '15', 'Created product TWYFORD 60X60 YMP66220G.', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-12 14:44:43');

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
INSERT INTO `branches` (`id`, `name`, `code`, `address`, `phone`, `email`, `is_default`, `status`, `created_at`, `updated_at`, `deleted_at`) VALUES ('1', 'Main Branch', 'MAIN', '12 Market Square, Reykjavik', '0548719221', 'baafisamuel888@gmail.com', '1', 'active', '2026-03-01 08:00:00', '2026-04-10 16:44:35', NULL);
INSERT INTO `branches` (`id`, `name`, `code`, `address`, `phone`, `email`, `is_default`, `status`, `created_at`, `updated_at`, `deleted_at`) VALUES ('2', 'Harbor Branch', 'HARBOR', '88 Ocean Drive, Reykjavik', '+3547001001', 'harbor@novapos.test', '0', 'active', '2026-03-01 08:00:00', '2026-04-10 16:44:34', NULL);

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
) ENGINE=InnoDB AUTO_INCREMENT=49 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
INSERT INTO `categories` (`id`, `parent_id`, `name`, `slug`, `description`, `created_at`, `updated_at`, `deleted_at`) VALUES ('18', NULL, 'TILES', 'tiles-18', 'Imported from legacy posystem categories.', '2024-11-18 14:51:36', '2024-11-18 14:51:36', NULL);
INSERT INTO `categories` (`id`, `parent_id`, `name`, `slug`, `description`, `created_at`, `updated_at`, `deleted_at`) VALUES ('19', NULL, 'METAL STRIPS', 'metal-strips-19', 'Imported from legacy posystem categories.', '2024-11-18 16:37:29', '2024-11-18 16:37:29', NULL);
INSERT INTO `categories` (`id`, `parent_id`, `name`, `slug`, `description`, `created_at`, `updated_at`, `deleted_at`) VALUES ('20', NULL, 'GROUT', 'grout-20', 'Imported from legacy posystem categories.', '2024-11-18 16:37:35', '2026-04-12 00:34:56', '2026-04-12 00:34:56');
INSERT INTO `categories` (`id`, `parent_id`, `name`, `slug`, `description`, `created_at`, `updated_at`, `deleted_at`) VALUES ('21', NULL, 'CHEMICALS', 'chemicals-21', 'Imported from legacy posystem categories.', '2024-11-18 16:37:42', '2026-04-12 00:32:12', '2026-04-12 00:32:12');
INSERT INTO `categories` (`id`, `parent_id`, `name`, `slug`, `description`, `created_at`, `updated_at`, `deleted_at`) VALUES ('22', NULL, 'DOORS', 'doors-22', 'Imported from legacy posystem categories.', '2024-11-18 17:21:10', '2026-04-12 00:34:28', '2026-04-12 00:34:28');
INSERT INTO `categories` (`id`, `parent_id`, `name`, `slug`, `description`, `created_at`, `updated_at`, `deleted_at`) VALUES ('23', NULL, 'HOME CHARM', 'home-charm', '', '2024-11-21 22:12:08', '2026-04-12 00:40:42', '2026-04-12 00:40:42');
INSERT INTO `categories` (`id`, `parent_id`, `name`, `slug`, `description`, `created_at`, `updated_at`, `deleted_at`) VALUES ('24', NULL, 'CEMENT', 'cement', '', '2024-11-21 23:01:13', '2026-04-12 00:31:31', '2026-04-12 00:31:31');
INSERT INTO `categories` (`id`, `parent_id`, `name`, `slug`, `description`, `created_at`, `updated_at`, `deleted_at`) VALUES ('25', NULL, 'BLOCKS', 'blocks', '', '2025-01-04 17:03:54', '2026-04-12 00:29:47', '2026-04-12 00:29:47');
INSERT INTO `categories` (`id`, `parent_id`, `name`, `slug`, `description`, `created_at`, `updated_at`, `deleted_at`) VALUES ('26', NULL, 'GROUT', 'grout', '', '2026-04-12 11:30:35', '2026-04-12 11:30:35', NULL);
INSERT INTO `categories` (`id`, `parent_id`, `name`, `slug`, `description`, `created_at`, `updated_at`, `deleted_at`) VALUES ('27', NULL, 'CHEMICALS', 'chemicals', '', '2026-04-12 11:31:06', '2026-04-12 11:31:06', NULL);
INSERT INTO `categories` (`id`, `parent_id`, `name`, `slug`, `description`, `created_at`, `updated_at`, `deleted_at`) VALUES ('28', NULL, 'DOORS', 'doors', '', '2026-04-12 11:31:18', '2026-04-12 11:31:18', NULL);
INSERT INTO `categories` (`id`, `parent_id`, `name`, `slug`, `description`, `created_at`, `updated_at`, `deleted_at`) VALUES ('29', NULL, 'HOME CHARM', 'home-charm-1', '', '2026-04-12 11:31:31', '2026-04-12 11:31:31', NULL);
INSERT INTO `categories` (`id`, `parent_id`, `name`, `slug`, `description`, `created_at`, `updated_at`, `deleted_at`) VALUES ('30', NULL, 'CEMENT', 'cement-1', '', '2026-04-12 11:31:44', '2026-04-12 11:31:44', NULL);
INSERT INTO `categories` (`id`, `parent_id`, `name`, `slug`, `description`, `created_at`, `updated_at`, `deleted_at`) VALUES ('31', NULL, 'SmokeCatEdit_20260412121027', 'smokecatedit-20260412121027', 'Smoke parent category updated', '2026-04-12 11:32:11', '2026-04-12 12:19:40', '2026-04-12 12:19:40');
INSERT INTO `categories` (`id`, `parent_id`, `name`, `slug`, `description`, `created_at`, `updated_at`, `deleted_at`) VALUES ('32', NULL, 'SmokeCat_20260412121027', 'smokecat-20260412121027', 'Smoke parent category', '2026-04-12 12:10:30', '2026-04-12 12:19:40', '2026-04-12 12:19:40');
INSERT INTO `categories` (`id`, `parent_id`, `name`, `slug`, `description`, `created_at`, `updated_at`, `deleted_at`) VALUES ('33', '31', 'SmokeSub_20260412121027', 'smokesub-20260412121027', 'Smoke child category', '2026-04-12 12:10:30', '2026-04-12 12:19:40', '2026-04-12 12:19:40');
INSERT INTO `categories` (`id`, `parent_id`, `name`, `slug`, `description`, `created_at`, `updated_at`, `deleted_at`) VALUES ('34', NULL, 'SmokeExtraA_20260412121027', 'smokeextraa-20260412121027', 'Smoke bulk delete category', '2026-04-12 12:10:30', '2026-04-12 12:19:40', '2026-04-12 12:19:40');
INSERT INTO `categories` (`id`, `parent_id`, `name`, `slug`, `description`, `created_at`, `updated_at`, `deleted_at`) VALUES ('35', NULL, 'SmokeExtraB_20260412121027', 'smokeextrab-20260412121027', 'Smoke bulk delete category', '2026-04-12 12:10:31', '2026-04-12 12:19:40', '2026-04-12 12:19:40');
INSERT INTO `categories` (`id`, `parent_id`, `name`, `slug`, `description`, `created_at`, `updated_at`, `deleted_at`) VALUES ('36', NULL, 'SmokeCatEdit_20260412141939', 'smokecatedit-20260412141939', 'Smoke parent category updated', '2026-04-12 12:19:40', '2026-04-12 12:19:40', '2026-04-12 12:19:40');
INSERT INTO `categories` (`id`, `parent_id`, `name`, `slug`, `description`, `created_at`, `updated_at`, `deleted_at`) VALUES ('37', '36', 'SmokeSub_20260412141939', 'smokesub-20260412141939', 'Smoke child category', '2026-04-12 12:19:40', '2026-04-12 12:19:40', '2026-04-12 12:19:40');
INSERT INTO `categories` (`id`, `parent_id`, `name`, `slug`, `description`, `created_at`, `updated_at`, `deleted_at`) VALUES ('38', NULL, 'SmokeExtraA_20260412141939', 'smokeextraa-20260412141939', 'Smoke bulk delete category', '2026-04-12 12:19:40', '2026-04-12 12:19:40', '2026-04-12 12:19:40');
INSERT INTO `categories` (`id`, `parent_id`, `name`, `slug`, `description`, `created_at`, `updated_at`, `deleted_at`) VALUES ('39', NULL, 'SmokeExtraB_20260412141939', 'smokeextrab-20260412141939', 'Smoke bulk delete category', '2026-04-12 12:19:40', '2026-04-12 12:19:40', '2026-04-12 12:19:40');
INSERT INTO `categories` (`id`, `parent_id`, `name`, `slug`, `description`, `created_at`, `updated_at`, `deleted_at`) VALUES ('40', NULL, 'FocusSmoke_20260412_144847_502_CatDeleteA', 'focussmoke-20260412-144847-502-catdeletea-7182', 'Smoke test category', '2026-04-12 12:48:47', '2026-04-12 12:48:48', '2026-04-12 12:48:48');
INSERT INTO `categories` (`id`, `parent_id`, `name`, `slug`, `description`, `created_at`, `updated_at`, `deleted_at`) VALUES ('41', NULL, 'FocusSmoke_20260412_144847_502_CatDeleteB', 'focussmoke-20260412-144847-502-catdeleteb-2161', 'Smoke test category', '2026-04-12 12:48:47', '2026-04-12 12:48:48', '2026-04-12 12:48:48');
INSERT INTO `categories` (`id`, `parent_id`, `name`, `slug`, `description`, `created_at`, `updated_at`, `deleted_at`) VALUES ('42', NULL, 'FocusSmoke_20260412_144847_502_CatBlockedParent', 'focussmoke-20260412-144847-502-catblockedparent-7591', 'Smoke test category', '2026-04-12 12:48:47', '2026-04-12 12:48:48', '2026-04-12 12:48:48');
INSERT INTO `categories` (`id`, `parent_id`, `name`, `slug`, `description`, `created_at`, `updated_at`, `deleted_at`) VALUES ('43', '42', 'FocusSmoke_20260412_144847_502_CatBlockedChild', 'focussmoke-20260412-144847-502-catblockedchild-6574', 'Smoke test child category', '2026-04-12 12:48:47', '2026-04-12 12:48:48', '2026-04-12 12:48:48');
INSERT INTO `categories` (`id`, `parent_id`, `name`, `slug`, `description`, `created_at`, `updated_at`, `deleted_at`) VALUES ('44', NULL, 'FocusSmoke_20260412_145753_954_CatDeleteA', 'focussmoke-20260412-145753-954-catdeletea-9764', 'Smoke test category', '2026-04-12 12:57:53', '2026-04-12 12:57:54', '2026-04-12 12:57:54');
INSERT INTO `categories` (`id`, `parent_id`, `name`, `slug`, `description`, `created_at`, `updated_at`, `deleted_at`) VALUES ('45', NULL, 'FocusSmoke_20260412_145753_954_CatDeleteB', 'focussmoke-20260412-145753-954-catdeleteb-4336', 'Smoke test category', '2026-04-12 12:57:53', '2026-04-12 12:57:54', '2026-04-12 12:57:54');
INSERT INTO `categories` (`id`, `parent_id`, `name`, `slug`, `description`, `created_at`, `updated_at`, `deleted_at`) VALUES ('46', NULL, 'FocusSmoke_20260412_145753_954_CatBlockedParent', 'focussmoke-20260412-145753-954-catblockedparent-3841', 'Smoke test category', '2026-04-12 12:57:53', '2026-04-12 12:57:54', '2026-04-12 12:57:54');
INSERT INTO `categories` (`id`, `parent_id`, `name`, `slug`, `description`, `created_at`, `updated_at`, `deleted_at`) VALUES ('47', NULL, 'FocusSmoke_20260412_145753_954_CatProductBucket', 'focussmoke-20260412-145753-954-catproductbucket-7014', 'Smoke test category', '2026-04-12 12:57:53', '2026-04-12 12:57:54', '2026-04-12 12:57:54');
INSERT INTO `categories` (`id`, `parent_id`, `name`, `slug`, `description`, `created_at`, `updated_at`, `deleted_at`) VALUES ('48', '46', 'FocusSmoke_20260412_145753_954_CatBlockedChild', 'focussmoke-20260412-145753-954-catblockedchild-3553', 'Smoke test child category', '2026-04-12 12:57:53', '2026-04-12 12:57:54', '2026-04-12 12:57:54');

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
) ENGINE=InnoDB AUTO_INCREMENT=32 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
INSERT INTO `customers` (`id`, `branch_id`, `customer_group_id`, `first_name`, `last_name`, `email`, `phone`, `address`, `credit_balance`, `loyalty_balance`, `special_pricing_type`, `special_pricing_value`, `created_at`, `updated_at`, `deleted_at`) VALUES ('25', '1', '1', 'Legacy', 'Customer 25', NULL, NULL, NULL, '0.00', '0', 'none', '0.00', '2024-01-02 19:16:37', '2024-01-02 19:16:37', NULL);
INSERT INTO `customers` (`id`, `branch_id`, `customer_group_id`, `first_name`, `last_name`, `email`, `phone`, `address`, `credit_balance`, `loyalty_balance`, `special_pricing_type`, `special_pricing_value`, `created_at`, `updated_at`, `deleted_at`) VALUES ('27', '1', '1', 'Option', 'One Enterprise', NULL, '(055) 401-9237', 'Offinso', '0.00', '0', 'none', '0.00', '2025-07-05 16:22:08', '2025-07-05 16:22:08', NULL);
INSERT INTO `customers` (`id`, `branch_id`, `customer_group_id`, `first_name`, `last_name`, `email`, `phone`, `address`, `credit_balance`, `loyalty_balance`, `special_pricing_type`, `special_pricing_value`, `created_at`, `updated_at`, `deleted_at`) VALUES ('28', '1', '1', 'thomas', 'Legacy', NULL, '(024) 880-1570', 'offinso', '0.00', '0', 'none', '0.00', '2025-06-25 18:17:20', '2025-06-25 18:17:20', NULL);
INSERT INTO `customers` (`id`, `branch_id`, `customer_group_id`, `first_name`, `last_name`, `email`, `phone`, `address`, `credit_balance`, `loyalty_balance`, `special_pricing_type`, `special_pricing_value`, `created_at`, `updated_at`, `deleted_at`) VALUES ('29', '1', '1', 'oppong', 'Legacy', NULL, '(053) 151-5851', 'ahenkro', '0.00', '0', 'none', '0.00', '2025-01-13 12:23:28', '2025-01-13 12:23:28', NULL);
INSERT INTO `customers` (`id`, `branch_id`, `customer_group_id`, `first_name`, `last_name`, `email`, `phone`, `address`, `credit_balance`, `loyalty_balance`, `special_pricing_type`, `special_pricing_value`, `created_at`, `updated_at`, `deleted_at`) VALUES ('30', '1', '1', 'climent', 'Legacy', NULL, '(054) 918-4437', 'offinso', '0.00', '0', 'none', '0.00', '2025-01-17 16:33:52', '2025-01-17 16:33:52', NULL);
INSERT INTO `customers` (`id`, `branch_id`, `customer_group_id`, `first_name`, `last_name`, `email`, `phone`, `address`, `credit_balance`, `loyalty_balance`, `special_pricing_type`, `special_pricing_value`, `created_at`, `updated_at`, `deleted_at`) VALUES ('31', '1', '1', 'AUGUSTINA', 'Legacy', NULL, '(054) 117-0456', '0FFINSO', '0.00', '0', 'none', '0.00', '2025-06-21 17:25:02', '2025-06-21 17:25:02', NULL);

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
) ENGINE=InnoDB AUTO_INCREMENT=16 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
INSERT INTO `inventory` (`id`, `product_id`, `branch_id`, `quantity_on_hand`, `quantity_reserved`, `average_cost`, `valuation_method`, `last_restocked_at`, `created_at`, `updated_at`) VALUES ('1', '1', '1', '159.00', '0.00', '0.00', 'FIFO', '2026-04-11 17:03:17', '2026-04-11 17:03:17', '2026-04-11 18:14:30');
INSERT INTO `inventory` (`id`, `product_id`, `branch_id`, `quantity_on_hand`, `quantity_reserved`, `average_cost`, `valuation_method`, `last_restocked_at`, `created_at`, `updated_at`) VALUES ('2', '2', '1', '2.00', '0.00', '8.25', 'FIFO', '2026-04-12 11:29:55', '2026-04-12 11:29:55', '2026-04-12 11:29:55');
INSERT INTO `inventory` (`id`, `product_id`, `branch_id`, `quantity_on_hand`, `quantity_reserved`, `average_cost`, `valuation_method`, `last_restocked_at`, `created_at`, `updated_at`) VALUES ('3', '3', '1', '1004.00', '0.00', '8.25', 'FIFO', '2026-04-12 12:10:29', '2026-04-12 12:10:28', '2026-04-12 12:10:29');
INSERT INTO `inventory` (`id`, `product_id`, `branch_id`, `quantity_on_hand`, `quantity_reserved`, `average_cost`, `valuation_method`, `last_restocked_at`, `created_at`, `updated_at`) VALUES ('4', '4', '1', '1.00', '0.00', '4.00', 'FIFO', '2026-04-12 12:10:29', '2026-04-12 12:10:29', '2026-04-12 12:10:29');
INSERT INTO `inventory` (`id`, `product_id`, `branch_id`, `quantity_on_hand`, `quantity_reserved`, `average_cost`, `valuation_method`, `last_restocked_at`, `created_at`, `updated_at`) VALUES ('5', '5', '1', '1.00', '0.00', '4.00', 'FIFO', '2026-04-12 12:10:29', '2026-04-12 12:10:29', '2026-04-12 12:10:29');
INSERT INTO `inventory` (`id`, `product_id`, `branch_id`, `quantity_on_hand`, `quantity_reserved`, `average_cost`, `valuation_method`, `last_restocked_at`, `created_at`, `updated_at`) VALUES ('6', '6', '1', '1004.00', '0.00', '8.25', 'FIFO', '2026-04-12 12:19:40', '2026-04-12 12:19:39', '2026-04-12 12:19:40');
INSERT INTO `inventory` (`id`, `product_id`, `branch_id`, `quantity_on_hand`, `quantity_reserved`, `average_cost`, `valuation_method`, `last_restocked_at`, `created_at`, `updated_at`) VALUES ('7', '7', '1', '1.00', '0.00', '4.00', 'FIFO', '2026-04-12 12:19:40', '2026-04-12 12:19:40', '2026-04-12 12:19:40');
INSERT INTO `inventory` (`id`, `product_id`, `branch_id`, `quantity_on_hand`, `quantity_reserved`, `average_cost`, `valuation_method`, `last_restocked_at`, `created_at`, `updated_at`) VALUES ('8', '8', '1', '1.00', '0.00', '4.00', 'FIFO', '2026-04-12 12:19:40', '2026-04-12 12:19:40', '2026-04-12 12:19:40');
INSERT INTO `inventory` (`id`, `product_id`, `branch_id`, `quantity_on_hand`, `quantity_reserved`, `average_cost`, `valuation_method`, `last_restocked_at`, `created_at`, `updated_at`) VALUES ('15', '15', '1', '200.00', '0.00', '0.00', 'FIFO', '2026-04-12 14:44:42', '2026-04-12 14:44:42', '2026-04-12 14:44:42');

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
) ENGINE=InnoDB AUTO_INCREMENT=45 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
INSERT INTO `login_attempts` (`id`, `email`, `ip_address`, `attempted_at`, `success`) VALUES ('23', 'superadmin@novapos.test', '::1', '2026-04-11 23:57:32', '0');
INSERT INTO `login_attempts` (`id`, `email`, `ip_address`, `attempted_at`, `success`) VALUES ('24', 'baafisamuel888@gmail.com', '::1', '2026-04-11 23:57:46', '1');
INSERT INTO `login_attempts` (`id`, `email`, `ip_address`, `attempted_at`, `success`) VALUES ('25', 'samuelbaafi800@gmail.com', '::1', '2026-04-11 23:59:20', '1');
INSERT INTO `login_attempts` (`id`, `email`, `ip_address`, `attempted_at`, `success`) VALUES ('26', 'genisoft-1@legacy.optionone.local', '::1', '2026-04-12 00:08:07', '1');
INSERT INTO `login_attempts` (`id`, `email`, `ip_address`, `attempted_at`, `success`) VALUES ('27', 'genisoft-1@legacy.optionone.local', '::1', '2026-04-12 08:22:27', '1');
INSERT INTO `login_attempts` (`id`, `email`, `ip_address`, `attempted_at`, `success`) VALUES ('28', 'superadmin@novapos.test', '::1', '2026-04-12 11:16:26', '0');
INSERT INTO `login_attempts` (`id`, `email`, `ip_address`, `attempted_at`, `success`) VALUES ('29', 'superadmin@novapos.test', '::1', '2026-04-12 11:17:39', '0');
INSERT INTO `login_attempts` (`id`, `email`, `ip_address`, `attempted_at`, `success`) VALUES ('30', 'codex.smoke.20260412132241.main@novapos.test', '::1', '2026-04-12 11:23:15', '1');
INSERT INTO `login_attempts` (`id`, `email`, `ip_address`, `attempted_at`, `success`) VALUES ('31', 'codex.smoke.20260412132241.harbor@novapos.test', '::1', '2026-04-12 11:23:15', '1');
INSERT INTO `login_attempts` (`id`, `email`, `ip_address`, `attempted_at`, `success`) VALUES ('32', 'codex.smoke.20260412132241.main@novapos.test', '::1', '2026-04-12 11:29:54', '1');
INSERT INTO `login_attempts` (`id`, `email`, `ip_address`, `attempted_at`, `success`) VALUES ('33', 'codex.smoke.20260412132241.main@novapos.test', '::1', '2026-04-12 11:30:45', '1');
INSERT INTO `login_attempts` (`id`, `email`, `ip_address`, `attempted_at`, `success`) VALUES ('34', 'codex.smoke.20260412132241.main@novapos.test', '::1', '2026-04-12 12:01:51', '1');
INSERT INTO `login_attempts` (`id`, `email`, `ip_address`, `attempted_at`, `success`) VALUES ('35', 'codex.smoke.20260412132241.main@novapos.test', '::1', '2026-04-12 12:05:47', '1');
INSERT INTO `login_attempts` (`id`, `email`, `ip_address`, `attempted_at`, `success`) VALUES ('36', 'codex.smoke.20260412132241.main@novapos.test', '::1', '2026-04-12 12:05:47', '1');
INSERT INTO `login_attempts` (`id`, `email`, `ip_address`, `attempted_at`, `success`) VALUES ('37', 'codex.smoke.20260412132241.main@novapos.test', '::1', '2026-04-12 12:10:27', '1');
INSERT INTO `login_attempts` (`id`, `email`, `ip_address`, `attempted_at`, `success`) VALUES ('38', 'codex.smoke.20260412132241.harbor@novapos.test', '::1', '2026-04-12 12:10:28', '1');
INSERT INTO `login_attempts` (`id`, `email`, `ip_address`, `attempted_at`, `success`) VALUES ('39', 'codex.smoke.20260412132241.main@novapos.test', '::1', '2026-04-12 12:19:39', '1');
INSERT INTO `login_attempts` (`id`, `email`, `ip_address`, `attempted_at`, `success`) VALUES ('40', 'codex.smoke.20260412132241.harbor@novapos.test', '::1', '2026-04-12 12:19:39', '1');
INSERT INTO `login_attempts` (`id`, `email`, `ip_address`, `attempted_at`, `success`) VALUES ('41', 'codex.smoke.20260412142102.main@novapos.test', '::1', '2026-04-12 12:28:34', '1');
INSERT INTO `login_attempts` (`id`, `email`, `ip_address`, `attempted_at`, `success`) VALUES ('42', 'codex.smoke.20260412142102.main@novapos.test', '::1', '2026-04-12 12:48:47', '1');
INSERT INTO `login_attempts` (`id`, `email`, `ip_address`, `attempted_at`, `success`) VALUES ('43', 'codex.smoke.20260412142102.main@novapos.test', '::1', '2026-04-12 12:57:53', '1');
INSERT INTO `login_attempts` (`id`, `email`, `ip_address`, `attempted_at`, `success`) VALUES ('44', 'codex.smoke.20260412142102.main@novapos.test', '::1', '2026-04-12 14:19:56', '1');

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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

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
) ENGINE=InnoDB AUTO_INCREMENT=16 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
INSERT INTO `products` (`id`, `branch_id`, `category_id`, `supplier_id`, `tax_id`, `name`, `slug`, `sku`, `barcode`, `description`, `image_path`, `unit`, `price`, `cost_price`, `low_stock_threshold`, `track_stock`, `status`, `inventory_method`, `created_at`, `updated_at`, `deleted_at`) VALUES ('1', '1', '18', NULL, NULL, 'TWYFORD 30 X 30 FGP33139G', 'twyford-30-x-30-fgp33139g-b21727', 'FGP33139G', '260411535941', '', NULL, 'pcs', '113.00', '0.00', '5.00', '1', 'active', 'FIFO', '2026-04-11 17:03:17', '2026-04-11 17:03:17', NULL);
INSERT INTO `products` (`id`, `branch_id`, `category_id`, `supplier_id`, `tax_id`, `name`, `slug`, `sku`, `barcode`, `description`, `image_path`, `unit`, `price`, `cost_price`, `low_stock_threshold`, `track_stock`, `status`, `inventory_method`, `created_at`, `updated_at`, `deleted_at`) VALUES ('2', '1', NULL, NULL, NULL, 'SmokeProd_20260412112954', 'smokeprod-20260412112954-954679', 'SMK-20260412112954', '947770875988', 'Smoke test product', NULL, 'pcs', '19.50', '8.25', '1.00', '1', 'active', 'FIFO', '2026-04-12 11:29:55', '2026-04-12 12:19:40', '2026-04-12 12:19:40');
INSERT INTO `products` (`id`, `branch_id`, `category_id`, `supplier_id`, `tax_id`, `name`, `slug`, `sku`, `barcode`, `description`, `image_path`, `unit`, `price`, `cost_price`, `low_stock_threshold`, `track_stock`, `status`, `inventory_method`, `created_at`, `updated_at`, `deleted_at`) VALUES ('3', '1', NULL, NULL, NULL, 'SmokeProdEdit_20260412121027', 'smokeprodedit-20260412121027-8df8f6', 'SMK-20260412121027', '948882519298', 'Smoke test product updated', NULL, 'pcs', '21.75', '8.25', '1.00', '1', 'active', 'FIFO', '2026-04-12 12:10:28', '2026-04-12 12:10:29', '2026-04-12 12:10:29');
INSERT INTO `products` (`id`, `branch_id`, `category_id`, `supplier_id`, `tax_id`, `name`, `slug`, `sku`, `barcode`, `description`, `image_path`, `unit`, `price`, `cost_price`, `low_stock_threshold`, `track_stock`, `status`, `inventory_method`, `created_at`, `updated_at`, `deleted_at`) VALUES ('4', '1', NULL, NULL, NULL, 'SmokeBulkA_20260412121027', 'smokebulka-20260412121027-425be0', 'SMKB1-20260412121027', '826618795391', 'Bulk archive smoke', NULL, 'pcs', '10.00', '4.00', '1.00', '1', 'active', 'FIFO', '2026-04-12 12:10:29', '2026-04-12 12:10:30', '2026-04-12 12:10:30');
INSERT INTO `products` (`id`, `branch_id`, `category_id`, `supplier_id`, `tax_id`, `name`, `slug`, `sku`, `barcode`, `description`, `image_path`, `unit`, `price`, `cost_price`, `low_stock_threshold`, `track_stock`, `status`, `inventory_method`, `created_at`, `updated_at`, `deleted_at`) VALUES ('5', '1', NULL, NULL, NULL, 'SmokeBulkB_20260412121027', 'smokebulkb-20260412121027-cc1aa5', 'SMKB2-20260412121027', '710444165744', 'Bulk archive smoke', NULL, 'pcs', '10.00', '4.00', '1.00', '1', 'active', 'FIFO', '2026-04-12 12:10:29', '2026-04-12 12:19:40', '2026-04-12 12:19:40');
INSERT INTO `products` (`id`, `branch_id`, `category_id`, `supplier_id`, `tax_id`, `name`, `slug`, `sku`, `barcode`, `description`, `image_path`, `unit`, `price`, `cost_price`, `low_stock_threshold`, `track_stock`, `status`, `inventory_method`, `created_at`, `updated_at`, `deleted_at`) VALUES ('6', '1', NULL, NULL, NULL, 'SmokeProdEdit_20260412141939', 'smokeprodedit-20260412141939-c3ee26', 'SMK-20260412141939', '993220241080', 'Smoke test product updated', NULL, 'pcs', '21.75', '8.25', '1.00', '1', 'active', 'FIFO', '2026-04-12 12:19:39', '2026-04-12 12:19:40', '2026-04-12 12:19:40');
INSERT INTO `products` (`id`, `branch_id`, `category_id`, `supplier_id`, `tax_id`, `name`, `slug`, `sku`, `barcode`, `description`, `image_path`, `unit`, `price`, `cost_price`, `low_stock_threshold`, `track_stock`, `status`, `inventory_method`, `created_at`, `updated_at`, `deleted_at`) VALUES ('7', '1', NULL, NULL, NULL, 'SmokeBulkA_20260412141939', 'smokebulka-20260412141939-5b0393', 'SMKB1-20260412141939', '827824317619', 'Bulk archive smoke', NULL, 'pcs', '10.00', '4.00', '1.00', '1', 'active', 'FIFO', '2026-04-12 12:19:40', '2026-04-12 12:19:40', '2026-04-12 12:19:40');
INSERT INTO `products` (`id`, `branch_id`, `category_id`, `supplier_id`, `tax_id`, `name`, `slug`, `sku`, `barcode`, `description`, `image_path`, `unit`, `price`, `cost_price`, `low_stock_threshold`, `track_stock`, `status`, `inventory_method`, `created_at`, `updated_at`, `deleted_at`) VALUES ('8', '1', NULL, NULL, NULL, 'SmokeBulkB_20260412141939', 'smokebulkb-20260412141939-9d35ab', 'SMKB2-20260412141939', '741919483092', 'Bulk archive smoke', NULL, 'pcs', '10.00', '4.00', '1.00', '1', 'active', 'FIFO', '2026-04-12 12:19:40', '2026-04-12 12:19:40', '2026-04-12 12:19:40');
INSERT INTO `products` (`id`, `branch_id`, `category_id`, `supplier_id`, `tax_id`, `name`, `slug`, `sku`, `barcode`, `description`, `image_path`, `unit`, `price`, `cost_price`, `low_stock_threshold`, `track_stock`, `status`, `inventory_method`, `created_at`, `updated_at`, `deleted_at`) VALUES ('9', '1', '40', NULL, NULL, 'FocusSmoke_20260412_144847_502_Guard', 'focussmoke-20260412-144847-502-guard-2269', 'SKU-A3DBB542A7', '9952805045', 'Smoke test product', NULL, 'pcs', '100.00', '60.00', '2.00', '1', 'active', 'FIFO', '2026-04-12 12:48:47', '2026-04-12 12:48:48', '2026-04-12 12:48:48');
INSERT INTO `products` (`id`, `branch_id`, `category_id`, `supplier_id`, `tax_id`, `name`, `slug`, `sku`, `barcode`, `description`, `image_path`, `unit`, `price`, `cost_price`, `low_stock_threshold`, `track_stock`, `status`, `inventory_method`, `created_at`, `updated_at`, `deleted_at`) VALUES ('10', '1', '40', NULL, NULL, 'FocusSmoke_20260412_144847_502_BulkArchiveA', 'focussmoke-20260412-144847-502-bulkarchivea-6667', 'SKU-B275D1E68C', '9965340531', 'Smoke test product', NULL, 'pcs', '100.00', '60.00', '2.00', '1', 'active', 'FIFO', '2026-04-12 12:48:47', '2026-04-12 12:48:48', '2026-04-12 12:48:48');
INSERT INTO `products` (`id`, `branch_id`, `category_id`, `supplier_id`, `tax_id`, `name`, `slug`, `sku`, `barcode`, `description`, `image_path`, `unit`, `price`, `cost_price`, `low_stock_threshold`, `track_stock`, `status`, `inventory_method`, `created_at`, `updated_at`, `deleted_at`) VALUES ('11', '1', '41', NULL, NULL, 'FocusSmoke_20260412_144847_502_BulkArchiveB', 'focussmoke-20260412-144847-502-bulkarchiveb-4751', 'SKU-51B02582B4', '9931448104', 'Smoke test product', NULL, 'pcs', '100.00', '60.00', '2.00', '1', 'active', 'FIFO', '2026-04-12 12:48:47', '2026-04-12 12:48:48', '2026-04-12 12:48:48');
INSERT INTO `products` (`id`, `branch_id`, `category_id`, `supplier_id`, `tax_id`, `name`, `slug`, `sku`, `barcode`, `description`, `image_path`, `unit`, `price`, `cost_price`, `low_stock_threshold`, `track_stock`, `status`, `inventory_method`, `created_at`, `updated_at`, `deleted_at`) VALUES ('12', '1', '47', NULL, NULL, 'FocusSmoke_20260412_145753_954_Guard', 'focussmoke-20260412-145753-954-guard-5770', 'SKU-75B0CB7164', '9931095021', 'Smoke test product', NULL, 'pcs', '100.00', '60.00', '2.00', '1', 'active', 'FIFO', '2026-04-12 12:57:53', '2026-04-12 12:57:54', '2026-04-12 12:57:54');
INSERT INTO `products` (`id`, `branch_id`, `category_id`, `supplier_id`, `tax_id`, `name`, `slug`, `sku`, `barcode`, `description`, `image_path`, `unit`, `price`, `cost_price`, `low_stock_threshold`, `track_stock`, `status`, `inventory_method`, `created_at`, `updated_at`, `deleted_at`) VALUES ('13', '1', '47', NULL, NULL, 'FocusSmoke_20260412_145753_954_BulkArchiveA', 'focussmoke-20260412-145753-954-bulkarchivea-5576', 'SKU-67120F4498', '9970901541', 'Smoke test product', NULL, 'pcs', '100.00', '60.00', '2.00', '1', 'active', 'FIFO', '2026-04-12 12:57:53', '2026-04-12 12:57:54', '2026-04-12 12:57:54');
INSERT INTO `products` (`id`, `branch_id`, `category_id`, `supplier_id`, `tax_id`, `name`, `slug`, `sku`, `barcode`, `description`, `image_path`, `unit`, `price`, `cost_price`, `low_stock_threshold`, `track_stock`, `status`, `inventory_method`, `created_at`, `updated_at`, `deleted_at`) VALUES ('14', '1', '47', NULL, NULL, 'FocusSmoke_20260412_145753_954_BulkArchiveB', 'focussmoke-20260412-145753-954-bulkarchiveb-6777', 'SKU-5E20594304', '9923989862', 'Smoke test product', NULL, 'pcs', '100.00', '60.00', '2.00', '1', 'active', 'FIFO', '2026-04-12 12:57:53', '2026-04-12 12:57:54', '2026-04-12 12:57:54');
INSERT INTO `products` (`id`, `branch_id`, `category_id`, `supplier_id`, `tax_id`, `name`, `slug`, `sku`, `barcode`, `description`, `image_path`, `unit`, `price`, `cost_price`, `low_stock_threshold`, `track_stock`, `status`, `inventory_method`, `created_at`, `updated_at`, `deleted_at`) VALUES ('15', '1', '18', NULL, NULL, 'TWYFORD 60X60 YMP66220G', 'twyford-60x60-ymp66220g-470fc0', 'YMP66220G', '260412275629', '', NULL, 'pcs', '217.00', '0.00', '5.00', '1', 'active', 'FIFO', '2026-04-12 14:44:42', '2026-04-12 14:44:42', NULL);

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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

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
) ENGINE=InnoDB AUTO_INCREMENT=36 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
INSERT INTO `settings` (`id`, `key_name`, `value_text`, `type`, `created_at`, `updated_at`) VALUES ('1', 'business_name', 'OPTION ONE ENT.', 'string', '2026-03-01 08:00:00', '2026-04-10 16:45:32');
INSERT INTO `settings` (`id`, `key_name`, `value_text`, `type`, `created_at`, `updated_at`) VALUES ('2', 'business_address', '12 Market Square, Reykjavik', 'string', '2026-03-01 08:00:00', '2026-04-10 16:45:32');
INSERT INTO `settings` (`id`, `key_name`, `value_text`, `type`, `created_at`, `updated_at`) VALUES ('3', 'business_phone', '+233548719221', 'string', '2026-03-01 08:00:00', '2026-04-10 16:45:32');
INSERT INTO `settings` (`id`, `key_name`, `value_text`, `type`, `created_at`, `updated_at`) VALUES ('4', 'currency', 'GHS', 'string', '2026-03-01 08:00:00', '2026-04-10 16:45:32');
INSERT INTO `settings` (`id`, `key_name`, `value_text`, `type`, `created_at`, `updated_at`) VALUES ('5', 'receipt_header', 'Thank you for shopping with ECHELONGH TECHNOLOGY LTD', 'string', '2026-03-01 08:00:00', '2026-04-10 16:45:32');
INSERT INTO `settings` (`id`, `key_name`, `value_text`, `type`, `created_at`, `updated_at`) VALUES ('6', 'receipt_footer', 'Goods sold are subject to store policy.', 'string', '2026-03-01 08:00:00', '2026-04-10 16:45:32');
INSERT INTO `settings` (`id`, `key_name`, `value_text`, `type`, `created_at`, `updated_at`) VALUES ('7', 'barcode_format', 'CODE128', 'string', '2026-03-01 08:00:00', '2026-04-10 16:45:32');
INSERT INTO `settings` (`id`, `key_name`, `value_text`, `type`, `created_at`, `updated_at`) VALUES ('8', 'tax_default', 'VAT 7.5%', 'string', '2026-03-01 08:00:00', '2026-04-10 16:45:32');
INSERT INTO `settings` (`id`, `key_name`, `value_text`, `type`, `created_at`, `updated_at`) VALUES ('9', 'multi_branch_enabled', 'true', 'boolean', '2026-03-01 08:00:00', '2026-04-10 16:45:32');
INSERT INTO `settings` (`id`, `key_name`, `value_text`, `type`, `created_at`, `updated_at`) VALUES ('13', 'business_email', 'baafisamuel888@gmail.com', 'string', '2026-03-18 17:22:35', '2026-04-10 16:45:32');
INSERT INTO `settings` (`id`, `key_name`, `value_text`, `type`, `created_at`, `updated_at`) VALUES ('20', 'business_logo_path', '', 'string', '2026-03-18 17:22:35', '2026-04-10 16:45:32');
INSERT INTO `settings` (`id`, `key_name`, `value_text`, `type`, `created_at`, `updated_at`) VALUES ('31', 'email_low_stock_alerts_enabled', 'true', 'boolean', '2026-04-10 16:45:32', '2026-04-10 16:45:32');
INSERT INTO `settings` (`id`, `key_name`, `value_text`, `type`, `created_at`, `updated_at`) VALUES ('32', 'email_daily_summary_enabled', 'true', 'boolean', '2026-04-10 16:45:32', '2026-04-10 16:45:32');
INSERT INTO `settings` (`id`, `key_name`, `value_text`, `type`, `created_at`, `updated_at`) VALUES ('33', 'ops_email_recipient_scope', 'business_and_team', 'string', '2026-04-10 16:45:32', '2026-04-10 16:45:32');
INSERT INTO `settings` (`id`, `key_name`, `value_text`, `type`, `created_at`, `updated_at`) VALUES ('34', 'ops_email_additional_recipients', '', 'string', '2026-04-10 16:45:32', '2026-04-10 16:45:32');

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
) ENGINE=InnoDB AUTO_INCREMENT=20 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
INSERT INTO `stock_movements` (`id`, `product_id`, `branch_id`, `user_id`, `movement_type`, `reason`, `reference_type`, `reference_id`, `quantity_change`, `balance_after`, `unit_cost`, `created_at`) VALUES ('1', '1', '1', NULL, 'opening', 'Initial stock', 'product', '1', '120.00', '120.00', '0.00', '2026-04-11 17:03:17');
INSERT INTO `stock_movements` (`id`, `product_id`, `branch_id`, `user_id`, `movement_type`, `reason`, `reference_type`, `reference_id`, `quantity_change`, `balance_after`, `unit_cost`, `created_at`) VALUES ('2', '1', '1', '1', 'adjustment', 'new product', 'manual_adjustment', '1', '52.00', '172.00', '0.00', '2026-04-11 17:37:41');
INSERT INTO `stock_movements` (`id`, `product_id`, `branch_id`, `user_id`, `movement_type`, `reason`, `reference_type`, `reference_id`, `quantity_change`, `balance_after`, `unit_cost`, `created_at`) VALUES ('3', '1', '1', '1', 'adjustment', 'Manual restock (stocktake correction)', 'manual_adjustment', '1', '-34.00', '138.00', '0.00', '2026-04-11 17:40:46');
INSERT INTO `stock_movements` (`id`, `product_id`, `branch_id`, `user_id`, `movement_type`, `reason`, `reference_type`, `reference_id`, `quantity_change`, `balance_after`, `unit_cost`, `created_at`) VALUES ('4', '1', '1', '1', 'adjustment', '[Manual restock (stocktake correction)] Manual restock (stocktake correction)', 'manual_adjustment', '1', '23.00', '161.00', '0.00', '2026-04-11 18:02:08');
INSERT INTO `stock_movements` (`id`, `product_id`, `branch_id`, `user_id`, `movement_type`, `reason`, `reference_type`, `reference_id`, `quantity_change`, `balance_after`, `unit_cost`, `created_at`) VALUES ('5', '1', '1', '1', 'adjustment', '[Manual restock (stocktake correction)] Manual restock (stocktake correction)', 'manual_adjustment', '1', '-2.00', '159.00', '0.00', '2026-04-11 18:14:30');
INSERT INTO `stock_movements` (`id`, `product_id`, `branch_id`, `user_id`, `movement_type`, `reason`, `reference_type`, `reference_id`, `quantity_change`, `balance_after`, `unit_cost`, `created_at`) VALUES ('6', '2', '1', NULL, 'opening', 'Initial stock', 'product', '2', '2.00', '2.00', '8.25', '2026-04-12 11:29:55');
INSERT INTO `stock_movements` (`id`, `product_id`, `branch_id`, `user_id`, `movement_type`, `reason`, `reference_type`, `reference_id`, `quantity_change`, `balance_after`, `unit_cost`, `created_at`) VALUES ('7', '3', '1', NULL, 'opening', 'Initial stock', 'product', '3', '2.00', '2.00', '8.25', '2026-04-12 12:10:28');
INSERT INTO `stock_movements` (`id`, `product_id`, `branch_id`, `user_id`, `movement_type`, `reason`, `reference_type`, `reference_id`, `quantity_change`, `balance_after`, `unit_cost`, `created_at`) VALUES ('8', '3', '1', NULL, 'adjustment', '[Manual restock (stocktake correction)] Smoke small restock', 'manual_adjustment', '3', '1.00', '3.00', '8.25', '2026-04-12 12:10:29');
INSERT INTO `stock_movements` (`id`, `product_id`, `branch_id`, `user_id`, `movement_type`, `reason`, `reference_type`, `reference_id`, `quantity_change`, `balance_after`, `unit_cost`, `created_at`) VALUES ('9', '3', '1', NULL, 'adjustment', '[Received from supplier - PO] Smoke large restock confirmed', 'manual_adjustment', '3', '1001.00', '1004.00', '8.25', '2026-04-12 12:10:29');
INSERT INTO `stock_movements` (`id`, `product_id`, `branch_id`, `user_id`, `movement_type`, `reason`, `reference_type`, `reference_id`, `quantity_change`, `balance_after`, `unit_cost`, `created_at`) VALUES ('10', '4', '1', NULL, 'opening', 'Initial stock', 'product', '4', '1.00', '1.00', '4.00', '2026-04-12 12:10:29');
INSERT INTO `stock_movements` (`id`, `product_id`, `branch_id`, `user_id`, `movement_type`, `reason`, `reference_type`, `reference_id`, `quantity_change`, `balance_after`, `unit_cost`, `created_at`) VALUES ('11', '5', '1', NULL, 'opening', 'Initial stock', 'product', '5', '1.00', '1.00', '4.00', '2026-04-12 12:10:29');
INSERT INTO `stock_movements` (`id`, `product_id`, `branch_id`, `user_id`, `movement_type`, `reason`, `reference_type`, `reference_id`, `quantity_change`, `balance_after`, `unit_cost`, `created_at`) VALUES ('12', '6', '1', NULL, 'opening', 'Initial stock', 'product', '6', '2.00', '2.00', '8.25', '2026-04-12 12:19:39');
INSERT INTO `stock_movements` (`id`, `product_id`, `branch_id`, `user_id`, `movement_type`, `reason`, `reference_type`, `reference_id`, `quantity_change`, `balance_after`, `unit_cost`, `created_at`) VALUES ('13', '6', '1', NULL, 'adjustment', '[Manual restock (stocktake correction)] Smoke small restock', 'manual_adjustment', '6', '1.00', '3.00', '8.25', '2026-04-12 12:19:40');
INSERT INTO `stock_movements` (`id`, `product_id`, `branch_id`, `user_id`, `movement_type`, `reason`, `reference_type`, `reference_id`, `quantity_change`, `balance_after`, `unit_cost`, `created_at`) VALUES ('14', '6', '1', NULL, 'adjustment', '[Received from supplier - PO] Smoke large restock confirmed', 'manual_adjustment', '6', '1001.00', '1004.00', '8.25', '2026-04-12 12:19:40');
INSERT INTO `stock_movements` (`id`, `product_id`, `branch_id`, `user_id`, `movement_type`, `reason`, `reference_type`, `reference_id`, `quantity_change`, `balance_after`, `unit_cost`, `created_at`) VALUES ('15', '7', '1', NULL, 'opening', 'Initial stock', 'product', '7', '1.00', '1.00', '4.00', '2026-04-12 12:19:40');
INSERT INTO `stock_movements` (`id`, `product_id`, `branch_id`, `user_id`, `movement_type`, `reason`, `reference_type`, `reference_id`, `quantity_change`, `balance_after`, `unit_cost`, `created_at`) VALUES ('16', '8', '1', NULL, 'opening', 'Initial stock', 'product', '8', '1.00', '1.00', '4.00', '2026-04-12 12:19:40');
INSERT INTO `stock_movements` (`id`, `product_id`, `branch_id`, `user_id`, `movement_type`, `reason`, `reference_type`, `reference_id`, `quantity_change`, `balance_after`, `unit_cost`, `created_at`) VALUES ('19', '15', '1', NULL, 'opening', 'Initial stock', 'product', '15', '200.00', '200.00', '0.00', '2026-04-12 14:44:42');

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
) ENGINE=InnoDB AUTO_INCREMENT=19 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
INSERT INTO `users` (`id`, `branch_id`, `role_id`, `first_name`, `last_name`, `email`, `phone`, `password`, `status`, `remember_token`, `remember_expires_at`, `last_login_at`, `last_activity_at`, `failed_login_attempts`, `locked_until`, `created_at`, `updated_at`, `deleted_at`) VALUES ('1', '1', '2', 'AFIMITECH', 'SOLUTIONS', 'genisoft-1@legacy.optionone.local', NULL, '$2y$10$Zl2d8yxwgqGXYP.6S6xUge2fYlr6umklHoTVzfKZywI0yZP/jRKS2', 'active', NULL, NULL, '2026-04-12 08:22:27', '2026-04-12 08:22:27', '0', NULL, '2024-11-18 19:42:32', '2026-04-12 08:22:27', NULL);
INSERT INTO `users` (`id`, `branch_id`, `role_id`, `first_name`, `last_name`, `email`, `phone`, `password`, `status`, `remember_token`, `remember_expires_at`, `last_login_at`, `last_activity_at`, `failed_login_attempts`, `locked_until`, `created_at`, `updated_at`, `deleted_at`) VALUES ('4', '1', '4', 'Collins', 'Legacy', 'seller-4@legacy.optionone.local', NULL, '$2a$07$asxx54ahjppf45sd87a5auEQRApNUi0LVoru/H5eUIECvcZOabMp2', 'active', NULL, NULL, '2024-12-21 22:29:30', '2024-12-21 22:29:30', '0', NULL, '2024-12-22 03:29:30', '2024-12-22 03:29:30', NULL);
INSERT INTO `users` (`id`, `branch_id`, `role_id`, `first_name`, `last_name`, `email`, `phone`, `password`, `status`, `remember_token`, `remember_expires_at`, `last_login_at`, `last_activity_at`, `failed_login_attempts`, `locked_until`, `created_at`, `updated_at`, `deleted_at`) VALUES ('6', '1', '4', 'Baafi', 'Samuel', 'baafisamuel888@gmail.com', '0548719221', '$2y$10$AjW0nEmcBLIkzx0WzkJyv.vvu7Bf9eXUr7X9voRt7de/TZrjBbgxq', 'active', NULL, NULL, '2026-04-11 23:57:46', '2026-04-11 23:57:46', '0', NULL, '2026-03-19 00:34:40', '2026-04-11 23:57:46', NULL);
INSERT INTO `users` (`id`, `branch_id`, `role_id`, `first_name`, `last_name`, `email`, `phone`, `password`, `status`, `remember_token`, `remember_expires_at`, `last_login_at`, `last_activity_at`, `failed_login_attempts`, `locked_until`, `created_at`, `updated_at`, `deleted_at`) VALUES ('7', '2', '4', 'Akua', 'Samuel', 'samuelbaafi800@gmail.com', '0548719221', '$2y$10$pmDs7EpfrnYnrpuB.8EiZOQGxMMHGFKXBmIyVv.BgLjqwg08C5MQ6', 'active', NULL, NULL, '2026-04-11 23:59:20', '2026-04-11 23:59:20', '0', NULL, '2026-03-19 00:36:32', '2026-04-11 23:59:20', NULL);
INSERT INTO `users` (`id`, `branch_id`, `role_id`, `first_name`, `last_name`, `email`, `phone`, `password`, `status`, `remember_token`, `remember_expires_at`, `last_login_at`, `last_activity_at`, `failed_login_attempts`, `locked_until`, `created_at`, `updated_at`, `deleted_at`) VALUES ('13', '1', '1', 'Administrator', 'Legacy', 'admin-13@legacy.optionone.local', NULL, '$2a$07$asxx54ahjppf45sd87a5auXBm1Vr2M1NV5t/zNQtGHGpS5fFirrbG', 'active', NULL, NULL, '2026-04-11 07:02:22', '2026-04-11 07:02:22', '0', NULL, '2026-04-11 12:02:22', '2026-04-11 12:02:22', NULL);
INSERT INTO `users` (`id`, `branch_id`, `role_id`, `first_name`, `last_name`, `email`, `phone`, `password`, `status`, `remember_token`, `remember_expires_at`, `last_login_at`, `last_activity_at`, `failed_login_attempts`, `locked_until`, `created_at`, `updated_at`, `deleted_at`) VALUES ('14', '1', '4', 'Asomaning', 'Grace Konadu', 'grace-14@legacy.optionone.local', NULL, '$2a$07$asxx54ahjppf45sd87a5auQXspIrqZbJUC8AUF7MUiH16FJUE9R6y', 'active', NULL, NULL, '2025-07-04 11:25:49', '2025-07-04 11:25:49', '0', NULL, '2025-07-04 16:25:49', '2025-07-04 16:25:49', NULL);
INSERT INTO `users` (`id`, `branch_id`, `role_id`, `first_name`, `last_name`, `email`, `phone`, `password`, `status`, `remember_token`, `remember_expires_at`, `last_login_at`, `last_activity_at`, `failed_login_attempts`, `locked_until`, `created_at`, `updated_at`, `deleted_at`) VALUES ('17', '1', '1', 'Codex', 'SmokeMain', 'codex.smoke.20260412142102.main@novapos.test', '+10000000001', '$2y$10$M6evs3zMfg6FUSs9Ej5WSOMO3KfJL6mmprQH43riEI2MCDJ1vqa32', 'active', NULL, NULL, '2026-04-12 14:19:56', '2026-04-12 14:19:56', '0', NULL, '2026-04-12 12:21:03', '2026-04-12 14:19:56', '2026-04-12 14:19:56');
INSERT INTO `users` (`id`, `branch_id`, `role_id`, `first_name`, `last_name`, `email`, `phone`, `password`, `status`, `remember_token`, `remember_expires_at`, `last_login_at`, `last_activity_at`, `failed_login_attempts`, `locked_until`, `created_at`, `updated_at`, `deleted_at`) VALUES ('18', '2', '3', 'Codex', 'SmokeHarbor', 'codex.smoke.20260412142102.harbor@novapos.test', '+10000000002', '$2y$10$M6evs3zMfg6FUSs9Ej5WSOMO3KfJL6mmprQH43riEI2MCDJ1vqa32', 'active', NULL, NULL, NULL, NULL, '0', NULL, '2026-04-12 12:21:03', '2026-04-12 12:57:54', '2026-04-12 12:57:54');

SET FOREIGN_KEY_CHECKS=1;
