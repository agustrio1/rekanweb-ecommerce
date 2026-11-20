
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
-- REKANWEB COMMERCE
-- SEED DATA: GADGET ACCESSORIES STORE
-- ==========================================

-- 1. CATEGORIES
INSERT INTO categories (name, slug, description, is_active, is_featured) VALUES
('Charger & Adaptor', 'charger-adaptor', 'Charger smartphone, adaptor fast charging, dan kepala charger.', 1, 1),
('Kabel Data', 'kabel-data', 'Kabel USB Type-C, Lightning, Micro USB, dan kabel fast charging.', 1, 1),
('Powerbank', 'powerbank', 'Powerbank portable untuk berbagai kebutuhan pengisian daya.', 1, 1),
('Audio & Earphone', 'audio-earphone', 'Earphone, TWS, headset, dan perangkat audio portable.', 1, 1),
('Holder & Stand', 'holder-stand', 'Holder smartphone, stand meja, dan aksesoris penyangga gadget.', 1, 0),
('Pelindung Gadget', 'pelindung-gadget', 'Tempered glass, casing, dan perlindungan smartphone.', 1, 1),
('Aksesoris Komputer', 'aksesoris-komputer', 'Mouse, keyboard, USB hub, dan aksesoris komputer.', 1, 0),
('Storage & OTG', 'storage-otg', 'Flashdisk, card reader, dan perangkat penyimpanan portable.', 1, 0);

-- 2. PRODUCTS
INSERT INTO products
(category_id, name, slug, description, weight_gram, length_cm, width_cm, height_cm)
VALUES

