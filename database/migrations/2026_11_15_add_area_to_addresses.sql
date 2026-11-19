ALTER TABLE `user_addresses`
  ADD COLUMN `area_id` VARCHAR(100) DEFAULT NULL AFTER `phone`,
  ADD COLUMN `area_name` VARCHAR(255) DEFAULT NULL AFTER `area_id`;

ALTER TABLE `order_addresses`
  ADD COLUMN `area_id` VARCHAR(100) DEFAULT NULL AFTER `phone`,
  ADD COLUMN `area_name` VARCHAR(255) DEFAULT NULL AFTER `area_id`;