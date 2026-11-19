
-- ==========================================
-- REKANWEB COMMERCE - DATABASE SCHEMA & SEED
-- Engine: InnoDB | Charset: utf8mb4_unicode_ci
-- ==========================================

SET FOREIGN_KEY_CHECKS = 0;

-- 1. USERS & AUTHENTICATION
DROP TABLE IF EXISTS `users`;
CREATE TABLE `users` (
  `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(100) NOT NULL,
  `email` VARCHAR(100) NOT NULL UNIQUE,
  `password` VARCHAR(255) NOT NULL,
  `phone` VARCHAR(20) DEFAULT NULL,
  `role` ENUM('customer', 'admin') DEFAULT 'customer',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX `idx_users_email` (`email`),
  INDEX `idx_users_role` (`role`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `user_addresses`;
CREATE TABLE `user_addresses` (
  `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `user_id` BIGINT UNSIGNED NOT NULL,
  `recipient_name` VARCHAR(100) NOT NULL,
  `phone` VARCHAR(20) NOT NULL,
  `area_id` VARCHAR(100) DEFAULT NULL,
  `area_name` VARCHAR(255) DEFAULT NULL,
  `province` VARCHAR(100) NOT NULL,
  `city` VARCHAR(100) NOT NULL,
  `district` VARCHAR(100) NOT NULL,
  `postal_code` VARCHAR(10) NOT NULL,
  `address_detail` TEXT NOT NULL,
  `is_default` TINYINT(1) DEFAULT 0,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  INDEX `idx_user_addresses_user` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. PRODUCT MANAGEMENT
DROP TABLE IF EXISTS `categories`;
CREATE TABLE `categories` (
  `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(100) NOT NULL,
  `slug` VARCHAR(100) NOT NULL UNIQUE,
  `description` TEXT DEFAULT NULL,
  `is_active` TINYINT(1) DEFAULT 1,
  `is_featured` TINYINT(1) NOT NULL DEFAULT 0,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_categories_slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `products`;
CREATE TABLE `products` (
  `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `category_id` BIGINT UNSIGNED NOT NULL,
  `name` VARCHAR(200) NOT NULL,
  `slug` VARCHAR(200) NOT NULL UNIQUE,
  `description` TEXT DEFAULT NULL,
  `weight_gram` INT UNSIGNED DEFAULT 0,
  `length_cm` INT UNSIGNED DEFAULT 0,
  `width_cm` INT UNSIGNED DEFAULT 0,
  `height_cm` INT UNSIGNED DEFAULT 0,
  `is_active` TINYINT(1) DEFAULT 1,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`category_id`) REFERENCES `categories`(`id`) ON DELETE RESTRICT,
  INDEX `idx_products_slug` (`slug`),
  INDEX `idx_products_category` (`category_id`),
  INDEX `idx_products_active_created` (`is_active`, `created_at`, `id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `product_variants`;
CREATE TABLE `product_variants` (
  `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `product_id` BIGINT UNSIGNED NOT NULL,
  `sku` VARCHAR(100) NOT NULL UNIQUE,
  `variant_name` VARCHAR(100) NOT NULL COMMENT 'Contoh: Merah - XL',
  `price` DECIMAL(15,0) NOT NULL COMMENT 'IDR tanpa desimal',
  `discount_price` DECIMAL(15,0) DEFAULT NULL,
  `stock` INT UNSIGNED NOT NULL DEFAULT 0,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`product_id`) REFERENCES `products`(`id`) ON DELETE CASCADE,
  INDEX `idx_variants_sku` (`sku`),
  INDEX `idx_variants_product` (`product_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `product_images`;
CREATE TABLE `product_images` (
  `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `product_id` BIGINT UNSIGNED NOT NULL,
  `image_path` VARCHAR(255) NOT NULL,
  `is_primary` TINYINT(1) DEFAULT 0,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`product_id`) REFERENCES `products`(`id`) ON DELETE CASCADE,
  INDEX `idx_images_product_primary` (`product_id`, `is_primary`, `id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. SHOPPING CART
DROP TABLE IF EXISTS `carts`;
CREATE TABLE `carts` (
  `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `user_id` BIGINT UNSIGNED DEFAULT NULL COMMENT 'NULL untuk guest (session)',
  `session_id` VARCHAR(100) DEFAULT NULL COMMENT 'Untuk guest cart',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX `idx_carts_user` (`user_id`),
  INDEX `idx_carts_session` (`session_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `cart_items`;
CREATE TABLE `cart_items` (
  `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `cart_id` BIGINT UNSIGNED NOT NULL,
  `product_variant_id` BIGINT UNSIGNED NOT NULL,
  `quantity` INT UNSIGNED NOT NULL DEFAULT 1,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`cart_id`) REFERENCES `carts`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`product_variant_id`) REFERENCES `product_variants`(`id`) ON DELETE CASCADE,
  INDEX `idx_cart_items_cart` (`cart_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. ORDERS & CHECKOUT
DROP TABLE IF EXISTS `orders`;
CREATE TABLE `orders` (
  `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `order_number` VARCHAR(50) NOT NULL UNIQUE COMMENT 'INV-YYYYMMDD-XXXX',
  `user_id` BIGINT UNSIGNED DEFAULT NULL,
  `subtotal` DECIMAL(15,0) NOT NULL,
  `shipping_cost` DECIMAL(15,0) NOT NULL,
  `payment_method` VARCHAR(20) DEFAULT NULL,
  `total_amount` DECIMAL(15,0) NOT NULL,
  `order_status` ENUM('pending', 'processing', 'shipped', 'completed', 'cancelled') DEFAULT 'pending',
  `payment_status` ENUM('pending', 'paid', 'failed', 'expired', 'refunded') DEFAULT 'pending',
  `expires_at` DATETIME DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX `idx_orders_number` (`order_number`),
  INDEX `idx_orders_user` (`user_id`),
  INDEX `idx_orders_status` (`order_status`, `payment_status`),
  INDEX `idx_orders_expiry` (`payment_status`, `expires_at`),
  INDEX `idx_orders_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `order_items`;
CREATE TABLE `order_items` (
  `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `order_id` BIGINT UNSIGNED NOT NULL,
  `product_variant_id` BIGINT UNSIGNED NOT NULL,
  `product_name_snapshot` VARCHAR(200) NOT NULL,
  `variant_name_snapshot` VARCHAR(100) NOT NULL,
  `sku_snapshot` VARCHAR(100) NOT NULL,
  `price_snapshot` DECIMAL(15,0) NOT NULL,
  `quantity` INT UNSIGNED NOT NULL,
  `weight_gram_snapshot` INT UNSIGNED NOT NULL,
  FOREIGN KEY (`order_id`) REFERENCES `orders`(`id`) ON DELETE CASCADE,
  INDEX `idx_order_items_order` (`order_id`),
  INDEX `idx_order_items_variant` (`product_variant_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `order_addresses`;
CREATE TABLE `order_addresses` (
  `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `order_id` BIGINT UNSIGNED NOT NULL UNIQUE,
  `recipient_name` VARCHAR(100) NOT NULL,
  `phone` VARCHAR(20) NOT NULL,
  `area_id` VARCHAR(100) DEFAULT NULL,
  `area_name` VARCHAR(255) DEFAULT NULL,
  `province` VARCHAR(100) NOT NULL,
  `city` VARCHAR(100) NOT NULL,
  `district` VARCHAR(100) NOT NULL,
  `postal_code` VARCHAR(10) NOT NULL,
  `address_detail` TEXT NOT NULL,
  `notes` TEXT DEFAULT NULL,
  FOREIGN KEY (`order_id`) REFERENCES `orders`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `order_status_histories`;
CREATE TABLE `order_status_histories` (
  `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `order_id` BIGINT UNSIGNED NOT NULL,
  `old_status` VARCHAR(50) DEFAULT NULL,
  `new_status` VARCHAR(50) NOT NULL,
  `notes` TEXT DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`order_id`) REFERENCES `orders`(`id`) ON DELETE CASCADE,
  INDEX `idx_history_order` (`order_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 5. PAYMENTS (Duitku)
DROP TABLE IF EXISTS `payments`;
CREATE TABLE `payments` (
  `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `order_id` BIGINT UNSIGNED NOT NULL UNIQUE,
  `payment_gateway` VARCHAR(50) NOT NULL DEFAULT 'duitku',
  `gateway_transaction_id` VARCHAR(100) DEFAULT NULL,
  `payment_url` VARCHAR(500) DEFAULT NULL,
  `payment_method` VARCHAR(50) DEFAULT NULL,
  `payment_name` VARCHAR(100) DEFAULT NULL,
  `payment_image` VARCHAR(255) DEFAULT NULL,
  `va_number` VARCHAR(50) DEFAULT NULL,
  `qr_string` TEXT DEFAULT NULL,
  `amount` DECIMAL(15,0) NOT NULL,
  `status` ENUM('pending', 'paid', 'failed', 'expired', 'refunded') DEFAULT 'pending',
  `paid_at` DATETIME DEFAULT NULL,
  `callback_data` JSON DEFAULT NULL COMMENT 'Menyimpan payload callback untuk audit',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`order_id`) REFERENCES `orders`(`id`) ON DELETE CASCADE,
  INDEX `idx_payments_gateway_id` (`gateway_transaction_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `payment_callbacks`;
CREATE TABLE `payment_callbacks` (
  `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `payment_id` BIGINT UNSIGNED DEFAULT NULL,
  `order_id` BIGINT UNSIGNED DEFAULT NULL,
  `gateway` VARCHAR(50) NOT NULL DEFAULT 'duitku',
  `merchant_order_id` VARCHAR(100) DEFAULT NULL,
  `reference` VARCHAR(100) DEFAULT NULL,
  `result_code` VARCHAR(20) DEFAULT NULL,
  `amount` DECIMAL(15,0) DEFAULT NULL,
  `payload` JSON NOT NULL,
  `is_valid` TINYINT(1) NOT NULL DEFAULT 0,
  `processed_at` DATETIME DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`payment_id`) REFERENCES `payments`(`id`) ON DELETE SET NULL,
  FOREIGN KEY (`order_id`) REFERENCES `orders`(`id`) ON DELETE SET NULL,
  INDEX `idx_callbacks_order` (`order_id`),
  INDEX `idx_callbacks_reference` (`reference`),
  INDEX `idx_callbacks_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 6. SHIPPING (Biteship)
DROP TABLE IF EXISTS `shipments`;
CREATE TABLE `shipments` (
  `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `order_id` BIGINT UNSIGNED NOT NULL UNIQUE,
  `biteship_order_id` VARCHAR(100) DEFAULT NULL,
  `waybill_id` VARCHAR(100) DEFAULT NULL COMMENT 'Nomor Resi',
  `courier_company` VARCHAR(50) DEFAULT NULL,
  `courier_type` VARCHAR(50) DEFAULT NULL,
  `courier_name` VARCHAR(100) DEFAULT NULL,
  `service_name` VARCHAR(100) DEFAULT NULL,
  `estimate` VARCHAR(50) DEFAULT NULL,
  `shipping_price` DECIMAL(15,0) NOT NULL,
  `status` VARCHAR(50) DEFAULT 'pending',
  `tracking_data` JSON DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`order_id`) REFERENCES `orders`(`id`) ON DELETE CASCADE,
  INDEX `idx_shipments_waybill` (`waybill_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `shipment_tracking_histories`;
CREATE TABLE `shipment_tracking_histories` (
  `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `shipment_id` BIGINT UNSIGNED NOT NULL,
  `order_id` BIGINT UNSIGNED NOT NULL,
  `event` VARCHAR(50) NOT NULL,
  `status` VARCHAR(50) DEFAULT NULL,
  `waybill_id` VARCHAR(100) DEFAULT NULL,
  `price` DECIMAL(15,0) DEFAULT NULL,
  `payload` JSON DEFAULT NULL,
  `event_hash` CHAR(64) NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY `uq_tracking_event_hash` (`event_hash`),
  FOREIGN KEY (`shipment_id`) REFERENCES `shipments`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`order_id`) REFERENCES `orders`(`id`) ON DELETE CASCADE,
  INDEX `idx_tracking_shipment` (`shipment_id`, `id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 7. CMS & SETTINGS
DROP TABLE IF EXISTS `store_settings`;
CREATE TABLE `store_settings` (
  `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `setting_key` VARCHAR(100) NOT NULL UNIQUE,
  `setting_value` TEXT NOT NULL,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 8. STOCK RESERVATIONS
DROP TABLE IF EXISTS `stock_reservations`;
CREATE TABLE `stock_reservations` (
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

-- 9. ADMIN ACTIVITY LOGS
CREATE TABLE IF NOT EXISTS `admin_activity_logs` (
  `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `admin_id` BIGINT UNSIGNED DEFAULT NULL,
  `action` VARCHAR(50) NOT NULL,
  `entity_type` VARCHAR(50) NOT NULL,
  `entity_id` BIGINT UNSIGNED DEFAULT NULL,
  `description` VARCHAR(255) DEFAULT NULL,
  `ip_address` VARCHAR(45) DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`admin_id`) REFERENCES `users`(`id`) ON DELETE SET NULL,
  INDEX `idx_log_entity` (`entity_type`, `entity_id`),
  INDEX `idx_log_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 10. BANNERS
CREATE TABLE IF NOT EXISTS `banners` (
  `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `title` VARCHAR(150) NOT NULL,
  `subtitle` VARCHAR(255) DEFAULT NULL,
  `image_path` VARCHAR(255) NOT NULL,
  `link_url` VARCHAR(255) DEFAULT NULL,
  `sort_order` INT NOT NULL DEFAULT 0,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX `idx_banners_active` (`is_active`, `sort_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 11. PAGES
CREATE TABLE IF NOT EXISTS `pages` (
  `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `slug` VARCHAR(100) NOT NULL UNIQUE,
  `title` VARCHAR(150) NOT NULL,
  `content` MEDIUMTEXT DEFAULT NULL,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 12. RATE LIMITS
CREATE TABLE IF NOT EXISTS `rate_limits` (
  `rl_key` CHAR(64) NOT NULL PRIMARY KEY,
  `hits` INT UNSIGNED NOT NULL DEFAULT 0,
  `window_start` INT UNSIGNED NOT NULL,
  INDEX `idx_rate_window` (`window_start`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;

-- SEED PAGES
INSERT IGNORE INTO `pages` (`slug`, `title`, `content`) VALUES
('tentang-kami', 'Tentang Kami', '<p>Ceritakan tentang toko Anda di sini.</p>'),
('kontak', 'Kontak', '<p>Tulis alamat, nomor WhatsApp, dan jam layanan toko Anda di sini.</p>'),
('kebijakan-privasi', 'Kebijakan Privasi', '<p>Tulis kebijakan privasi toko Anda di sini.</p>'),
('syarat-ketentuan', 'Syarat dan Ketentuan', '<p>Tulis syarat dan ketentuan toko Anda di sini.</p>');

-- ==========================================
-- SEED DATA (Data Demonstrasi)
-- ==========================================

-- 2. Categories
INSERT INTO `categories` (`name`, `slug`, `description`) VALUES
('Pakaian Pria', 'pakaian-pria', 'Koleksi pakaian pria berkualitas'),
('Elektronik', 'elektronik', 'Gadget dan aksesoris elektronik');

-- 3. Products
INSERT INTO `products` (`category_id`, `name`, `slug`, `description`, `weight_gram`, `length_cm`, `width_cm`, `height_cm`) VALUES
(1, 'Kaos Polos Premium', 'kaos-polos-premium', 'Kaos katun combed 30s yang nyaman dipakai sehari-hari.', 200, 20, 15, 2),
(2, 'Powerbank 10000mAh', 'powerbank-10000mah', 'Powerbank fast charging dengan kapasitas 10000mAh.', 350, 15, 10, 3);

-- 4. Product Variants
INSERT INTO `product_variants` (`product_id`, `sku`, `variant_name`, `price`, `discount_price`, `stock`) VALUES
(1, 'KAOS-PTH-M', 'Putih - M', 85000, 75000, 50),
(1, 'KAOS-PTH-L', 'Putih - L', 85000, 75000, 45),
(1, 'KAOS-HTM-M', 'Hitam - M', 85000, NULL, 60),
(2, 'PWB-10K-BLK', 'Hitam', 150000, 135000, 30);

-- 5. Product Images
INSERT INTO `product_images` (`product_id`, `image_path`, `is_primary`) VALUES
(1, '/uploads/products/kaos-putih.jpg', 1),
(2, '/uploads/products/powerbank.jpg', 1);

-- 6. Store Settings
INSERT INTO `store_settings` (`setting_key`, `setting_value`) VALUES
('store_name', 'RekanWeb Official Store'),
('origin_name', 'Gudang RekanWeb'),
('origin_phone', '081234567890'),
('origin_address', 'Jl. Teknologi No. 10, Kawasan Industri'),
('origin_province', 'DKI Jakarta'),
('origin_city', 'Jakarta Selatan'),
('origin_district', 'Kebayoran Baru'),
('origin_postal_code', '12110');
