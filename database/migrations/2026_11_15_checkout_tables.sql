ALTER TABLE `orders`
  ADD COLUMN `expires_at` DATETIME DEFAULT NULL AFTER `payment_status`,
  ADD INDEX `idx_orders_expiry` (`payment_status`, `expires_at`);

ALTER TABLE `shipments`
  ADD COLUMN `courier_name` VARCHAR(100) DEFAULT NULL AFTER `courier_type`,
  ADD COLUMN `service_name` VARCHAR(100) DEFAULT NULL AFTER `courier_name`,
  ADD COLUMN `estimate` VARCHAR(50) DEFAULT NULL AFTER `service_name`;

CREATE TABLE IF NOT EXISTS `stock_reservations` (
  `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `order_id` BIGINT UNSIGNED NOT NULL,
  `product_variant_id` BIGINT UNSIGNED NOT NULL,
  `quantity` INT UNSIGNED NOT NULL,
  `status` ENUM('active', 'consumed', 'released') NOT NULL DEFAULT 'active',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`order_id`) REFERENCES `orders`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`product_variant_id`) REFERENCES `product_variants`(`id`) ON DELETE RESTRICT,
  INDEX `idx_reservations_order` (`order_id`, `status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;