(
 (SELECT id FROM categories WHERE slug = 'charger-adaptor'),
 'Charger Fast Charging 20W USB-C',
 'charger-fast-charging-20w',
 'Adaptor charger USB-C 20W dengan teknologi fast charging. Cocok untuk smartphone yang mendukung pengisian daya USB-C.',
 120, 10, 7, 4
),
(
 (SELECT id FROM categories WHERE slug = 'charger-adaptor'),
 'Charger GaN Fast Charging 65W',
 'charger-gan-fast-charging-65w',
 'Charger GaN 65W dengan desain compact, mendukung pengisian daya laptop, tablet, dan smartphone.',
 180, 12, 8, 5
),
(
 (SELECT id FROM categories WHERE slug = 'charger-adaptor'),
 'Charger Wireless 15W',
 'charger-wireless-15w',
 'Wireless charging pad dengan desain minimalis dan dukungan pengisian daya hingga 15W.',
 150, 12, 12, 3
),
(
 (SELECT id FROM categories WHERE slug = 'kabel-data'),
 'Kabel USB-C to USB-C 100W',
 'kabel-usbc-usbc-100w',
 'Kabel USB-C to USB-C dengan dukungan pengisian daya hingga 100W dan transfer data.',
 80, 15, 10, 3
),
(
 (SELECT id FROM categories WHERE slug = 'kabel-data'),
 'Kabel USB-A to USB-C Fast Charging',
 'kabel-usba-usbc-fast-charging',
 'Kabel USB-A ke USB-C untuk pengisian daya dan transfer data smartphone.',
 65, 12, 9, 3
),
(
 (SELECT id FROM categories WHERE slug = 'kabel-data'),
 'Kabel Lightning Fast Charging',
 'kabel-lightning-fast-charging',
 'Kabel konektor Lightning untuk perangkat Apple yang kompatibel.',
 60, 12, 9, 3
),
(
 (SELECT id FROM categories WHERE slug = 'kabel-data'),
 'Kabel 3 in 1 Multifungsi',
 'kabel-3-in-1-multifungsi',
 'Kabel pengisian daya multifungsi dengan konektor USB-C, Lightning, dan Micro USB.',
 100, 15, 10, 4
),
(
 (SELECT id FROM categories WHERE slug = 'powerbank'),
 'Powerbank 10000mAh Slim',
 'powerbank-10000mah-slim',
 'Powerbank kapasitas 10000mAh dengan desain tipis dan portable untuk penggunaan sehari-hari.',
 250, 16, 9, 3
),
(
 (SELECT id FROM categories WHERE slug = 'powerbank'),
 'Powerbank 20000mAh Fast Charging',
 'powerbank-20000mah-fast-charging',
 'Powerbank berkapasitas 20000mAh dengan dukungan fast charging dan beberapa port output.',
 420, 18, 10, 4
),
(
 (SELECT id FROM categories WHERE slug = 'powerbank'),
 'Powerbank Magnetic Wireless',
 'powerbank-magnetic-wireless',
 'Powerbank magnetic wireless dengan desain compact untuk smartphone yang kompatibel.',
 230, 12, 8, 3
),
(
 (SELECT id FROM categories WHERE slug = 'audio-earphone'),
 'TWS Bluetooth 5.3',
 'tws-bluetooth-53',
 'True wireless stereo dengan koneksi Bluetooth 5.3, charging case, dan desain ergonomis.',
 120, 10, 8, 5
),
(
 (SELECT id FROM categories WHERE slug = 'audio-earphone'),
 'Earphone USB-C Hi-Fi',
 'earphone-usbc-hifi',
 'Earphone kabel dengan konektor USB-C dan desain in-ear untuk kebutuhan musik dan komunikasi.',
 45, 12, 8, 3
),
(
 (SELECT id FROM categories WHERE slug = 'audio-earphone'),
 'Headset Wireless ANC',
 'headset-wireless-anc',
 'Headset wireless dengan fitur active noise cancellation dan desain over-ear.',
 280, 22, 20, 10
),
(
 (SELECT id FROM categories WHERE slug = 'holder-stand'),
 'Holder HP Adjustable Desk',
 'holder-hp-adjustable-desk',
 'Stand smartphone meja dengan sudut kemiringan yang dapat disesuaikan.',
 180, 15, 12, 5
),
(
 (SELECT id FROM categories WHERE slug = 'holder-stand'),
 'Car Phone Holder Dashboard',
 'car-phone-holder-dashboard',
 'Holder smartphone untuk dashboard mobil dengan sistem penjepit yang praktis.',
 220, 15, 12, 10
),
(
 (SELECT id FROM categories WHERE slug = 'pelindung-gadget'),
 'Tempered Glass Full Cover',
 'tempered-glass-full-cover',
 'Pelindung layar tempered glass full cover untuk smartphone yang kompatibel.',
 40, 18, 10, 2
),
(
 (SELECT id FROM categories WHERE slug = 'pelindung-gadget'),
 'Silicone Case Premium',
 'silicone-case-premium',
 'Casing smartphone berbahan silikon dengan desain minimalis dan perlindungan tambahan.',
 70, 18, 10, 3
),
(
 (SELECT id FROM categories WHERE slug = 'aksesoris-komputer'),
 'Wireless Mouse Silent Click',
 'wireless-mouse-silent-click',
 'Mouse wireless dengan silent click, desain ergonomis, dan koneksi USB receiver.',
 130, 13, 9, 5
),
(
 (SELECT id FROM categories WHERE slug = 'aksesoris-komputer'),
 'USB Hub 4 Port USB 3.0',
 'usb-hub-4-port-usb-30',
 'USB hub dengan empat port USB 3.0 untuk menambah konektivitas laptop dan komputer.',
 110, 12, 8, 3
),
(
 (SELECT id FROM categories WHERE slug = 'storage-otg'),
 'Flashdisk USB 3.0 64GB',
 'flashdisk-usb-30-64gb',
 'Flashdisk USB 3.0 kapasitas 64GB untuk penyimpanan dan pemindahan data.',
 25, 8, 3, 2
);

-- 3. PRODUCT VARIANTS
INSERT INTO product_variants
(product_id, sku, variant_name, price, discount_price, stock)
VALUES

