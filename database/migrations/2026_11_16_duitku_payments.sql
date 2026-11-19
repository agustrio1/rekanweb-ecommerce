ALTER TABLE `payments`
  ADD COLUMN `payment_url` VARCHAR(500) DEFAULT NULL AFTER `gateway_transaction_id`,
  ADD COLUMN `payment_method` VARCHAR(50) DEFAULT NULL AFTER `payment_url`,
  ADD COLUMN `paid_at` DATETIME DEFAULT NULL AFTER `status`;

CREATE TABLE IF NOT EXISTS `payment_callbacks` (
  `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `order_id` BIGINT UNSIGNED DEFAULT NULL,
  `merchant_order_id` VARCHAR(50) NOT NULL,
  `reference` VARCHAR(100) DEFAULT NULL,
  `result_code` VARCHAR(10) NOT NULL,
  `amount` BIGINT UNSIGNED NOT NULL DEFAULT 0,
  `note` VARCHAR(255) DEFAULT NULL,
  `payload` JSON DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`order_id`) REFERENCES `orders`(`id`) ON DELETE SET NULL,
  INDEX `idx_callbacks_order_number` (`merchant_order_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;