-- Charger
((SELECT id FROM products WHERE slug = 'charger-fast-charging-20w'), 'CHG-20W-WHT', 'Putih', 149000, 119000, 45),
((SELECT id FROM products WHERE slug = 'charger-fast-charging-20w'), 'CHG-20W-BLK', 'Hitam', 149000, NULL, 30),

((SELECT id FROM products WHERE slug = 'charger-gan-fast-charging-65w'), 'GAN-65W-WHT', 'Putih', 399000, 349000, 20),
((SELECT id FROM products WHERE slug = 'charger-gan-fast-charging-65w'), 'GAN-65W-BLK', 'Hitam', 399000, NULL, 15),

((SELECT id FROM products WHERE slug = 'charger-wireless-15w'), 'WCH-15W-BLK', 'Hitam', 179000, 149000, 25),

-- Kabel
((SELECT id FROM products WHERE slug = 'kabel-usbc-usbc-100w'), 'CBL-C2C-1M', '1 Meter', 89000, 69000, 60),
((SELECT id FROM products WHERE slug = 'kabel-usbc-usbc-100w'), 'CBL-C2C-2M', '2 Meter', 109000, 89000, 40),

((SELECT id FROM products WHERE slug = 'kabel-usba-usbc-fast-charging'), 'CBL-A2C-1M', '1 Meter', 49000, 39000, 70),
((SELECT id FROM products WHERE slug = 'kabel-usba-usbc-fast-charging'), 'CBL-A2C-2M', '2 Meter', 69000, NULL, 35),

((SELECT id FROM products WHERE slug = 'kabel-lightning-fast-charging'), 'CBL-LGT-1M', '1 Meter', 79000, 59000, 40),

((SELECT id FROM products WHERE slug = 'kabel-3-in-1-multifungsi'), 'CBL-3IN1-BLK', 'Hitam', 99000, 79000, 35),

-- Powerbank
((SELECT id FROM products WHERE slug = 'powerbank-10000mah-slim'), 'PB-10K-BLK', 'Hitam', 199000, 169000, 35),
((SELECT id FROM products WHERE slug = 'powerbank-10000mah-slim'), 'PB-10K-WHT', 'Putih', 199000, NULL, 25),

((SELECT id FROM products WHERE slug = 'powerbank-20000mah-fast-charging'), 'PB-20K-BLK', 'Hitam', 329000, 289000, 25),

((SELECT id FROM products WHERE slug = 'powerbank-magnetic-wireless'), 'PB-MAG-WHT', 'Putih', 299000, 259000, 20),
((SELECT id FROM products WHERE slug = 'powerbank-magnetic-wireless'), 'PB-MAG-BLK', 'Hitam', 299000, NULL, 20),

-- Audio
((SELECT id FROM products WHERE slug = 'tws-bluetooth-53'), 'TWS-53-WHT', 'Putih', 249000, 199000, 30),
((SELECT id FROM products WHERE slug = 'tws-bluetooth-53'), 'TWS-53-BLK', 'Hitam', 249000, 199000, 25),

((SELECT id FROM products WHERE slug = 'earphone-usbc-hifi'), 'EAR-USBC-BLK', 'Hitam', 89000, 69000, 45),

((SELECT id FROM products WHERE slug = 'headset-wireless-anc'), 'HDP-ANC-BLK', 'Hitam', 599000, 529000, 12),

-- Holder
((SELECT id FROM products WHERE slug = 'holder-hp-adjustable-desk'), 'HLD-DSK-SLV', 'Silver', 89000, 69000, 35),
((SELECT id FROM products WHERE slug = 'holder-hp-adjustable-desk'), 'HLD-DSK-BLK', 'Hitam', 89000, NULL, 30),

((SELECT id FROM products WHERE slug = 'car-phone-holder-dashboard'), 'HLD-CAR-BLK', 'Hitam', 119000, 99000, 25),

-- Protection
((SELECT id FROM products WHERE slug = 'tempered-glass-full-cover'), 'TGL-IP15', 'iPhone 15', 49000, 35000, 30),
((SELECT id FROM products WHERE slug = 'tempered-glass-full-cover'), 'TGL-S24', 'Samsung S24', 49000, 35000, 30),

((SELECT id FROM products WHERE slug = 'silicone-case-premium'), 'CASE-IP15-BLK', 'iPhone 15 - Hitam', 79000, 59000, 20),
((SELECT id FROM products WHERE slug = 'silicone-case-premium'), 'CASE-S24-BLK', 'Samsung S24 - Hitam', 79000, 59000, 20),

-- Computer accessories
((SELECT id FROM products WHERE slug = 'wireless-mouse-silent-click'), 'MSE-SLNT-BLK', 'Hitam', 129000, 99000, 30),
((SELECT id FROM products WHERE slug = 'usb-hub-4-port-usb-30'), 'USBHUB-4P', '4 Port', 159000, 129000, 25),

-- Storage
((SELECT id FROM products WHERE slug = 'flashdisk-usb-30-64gb'), 'FD-64GB', '64GB', 99000, 79000, 40);

-- 4. PRODUCT IMAGES
INSERT INTO product_images (product_id, image_path, is_primary)
VALUES
((SELECT id FROM products WHERE slug = 'charger-fast-charging-20w'), '/uploads/products/charger-20w.jpg', 1),
((SELECT id FROM products WHERE slug = 'charger-gan-fast-charging-65w'), '/uploads/products/charger-gan-65w.jpg', 1),
((SELECT id FROM products WHERE slug = 'charger-wireless-15w'), '/uploads/products/wireless-charger.jpg', 1),
((SELECT id FROM products WHERE slug = 'kabel-usbc-usbc-100w'), '/uploads/products/kabel-usbc-100w.jpg', 1),
((SELECT id FROM products WHERE slug = 'kabel-usba-usbc-fast-charging'), '/uploads/products/kabel-usbc.jpg', 1),
((SELECT id FROM products WHERE slug = 'kabel-lightning-fast-charging'), '/uploads/products/kabel-lightning.jpg', 1),
((SELECT id FROM products WHERE slug = 'kabel-3-in-1-multifungsi'), '/uploads/products/kabel-3in1.jpg', 1),
((SELECT id FROM products WHERE slug = 'powerbank-10000mah-slim'), '/uploads/products/powerbank-10000.jpg', 1),
((SELECT id FROM products WHERE slug = 'powerbank-20000mah-fast-charging'), '/uploads/products/powerbank-20000.jpg', 1),
((SELECT id FROM products WHERE slug = 'powerbank-magnetic-wireless'), '/uploads/products/powerbank-magnetic.jpg', 1),
((SELECT id FROM products WHERE slug = 'tws-bluetooth-53'), '/uploads/products/tws-bluetooth.jpg', 1),
((SELECT id FROM products WHERE slug = 'earphone-usbc-hifi'), '/uploads/products/earphone-usbc.jpg', 1),
((SELECT id FROM products WHERE slug = 'headset-wireless-anc'), '/uploads/products/headset-anc.jpg', 1),
((SELECT id FROM products WHERE slug = 'holder-hp-adjustable-desk'), '/uploads/products/holder-desk.jpg', 1),
((SELECT id FROM products WHERE slug = 'car-phone-holder-dashboard'), '/uploads/products/car-holder.jpg', 1),
((SELECT id FROM products WHERE slug = 'tempered-glass-full-cover'), '/uploads/products/tempered-glass.jpg', 1),
((SELECT id FROM products WHERE slug = 'silicone-case-premium'), '/uploads/products/silicone-case.jpg', 1),
((SELECT id FROM products WHERE slug = 'wireless-mouse-silent-click'), '/uploads/products/wireless-mouse.jpg', 1),
((SELECT id FROM products WHERE slug = 'usb-hub-4-port-usb-30'), '/uploads/products/usb-hub.jpg', 1),
((SELECT id FROM products WHERE slug = 'flashdisk-usb-30-64gb'), '/uploads/products/flashdisk-64gb.jpg', 1);

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
